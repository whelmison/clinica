<?php

namespace Clinic\Repositories;

use PDO;

final class BatchRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function clinicId(): int
    {
        return app_active_clinic_id();
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);

        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT l.id)
             FROM lotes l
             LEFT JOIN guias g ON g.lote_id = l.id AND g.clinica_id = l.clinica_id
             LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
             LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
             ' . $whereSql
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = app_pagination($page, $perPage, $total, 'administrativo_lotes.php', $filters);

        $stmt = $this->pdo->prepare(
            'SELECT l.*,
                    COUNT(g.id) AS total_guias,
                    COALESCE(SUM(g.valor_guia), 0) AS total_valor
             FROM lotes l
             LEFT JOIN guias g ON g.lote_id = l.id AND g.clinica_id = l.clinica_id
             LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
             LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
             ' . $whereSql . '
             GROUP BY l.id
             ORDER BY l.data DESC, l.id DESC
             LIMIT :limit OFFSET :offset'
        );

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

    public function find(int $batchId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lotes WHERE clinica_id = :clinic_id AND id = :id LIMIT 1');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $batchId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByNumber(string $number, ?int $ignoreId = null): ?array
    {
        $sql = 'SELECT * FROM lotes WHERE clinica_id = :clinic_id AND numero_lote = :numero_lote';
        $params = [':clinic_id' => $this->clinicId(), ':numero_lote' => $number];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function nextNumber(): string
    {
        $prefix = 'L-' . date('Ym') . '-';
        $stmt = $this->pdo->prepare('SELECT numero_lote FROM lotes WHERE clinica_id = :clinic_id AND numero_lote LIKE :prefix ORDER BY numero_lote DESC LIMIT 1');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':prefix' => $prefix . '%']);
        $last = (string) ($stmt->fetchColumn() ?: '');

        if ($last === '') {
            return $prefix . '001';
        }

        $sequence = (int) substr($last, -3);

        return $prefix . str_pad((string) ($sequence + 1), 3, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO lotes (clinica_id, numero_lote, convenio, data, status)
             VALUES (:clinic_id, :numero_lote, :convenio, :data, :status)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':numero_lote' => $data['numero_lote'],
            ':convenio' => $data['convenio'],
            ':data' => $data['data'],
            ':status' => $data['status'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $batchId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE lotes
             SET numero_lote = :numero_lote,
                 convenio = :convenio,
                 data = :data,
                 status = :status
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $batchId,
            ':numero_lote' => $data['numero_lote'],
            ':convenio' => $data['convenio'],
            ':data' => $data['data'],
            ':status' => $data['status'],
        ]);
    }

    public function delete(int $batchId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lotes WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $batchId]);
    }

    public function receivableForBatch(int $batchId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contas_receber WHERE clinica_id = :clinic_id AND origem_tipo = :tipo AND origem_id = :id LIMIT 1');
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':tipo' => 'lote',
            ':id' => $batchId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function upsertBatchReceivable(int $batchId): void
    {
        $batch = $this->find($batchId);
        $summary = $this->summary($batchId);

        if (!$batch || $summary['total_guias'] <= 0) {
            return;
        }

        $description = 'Lote ' . $batch['numero_lote'];
        $existing = $this->receivableForBatch($batchId);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE contas_receber
                 SET descricao = :descricao,
                     valor = :valor,
                     vencimento = :vencimento
                 WHERE clinica_id = :clinic_id AND id = :id'
            );
            $stmt->execute([
                ':clinic_id' => $this->clinicId(),
                ':descricao' => $description,
                ':valor' => $summary['total_valor'],
                ':vencimento' => $batch['data'],
                ':id' => $existing['id'],
            ]);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO contas_receber (clinica_id, descricao, valor, vencimento, status, origem_tipo, origem_id)
             VALUES (:clinic_id, :descricao, :valor, :vencimento, :status, :origem_tipo, :origem_id)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':descricao' => $description,
            ':valor' => $summary['total_valor'],
            ':vencimento' => $batch['data'],
            ':status' => 'aberto',
            ':origem_tipo' => 'lote',
            ':origem_id' => $batchId,
        ]);
    }

    public function deleteBatchReceivable(int $batchId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM contas_receber WHERE clinica_id = :clinic_id AND origem_tipo = :tipo AND origem_id = :id');
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':tipo' => 'lote',
            ':id' => $batchId,
        ]);
    }

    public function linkedGuides(int $batchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.codigo, g.valor_guia, g.data, g.convenio,
                    pa.nome AS paciente_nome,
                    pr.nome AS profissional_nome
             FROM guias g
             LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
             LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
             WHERE g.clinica_id = :clinic_id AND g.lote_id = :batch_id
             ORDER BY g.data DESC, g.id DESC'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':batch_id' => $batchId]);

        return $stmt->fetchAll();
    }

    public function availableGuides(array $filters, ?int $batchId = null): array
    {
        $clauses = ['g.clinica_id = :clinic_id', 'g.tipo_guia = :tipo_guia', 'g.sessoes_usadas >= g.total_sessoes'];
        $params = [':clinic_id' => $this->clinicId(), ':tipo_guia' => 'convenio_lote'];

        if ($batchId !== null) {
            $clauses[] = '(g.lote_id IS NULL OR g.lote_id = :batch_id)';
            $params[':batch_id'] = $batchId;
        } else {
            $clauses[] = 'g.lote_id IS NULL';
        }

        $guideId = (int) ($filters['guia_id'] ?? 0);
        $patientId = (int) ($filters['paciente_id'] ?? 0);
        $professionalId = (int) ($filters['profissional_id'] ?? 0);

        if ($guideId > 0) {
            $clauses[] = 'g.id = :guide_id';
            $params[':guide_id'] = $guideId;
        }

        if ($patientId > 0) {
            $clauses[] = 'g.paciente_id = :patient_id';
            $params[':patient_id'] = $patientId;
        }

        if ($professionalId > 0) {
            $clauses[] = 'g.profissional_id = :professional_id';
            $params[':professional_id'] = $professionalId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.codigo, g.valor_guia, g.data, g.convenio, g.lote_id,
                    pa.nome AS paciente_nome,
                    pr.nome AS profissional_nome
             FROM guias g
             LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
             LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
             WHERE ' . implode(' AND ', $clauses) . '
             ORDER BY g.data DESC, g.id DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function syncGuides(int $batchId, array $guideIds): void
    {
        $this->pdo->prepare('UPDATE guias SET lote_id = NULL WHERE clinica_id = :clinic_id AND lote_id = :batch_id')->execute([
            ':clinic_id' => $this->clinicId(),
            ':batch_id' => $batchId,
        ]);

        if ($guideIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($guideIds), '?'));
        $params = array_merge([$batchId, $this->clinicId()], $guideIds);
        $stmt = $this->pdo->prepare('UPDATE guias SET lote_id = ? WHERE clinica_id = ? AND id IN (' . $placeholders . ')');
        $stmt->execute($params);
    }

    public function summary(int $batchId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total_guias, COALESCE(SUM(valor_guia), 0) AS total_valor
             FROM guias
             WHERE clinica_id = :clinic_id AND lote_id = :batch_id'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':batch_id' => $batchId]);
        $row = $stmt->fetch();

        return [
            'total_guias' => (int) ($row['total_guias'] ?? 0),
            'total_valor' => (float) ($row['total_valor'] ?? 0),
        ];
    }

    public function options(): array
    {
        return [
            'patients' => $this->optionRows('SELECT id, nome FROM pacientes WHERE clinica_id = :clinic_id ORDER BY nome'),
            'professionals' => $this->optionRows('SELECT id, nome FROM profissionais WHERE clinica_id = :clinic_id ORDER BY nome'),
            'guides' => $this->optionRows('SELECT id, codigo FROM guias WHERE clinica_id = :clinic_id AND tipo_guia = "convenio_lote" ORDER BY codigo, id DESC'),
        ];
    }

    private function optionRows(string $sql): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    private function buildWhere(array $filters): array
    {
        $clauses = ['l.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];
        $guideId = (int) ($filters['guia_id'] ?? 0);
        $patientId = (int) ($filters['paciente_id'] ?? 0);
        $professionalId = (int) ($filters['profissional_id'] ?? 0);
        $status = trim((string) ($filters['status'] ?? ''));

        if ($guideId > 0) {
            $clauses[] = 'g.id = :guide_id';
            $params[':guide_id'] = $guideId;
        }

        if ($patientId > 0) {
            $clauses[] = 'g.paciente_id = :patient_id';
            $params[':patient_id'] = $patientId;
        }

        if ($professionalId > 0) {
            $clauses[] = 'g.profissional_id = :professional_id';
            $params[':professional_id'] = $professionalId;
        }

        if ($status !== '') {
            $clauses[] = 'l.status = :status';
            $params[':status'] = $status;
        }

        $whereSql = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }
}
