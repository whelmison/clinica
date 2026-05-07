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

    private function isProfessionalEditor(array $user): bool
    {
        return ($user['perfil'] ?? '') === 'profissional' && (int) ($user['profissional_id'] ?? 0) > 0;
    }

    private function normalizeData(array $input, ?int $ignoreId = null, ?int $forcedProfessionalId = null): array
    {
        $patientId = (int) ($input['paciente_id'] ?? 0);
        $professionalId = $forcedProfessionalId ?? (int) ($input['profissional_id'] ?? 0);
        $planId = !empty($input['plano_id']) ? (int) $input['plano_id'] : null;
        $date = trim((string) ($input['data'] ?? date('Y-m-d')));
        $totalSessions = max(1, (int) ($input['total_sessoes'] ?? 1));
        $type = trim((string) ($input['tipo_guia'] ?? ''));
        $code = trim((string) ($input['codigo'] ?? ''));
        $value = app_parse_money((string) ($input['valor_guia'] ?? '0'));
        $convenio = trim((string) ($input['convenio'] ?? ''));
        $notes = trim((string) ($input['observacoes'] ?? ''));
        $batchId = !empty($input['lote_id']) ? (int) $input['lote_id'] : null;

        if ($patientId <= 0) {
            throw new InvalidArgumentException('Selecione um paciente valido.');
        }

        if ($professionalId <= 0) {
            throw new InvalidArgumentException('Selecione um profissional valido.');
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

        if ($planId !== null && $value <= 0) {
            $value = $this->repository->planValue($planId) * $totalSessions;
        }

        if ($value <= 0) {
            throw new InvalidArgumentException('Informe um valor valido para a guia.');
        }

        return [
            'codigo' => $code,
            'paciente_id' => $patientId,
            'plano_id' => $planId,
            'total_sessoes' => $totalSessions,
            'data' => $date,
            'valor_guia' => $value,
            'profissional_id' => $professionalId,
            'tipo_guia' => $type,
            'lote_id' => $batchId,
            'convenio' => $convenio,
            'observacoes' => $notes,
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
