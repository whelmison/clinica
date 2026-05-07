<?php

namespace Clinic\Repositories;

use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function clinicId(): int
    {
        return app_active_clinic_id();
    }

    private function rows(string $sql): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    public function filters(): array
    {
        return [
            'professionals' => $this->rows('SELECT id, nome FROM profissionais WHERE clinica_id = :clinic_id ORDER BY nome'),
            'patients' => $this->rows('SELECT id, nome FROM pacientes WHERE clinica_id = :clinic_id ORDER BY nome'),
            'guides' => $this->rows('SELECT id, codigo FROM guias WHERE clinica_id = :clinic_id ORDER BY codigo, id DESC'),
        ];
    }

    public function attendanceByPeriod(string $startDate, string $endDate, array $filters = []): array
    {
        $params = [':clinic_id' => $this->clinicId(), ':start_date' => $startDate, ':end_date' => $endDate];
        $where = ' WHERE a.clinica_id = :clinic_id AND a.data BETWEEN :start_date AND :end_date';

        if (!empty($filters['guia_id'])) {
            $where .= ' AND a.guia_id = :guia_id';
            $params[':guia_id'] = (int) $filters['guia_id'];
        }

        if (!empty($filters['paciente_id'])) {
            $where .= ' AND a.paciente_id = :paciente_id';
            $params[':paciente_id'] = (int) $filters['paciente_id'];
        }

        if (!empty($filters['profissional_id'])) {
            $where .= ' AND g.profissional_id = :profissional_id';
            $params[':profissional_id'] = (int) $filters['profissional_id'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.data,
                    COUNT(*) AS total_atendimentos,
                    COALESCE(SUM(a.valor), 0) AS total_valor
             FROM atendimentos a
             LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
             ' . $where . '
             GROUP BY a.data
             ORDER BY a.data DESC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function guidesByProfessional(array $filters = []): array
    {
        $params = [':clinic_id' => $this->clinicId()];
        $where = ' WHERE g.clinica_id = :clinic_id';

        if (!empty($filters['guia_id'])) {
            $where .= ' AND g.id = :guia_id';
            $params[':guia_id'] = (int) $filters['guia_id'];
        }

        if (!empty($filters['paciente_id'])) {
            $where .= ' AND g.paciente_id = :paciente_id';
            $params[':paciente_id'] = (int) $filters['paciente_id'];
        }

        if (!empty($filters['profissional_id'])) {
            $where .= ' AND g.profissional_id = :profissional_id';
            $params[':profissional_id'] = (int) $filters['profissional_id'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT pr.nome AS profissional_nome,
                    COUNT(g.id) AS total_guias,
                    COALESCE(SUM(g.valor_guia), 0) AS total_valor,
                    COALESCE(SUM(g.recebido), 0) AS total_recebido
             FROM guias g
             INNER JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
             ' . $where . '
             GROUP BY g.profissional_id
             ORDER BY pr.nome'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function billingSummary(string $startDate, string $endDate, array $filters = []): array
    {
        $params = [':clinic_id' => $this->clinicId(), ':start_date' => $startDate, ':end_date' => $endDate];
        $where = ' WHERE cr.clinica_id = :clinic_id AND cr.vencimento BETWEEN :start_date AND :end_date';

        if (!empty($filters['profissional_id'])) {
            $where .= ' AND cr.profissional_id = :profissional_id';
            $params[':profissional_id'] = (int) $filters['profissional_id'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT cr.status,
                    COUNT(cr.id) AS total_registros,
                    COALESCE(SUM(cr.valor), 0) AS total_valor
             FROM contas_receber cr
             ' . $where . '
             GROUP BY cr.status
             ORDER BY cr.status'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function scheduleSummary(string $startDate, string $endDate, array $filters = []): array
    {
        $params = [':clinic_id' => $this->clinicId(), ':start_date' => $startDate, ':end_date' => $endDate];
        $where = ' WHERE a.clinica_id = :clinic_id AND a.data_agendamento BETWEEN :start_date AND :end_date';

        if (!empty($filters['paciente_id'])) {
            $where .= ' AND a.cliente_id = :paciente_id';
            $params[':paciente_id'] = (int) $filters['paciente_id'];
        }

        if (!empty($filters['profissional_id'])) {
            $where .= ' AND a.profissional_id = :profissional_id';
            $params[':profissional_id'] = (int) $filters['profissional_id'];
        }

        if (!empty($filters['guia_id'])) {
            $where .= ' AND EXISTS (
                SELECT 1
                FROM guias g
                WHERE g.id = :guia_id
                  AND g.clinica_id = a.clinica_id
                  AND g.paciente_id = a.cliente_id
                  AND g.profissional_id = a.profissional_id
            )';
            $params[':guia_id'] = (int) $filters['guia_id'];
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.nome AS profissional_nome,
                    a.status,
                    COUNT(a.id) AS total_agendamentos
             FROM agenda a
             INNER JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
             ' . $where . '
             GROUP BY a.profissional_id, a.status
             ORDER BY p.nome, a.status'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function managerialScheduleOverview(string $startDate, string $endDate, ?int $professionalId = null): array
    {
        $params = [':clinic_id' => $this->clinicId(), ':start_date' => $startDate, ':end_date' => $endDate];
        $where = ' WHERE a.clinica_id = :clinic_id AND a.data_agendamento BETWEEN :start_date AND :end_date';

        if ($professionalId !== null && $professionalId > 0) {
            $where .= ' AND a.profissional_id = :profissional_id';
            $params[':profissional_id'] = $professionalId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(a.id) AS total_registros,
                    COALESCE(SUM(CASE WHEN a.status = \'realizado\' THEN 1 ELSE 0 END), 0) AS total_realizado,
                    COALESCE(SUM(CASE WHEN a.status = \'cancelado\' THEN 1 ELSE 0 END), 0) AS total_cancelado,
                    COALESCE(SUM(CASE WHEN a.status = \'agendado\' THEN 1 ELSE 0 END), 0) AS total_agendado,
                    COALESCE(SUM(CASE WHEN a.status = \'confirmado\' THEN 1 ELSE 0 END), 0) AS total_confirmado,
                    COALESCE(SUM(CASE WHEN a.status NOT IN (\'realizado\', \'cancelado\') THEN 1 ELSE 0 END), 0) AS total_nao_realizado
             FROM agenda a
             ' . $where
        );
        $stmt->execute($params);

        return $stmt->fetch() ?: [
            'total_registros' => 0,
            'total_realizado' => 0,
            'total_cancelado' => 0,
            'total_agendado' => 0,
            'total_confirmado' => 0,
            'total_nao_realizado' => 0,
        ];
    }

    public function managerialScheduleByProfessional(string $startDate, string $endDate, ?int $professionalId = null): array
    {
        $params = [':clinic_id' => $this->clinicId(), ':start_date' => $startDate, ':end_date' => $endDate];
        $where = ' WHERE a.clinica_id = :clinic_id AND a.data_agendamento BETWEEN :start_date AND :end_date';

        if ($professionalId !== null && $professionalId > 0) {
            $where .= ' AND a.profissional_id = :profissional_id';
            $params[':profissional_id'] = $professionalId;
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.id AS profissional_id,
                    p.nome AS profissional_nome,
                    COUNT(a.id) AS total_registros,
                    COALESCE(SUM(CASE WHEN a.status = \'realizado\' THEN 1 ELSE 0 END), 0) AS total_realizado,
                    COALESCE(SUM(CASE WHEN a.status = \'cancelado\' THEN 1 ELSE 0 END), 0) AS total_cancelado,
                    COALESCE(SUM(CASE WHEN a.status = \'agendado\' THEN 1 ELSE 0 END), 0) AS total_agendado,
                    COALESCE(SUM(CASE WHEN a.status = \'confirmado\' THEN 1 ELSE 0 END), 0) AS total_confirmado,
                    COALESCE(SUM(CASE WHEN a.status NOT IN (\'realizado\', \'cancelado\') THEN 1 ELSE 0 END), 0) AS total_nao_realizado
             FROM agenda a
             INNER JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
             ' . $where . '
             GROUP BY p.id, p.nome
             ORDER BY total_realizado DESC, total_cancelado DESC, p.nome ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
