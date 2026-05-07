<?php

namespace Clinic\Repositories;

use PDO;

final class ServiceCatalogRepository
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
        $search = trim((string) ($filters['busca_servico'] ?? ''));
        $clauses = ['s.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];

        if ($search !== '') {
            $clauses[] = 's.nome LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }

        $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM servicos s' . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = app_pagination($page, $perPage, $total, 'secretaria_servicos.php', $filters, 'service_page');

        $stmt = $this->pdo->prepare(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM profissional_servico ps WHERE ps.clinica_id = s.clinica_id AND ps.servico_id = s.id) AS total_profissionais
             FROM servicos s
             ' . $where . '
             ORDER BY s.nome
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

    public function find(int $serviceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM servicos WHERE clinica_id = :clinic_id AND id = :id LIMIT 1');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $serviceId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO servicos (clinica_id, nome, tempo_minutos, ativo)
             VALUES (:clinic_id, :nome, :tempo_minutos, :ativo)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':nome' => $data['nome'],
            ':tempo_minutos' => $data['tempo_minutos'],
            ':ativo' => $data['ativo'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $serviceId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE servicos
             SET nome = :nome,
                 tempo_minutos = :tempo_minutos,
                 ativo = :ativo
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $serviceId,
            ':nome' => $data['nome'],
            ':tempo_minutos' => $data['tempo_minutos'],
            ':ativo' => $data['ativo'],
        ]);
    }

    public function dependencies(int $serviceId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM profissional_servico WHERE clinica_id = :clinic_id AND servico_id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $serviceId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(int $serviceId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM servicos WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $serviceId]);
    }
}
