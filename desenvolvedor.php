<?php
include 'config/db.php';

app_install_schema($conn);
app_ensure_default_developer_user($conn);

$currentUser = app_current_user() ?? [];
$currentClinicId = app_active_clinic_id();
$requestMethod = app_request_method();
$selectedClinicId = $requestMethod === 'POST'
    ? app_post_int('clinic_id')
    : app_query_int('clinic_id');
$autoOpenClinicForm = false;
$defaultCredentials = app_default_developer_credentials($conn);

function dev_clinic_form_defaults(?array $clinic = null): array
{
    return [
        'id' => (int) ($clinic['id'] ?? 0),
        'nome_fantasia' => (string) ($clinic['nome_fantasia'] ?? ''),
        'razao_social' => (string) ($clinic['razao_social'] ?? ''),
        'cnpj' => (string) ($clinic['cnpj'] ?? ''),
        'telefone' => (string) ($clinic['telefone'] ?? ''),
        'whatsapp' => (string) ($clinic['whatsapp'] ?? ''),
        'email' => (string) ($clinic['email'] ?? ''),
        'endereco' => (string) ($clinic['endereco'] ?? ''),
        'cidade' => (string) ($clinic['cidade'] ?? ''),
        'estado' => (string) ($clinic['estado'] ?? ''),
        'ativo' => !isset($clinic['ativo']) || (int) ($clinic['ativo'] ?? 1) === 1,
        'liberada' => !isset($clinic['liberada']) || (int) ($clinic['liberada'] ?? 1) === 1,
    ];
}

function dev_fetch_clinic(mysqli $conn, int $clinicId): ?array
{
    if ($clinicId <= 0) {
        return null;
    }

    return app_stmt_one($conn, 'SELECT * FROM clinicas WHERE id = ? LIMIT 1', 'i', [$clinicId]);
}

function dev_delete_clinic(mysqli $conn, int $clinicId, int $currentClinicId): array
{
    if ($clinicId <= 0) {
        return ['ok' => false, 'message' => 'Clinica invalida.'];
    }

    if ($clinicId === $currentClinicId) {
        return ['ok' => false, 'message' => 'Nao e permitido excluir a clinica em uso na sessao atual.'];
    }

    $clinic = dev_fetch_clinic($conn, $clinicId);

    if (!$clinic) {
        return ['ok' => false, 'message' => 'Clinica nao encontrada.'];
    }

    $total = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM clinicas');

    if ((int) ($total['total'] ?? 0) <= 1) {
        return ['ok' => false, 'message' => 'Mantenha pelo menos uma clinica cadastrada.'];
    }

    $tables = [
        'agenda_grupo_pacientes',
        'agenda_grupos',
        'paciente_fichas_evolucao',
        'paciente_fichas_avaliacao',
        'perfil_permissoes',
        'profissional_servico',
        'recebimentos',
        'atendimentos',
        'agenda_disponibilidade',
        'agenda',
        'guias',
        'lotes',
        'contas_receber',
        'contas_pagar',
        'contas_financeiras',
        'centros_custo',
        'plano_contas',
        'planos',
        'servicos',
        'pacientes',
        'profissionais',
        'usuarios',
        'config',
    ];

    $conn->begin_transaction();

    try {
        foreach ($tables as $table) {
            if (!app_table_exists($conn, $table) || !app_column_exists($conn, $table, 'clinica_id')) {
                continue;
            }

            app_stmt_execute($conn, "DELETE FROM `$table` WHERE clinica_id = ?", 'i', [$clinicId]);
        }

        app_stmt_execute($conn, 'DELETE FROM clinicas WHERE id = ?', 'i', [$clinicId]);
        $conn->commit();

        return ['ok' => true, 'message' => 'Clinica excluida com sucesso.'];
    } catch (Throwable $exception) {
        $conn->rollback();

        return ['ok' => false, 'message' => 'Nao foi possivel excluir a clinica.'];
    }
}

function dev_switch_clinic(mysqli $conn, int $clinicId): array
{
    $user = app_stmt_one(
        $conn,
        'SELECT u.id,
                u.clinica_id,
                c.nome_fantasia AS clinica_nome,
                u.login,
                u.perfil,
                u.profissional_id,
                COALESCE(p.nome, u.nome_exibicao, u.login) AS nome_exibicao
         FROM usuarios u
         INNER JOIN clinicas c ON c.id = u.clinica_id
         LEFT JOIN profissionais p ON p.id = u.profissional_id AND p.clinica_id = u.clinica_id
         WHERE u.clinica_id = ? AND u.perfil = ? AND u.ativo = 1 AND c.ativo = 1
         ORDER BY COALESCE(u.usuario_padrao, 0) DESC, u.id
         LIMIT 1',
        'is',
        [$clinicId, 'desenvolvedor']
    );

    if (!$user) {
        return ['ok' => false, 'message' => 'Nao ha usuario desenvolvedor ativo nesta clinica.'];
    }

    app_login_user($user);

    return ['ok' => true, 'message' => 'Clinica ativa alterada.'];
}

function dev_clinic_counts(mysqli $conn): array
{
    return app_stmt_all(
        $conn,
        'SELECT c.*,
                (SELECT COUNT(*) FROM usuarios u WHERE u.clinica_id = c.id) AS total_usuarios,
                (SELECT COUNT(*) FROM profissionais p WHERE p.clinica_id = c.id) AS total_profissionais,
                (SELECT COUNT(*) FROM pacientes pa WHERE pa.clinica_id = c.id) AS total_pacientes
         FROM clinicas c
         ORDER BY c.ativo DESC, c.liberada DESC, c.nome_fantasia, c.id'
    );
}

$clinicFormValues = dev_clinic_form_defaults(dev_fetch_clinic($conn, $selectedClinicId));

if ($requestMethod === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'save_default_user') {
        $login = app_request_post('default_login', '') ?? '';
        $displayName = app_request_post('default_nome', '') ?? '';
        $password = app_request_post('default_senha', '') ?? '';
        $passwordConfirm = app_request_post('default_senha_confirmar', '') ?? '';

        if ($password !== '' && $password !== $passwordConfirm) {
            $result = ['ok' => false, 'message' => 'A confirmacao da senha padrao nao confere.'];
        } else {
            $result = app_sync_default_developer_user($conn, $login, $password !== '' ? $password : null, $displayName);

            if ($result['ok']) {
                $_SESSION['app_user']['login'] = trim($login);
                $_SESSION['app_user']['nome_exibicao'] = trim($displayName) !== '' ? trim($displayName) : trim($login);
                $defaultCredentials = app_default_developer_credentials($conn);
            }
        }
    } elseif ($action === 'save_clinic') {
        $clinicId = app_post_int('clinic_id');
        $clinicFormValues = [
            'id' => $clinicId,
            'nome_fantasia' => app_request_post('nome_fantasia', '') ?? '',
            'razao_social' => app_request_post('razao_social', '') ?? '',
            'cnpj' => app_request_post('cnpj', '') ?? '',
            'telefone' => app_request_post('telefone', '') ?? '',
            'whatsapp' => app_request_post('whatsapp', '') ?? '',
            'email' => app_request_post('email', '') ?? '',
            'endereco' => app_request_post('endereco', '') ?? '',
            'cidade' => app_request_post('cidade', '') ?? '',
            'estado' => strtoupper(app_request_post('estado', '') ?? ''),
            'ativo' => isset($_POST['ativo']),
            'liberada' => isset($_POST['liberada']),
        ];

        if (trim($clinicFormValues['nome_fantasia']) === '') {
            $result = ['ok' => false, 'message' => 'Informe o nome da clinica.'];
            $autoOpenClinicForm = true;
        } elseif (trim((string) $clinicFormValues['cnpj']) !== '' && !app_cnpj_valid((string) $clinicFormValues['cnpj'])) {
            $result = ['ok' => false, 'message' => 'Informe um CNPJ valido.'];
            $autoOpenClinicForm = true;
        } elseif (trim((string) $clinicFormValues['cnpj']) !== '' && app_clinic_cnpj_conflict($conn, (string) $clinicFormValues['cnpj'], $clinicId > 0 ? $clinicId : null) !== null) {
            $result = ['ok' => false, 'message' => 'Ja existe uma clinica com este CNPJ.'];
            $autoOpenClinicForm = true;
        } else {
            $clinicCnpj = trim((string) $clinicFormValues['cnpj']) !== '' ? app_format_cnpj((string) $clinicFormValues['cnpj']) : null;
            $clinicCnpjDigits = trim((string) $clinicFormValues['cnpj']) !== '' ? app_only_digits((string) $clinicFormValues['cnpj']) : null;
            $clinicFormValues['cnpj'] = $clinicCnpj ?? '';

            if ($clinicId > 0) {
                $ok = app_stmt_execute(
                    $conn,
                    'UPDATE clinicas
                     SET nome_fantasia = ?,
                         razao_social = ?,
                         cnpj = ?,
                         cnpj_digits = ?,
                         telefone = ?,
                         whatsapp = ?,
                         email = ?,
                         endereco = ?,
                         cidade = ?,
                         estado = ?,
                         ativo = ?,
                         liberada = ?
                     WHERE id = ?',
                    'ssssssssssiii',
                    [
                        $clinicFormValues['nome_fantasia'],
                        $clinicFormValues['razao_social'],
                        $clinicCnpj,
                        $clinicCnpjDigits,
                        $clinicFormValues['telefone'],
                        $clinicFormValues['whatsapp'],
                        $clinicFormValues['email'],
                        $clinicFormValues['endereco'],
                        $clinicFormValues['cidade'],
                        $clinicFormValues['estado'],
                        $clinicFormValues['ativo'] ? 1 : 0,
                        $clinicFormValues['liberada'] ? 1 : 0,
                        $clinicId,
                    ]
                );
                $result = ['ok' => $ok, 'message' => $ok ? 'Clinica atualizada com sucesso.' : 'Nao foi possivel atualizar a clinica.'];

                if ($ok && $clinicId === $currentClinicId) {
                    $_SESSION['app_user']['clinica_nome'] = $clinicFormValues['nome_fantasia'];
                }
            } else {
                $conn->begin_transaction();

                try {
                    app_stmt_execute(
                        $conn,
                        'INSERT INTO clinicas (nome_fantasia, razao_social, cnpj, cnpj_digits, telefone, whatsapp, email, endereco, cidade, estado, ativo, liberada)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        'ssssssssssii',
                        [
                            $clinicFormValues['nome_fantasia'],
                            $clinicFormValues['razao_social'],
                            $clinicCnpj,
                            $clinicCnpjDigits,
                            $clinicFormValues['telefone'],
                            $clinicFormValues['whatsapp'],
                            $clinicFormValues['email'],
                            $clinicFormValues['endereco'],
                            $clinicFormValues['cidade'],
                            $clinicFormValues['estado'],
                            $clinicFormValues['ativo'] ? 1 : 0,
                            $clinicFormValues['liberada'] ? 1 : 0,
                        ]
                    );
                    $newClinicId = (int) $conn->insert_id;
                    app_financial_seed_plan_accounts($conn, $newClinicId);
                    app_financial_seed_cost_centers($conn, $newClinicId);
                    app_financial_seed_accounts($conn, $newClinicId);
                    $sync = app_sync_default_developer_user($conn);

                    if (!$sync['ok']) {
                        throw new RuntimeException($sync['message']);
                    }

                    $conn->commit();
                    $result = ['ok' => true, 'message' => 'Clinica cadastrada com usuario padrao.'];
                    $selectedClinicId = $newClinicId;
                    $clinicFormValues = dev_clinic_form_defaults(dev_fetch_clinic($conn, $newClinicId));
                } catch (Throwable $exception) {
                    $conn->rollback();
                    $result = ['ok' => false, 'message' => 'Nao foi possivel cadastrar a clinica.'];
                    $autoOpenClinicForm = true;
                }
            }
        }
    } elseif ($action === 'delete_clinic') {
        $result = dev_delete_clinic($conn, app_post_int('clinic_id'), $currentClinicId);
    } elseif ($action === 'switch_clinic') {
        $result = dev_switch_clinic($conn, app_post_int('clinic_id'));
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok']) {
        app_redirect('desenvolvedor.php');
    }
}

$clinics = dev_clinic_counts($conn);
$totalClinics = count($clinics);
$releasedClinics = count(array_filter($clinics, static fn (array $clinic): bool => (int) ($clinic['liberada'] ?? 1) === 1 && (int) ($clinic['ativo'] ?? 1) === 1));
$blockedClinics = count(array_filter($clinics, static fn (array $clinic): bool => (int) ($clinic['liberada'] ?? 1) !== 1));
$totalUsers = array_sum(array_map(static fn (array $clinic): int => (int) ($clinic['total_usuarios'] ?? 0), $clinics));
$totalProfessionals = array_sum(array_map(static fn (array $clinic): int => (int) ($clinic['total_profissionais'] ?? 0), $clinics));
$isEditingClinic = (int) ($clinicFormValues['id'] ?? 0) > 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Painel Desenvolvedor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
.dev-shell {
    padding-top: 1rem;
    padding-bottom: 1.5rem;
}

.dev-card {
    border: 0;
    border-radius: 18px;
    box-shadow: 0 1rem 2.4rem rgba(15, 76, 92, 0.08);
}

.dev-table {
    font-size: 0.82rem;
}

.dev-table th {
    color: #5f7480;
    font-size: 0.68rem;
    text-transform: uppercase;
}

.dev-status {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 999px;
    padding: 0.25rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 700;
}

.dev-status-success {
    background: #e8f6ee;
    color: #17683a;
}

.dev-status-warning {
    background: #fff4dd;
    color: #8a5a00;
}

.dev-status-muted {
    background: #eef3f6;
    color: #536a75;
}

.dev-form .form-control,
.dev-form .form-select {
    min-height: 40px;
    border-radius: 12px;
}

.dev-form .form-check {
    border: 1px solid rgba(15, 76, 92, 0.1);
    border-radius: 12px;
    padding: 0.55rem 0.85rem 0.55rem 2.3rem;
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container dev-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Painel do desenvolvedor</h3>
                <p>Controle das clinicas, liberacao de acesso e usuario padrao global.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#clinicFormModal">+ Nova clinica</button>
                <a class="btn btn-light btn-sm rounded-pill px-3" href="administrativo_permissoes.php">Permissoes</a>
            </div>
        </div>
    </section>

    <div class="row g-3 my-3">
        <div class="col-md-3">
            <div class="card dev-card p-3">
                <div class="text-muted small">Clinicas</div>
                <div class="fs-3 fw-bold"><?= $totalClinics ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dev-card p-3">
                <div class="text-muted small">Liberadas</div>
                <div class="fs-3 fw-bold"><?= $releasedClinics ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dev-card p-3">
                <div class="text-muted small">Bloqueadas</div>
                <div class="fs-3 fw-bold"><?= $blockedClinics ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dev-card p-3">
                <div class="text-muted small">Usuarios / profissionais</div>
                <div class="fs-5 fw-bold"><?= $totalUsers ?> / <?= $totalProfessionals ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card dev-card">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <h5 class="mb-1">Clinicas cadastradas</h5>
                            <span class="text-muted small">CRUD completo e status de liberacao para cobranca futura.</span>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle dev-table mb-0">
                            <thead>
                            <tr>
                                <th>Clinica</th>
                                <th>Status</th>
                                <th>Base</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($clinics as $clinic): ?>
                                <?php
                                $clinicId = (int) ($clinic['id'] ?? 0);
                                $isCurrent = $clinicId === app_active_clinic_id();
                                $isActive = (int) ($clinic['ativo'] ?? 1) === 1;
                                $isReleased = (int) ($clinic['liberada'] ?? 1) === 1;
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= app_h((string) ($clinic['nome_fantasia'] ?? '')) ?></strong>
                                        <?php if ($isCurrent): ?>
                                            <span class="badge text-bg-primary ms-1">Atual</span>
                                        <?php endif; ?>
                                        <span class="badge text-bg-light ms-1">Codigo <?= $clinicId ?></span>
                                        <div class="small text-muted">
                                            <?= app_h((string) ($clinic['email'] ?? '')) ?>
                                            <?= trim((string) ($clinic['telefone'] ?? '')) !== '' ? ' | ' . app_h((string) $clinic['telefone']) : '' ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!$isActive): ?>
                                            <span class="dev-status dev-status-muted">Inativa</span>
                                        <?php elseif ($isReleased): ?>
                                            <span class="dev-status dev-status-success">Liberada</span>
                                        <?php else: ?>
                                            <span class="dev-status dev-status-warning">Bloqueada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= (int) ($clinic['total_usuarios'] ?? 0) ?> usuario(s)</div>
                                        <div class="small text-muted"><?= (int) ($clinic['total_profissionais'] ?? 0) ?> profissional(is), <?= (int) ($clinic['total_pacientes'] ?? 0) ?> paciente(s)</div>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                            <?php if (!$isCurrent && $isActive): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="switch_clinic">
                                                    <input type="hidden" name="clinic_id" value="<?= $clinicId ?>">
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Usar</button>
                                                </form>
                                            <?php endif; ?>
                                            <a class="btn btn-sm btn-outline-primary" href="desenvolvedor.php?<?= app_h(app_build_query(['clinic_id' => $clinicId])) ?>" data-edit-clinic="1">Editar</a>
                                            <form method="POST" onsubmit="return confirm('Excluir esta clinica e todos os dados vinculados?')">
                                                <input type="hidden" name="action" value="delete_clinic">
                                                <input type="hidden" name="clinic_id" value="<?= $clinicId ?>">
                                                <button class="btn btn-sm btn-outline-danger" type="submit" <?= $isCurrent ? 'disabled title="Nao exclua a clinica atual."' : '' ?>>Excluir</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card dev-card">
                <div class="card-header bg-white border-0 pb-0">
                    <h5 class="mb-1">Usuario padrao</h5>
                    <span class="text-muted small">Alterar aqui sincroniza todas as clinicas.</span>
                </div>
                <div class="card-body">
                    <form method="POST" class="dev-form d-grid gap-3">
                        <input type="hidden" name="action" value="save_default_user">
                        <div>
                            <label class="form-label small text-muted">Nome de exibicao</label>
                            <input type="text" name="default_nome" class="form-control" value="<?= app_h((string) ($defaultCredentials['nome_exibicao'] ?? app_default_developer_name())) ?>" required>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Login</label>
                            <input type="text" name="default_login" class="form-control" value="<?= app_h((string) ($defaultCredentials['login'] ?? app_default_developer_login())) ?>" required>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Nova senha</label>
                            <input type="password" name="default_senha" class="form-control" placeholder="Preencha apenas para trocar">
                        </div>
                        <div>
                            <label class="form-label small text-muted">Confirmar senha</label>
                            <input type="password" name="default_senha_confirmar" class="form-control" placeholder="Repita a nova senha">
                        </div>
                        <button class="btn btn-primary">Sincronizar em todas</button>
                    </form>
                </div>
            </div>

            <div class="card dev-card mt-3">
                <div class="card-body">
                    <h6 class="mb-2">Modulos</h6>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="index.php">Profissional</a>
                        <a class="btn btn-outline-primary btn-sm" href="secretaria.php">Secretaria</a>
                        <a class="btn btn-outline-primary btn-sm" href="administrativo.php">Administrativo</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="clinicFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1"><?= $isEditingClinic ? 'Editar clinica' : 'Nova clinica' ?></h5>
                    <div class="small text-muted">Ao cadastrar, o usuario padrao do desenvolvedor e criado automaticamente.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form method="POST" class="row g-3 dev-form">
                    <input type="hidden" name="action" value="save_clinic">
                    <input type="hidden" name="clinic_id" value="<?= (int) ($clinicFormValues['id'] ?? 0) ?>">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Nome da clinica</label>
                        <input type="text" name="nome_fantasia" class="form-control" value="<?= app_h((string) $clinicFormValues['nome_fantasia']) ?>" required data-page-autofocus="1">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Razao social</label>
                        <input type="text" name="razao_social" class="form-control" value="<?= app_h((string) $clinicFormValues['razao_social']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">CNPJ</label>
                        <input type="text" name="cnpj" class="form-control" value="<?= app_h((string) $clinicFormValues['cnpj']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">Telefone</label>
                        <input type="text" name="telefone" class="form-control" value="<?= app_h((string) $clinicFormValues['telefone']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">WhatsApp</label>
                        <input type="text" name="whatsapp" class="form-control" value="<?= app_h((string) $clinicFormValues['whatsapp']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">E-mail</label>
                        <input type="email" name="email" class="form-control" value="<?= app_h((string) $clinicFormValues['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Endereco</label>
                        <input type="text" name="endereco" class="form-control" value="<?= app_h((string) $clinicFormValues['endereco']) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small text-muted">Cidade</label>
                        <input type="text" name="cidade" class="form-control" value="<?= app_h((string) $clinicFormValues['cidade']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted">UF</label>
                        <input type="text" name="estado" class="form-control" maxlength="2" value="<?= app_h((string) $clinicFormValues['estado']) ?>">
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ativo" id="clinicActive" <?= !empty($clinicFormValues['ativo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="clinicActive">Clinica ativa</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="liberada" id="clinicReleased" <?= !empty($clinicFormValues['liberada']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="clinicReleased">Clinica liberada</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-column flex-md-row justify-content-between gap-2">
                        <a class="btn btn-outline-secondary" href="desenvolvedor.php">Limpar</a>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary px-4"><?= $isEditingClinic ? 'Salvar clinica' : 'Cadastrar clinica' ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($isEditingClinic || $autoOpenClinicForm): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('clinicFormModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
