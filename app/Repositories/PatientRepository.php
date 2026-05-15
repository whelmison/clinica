<?php

namespace Clinic\Repositories;

use PDO;

final class PatientRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function clinicId(): int
    {
        return app_active_clinic_id();
    }

    public function dashboardRows(array $filters = []): array
    {
        $where = ['p.clinica_id = :clinic_id'];
        $having = [];
        $params = [':clinic_id' => $this->clinicId()];

        $patient = trim((string) ($filters['paciente'] ?? ''));
        $patientId = (int) ($filters['paciente_id'] ?? 0);
        $patientDigits = preg_replace('/\D+/', '', $patient);
        $plan = trim((string) ($filters['plano'] ?? ''));

        if ($patientId > 0) {
            $where[] = 'p.id = :paciente_id';
            $params[':paciente_id'] = $patientId;
        } elseif ($patient !== '') {
            $birthDateSql = "DATE_FORMAT(p.data_nascimento, '%d/%m/%Y')";
            $birthIsoSql = "DATE_FORMAT(p.data_nascimento, '%Y-%m-%d')";
            $search = [
                'p.nome LIKE :paciente',
                $birthDateSql . ' LIKE :paciente_birth_br',
                $birthIsoSql . ' LIKE :paciente_birth_iso',
            ];
            $params[':paciente'] = '%' . $patient . '%';
            $params[':paciente_birth_br'] = '%' . $patient . '%';
            $params[':paciente_birth_iso'] = '%' . $patient . '%';

            if (strlen($patientDigits) >= 2) {
                $cpfDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.cpf, ''), '.', ''), '-', ''), '/', ''), ' ', '')";
                $phoneDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
                $emergencyDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.telefone_emergencia, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
                $birthDigitsSql = "DATE_FORMAT(p.data_nascimento, '%d%m%Y')";
                $search[] = $cpfDigitsSql . ' LIKE :paciente_cpf_digits';
                $search[] = $phoneDigitsSql . ' LIKE :paciente_phone_digits';
                $search[] = $emergencyDigitsSql . ' LIKE :paciente_emergency_digits';
                $search[] = $birthDigitsSql . ' LIKE :paciente_birth_digits';
                $params[':paciente_cpf_digits'] = '%' . $patientDigits . '%';
                $params[':paciente_phone_digits'] = '%' . $patientDigits . '%';
                $params[':paciente_emergency_digits'] = '%' . $patientDigits . '%';
                $params[':paciente_birth_digits'] = '%' . $patientDigits . '%';
            }

            $where[] = '(' . implode(' OR ', $search) . ')';
        }

        if ($plan !== '') {
            $having[] = 'plano LIKE :plano';
            $params[':plano'] = '%' . $plan . '%';
        }

        $sql = 'SELECT
                p.id,
                p.nome,
                p.telefone,
                p.cpf,
                p.data_nascimento,
                p.telefone_emergencia,
                p.prontuario,
                p.dia_preferencia,
                p.horario_preferencia,
                (
                    SELECT pl.nome
                    FROM guias g
                    LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
                    WHERE g.clinica_id = p.clinica_id
                      AND g.paciente_id = p.id
                    ORDER BY g.id DESC
                    LIMIT 1
                ) AS plano,
                (
                    SELECT COUNT(*)
                    FROM guias g
                    WHERE g.clinica_id = p.clinica_id
                      AND g.paciente_id = p.id
                      AND (
                          SELECT COUNT(*)
                          FROM atendimentos a
                          WHERE a.clinica_id = g.clinica_id
                            AND a.guia_id = g.id
                      ) < g.total_sessoes
                ) AS abertas,
                (
                    SELECT MIN(
                        g.total_sessoes - (
                            SELECT COUNT(*)
                            FROM atendimentos a
                            WHERE a.clinica_id = g.clinica_id
                              AND a.guia_id = g.id
                        )
                    )
                    FROM guias g
                    WHERE g.clinica_id = p.clinica_id
                      AND g.paciente_id = p.id
                      AND (
                          SELECT COUNT(*)
                          FROM atendimentos a
                          WHERE a.clinica_id = g.clinica_id
                            AND a.guia_id = g.id
                      ) < g.total_sessoes
                ) AS menor_restante
             FROM pacientes p';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if ($having !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $having);
        }

        $sql .= ' ORDER BY p.nome';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $patientId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,
                    nome,
                    telefone,
                    cpf,
                    data_nascimento,
                    cep,
                    endereco,
                    numero,
                    complemento,
                    bairro,
                    cidade,
                    estado,
                    telefone_emergencia,
                    observacoes,
                    indicado_por,
                    prontuario,
                    dia_preferencia,
                    horario_preferencia
             FROM pacientes
             WHERE clinica_id = :clinic_id AND id = :id
             LIMIT 1'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $patientId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByDocument(string $document, ?int $ignorePatientId = null): ?array
    {
        $documentDigits = preg_replace('/\D+/', '', $document);

        if ($documentDigits === '') {
            return null;
        }

        $documentDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cpf, ''), '.', ''), '-', ''), '/', ''), ' ', '')";
        $sql = 'SELECT id, nome, cpf
                FROM pacientes
                WHERE clinica_id = :clinic_id
                  AND ' . $documentDigitsSql . ' = :document_digits';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':document_digits' => $documentDigits,
        ];

        if ($ignorePatientId !== null && $ignorePatientId > 0) {
            $sql .= ' AND id <> :ignore_id';
            $params[':ignore_id'] = $ignorePatientId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO pacientes (
                clinica_id,
                nome,
                telefone,
                cpf,
                data_nascimento,
                cep,
                endereco,
                numero,
                complemento,
                bairro,
                cidade,
                estado,
                telefone_emergencia,
                observacoes,
                indicado_por,
                prontuario,
                dia_preferencia,
                horario_preferencia
            ) VALUES (
                :clinic_id,
                :nome,
                :telefone,
                :cpf,
                :data_nascimento,
                :cep,
                :endereco,
                :numero,
                :complemento,
                :bairro,
                :cidade,
                :estado,
                :telefone_emergencia,
                :observacoes,
                :indicado_por,
                :prontuario,
                :dia_preferencia,
                :horario_preferencia
            )'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':nome' => $data['nome'],
            ':telefone' => $data['telefone'],
            ':cpf' => $data['cpf'],
            ':data_nascimento' => $data['data_nascimento'],
            ':cep' => $data['cep'],
            ':endereco' => $data['endereco'],
            ':numero' => $data['numero'],
            ':complemento' => $data['complemento'],
            ':bairro' => $data['bairro'],
            ':cidade' => $data['cidade'],
            ':estado' => $data['estado'],
            ':telefone_emergencia' => $data['telefone_emergencia'],
            ':observacoes' => $data['observacoes'],
            ':indicado_por' => $data['indicado_por'],
            ':prontuario' => $data['prontuario'],
            ':dia_preferencia' => $data['dia_preferencia'],
            ':horario_preferencia' => $data['horario_preferencia'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $patientId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pacientes
             SET nome = :nome,
                 telefone = :telefone,
                 cpf = :cpf,
                 data_nascimento = :data_nascimento,
                 cep = :cep,
                 endereco = :endereco,
                 numero = :numero,
                 complemento = :complemento,
                 bairro = :bairro,
                 cidade = :cidade,
                 estado = :estado,
                 telefone_emergencia = :telefone_emergencia,
                 observacoes = :observacoes,
                 indicado_por = :indicado_por,
                 prontuario = :prontuario,
                 dia_preferencia = :dia_preferencia,
                 horario_preferencia = :horario_preferencia
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $patientId,
            ':nome' => $data['nome'],
            ':telefone' => $data['telefone'],
            ':cpf' => $data['cpf'],
            ':data_nascimento' => $data['data_nascimento'],
            ':cep' => $data['cep'],
            ':endereco' => $data['endereco'],
            ':numero' => $data['numero'],
            ':complemento' => $data['complemento'],
            ':bairro' => $data['bairro'],
            ':cidade' => $data['cidade'],
            ':estado' => $data['estado'],
            ':telefone_emergencia' => $data['telefone_emergencia'],
            ':observacoes' => $data['observacoes'],
            ':indicado_por' => $data['indicado_por'],
            ':prontuario' => $data['prontuario'],
            ':dia_preferencia' => $data['dia_preferencia'],
            ':horario_preferencia' => $data['horario_preferencia'],
        ]);
    }
}
