<?php

namespace Clinic\Repositories;

use PDO;

final class PlanRepository
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

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM planos' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $paginationQuery = array_filter(
            $filters,
            static fn (mixed $value): bool => !($value === null || $value === '')
        );
        $pagination = app_pagination($page, $perPage, $total, 'planos.php', $paginationQuery, 'plan_page');

        $stmt = $this->pdo->prepare(
            'SELECT id, nome
             FROM planos' . $whereSql . '
             ORDER BY nome ASC, id DESC
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

    public function summary(array $filters = []): array
    {
        [$whereSql, $params] = $this->buildWhere($filters);
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total,
                    0 AS media,
                    0 AS maior
             FROM planos' . $whereSql
        );
        $stmt->execute($params);

        return $stmt->fetch() ?: [
            'total' => 0,
            'media' => 0,
            'maior' => 0,
        ];
    }

    public function find(int $planId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome
             FROM planos
             WHERE clinica_id = :clinic_id AND id = :id
             LIMIT 1'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $planId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO planos (clinica_id, nome, valor_sessao)
             VALUES (:clinic_id, :nome, 0)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':nome' => $data['nome'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $planId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE planos
             SET nome = :nome
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $planId,
            ':nome' => $data['nome'],
        ]);
    }

    private function buildWhere(array $filters): array
    {
        $where = ['clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];
        $search = trim((string) ($filters['busca_plano'] ?? ''));

        if ($search !== '') {
            $where[] = 'nome LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        return [$whereSql, $params];
    }
}
