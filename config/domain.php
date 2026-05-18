<?php

function app_db_all(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);

    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function app_stmt_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return [];
    }

    if ($types !== '' && $params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function app_stmt_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = app_stmt_all($conn, $sql, $types, $params);

    return $rows[0] ?? null;
}

function app_stmt_execute(mysqli $conn, string $sql, string $types = '', array $params = []): bool
{
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    if ($types !== '' && $params !== []) {
        $stmt->bind_param($types, ...$params);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function app_has_users(mysqli $conn): bool
{
    $row = $conn->query('SELECT COUNT(*) AS total FROM usuarios')->fetch_assoc();

    return (int) ($row['total'] ?? 0) > 0;
}

function app_default_developer_login(): string
{
    return 'whelmison';
}

function app_default_developer_password(): string
{
    return '123456';
}

function app_default_developer_name(): string
{
    return 'Whelmison';
}

function app_default_developer_credentials(mysqli $conn): array
{
    if (app_table_exists($conn, 'usuarios') && app_column_exists($conn, 'usuarios', 'usuario_padrao')) {
        $row = app_stmt_one(
            $conn,
            'SELECT login, senha_hash, nome_exibicao
             FROM usuarios
             WHERE usuario_padrao = 1
             ORDER BY id
             LIMIT 1'
        );

        if ($row) {
            $storedLogin = trim((string) ($row['login'] ?? '')) ?: app_default_developer_login();
            $storedName = trim((string) ($row['nome_exibicao'] ?? '')) ?: app_default_developer_name();

            if (strcasecmp($storedLogin, app_default_developer_login()) === 0) {
                if (strcasecmp($storedName, $storedLogin) === 0) {
                    $storedName = app_default_developer_name();
                }

                $storedLogin = app_default_developer_login();
            }

            return [
                'login' => $storedLogin,
                'senha_hash' => trim((string) ($row['senha_hash'] ?? '')),
                'nome_exibicao' => $storedName,
            ];
        }
    }

    return [
        'login' => app_default_developer_login(),
        'senha_hash' => password_hash(app_default_developer_password(), PASSWORD_DEFAULT),
        'nome_exibicao' => app_default_developer_name(),
    ];
}

function app_sync_default_developer_user(mysqli $conn, ?string $login = null, ?string $password = null, ?string $displayName = null): array
{
    app_ensure_clinics_table($conn);

    if (!app_table_exists($conn, 'usuarios')) {
        return ['ok' => false, 'message' => 'Tabela de usuarios nao encontrada.'];
    }

    app_ensure_column($conn, 'usuarios', 'usuario_padrao', 'TINYINT(1) NOT NULL DEFAULT 0');
    app_ensure_index($conn, 'usuarios', 'idx_usuarios_padrao', 'CREATE INDEX idx_usuarios_padrao ON usuarios (usuario_padrao)');

    $current = app_default_developer_credentials($conn);
    $login = trim((string) ($login ?? $current['login'] ?? app_default_developer_login()));
    $displayName = trim((string) ($displayName ?? $current['nome_exibicao'] ?? app_default_developer_name()));
    $password = $password !== null ? trim($password) : null;

    if (strlen($login) < 3) {
        return ['ok' => false, 'message' => 'Informe um login padrao com ao menos 3 caracteres.'];
    }

    if ($password !== null && $password !== '' && strlen($password) < 6) {
        return ['ok' => false, 'message' => 'A senha padrao precisa ter pelo menos 6 caracteres.'];
    }

    if ($displayName === '') {
        $displayName = $login;
    }

    $passwordHash = $password !== null && $password !== ''
        ? password_hash($password, PASSWORD_DEFAULT)
        : (trim((string) ($current['senha_hash'] ?? '')) ?: password_hash(app_default_developer_password(), PASSWORD_DEFAULT));

    $conflicts = app_stmt_all(
        $conn,
        'SELECT c.nome_fantasia
         FROM usuarios u
         INNER JOIN clinicas c ON c.id = u.clinica_id
         WHERE u.login = ? AND COALESCE(u.usuario_padrao, 0) = 0
         ORDER BY c.nome_fantasia
         LIMIT 5',
        's',
        [$login]
    );

    if ($conflicts !== []) {
        $names = implode(', ', array_map(static fn (array $row): string => (string) ($row['nome_fantasia'] ?? ''), $conflicts));

        return ['ok' => false, 'message' => 'Este login ja existe como usuario comum em: ' . $names . '. Altere esses logins antes de sincronizar o usuario padrao.'];
    }

    $clinics = app_stmt_all($conn, 'SELECT id FROM clinicas ORDER BY id');

    foreach ($clinics as $clinic) {
        $clinicId = (int) ($clinic['id'] ?? 0);

        if ($clinicId <= 0) {
            continue;
        }

        $existing = app_stmt_one(
            $conn,
            'SELECT id FROM usuarios WHERE clinica_id = ? AND usuario_padrao = 1 ORDER BY id LIMIT 1',
            'i',
            [$clinicId]
        );

        if ($existing) {
            app_stmt_execute(
                $conn,
                'UPDATE usuarios
                 SET login = ?,
                     senha_hash = ?,
                     perfil = ?,
                     profissional_id = NULL,
                     nome_exibicao = ?,
                     ativo = 1,
                     usuario_padrao = 1
                 WHERE id = ?',
                'ssssi',
                [$login, $passwordHash, 'desenvolvedor', $displayName, (int) $existing['id']]
            );
            continue;
        }

        app_stmt_execute(
            $conn,
            'INSERT INTO usuarios (clinica_id, login, senha_hash, perfil, profissional_id, nome_exibicao, ativo, usuario_padrao)
             VALUES (?, ?, ?, ?, NULL, ?, 1, 1)',
            'issss',
            [$clinicId, $login, $passwordHash, 'desenvolvedor', $displayName]
        );
    }

    return ['ok' => true, 'message' => 'Usuario padrao sincronizado em todas as clinicas.'];
}

function app_ensure_default_developer_user(mysqli $conn): void
{
    if (!app_table_exists($conn, 'usuarios')) {
        return;
    }

    app_ensure_column($conn, 'usuarios', 'usuario_padrao', 'TINYINT(1) NOT NULL DEFAULT 0');

    $totalDefault = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM usuarios WHERE usuario_padrao = 1');

    if ((int) ($totalDefault['total'] ?? 0) === 0) {
        app_stmt_execute(
            $conn,
            'UPDATE usuarios
             SET usuario_padrao = 1,
                 perfil = ?,
                 profissional_id = NULL,
                 ativo = 1
             WHERE login = ? AND perfil = ?',
            'sss',
            ['desenvolvedor', app_default_developer_login(), 'desenvolvedor']
        );
    }

    app_sync_default_developer_user($conn);
}

function app_current_clinic_is_released(mysqli $conn): bool
{
    if (!app_table_exists($conn, 'clinicas')) {
        return true;
    }

    app_ensure_column($conn, 'clinicas', 'liberada', 'TINYINT(1) NOT NULL DEFAULT 1');
    $clinicId = app_active_clinic_id();
    $row = app_stmt_one($conn, 'SELECT liberada FROM clinicas WHERE id = ? LIMIT 1', 'i', [$clinicId]);

    return !$row || (int) ($row['liberada'] ?? 1) === 1;
}

function app_create_first_user(mysqli $conn, string $clinicName, string $name, string $login, string $password, ?string $cnpj = null): array
{
    app_install_schema($conn);

    if (app_has_users($conn)) {
        return ['ok' => false, 'message' => 'A configuracao inicial ja foi concluida.'];
    }

    if (trim($clinicName) === '') {
        return ['ok' => false, 'message' => 'Informe o nome da clinica.'];
    }

    if (strlen(trim($login)) < 3 || strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Use login com 3+ caracteres e senha com 6+ caracteres.'];
    }

    $clinicCnpj = trim((string) $cnpj);

    if ($clinicCnpj !== '' && !app_cnpj_valid($clinicCnpj)) {
        return ['ok' => false, 'message' => 'Informe um CNPJ valido para a clinica.'];
    }

    $clinicId = app_ensure_default_clinic($conn);
    $clinicCnpjDigits = app_only_digits($clinicCnpj) ?: null;
    app_stmt_execute($conn, 'UPDATE clinicas SET nome_fantasia = ?, cnpj = ?, cnpj_digits = ? WHERE id = ?', 'sssi', [trim($clinicName), $clinicCnpj !== '' ? app_format_cnpj($clinicCnpj) : null, $clinicCnpjDigits, $clinicId]);

    $sync = app_sync_default_developer_user($conn, trim($login), $password, trim($name) ?: app_default_developer_name());

    if (!$sync['ok']) {
        return $sync;
    }

    return ['ok' => true, 'message' => 'Usuario inicial criado com sucesso.'];
}

function app_create_clinic_account(mysqli $conn, array $input): array
{
    app_install_schema($conn);

    $clinicName = trim((string) ($input['clinica_nome'] ?? ''));
    $clinicCnpj = trim((string) ($input['clinica_cnpj'] ?? ''));
    $phone = trim((string) ($input['telefone'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $userName = trim((string) ($input['nome'] ?? ''));
    $login = trim((string) ($input['login'] ?? ''));
    $password = (string) ($input['senha'] ?? '');
    $defaultCredentials = app_default_developer_credentials($conn);
    $defaultLogin = (string) ($defaultCredentials['login'] ?? app_default_developer_login());

    if ($clinicName === '') {
        return ['ok' => false, 'message' => 'Informe o nome da clinica.'];
    }

    if ($clinicCnpj !== '' && !app_cnpj_valid($clinicCnpj)) {
        return ['ok' => false, 'message' => 'Informe um CNPJ valido para a clinica.'];
    }

    if ($userName === '') {
        return ['ok' => false, 'message' => 'Informe o nome do usuario administrador.'];
    }

    if (strlen($login) < 3 || strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Use login com 3+ caracteres e senha com 6+ caracteres.'];
    }

    if (strcasecmp($login, $defaultLogin) === 0) {
        return ['ok' => false, 'message' => 'Este login esta reservado para o usuario padrao do desenvolvedor.'];
    }

    if ($clinicCnpj !== '' && app_clinic_cnpj_conflict($conn, $clinicCnpj) !== null) {
        return ['ok' => false, 'message' => 'Ja existe uma clinica com este CNPJ.'];
    }

    $clinicCnpjDigits = app_only_digits($clinicCnpj) ?: null;

    $conn->begin_transaction();

    try {
        app_stmt_execute(
            $conn,
            'INSERT INTO clinicas (nome_fantasia, cnpj, cnpj_digits, telefone, email, ativo, liberada) VALUES (?, ?, ?, ?, ?, 1, 1)',
            'sssss',
            [$clinicName, $clinicCnpj !== '' ? app_format_cnpj($clinicCnpj) : null, $clinicCnpjDigits, $phone, $email]
        );
        $clinicId = (int) $conn->insert_id;
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        app_stmt_execute(
            $conn,
            'INSERT INTO usuarios (clinica_id, login, senha_hash, perfil, nome_exibicao, ativo) VALUES (?, ?, ?, ?, ?, 1)',
            'issss',
            [$clinicId, $login, $passwordHash, 'administrativo', $userName]
        );

        app_financial_seed_plan_accounts($conn, $clinicId);
        app_financial_seed_cost_centers($conn, $clinicId);
        app_financial_seed_accounts($conn, $clinicId);
        $sync = app_sync_default_developer_user($conn);

        if (!$sync['ok']) {
            throw new RuntimeException($sync['message']);
        }

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();

        return ['ok' => false, 'message' => 'Nao foi possivel criar a clinica e o usuario.'];
    }

    return ['ok' => true, 'message' => 'Clinica cadastrada com sucesso. Entre com o login criado.'];
}

function app_active_clinics(mysqli $conn): array
{
    app_ensure_clinics_table($conn);

    return app_stmt_all(
        $conn,
        'SELECT id, nome_fantasia, cnpj, liberada FROM clinicas WHERE ativo = 1 ORDER BY nome_fantasia, id'
    );
}

function app_active_clinics_by_cnpj(mysqli $conn, string $cnpj, int $limit = 2): array
{
    app_ensure_clinics_table($conn);

    $digits = app_only_digits($cnpj);

    if ($digits === '') {
        return [];
    }

    return app_stmt_all(
        $conn,
        "SELECT id, nome_fantasia, cnpj, liberada
         FROM clinicas
         WHERE ativo = 1
           AND cnpj_digits = ?
         ORDER BY nome_fantasia, id
         LIMIT ?",
        'si',
        [$digits, max(1, $limit)]
    );
}

function app_clinic_cnpj_conflict(mysqli $conn, string $cnpj, ?int $ignoreClinicId = null): ?array
{
    app_ensure_clinics_table($conn);

    $digits = app_only_digits($cnpj);

    if ($digits === '') {
        return null;
    }

    $sql = 'SELECT id, nome_fantasia, cnpj FROM clinicas WHERE cnpj_digits = ?';
    $types = 's';
    $params = [$digits];

    if ($ignoreClinicId !== null && $ignoreClinicId > 0) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $ignoreClinicId;
    }

    $sql .= ' LIMIT 1';

    return app_stmt_one($conn, $sql, $types, $params);
}

function app_active_login_users(mysqli $conn, string $login, ?int $clinicId = null, int $limit = 2): array
{
    $login = trim($login);

    if ($login === '') {
        return [];
    }

    $sql = 'SELECT u.id,
                   u.clinica_id,
                   COALESCE(c.nome_fantasia, ?) AS clinica_nome,
                   u.login,
                   u.senha_hash,
                   u.perfil,
                   u.profissional_id,
                   COALESCE(u.usuario_padrao, 0) AS usuario_padrao,
                   COALESCE(c.liberada, 1) AS clinica_liberada,
                   COALESCE(p.nome, u.nome_exibicao, u.login) AS nome_exibicao
            FROM usuarios u
            LEFT JOIN clinicas c ON c.id = u.clinica_id
            LEFT JOIN profissionais p ON p.id = u.profissional_id AND p.clinica_id = u.clinica_id
            WHERE u.login = ? AND u.ativo = 1 AND COALESCE(c.ativo, 1) = 1';
    $types = 'ss';
    $params = [app_default_clinic_name(), $login];

    if ($clinicId !== null && $clinicId > 0) {
        $sql .= ' AND u.clinica_id = ?';
        $types .= 'i';
        $params[] = $clinicId;
    }

    $sql .= ' ORDER BY c.nome_fantasia, u.id LIMIT ?';
    $types .= 'i';
    $params[] = max(1, $limit);

    return app_stmt_all($conn, $sql, $types, $params);
}

function app_attempt_login(mysqli $conn, string $login, string $password, ?int $clinicId = null): array
{
    app_install_schema($conn);

    $users = app_active_login_users($conn, $login, $clinicId, 2);

    if ($clinicId === null && count($users) > 1) {
        return ['ok' => false, 'message' => 'Este login existe em mais de uma clinica. Informe o CNPJ da sua clinica para entrar.'];
    }

    $user = $users[0] ?? null;

    if (!$user || !password_verify($password, $user['senha_hash'])) {
        return ['ok' => false, 'message' => 'Login ou senha invalidos.'];
    }

    if ((int) ($user['clinica_liberada'] ?? 1) !== 1 && (string) ($user['perfil'] ?? '') !== 'desenvolvedor') {
        return ['ok' => false, 'message' => 'Clinica bloqueada no momento. Fale com o suporte para liberar o acesso.'];
    }

    unset($user['senha_hash']);
    unset($user['clinica_liberada']);
    app_login_user($user);

    return ['ok' => true, 'message' => 'Login realizado com sucesso.'];
}

function app_password_reset_code_path(): string
{
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'clinica_fisiolife_reset_code.txt';
}

function app_password_reset_code(): string
{
    $path = app_password_reset_code_path();

    if (is_file($path)) {
        $code = trim((string) file_get_contents($path));
        if ($code !== '') {
            return $code;
        }
    }

    $code = (string) random_int(100000, 999999);
    file_put_contents($path, $code);

    return $code;
}

function app_reset_user_password(mysqli $conn, string $login, string $resetCode, string $password, string $passwordConfirm, ?int $clinicId = null): array
{
    $login = trim($login);
    $resetCode = trim($resetCode);

    if ($login === '' || $resetCode === '') {
        return ['ok' => false, 'message' => 'Informe login e codigo local de reset.'];
    }

    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'A nova senha precisa ter pelo menos 6 caracteres.'];
    }

    if ($password !== $passwordConfirm) {
        return ['ok' => false, 'message' => 'A confirmacao da senha nao confere.'];
    }

    if (!hash_equals(app_password_reset_code(), $resetCode)) {
        return ['ok' => false, 'message' => 'Codigo local de reset invalido.'];
    }

    $users = app_active_login_users($conn, $login, $clinicId, 2);

    if ($clinicId === null && count($users) > 1) {
        return ['ok' => false, 'message' => 'Este login existe em mais de uma clinica. Informe o CNPJ da sua clinica para redefinir a senha.'];
    }

    $user = $users[0] ?? null;

    if (!$user) {
        return ['ok' => false, 'message' => 'Usuario ativo nao encontrado.'];
    }

    if ((int) ($user['usuario_padrao'] ?? 0) === 1) {
        $sync = app_sync_default_developer_user($conn, (string) $user['login'], $password, (string) ($user['nome_exibicao'] ?? app_default_developer_name()));

        return $sync['ok']
            ? ['ok' => true, 'message' => 'Senha padrao redefinida em todas as clinicas. Entre com a nova senha.']
            : $sync;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $ok = app_stmt_execute(
        $conn,
        'UPDATE usuarios SET senha_hash = ? WHERE id = ?',
        'si',
        [$passwordHash, (int) $user['id']]
    );

    if (!$ok) {
        return ['ok' => false, 'message' => 'Nao foi possivel atualizar a senha.'];
    }

    return ['ok' => true, 'message' => 'Senha redefinida com sucesso. Entre com a nova senha.'];
}

function app_professional_scope_sql(string $column): string
{
    if (!app_is_professional_user()) {
        return '';
    }

    $professionalId = app_current_professional_id();

    if ($professionalId === null) {
        return ' AND 1 = 0 ';
    }

    return ' AND ' . $column . ' = ' . $professionalId . ' ';
}

function app_professional_scope_exists_for_patient(string $patientColumn): string
{
    if (!app_is_professional_user()) {
        return '';
    }

    $professionalId = app_current_professional_id();

    if ($professionalId === null) {
        return ' AND 1 = 0 ';
    }

    return ' AND EXISTS (
        SELECT 1 FROM paciente_profissionais pp
        WHERE pp.paciente_id = ' . $patientColumn . '
        AND pp.clinica_id = ' . app_active_clinic_id() . '
        AND pp.profissional_id = ' . $professionalId . '
        UNION ALL
        SELECT 1 FROM guias gp
        WHERE gp.paciente_id = ' . $patientColumn . '
        AND gp.clinica_id = ' . app_active_clinic_id() . '
        AND gp.profissional_id = ' . $professionalId . '
        UNION ALL
        SELECT 1 FROM agenda agp
        WHERE agp.cliente_id = ' . $patientColumn . '
        AND agp.clinica_id = ' . app_active_clinic_id() . '
        AND agp.profissional_id = ' . $professionalId . '
        UNION ALL
        SELECT 1 FROM paciente_fichas_avaliacao fa
        WHERE fa.paciente_id = ' . $patientColumn . '
        AND fa.clinica_id = ' . app_active_clinic_id() . '
        AND fa.profissional_id = ' . $professionalId . '
        UNION ALL
        SELECT 1 FROM paciente_fichas_evolucao fe
        WHERE fe.paciente_id = ' . $patientColumn . '
        AND fe.clinica_id = ' . app_active_clinic_id() . '
        AND fe.profissional_id = ' . $professionalId . '
    ) ';
}

function app_fetch_profissionais(mysqli $conn): array
{
    return app_stmt_all($conn, 'SELECT * FROM profissionais WHERE clinica_id = ? ORDER BY nome', 'i', [app_active_clinic_id()]);
}

function app_fetch_servicos(mysqli $conn): array
{
    return app_stmt_all($conn, 'SELECT * FROM servicos WHERE clinica_id = ? AND ativo = 1 ORDER BY nome', 'i', [app_active_clinic_id()]);
}

function app_fetch_planos_conta(mysqli $conn, ?string $type = null): array
{
    $clinicId = app_active_clinic_id();

    if ($type === null) {
        return app_stmt_all($conn, 'SELECT * FROM plano_contas WHERE clinica_id = ? ORDER BY tipo, ordem_exibicao, codigo, nome', 'i', [$clinicId]);
    }

    return app_stmt_all($conn, 'SELECT * FROM plano_contas WHERE clinica_id = ? AND tipo = ? ORDER BY ordem_exibicao, codigo, nome', 'is', [$clinicId, $type]);
}

function app_fetch_planos_conta_options(mysqli $conn, ?string $type = null, bool $onlyLaunchable = true, bool $onlyActive = true): array
{
    $where = ['clinica_id = ?'];
    $types = 'i';
    $params = [app_active_clinic_id()];

    if ($type !== null) {
        $where[] = 'tipo = ?';
        $types .= 's';
        $params[] = $type;
    }

    if ($onlyLaunchable) {
        $where[] = 'aceita_lancamento = 1';
    }

    if ($onlyActive) {
        $where[] = 'ativo = 1';
    }

    $sql = 'SELECT * FROM plano_contas';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY tipo, ordem_exibicao, codigo, nome';

    return app_stmt_all($conn, $sql, $types, $params);
}

function app_fetch_centros_custo(mysqli $conn, bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM centros_custo WHERE clinica_id = ?';
    $types = 'i';
    $params = [app_active_clinic_id()];

    if ($onlyActive) {
        $sql .= ' AND ativo = 1';
    }
    $sql .= ' ORDER BY ativo DESC, nome';

    return app_stmt_all($conn, $sql, $types, $params);
}

function app_fetch_contas_financeiras(mysqli $conn, bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM contas_financeiras WHERE clinica_id = ?';
    $types = 'i';
    $params = [app_active_clinic_id()];

    if ($onlyActive) {
        $sql .= ' AND ativo = 1';
    }
    $sql .= ' ORDER BY ativo DESC, nome';

    return app_stmt_all($conn, $sql, $types, $params);
}

function app_fetch_pacientes(mysqli $conn): array
{
    return app_stmt_all($conn, 'SELECT id, nome FROM pacientes WHERE clinica_id = ? ORDER BY nome', 'i', [app_active_clinic_id()]);
}

function app_fetch_open_lotes(mysqli $conn): array
{
    return app_stmt_all($conn, 'SELECT * FROM lotes WHERE clinica_id = ? AND status IN (?, ?) ORDER BY data DESC, id DESC', 'iss', [app_active_clinic_id(), 'aberto', 'enviado']);
}

function app_fetch_professional_service_ids(mysqli $conn, int $professionalId): array
{
    $rows = app_stmt_all($conn, 'SELECT servico_id, COALESCE(tempo_minutos, 0) AS tempo_minutos FROM profissional_servico WHERE clinica_id = ? AND profissional_id = ?', 'ii', [app_active_clinic_id(), $professionalId]);
    $map = [];

    foreach ($rows as $row) {
        $map[(int) $row['servico_id']] = (int) $row['tempo_minutos'];
    }

    return $map;
}

function app_service_belongs_to_professional(mysqli $conn, int $professionalId, int $serviceId): bool
{
    $row = app_stmt_one(
        $conn,
        'SELECT COUNT(*) AS total FROM profissional_servico WHERE clinica_id = ? AND profissional_id = ? AND servico_id = ?',
        'iii',
        [app_active_clinic_id(), $professionalId, $serviceId]
    );

    return (int) ($row['total'] ?? 0) > 0;
}

function app_service_duration(mysqli $conn, int $professionalId, int $serviceId): ?int
{
    $row = app_stmt_one(
        $conn,
        'SELECT COALESCE(ps.tempo_minutos, s.tempo_minutos) AS tempo
         FROM profissional_servico ps
         INNER JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = ps.clinica_id
         WHERE ps.clinica_id = ? AND ps.profissional_id = ? AND ps.servico_id = ?
         LIMIT 1',
        'iii',
        [app_active_clinic_id(), $professionalId, $serviceId]
    );

    if (!$row) {
        return null;
    }

    return (int) $row['tempo'];
}

function app_schedule_has_conflict(mysqli $conn, string $date, string $startTime, string $endTime, int $professionalId, ?int $ignoreId = null): bool
{
    $sql = 'SELECT COUNT(*) AS total
            FROM agenda
            WHERE clinica_id = ?
            AND data_agendamento = ?
            AND profissional_id = ?
            AND status <> ?
            AND NOT (hora_fim <= ? OR hora_inicio >= ?)';

    $types = 'isisss';
    $params = [app_active_clinic_id(), $date, $professionalId, 'cancelado', $startTime, $endTime];

    if ($ignoreId !== null) {
        $sql .= ' AND id <> ?';
        $types .= 'i';
        $params[] = $ignoreId;
    }

    $row = app_stmt_one($conn, $sql, $types, $params);

    return (int) ($row['total'] ?? 0) > 0;
}

function app_generate_guide_code(mysqli $conn): string
{
    $row = app_stmt_one($conn, 'SELECT MAX(id) AS max_id FROM guias WHERE clinica_id = ?', 'i', [app_active_clinic_id()]);
    $next = ((int) ($row['max_id'] ?? 0)) + 1;

    return 'GUIA-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function app_guide_types(): array
{
    return [
        'particular' => 'Particular',
        'convenio_direto' => 'Convenio direto',
        'convenio_lote' => 'Convenio por lote',
    ];
}

function app_guide_operational_statuses(): array
{
    return [
        'aguardando_autorizacao' => 'Aguardando autorizacao',
        'autorizada' => 'Autorizada',
        'em_uso' => 'Em uso',
        'ultimas_sessoes' => 'Ultimas sessoes',
        'finalizada' => 'Finalizada',
        'cancelada' => 'Cancelada',
    ];
}

function app_normalize_guide_operational_status(?string $status): string
{
    $status = strtolower(trim((string) $status));
    $status = str_replace(
        [' ', '-', 'ç', 'ã', 'õ', 'á', 'é', 'ê', 'í', 'ó', 'ú'],
        ['_', '_', 'c', 'a', 'o', 'a', 'e', 'e', 'i', 'o', 'u'],
        $status
    );

    $aliases = [
        'andamento' => 'em_uso',
        'ativa' => 'autorizada',
        'ativo' => 'autorizada',
        'ultimas_sessoes' => 'ultimas_sessoes',
    ];

    $status = $aliases[$status] ?? $status;

    if ($status === 'criada') {
        return 'aguardando_autorizacao';
    }

    return array_key_exists($status, app_guide_operational_statuses()) ? $status : 'aguardando_autorizacao';
}

function app_guide_operational_status_data(array $guide): array
{
    $storedStatus = app_normalize_guide_operational_status((string) ($guide['status_operacional'] ?? 'aguardando_autorizacao'));
    $usedSessions = (int) ($guide['total_atendimentos'] ?? $guide['usadas'] ?? $guide['sessoes_usadas'] ?? 0);
    $totalSessions = max(0, (int) ($guide['total_sessoes'] ?? 0));
    $remainingSessions = max(0, $totalSessions - $usedSessions);
    $authorized = (int) ($guide['autorizada'] ?? 0) === 1;

    if ($storedStatus === 'cancelada') {
        return ['value' => 'cancelada', 'label' => 'Cancelada', 'class' => 'status-danger'];
    }

    if ($storedStatus === 'finalizada' || ($totalSessions > 0 && $usedSessions >= $totalSessions)) {
        return ['value' => 'finalizada', 'label' => 'Finalizada', 'class' => 'status-success'];
    }

    if ($storedStatus === 'ultimas_sessoes' || ($usedSessions > 0 && $remainingSessions <= 2)) {
        return ['value' => 'ultimas_sessoes', 'label' => 'Ultimas sessoes', 'class' => 'status-warning'];
    }

    if ($storedStatus === 'em_uso' || $usedSessions > 0) {
        return ['value' => 'em_uso', 'label' => 'Em uso', 'class' => 'status-info'];
    }

    if ($storedStatus === 'autorizada' || $authorized) {
        return ['value' => 'autorizada', 'label' => 'Autorizada', 'class' => 'status-success-soft'];
    }

    if ($storedStatus === 'aguardando_autorizacao') {
        return ['value' => 'aguardando_autorizacao', 'label' => 'Aguardando autorizacao', 'class' => 'status-warning'];
    }

    return ['value' => 'aguardando_autorizacao', 'label' => 'Aguardando autorizacao', 'class' => 'status-warning'];
}

function app_schedule_statuses(): array
{
    return [
        'agendado' => 'Agendado',
        'confirmado' => 'Confirmado',
        'cancelado' => 'Cancelado',
        'realizado' => 'Realizado',
    ];
}

function app_account_statuses(): array
{
    return [
        'aberto' => 'Aberto',
        'parcial' => 'Parcial',
        'pago' => 'Pago',
        'cancelado' => 'Cancelado',
    ];
}

function app_financial_payment_methods(): array
{
    return [
        'boleto' => 'Boleto',
        'dinheiro' => 'Dinheiro',
        'pix' => 'PIX',
        'cartao_credito' => 'Cartao de credito',
        'cartao_debito' => 'Cartao de debito',
        'transferencia' => 'Transferencia',
        'link_pagamento' => 'Link de pagamento',
        'cheque' => 'Cheque',
        'debito_automatico' => 'Debito automatico',
        'convenio' => 'Convenio',
    ];
}

function app_financial_account_types(): array
{
    return [
        'caixa' => 'Caixa',
        'banco' => 'Conta bancaria',
        'pix' => 'Carteira PIX',
        'cartao' => 'Recebiveis de cartao',
        'outro' => 'Outro',
    ];
}

function app_financial_net_amount(float $value, float $interest = 0, float $fine = 0, float $discount = 0): float
{
    return max(0, $value + $interest + $fine - $discount);
}

function app_financial_payment_method_label(?string $method): string
{
    $normalized = trim((string) $method);

    if ($normalized === '' || $normalized === 'nao_informado') {
        return 'Nao informado';
    }

    $labels = app_financial_payment_methods();

    return $labels[$normalized] ?? ucfirst(str_replace('_', ' ', $normalized));
}

function app_financial_expected_sql(string $alias): string
{
    return 'GREATEST(0, ' . $alias . '.valor + COALESCE(' . $alias . '.juros, 0) + COALESCE(' . $alias . '.multa, 0) - COALESCE(' . $alias . '.desconto, 0))';
}

function app_financial_settled_sql(string $alias, string $settledField): string
{
    $expectedSql = app_financial_expected_sql($alias);

    return 'CASE
        WHEN ' . $alias . '.status IN ("pago", "parcial")
        THEN CASE
            WHEN COALESCE(' . $alias . '.' . $settledField . ', 0) > 0 THEN ' . $alias . '.' . $settledField . '
            ELSE ' . $expectedSql . '
        END
        ELSE 0
    END';
}

function app_financial_open_sql(string $alias, string $settledField): string
{
    $expectedSql = app_financial_expected_sql($alias);

    return 'CASE
        WHEN ' . $alias . '.status IN ("pago", "cancelado") THEN 0
        WHEN ' . $alias . '.status = "parcial" THEN GREATEST(0, ' . $expectedSql . ' - COALESCE(' . $alias . '.' . $settledField . ', 0))
        ELSE ' . $expectedSql . '
    END';
}

function app_financial_row_amounts(array $row, string $settledField): array
{
    $expected = app_financial_net_amount(
        (float) ($row['valor'] ?? 0),
        (float) ($row['juros'] ?? 0),
        (float) ($row['multa'] ?? 0),
        (float) ($row['desconto'] ?? 0)
    );

    $status = strtolower(trim((string) ($row['status'] ?? 'aberto')));
    $explicitSettled = (float) ($row[$settledField] ?? 0);
    $settled = 0.0;

    if (in_array($status, ['pago', 'parcial'], true)) {
        $settled = $explicitSettled > 0 ? $explicitSettled : $expected;
    }

    if (in_array($status, ['pago', 'cancelado'], true)) {
        $open = 0.0;
    } elseif ($status === 'parcial') {
        $open = max(0, $expected - $settled);
    } else {
        $open = $expected;
    }

    return [
        'expected' => $expected,
        'settled' => $settled,
        'open' => $open,
    ];
}

function app_financial_period_label(string $startDate, string $endDate): string
{
    if ($startDate === $endDate) {
        return app_date_br($startDate);
    }

    return app_date_br($startDate) . ' a ' . app_date_br($endDate);
}

function app_financial_status_meta(string $status, ?string $dueDate = null): array
{
    $status = strtolower(trim($status));
    $today = date('Y-m-d');
    $isOverdue = $dueDate !== null && $dueDate !== '' && $dueDate < $today && !in_array($status, ['pago', 'cancelado'], true);

    if ($isOverdue) {
        return [
            'label' => 'Atrasado',
            'dot' => 'status-danger',
            'chip' => 'chip-danger',
        ];
    }

    return match ($status) {
        'pago' => ['label' => 'Pago', 'dot' => 'status-success', 'chip' => 'chip-success'],
        'parcial' => ['label' => 'Parcial', 'dot' => 'status-warning', 'chip' => 'chip-warning'],
        'cancelado' => ['label' => 'Cancelado', 'dot' => 'status-danger', 'chip' => 'chip-danger'],
        default => ['label' => 'Aberto', 'dot' => 'status-info', 'chip' => 'chip-info'],
    };
}

function app_lote_statuses(): array
{
    return [
        'aberto' => 'Aberto',
        'enviado' => 'Enviado',
        'faturado' => 'Faturado',
        'pago' => 'Pago',
    ];
}

function app_parse_money(string $value): float
{
    $normalized = trim($value);
    $normalized = str_replace(['R$', ' '], '', $normalized);
    $normalized = str_replace('.', '', $normalized);
    $normalized = str_replace(',', '.', $normalized);

    return (float) $normalized;
}

function app_create_user(mysqli $conn, string $login, string $password, string $profile, ?int $professionalId = null, ?string $displayName = null): array
{
    $allowedProfiles = ['profissional', 'secretaria', 'administrativo', 'desenvolvedor'];
    $clinicId = app_active_clinic_id();

    if (!in_array($profile, $allowedProfiles, true)) {
        return ['ok' => false, 'message' => 'Perfil invalido.'];
    }

    if (strlen(trim($login)) < 3 || strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Informe login com 3+ caracteres e senha com 6+ caracteres.'];
    }

    $exists = app_stmt_one($conn, 'SELECT id FROM usuarios WHERE clinica_id = ? AND login = ? LIMIT 1', 'is', [$clinicId, trim($login)]);

    if ($exists) {
        return ['ok' => false, 'message' => 'Ja existe um usuario com este login nesta clinica.'];
    }

    if ($profile === 'profissional' && $professionalId === null) {
        return ['ok' => false, 'message' => 'Usuario profissional precisa estar vinculado a um profissional.'];
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $ok = app_stmt_execute(
        $conn,
        'INSERT INTO usuarios (clinica_id, login, senha_hash, perfil, profissional_id, nome_exibicao) VALUES (?, ?, ?, ?, ?, ?)',
        'isssis',
        [$clinicId, trim($login), $passwordHash, $profile, $professionalId, $displayName ?: trim($login)]
    );

    if (!$ok) {
        return ['ok' => false, 'message' => 'Nao foi possivel criar o usuario.'];
    }

    return ['ok' => true, 'message' => 'Usuario criado com sucesso.'];
}

function app_create_professional(mysqli $conn, string $name, string $address, string $phone, string $profession): array
{
    if (trim($name) === '') {
        return ['ok' => false, 'message' => 'Informe o nome do profissional.'];
    }

    $ok = app_stmt_execute(
        $conn,
        'INSERT INTO profissionais (clinica_id, nome, endereco, telefone, profissao) VALUES (?, ?, ?, ?, ?)',
        'issss',
        [app_active_clinic_id(), trim($name), trim($address), trim($phone), trim($profession)]
    );

    if (!$ok) {
        return ['ok' => false, 'message' => 'Nao foi possivel cadastrar o profissional.'];
    }

    return ['ok' => true, 'message' => 'Profissional cadastrado com sucesso.', 'id' => $conn->insert_id];
}

function app_update_professional_services(mysqli $conn, int $professionalId, array $serviceTimes): array
{
    $clinicId = app_active_clinic_id();
    app_stmt_execute($conn, 'DELETE FROM profissional_servico WHERE clinica_id = ? AND profissional_id = ?', 'ii', [$clinicId, $professionalId]);

    foreach ($serviceTimes as $serviceId => $minutes) {
        $serviceId = (int) $serviceId;
        $minutes = $minutes !== null && $minutes !== '' ? (int) $minutes : null;

        app_stmt_execute(
            $conn,
            'INSERT INTO profissional_servico (clinica_id, profissional_id, servico_id, tempo_minutos) VALUES (?, ?, ?, ?)',
            'iiii',
            [$clinicId, $professionalId, $serviceId, $minutes]
        );
    }

    return ['ok' => true, 'message' => 'Servicos vinculados ao profissional.'];
}

function app_create_service(mysqli $conn, string $name, int $durationMinutes): array
{
    if (trim($name) === '' || $durationMinutes <= 0) {
        return ['ok' => false, 'message' => 'Informe nome e duracao valida para o servico.'];
    }

    $ok = app_stmt_execute(
        $conn,
        'INSERT INTO servicos (clinica_id, nome, tempo_minutos) VALUES (?, ?, ?)',
        'isi',
        [app_active_clinic_id(), trim($name), $durationMinutes]
    );

    if (!$ok) {
        return ['ok' => false, 'message' => 'Nao foi possivel cadastrar o servico.'];
    }

    return ['ok' => true, 'message' => 'Servico cadastrado com sucesso.'];
}

function app_professional_profile(mysqli $conn, int $professionalId): ?array
{
    return app_stmt_one($conn, 'SELECT * FROM profissionais WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [app_active_clinic_id(), $professionalId]);
}

function app_service_price_data_for_guide(mysqli $conn, int $clinicId, int $serviceId, ?int $planId = null): array
{
    if ($serviceId <= 0) {
        return ['valor' => 0.0, 'permite_alterar_guia' => 0];
    }

    if ($planId !== null && $planId > 0) {
        $row = app_stmt_one(
            $conn,
            'SELECT valor, permite_alterar_guia
             FROM servico_precos
             WHERE clinica_id = ? AND servico_id = ? AND plano_id = ? AND ativo = 1
             ORDER BY id DESC
             LIMIT 1',
            'iii',
            [$clinicId, $serviceId, $planId]
        );

        if ($row) {
            return [
                'valor' => (float) ($row['valor'] ?? 0),
                'permite_alterar_guia' => (int) ($row['permite_alterar_guia'] ?? 0),
            ];
        }

        return ['valor' => 0.0, 'permite_alterar_guia' => 0];
    }

    $row = app_stmt_one(
        $conn,
        'SELECT valor, permite_alterar_guia
         FROM servico_precos
         WHERE clinica_id = ? AND servico_id = ? AND plano_id IS NULL AND ativo = 1
         ORDER BY id DESC
         LIMIT 1',
        'ii',
        [$clinicId, $serviceId]
    );

    return [
        'valor' => (float) ($row['valor'] ?? 0),
        'permite_alterar_guia' => (int) ($row['permite_alterar_guia'] ?? 0),
    ];
}

function app_service_price_for_guide(mysqli $conn, int $clinicId, int $serviceId, ?int $planId = null): float
{
    $price = app_service_price_data_for_guide($conn, $clinicId, $serviceId, $planId);

    return (float) ($price['valor'] ?? 0);
}

function app_professional_has_service(mysqli $conn, int $clinicId, int $professionalId, int $serviceId): bool
{
    if ($professionalId <= 0 || $serviceId <= 0) {
        return false;
    }

    $row = app_stmt_one(
        $conn,
        'SELECT ps.servico_id
         FROM profissional_servico ps
         INNER JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = ps.clinica_id
         WHERE ps.clinica_id = ? AND ps.profissional_id = ? AND ps.servico_id = ? AND s.ativo = 1
         LIMIT 1',
        'iii',
        [$clinicId, $professionalId, $serviceId]
    );

    return $row !== null;
}

function app_services_for_professional(mysqli $conn, int $professionalId, ?int $planId = null): array
{
    $clinicId = app_active_clinic_id();
    $rows = app_stmt_all(
        $conn,
        'SELECT s.id,
                s.nome,
                COALESCE(ps.tempo_minutos, s.tempo_minutos) AS tempo_minutos,
                COALESCE(s.tipo_agendamento, \'individual\') AS tipo_agendamento,
                COALESCE(s.capacidade_agendamento, 1) AS capacidade_agendamento
         FROM profissional_servico ps
         INNER JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = ps.clinica_id
         WHERE ps.clinica_id = ? AND ps.profissional_id = ? AND s.ativo = 1
         ORDER BY s.nome',
        'ii',
        [$clinicId, $professionalId]
    );

    foreach ($rows as &$row) {
        $price = app_service_price_data_for_guide($conn, $clinicId, (int) $row['id'], $planId);
        $row['valor_sessao'] = (float) ($price['valor'] ?? 0);
        $row['permite_alterar_guia'] = (int) ($price['permite_alterar_guia'] ?? 0);
    }
    unset($row);

    return $rows;
}

function app_create_guide(mysqli $conn, array $data): array
{
    $patientId = (int) ($data['paciente_id'] ?? 0);
    $professionalId = (int) ($data['profissional_id'] ?? 0);
    $serviceId = (int) ($data['servico_id'] ?? 0);
    $planId = (int) ($data['plano_id'] ?? 0);
    $type = trim((string) ($data['tipo_guia'] ?? ''));
    $date = trim((string) ($data['data'] ?? ''));
    $sessions = max(1, (int) ($data['total_sessoes'] ?? 1));
    $convenio = trim((string) ($data['convenio'] ?? ''));
    $value = app_parse_money((string) ($data['valor_guia'] ?? '0'));
    $code = trim((string) ($data['codigo'] ?? ''));
    $notes = trim((string) ($data['observacoes'] ?? ''));
    $authorized = !empty($data['autorizada']) ? 1 : 0;
    $operationalStatus = app_normalize_guide_operational_status((string) ($data['status_operacional'] ?? 'aguardando_autorizacao'));

    if ($patientId <= 0) {
        return ['ok' => false, 'message' => 'Nao permitir guia sem paciente.'];
    }

    if ($professionalId <= 0) {
        return ['ok' => false, 'message' => 'Nao permitir guia sem profissional.'];
    }

    if ($serviceId <= 0 || !app_professional_has_service($conn, app_active_clinic_id(), $professionalId, $serviceId)) {
        return ['ok' => false, 'message' => 'Selecione um servico vinculado ao profissional.'];
    }

    if ($planId <= 0) {
        return ['ok' => false, 'message' => 'Selecione o plano da guia.'];
    }

    if (!array_key_exists($type, app_guide_types())) {
        return ['ok' => false, 'message' => 'Nao permitir guia sem tipo.'];
    }

    if ($date === '') {
        $date = date('Y-m-d');
    }

    if ($code === '') {
        $code = app_generate_guide_code($conn);
    }

    $clinicId = app_active_clinic_id();
    $existingCode = app_stmt_one($conn, 'SELECT id FROM guias WHERE clinica_id = ? AND codigo = ? LIMIT 1', 'is', [$clinicId, $code]);

    if ($existingCode) {
        return ['ok' => false, 'message' => 'Ja existe uma guia com este codigo.'];
    }

    $servicePriceData = app_service_price_data_for_guide($conn, $clinicId, $serviceId, $planId);
    $servicePrice = (float) ($servicePriceData['valor'] ?? 0);

    if ($servicePrice <= 0) {
        return ['ok' => false, 'message' => 'Cadastre o preco deste servico para este plano no cadastro de servico.'];
    }

    $calculatedValue = $servicePrice * $sessions;
    $value = !empty($servicePriceData['permite_alterar_guia']) && $value > 0 ? $value : $calculatedValue;

    if ($value <= 0) {
        return ['ok' => false, 'message' => 'Informe um valor valido para a guia.'];
    }

    if ($operationalStatus === 'cancelada') {
        $authorized = 0;
    } elseif ($operationalStatus === 'autorizada') {
        $authorized = 1;
    } elseif ($authorized === 1 && $operationalStatus === 'aguardando_autorizacao') {
        $operationalStatus = 'autorizada';
    }

    $ok = app_stmt_execute(
        $conn,
        'INSERT INTO guias (clinica_id, codigo, paciente_id, plano_id, total_sessoes, data, valor_guia, recebido, profissional_id, servico_id, tipo_guia, lote_id, convenio, observacoes, autorizada, status_operacional)
         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NULL, ?, ?, ?, ?)',
        'isiiisdiisssis',
        [$clinicId, $code, $patientId, $planId, $sessions, $date, $value, $professionalId, $serviceId, $type, $convenio, $notes, $authorized, $operationalStatus]
    );

    if (!$ok) {
        return ['ok' => false, 'message' => 'Nao foi possivel salvar a guia.'];
    }

    $guideId = $conn->insert_id;
    $billingMessage = null;

    if (in_array($type, ['particular', 'convenio_direto'], true)) {
        $billing = app_create_receivable_for_guide($conn, $guideId);
        $billingMessage = $billing['message'];
    }

    return [
        'ok' => true,
        'message' => $billingMessage ? 'Guia salva. ' . $billingMessage : 'Guia salva com sucesso.',
        'id' => $guideId,
    ];
}

function app_create_receivable_for_guide(mysqli $conn, int $guideId): array
{
    $guide = app_stmt_one(
        $conn,
        'SELECT g.id, g.codigo, g.valor_guia, g.data, g.profissional_id, g.tipo_guia, g.conta_receber_id, p.nome AS paciente_nome
         FROM guias g
         LEFT JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
         WHERE g.clinica_id = ? AND g.id = ?
         LIMIT 1',
        'ii',
        [app_active_clinic_id(), $guideId]
    );

    if (!$guide) {
        return ['ok' => false, 'message' => 'Guia nao encontrada.'];
    }

    if (!in_array($guide['tipo_guia'], ['particular', 'convenio_direto'], true)) {
        return ['ok' => false, 'message' => 'Este tipo de guia nao gera conta automatica.'];
    }

    $existing = app_stmt_one(
        $conn,
        'SELECT id FROM contas_receber WHERE clinica_id = ? AND origem_tipo = ? AND origem_id = ? LIMIT 1',
        'isi',
        [app_active_clinic_id(), 'guia', $guideId]
    );

    if ($existing) {
        return ['ok' => false, 'message' => 'Ja existe cobranca gerada para esta guia.'];
    }

    $description = 'Guia ' . ($guide['codigo'] ?: ('#' . $guide['id'])) . ' - ' . ($guide['paciente_nome'] ?: 'Sem paciente');

    $ok = app_stmt_execute(
        $conn,
        'INSERT INTO contas_receber (clinica_id, descricao, valor, vencimento, status, profissional_id, origem_tipo, origem_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'isdssisi',
        [
            app_active_clinic_id(),
            $description,
            (float) $guide['valor_guia'],
            $guide['data'],
            'aberto',
            $guide['profissional_id'] !== null ? (int) $guide['profissional_id'] : null,
            'guia',
            $guideId,
        ]
    );

    if (!$ok) {
        return ['ok' => false, 'message' => 'Nao foi possivel gerar a conta a receber.'];
    }

    $receivableId = $conn->insert_id;
    app_stmt_execute(
        $conn,
        'UPDATE guias SET conta_receber_gerada = 1, conta_receber_id = ? WHERE clinica_id = ? AND id = ?',
        'iii',
        [$receivableId, app_active_clinic_id(), $guideId]
    );

    return ['ok' => true, 'message' => 'Conta a receber gerada automaticamente.'];
}

function app_close_lote(mysqli $conn, int $batchId): array
{
    $batch = app_stmt_one($conn, 'SELECT * FROM lotes WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [app_active_clinic_id(), $batchId]);

    if (!$batch) {
        return ['ok' => false, 'message' => 'Lote nao encontrado.'];
    }

    $summary = app_stmt_one(
        $conn,
        'SELECT COUNT(*) AS total_guias, COALESCE(SUM(valor_guia), 0) AS total_valor
         FROM guias
         WHERE clinica_id = ? AND lote_id = ? AND tipo_guia = ?',
        'iis',
        [app_active_clinic_id(), $batchId, 'convenio_lote']
    );

    if ((int) ($summary['total_guias'] ?? 0) === 0) {
        return ['ok' => false, 'message' => 'Nao e permitido faturar um lote vazio.'];
    }

    $existing = app_stmt_one(
        $conn,
        'SELECT id FROM contas_receber WHERE clinica_id = ? AND origem_tipo = ? AND origem_id = ? LIMIT 1',
        'isi',
        [app_active_clinic_id(), 'lote', $batchId]
    );

    if ($existing) {
        return ['ok' => false, 'message' => 'Este lote ja possui cobranca gerada.'];
    }

    $description = 'Lote ' . $batch['id'] . ' - ' . $batch['convenio'];
    $ok = app_stmt_execute(
        $conn,
        'INSERT INTO contas_receber (clinica_id, descricao, valor, vencimento, status, origem_tipo, origem_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        'isdsssi',
        [
            app_active_clinic_id(),
            $description,
            (float) $summary['total_valor'],
            $batch['data'],
            'aberto',
            'lote',
            $batchId,
        ]
    );

    if (!$ok) {
        return ['ok' => false, 'message' => 'Nao foi possivel gerar a conta do lote.'];
    }

    app_stmt_execute($conn, 'UPDATE lotes SET status = ? WHERE clinica_id = ? AND id = ?', 'sii', ['faturado', app_active_clinic_id(), $batchId]);

    return ['ok' => true, 'message' => 'Lote faturado e conta a receber criada.'];
}

function app_whatsapp_url(string $phone, string $client, string $date, string $time, string $service): string
{
    $digits = app_normalize_phone($phone);
    $message = "Novo agendamento:\nCliente: {$client}\nData: {$date}\nHorario: {$time}\nServico: {$service}";

    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
}
