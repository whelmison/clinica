<?php

namespace Clinic\Services;

use Clinic\Repositories\ScheduleRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

final class ScheduleService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ScheduleRepository $repository
    ) {
    }

    public function createAppointment(array $input, array $user): array
    {
        if (!$this->canManageAppointments($user)) {
            return ['ok' => false, 'message' => 'Somente secretaria, administrativo ou desenvolvedor podem agendar pacientes.'];
        }

        try {
            $data = $this->normalizeAppointment($input);
            $guide = $this->guideForRealizedAppointment($data, $input);
            $this->pdo->beginTransaction();
            $appointmentId = $this->repository->createAppointment($data);
            $appointment = $this->repository->findAppointment($appointmentId);

            if ($guide !== null && $appointment !== null) {
                $this->repository->createAttendanceFromAppointment($appointment, $guide);
            }

            $this->pdo->commit();

            return [
                'ok' => true,
                'message' => $guide !== null
                    ? 'Agendamento salvo e atendimento registrado com sucesso.'
                    : 'Agendamento salvo com sucesso.',
                'id' => $appointmentId,
                'whatsapp_url' => $appointment ? $this->buildWhatsappUrl($appointment) : null,
            ];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel salvar o agendamento.'];
        }
    }

    public function updateAppointment(int $appointmentId, array $input, array $user): array
    {
        if (!$this->canManageAppointments($user)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode editar agendamentos.'];
        }

        $existing = $this->repository->findAppointment($appointmentId);

        if (!$existing) {
            return ['ok' => false, 'message' => 'Agendamento nao encontrado.'];
        }

        try {
            $data = $this->normalizeAppointment($input, $appointmentId);
            $existingAttendance = $this->repository->findAttendanceByAppointment($appointmentId);
            $guide = $this->guideForRealizedAppointment($data, $input, $existingAttendance);
            $attendanceCreated = false;

            $this->pdo->beginTransaction();
            $this->repository->updateAppointment($appointmentId, $data);
            $appointment = $this->repository->findAppointment($appointmentId);

            if ($data['status'] === 'realizado' && $guide !== null && $appointment !== null) {
                if ($existingAttendance) {
                    $this->repository->updateAttendanceFromAppointment((int) $existingAttendance['id'], $appointment, $guide);
                } else {
                    $this->repository->createAttendanceFromAppointment($appointment, $guide);
                    $attendanceCreated = true;
                }
            } elseif ($existingAttendance) {
                $this->repository->deleteAttendanceForAppointment($appointmentId);
            }

            $this->pdo->commit();

            return [
                'ok' => true,
                'message' => $attendanceCreated
                    ? 'Agendamento atualizado e atendimento registrado com sucesso.'
                    : 'Agendamento atualizado com sucesso.',
                'id' => $appointmentId,
                'whatsapp_url' => $appointment ? $this->buildWhatsappUrl($appointment) : null,
            ];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel atualizar o agendamento.'];
        }
    }

    public function moveAppointment(int $appointmentId, array $input, array $user): array
    {
        if (!$this->canManageAppointments($user)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode mover agendamentos.'];
        }

        $existing = $this->repository->findAppointment($appointmentId);

        if (!$existing) {
            return ['ok' => false, 'message' => 'Agendamento nao encontrado.'];
        }

        $payload = [
            'cliente_id' => (int) ($existing['cliente_id'] ?? 0),
            'profissional_id' => (int) ($existing['profissional_id'] ?? 0),
            'servico_id' => (int) ($existing['servico_id'] ?? 0),
            'data_agendamento' => trim((string) ($input['data_agendamento'] ?? ($existing['data_agendamento'] ?? ''))),
            'hora_inicio' => trim((string) ($input['hora_inicio'] ?? ($existing['hora_inicio'] ?? ''))),
            'status' => trim((string) ($existing['status'] ?? 'agendado')),
            'observacoes' => trim((string) ($existing['observacoes'] ?? '')),
            'cliente_nome' => trim((string) (($existing['cliente_nome'] ?? '') ?: ($existing['paciente_nome'] ?? ''))),
            'cliente_telefone' => trim((string) (($existing['cliente_telefone'] ?? '') ?: ($existing['paciente_telefone'] ?? ''))),
        ];

        try {
            $data = $this->normalizeAppointment($payload, $appointmentId);
            $this->repository->updateAppointment($appointmentId, $data);

            return [
                'ok' => true,
                'message' => 'Agendamento remarcado com sucesso.',
                'id' => $appointmentId,
            ];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Nao foi possivel mover o agendamento.'];
        }
    }

    public function cancelAppointment(int $appointmentId, array $user): array
    {
        if (!$this->canManageAppointments($user)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode cancelar agendamentos.'];
        }

        $existing = $this->repository->findAppointment($appointmentId);

        if (!$existing) {
            return ['ok' => false, 'message' => 'Agendamento nao encontrado.'];
        }

        try {
            $this->pdo->beginTransaction();
            $this->repository->deleteAttendanceForAppointment($appointmentId);
            $this->repository->updateAppointmentStatus($appointmentId, 'cancelado');
            $this->pdo->commit();

            return ['ok' => true, 'message' => 'Agendamento cancelado e removido da agenda ativa.'];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel cancelar o agendamento.'];
        }
    }

    public function deleteAppointment(int $appointmentId, array $user): array
    {
        if (!$this->canManageAppointments($user)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode excluir agendamentos.'];
        }

        $existing = $this->repository->findAppointment($appointmentId);

        if (!$existing) {
            return ['ok' => false, 'message' => 'Agendamento nao encontrado.'];
        }

        try {
            $this->pdo->beginTransaction();
            $this->repository->deleteAttendanceForAppointment($appointmentId);
            $this->repository->deleteAppointment($appointmentId);
            $this->pdo->commit();

            return ['ok' => true, 'message' => 'Agendamento excluido com sucesso.'];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel excluir o agendamento.'];
        }
    }

    public function createAvailability(array $input, array $user): array
    {
        if (!$this->canManageAvailability($user, $input)) {
            return ['ok' => false, 'message' => 'Somente o proprio profissional, a secretaria autorizada, o administrativo ou o desenvolvedor podem liberar agenda.'];
        }

        try {
            $this->pdo->beginTransaction();
            $entries = $this->expandAvailabilityEntries($input);

            if ($entries === []) {
                throw new InvalidArgumentException('Selecione pelo menos um dia para liberar.');
            }

            $lastId = null;

            foreach ($entries as $entry) {
                $data = $this->normalizeAvailability($entry, $user);
                $lastId = $this->repository->createAvailability($data);
            }

            $this->pdo->commit();

            $createdCount = count($entries);

            return [
                'ok' => true,
                'message' => $createdCount > 1
                    ? 'Disponibilidade liberada em ' . $createdCount . ' periodo(s).'
                    : 'Disponibilidade liberada com sucesso.',
                'id' => $lastId,
            ];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            return ['ok' => false, 'message' => 'Nao foi possivel salvar a disponibilidade.'];
        }
    }

    public function updateAvailability(int $availabilityId, array $input, array $user): array
    {
        $existing = $this->repository->findAvailability($availabilityId);

        if (!$existing) {
            return ['ok' => false, 'message' => 'Disponibilidade nao encontrada.'];
        }

        if (!$this->canManageAvailability($user, $input, $existing)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode editar esta disponibilidade.'];
        }

        try {
            $data = $this->normalizeAvailability($input, $user, $existing);
            $this->repository->updateAvailability($availabilityId, $data);

            return ['ok' => true, 'message' => 'Disponibilidade atualizada com sucesso.', 'id' => $availabilityId];
        } catch (InvalidArgumentException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'Nao foi possivel atualizar a disponibilidade.'];
        }
    }

    public function deleteAvailability(int $availabilityId, array $user): array
    {
        $existing = $this->repository->findAvailability($availabilityId);

        if (!$existing) {
            return ['ok' => false, 'message' => 'Disponibilidade nao encontrada.'];
        }

        if (!$this->canManageAvailability($user, [], $existing)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode excluir esta disponibilidade.'];
        }

        $this->repository->deleteAvailability($availabilityId);

        return ['ok' => true, 'message' => 'Disponibilidade excluida com sucesso.'];
    }

    public function canManageAppointments(array $user): bool
    {
        return in_array($user['perfil'] ?? '', ['secretaria', 'administrativo', 'desenvolvedor'], true);
    }

    public function canManageAvailabilityForProfessional(array $user, int $professionalId): bool
    {
        if ($professionalId <= 0) {
            return false;
        }

        $profile = $user['perfil'] ?? '';

        if (in_array($profile, ['administrativo', 'desenvolvedor'], true)) {
            return true;
        }

        if ($profile === 'profissional') {
            return (int) ($user['profissional_id'] ?? 0) === $professionalId;
        }

        if ($profile !== 'secretaria') {
            return false;
        }

        $professional = $this->repository->findProfessional($professionalId);

        return !empty($professional['permite_secretaria_liberar_agenda']);
    }

    public function buildProfessionalAgendaWhatsappUrl(int $professionalId, string $period, string $referenceDate): ?string
    {
        $professional = $this->repository->findProfessional($professionalId);

        if (!$professional) {
            return null;
        }

        $phone = app_normalize_phone($professional['telefone'] ?? '');

        if ($phone === '') {
            return null;
        }

        [$startDate, $endDate] = $this->periodBounds($period, $referenceDate);
        $appointments = $this->repository->appointmentsBetween($professionalId, $startDate, $endDate);
        $message = $this->buildProfessionalAgendaMessage($professional, $appointments, $period, $startDate, $endDate);

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    private function canManageAvailability(array $user, array $input = [], ?array $existing = null): bool
    {
        $targetProfessionalId = $existing
            ? (int) $existing['profissional_id']
            : (int) ($input['profissional_id'] ?? 0);

        return $this->canManageAvailabilityForProfessional($user, $targetProfessionalId);
    }

    private function guideForRealizedAppointment(array $data, array $input, ?array $existingAttendance = null): ?array
    {
        if (($data['status'] ?? '') !== 'realizado') {
            return null;
        }

        $guideId = (int) ($input['guia_atendimento_id'] ?? 0);

        if ($guideId <= 0 && $existingAttendance) {
            $guideId = (int) ($existingAttendance['guia_id'] ?? 0);
        }

        if ($guideId <= 0) {
            throw new InvalidArgumentException('Informe a guia do atendimento para marcar como realizado.');
        }

        $ignoreAttendanceId = $existingAttendance ? (int) ($existingAttendance['id'] ?? 0) : null;
        $guide = $this->repository->findActiveGuideForAttendance(
            $guideId,
            (int) ($data['cliente_id'] ?? 0),
            (int) ($data['profissional_id'] ?? 0),
            $ignoreAttendanceId
        );

        if (!$guide) {
            throw new InvalidArgumentException('A guia selecionada nao esta ativa para este paciente/profissional ou ja finalizou.');
        }

        return $guide;
    }

    private function normalizeAppointment(array $input, ?int $ignoreId = null): array
    {
        $patientId = !empty($input['cliente_id']) ? (int) $input['cliente_id'] : null;
        $professionalId = (int) ($input['profissional_id'] ?? 0);
        $serviceId = (int) ($input['servico_id'] ?? 0);
        $date = trim((string) ($input['data_agendamento'] ?? ''));
        $start = trim((string) ($input['hora_inicio'] ?? ''));
        $status = trim((string) ($input['status'] ?? 'agendado'));
        $notes = trim((string) ($input['observacoes'] ?? ''));
        $clientName = trim((string) ($input['cliente_nome'] ?? ''));
        $clientPhone = trim((string) ($input['cliente_telefone'] ?? ''));

        if ($professionalId <= 0 || $serviceId <= 0 || $date === '' || $start === '') {
            throw new InvalidArgumentException('Preencha profissional, servico, data e horario.');
        }

        $dateTimestamp = strtotime($date);

        if ($dateTimestamp === false) {
            throw new InvalidArgumentException('Informe uma data valida para o agendamento.');
        }

        $date = date('Y-m-d', $dateTimestamp);

        if ($date < date('Y-m-d')) {
            throw new InvalidArgumentException('Nao e permitido agendar paciente em data anterior ao dia atual.');
        }

        if (!array_key_exists($status, app_schedule_statuses())) {
            throw new InvalidArgumentException('Selecione um status de agenda valido.');
        }

        if ($patientId === null || $patientId <= 0) {
            throw new InvalidArgumentException('Selecione um paciente cadastrado. Nao e permitido agendar sem cadastro.');
        }

        $patient = $this->repository->findPatient($patientId);
        if (!$patient) {
            throw new InvalidArgumentException('Paciente nao encontrado.');
        }

        $clientName = $patient['nome'];
        if ($clientPhone === '') {
            $clientPhone = (string) $patient['telefone'];
        }

        $service = $this->repository->findProfessionalService($professionalId, $serviceId);
        $duration = $service ? (int) ($service['tempo_minutos'] ?? 0) : null;

        if ($duration === null || $duration <= 0) {
            throw new InvalidArgumentException('O servico precisa estar vinculado ao profissional com duracao valida.');
        }

        if (($service['tipo_agendamento'] ?? 'individual') === 'grupo') {
            throw new InvalidArgumentException('Este servico usa agenda em grupo. Abra a agenda em grupo para agendar.');
        }

        $startDate = \DateTime::createFromFormat('H:i', $start) ?: \DateTime::createFromFormat('H:i:s', $start);

        if (!$startDate) {
            throw new InvalidArgumentException('Horario inicial invalido.');
        }

        $endDate = clone $startDate;
        $endDate->modify('+' . $duration . ' minutes');
        $startSql = $startDate->format('H:i:s');
        $endSql = $endDate->format('H:i:s');

        if (!$this->repository->hasAvailability($date, $startSql, $endSql, $professionalId)) {
            throw new InvalidArgumentException('O horario escolhido ainda nao foi liberado pelo profissional.');
        }

        if ($this->repository->hasConflict($date, $startSql, $endSql, $professionalId, $ignoreId)) {
            throw new InvalidArgumentException('Ja existe outro agendamento ocupando este horario.');
        }

        if ($this->repository->hasGroupConflict($date, $startSql, $endSql, $professionalId)) {
            throw new InvalidArgumentException('Ja existe uma agenda em grupo ocupando este horario.');
        }

        $patientConflict = $this->repository->findPatientScheduleConflict($date, $startSql, $endSql, $patientId, $ignoreId);

        if ($patientConflict !== null) {
            throw new InvalidArgumentException($this->patientScheduleConflictMessage($patientConflict));
        }

        return [
            'data_agendamento' => $date,
            'hora_inicio' => $startSql,
            'hora_fim' => $endSql,
            'profissional_id' => $professionalId,
            'servico_id' => $serviceId,
            'cliente_id' => $patientId,
            'cliente_nome' => $clientName,
            'cliente_telefone' => $clientPhone,
            'status' => $status,
            'observacoes' => $notes,
        ];
    }

    private function patientScheduleConflictMessage(array $conflict): string
    {
        $type = ($conflict['tipo_agenda'] ?? '') === 'grupo' ? 'sessao em grupo' : 'agenda individual';
        $professional = trim((string) ($conflict['profissional_nome'] ?? '')) ?: 'profissional nao informado';
        $service = trim((string) ($conflict['servico_nome'] ?? '')) ?: 'servico nao informado';
        $date = (string) ($conflict['data_agendamento'] ?? '');
        $dateLabel = $date !== '' ? app_date_br($date) : 'data nao informada';
        $start = substr((string) ($conflict['hora_inicio'] ?? ''), 0, 5);
        $end = substr((string) ($conflict['hora_fim'] ?? ''), 0, 5);
        $timeLabel = $start !== '' && $end !== '' ? $start . ' as ' . $end : 'horario nao informado';
        $status = trim((string) ($conflict['status'] ?? '')) ?: 'sem status';

        return 'Este paciente ja possui agendamento neste mesmo horario. Motivo: ja existe ' . $type
            . ' com ' . $professional
            . ', servico ' . $service
            . ', em ' . $dateLabel
            . ' das ' . $timeLabel
            . ' (status: ' . $status . ').';
    }

    private function normalizeAvailability(array $input, array $user, ?array $existing = null): array
    {
        $profile = $user['perfil'] ?? '';
        $professionalId = $profile === 'profissional'
            ? (int) ($user['profissional_id'] ?? 0)
            : (int) ($input['profissional_id'] ?? ($existing['profissional_id'] ?? 0));
        $date = trim((string) ($input['data_disponivel'] ?? ($existing['data_disponivel'] ?? '')));
        $start = trim((string) ($input['hora_inicio'] ?? ($existing['hora_inicio'] ?? '')));
        $end = trim((string) ($input['hora_fim'] ?? ($existing['hora_fim'] ?? '')));
        $notes = trim((string) ($input['observacoes'] ?? ($existing['observacoes'] ?? '')));
        $active = isset($input['ativo']) ? 1 : (int) ($existing['ativo'] ?? 1);

        if ($professionalId <= 0 || $date === '' || $start === '' || $end === '') {
            throw new InvalidArgumentException('Preencha profissional, data e faixa de horario.');
        }

        $startDate = \DateTime::createFromFormat('H:i', $start) ?: \DateTime::createFromFormat('H:i:s', $start);
        $endDate = \DateTime::createFromFormat('H:i', $end) ?: \DateTime::createFromFormat('H:i:s', $end);

        if (!$startDate || !$endDate || $endDate <= $startDate) {
            throw new InvalidArgumentException('Informe uma faixa de horario valida.');
        }

        $startSql = $startDate->format('H:i:s');
        $endSql = $endDate->format('H:i:s');
        $ignoreId = $existing ? (int) ($existing['id'] ?? 0) : null;

        if ($this->repository->hasAvailabilityConflict($date, $startSql, $endSql, $professionalId, $ignoreId)) {
            throw new InvalidArgumentException('Ja existe outra liberacao de agenda sobreposta neste horario.');
        }

        return [
            'profissional_id' => $professionalId,
            'data_disponivel' => $date,
            'hora_inicio' => $startSql,
            'hora_fim' => $endSql,
            'observacoes' => $notes,
            'ativo' => $active,
        ];
    }

    private function expandAvailabilityEntries(array $input): array
    {
        $scope = trim((string) ($input['abrangencia'] ?? 'data_unica'));
        $baseDate = trim((string) ($input['data_disponivel'] ?? ''));
        $monthReference = trim((string) ($input['mes_referencia'] ?? ''));

        if ($scope !== 'mes_inteiro' && $baseDate === '') {
            throw new InvalidArgumentException('Informe a data para liberar a agenda.');
        }

        $range = $this->resolveAvailabilityRange($input);
        $onlyBusinessDays = isset($input['somente_dias_uteis']);
        $dates = match ($scope) {
            'semana_inteira' => $this->weekDates($baseDate, $onlyBusinessDays),
            'mes_inteiro' => $this->monthDates($monthReference !== '' ? ($monthReference . '-01') : $baseDate, $onlyBusinessDays),
            default => [$baseDate],
        };

        $entries = [];

        foreach ($dates as $date) {
            $entry = $input;
            $entry['data_disponivel'] = $date;
            $entry['hora_inicio'] = $range['hora_inicio'];
            $entry['hora_fim'] = $range['hora_fim'];
            $entries[] = $entry;
        }

        return $entries;
    }

    private function resolveAvailabilityRange(array $input): array
    {
        $period = trim((string) ($input['periodo_liberacao'] ?? 'personalizado'));

        return match ($period) {
            'manha' => ['hora_inicio' => '07:00', 'hora_fim' => '12:00'],
            'tarde' => ['hora_inicio' => '13:00', 'hora_fim' => '18:00'],
            'dia_todo' => ['hora_inicio' => '07:00', 'hora_fim' => '22:00'],
            default => [
                'hora_inicio' => trim((string) ($input['hora_inicio'] ?? '')),
                'hora_fim' => trim((string) ($input['hora_fim'] ?? '')),
            ],
        };
    }

    private function monthDates(string $baseDate, bool $onlyBusinessDays): array
    {
        $start = \DateTime::createFromFormat('Y-m-d', date('Y-m-01', strtotime($baseDate)));
        $end = \DateTime::createFromFormat('Y-m-d', date('Y-m-t', strtotime($baseDate)));

        if (!$start || !$end) {
            throw new InvalidArgumentException('Informe uma data valida para repetir no mes.');
        }

        $dates = [];
        $cursor = clone $start;

        while ($cursor <= $end) {
            $dayOfWeek = (int) $cursor->format('N');

            if (!$onlyBusinessDays || $dayOfWeek <= 5) {
                $dates[] = $cursor->format('Y-m-d');
            }

            $cursor->modify('+1 day');
        }

        return $dates;
    }

    private function weekDates(string $baseDate, bool $onlyBusinessDays): array
    {
        $start = \DateTime::createFromFormat('Y-m-d', app_week_start($baseDate));

        if (!$start) {
            throw new InvalidArgumentException('Informe uma data valida para repetir na semana.');
        }

        $dates = [];

        for ($i = 0; $i < 7; $i++) {
            $cursor = clone $start;
            $cursor->modify('+' . $i . ' days');
            $dayOfWeek = (int) $cursor->format('N');

            if (!$onlyBusinessDays || $dayOfWeek <= 5) {
                $dates[] = $cursor->format('Y-m-d');
            }
        }

        return $dates;
    }

    private function buildProfessionalAgendaMessage(array $professional, array $appointments, string $period, string $startDate, string $endDate): string
    {
        $header = [
            'Ola, ' . ($professional['nome'] ?? 'profissional') . '!',
            'Segue sua agenda da clinica.',
            'Periodo: ' . $this->periodLabel($period, $startDate, $endDate),
            'Total de agendamentos: ' . count($appointments),
            '',
        ];

        if ($appointments === []) {
            $header[] = 'Nenhum agendamento encontrado neste periodo.';

            return implode("\n", $header);
        }

        $maxItems = match ($period) {
            'day' => 18,
            'week' => 24,
            'month' => 28,
            default => 20,
        };

        $lines = [];
        foreach (array_slice($appointments, 0, $maxItems) as $appointment) {
            $lines[] = sprintf(
                '%s %s - %s / %s',
                app_date_br($appointment['data_agendamento'] ?? ''),
                app_time_br($appointment['hora_inicio'] ?? ''),
                trim((string) ($appointment['paciente_nome'] ?? $appointment['cliente_nome'] ?? 'Paciente')),
                trim((string) ($appointment['servico_nome'] ?? 'Servico'))
            );
        }

        $remaining = count($appointments) - count($lines);
        if ($remaining > 0) {
            $lines[] = '+ ' . $remaining . ' agendamento(s) no periodo.';
        }

        return implode("\n", array_merge($header, $lines));
    }

    private function periodBounds(string $period, string $referenceDate): array
    {
        $timestamp = strtotime($referenceDate);
        $safeDate = $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');

        return match ($period) {
            'day' => [$safeDate, $safeDate],
            'month' => [
                date('Y-m-01', strtotime($safeDate)),
                date('Y-m-t', strtotime($safeDate)),
            ],
            default => [
                app_week_start($safeDate),
                date('Y-m-d', strtotime(app_week_start($safeDate) . ' +6 days')),
            ],
        };
    }

    private function periodLabel(string $period, string $startDate, string $endDate): string
    {
        return match ($period) {
            'day' => 'Dia ' . app_date_br($startDate),
            'month' => app_month_label(substr($startDate, 0, 7)),
            default => 'Semana de ' . app_date_br($startDate) . ' a ' . app_date_br($endDate),
        };
    }

    private function buildWhatsappUrl(array $appointment): ?string
    {
        $phone = app_normalize_phone($appointment['cliente_telefone'] ?? $appointment['paciente_telefone'] ?? '');

        if ($phone === '') {
            return null;
        }

        $patientName = $appointment['paciente_nome'] ?: $appointment['cliente_nome'];
        $date = app_date_br($appointment['data_agendamento']);
        $time = app_time_br($appointment['hora_inicio']);
        $professionalName = $appointment['profissional_nome'];
        $message = "Ola, {$patientName}! Seu agendamento foi confirmado.\nNome: {$patientName}\nData: {$date}\nHora: {$time}\nProfissional: {$professionalName}";

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
