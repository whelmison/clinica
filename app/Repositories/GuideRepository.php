<?php

namespace Clinic\Repositories;

use PDO;

final class GuideRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function clinicId(): int
    {
        return app_active_clinic_id();
    }

    public function paginate(array $filters, int $page, int $perPage, ?int $scopeProfessionalId = null): array
    {
        [$whereSql, $params] = $this->buildWhere($filters, $scopeProfessionalId);

        $countStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM guias g
             LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
             LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
             ' . $whereSql
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $paginationQuery = array_filter(
            $filters,
            static fn (mixed $value): bool => !($value === null || $value === '' || $value === 0 || $value === '0')
        );
        $pagination = app_pagination($page, $perPage, $total, 'gestao_guias.php', $paginationQuery);

        $sql = 'SELECT g.*,
                       pa.nome AS paciente_nome,
                       pa.telefone AS paciente_telefone,
                       pr.nome AS profissional_nome,
                       pl.nome AS plano_nome,
                       s.nome AS servico_nome,
                       l.numero_lote,
                       l.status AS lote_status,
                       COALESCE(att.total_atendimentos, 0) AS total_atendimentos,
                       COALESCE(att.total_glosas, 0) AS total_glosas
                FROM guias g
                LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
                LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
                LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
                LEFT JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
                LEFT JOIN lotes l ON l.id = g.lote_id AND l.clinica_id = g.clinica_id
                LEFT JOIN (
                    SELECT guia_id,
                           COUNT(*) AS total_atendimentos,
                           SUM(CASE WHEN status_atendimento = \'Glosado\' THEN 1 ELSE 0 END) AS total_glosas
                    FROM atendimentos
                    WHERE clinica_id = :attendance_clinic_id
                    GROUP BY guia_id
                ) att ON att.guia_id = g.id
                ' . $whereSql . '
                ORDER BY g.data DESC, g.id DESC
                LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        $queryParams = $params;
        $queryParams[':attendance_clinic_id'] = $this->clinicId();

        foreach ($queryParams as $name => $value) {
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

    public function find(int $id, ?int $scopeProfessionalId = null): ?array
    {
        [$whereSql, $params] = $this->buildWhere(['selected' => $id], $scopeProfessionalId, true);

        $stmt = $this->pdo->prepare(
            'SELECT g.*,
                    pa.nome AS paciente_nome,
                    pa.telefone AS paciente_telefone,
                    pr.nome AS profissional_nome,
                    pl.nome AS plano_nome,
                    s.nome AS servico_nome,
                    COALESCE(att.total_atendimentos, 0) AS total_atendimentos,
                    COALESCE(att.total_glosas, 0) AS total_glosas
             FROM guias g
             LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
             LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
             LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
             LEFT JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
             LEFT JOIN (
                SELECT guia_id,
                       COUNT(*) AS total_atendimentos,
                       SUM(CASE WHEN status_atendimento = \'Glosado\' THEN 1 ELSE 0 END) AS total_glosas
                FROM atendimentos
                WHERE clinica_id = :attendance_clinic_id
                GROUP BY guia_id
             ) att ON att.guia_id = g.id
             ' . $whereSql . '
             LIMIT 1'
        );
        $params[':attendance_clinic_id'] = $this->clinicId();
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function optionLists(?int $scopeProfessionalId = null): array
    {
        $clinicId = $this->clinicId();
        $patientScope = $scopeProfessionalId !== null ? app_professional_scope_exists_for_patient('p.id') : '';
        $patients = $this->pdo->prepare('SELECT p.id, p.nome FROM pacientes p WHERE p.clinica_id = :clinic_id ' . $patientScope . ' ORDER BY p.nome');
        $patients->execute([':clinic_id' => $clinicId]);
        $plans = $this->pdo->prepare('SELECT id, nome FROM planos WHERE clinica_id = :clinic_id ORDER BY nome');
        $plans->execute([':clinic_id' => $clinicId]);
        $batches = $this->pdo->prepare('SELECT id, numero_lote, convenio, status FROM lotes WHERE clinica_id = :clinic_id ORDER BY data DESC, id DESC');
        $batches->execute([':clinic_id' => $clinicId]);

        if ($scopeProfessionalId !== null) {
            $professionals = $this->pdo->prepare('SELECT id, nome FROM profissionais WHERE clinica_id = :clinic_id AND id = :id ORDER BY nome');
            $professionals->execute([':clinic_id' => $clinicId, ':id' => $scopeProfessionalId]);
            $guideOptions = $this->pdo->prepare('SELECT id, codigo FROM guias WHERE clinica_id = :clinic_id AND profissional_id = :id ORDER BY codigo, id DESC');
            $guideOptions->execute([':clinic_id' => $clinicId, ':id' => $scopeProfessionalId]);
            $professionalRows = $professionals->fetchAll();
            $guideRows = $guideOptions->fetchAll();
        } else {
            $professionals = $this->pdo->prepare('SELECT id, nome FROM profissionais WHERE clinica_id = :clinic_id ORDER BY nome');
            $professionals->execute([':clinic_id' => $clinicId]);
            $guideOptions = $this->pdo->prepare('SELECT id, codigo FROM guias WHERE clinica_id = :clinic_id ORDER BY codigo, id DESC');
            $guideOptions->execute([':clinic_id' => $clinicId]);
            $professionalRows = $professionals->fetchAll();
            $guideRows = $guideOptions->fetchAll();
        }

        return [
            'patients' => $patients->fetchAll(),
            'plans' => $plans->fetchAll(),
            'batches' => $batches->fetchAll(),
            'professionals' => $professionalRows,
            'guides' => $guideRows,
        ];
    }

    public function codeExists(string $code, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM guias WHERE clinica_id = :clinic_id AND codigo = :codigo';
        $params = [':clinic_id' => $this->clinicId(), ':codigo' => $code];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignoreId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function nextCode(): string
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM guias WHERE clinica_id = :clinic_id');
        $stmt->execute([':clinic_id' => $this->clinicId()]);
        $maxId = (int) $stmt->fetchColumn();

        return 'GUIA-' . str_pad((string) ($maxId + 1), 4, '0', STR_PAD_LEFT);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO guias
                (clinica_id, codigo, paciente_id, plano_id, total_sessoes, data, valor_guia, recebido, profissional_id, servico_id, tipo_guia, lote_id, convenio, observacoes, autorizada, status_operacional)
             VALUES
                (:clinic_id, :codigo, :paciente_id, :plano_id, :total_sessoes, :data, :valor_guia, 0, :profissional_id, :servico_id, :tipo_guia, :lote_id, :convenio, :observacoes, :autorizada, :status_operacional)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':codigo' => $data['codigo'],
            ':paciente_id' => $data['paciente_id'],
            ':plano_id' => $data['plano_id'],
            ':total_sessoes' => $data['total_sessoes'],
            ':data' => $data['data'],
            ':valor_guia' => $data['valor_guia'],
            ':profissional_id' => $data['profissional_id'],
            ':servico_id' => $data['servico_id'],
            ':tipo_guia' => $data['tipo_guia'],
            ':lote_id' => $data['lote_id'],
            ':convenio' => $data['convenio'],
            ':observacoes' => $data['observacoes'],
            ':autorizada' => $data['autorizada'],
            ':status_operacional' => $data['status_operacional'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function linkPatientProfessional(int $patientId, int $professionalId, string $origin = 'guia'): void
    {
        if ($patientId <= 0 || $professionalId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO paciente_profissionais (clinica_id, paciente_id, profissional_id, origem)
             VALUES (:clinic_id, :paciente_id, :profissional_id, :origem)
             ON DUPLICATE KEY UPDATE origem = VALUES(origem)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':paciente_id' => $patientId,
            ':profissional_id' => $professionalId,
            ':origem' => $origin,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE guias
             SET codigo = :codigo,
                 paciente_id = :paciente_id,
                 plano_id = :plano_id,
                 total_sessoes = :total_sessoes,
                 data = :data,
                 valor_guia = :valor_guia,
                 profissional_id = :profissional_id,
                 servico_id = :servico_id,
                 tipo_guia = :tipo_guia,
                 lote_id = :lote_id,
                 convenio = :convenio,
                 observacoes = :observacoes,
                 autorizada = :autorizada,
                 status_operacional = :status_operacional
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $id,
            ':codigo' => $data['codigo'],
            ':paciente_id' => $data['paciente_id'],
            ':plano_id' => $data['plano_id'],
            ':total_sessoes' => $data['total_sessoes'],
            ':data' => $data['data'],
            ':valor_guia' => $data['valor_guia'],
            ':profissional_id' => $data['profissional_id'],
            ':servico_id' => $data['servico_id'],
            ':tipo_guia' => $data['tipo_guia'],
            ':lote_id' => $data['lote_id'],
            ':convenio' => $data['convenio'],
            ':observacoes' => $data['observacoes'],
            ':autorizada' => $data['autorizada'],
            ':status_operacional' => $data['status_operacional'],
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM guias WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $id]);
    }

    public function professionalHasService(int $professionalId, int $serviceId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT ps.servico_id
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

        return (bool) $stmt->fetchColumn();
    }

    public function servicePrice(int $serviceId, ?int $planId): float
    {
        $price = $this->servicePriceData($serviceId, $planId);

        return (float) ($price['valor'] ?? 0);
    }

    public function servicePriceData(int $serviceId, ?int $planId): array
    {
        if ($planId !== null && $planId > 0) {
            $stmt = $this->pdo->prepare(
                'SELECT valor, permite_alterar_guia
                 FROM servico_precos
                 WHERE clinica_id = :clinic_id
                   AND servico_id = :service_id
                   AND plano_id = :plan_id
                   AND ativo = 1
                 ORDER BY id DESC
                 LIMIT 1'
            );
            $stmt->execute([
                ':clinic_id' => $this->clinicId(),
                ':service_id' => $serviceId,
                ':plan_id' => $planId,
            ]);
            $row = $stmt->fetch();

            if ($row) {
                return [
                    'valor' => (float) ($row['valor'] ?? 0),
                    'permite_alterar_guia' => (int) ($row['permite_alterar_guia'] ?? 0),
                ];
            }

            return ['valor' => 0.0, 'permite_alterar_guia' => 0];
        }

        $stmt = $this->pdo->prepare(
            'SELECT valor, permite_alterar_guia
             FROM servico_precos
             WHERE clinica_id = :clinic_id
               AND servico_id = :service_id
               AND plano_id IS NULL
               AND ativo = 1
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':service_id' => $serviceId,
        ]);
        $row = $stmt->fetch();

        return [
            'valor' => (float) ($row['valor'] ?? 0),
            'permite_alterar_guia' => (int) ($row['permite_alterar_guia'] ?? 0),
        ];
    }

    public function countAttendances(int $guideId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM atendimentos WHERE clinica_id = :clinic_id AND guia_id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $guideId]);

        return (int) $stmt->fetchColumn();
    }

    public function receivableForGuide(int $guideId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM contas_receber
             WHERE clinica_id = :clinic_id AND origem_tipo = :origem_tipo AND origem_id = :origem_id
             LIMIT 1'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':origem_tipo' => 'guia',
            ':origem_id' => $guideId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function deleteGuideReceivable(int $guideId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM contas_receber WHERE clinica_id = :clinic_id AND origem_tipo = :origem_tipo AND origem_id = :origem_id');
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':origem_tipo' => 'guia',
            ':origem_id' => $guideId,
        ]);
    }

    public function updateGuideReceivableLink(int $guideId, ?int $receivableId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE guias
             SET conta_receber_gerada = :gerada, conta_receber_id = :receivable_id
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':gerada' => $receivableId !== null ? 1 : 0,
            ':receivable_id' => $receivableId,
            ':id' => $guideId,
        ]);
    }

    public function upsertGuideReceivable(int $guideId): void
    {
        $guide = $this->findGuideBillingData($guideId);

        if (!$guide) {
            return;
        }

        $description = 'Guia ' . ($guide['codigo'] ?: ('#' . $guide['id'])) . ' - ' . ($guide['paciente_nome'] ?: 'Sem paciente');
        $existing = $this->receivableForGuide($guideId);

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE contas_receber
                 SET descricao = :descricao,
                     valor = :valor,
                     vencimento = :vencimento,
                     profissional_id = :profissional_id
                 WHERE clinica_id = :clinic_id AND id = :id'
            );
            $stmt->execute([
                ':clinic_id' => $this->clinicId(),
                ':descricao' => $description,
                ':valor' => $guide['valor_guia'],
                ':vencimento' => $guide['data'],
                ':profissional_id' => $guide['profissional_id'],
                ':id' => $existing['id'],
            ]);
            $this->updateGuideReceivableLink($guideId, (int) $existing['id']);

            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO contas_receber
                (clinica_id, descricao, valor, vencimento, status, profissional_id, origem_tipo, origem_id)
             VALUES
                (:clinic_id, :descricao, :valor, :vencimento, :status, :profissional_id, :origem_tipo, :origem_id)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':descricao' => $description,
            ':valor' => $guide['valor_guia'],
            ':vencimento' => $guide['data'],
            ':status' => 'aberto',
            ':profissional_id' => $guide['profissional_id'],
            ':origem_tipo' => 'guia',
            ':origem_id' => $guideId,
        ]);
        $this->updateGuideReceivableLink($guideId, (int) $this->pdo->lastInsertId());
    }

    public function professionalCanEditGuides(int $professionalId): bool
    {
        $stmt = $this->pdo->prepare('SELECT permite_editar_guias FROM profissionais WHERE clinica_id = :clinic_id AND id = :id LIMIT 1');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $professionalId]);

        return (bool) $stmt->fetchColumn();
    }

    private function findGuideBillingData(int $guideId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.codigo, g.valor_guia, g.data, g.profissional_id, g.tipo_guia, pa.nome AS paciente_nome
             FROM guias g
             LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
             WHERE g.clinica_id = :clinic_id AND g.id = :id
             LIMIT 1'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $guideId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function buildWhere(array $filters, ?int $scopeProfessionalId = null, bool $forceSelected = false): array
    {
        $clauses = ['g.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];

        if ($scopeProfessionalId !== null) {
            $clauses[] = 'g.profissional_id = :scope_professional_id';
            $params[':scope_professional_id'] = $scopeProfessionalId;
        }

        $selected = (int) ($filters['selected'] ?? 0);

        if ($selected > 0 && $forceSelected) {
            $clauses[] = 'g.id = :selected_id';
            $params[':selected_id'] = $selected;
        } else {
            $guideId = (int) ($filters['guia_id'] ?? 0);
            $patientId = (int) ($filters['paciente_id'] ?? 0);
            $professionalId = (int) ($filters['profissional_id'] ?? 0);
            $search = trim((string) ($filters['busca'] ?? ''));
            $operationalStatus = app_normalize_guide_operational_status((string) ($filters['status_operacional'] ?? ''));

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

            if ($search !== '') {
                $clauses[] = '(g.codigo LIKE :search_code OR pa.nome LIKE :search_patient OR pr.nome LIKE :search_professional)';
                $params[':search_code'] = '%' . $search . '%';
                $params[':search_patient'] = '%' . $search . '%';
                $params[':search_professional'] = '%' . $search . '%';
            }

            if (($filters['status_operacional'] ?? '') !== '') {
                $clauses[] = $this->operationalStatusSql() . ' = :status_operacional';
                $params[':status_operacional'] = $operationalStatus;
            }
        }

        $whereSql = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';

        return [$whereSql, $params];
    }

    private function operationalStatusSql(): string
    {
        $usedSql = '(SELECT COUNT(*) FROM atendimentos ax WHERE ax.clinica_id = g.clinica_id AND ax.guia_id = g.id)';

        return "CASE
            WHEN COALESCE(g.status_operacional, 'aguardando_autorizacao') = 'cancelada' THEN 'cancelada'
            WHEN COALESCE(g.status_operacional, 'aguardando_autorizacao') = 'finalizada' OR (g.total_sessoes > 0 AND {$usedSql} >= g.total_sessoes) THEN 'finalizada'
            WHEN COALESCE(g.status_operacional, 'aguardando_autorizacao') = 'ultimas_sessoes' OR ({$usedSql} > 0 AND (g.total_sessoes - {$usedSql}) <= 2) THEN 'ultimas_sessoes'
            WHEN COALESCE(g.status_operacional, 'aguardando_autorizacao') = 'em_uso' OR {$usedSql} > 0 THEN 'em_uso'
            WHEN COALESCE(g.status_operacional, 'aguardando_autorizacao') = 'autorizada' OR g.autorizada = 1 THEN 'autorizada'
            WHEN COALESCE(g.status_operacional, 'aguardando_autorizacao') = 'aguardando_autorizacao' THEN 'aguardando_autorizacao'
            ELSE 'aguardando_autorizacao'
        END";
    }
}
