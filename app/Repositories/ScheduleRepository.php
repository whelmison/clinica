<?php

namespace Clinic\Repositories;

use PDO;

final class ScheduleRepository
{
    private ?bool $attendanceAppointmentColumnExists = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    private function clinicId(): int
    {
        return app_active_clinic_id();
    }

    public function professionals(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, permite_secretaria_liberar_agenda FROM profissionais WHERE clinica_id = :clinic_id ORDER BY nome');
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    public function professionalsWithServices(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.id, p.nome, p.telefone, p.profissao, p.permite_secretaria_liberar_agenda
             FROM profissionais p
             INNER JOIN profissional_servico ps ON ps.profissional_id = p.id AND ps.clinica_id = p.clinica_id
             WHERE p.clinica_id = :clinic_id
             ORDER BY p.nome'
        );
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    public function patients(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, telefone, dia_preferencia, horario_preferencia
             FROM pacientes
             WHERE clinica_id = :clinic_id
             ORDER BY nome'
        );
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    public function servicesForProfessional(int $professionalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id,
                    s.nome,
                    COALESCE(ps.tempo_minutos, s.tempo_minutos) AS tempo_minutos,
                    COALESCE(s.tipo_agendamento, \'individual\') AS tipo_agendamento,
                    COALESCE(s.capacidade_agendamento, 1) AS capacidade_agendamento
             FROM profissional_servico ps
             INNER JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = ps.clinica_id
             WHERE ps.clinica_id = :clinic_id AND ps.profissional_id = :professional_id AND s.ativo = 1
             ORDER BY s.nome'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':professional_id' => $professionalId]);

        return $stmt->fetchAll();
    }

    public function findPatient(int $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, telefone, dia_preferencia, horario_preferencia
             FROM pacientes
             WHERE clinica_id = :clinic_id AND id = :id
             LIMIT 1'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $patientId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findProfessional(int $professionalId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, telefone, mensagem_padrao_whatsapp, permite_secretaria_liberar_agenda
             FROM profissionais
             WHERE clinica_id = :clinic_id AND id = :id
             LIMIT 1'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $professionalId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findServiceDuration(int $professionalId, int $serviceId): ?int
    {
        $service = $this->findProfessionalService($professionalId, $serviceId);

        return $service ? (int) $service['tempo_minutos'] : null;
    }

    public function findProfessionalService(int $professionalId, int $serviceId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id,
                    s.nome,
                    COALESCE(ps.tempo_minutos, s.tempo_minutos) AS tempo_minutos,
                    COALESCE(s.tipo_agendamento, \'individual\') AS tipo_agendamento,
                    COALESCE(s.capacidade_agendamento, 1) AS capacidade_agendamento
             FROM profissional_servico ps
             INNER JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = ps.clinica_id
             WHERE ps.clinica_id = :clinic_id
               AND ps.profissional_id = :professional_id
               AND ps.servico_id = :service_id
               AND s.ativo = 1
             LIMIT 1'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':service_id' => $serviceId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findActiveGuideForAttendance(int $guideId, int $patientId, int $professionalId, int $serviceId = 0, ?int $ignoreAttendanceId = null): ?array
    {
        $attendanceWhere = '';
        $clinicId = $this->clinicId();
        $params = [
            ':clinic_id' => $clinicId,
            ':attendance_clinic_id' => $clinicId,
            ':guide_id' => $guideId,
            ':patient_id' => $patientId,
            ':professional_id' => $professionalId,
        ];
        $serviceWhere = '';

        if ($serviceId > 0) {
            $serviceWhere = 'AND (g.servico_id = :service_id OR g.servico_id IS NULL)';
            $params[':service_id'] = $serviceId;
        }

        if ($ignoreAttendanceId !== null && $ignoreAttendanceId > 0) {
            $attendanceWhere = 'WHERE id <> :ignore_attendance_id';
            $params[':ignore_attendance_id'] = $ignoreAttendanceId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT g.id,
                    g.codigo,
                    g.paciente_id,
                    g.profissional_id,
                    g.servico_id,
                    g.autorizada,
                    g.total_sessoes,
                    COALESCE(pl.nome, \'\') AS plano_nome,
                    COALESCE(a.usadas, 0) AS usadas
             FROM guias g
             LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
             LEFT JOIN (
                SELECT guia_id, COUNT(*) AS usadas
                FROM atendimentos
                ' . ($attendanceWhere === '' ? 'WHERE clinica_id = :attendance_clinic_id' : $attendanceWhere . ' AND clinica_id = :attendance_clinic_id') . '
                GROUP BY guia_id
             ) a ON a.guia_id = g.id
             WHERE g.clinica_id = :clinic_id
               AND g.id = :guide_id
               AND g.paciente_id = :patient_id
               AND (g.profissional_id = :professional_id OR g.profissional_id IS NULL)
               ' . $serviceWhere . '
               AND g.autorizada = 1
              AND COALESCE(g.status_operacional, \'aguardando_autorizacao\') NOT IN (\'cancelada\', \'finalizada\')
             LIMIT 1'
        );
        $stmt->execute($params);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $total = (int) ($row['total_sessoes'] ?? 0);
        $used = (int) ($row['usadas'] ?? 0);

        if ($total <= 0 || $used >= $total) {
            return null;
        }

        return $row;
    }

    public function findAttendanceByAppointment(int $appointmentId): ?array
    {
        if (!$this->attendanceAppointmentColumnExists()) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, paciente_id, data, guia_id, agenda_id, status_atendimento
             FROM atendimentos
             WHERE clinica_id = :clinic_id AND agenda_id = :appointment_id
             LIMIT 1'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':appointment_id' => $appointmentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createAttendanceFromAppointment(array $appointment, array $guide): int
    {
        $hasAppointmentColumn = $this->attendanceAppointmentColumnExists();
        $sql = 'INSERT INTO atendimentos (clinica_id, paciente_id, data, tipo, pago, guia_id, status_atendimento'
            . ($hasAppointmentColumn ? ', agenda_id' : '')
            . ') VALUES (:clinic_id, :paciente_id, :data, :tipo, :pago, :guia_id, :status_atendimento'
            . ($hasAppointmentColumn ? ', :agenda_id' : '')
            . ')';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':paciente_id' => (int) ($appointment['cliente_id'] ?? 0),
            ':data' => (string) ($appointment['data_agendamento'] ?? date('Y-m-d')),
            ':tipo' => (string) ($guide['plano_nome'] ?? ''),
            ':pago' => 'Pendente',
            ':guia_id' => (int) ($guide['id'] ?? 0),
            ':status_atendimento' => 'Realizado',
        ];

        if ($hasAppointmentColumn) {
            $params[':agenda_id'] = (int) ($appointment['id'] ?? 0);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateAttendanceFromAppointment(int $attendanceId, array $appointment, array $guide): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE atendimentos
             SET paciente_id = :paciente_id,
                 data = :data,
                 tipo = :tipo,
                 guia_id = :guia_id
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $attendanceId,
            ':paciente_id' => (int) ($appointment['cliente_id'] ?? 0),
            ':data' => (string) ($appointment['data_agendamento'] ?? date('Y-m-d')),
            ':tipo' => (string) ($guide['plano_nome'] ?? ''),
            ':guia_id' => (int) ($guide['id'] ?? 0),
        ]);
    }

    public function deleteAttendanceForAppointment(int $appointmentId): void
    {
        if (!$this->attendanceAppointmentColumnExists()) {
            return;
        }

        $stmt = $this->pdo->prepare('DELETE FROM atendimentos WHERE clinica_id = :clinic_id AND agenda_id = :appointment_id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':appointment_id' => $appointmentId]);
    }

    public function hasConflict(string $date, string $startTime, string $endTime, int $professionalId, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM agenda
                WHERE clinica_id = :clinic_id
                  AND data_agendamento = :data_agendamento
                  AND profissional_id = :professional_id
                  AND status <> :status_cancelado
                  AND NOT (hora_fim <= :hora_inicio OR hora_inicio >= :hora_fim)';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':data_agendamento' => $date,
            ':professional_id' => $professionalId,
            ':status_cancelado' => 'cancelado',
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasGroupConflict(string $date, string $startTime, string $endTime, int $professionalId): bool
    {
        $tableStmt = $this->pdo->query("SHOW TABLES LIKE 'agenda_grupos'");
        if (!$tableStmt || !$tableStmt->fetchColumn()) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM agenda_grupos
             WHERE clinica_id = :clinic_id
               AND data_agendamento = :data_agendamento
               AND profissional_id = :professional_id
               AND NOT (hora_fim <= :hora_inicio OR hora_inicio >= :hora_fim)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':data_agendamento' => $date,
            ':professional_id' => $professionalId,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasPatientIndividualConflict(string $date, string $startTime, string $endTime, int $patientId, ?int $ignoreId = null): bool
    {
        if ($patientId <= 0) {
            return false;
        }

        $sql = 'SELECT COUNT(*)
                FROM agenda
                WHERE clinica_id = :clinic_id
                  AND data_agendamento = :data_agendamento
                  AND cliente_id = :patient_id
                  AND status <> :status_cancelado
                  AND NOT (hora_fim <= :hora_inicio OR hora_inicio >= :hora_fim)';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':data_agendamento' => $date,
            ':patient_id' => $patientId,
            ':status_cancelado' => 'cancelado',
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findPatientScheduleConflict(string $date, string $startTime, string $endTime, int $patientId, ?int $ignoreId = null, ?int $ignoreMemberId = null): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        $sql = 'SELECT a.id,
                       a.data_agendamento,
                       a.hora_inicio,
                       a.hora_fim,
                       a.status,
                       COALESCE(p.nome, \'\') AS profissional_nome,
                       COALESCE(s.nome, \'\') AS servico_nome
                FROM agenda a
                LEFT JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
                LEFT JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
                WHERE a.clinica_id = :clinic_id
                  AND a.data_agendamento = :data_agendamento
                  AND a.cliente_id = :patient_id
                  AND a.status <> :status_cancelado
                  AND NOT (a.hora_fim <= :hora_inicio OR a.hora_inicio >= :hora_fim)';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':data_agendamento' => $date,
            ':patient_id' => $patientId,
            ':status_cancelado' => 'cancelado',
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND a.id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $sql .= ' ORDER BY a.hora_inicio LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $individual = $stmt->fetch();

        if ($individual) {
            $individual['tipo_agenda'] = 'individual';

            return $individual;
        }

        $tableStmt = $this->pdo->query("SHOW TABLES LIKE 'agenda_grupos'");
        if (!$tableStmt || !$tableStmt->fetchColumn()) {
            return null;
        }

        $sql = 'SELECT gp.id,
                       g.data_agendamento,
                       g.hora_inicio,
                       g.hora_fim,
                       gp.status,
                       COALESCE(p.nome, \'\') AS profissional_nome,
                       COALESCE(s.nome, \'\') AS servico_nome
                FROM agenda_grupo_pacientes gp
                INNER JOIN agenda_grupos g ON g.id = gp.grupo_id AND g.clinica_id = gp.clinica_id
                LEFT JOIN profissionais p ON p.id = g.profissional_id AND p.clinica_id = g.clinica_id
                LEFT JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
                WHERE gp.clinica_id = :clinic_id
                  AND gp.paciente_id = :patient_id
                  AND gp.status <> :status_cancelado
                  AND g.data_agendamento = :data_agendamento
                  AND NOT (g.hora_fim <= :hora_inicio OR g.hora_inicio >= :hora_fim)';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':patient_id' => $patientId,
            ':status_cancelado' => 'cancelado',
            ':data_agendamento' => $date,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ];

        if ($ignoreMemberId !== null) {
            $sql .= ' AND gp.id <> :ignore_member_id';
            $params[':ignore_member_id'] = $ignoreMemberId;
        }

        $sql .= ' ORDER BY g.hora_inicio LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $group = $stmt->fetch();

        if ($group) {
            $group['tipo_agenda'] = 'grupo';

            return $group;
        }

        return null;
    }

    public function hasPatientGroupConflict(string $date, string $startTime, string $endTime, int $patientId, ?int $ignoreMemberId = null): bool
    {
        if ($patientId <= 0) {
            return false;
        }

        $tableStmt = $this->pdo->query("SHOW TABLES LIKE 'agenda_grupos'");
        if (!$tableStmt || !$tableStmt->fetchColumn()) {
            return false;
        }

        $sql = 'SELECT COUNT(*)
                FROM agenda_grupo_pacientes gp
                INNER JOIN agenda_grupos g ON g.id = gp.grupo_id AND g.clinica_id = gp.clinica_id
                WHERE gp.clinica_id = :clinic_id
                  AND gp.paciente_id = :patient_id
                  AND gp.status <> :status_cancelado
                  AND g.data_agendamento = :data_agendamento
                  AND NOT (g.hora_fim <= :hora_inicio OR g.hora_inicio >= :hora_fim)';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':patient_id' => $patientId,
            ':status_cancelado' => 'cancelado',
            ':data_agendamento' => $date,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ];

        if ($ignoreMemberId !== null) {
            $sql .= ' AND gp.id <> :ignore_member_id';
            $params[':ignore_member_id'] = $ignoreMemberId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasAvailability(string $date, string $startTime, string $endTime, int $professionalId, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM agenda_disponibilidade
                WHERE clinica_id = :clinic_id
                  AND profissional_id = :professional_id
                  AND data_disponivel = :data_disponivel
                  AND ativo = 1
                  AND hora_inicio <= :hora_inicio
                  AND hora_fim >= :hora_fim';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':data_disponivel' => $date,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasAvailabilityConflict(string $date, string $startTime, string $endTime, int $professionalId, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM agenda_disponibilidade
                WHERE clinica_id = :clinic_id
                  AND profissional_id = :professional_id
                  AND data_disponivel = :data_disponivel
                  AND ativo = 1
                  AND NOT (hora_fim <= :hora_inicio OR hora_inicio >= :hora_fim)';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':data_disponivel' => $date,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function findAppointment(int $appointmentId, ?int $scopeProfessionalId = null): ?array
    {
        $attendanceSelect = $this->attendanceAppointmentColumnExists()
            ? ', at.id AS atendimento_id, at.guia_id AS atendimento_guia_id'
            : ', NULL AS atendimento_id, NULL AS atendimento_guia_id';
        $attendanceJoin = $this->attendanceAppointmentColumnExists()
            ? ' LEFT JOIN atendimentos at ON at.agenda_id = a.id AND at.clinica_id = a.clinica_id'
            : '';
        $sql = 'SELECT a.*,
                       p.nome AS profissional_nome,
                       pa.nome AS paciente_nome,
                       pa.telefone AS paciente_telefone,
                       pa.dia_preferencia AS paciente_dia_preferencia,
                       pa.horario_preferencia AS paciente_horario_preferencia,
                       s.nome AS servico_nome
                       ' . $attendanceSelect . '
                FROM agenda a
                INNER JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
                INNER JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
                LEFT JOIN pacientes pa ON pa.id = a.cliente_id AND pa.clinica_id = a.clinica_id
                ' . $attendanceJoin . '
                WHERE a.clinica_id = :clinic_id AND a.id = :id';
        $params = [':clinic_id' => $this->clinicId(), ':id' => $appointmentId];

        if ($scopeProfessionalId !== null) {
            $sql .= ' AND a.profissional_id = :scope_professional_id';
            $params[':scope_professional_id'] = $scopeProfessionalId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createAppointment(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO agenda
                (clinica_id, data_agendamento, hora_inicio, hora_fim, profissional_id, servico_id, cliente_id, cliente_nome, cliente_telefone, status, observacoes)
             VALUES
                (:clinic_id, :data_agendamento, :hora_inicio, :hora_fim, :profissional_id, :servico_id, :cliente_id, :cliente_nome, :cliente_telefone, :status, :observacoes)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':data_agendamento' => $data['data_agendamento'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fim' => $data['hora_fim'],
            ':profissional_id' => $data['profissional_id'],
            ':servico_id' => $data['servico_id'],
            ':cliente_id' => $data['cliente_id'],
            ':cliente_nome' => $data['cliente_nome'],
            ':cliente_telefone' => $data['cliente_telefone'],
            ':status' => $data['status'],
            ':observacoes' => $data['observacoes'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateAppointment(int $appointmentId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE agenda
             SET data_agendamento = :data_agendamento,
                 hora_inicio = :hora_inicio,
                 hora_fim = :hora_fim,
                 profissional_id = :profissional_id,
                 servico_id = :servico_id,
                 cliente_id = :cliente_id,
                 cliente_nome = :cliente_nome,
                 cliente_telefone = :cliente_telefone,
                 status = :status,
                 observacoes = :observacoes
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $appointmentId,
            ':data_agendamento' => $data['data_agendamento'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fim' => $data['hora_fim'],
            ':profissional_id' => $data['profissional_id'],
            ':servico_id' => $data['servico_id'],
            ':cliente_id' => $data['cliente_id'],
            ':cliente_nome' => $data['cliente_nome'],
            ':cliente_telefone' => $data['cliente_telefone'],
            ':status' => $data['status'],
            ':observacoes' => $data['observacoes'],
        ]);
    }

    public function updateAppointmentStatus(int $appointmentId, string $status): void
    {
        $stmt = $this->pdo->prepare('UPDATE agenda SET status = :status WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $appointmentId,
            ':status' => $status,
        ]);
    }

    public function deleteAppointment(int $appointmentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM agenda WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $appointmentId]);
    }

    public function paginateAppointments(
        array $filters,
        int $page,
        int $perPage,
        ?int $scopeProfessionalId = null,
        string $path = 'secretaria_agenda.php',
        string $pageParam = 'appointment_page'
    ): array
    {
        [$whereSql, $params] = $this->buildAppointmentWhere($filters, $scopeProfessionalId);

        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM agenda a
             LEFT JOIN pacientes pa ON pa.id = a.cliente_id AND pa.clinica_id = a.clinica_id
             LEFT JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
             ' . $whereSql
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $paginationQuery = array_filter(
            $filters,
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 0 && $value !== '0'
        );
        $pagination = app_pagination($page, $perPage, $total, $path, $paginationQuery, $pageParam);

        $sql = 'SELECT a.*,
                       p.nome AS profissional_nome,
                       s.nome AS servico_nome,
                       COALESCE(pa.nome, a.cliente_nome) AS paciente_nome
                FROM agenda a
                INNER JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
                INNER JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
                LEFT JOIN pacientes pa ON pa.id = a.cliente_id AND pa.clinica_id = a.clinica_id
                ' . $whereSql . '
                ORDER BY a.data_agendamento DESC, a.hora_inicio ASC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'pagination' => $pagination,
        ];
    }

    public function findAvailability(int $availabilityId, ?int $scopeProfessionalId = null): ?array
    {
        $sql = 'SELECT ad.*, p.nome AS profissional_nome
                FROM agenda_disponibilidade ad
                INNER JOIN profissionais p ON p.id = ad.profissional_id AND p.clinica_id = ad.clinica_id
                WHERE ad.clinica_id = :clinic_id AND ad.id = :id';
        $params = [':clinic_id' => $this->clinicId(), ':id' => $availabilityId];

        if ($scopeProfessionalId !== null) {
            $sql .= ' AND ad.profissional_id = :scope_professional_id';
            $params[':scope_professional_id'] = $scopeProfessionalId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createAvailability(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO agenda_disponibilidade
                (clinica_id, profissional_id, data_disponivel, hora_inicio, hora_fim, observacoes, ativo)
             VALUES
                (:clinic_id, :profissional_id, :data_disponivel, :hora_inicio, :hora_fim, :observacoes, :ativo)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':profissional_id' => $data['profissional_id'],
            ':data_disponivel' => $data['data_disponivel'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fim' => $data['hora_fim'],
            ':observacoes' => $data['observacoes'],
            ':ativo' => $data['ativo'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function mergeAvailabilityRange(array $data): int
    {
        $ranges = $this->availabilityRangesTouching(
            (int) $data['profissional_id'],
            (string) $data['data_disponivel'],
            (string) $data['hora_inicio'],
            (string) $data['hora_fim']
        );

        if ($ranges === []) {
            return $this->createAvailability($data);
        }

        $mergedStart = (string) $data['hora_inicio'];
        $mergedEnd = (string) $data['hora_fim'];
        $keep = $ranges[0];

        foreach ($ranges as $range) {
            if (strtotime('2000-01-01 ' . $range['hora_inicio']) < strtotime('2000-01-01 ' . $mergedStart)) {
                $mergedStart = (string) $range['hora_inicio'];
            }

            if (strtotime('2000-01-01 ' . $range['hora_fim']) > strtotime('2000-01-01 ' . $mergedEnd)) {
                $mergedEnd = (string) $range['hora_fim'];
            }
        }

        $this->updateAvailability((int) $keep['id'], [
            'profissional_id' => (int) $data['profissional_id'],
            'data_disponivel' => (string) $data['data_disponivel'],
            'hora_inicio' => $mergedStart,
            'hora_fim' => $mergedEnd,
            'observacoes' => trim((string) ($data['observacoes'] ?? '')) !== ''
                ? (string) $data['observacoes']
                : (string) ($keep['observacoes'] ?? ''),
            'ativo' => 1,
        ]);

        foreach (array_slice($ranges, 1) as $range) {
            $this->deleteAvailability((int) $range['id']);
        }

        return (int) $keep['id'];
    }

    public function updateAvailability(int $availabilityId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE agenda_disponibilidade
             SET profissional_id = :profissional_id,
                 data_disponivel = :data_disponivel,
                 hora_inicio = :hora_inicio,
                 hora_fim = :hora_fim,
                 observacoes = :observacoes,
                 ativo = :ativo
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $availabilityId,
            ':profissional_id' => $data['profissional_id'],
            ':data_disponivel' => $data['data_disponivel'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fim' => $data['hora_fim'],
            ':observacoes' => $data['observacoes'],
            ':ativo' => $data['ativo'],
        ]);
    }

    public function deleteAvailability(int $availabilityId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM agenda_disponibilidade WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $availabilityId]);
    }

    public function countAppointmentsInRange(int $professionalId, string $date, string $startTime, string $endTime): int
    {
        return count($this->appointmentRangesInRange($professionalId, $date, $startTime, $endTime));
    }

    public function appointmentRangesInRange(int $professionalId, string $date, string $startTime, string $endTime): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT hora_inicio, hora_fim
             FROM agenda
             WHERE clinica_id = :clinic_id
               AND profissional_id = :professional_id
               AND data_agendamento = :data_agendamento
               AND status <> :status_cancelado
               AND NOT (hora_fim <= :hora_inicio OR hora_inicio >= :hora_fim)
             ORDER BY hora_inicio'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':data_agendamento' => $date,
            ':status_cancelado' => 'cancelado',
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ]);
        $ranges = $stmt->fetchAll();

        $tableStmt = $this->pdo->query("SHOW TABLES LIKE 'agenda_grupos'");
        if (!$tableStmt || !$tableStmt->fetchColumn()) {
            return $ranges;
        }

        $groupStmt = $this->pdo->prepare(
            'SELECT hora_inicio, hora_fim
             FROM agenda_grupos
             WHERE clinica_id = :clinic_id
               AND profissional_id = :professional_id
               AND data_agendamento = :data_agendamento
               AND NOT (hora_fim <= :hora_inicio OR hora_inicio >= :hora_fim)
             ORDER BY hora_inicio'
        );
        $groupStmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':data_agendamento' => $date,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ]);

        $ranges = array_merge($ranges, $groupStmt->fetchAll());
        usort(
            $ranges,
            static fn (array $left, array $right): int => strcmp((string) $left['hora_inicio'], (string) $right['hora_inicio'])
        );

        return $ranges;
    }

    public function removeAvailabilityRange(int $professionalId, string $date, string $startTime, string $endTime): int
    {
        $ranges = $this->availabilityRangesOverlapping($professionalId, $date, $startTime, $endTime);
        $changed = 0;

        foreach ($ranges as $range) {
            $currentStart = (string) $range['hora_inicio'];
            $currentEnd = (string) $range['hora_fim'];
            $removeStart = max(strtotime('2000-01-01 ' . $startTime), strtotime('2000-01-01 ' . $currentStart));
            $removeEnd = min(strtotime('2000-01-01 ' . $endTime), strtotime('2000-01-01 ' . $currentEnd));
            $currentStartTs = strtotime('2000-01-01 ' . $currentStart);
            $currentEndTs = strtotime('2000-01-01 ' . $currentEnd);

            if ($removeStart <= $currentStartTs && $removeEnd >= $currentEndTs) {
                $this->deleteAvailability((int) $range['id']);
                $changed++;
                continue;
            }

            if ($removeStart > $currentStartTs && $removeEnd < $currentEndTs) {
                $this->updateAvailability((int) $range['id'], [
                    'profissional_id' => $professionalId,
                    'data_disponivel' => $date,
                    'hora_inicio' => $currentStart,
                    'hora_fim' => date('H:i:s', $removeStart),
                    'observacoes' => (string) ($range['observacoes'] ?? ''),
                    'ativo' => 1,
                ]);
                $this->createAvailability([
                    'profissional_id' => $professionalId,
                    'data_disponivel' => $date,
                    'hora_inicio' => date('H:i:s', $removeEnd),
                    'hora_fim' => $currentEnd,
                    'observacoes' => (string) ($range['observacoes'] ?? ''),
                    'ativo' => 1,
                ]);
                $changed++;
                continue;
            }

            if ($removeStart <= $currentStartTs) {
                $this->updateAvailability((int) $range['id'], [
                    'profissional_id' => $professionalId,
                    'data_disponivel' => $date,
                    'hora_inicio' => date('H:i:s', $removeEnd),
                    'hora_fim' => $currentEnd,
                    'observacoes' => (string) ($range['observacoes'] ?? ''),
                    'ativo' => 1,
                ]);
                $changed++;
                continue;
            }

            $this->updateAvailability((int) $range['id'], [
                'profissional_id' => $professionalId,
                'data_disponivel' => $date,
                'hora_inicio' => $currentStart,
                'hora_fim' => date('H:i:s', $removeStart),
                'observacoes' => (string) ($range['observacoes'] ?? ''),
                'ativo' => 1,
            ]);
            $changed++;
        }

        return $changed;
    }

    public function paginateAvailabilities(array $filters, int $page, int $perPage, ?int $scopeProfessionalId = null, string $path = 'agenda_liberacao.php'): array
    {
        [$whereSql, $params] = $this->buildAvailabilityWhere($filters, $scopeProfessionalId);

        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM agenda_disponibilidade ad
             LEFT JOIN profissionais p ON p.id = ad.profissional_id AND p.clinica_id = ad.clinica_id
             ' . $whereSql
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = app_pagination($page, $perPage, $total, $path, $filters, 'availability_page');

        $sql = 'SELECT ad.*, p.nome AS profissional_nome
                FROM agenda_disponibilidade ad
                INNER JOIN profissionais p ON p.id = ad.profissional_id AND p.clinica_id = ad.clinica_id
                ' . $whereSql . '
                ORDER BY ad.data_disponivel DESC, ad.hora_inicio ASC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'pagination' => $pagination,
        ];
    }

    public function calendar(string $weekStart, int $professionalId): array
    {
        $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $attendanceSelect = $this->attendanceAppointmentColumnExists()
            ? ', at.id AS atendimento_id, at.guia_id AS atendimento_guia_id'
            : ', NULL AS atendimento_id, NULL AS atendimento_guia_id';
        $attendanceJoin = $this->attendanceAppointmentColumnExists()
            ? ' LEFT JOIN atendimentos at ON at.agenda_id = a.id AND at.clinica_id = a.clinica_id'
            : '';

        $availabilityStmt = $this->pdo->prepare(
            'SELECT *
             FROM agenda_disponibilidade
             WHERE clinica_id = :clinic_id
               AND profissional_id = :professional_id
               AND data_disponivel BETWEEN :week_start AND :week_end
               AND ativo = 1
             ORDER BY data_disponivel, hora_inicio'
        );
        $availabilityStmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':week_start' => $weekStart,
            ':week_end' => $weekEnd,
        ]);

        $appointmentStmt = $this->pdo->prepare(
            'SELECT a.*,
                    p.nome AS profissional_nome,
                    s.nome AS servico_nome,
                    COALESCE(pa.nome, a.cliente_nome) AS paciente_nome,
                    COALESCE(pa.telefone, a.cliente_telefone) AS paciente_telefone,
                    pa.dia_preferencia AS paciente_dia_preferencia,
                    pa.horario_preferencia AS paciente_horario_preferencia
                    ' . $attendanceSelect . '
             FROM agenda a
             INNER JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
             INNER JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
             LEFT JOIN pacientes pa ON pa.id = a.cliente_id AND pa.clinica_id = a.clinica_id
             ' . $attendanceJoin . '
             WHERE a.clinica_id = :clinic_id
               AND a.profissional_id = :professional_id
               AND a.data_agendamento BETWEEN :week_start AND :week_end
               AND a.status <> :status_cancelado
             ORDER BY a.data_agendamento, a.hora_inicio'
        );
        $appointmentStmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':week_start' => $weekStart,
            ':week_end' => $weekEnd,
            ':status_cancelado' => 'cancelado',
        ]);

        return [
            'availabilities' => $availabilityStmt->fetchAll(),
            'appointments' => $appointmentStmt->fetchAll(),
        ];
    }

    public function appointmentsBetween(int $professionalId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*,
                    p.nome AS profissional_nome,
                    s.nome AS servico_nome,
                    COALESCE(pa.nome, a.cliente_nome) AS paciente_nome,
                    COALESCE(pa.telefone, a.cliente_telefone) AS paciente_telefone
             FROM agenda a
             INNER JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
             INNER JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
             LEFT JOIN pacientes pa ON pa.id = a.cliente_id AND pa.clinica_id = a.clinica_id
             WHERE a.clinica_id = :clinic_id
               AND a.profissional_id = :professional_id
               AND a.data_agendamento BETWEEN :start_date AND :end_date
               AND a.status <> :status_cancelado
             ORDER BY a.data_agendamento, a.hora_inicio'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':status_cancelado' => 'cancelado',
        ]);

        return $stmt->fetchAll();
    }

    private function availabilityRangesTouching(int $professionalId, string $date, string $startTime, string $endTime): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM agenda_disponibilidade
             WHERE clinica_id = :clinic_id
               AND profissional_id = :professional_id
               AND data_disponivel = :data_disponivel
               AND ativo = 1
               AND hora_fim >= :hora_inicio
               AND hora_inicio <= :hora_fim
             ORDER BY hora_inicio'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':data_disponivel' => $date,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ]);

        return $stmt->fetchAll();
    }

    private function availabilityRangesOverlapping(int $professionalId, string $date, string $startTime, string $endTime): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM agenda_disponibilidade
             WHERE clinica_id = :clinic_id
               AND profissional_id = :professional_id
               AND data_disponivel = :data_disponivel
               AND ativo = 1
               AND NOT (hora_fim <= :hora_inicio OR hora_inicio >= :hora_fim)
             ORDER BY hora_inicio'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':professional_id' => $professionalId,
            ':data_disponivel' => $date,
            ':hora_inicio' => $startTime,
            ':hora_fim' => $endTime,
        ]);

        return $stmt->fetchAll();
    }

    private function buildAppointmentWhere(array $filters, ?int $scopeProfessionalId = null): array
    {
        $clauses = ['a.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];

        if ($scopeProfessionalId !== null) {
            $clauses[] = 'a.profissional_id = :scope_professional_id';
            $params[':scope_professional_id'] = $scopeProfessionalId;
        }

        $guideId = (int) ($filters['guia_id'] ?? 0);
        $patientId = (int) ($filters['paciente_id'] ?? 0);
        $patient = trim((string) ($filters['paciente'] ?? ''));
        $patientDigits = preg_replace('/\D+/', '', $patient);
        $professionalId = (int) ($filters['profissional_id'] ?? 0);
        $status = trim((string) ($filters['status'] ?? ''));
        $startDate = trim((string) ($filters['data_inicio'] ?? ''));
        $endDate = trim((string) ($filters['data_fim'] ?? ''));

        if ($guideId > 0) {
            if ($this->attendanceAppointmentColumnExists()) {
                $clauses[] = 'EXISTS (
                    SELECT 1
                    FROM atendimentos at
                    WHERE at.clinica_id = a.clinica_id
                      AND at.agenda_id = a.id
                      AND at.guia_id = :guide_id
                )';
            } else {
                $clauses[] = 'EXISTS (
                    SELECT 1
                    FROM guias g
                    WHERE g.id = :guide_id
                      AND g.clinica_id = a.clinica_id
                      AND g.paciente_id = a.cliente_id
                      AND g.profissional_id = a.profissional_id
                )';
            }
            $params[':guide_id'] = $guideId;
        }

        if ($patientId > 0) {
            $clauses[] = 'a.cliente_id = :patient_id';
            $params[':patient_id'] = $patientId;
        } elseif ($patient !== '') {
            $birthDateSql = "DATE_FORMAT(pa.data_nascimento, '%d/%m/%Y')";
            $birthIsoSql = "DATE_FORMAT(pa.data_nascimento, '%Y-%m-%d')";
            $search = [
                'COALESCE(pa.nome, a.cliente_nome) LIKE :patient_search',
                $birthDateSql . ' LIKE :patient_birth_br',
                $birthIsoSql . ' LIKE :patient_birth_iso',
            ];
            $params[':patient_search'] = '%' . $patient . '%';
            $params[':patient_birth_br'] = '%' . $patient . '%';
            $params[':patient_birth_iso'] = '%' . $patient . '%';

            if (strlen($patientDigits) >= 2) {
                $cpfDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pa.cpf, ''), '.', ''), '-', ''), '/', ''), ' ', '')";
                $phoneDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pa.telefone, a.cliente_telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
                $emergencyDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pa.telefone_emergencia, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
                $birthDigitsSql = "DATE_FORMAT(pa.data_nascimento, '%d%m%Y')";
                $search[] = $cpfDigitsSql . ' LIKE :patient_cpf_digits';
                $search[] = $phoneDigitsSql . ' LIKE :patient_phone_digits';
                $search[] = $emergencyDigitsSql . ' LIKE :patient_emergency_digits';
                $search[] = $birthDigitsSql . ' LIKE :patient_birth_digits';
                $params[':patient_cpf_digits'] = '%' . $patientDigits . '%';
                $params[':patient_phone_digits'] = '%' . $patientDigits . '%';
                $params[':patient_emergency_digits'] = '%' . $patientDigits . '%';
                $params[':patient_birth_digits'] = '%' . $patientDigits . '%';
            }

            $clauses[] = '(' . implode(' OR ', $search) . ')';
        }

        if ($professionalId > 0) {
            $clauses[] = 'a.profissional_id = :professional_id';
            $params[':professional_id'] = $professionalId;
        }

        if ($status !== '') {
            $clauses[] = 'a.status = :status';
            $params[':status'] = $status;
        }

        if ($startDate !== '') {
            $clauses[] = 'a.data_agendamento >= :start_date';
            $params[':start_date'] = $startDate;
        }

        if ($endDate !== '') {
            $clauses[] = 'a.data_agendamento <= :end_date';
            $params[':end_date'] = $endDate;
        }

        $whereSql = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }

    private function attendanceAppointmentColumnExists(): bool
    {
        if ($this->attendanceAppointmentColumnExists !== null) {
            return $this->attendanceAppointmentColumnExists;
        }

        $stmt = $this->pdo->query("SHOW COLUMNS FROM atendimentos LIKE 'agenda_id'");
        $this->attendanceAppointmentColumnExists = (bool) $stmt->fetch();

        return $this->attendanceAppointmentColumnExists;
    }

    private function buildAvailabilityWhere(array $filters, ?int $scopeProfessionalId = null): array
    {
        $clauses = ['ad.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];

        if ($scopeProfessionalId !== null) {
            $clauses[] = 'ad.profissional_id = :scope_professional_id';
            $params[':scope_professional_id'] = $scopeProfessionalId;
        }

        $professionalId = (int) ($filters['profissional_id'] ?? 0);
        $month = trim((string) ($filters['mes'] ?? ''));

        if ($professionalId > 0) {
            $clauses[] = 'ad.profissional_id = :professional_id';
            $params[':professional_id'] = $professionalId;
        }

        if ($month !== '') {
            $clauses[] = 'DATE_FORMAT(ad.data_disponivel, "%Y-%m") = :month';
            $params[':month'] = $month;
        }

        $whereSql = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }
}
