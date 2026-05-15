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

    public function serviceOptions(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome, ativo
             FROM servicos
             WHERE clinica_id = :clinic_id
             ORDER BY ativo DESC, nome'
        );
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    public function planOptions(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, nome
             FROM planos
             WHERE clinica_id = :clinic_id
             ORDER BY nome'
        );
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    public function priceReport(array $filters = []): array
    {
        $search = trim((string) ($filters['busca_servico'] ?? ''));
        $clauses = ['s.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];

        if ($search !== '') {
            $clauses[] = '(s.nome LIKE :search_service OR pl.nome LIKE :search_plan)';
            $params[':search_service'] = '%' . $search . '%';
            $params[':search_plan'] = '%' . $search . '%';
        }

        $stmt = $this->pdo->prepare(
            'SELECT s.id AS service_id,
                    s.nome AS service_name,
                    s.tempo_minutos,
                    s.tipo_agendamento,
                    s.capacidade_agendamento,
                    s.ativo AS service_active,
                    sp.id AS price_id,
                    sp.plano_id,
                    pl.nome AS plano_nome,
                    sp.valor,
                    sp.ativo AS price_active,
                    sp.permite_alterar_guia,
                    sp.observacoes
             FROM servicos s
             LEFT JOIN servico_precos sp ON sp.clinica_id = s.clinica_id AND sp.servico_id = s.id
             LEFT JOIN planos pl ON pl.clinica_id = s.clinica_id AND pl.id = sp.plano_id
             WHERE ' . implode(' AND ', $clauses) . '
             ORDER BY s.nome, sp.ativo DESC, COALESCE(pl.nome, \'Sem plano\'), sp.valor'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function pricesForService(int $serviceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.*,
                    pl.nome AS plano_nome
             FROM servico_precos sp
             LEFT JOIN planos pl ON pl.clinica_id = sp.clinica_id AND pl.id = sp.plano_id
             WHERE sp.clinica_id = :clinic_id
               AND sp.servico_id = :servico_id
             ORDER BY sp.ativo DESC, COALESCE(pl.nome, \'Sem plano\'), sp.valor'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':servico_id' => $serviceId,
        ]);

        return $stmt->fetchAll();
    }

    public function findPrice(int $priceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM servico_precos WHERE clinica_id = :clinic_id AND id = :id LIMIT 1');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $priceId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function priceExistsForPlan(int $serviceId, int $planId, ?int $ignorePriceId = null): bool
    {
        $sql = 'SELECT id
                FROM servico_precos
                WHERE clinica_id = :clinic_id
                  AND servico_id = :service_id
                  AND plano_id = :plan_id';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':service_id' => $serviceId,
            ':plan_id' => $planId,
        ];

        if ($ignorePriceId !== null && $ignorePriceId > 0) {
            $sql .= ' AND id <> :ignore_price_id';
            $params[':ignore_price_id'] = $ignorePriceId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO servicos (clinica_id, nome, tempo_minutos, tipo_agendamento, capacidade_agendamento, ativo)
             VALUES (:clinic_id, :nome, :tempo_minutos, :tipo_agendamento, :capacidade_agendamento, :ativo)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':nome' => $data['nome'],
            ':tempo_minutos' => $data['tempo_minutos'],
            ':tipo_agendamento' => $data['tipo_agendamento'],
            ':capacidade_agendamento' => $data['capacidade_agendamento'],
            ':ativo' => $data['ativo'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function createPrice(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO servico_precos (clinica_id, servico_id, plano_id, forma_pagamento, valor, ativo, permite_alterar_guia, observacoes)
             VALUES (:clinic_id, :servico_id, :plano_id, :forma_pagamento, :valor, :ativo, :permite_alterar_guia, :observacoes)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':servico_id' => $data['servico_id'],
            ':plano_id' => $data['plano_id'],
            ':forma_pagamento' => $data['forma_pagamento'],
            ':valor' => $data['valor'],
            ':ativo' => $data['ativo'],
            ':permite_alterar_guia' => $data['permite_alterar_guia'],
            ':observacoes' => $data['observacoes'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $serviceId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE servicos
             SET nome = :nome,
                 tempo_minutos = :tempo_minutos,
                 tipo_agendamento = :tipo_agendamento,
                 capacidade_agendamento = :capacidade_agendamento,
                 ativo = :ativo
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $serviceId,
            ':nome' => $data['nome'],
            ':tempo_minutos' => $data['tempo_minutos'],
            ':tipo_agendamento' => $data['tipo_agendamento'],
            ':capacidade_agendamento' => $data['capacidade_agendamento'],
            ':ativo' => $data['ativo'],
        ]);
    }

    public function updatePrice(int $priceId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE servico_precos
             SET servico_id = :servico_id,
                 plano_id = :plano_id,
                 forma_pagamento = :forma_pagamento,
                 valor = :valor,
                 ativo = :ativo,
                 permite_alterar_guia = :permite_alterar_guia,
                 observacoes = :observacoes
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $priceId,
            ':servico_id' => $data['servico_id'],
            ':plano_id' => $data['plano_id'],
            ':forma_pagamento' => $data['forma_pagamento'],
            ':valor' => $data['valor'],
            ':ativo' => $data['ativo'],
            ':permite_alterar_guia' => $data['permite_alterar_guia'],
            ':observacoes' => $data['observacoes'],
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

    public function deletePrice(int $priceId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM servico_precos WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $priceId]);
    }
}
