<?php

namespace Clinic\Services;

use Clinic\Repositories\BatchRepository;
use InvalidArgumentException;
use PDO;
use Throwable;

final class BatchService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly BatchRepository $repository
    ) {
    }

    public function save(?int $batchId, array $input, array $user): array
    {
        if (!$this->canManage($user)) {
            return ['ok' => false, 'message' => 'Somente secretaria, administrativo ou desenvolvedor podem gerenciar lotes.'];
        }

        try {
            $data = $this->normalize($input, $batchId);
            $this->pdo->beginTransaction();
            $savedBatchId = $batchId ?? $this->repository->create($data);

            if ($batchId !== null) {
                $this->repository->update($batchId, $data);
            }

            $this->repository->syncGuides($savedBatchId, $data['guia_ids']);
            $this->syncReceivableStatus($savedBatchId, $data['status']);
            $this->pdo->commit();

            return [
                'ok' => true,
                'message' => $batchId === null ? 'Lote criado com sucesso.' : 'Lote atualizado com sucesso.',
                'id' => $savedBatchId,
            ];
        } catch (InvalidArgumentException $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel salvar o lote.'];
        }
    }

    public function delete(int $batchId, array $user): array
    {
        if (!$this->canManage($user)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode excluir lotes.'];
        }

        $batch = $this->repository->find($batchId);

        if (!$batch) {
            return ['ok' => false, 'message' => 'Lote nao encontrado.'];
        }

        $receivable = $this->repository->receivableForBatch($batchId);

        if ($receivable && !in_array($receivable['status'], ['aberto', 'cancelado'], true)) {
            return ['ok' => false, 'message' => 'O lote possui faturamento em andamento e nao pode ser excluido.'];
        }

        try {
            $this->pdo->beginTransaction();
            $this->repository->syncGuides($batchId, []);
            $this->repository->deleteBatchReceivable($batchId);
            $this->repository->delete($batchId);
            $this->pdo->commit();

            return ['ok' => true, 'message' => 'Lote excluido com sucesso.'];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();

            return ['ok' => false, 'message' => 'Nao foi possivel excluir o lote.'];
        }
    }

    public function markAsPaid(int $batchId, array $user): array
    {
        if (!$this->canManage($user)) {
            return ['ok' => false, 'message' => 'Seu perfil nao pode dar baixa em lotes.'];
        }

        $batch = $this->repository->find($batchId);

        if (!$batch) {
            return ['ok' => false, 'message' => 'Lote nao encontrado.'];
        }

        if ($batch['status'] === 'pago') {
            return ['ok' => false, 'message' => 'Este lote ja esta pago.'];
        }

        try {
            $this->pdo->beginTransaction();
            $data = [
                'numero_lote' => $batch['numero_lote'],
                'convenio' => $batch['convenio'],
                'data' => $batch['data'],
                'status' => 'pago',
            ];
            $this->repository->update($batchId, $data);
            $this->syncReceivableStatus($batchId, 'pago');
            $this->pdo->commit();

            return ['ok' => true, 'message' => 'Baixa realizada com sucesso.'];
        } catch (Throwable $exception) {
            $this->rollbackIfNeeded();
            return ['ok' => false, 'message' => 'Nao foi possivel dar baixa no lote.'];
        }
    }

    public function canManage(array $user): bool
    {
        return in_array($user['perfil'] ?? '', ['secretaria', 'administrativo', 'desenvolvedor'], true);
    }

    private function normalize(array $input, ?int $batchId = null): array
    {
        $number = trim((string) ($input['numero_lote'] ?? ''));
        $date = trim((string) ($input['data'] ?? date('Y-m-d')));
        $status = trim((string) ($input['status'] ?? 'aberto'));
        $guideIds = array_values(array_unique(array_filter(array_map('intval', $input['guias'] ?? []))));

        if ($number === '') {
            $number = $this->repository->nextNumber();
        }

        if ($this->repository->findByNumber($number, $batchId)) {
            throw new InvalidArgumentException('Ja existe um lote com este numero.');
        }

        if (!array_key_exists($status, app_lote_statuses())) {
            throw new InvalidArgumentException('Selecione um status de lote valido.');
        }

        if ($guideIds === []) {
            throw new InvalidArgumentException('Selecione ao menos uma guia para o lote.');
        }

        $availableGuides = $this->repository->availableGuides([], $batchId);
        $availableIds = array_map(static fn (array $guide): int => (int) $guide['id'], $availableGuides);

        foreach ($guideIds as $guideId) {
            if (!in_array($guideId, $availableIds, true)) {
                throw new InvalidArgumentException('Uma ou mais guias selecionadas nao estao disponiveis para este lote.');
            }
        }

        $selectedGuides = array_values(array_filter($availableGuides, static fn (array $guide): bool => in_array((int) $guide['id'], $guideIds, true)));
        $convenios = array_values(array_unique(array_filter(array_map(static fn (array $guide): string => trim((string) ($guide['convenio'] ?? '')), $selectedGuides))));
        $convenio = $convenios !== [] ? implode(' / ', $convenios) : 'Sem convenio informado';

        return [
            'numero_lote' => $number,
            'data' => $date,
            'status' => $status,
            'convenio' => $convenio,
            'guia_ids' => $guideIds,
        ];
    }

    private function syncReceivableStatus(int $batchId, string $status): void
    {
        if (in_array($status, ['faturado', 'pago'], true)) {
            $this->repository->upsertBatchReceivable($batchId);
        } else {
            $existing = $this->repository->receivableForBatch($batchId);

            if ($existing && in_array($existing['status'], ['aberto', 'cancelado'], true)) {
                $this->repository->deleteBatchReceivable($batchId);
            }
        }

        $receivable = $this->repository->receivableForBatch($batchId);

        if ($receivable && $status === 'pago') {
            $stmt = $this->pdo->prepare(
                'UPDATE contas_receber
                 SET status = :status, recebimento = :recebimento
                 WHERE clinica_id = :clinic_id AND id = :id'
            );
            $stmt->execute([
                ':clinic_id' => app_active_clinic_id(),
                ':status' => 'pago',
                ':recebimento' => date('Y-m-d'),
                ':id' => $receivable['id'],
            ]);
        }
    }

    private function rollbackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
