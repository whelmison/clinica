<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_is_cli(): bool
{
    return PHP_SAPI === 'cli';
}

function app_auto_schema_enabled(): bool
{
    return in_array(strtolower((string) getenv('APP_AUTO_SCHEMA')), ['1', 'true', 'on', 'yes'], true);
}

function app_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_first_name(?string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    $parts = preg_split('/\s+/u', $value);

    return (string) ($parts[0] ?? $value);
}

function app_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function app_current_page(): string
{
    return basename($_SERVER['PHP_SELF'] ?? '');
}

function app_current_user(): ?array
{
    return $_SESSION['app_user'] ?? null;
}

function app_default_clinic_name(): string
{
    return 'CliniPs';
}

function app_current_clinic_id(): int
{
    $user = app_current_user();

    return max(0, (int) ($user['clinica_id'] ?? 0));
}

function app_active_clinic_id(): int
{
    $clinicId = app_current_clinic_id();

    return $clinicId > 0 ? $clinicId : 1;
}

function app_current_clinic_name(): string
{
    $user = app_current_user();
    $name = trim((string) ($user['clinica_nome'] ?? ''));

    return $name !== '' ? $name : app_default_clinic_name();
}

function app_current_profile(): ?string
{
    $user = app_current_user();

    return $user['perfil'] ?? null;
}

function app_current_professional_id(): ?int
{
    $user = app_current_user();

    if (!isset($user['profissional_id']) || $user['profissional_id'] === null || $user['profissional_id'] === '') {
        return null;
    }

    return (int) $user['profissional_id'];
}

function app_is_logged_in(): bool
{
    return app_current_user() !== null;
}

function app_is_developer(): bool
{
    return app_current_profile() === 'desenvolvedor';
}

function app_is_professional_user(): bool
{
    return app_current_profile() === 'profissional';
}

function app_has_any_role(array $roles): bool
{
    $profile = app_current_profile();

    if ($profile === null) {
        return false;
    }

    if ($profile === 'desenvolvedor') {
        return true;
    }

    return in_array($profile, $roles, true);
}

function app_flash(string $type, string $message): void
{
    $_SESSION['app_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function app_take_flash(): ?array
{
    if (!isset($_SESSION['app_flash'])) {
        return null;
    }

    $flash = $_SESSION['app_flash'];
    unset($_SESSION['app_flash']);

    return $flash;
}

function app_login_user(array $user): void
{
    $_SESSION['app_user'] = [
        'id' => (int) $user['id'],
        'clinica_id' => max(1, (int) ($user['clinica_id'] ?? 1)),
        'clinica_nome' => trim((string) ($user['clinica_nome'] ?? '')) ?: app_default_clinic_name(),
        'login' => $user['login'],
        'perfil' => $user['perfil'],
        'profissional_id' => ($user['profissional_id'] ?? null) !== null ? (int) $user['profissional_id'] : null,
        'nome_exibicao' => $user['nome_exibicao'],
    ];
}

function app_logout_user(): void
{
    unset($_SESSION['app_user']);
}

function app_profile_home(?string $profile = null): string
{
    $profile = $profile ?? app_current_profile();

    return match ($profile) {
        'secretaria' => 'secretaria.php',
        'administrativo' => 'administrativo.php',
        'desenvolvedor' => 'desenvolvedor.php',
        default => 'index.php',
    };
}

function app_page_access_map(): array
{
    return [
        'login.php' => ['public'],
        'logout.php' => ['auth'],
        'index.php' => ['profissional', 'desenvolvedor'],
        'meu_cadastro.php' => ['profissional', 'desenvolvedor'],
        'atendimentos.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'novo_atendimento.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'editar_atendimento.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'excluir_atendimento.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'toggle_glosa.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'buscar_guias.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'guia_modal_dados.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'pacientes_busca.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'paciente_historico.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'paciente_fichas.php' => ['profissional'],
        'guias.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'financeiro.php' => ['desenvolvedor'],
        'financeiro_profissional.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'financeiro_mensal.php' => ['desenvolvedor'],
        'financeiro_mensal_dados.php' => ['desenvolvedor'],
        'financeiro_mensal_api.php' => ['desenvolvedor'],
        'recebimentos.php' => ['desenvolvedor'],
        'administrativo.php' => ['administrativo', 'desenvolvedor'],
        'administrativo_profissionais.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'administrativo_clinica.php' => ['administrativo', 'desenvolvedor'],
        'novo_profissional.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'editar_profissional.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'administrativo_usuarios.php' => ['administrativo', 'desenvolvedor'],
        'administrativo_permissoes.php' => ['administrativo', 'desenvolvedor'],
        'administrativo_financeiro.php' => ['administrativo', 'desenvolvedor'],
        'financeiro_plano_contas.php' => ['administrativo', 'desenvolvedor'],
        'financeiro_centros_custo.php' => ['administrativo', 'desenvolvedor'],
        'financeiro_contas_financeiras.php' => ['administrativo', 'desenvolvedor'],
        'novo_plano_contas.php' => ['administrativo', 'desenvolvedor'],
        'editar_plano_contas.php' => ['administrativo', 'desenvolvedor'],
        'financeiro_contas_pagar.php' => ['administrativo', 'desenvolvedor'],
        'nova_conta_pagar.php' => ['administrativo', 'desenvolvedor'],
        'editar_conta_pagar.php' => ['administrativo', 'desenvolvedor'],
        'financeiro_contas_receber.php' => ['administrativo', 'desenvolvedor'],
        'nova_conta_receber.php' => ['administrativo', 'desenvolvedor'],
        'editar_conta_receber.php' => ['administrativo', 'desenvolvedor'],
        'relatorio_financeiro_fechamento.php' => ['administrativo', 'desenvolvedor'],
        'administrativo_lotes.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'secretaria.php' => ['secretaria', 'desenvolvedor'],
        'secretaria_agenda.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'secretaria_agenda_grupo.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'agenda_liberacao.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'agenda_lista_agendamentos.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'agenda_relatorio_gerencial.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'secretaria_servicos.php' => ['secretaria', 'desenvolvedor'],
        'novo_servico.php' => ['secretaria', 'desenvolvedor'],
        'editar_servico.php' => ['secretaria', 'desenvolvedor'],
        'gestao_guias.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'nova_guia_gestao.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'editar_guia_gestao.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'relatorios.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'buscar_servicos_profissional.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'desenvolvedor.php' => ['desenvolvedor'],
        'nova_guia.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'editar_guia.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'excluir_guia.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'baixar_guia.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'pacientes.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'novo_paciente.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'editar_paciente.php' => ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'],
        'excluir_paciente.php' => ['secretaria', 'administrativo', 'desenvolvedor'],
        'planos.php' => ['administrativo', 'desenvolvedor'],
        'editar_plano.php' => ['administrativo', 'desenvolvedor'],
        'excluir_plano.php' => ['administrativo', 'desenvolvedor'],
        'buscar_plano.php' => ['administrativo', 'desenvolvedor'],
        'novo_recebimento.php' => ['administrativo', 'desenvolvedor'],
    ];
}

function app_profile_matches_access(array $roles): bool
{
    $profile = app_current_profile();

    return $profile !== null && in_array($profile, $roles, true);
}

function app_forced_profile_access_pages(): array
{
    return [
        'profissional' => ['index.php', 'meu_cadastro.php', 'secretaria_agenda.php', 'agenda_liberacao.php', 'atendimentos.php', 'pacientes.php', 'novo_paciente.php', 'editar_paciente.php', 'pacientes_busca.php', 'paciente_historico.php', 'paciente_fichas.php', 'guias.php', 'financeiro_profissional.php', 'gestao_guias.php', 'nova_guia_gestao.php', 'editar_guia_gestao.php'],
        'secretaria' => ['secretaria.php'],
        'administrativo' => ['administrativo.php', 'administrativo_clinica.php', 'administrativo_permissoes.php'],
        'desenvolvedor' => ['desenvolvedor.php', 'administrativo_clinica.php', 'administrativo_permissoes.php'],
    ];
}

function app_ensure_profile_permissions_schema(mysqli $conn): void
{
    $conn->query('CREATE TABLE IF NOT EXISTS perfil_permissoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        perfil VARCHAR(30) NOT NULL,
        pagina VARCHAR(160) NOT NULL,
        permitido TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_clinica_perfil_pagina (clinica_id, perfil, pagina),
        INDEX idx_perfil_permissoes_clinica (clinica_id),
        INDEX idx_perfil_permissoes_perfil (perfil)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $defaultClinicId = app_ensure_default_clinic($conn);
    app_ensure_column($conn, 'perfil_permissoes', 'clinica_id', 'INT NOT NULL DEFAULT 1');
    $conn->query("UPDATE perfil_permissoes SET clinica_id = {$defaultClinicId} WHERE clinica_id IS NULL OR clinica_id <= 0");
    app_drop_index_if_exists($conn, 'perfil_permissoes', 'uq_perfil_pagina');
    app_ensure_index($conn, 'perfil_permissoes', 'uq_clinica_perfil_pagina', 'CREATE UNIQUE INDEX uq_clinica_perfil_pagina ON perfil_permissoes (clinica_id, perfil, pagina)');
    app_ensure_index($conn, 'perfil_permissoes', 'idx_perfil_permissoes_clinica', 'CREATE INDEX idx_perfil_permissoes_clinica ON perfil_permissoes (clinica_id)');
}

function app_effective_page_access_map(mysqli $conn): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $map = app_page_access_map();

    if (!app_table_exists($conn, 'perfil_permissoes')) {
        return $cache = $map;
    }

    $rows = app_stmt_all($conn, 'SELECT perfil, pagina, permitido FROM perfil_permissoes WHERE clinica_id = ?', 'i', [app_active_clinic_id()]);

    if ($rows === []) {
        return $cache = $map;
    }

    $customProfiles = [];

    foreach ($rows as $row) {
        $profile = (string) ($row['perfil'] ?? '');

        if ($profile !== '' && !in_array($profile, $customProfiles, true)) {
            $customProfiles[] = $profile;
        }
    }

    foreach ($customProfiles as $profile) {
        foreach ($map as $page => $roles) {
            if (in_array('public', $roles, true) || in_array('auth', $roles, true)) {
                continue;
            }

            $map[$page] = array_values(array_filter(
                $roles,
                static fn (string $role): bool => $role !== $profile
            ));
        }
    }

    foreach ($rows as $row) {
        if ((int) ($row['permitido'] ?? 0) !== 1) {
            continue;
        }

        $profile = (string) ($row['perfil'] ?? '');
        $page = (string) ($row['pagina'] ?? '');

        if ($profile === '' || !isset($map[$page])) {
            continue;
        }

        if ($page === 'paciente_fichas.php' && $profile !== 'profissional') {
            continue;
        }

        if ($profile === 'profissional' && in_array($page, [
            'excluir_paciente.php',
            'excluir_guia.php',
            'baixar_guia.php',
            'financeiro.php',
            'financeiro_mensal.php',
            'financeiro_mensal_dados.php',
            'financeiro_mensal_api.php',
            'recebimentos.php',
            'administrativo_lotes.php',
            'financeiro_contas_receber.php',
            'financeiro_contas_pagar.php',
            'toggle_glosa.php',
            'novo_atendimento.php',
            'editar_atendimento.php',
            'excluir_atendimento.php',
            'agenda_lista_agendamentos.php',
            'agenda_relatorio_gerencial.php',
            'secretaria_agenda_grupo.php',
        ], true)) {
            continue;
        }

        if (in_array('public', $map[$page], true) || in_array('auth', $map[$page], true)) {
            continue;
        }

        if (!in_array($profile, $map[$page], true)) {
            $map[$page][] = $profile;
        }
    }

    foreach (app_forced_profile_access_pages() as $profile => $pages) {
        foreach ($pages as $page) {
            if (!isset($map[$page]) || in_array($profile, $map[$page], true)) {
                continue;
            }

            $map[$page][] = $profile;
        }
    }

    return $cache = $map;
}

function app_table_exists(mysqli $conn, string $table): bool
{
    $database = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->bind_param('ss', $database, $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function app_column_exists(mysqli $conn, string $table, string $column): bool
{
    $database = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?');
    $stmt->bind_param('sss', $database, $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function app_index_exists(mysqli $conn, string $table, string $index): bool
{
    $database = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';
    $stmt = $conn->prepare('SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?');
    $stmt->bind_param('sss', $database, $table, $index);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['total'] ?? 0) > 0;
}

function app_ensure_column(mysqli $conn, string $table, string $column, string $definition): void
{
    if (!app_column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function app_ensure_index(mysqli $conn, string $table, string $index, string $sql): void
{
    if (!app_index_exists($conn, $table, $index)) {
        $conn->query($sql);
    }
}

function app_drop_index_if_exists(mysqli $conn, string $table, string $index): void
{
    if (app_index_exists($conn, $table, $index)) {
        $conn->query("ALTER TABLE `$table` DROP INDEX `$index`");
    }
}

function app_drop_single_login_unique_indexes(mysqli $conn): void
{
    if (!app_table_exists($conn, 'usuarios')) {
        return;
    }

    $database = $conn->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '';
    $stmt = $conn->prepare(
        "SELECT index_name
         FROM information_schema.statistics
         WHERE table_schema = ? AND table_name = 'usuarios' AND non_unique = 0 AND index_name <> 'PRIMARY'
         GROUP BY index_name
         HAVING COUNT(*) = 1 AND SUM(CASE WHEN column_name = 'login' THEN 1 ELSE 0 END) = 1"
    );
    $stmt->bind_param('s', $database);
    $stmt->execute();
    $result = $stmt->get_result();
    $indexes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    foreach ($indexes as $index) {
        $indexName = (string) ($index['index_name'] ?? '');

        if ($indexName !== '') {
            app_drop_index_if_exists($conn, 'usuarios', $indexName);
        }
    }
}

function app_schema_cnpj_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?: '';
}

function app_schema_format_cnpj(?string $value): ?string
{
    $digits = app_schema_cnpj_digits($value);

    if (strlen($digits) !== 14) {
        return null;
    }

    return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
}

function app_normalize_clinic_cnpjs(mysqli $conn): void
{
    if (!app_table_exists($conn, 'clinicas') || !app_column_exists($conn, 'clinicas', 'cnpj_digits')) {
        return;
    }

    $result = $conn->query('SELECT id, cnpj FROM clinicas ORDER BY id');

    if (!$result) {
        return;
    }

    $seen = [];

    while ($row = $result->fetch_assoc()) {
        $clinicId = (int) ($row['id'] ?? 0);
        $digits = app_schema_cnpj_digits((string) ($row['cnpj'] ?? ''));
        $formatted = app_schema_format_cnpj($digits);

        if ($clinicId <= 0 || $formatted === null || isset($seen[$digits])) {
            app_stmt_execute($conn, 'UPDATE clinicas SET cnpj = NULL, cnpj_digits = NULL WHERE id = ?', 'i', [$clinicId]);
            continue;
        }

        $seen[$digits] = true;
        app_stmt_execute($conn, 'UPDATE clinicas SET cnpj = ?, cnpj_digits = ? WHERE id = ?', 'ssi', [$formatted, $digits, $clinicId]);
    }
}

function app_ensure_clinics_table(mysqli $conn): void
{
    $conn->query('CREATE TABLE IF NOT EXISTS clinicas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome_fantasia VARCHAR(180) NOT NULL,
        razao_social VARCHAR(180) NULL,
        cnpj VARCHAR(20) NULL,
        cnpj_digits VARCHAR(14) NULL,
        telefone VARCHAR(30) NULL,
        whatsapp VARCHAR(30) NULL,
        email VARCHAR(180) NULL,
        endereco VARCHAR(255) NULL,
        cidade VARCHAR(120) NULL,
        estado VARCHAR(2) NULL,
        logotipo VARCHAR(255) NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        liberada TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_clinicas_ativo_nome (ativo, nome_fantasia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    app_ensure_column($conn, 'clinicas', 'cnpj', 'VARCHAR(20) NULL');
    app_ensure_column($conn, 'clinicas', 'cnpj_digits', 'VARCHAR(14) NULL');
    app_ensure_column($conn, 'clinicas', 'logotipo', 'VARCHAR(255) NULL');
    app_ensure_column($conn, 'clinicas', 'liberada', 'TINYINT(1) NOT NULL DEFAULT 1');
    app_normalize_clinic_cnpjs($conn);
    app_drop_index_if_exists($conn, 'clinicas', 'idx_clinicas_cnpj');
    app_ensure_index($conn, 'clinicas', 'uq_clinicas_cnpj', 'CREATE UNIQUE INDEX uq_clinicas_cnpj ON clinicas (cnpj)');
    app_ensure_index($conn, 'clinicas', 'uq_clinicas_cnpj_digits', 'CREATE UNIQUE INDEX uq_clinicas_cnpj_digits ON clinicas (cnpj_digits)');
    app_ensure_index($conn, 'clinicas', 'idx_clinicas_liberada_nome', 'CREATE INDEX idx_clinicas_liberada_nome ON clinicas (liberada, nome_fantasia)');
}

function app_ensure_default_clinic(mysqli $conn): int
{
    app_ensure_clinics_table($conn);

    $row = app_stmt_one($conn, 'SELECT id FROM clinicas ORDER BY id ASC LIMIT 1');

    if ($row) {
        return (int) $row['id'];
    }

    app_stmt_execute(
        $conn,
        'INSERT INTO clinicas (nome_fantasia, ativo, liberada) VALUES (?, 1, 1)',
        's',
        [app_default_clinic_name()]
    );

    return (int) $conn->insert_id;
}

function app_ensure_multiclinic_schema(mysqli $conn, ?int $defaultClinicId = null): int
{
    $defaultClinicId = $defaultClinicId ?: app_ensure_default_clinic($conn);
    $definition = 'INT NOT NULL DEFAULT ' . $defaultClinicId;
    $tables = [
        'usuarios',
        'pacientes',
        'paciente_profissionais',
        'profissionais',
        'servicos',
        'profissional_servico',
        'planos',
        'guias',
        'atendimentos',
        'agenda',
        'agenda_disponibilidade',
        'lotes',
        'recebimentos',
        'config',
        'paciente_fichas_avaliacao',
        'paciente_fichas_evolucao',
        'contas_pagar',
        'contas_receber',
        'contas_financeiras',
        'centros_custo',
        'plano_contas',
    ];

    foreach ($tables as $table) {
        if (!app_table_exists($conn, $table)) {
            continue;
        }

        app_ensure_column($conn, $table, 'clinica_id', $definition);
        $conn->query("UPDATE `$table` SET clinica_id = {$defaultClinicId} WHERE clinica_id IS NULL OR clinica_id <= 0");
        app_ensure_index($conn, $table, 'idx_' . $table . '_clinica', "CREATE INDEX idx_{$table}_clinica ON `$table` (clinica_id)");
    }

    if (app_table_exists($conn, 'usuarios')) {
        app_drop_single_login_unique_indexes($conn);
        app_ensure_index($conn, 'usuarios', 'uq_usuarios_clinica_login', 'CREATE UNIQUE INDEX uq_usuarios_clinica_login ON usuarios (clinica_id, login)');
    }

    return $defaultClinicId;
}

function app_financial_plan_seed_rows(): array
{
    return [
        ['codigo' => 'R.001', 'nome' => 'Consultas e atendimentos', 'tipo' => 'receita', 'descricao' => 'Receitas assistenciais da clinica.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 10, 'categoria_pai_codigo' => null],
        ['codigo' => 'R.001.001', 'nome' => 'Avaliacao inicial', 'tipo' => 'receita', 'descricao' => 'Avaliacoes e consultas iniciais.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 11, 'categoria_pai_codigo' => 'R.001'],
        ['codigo' => 'R.001.002', 'nome' => 'Sessoes particulares', 'tipo' => 'receita', 'descricao' => 'Atendimentos particulares da rotina clinica.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 12, 'categoria_pai_codigo' => 'R.001'],
        ['codigo' => 'R.001.003', 'nome' => 'Pacotes terapicos', 'tipo' => 'receita', 'descricao' => 'Pacotes e programas de tratamento.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 13, 'categoria_pai_codigo' => 'R.001'],
        ['codigo' => 'R.001.004', 'nome' => 'Atendimento domiciliar', 'tipo' => 'receita', 'descricao' => 'Receitas de atendimentos externos e domiciliares.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 14, 'categoria_pai_codigo' => 'R.001'],
        ['codigo' => 'R.002', 'nome' => 'Procedimentos e pacotes', 'tipo' => 'receita', 'descricao' => 'Receitas de procedimentos, programas e vendas clinicas.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 20, 'categoria_pai_codigo' => null],
        ['codigo' => 'R.002.001', 'nome' => 'Pilates e programas continuos', 'tipo' => 'receita', 'descricao' => 'Programas recorrentes e acompanhamentos continuos.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 21, 'categoria_pai_codigo' => 'R.002'],
        ['codigo' => 'R.002.002', 'nome' => 'Procedimentos complementares', 'tipo' => 'receita', 'descricao' => 'Procedimentos adicionais cobrados a parte.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 22, 'categoria_pai_codigo' => 'R.002'],
        ['codigo' => 'R.002.003', 'nome' => 'Venda de produtos', 'tipo' => 'receita', 'descricao' => 'Produtos, materiais e itens de apoio vendidos ao paciente.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 23, 'categoria_pai_codigo' => 'R.002'],
        ['codigo' => 'R.003', 'nome' => 'Convenios e faturamento', 'tipo' => 'receita', 'descricao' => 'Recebimentos relacionados a convenios, guias e lotes.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 30, 'categoria_pai_codigo' => null],
        ['codigo' => 'R.003.001', 'nome' => 'Guias particulares geradas', 'tipo' => 'receita', 'descricao' => 'Titulos gerados automaticamente para guias.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 31, 'categoria_pai_codigo' => 'R.003'],
        ['codigo' => 'R.003.002', 'nome' => 'Faturamento por lote', 'tipo' => 'receita', 'descricao' => 'Receitas de lotes e faturamento TISS.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 32, 'categoria_pai_codigo' => 'R.003'],
        ['codigo' => 'R.003.003', 'nome' => 'Reembolso de convenio', 'tipo' => 'receita', 'descricao' => 'Recebimentos de convenio e repasses administrativos.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 33, 'categoria_pai_codigo' => 'R.003'],
        ['codigo' => 'R.004', 'nome' => 'Outras receitas', 'tipo' => 'receita', 'descricao' => 'Receitas acessorias e administrativas.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 40, 'categoria_pai_codigo' => null],
        ['codigo' => 'R.004.001', 'nome' => 'Aluguel de sala', 'tipo' => 'receita', 'descricao' => 'Locacao de sala e uso de espaco.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 41, 'categoria_pai_codigo' => 'R.004'],
        ['codigo' => 'R.004.002', 'nome' => 'Taxas administrativas recebidas', 'tipo' => 'receita', 'descricao' => 'Taxas cobradas de parceiros e terceiros.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 42, 'categoria_pai_codigo' => 'R.004'],
        ['codigo' => 'D.001', 'nome' => 'Folha e repasses profissionais', 'tipo' => 'despesa', 'descricao' => 'Despesas com equipe assistencial, repasses e pro-labore.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 110, 'categoria_pai_codigo' => null],
        ['codigo' => 'D.001.001', 'nome' => 'Salarios e beneficios', 'tipo' => 'despesa', 'descricao' => 'Folha fixa, beneficios e encargos trabalhistas.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 111, 'categoria_pai_codigo' => 'D.001'],
        ['codigo' => 'D.001.002', 'nome' => 'Repasse de profissionais', 'tipo' => 'despesa', 'descricao' => 'Comissoes, honorarios e repasses medicos.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 112, 'categoria_pai_codigo' => 'D.001'],
        ['codigo' => 'D.001.003', 'nome' => 'Terceirizados e plantoes', 'tipo' => 'despesa', 'descricao' => 'Prestadores terceirizados e coberturas de agenda.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 113, 'categoria_pai_codigo' => 'D.001'],
        ['codigo' => 'D.002', 'nome' => 'Estrutura e operacao', 'tipo' => 'despesa', 'descricao' => 'Custos fixos e operacionais da estrutura fisica da clinica.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 120, 'categoria_pai_codigo' => null],
        ['codigo' => 'D.002.001', 'nome' => 'Aluguel e condominio', 'tipo' => 'despesa', 'descricao' => 'Aluguel da unidade, condominio e taxas prediais.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 121, 'categoria_pai_codigo' => 'D.002'],
        ['codigo' => 'D.002.002', 'nome' => 'Energia, agua e internet', 'tipo' => 'despesa', 'descricao' => 'Utilidades e servicos essenciais.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 122, 'categoria_pai_codigo' => 'D.002'],
        ['codigo' => 'D.002.003', 'nome' => 'Limpeza e conservacao', 'tipo' => 'despesa', 'descricao' => 'Limpeza, lavanderia, manutencao e higienizacao.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 123, 'categoria_pai_codigo' => 'D.002'],
        ['codigo' => 'D.002.004', 'nome' => 'Recepcao e apoio', 'tipo' => 'despesa', 'descricao' => 'Despesas administrativas de recepcao e apoio ao paciente.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 124, 'categoria_pai_codigo' => 'D.002'],
        ['codigo' => 'D.003', 'nome' => 'Tributos e taxas', 'tipo' => 'despesa', 'descricao' => 'Impostos, encargos financeiros e taxas operacionais.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 130, 'categoria_pai_codigo' => null],
        ['codigo' => 'D.003.001', 'nome' => 'Impostos e contribuicoes', 'tipo' => 'despesa', 'descricao' => 'ISS, DAS, impostos e contribuicoes obrigatorias.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 131, 'categoria_pai_codigo' => 'D.003'],
        ['codigo' => 'D.003.002', 'nome' => 'Taxas bancarias', 'tipo' => 'despesa', 'descricao' => 'Tarifas de banco, boletos e cobrancas.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 132, 'categoria_pai_codigo' => 'D.003'],
        ['codigo' => 'D.003.003', 'nome' => 'Taxas de cartao', 'tipo' => 'despesa', 'descricao' => 'Antecipacao, MDR e bandeiras de cartao.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 133, 'categoria_pai_codigo' => 'D.003'],
        ['codigo' => 'D.004', 'nome' => 'Marketing e aquisicao', 'tipo' => 'despesa', 'descricao' => 'Investimentos em marketing, captacao e relacionamento.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 140, 'categoria_pai_codigo' => null],
        ['codigo' => 'D.004.001', 'nome' => 'Anuncios e trafego pago', 'tipo' => 'despesa', 'descricao' => 'Campanhas em Google, Meta e afins.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 141, 'categoria_pai_codigo' => 'D.004'],
        ['codigo' => 'D.004.002', 'nome' => 'Design e conteudo', 'tipo' => 'despesa', 'descricao' => 'Criacao, videos, social media e materiais promocionais.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 142, 'categoria_pai_codigo' => 'D.004'],
        ['codigo' => 'D.005', 'nome' => 'Materiais e suprimentos', 'tipo' => 'despesa', 'descricao' => 'Itens clinicos, administrativos e de estoque.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 150, 'categoria_pai_codigo' => null],
        ['codigo' => 'D.005.001', 'nome' => 'Materiais clinicos', 'tipo' => 'despesa', 'descricao' => 'Descartaveis, insumos e materiais assistenciais.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 151, 'categoria_pai_codigo' => 'D.005'],
        ['codigo' => 'D.005.002', 'nome' => 'Escritorio e recepcao', 'tipo' => 'despesa', 'descricao' => 'Papelaria, impressos e itens de recepcao.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 152, 'categoria_pai_codigo' => 'D.005'],
        ['codigo' => 'D.005.003', 'nome' => 'Estoque e produtos', 'tipo' => 'despesa', 'descricao' => 'Compra de produtos para revenda ou uso interno.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 153, 'categoria_pai_codigo' => 'D.005'],
        ['codigo' => 'D.006', 'nome' => 'Tecnologia e sistemas', 'tipo' => 'despesa', 'descricao' => 'Softwares, licencas, suporte e equipamentos.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 160, 'categoria_pai_codigo' => null],
        ['codigo' => 'D.006.001', 'nome' => 'Sistema da clinica', 'tipo' => 'despesa', 'descricao' => 'ERP, prontuario, agenda e licencas recorrentes.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 161, 'categoria_pai_codigo' => 'D.006'],
        ['codigo' => 'D.006.002', 'nome' => 'Equipamentos e manutencao', 'tipo' => 'despesa', 'descricao' => 'Compra e manutencao de equipamentos.', 'ativo' => 1, 'aceita_lancamento' => 1, 'ordem_exibicao' => 162, 'categoria_pai_codigo' => 'D.006'],
    ];
}

function app_financial_seed_plan_accounts(mysqli $conn, ?int $clinicId = null): void
{
    $clinicId = $clinicId ?: app_ensure_default_clinic($conn);

    foreach (app_financial_plan_seed_rows() as $item) {
        $existing = app_stmt_one($conn, 'SELECT id FROM plano_contas WHERE codigo = ? AND clinica_id = ? LIMIT 1', 'si', [$item['codigo'], $clinicId]);

        if ($existing) {
            continue;
        }

        $parentId = null;
        if (!empty($item['categoria_pai_codigo'])) {
            $parent = app_stmt_one($conn, 'SELECT id FROM plano_contas WHERE codigo = ? AND clinica_id = ? LIMIT 1', 'si', [$item['categoria_pai_codigo'], $clinicId]);
            $parentId = $parent ? (int) $parent['id'] : null;
        }

        app_stmt_execute(
            $conn,
            'INSERT INTO plano_contas (clinica_id, codigo, nome, tipo, categoria_pai_id, descricao, ativo, aceita_lancamento, ordem_exibicao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            'isssisiii',
            [
                $clinicId,
                $item['codigo'],
                $item['nome'],
                $item['tipo'],
                $parentId,
                $item['descricao'],
                (int) $item['ativo'],
                (int) $item['aceita_lancamento'],
                (int) $item['ordem_exibicao'],
            ]
        );
    }
}

function app_financial_seed_cost_centers(mysqli $conn, ?int $clinicId = null): void
{
    $clinicId = $clinicId ?: app_ensure_default_clinic($conn);
    $rows = [
        ['nome' => 'Recepcao', 'descricao' => 'Operacao de recepcao e atendimento inicial do paciente.'],
        ['nome' => 'Assistencial', 'descricao' => 'Atendimentos, procedimentos e servicos assistenciais.'],
        ['nome' => 'Administrativo', 'descricao' => 'Financeiro, RH, compras e gestao interna.'],
        ['nome' => 'Convenios', 'descricao' => 'Fluxo de guias, lotes, glosas e faturamento TISS.'],
        ['nome' => 'Marketing', 'descricao' => 'Captacao, campanhas e relacionamento com pacientes.'],
        ['nome' => 'Estoque', 'descricao' => 'Produtos, materiais clinicos e suprimentos.'],
    ];

    foreach ($rows as $item) {
        $existing = app_stmt_one($conn, 'SELECT id FROM centros_custo WHERE nome = ? AND clinica_id = ? LIMIT 1', 'si', [$item['nome'], $clinicId]);

        if ($existing) {
            continue;
        }

        app_stmt_execute(
            $conn,
            'INSERT INTO centros_custo (clinica_id, nome, descricao, ativo) VALUES (?, ?, ?, 1)',
            'iss',
            [$clinicId, $item['nome'], $item['descricao']]
        );
    }
}

function app_financial_seed_accounts(mysqli $conn, ?int $clinicId = null): void
{
    $clinicId = $clinicId ?: app_ensure_default_clinic($conn);
    $rows = [
        ['nome' => 'Caixa principal', 'tipo' => 'caixa', 'instituicao' => 'Clinica', 'saldo_inicial' => 0, 'cor' => '#1f7a8c'],
        ['nome' => 'Conta bancaria principal', 'tipo' => 'banco', 'instituicao' => 'Banco principal', 'saldo_inicial' => 0, 'cor' => '#2e7d32'],
        ['nome' => 'Carteira PIX', 'tipo' => 'pix', 'instituicao' => 'PIX', 'saldo_inicial' => 0, 'cor' => '#0ea5a2'],
        ['nome' => 'Recebiveis de cartao', 'tipo' => 'cartao', 'instituicao' => 'Operadoras', 'saldo_inicial' => 0, 'cor' => '#7a4ff6'],
    ];

    foreach ($rows as $item) {
        $existing = app_stmt_one($conn, 'SELECT id FROM contas_financeiras WHERE nome = ? AND clinica_id = ? LIMIT 1', 'si', [$item['nome'], $clinicId]);

        if ($existing) {
            continue;
        }

        app_stmt_execute(
            $conn,
            'INSERT INTO contas_financeiras (clinica_id, nome, tipo, instituicao, saldo_inicial, cor, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)',
            'isssds',
            [$clinicId, $item['nome'], $item['tipo'], $item['instituicao'], (float) $item['saldo_inicial'], $item['cor']]
        );
    }
}

function app_patient_sheet_detail_columns(): array
{
    return [
        'postura_observacoes_gerais',
        'postura_cabeca',
        'postura_ombros',
        'postura_coluna',
        'postura_pelve',
        'postura_membros',
        'amplitude_cervical',
        'amplitude_ombro',
        'amplitude_cotovelo',
        'amplitude_punho_mao',
        'amplitude_coluna',
        'amplitude_quadril',
        'amplitude_joelho',
        'amplitude_tornozelo_pe',
        'forca_cervicais',
        'forca_ombro',
        'forca_cotovelo',
        'forca_punho_mao',
        'forca_coluna',
        'forca_quadril',
        'forca_joelho',
        'forca_tornozelo_pe',
        'sensibilidade_tatil',
        'sensibilidade_termica',
        'sensibilidade_dolorosa',
        'equilibrio_estatico',
        'equilibrio_dinamico',
        'marcha',
        'avd_higiene',
        'avd_vestir',
        'avd_alimentacao',
        'avd_locomocao',
        'avd_outras',
    ];
}

function app_ensure_patient_sheet_detail_schema(mysqli $conn): void
{
    if (!app_table_exists($conn, 'paciente_fichas_avaliacao')) {
        return;
    }

    foreach (app_patient_sheet_detail_columns() as $column) {
        app_ensure_column($conn, 'paciente_fichas_avaliacao', $column, 'VARCHAR(180) NULL');
    }
}

function app_install_schema(mysqli $conn): void
{
    static $installed = false;

    if ($installed) {
        return;
    }

    $installed = true;
    $defaultClinicId = app_ensure_default_clinic($conn);

    $conn->query('CREATE TABLE IF NOT EXISTS pacientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        telefone VARCHAR(30) NULL,
        cpf VARCHAR(20) NULL,
        data_nascimento DATE NULL,
        cep VARCHAR(9) NULL,
        endereco VARCHAR(255) NULL,
        numero VARCHAR(30) NULL,
        complemento VARCHAR(120) NULL,
        bairro VARCHAR(120) NULL,
        cidade VARCHAR(120) NULL,
        estado VARCHAR(2) NULL,
        telefone_emergencia VARCHAR(30) NULL,
        observacoes TEXT NULL,
        indicado_por VARCHAR(180) NULL,
        convenio VARCHAR(120) NULL,
        valor_sessao DECIMAL(10,2) DEFAULT 0,
        prontuario VARCHAR(120) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS planos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(120) NOT NULL,
        valor_sessao DECIMAL(10,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS profissionais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        endereco VARCHAR(255) NULL,
        telefone VARCHAR(30) NULL,
        profissao VARCHAR(120) NULL,
        foto VARCHAR(255) NULL,
        permite_editar_guias TINYINT(1) NOT NULL DEFAULT 0,
        permite_secretaria_liberar_agenda TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        login VARCHAR(120) NOT NULL,
        senha_hash VARCHAR(255) NOT NULL,
        perfil VARCHAR(30) NOT NULL,
        profissional_id INT NULL,
        nome_exibicao VARCHAR(255) NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        usuario_padrao TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_usuarios_clinica_login (clinica_id, login),
        INDEX idx_usuarios_clinica (clinica_id),
        INDEX idx_usuarios_perfil (perfil),
        INDEX idx_usuarios_profissional (profissional_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS servicos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(180) NOT NULL,
        tempo_minutos INT NOT NULL,
        tipo_agendamento VARCHAR(20) NOT NULL DEFAULT \'individual\',
        capacidade_agendamento INT NOT NULL DEFAULT 1,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS paciente_profissionais (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        paciente_id INT NOT NULL,
        profissional_id INT NOT NULL,
        origem VARCHAR(40) NOT NULL DEFAULT \'manual\',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_paciente_profissional (clinica_id, paciente_id, profissional_id),
        INDEX idx_paciente_profissionais_profissional (clinica_id, profissional_id),
        INDEX idx_paciente_profissionais_paciente (clinica_id, paciente_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS servico_precos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        servico_id INT NOT NULL,
        plano_id INT NULL,
        forma_pagamento VARCHAR(80) NOT NULL DEFAULT \'Tabela\',
        valor DECIMAL(10,2) NOT NULL DEFAULT 0,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        permite_alterar_guia TINYINT(1) NOT NULL DEFAULT 0,
        observacoes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS profissional_servico (
        clinica_id INT NOT NULL DEFAULT 1,
        profissional_id INT NOT NULL,
        servico_id INT NOT NULL,
        tempo_minutos INT NULL,
        cobranca_tipo VARCHAR(20) NOT NULL DEFAULT \'percentual\',
        cobranca_valor DECIMAL(10,2) NOT NULL DEFAULT 0,
        cobra_imposto TINYINT(1) NOT NULL DEFAULT 0,
        imposto_percentual DECIMAL(5,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (profissional_id, servico_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS guias (
        id INT AUTO_INCREMENT PRIMARY KEY,
        codigo VARCHAR(60) NULL,
        paciente_id INT NOT NULL,
        plano_id INT NULL,
        total_sessoes INT NOT NULL DEFAULT 1,
        data DATE NOT NULL,
        valor_guia DECIMAL(10,2) NOT NULL DEFAULT 0,
        recebido DECIMAL(10,2) NOT NULL DEFAULT 0,
        sessoes_usadas INT NOT NULL DEFAULT 0,
        profissional_id INT NULL,
        servico_id INT NULL,
        tipo_guia VARCHAR(30) NULL,
        lote_id INT NULL,
        convenio VARCHAR(120) NULL,
        conta_receber_gerada TINYINT(1) NOT NULL DEFAULT 0,
        conta_receber_id INT NULL,
        autorizada TINYINT(1) NOT NULL DEFAULT 0,
        status_operacional VARCHAR(30) NOT NULL DEFAULT \'aguardando_autorizacao\',
        observacoes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS atendimentos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        paciente_id INT NOT NULL,
        data DATE NOT NULL,
        tipo VARCHAR(80) NULL,
        status VARCHAR(50) NULL,
        valor DECIMAL(10,2) DEFAULT 0,
        pago VARCHAR(30) NULL,
        guia_id INT NULL,
        agenda_id INT NULL,
        status_atendimento VARCHAR(50) NOT NULL DEFAULT \'Realizado\',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS agenda (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_agendamento DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fim TIME NOT NULL,
        profissional_id INT NOT NULL,
        servico_id INT NOT NULL,
        cliente_id INT NULL,
        cliente_nome VARCHAR(255) NOT NULL,
        cliente_telefone VARCHAR(30) NULL,
        status VARCHAR(30) NOT NULL DEFAULT \'agendado\',
        observacoes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS agenda_disponibilidade (
        id INT AUTO_INCREMENT PRIMARY KEY,
        profissional_id INT NOT NULL,
        data_disponivel DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fim TIME NOT NULL,
        observacoes TEXT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS agenda_grupos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        profissional_id INT NOT NULL,
        servico_id INT NOT NULL,
        data_agendamento DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fim TIME NOT NULL,
        capacidade INT NOT NULL DEFAULT 1,
        observacoes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS agenda_grupo_pacientes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        grupo_id INT NOT NULL,
        paciente_id INT NOT NULL,
        guia_id INT NULL,
        atendimento_id INT NULL,
        status VARCHAR(30) NOT NULL DEFAULT \'agendado\',
        observacoes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS paciente_fichas_avaliacao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        paciente_id INT NOT NULL,
        profissional_id INT NULL,
        data_avaliacao DATE NULL,
        sexo VARCHAR(30) NULL,
        queixa_principal TEXT NULL,
        historia_pregressa TEXT NULL,
        historia_atual TEXT NULL,
        lesoes_previas TEXT NULL,
        historia_cirurgica TEXT NULL,
        avaliacao_postura TEXT NULL,
        amplitude_movimento TEXT NULL,
        forca_muscular TEXT NULL,
        sensibilidade TEXT NULL,
        equilibrio_marcha TEXT NULL,
        avds TEXT NULL,
        objetivos TEXT NULL,
        condutas TEXT NULL,
        observacoes_finais TEXT NULL,
        assinatura_fisioterapeuta VARCHAR(180) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS paciente_fichas_evolucao (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clinica_id INT NOT NULL DEFAULT 1,
        paciente_id INT NOT NULL,
        atendimento_id INT NULL,
        profissional_id INT NULL,
        data_evolucao DATE NULL,
        condutas_observacoes TEXT NULL,
        assinatura_fisioterapeuta VARCHAR(180) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS plano_contas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(180) NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        categoria_pai_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS centros_custo (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(180) NOT NULL,
        descricao TEXT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS contas_financeiras (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(180) NOT NULL,
        tipo VARCHAR(30) NOT NULL DEFAULT \'banco\',
        instituicao VARCHAR(120) NULL,
        agencia VARCHAR(30) NULL,
        conta_numero VARCHAR(40) NULL,
        saldo_inicial DECIMAL(10,2) NOT NULL DEFAULT 0,
        cor VARCHAR(20) NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS contas_pagar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        vencimento DATE NOT NULL,
        pagamento DATE NULL,
        status VARCHAR(30) NOT NULL DEFAULT \'aberto\',
        plano_conta_id INT NULL,
        origem_tipo VARCHAR(30) NULL,
        origem_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS contas_receber (
        id INT AUTO_INCREMENT PRIMARY KEY,
        descricao VARCHAR(255) NOT NULL,
        valor DECIMAL(10,2) NOT NULL,
        vencimento DATE NOT NULL,
        recebimento DATE NULL,
        status VARCHAR(30) NOT NULL DEFAULT \'aberto\',
        plano_conta_id INT NULL,
        profissional_id INT NULL,
        origem_tipo VARCHAR(30) NULL,
        origem_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $conn->query('CREATE TABLE IF NOT EXISTS lotes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero_lote VARCHAR(50) NULL,
        convenio VARCHAR(180) NOT NULL,
        data DATE NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT \'aberto\',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    app_ensure_column($conn, 'pacientes', 'telefone', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'pacientes', 'cpf', 'VARCHAR(20) NULL');
    @$conn->query('ALTER TABLE pacientes MODIFY cpf VARCHAR(20) NULL');
    app_ensure_column($conn, 'pacientes', 'data_nascimento', 'DATE NULL');
    app_ensure_column($conn, 'pacientes', 'cep', 'VARCHAR(9) NULL');
    app_ensure_column($conn, 'pacientes', 'endereco', 'VARCHAR(255) NULL');
    app_ensure_column($conn, 'pacientes', 'numero', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'pacientes', 'complemento', 'VARCHAR(120) NULL');
    app_ensure_column($conn, 'pacientes', 'bairro', 'VARCHAR(120) NULL');
    app_ensure_column($conn, 'pacientes', 'cidade', 'VARCHAR(120) NULL');
    app_ensure_column($conn, 'pacientes', 'estado', 'VARCHAR(2) NULL');
    app_ensure_column($conn, 'pacientes', 'telefone_emergencia', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'pacientes', 'observacoes', 'TEXT NULL');
    app_ensure_column($conn, 'pacientes', 'indicado_por', 'VARCHAR(180) NULL');
    app_ensure_column($conn, 'pacientes', 'convenio', 'VARCHAR(120) NULL');
    app_ensure_column($conn, 'pacientes', 'valor_sessao', 'DECIMAL(10,2) DEFAULT 0');
    app_ensure_column($conn, 'pacientes', 'prontuario', 'VARCHAR(120) NULL');
    app_ensure_column($conn, 'pacientes', 'dia_preferencia', 'VARCHAR(50) NULL');
    app_ensure_column($conn, 'pacientes', 'horario_preferencia', 'VARCHAR(20) NULL');
    app_ensure_column($conn, 'paciente_profissionais', 'clinica_id', 'INT NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'paciente_profissionais', 'paciente_id', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'paciente_profissionais', 'profissional_id', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'paciente_profissionais', 'origem', 'VARCHAR(40) NOT NULL DEFAULT \'manual\'');
    app_ensure_column($conn, 'planos', 'valor_sessao', 'DECIMAL(10,2) DEFAULT 0');
    app_ensure_column($conn, 'usuarios', 'usuario_padrao', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_index($conn, 'usuarios', 'idx_usuarios_padrao', 'CREATE INDEX idx_usuarios_padrao ON usuarios (usuario_padrao)');
    app_ensure_column($conn, 'profissionais', 'permite_editar_guias', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissionais', 'permite_secretaria_liberar_agenda', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissionais', 'salario_fixo', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissionais', 'comissao_percentual', 'DECIMAL(5,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissionais', 'imposto_fixo', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissionais', 'imposto_percentual', 'DECIMAL(5,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissionais', 'mensagem_padrao_whatsapp', 'TEXT NULL');
    app_ensure_column($conn, 'profissionais', 'foto', 'VARCHAR(255) NULL');
    app_ensure_column($conn, 'servicos', 'tipo_agendamento', 'VARCHAR(20) NOT NULL DEFAULT \'individual\'');
    app_ensure_column($conn, 'servicos', 'capacidade_agendamento', 'INT NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'servico_precos', 'clinica_id', 'INT NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'servico_precos', 'servico_id', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'servico_precos', 'plano_id', 'INT NULL');
    app_ensure_column($conn, 'servico_precos', 'forma_pagamento', 'VARCHAR(80) NOT NULL DEFAULT \'Tabela\'');
    app_ensure_column($conn, 'servico_precos', 'valor', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'servico_precos', 'ativo', 'TINYINT(1) NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'servico_precos', 'permite_alterar_guia', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'servico_precos', 'observacoes', 'TEXT NULL');
    app_ensure_column($conn, 'servico_precos', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    app_ensure_column($conn, 'profissional_servico', 'clinica_id', 'INT NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'profissional_servico', 'profissional_id', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissional_servico', 'servico_id', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissional_servico', 'tempo_minutos', 'INT NULL');
    app_ensure_column($conn, 'profissional_servico', 'cobranca_tipo', 'VARCHAR(20) NOT NULL DEFAULT \'percentual\'');
    app_ensure_column($conn, 'profissional_servico', 'cobranca_valor', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissional_servico', 'cobra_imposto', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'profissional_servico', 'imposto_percentual', 'DECIMAL(5,2) NOT NULL DEFAULT 0');
    $conn->query("UPDATE profissional_servico ps
        INNER JOIN profissionais p ON p.id = ps.profissional_id AND p.clinica_id = ps.clinica_id
        SET ps.cobranca_tipo = 'percentual',
            ps.cobranca_valor = p.comissao_percentual,
            ps.cobra_imposto = CASE WHEN p.imposto_percentual > 0 OR p.imposto_fixo > 0 THEN 1 ELSE ps.cobra_imposto END,
            ps.imposto_percentual = p.imposto_percentual
        WHERE (ps.cobranca_valor IS NULL OR ps.cobranca_valor = 0)
          AND p.comissao_percentual > 0");

    $hadGuideAuthorizationColumn = app_column_exists($conn, 'guias', 'autorizada');
    $hadGuideOperationalStatusColumn = app_column_exists($conn, 'guias', 'status_operacional');

    app_ensure_column($conn, 'guias', 'codigo', 'VARCHAR(60) NULL');
    app_ensure_column($conn, 'guias', 'paciente_id', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'guias', 'plano_id', 'INT NULL');
    app_ensure_column($conn, 'guias', 'total_sessoes', 'INT NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'guias', 'data', 'DATE NULL');
    app_ensure_column($conn, 'guias', 'valor_guia', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'guias', 'recebido', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'guias', 'sessoes_usadas', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'guias', 'profissional_id', 'INT NULL');
    app_ensure_column($conn, 'guias', 'servico_id', 'INT NULL');
    app_ensure_column($conn, 'guias', 'tipo_guia', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'guias', 'lote_id', 'INT NULL');
    app_ensure_column($conn, 'guias', 'convenio', 'VARCHAR(120) NULL');
    app_ensure_column($conn, 'guias', 'conta_receber_gerada', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'guias', 'conta_receber_id', 'INT NULL');
    app_ensure_column($conn, 'guias', 'autorizada', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'guias', 'status_operacional', 'VARCHAR(30) NOT NULL DEFAULT \'aguardando_autorizacao\'');
    app_ensure_column($conn, 'guias', 'observacoes', 'TEXT NULL');

    if (!$hadGuideAuthorizationColumn) {
        $conn->query('UPDATE guias SET autorizada = 1');
    }

    if (!$hadGuideOperationalStatusColumn) {
        $conn->query("UPDATE guias g
            LEFT JOIN (
                SELECT clinica_id, guia_id, COUNT(*) AS usadas
                FROM atendimentos
                GROUP BY clinica_id, guia_id
            ) a ON a.guia_id = g.id AND a.clinica_id = g.clinica_id
            SET g.status_operacional = CASE
                WHEN g.total_sessoes > 0 AND COALESCE(a.usadas, 0) >= g.total_sessoes THEN 'finalizada'
                WHEN COALESCE(a.usadas, 0) > 0 AND (g.total_sessoes - COALESCE(a.usadas, 0)) <= 2 THEN 'ultimas_sessoes'
                WHEN COALESCE(a.usadas, 0) > 0 THEN 'em_uso'
                WHEN g.autorizada = 1 THEN 'autorizada'
                ELSE 'aguardando_autorizacao'
            END");
    }
    $conn->query("UPDATE guias SET status_operacional = 'aguardando_autorizacao' WHERE status_operacional IS NULL OR status_operacional = '' OR status_operacional = 'criada'");
    @$conn->query("ALTER TABLE guias ALTER status_operacional SET DEFAULT 'aguardando_autorizacao'");

    app_ensure_column($conn, 'atendimentos', 'guia_id', 'INT NULL');
    app_ensure_column($conn, 'atendimentos', 'agenda_id', 'INT NULL');
    app_ensure_column($conn, 'atendimentos', 'status_atendimento', 'VARCHAR(50) NOT NULL DEFAULT \'Realizado\'');
    app_ensure_column($conn, 'agenda', 'cliente_id', 'INT NULL');
    app_ensure_column($conn, 'agenda', 'cliente_nome', 'VARCHAR(255) NOT NULL DEFAULT \'\'');
    app_ensure_column($conn, 'agenda', 'cliente_telefone', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'agenda', 'observacoes', 'TEXT NULL');
    app_ensure_column($conn, 'agenda', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    app_ensure_column($conn, 'lotes', 'numero_lote', 'VARCHAR(50) NULL');
    app_ensure_column($conn, 'lotes', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    app_ensure_column($conn, 'plano_contas', 'codigo', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'plano_contas', 'descricao', 'TEXT NULL');
    app_ensure_column($conn, 'plano_contas', 'ativo', 'TINYINT(1) NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'plano_contas', 'aceita_lancamento', 'TINYINT(1) NOT NULL DEFAULT 1');
    app_ensure_column($conn, 'plano_contas', 'ordem_exibicao', 'INT NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'contas_pagar', 'competencia', 'DATE NULL');
    app_ensure_column($conn, 'contas_pagar', 'valor_pago', 'DECIMAL(10,2) NULL');
    app_ensure_column($conn, 'contas_pagar', 'juros', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'contas_pagar', 'multa', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'contas_pagar', 'desconto', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'contas_pagar', 'forma_pagamento', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'contas_pagar', 'conta_financeira_id', 'INT NULL');
    app_ensure_column($conn, 'contas_pagar', 'centro_custo_id', 'INT NULL');
    app_ensure_column($conn, 'contas_pagar', 'numero_documento', 'VARCHAR(80) NULL');
    app_ensure_column($conn, 'contas_pagar', 'favorecido', 'VARCHAR(180) NULL');
    app_ensure_column($conn, 'contas_pagar', 'observacoes', 'TEXT NULL');
    app_ensure_column($conn, 'contas_receber', 'competencia', 'DATE NULL');
    app_ensure_column($conn, 'contas_receber', 'valor_recebido', 'DECIMAL(10,2) NULL');
    app_ensure_column($conn, 'contas_receber', 'juros', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'contas_receber', 'multa', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'contas_receber', 'desconto', 'DECIMAL(10,2) NOT NULL DEFAULT 0');
    app_ensure_column($conn, 'contas_receber', 'forma_pagamento', 'VARCHAR(30) NULL');
    app_ensure_column($conn, 'contas_receber', 'conta_financeira_id', 'INT NULL');
    app_ensure_column($conn, 'contas_receber', 'centro_custo_id', 'INT NULL');
    app_ensure_column($conn, 'contas_receber', 'numero_documento', 'VARCHAR(80) NULL');
    app_ensure_column($conn, 'contas_receber', 'fonte_pagadora', 'VARCHAR(180) NULL');
    app_ensure_column($conn, 'contas_receber', 'paciente_id', 'INT NULL');
    app_ensure_column($conn, 'contas_receber', 'observacoes', 'TEXT NULL');

    $defaultClinicId = app_ensure_multiclinic_schema($conn, $defaultClinicId);
    $conn->query("INSERT IGNORE INTO paciente_profissionais (clinica_id, paciente_id, profissional_id, origem)
        SELECT DISTINCT clinica_id, paciente_id, profissional_id, 'guia'
        FROM guias
        WHERE paciente_id > 0 AND profissional_id > 0");
    $conn->query("INSERT IGNORE INTO paciente_profissionais (clinica_id, paciente_id, profissional_id, origem)
        SELECT DISTINCT clinica_id, cliente_id, profissional_id, 'agenda'
        FROM agenda
        WHERE cliente_id > 0 AND profissional_id > 0");
    $conn->query("INSERT IGNORE INTO paciente_profissionais (clinica_id, paciente_id, profissional_id, origem)
        SELECT DISTINCT clinica_id, paciente_id, profissional_id, 'ficha'
        FROM paciente_fichas_avaliacao
        WHERE paciente_id > 0 AND profissional_id > 0");
    $conn->query("INSERT IGNORE INTO paciente_profissionais (clinica_id, paciente_id, profissional_id, origem)
        SELECT DISTINCT clinica_id, paciente_id, profissional_id, 'evolucao'
        FROM paciente_fichas_evolucao
        WHERE paciente_id > 0 AND profissional_id > 0");
    app_ensure_profile_permissions_schema($conn);
    app_ensure_patient_sheet_detail_schema($conn);

    app_ensure_index($conn, 'guias', 'idx_guias_paciente', 'CREATE INDEX idx_guias_paciente ON guias (paciente_id)');
    app_ensure_index($conn, 'guias', 'idx_guias_profissional', 'CREATE INDEX idx_guias_profissional ON guias (profissional_id)');
    app_ensure_index($conn, 'guias', 'idx_guias_servico', 'CREATE INDEX idx_guias_servico ON guias (servico_id)');
    app_ensure_index($conn, 'guias', 'idx_guias_lote', 'CREATE INDEX idx_guias_lote ON guias (lote_id)');
    app_ensure_index($conn, 'atendimentos', 'idx_atendimentos_guia', 'CREATE INDEX idx_atendimentos_guia ON atendimentos (guia_id)');
    app_ensure_index($conn, 'atendimentos', 'idx_atendimentos_agenda', 'CREATE UNIQUE INDEX idx_atendimentos_agenda ON atendimentos (agenda_id)');
    app_ensure_index($conn, 'agenda', 'idx_agenda_profissional_data', 'CREATE INDEX idx_agenda_profissional_data ON agenda (profissional_id, data_agendamento)');
    app_ensure_index($conn, 'agenda', 'idx_agenda_cliente', 'CREATE INDEX idx_agenda_cliente ON agenda (cliente_id)');
    app_ensure_index($conn, 'agenda_disponibilidade', 'idx_agenda_disponibilidade_profissional_data', 'CREATE INDEX idx_agenda_disponibilidade_profissional_data ON agenda_disponibilidade (profissional_id, data_disponivel)');
    app_ensure_index($conn, 'agenda_grupos', 'idx_agenda_grupos_clinica_prof_data', 'CREATE INDEX idx_agenda_grupos_clinica_prof_data ON agenda_grupos (clinica_id, profissional_id, data_agendamento)');
    app_ensure_index($conn, 'agenda_grupos', 'uq_agenda_grupos_slot', 'CREATE UNIQUE INDEX uq_agenda_grupos_slot ON agenda_grupos (clinica_id, profissional_id, servico_id, data_agendamento, hora_inicio)');
    app_ensure_index($conn, 'agenda_grupo_pacientes', 'idx_agenda_grupo_pacientes_grupo', 'CREATE INDEX idx_agenda_grupo_pacientes_grupo ON agenda_grupo_pacientes (grupo_id)');
    app_ensure_index($conn, 'agenda_grupo_pacientes', 'uq_agenda_grupo_paciente', 'CREATE UNIQUE INDEX uq_agenda_grupo_paciente ON agenda_grupo_pacientes (clinica_id, grupo_id, paciente_id)');
    app_ensure_index($conn, 'agenda_grupo_pacientes', 'idx_agenda_grupo_pacientes_atendimento', 'CREATE INDEX idx_agenda_grupo_pacientes_atendimento ON agenda_grupo_pacientes (atendimento_id)');
    app_ensure_index($conn, 'servico_precos', 'idx_servico_precos_servico', 'CREATE INDEX idx_servico_precos_servico ON servico_precos (clinica_id, servico_id)');
    app_ensure_index($conn, 'servico_precos', 'idx_servico_precos_plano', 'CREATE INDEX idx_servico_precos_plano ON servico_precos (clinica_id, plano_id)');
    app_ensure_index($conn, 'profissional_servico', 'idx_profissional_servico_clinica_profissional', 'CREATE INDEX idx_profissional_servico_clinica_profissional ON profissional_servico (clinica_id, profissional_id)');
    app_ensure_index($conn, 'profissional_servico', 'idx_profissional_servico_clinica_servico', 'CREATE INDEX idx_profissional_servico_clinica_servico ON profissional_servico (clinica_id, servico_id)');
    app_ensure_index($conn, 'paciente_profissionais', 'uq_paciente_profissional', 'CREATE UNIQUE INDEX uq_paciente_profissional ON paciente_profissionais (clinica_id, paciente_id, profissional_id)');
    app_ensure_index($conn, 'paciente_profissionais', 'idx_paciente_profissionais_profissional', 'CREATE INDEX idx_paciente_profissionais_profissional ON paciente_profissionais (clinica_id, profissional_id)');
    app_ensure_index($conn, 'paciente_profissionais', 'idx_paciente_profissionais_paciente', 'CREATE INDEX idx_paciente_profissionais_paciente ON paciente_profissionais (clinica_id, paciente_id)');
    app_ensure_index($conn, 'paciente_fichas_avaliacao', 'idx_fichas_avaliacao_paciente', 'CREATE INDEX idx_fichas_avaliacao_paciente ON paciente_fichas_avaliacao (clinica_id, paciente_id, data_avaliacao)');
    app_ensure_index($conn, 'paciente_fichas_evolucao', 'idx_fichas_evolucao_paciente', 'CREATE INDEX idx_fichas_evolucao_paciente ON paciente_fichas_evolucao (clinica_id, paciente_id, data_evolucao)');
    app_ensure_index($conn, 'plano_contas', 'idx_plano_contas_tipo_nome', 'CREATE INDEX idx_plano_contas_tipo_nome ON plano_contas (tipo, nome)');
    app_ensure_index($conn, 'plano_contas', 'idx_plano_contas_codigo', 'CREATE INDEX idx_plano_contas_codigo ON plano_contas (codigo)');
    app_ensure_index($conn, 'centros_custo', 'idx_centros_custo_nome', 'CREATE INDEX idx_centros_custo_nome ON centros_custo (nome)');
    app_ensure_index($conn, 'contas_financeiras', 'idx_contas_financeiras_nome', 'CREATE INDEX idx_contas_financeiras_nome ON contas_financeiras (nome)');
    app_ensure_index($conn, 'contas_pagar', 'idx_contas_pagar_vencimento', 'CREATE INDEX idx_contas_pagar_vencimento ON contas_pagar (vencimento)');
    app_ensure_index($conn, 'contas_pagar', 'idx_contas_pagar_pagamento', 'CREATE INDEX idx_contas_pagar_pagamento ON contas_pagar (pagamento)');
    app_ensure_index($conn, 'contas_pagar', 'idx_contas_pagar_plano', 'CREATE INDEX idx_contas_pagar_plano ON contas_pagar (plano_conta_id)');
    app_ensure_index($conn, 'contas_pagar', 'idx_contas_pagar_centro', 'CREATE INDEX idx_contas_pagar_centro ON contas_pagar (centro_custo_id)');
    app_ensure_index($conn, 'contas_pagar', 'idx_contas_pagar_conta', 'CREATE INDEX idx_contas_pagar_conta ON contas_pagar (conta_financeira_id)');
    app_ensure_index($conn, 'contas_receber', 'idx_contas_receber_vencimento', 'CREATE INDEX idx_contas_receber_vencimento ON contas_receber (vencimento)');
    app_ensure_index($conn, 'contas_receber', 'idx_contas_receber_recebimento', 'CREATE INDEX idx_contas_receber_recebimento ON contas_receber (recebimento)');
    app_ensure_index($conn, 'contas_receber', 'idx_contas_receber_plano', 'CREATE INDEX idx_contas_receber_plano ON contas_receber (plano_conta_id)');
    app_ensure_index($conn, 'contas_receber', 'idx_contas_receber_centro', 'CREATE INDEX idx_contas_receber_centro ON contas_receber (centro_custo_id)');
    app_ensure_index($conn, 'contas_receber', 'idx_contas_receber_conta', 'CREATE INDEX idx_contas_receber_conta ON contas_receber (conta_financeira_id)');
    app_ensure_index($conn, 'contas_receber', 'idx_contas_receber_origem', 'CREATE INDEX idx_contas_receber_origem ON contas_receber (origem_tipo, origem_id)');
    app_drop_index_if_exists($conn, 'lotes', 'idx_lotes_numero');
    app_ensure_index($conn, 'lotes', 'idx_lotes_clinica_numero', 'CREATE UNIQUE INDEX idx_lotes_clinica_numero ON lotes (clinica_id, numero_lote)');

    app_financial_seed_plan_accounts($conn, $defaultClinicId);
    app_financial_seed_cost_centers($conn, $defaultClinicId);
    app_financial_seed_accounts($conn, $defaultClinicId);
}

function app_bootstrap(mysqli $conn): void
{
    if (app_auto_schema_enabled()) {
        app_install_schema($conn);
    }

    if (app_is_cli()) {
        return;
    }

    $page = app_current_page();
    $access = app_effective_page_access_map($conn)[$page] ?? ['auth'];

    if (in_array('public', $access, true)) {
        if ($page === 'login.php' && app_is_logged_in()) {
            app_redirect(app_profile_home());
        }

        return;
    }

    if (!app_is_logged_in()) {
        app_redirect('login.php');
    }

    if (!app_is_developer() && !app_current_clinic_is_released($conn)) {
        app_logout_user();
        app_flash('warning', 'Clinica bloqueada no momento. Fale com o suporte para liberar o acesso.');
        app_redirect('login.php');
    }

    if (in_array('auth', $access, true)) {
        return;
    }

    if (!app_profile_matches_access($access)) {
        app_flash('warning', 'Acesso nao permitido para este perfil.');
        app_redirect(app_profile_home());
    }
}
