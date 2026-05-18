<?php

namespace Clinic\Repositories;

use PDO;

final class ProfessionalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    private function clinicId(): int
    {
        return app_active_clinic_id();
    }

    public function services(): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, tempo_minutos FROM servicos WHERE clinica_id = :clinic_id AND ativo = 1 ORDER BY nome');
        $stmt->execute([':clinic_id' => $this->clinicId()]);

        return $stmt->fetchAll();
    }

    public function professionals(int $page, int $perPage, array $filters = []): array
    {
        $search = trim((string) ($filters['busca_profissional'] ?? ''));
        $searchDigits = preg_replace('/\D+/', '', $search);
        $professionalId = (int) ($filters['profissional_id'] ?? 0);
        $clauses = ['p.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];

        if ($professionalId > 0) {
            $clauses[] = 'p.id = :profissional_id';
            $params[':profissional_id'] = $professionalId;
        } elseif ($search !== '') {
            $phoneDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(p.telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '.', ''), '+', '')";
            $searchParts = [
                'p.nome LIKE :search_name_start',
                'p.nome LIKE :search_name_word',
                'p.profissao LIKE :search_profession',
                'p.telefone LIKE :search_phone',
            ];
            $params[':search_name_start'] = $search . '%';
            $params[':search_name_word'] = '% ' . $search . '%';
            $params[':search_profession'] = '%' . $search . '%';
            $params[':search_phone'] = '%' . $search . '%';

            if (strlen($searchDigits) >= 2) {
                $searchParts[] = $phoneDigitsSql . ' LIKE :search_phone_digits';
                $params[':search_phone_digits'] = '%' . $searchDigits . '%';
            }

            $clauses[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM profissionais p' . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = app_pagination($page, $perPage, $total, 'administrativo_profissionais.php', $filters, 'professional_page');

        $stmt = $this->pdo->prepare(
            'SELECT p.*,
                    COALESCE(GROUP_CONCAT(CONCAT(s.nome, " (", COALESCE(ps.tempo_minutos, s.tempo_minutos), " min)") ORDER BY s.nome SEPARATOR ", "), "") AS servicos
             FROM profissionais p
             LEFT JOIN profissional_servico ps ON ps.profissional_id = p.id AND ps.clinica_id = p.clinica_id
             LEFT JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = p.clinica_id
             ' . $where . '
             GROUP BY p.id
             ORDER BY p.nome
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

    public function findProfessional(int $professionalId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profissionais WHERE clinica_id = :clinic_id AND id = :id LIMIT 1');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $professionalId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function professionalServiceMap(int $professionalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT servico_id,
                    COALESCE(tempo_minutos, 0) AS tempo_minutos,
                    COALESCE(cobranca_tipo, \'percentual\') AS cobranca_tipo,
                    COALESCE(cobranca_valor, 0) AS cobranca_valor,
                    COALESCE(cobra_imposto, 0) AS cobra_imposto,
                    COALESCE(imposto_percentual, 0) AS imposto_percentual
             FROM profissional_servico
             WHERE clinica_id = :clinic_id AND profissional_id = :professional_id'
        );
        $stmt->execute([':clinic_id' => $this->clinicId(), ':professional_id' => $professionalId]);
        $map = [];

        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['servico_id']] = [
                'tempo_minutos' => (int) $row['tempo_minutos'],
                'cobranca_tipo' => (string) $row['cobranca_tipo'],
                'cobranca_valor' => (float) $row['cobranca_valor'],
                'cobra_imposto' => (int) $row['cobra_imposto'],
                'imposto_percentual' => (float) $row['imposto_percentual'],
            ];
        }

        return $map;
    }

    public function createProfessional(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO profissionais (clinica_id, nome, endereco, telefone, profissao, foto, permite_editar_guias, permite_secretaria_liberar_agenda, salario_fixo, comissao_percentual, imposto_fixo, imposto_percentual, mensagem_padrao_whatsapp)
             VALUES (:clinic_id, :nome, :endereco, :telefone, :profissao, :foto, :permite_editar_guias, :permite_secretaria_liberar_agenda, :salario_fixo, :comissao_percentual, :imposto_fixo, :imposto_percentual, :mensagem_padrao_whatsapp)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':nome' => $data['nome'],
            ':endereco' => $data['endereco'],
            ':telefone' => $data['telefone'],
            ':profissao' => $data['profissao'],
            ':foto' => $data['foto'] ?? null,
            ':permite_editar_guias' => $data['permite_editar_guias'],
            ':permite_secretaria_liberar_agenda' => $data['permite_secretaria_liberar_agenda'],
            ':salario_fixo' => $data['salario_fixo'] ?? 0,
            ':comissao_percentual' => $data['comissao_percentual'] ?? 0,
            ':imposto_fixo' => $data['imposto_fixo'] ?? 0,
            ':imposto_percentual' => $data['imposto_percentual'] ?? 0,
            ':mensagem_padrao_whatsapp' => $data['mensagem_padrao_whatsapp'] ?? '',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateProfessional(int $professionalId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE profissionais
             SET nome = :nome,
                 endereco = :endereco,
                 telefone = :telefone,
                 profissao = :profissao,
                 foto = :foto,
                 permite_editar_guias = :permite_editar_guias,
                 permite_secretaria_liberar_agenda = :permite_secretaria_liberar_agenda,
                 salario_fixo = :salario_fixo,
                 comissao_percentual = :comissao_percentual,
                 imposto_fixo = :imposto_fixo,
                 imposto_percentual = :imposto_percentual,
                 mensagem_padrao_whatsapp = :mensagem_padrao_whatsapp
             WHERE clinica_id = :clinic_id AND id = :id'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':id' => $professionalId,
            ':nome' => $data['nome'],
            ':endereco' => $data['endereco'],
            ':telefone' => $data['telefone'],
            ':profissao' => $data['profissao'],
            ':foto' => $data['foto'] ?? null,
            ':permite_editar_guias' => $data['permite_editar_guias'],
            ':permite_secretaria_liberar_agenda' => $data['permite_secretaria_liberar_agenda'],
            ':salario_fixo' => $data['salario_fixo'] ?? 0,
            ':comissao_percentual' => $data['comissao_percentual'] ?? 0,
            ':imposto_fixo' => $data['imposto_fixo'] ?? 0,
            ':imposto_percentual' => $data['imposto_percentual'] ?? 0,
            ':mensagem_padrao_whatsapp' => $data['mensagem_padrao_whatsapp'] ?? '',
        ]);
    }

    public function syncProfessionalServices(int $professionalId, array $services): void
    {
        $clinicId = $this->clinicId();
        $this->pdo->prepare('DELETE FROM profissional_servico WHERE clinica_id = :clinic_id AND profissional_id = :professional_id')->execute([
            ':clinic_id' => $clinicId,
            ':professional_id' => $professionalId,
        ]);

        if ($services === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO profissional_servico
                (clinica_id, profissional_id, servico_id, tempo_minutos, cobranca_tipo, cobranca_valor, cobra_imposto, imposto_percentual)
             VALUES
                (:clinic_id, :professional_id, :service_id, :tempo_minutos, :cobranca_tipo, :cobranca_valor, :cobra_imposto, :imposto_percentual)'
        );

        foreach ($services as $serviceId => $serviceData) {
            $stmt->execute([
                ':clinic_id' => $clinicId,
                ':professional_id' => $professionalId,
                ':service_id' => $serviceId,
                ':tempo_minutos' => $serviceData['tempo_minutos'],
                ':cobranca_tipo' => $serviceData['cobranca_tipo'],
                ':cobranca_valor' => $serviceData['cobranca_valor'],
                ':cobra_imposto' => $serviceData['cobra_imposto'],
                ':imposto_percentual' => $serviceData['imposto_percentual'],
            ]);
        }
    }

    public function professionalDependencies(int $professionalId): array
    {
        $queries = [
            'usuarios' => 'SELECT COUNT(*) FROM usuarios WHERE clinica_id = :clinic_id AND profissional_id = :id',
            'guias' => 'SELECT COUNT(*) FROM guias WHERE clinica_id = :clinic_id AND profissional_id = :id',
            'agenda' => 'SELECT COUNT(*) FROM agenda WHERE clinica_id = :clinic_id AND profissional_id = :id',
            'disponibilidade' => 'SELECT COUNT(*) FROM agenda_disponibilidade WHERE clinica_id = :clinic_id AND profissional_id = :id',
        ];
        $counts = [];

        foreach ($queries as $key => $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $professionalId]);
            $counts[$key] = (int) $stmt->fetchColumn();
        }

        return $counts;
    }

    public function deleteProfessional(int $professionalId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM profissionais WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $professionalId]);
    }

    public function users(int $page, int $perPage, array $filters = []): array
    {
        $search = trim((string) ($filters['busca_usuario'] ?? ''));
        $clauses = ['u.clinica_id = :clinic_id'];
        $params = [':clinic_id' => $this->clinicId()];

        if ($search !== '') {
            $clauses[] = '(u.login LIKE :search_login OR u.nome_exibicao LIKE :search_display OR p.nome LIKE :search_professional)';
            $params[':search_login'] = '%' . $search . '%';
            $params[':search_display'] = '%' . $search . '%';
            $params[':search_professional'] = '%' . $search . '%';
        }

        $where = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM usuarios u LEFT JOIN profissionais p ON p.id = u.profissional_id AND p.clinica_id = u.clinica_id' . $where);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $pagination = app_pagination($page, $perPage, $total, 'administrativo_usuarios.php', $filters, 'user_page');

        $stmt = $this->pdo->prepare(
             'SELECT u.*, p.nome AS profissional_nome
             FROM usuarios u
             LEFT JOIN profissionais p ON p.id = u.profissional_id AND p.clinica_id = u.clinica_id
             ' . $where . '
             ORDER BY u.login
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

    public function findUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE clinica_id = :clinic_id AND id = :id LIMIT 1');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function userByLogin(string $login, ?int $ignoreId = null): ?array
    {
        $sql = 'SELECT * FROM usuarios WHERE clinica_id = :clinic_id AND login = :login';
        $params = [
            ':clinic_id' => $this->clinicId(),
            ':login' => $login,
        ];

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

    public function createUser(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO usuarios (clinica_id, login, senha_hash, perfil, profissional_id, nome_exibicao, ativo)
             VALUES (:clinic_id, :login, :senha_hash, :perfil, :profissional_id, :nome_exibicao, :ativo)'
        );
        $stmt->execute([
            ':clinic_id' => $this->clinicId(),
            ':login' => $data['login'],
            ':senha_hash' => $data['senha_hash'],
            ':perfil' => $data['perfil'],
            ':profissional_id' => $data['profissional_id'],
            ':nome_exibicao' => $data['nome_exibicao'],
            ':ativo' => $data['ativo'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateUser(int $userId, array $data): void
    {
        $sql = 'UPDATE usuarios
                SET login = :login,
                    perfil = :perfil,
                    profissional_id = :profissional_id,
                    nome_exibicao = :nome_exibicao,
                    ativo = :ativo';
        $params = [
            ':id' => $userId,
            ':login' => $data['login'],
            ':perfil' => $data['perfil'],
            ':profissional_id' => $data['profissional_id'],
            ':nome_exibicao' => $data['nome_exibicao'],
            ':ativo' => $data['ativo'],
        ];

        if (!empty($data['senha_hash'])) {
            $sql .= ', senha_hash = :senha_hash';
            $params[':senha_hash'] = $data['senha_hash'];
        }

        $sql .= ' WHERE clinica_id = :clinic_id AND id = :id';
        $params[':clinic_id'] = $this->clinicId();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deleteUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM usuarios WHERE clinica_id = :clinic_id AND id = :id');
        $stmt->execute([':clinic_id' => $this->clinicId(), ':id' => $userId]);
    }
}
