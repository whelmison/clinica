<?php

namespace Clinic\Services;

use Clinic\Repositories\GuideRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

final class GuideService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly GuideRepository $repository
    ) {
    }

    public function create(array $input, array $user): array
    {
        if (!$this->canCreate($user)) {
            return ['ok' => false, 'message' => 'Sem permissao para criar guias.'];
        }

        try {
            $data = $this->normalizeData($input);
            $this->pdo->beginTransaction();
            $guideId = $this->repository->create($data);
            $this->syncReceivable($guideId, $data['tipo_guia']);
            $this->pdo->commit();

            return ['ok' => true, 'message' => 'Guia criada com sucesso.', 'id' => $guideId];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel salvar a guia.'];
        }
    }

    public function update(int $guideId, array $input, array $user): array
    {
        $guide = $this->repository->find($guideId);

        if (!$guide) {
            return ['ok' => false, 'message' => 'Guia nao encontrada.'];
        }

        if (!$this->canEdit($user, $guide)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode editar esta guia.'];
        }

        if ($this->isLockedForEdit($guide)) {
            return ['ok' => false, 'message' => 'Esta guia ja esta em uso ou finalizada e nao pode ser alterada.'];
        }

        try {
            $forcedProfessionalId = $this->isProfessionalEditor($user) ? (int) $guide['profissional_id'] : null;
            $data = $this->normalizeData($input, $guideId, $forcedProfessionalId);
            $this->pdo->beginTransaction();
            $this->repository->update($guideId, $data);
            $this->syncReceivable($guideId, $data['tipo_guia']);
            $this->pdo->commit();

            return ['ok' => true, 'message' => 'Guia atualizada com sucesso.', 'id' => $guideId];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel atualizar a guia.'];
        }
    }

    public function delete(int $guideId, array $user): array
    {
        if (!$this->canDelete($user)) {
            return ['ok' => false, 'message' => 'Sem permissao para excluir guias.'];
        }

        $guide = $this->repository->find($guideId);

        if (!$guide) {
            return ['ok' => false, 'message' => 'Guia nao encontrada.'];
        }

        if ($this->isLockedForEdit($guide)) {
            return ['ok' => false, 'message' => 'Nao e permitido excluir uma guia em uso ou finalizada.'];
        }

        if ($this->repository->countAttendances($guideId) > 0) {
            return ['ok' => false, 'message' => 'Nao e permitido excluir uma guia com atendimentos vinculados.'];
        }

        $receivable = $this->repository->receivableForGuide($guideId);

        if ($receivable && !in_array($receivable['status'], ['aberto', 'cancelado'], true)) {
            return ['ok' => false, 'message' => 'A guia possui faturamento em andamento e nao pode ser excluida.'];
        }

        try {
            $this->pdo->beginTransaction();
            $this->repository->deleteGuideReceivable($guideId);
            $this->repository->delete($guideId);
            $this->pdo->commit();

            return ['ok' => true, 'message' => 'Guia excluida com sucesso.'];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel excluir a guia.'];
        }
    }

    public function canCreate(array $user): bool
    {
        $profile = $user['perfil'] ?? '';
        return in_array($profile, ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'], true);
    }

    public function canDelete(array $user): bool
    {
        $profile = $user['perfil'] ?? '';

        if (in_array($profile, ['secretaria', 'administrativo', 'desenvolvedor'], true)) {
            return true;
        }

        if ($profile === 'profissional') {
            $professionalId = (int) ($user['profissional_id'] ?? 0);
            return $professionalId > 0 && $this->repository->professionalCanEditGuides($professionalId);
        }

        return false;
    }

    public function canEdit(array $user, ?array $guide = null): bool
    {
        $profile = $user['perfil'] ?? '';

        if (in_array($profile, ['secretaria', 'administrativo', 'desenvolvedor'], true)) {
            return true;
        }

        if ($profile !== 'profissional' || $guide === null) {
            return false;
        }

        $professionalId = (int) ($user['profissional_id'] ?? 0);

        if ($professionalId <= 0 || (int) $guide['profissional_id'] !== $professionalId) {
            return false;
        }

        return $this->repository->professionalCanEditGuides($professionalId);
    }

    public function isLockedForEdit(array $guide): bool
    {
        $usedSessions = (int) ($guide['total_atendimentos'] ?? $guide['usadas'] ?? $guide['sessoes_usadas'] ?? 0);

        if ($usedSessions > 0) {
            return true;
        }

        return app_guide_operational_status_data($guide)['value'] === 'finalizada';
    }

    private function isProfessionalEditor(array $user): bool
    {
        return ($user['perfil'] ?? '') === 'profissional' && (int) ($user['profissional_id'] ?? 0) > 0;
    }

    private function normalizeData(array $input, ?int $ignoreId = null, ?int $forcedProfessionalId = null): array
    {
        $patientId = (int) ($input['paciente_id'] ?? 0);
        $professionalId = $forcedProfessionalId ?? (int) ($input['profissional_id'] ?? 0);
        $serviceId = (int) ($input['servico_id'] ?? 0);
        $planId = (int) ($input['plano_id'] ?? 0);
        $date = trim((string) ($input['data'] ?? date('Y-m-d')));
        $totalSessions = max(1, (int) ($input['total_sessoes'] ?? 1));
        $type = trim((string) ($input['tipo_guia'] ?? ''));
        $code = trim((string) ($input['codigo'] ?? ''));
        $value = app_parse_money((string) ($input['valor_guia'] ?? '0'));
        $convenio = trim((string) ($input['convenio'] ?? ''));
        $notes = trim((string) ($input['observacoes'] ?? ''));
        $batchId = !empty($input['lote_id']) ? (int) $input['lote_id'] : null;
        $authorized = !empty($input['autorizada']) ? 1 : 0;
        $operationalStatus = app_normalize_guide_operational_status((string) ($input['status_operacional'] ?? 'aguardando_autorizacao'));

        if ($patientId <= 0) {
            throw new InvalidArgumentException('Selecione um paciente valido.');
        }

        if ($professionalId <= 0) {
            throw new InvalidArgumentException('Selecione um profissional valido.');
        }

        if ($serviceId <= 0 || !$this->repository->professionalHasService($professionalId, $serviceId)) {
            throw new InvalidArgumentException('Selecione um servico vinculado ao profissional.');
        }

        if ($planId <= 0) {
            throw new InvalidArgumentException('Selecione o plano da guia.');
        }

        if (!array_key_exists($type, app_guide_types())) {
            throw new InvalidArgumentException('Selecione um tipo de guia valido.');
        }

        if ($type !== 'convenio_lote') {
            $batchId = null;
        }

        if ($code === '') {
            $code = $this->repository->nextCode();
        }

        if ($this->repository->codeExists($code, $ignoreId)) {
            throw new InvalidArgumentException('Ja existe uma guia com este codigo.');
        }

        $servicePriceData = $this->repository->servicePriceData($serviceId, $planId);
        $servicePrice = (float) ($servicePriceData['valor'] ?? 0);

        if ($servicePrice <= 0) {
            throw new InvalidArgumentException('Cadastre o preco deste servico para este plano no cadastro de servico.');
        }

        $calculatedValue = $servicePrice * $totalSessions;
        $value = !empty($servicePriceData['permite_alterar_guia']) && $value > 0 ? $value : $calculatedValue;

        if ($value <= 0) {
            throw new InvalidArgumentException('Informe um valor valido para a guia.');
        }

        if ($operationalStatus === 'cancelada') {
            $authorized = 0;
        } elseif ($operationalStatus === 'autorizada') {
            $authorized = 1;
        } elseif ($authorized === 1 && $operationalStatus === 'aguardando_autorizacao') {
            $operationalStatus = 'autorizada';
        } elseif ($authorized === 0 && $operationalStatus === 'autorizada') {
            $operationalStatus = 'aguardando_autorizacao';
        }

        return [
            'codigo' => $code,
            'paciente_id' => $patientId,
            'plano_id' => $planId,
            'servico_id' => $serviceId,
            'total_sessoes' => $totalSessions,
            'data' => $date,
            'valor_guia' => $value,
            'profissional_id' => $professionalId,
            'tipo_guia' => $type,
            'lote_id' => $batchId,
            'convenio' => $convenio,
            'observacoes' => $notes,
            'autorizada' => $authorized,
            'status_operacional' => $operationalStatus,
        ];
    }

    private function syncReceivable(int $guideId, string $type): void
    {
        $directTypes = ['particular', 'convenio_direto'];
        $existing = $this->repository->receivableForGuide($guideId);

        if (in_array($type, $directTypes, true)) {
            $this->repository->upsertGuideReceivable($guideId);

            return;
        }

        if (!$existing) {
            $this->repository->updateGuideReceivableLink($guideId, null);

            return;
        }

        if (!in_array($existing['status'], ['aberto', 'cancelado'], true)) {
            throw new InvalidArgumentException('A guia ja possui recebimento em andamento e nao pode trocar este tipo de faturamento.');
        }

        $this->repository->deleteGuideReceivable($guideId);
        $this->repository->updateGuideReceivableLink($guideId, null);
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
