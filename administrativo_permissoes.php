<?php
include 'config/db.php';

app_ensure_profile_permissions_schema($conn);
$clinicId = app_active_clinic_id();

$profiles = [
    'profissional' => 'Profissional',
    'secretaria' => 'Secretaria',
    'administrativo' => 'Administrativo',
    'desenvolvedor' => 'Desenvolvedor',
];

$users = app_stmt_all(
    $conn,
    'SELECT u.id, u.login, u.nome_exibicao, u.perfil, u.ativo, p.nome AS profissional_nome
     FROM usuarios u
     LEFT JOIN profissionais p ON p.id = u.profissional_id AND p.clinica_id = u.clinica_id
     WHERE u.clinica_id = ?
     ORDER BY FIELD(u.perfil, "desenvolvedor", "administrativo", "secretaria", "profissional"), u.login',
    'i',
    [$clinicId]
);

$selectedUserId = app_request_method() === 'POST'
    ? app_post_int('usuario_id')
    : app_query_int('usuario_id');
$selectedUser = null;

foreach ($users as $user) {
    if ((int) ($user['id'] ?? 0) === $selectedUserId) {
        $selectedUser = $user;
        break;
    }
}

$selectedProfile = app_request_method() === 'POST'
    ? (app_request_post('perfil', 'secretaria') ?? 'secretaria')
    : (app_request_query('perfil', '') ?: ($selectedUser['perfil'] ?? 'secretaria'));

if (!isset($profiles[$selectedProfile])) {
    $selectedProfile = 'secretaria';
}

$permissionModules = [
    [
        'id' => 'painel_profissional',
        'label' => 'Painel do profissional',
        'description' => 'Tela inicial do profissional e meu cadastro.',
        'pages' => ['index.php', 'meu_cadastro.php'],
    ],
    [
        'id' => 'painel_secretaria',
        'label' => 'Painel da secretaria',
        'description' => 'Entrada principal da secretaria.',
        'pages' => ['secretaria.php'],
    ],
    [
        'id' => 'painel_administrativo',
        'label' => 'Painel administrativo',
        'description' => 'Entrada administrativa e cadastro da clinica.',
        'pages' => ['administrativo.php', 'administrativo_clinica.php'],
    ],
    [
        'id' => 'agenda',
        'label' => 'Agenda',
        'description' => 'Abrir agenda, consultar horarios e usar servicos vinculados.',
        'pages' => ['secretaria_agenda.php', 'buscar_servicos_profissional.php'],
    ],
    [
        'id' => 'agenda_grupo',
        'label' => 'Agenda em grupo',
        'description' => 'Agendamentos coletivos por profissional e servico.',
        'pages' => ['secretaria_agenda_grupo.php'],
    ],
    [
        'id' => 'agenda_liberacao',
        'label' => 'Liberacao de agenda',
        'description' => 'Liberar horarios do profissional.',
        'pages' => ['agenda_liberacao.php'],
    ],
    [
        'id' => 'agenda_relatorios',
        'label' => 'Relatorios da agenda',
        'description' => 'Lista de agendamentos e relatorio gerencial.',
        'pages' => ['agenda_lista_agendamentos.php', 'agenda_relatorio_gerencial.php'],
    ],
    [
        'id' => 'pacientes',
        'label' => 'Cadastro de pacientes',
        'description' => 'Pesquisar, cadastrar, editar e ver historico do paciente.',
        'pages' => ['pacientes.php', 'novo_paciente.php', 'editar_paciente.php', 'pacientes_busca.php', 'paciente_historico.php'],
    ],
    [
        'id' => 'pacientes_excluir',
        'label' => 'Excluir pacientes',
        'description' => 'Permite apagar pacientes sem fichas, guias ou atendimentos vinculados.',
        'pages' => ['excluir_paciente.php'],
    ],
    [
        'id' => 'fichas_profissional',
        'label' => 'Fichas clinicas do profissional',
        'description' => 'Somente o profissional acessa as fichas que ele mesmo lancou.',
        'pages' => ['paciente_fichas.php'],
        'profiles' => ['profissional'],
    ],
    [
        'id' => 'atendimentos',
        'label' => 'Atendimentos e glosa',
        'description' => 'Consultar atendimentos e marcar glosa.',
        'pages' => ['atendimentos.php', 'toggle_glosa.php', 'buscar_guias.php'],
    ],
    [
        'id' => 'guias',
        'label' => 'Guias',
        'description' => 'Consultar, cadastrar, editar, baixar e gerir guias.',
        'pages' => ['guias.php', 'gestao_guias.php', 'nova_guia.php', 'editar_guia.php', 'nova_guia_gestao.php', 'editar_guia_gestao.php', 'baixar_guia.php', 'buscar_guias.php', 'guia_modal_dados.php'],
    ],
    [
        'id' => 'guias_excluir',
        'label' => 'Excluir guias',
        'description' => 'Permite apagar guias quando a regra do sistema permitir.',
        'pages' => ['excluir_guia.php'],
    ],
    [
        'id' => 'servicos',
        'label' => 'Servicos e precos',
        'description' => 'Cadastro de servicos e relatorio de precos.',
        'pages' => ['secretaria_servicos.php', 'novo_servico.php', 'editar_servico.php', 'buscar_servicos_profissional.php'],
    ],
    [
        'id' => 'profissionais',
        'label' => 'Profissionais',
        'description' => 'Cadastro de profissionais, servicos e vinculos.',
        'pages' => ['administrativo_profissionais.php', 'novo_profissional.php', 'editar_profissional.php'],
    ],
    [
        'id' => 'usuarios',
        'label' => 'Usuarios e permissoes',
        'description' => 'Cadastrar usuarios, definir perfil e ajustar acessos.',
        'pages' => ['administrativo_usuarios.php', 'administrativo_permissoes.php'],
    ],
    [
        'id' => 'financeiro_administrativo',
        'label' => 'Financeiro administrativo',
        'description' => 'Contas, plano de contas, centros de custo e fechamento.',
        'pages' => [
            'administrativo_financeiro.php',
            'financeiro_plano_contas.php',
            'financeiro_centros_custo.php',
            'financeiro_contas_financeiras.php',
            'financeiro_contas_pagar.php',
            'financeiro_contas_receber.php',
            'relatorio_financeiro_fechamento.php',
            'novo_plano_contas.php',
            'editar_plano_contas.php',
            'nova_conta_pagar.php',
            'editar_conta_pagar.php',
            'nova_conta_receber.php',
            'editar_conta_receber.php',
            'novo_recebimento.php',
        ],
    ],
    [
        'id' => 'financeiro_profissional',
        'label' => 'Financeiro profissional',
        'description' => 'Financeiro mensal e recebimentos do profissional.',
        'pages' => ['financeiro.php', 'financeiro_mensal.php', 'financeiro_mensal_dados.php', 'financeiro_mensal_api.php', 'recebimentos.php'],
    ],
    [
        'id' => 'faturamento',
        'label' => 'Faturamento e lotes',
        'description' => 'Montagem e baixa de lotes.',
        'pages' => ['administrativo_lotes.php'],
    ],
    [
        'id' => 'planos',
        'label' => 'Planos',
        'description' => 'Cadastro e manutencao de planos.',
        'pages' => ['planos.php', 'editar_plano.php', 'excluir_plano.php', 'buscar_plano.php'],
    ],
    [
        'id' => 'relatorios',
        'label' => 'Relatorios gerais',
        'description' => 'Relatorios operacionais do sistema.',
        'pages' => ['relatorios.php'],
    ],
    [
        'id' => 'desenvolvedor',
        'label' => 'Painel desenvolvedor',
        'description' => 'Recursos tecnicos do desenvolvedor.',
        'pages' => ['desenvolvedor.php'],
        'profiles' => ['desenvolvedor'],
    ],
];

$defaultAccessMap = app_page_access_map();
$allConfigurablePages = [];

foreach ($defaultAccessMap as $page => $roles) {
    if (in_array('public', $roles, true) || in_array('auth', $roles, true)) {
        continue;
    }

    $allConfigurablePages[$page] = $roles;
}

$coveredPages = [];

foreach ($permissionModules as $module) {
    foreach ($module['pages'] as $page) {
        $coveredPages[$page] = true;
    }
}

$missingPages = array_values(array_diff(array_keys($allConfigurablePages), array_keys($coveredPages)));

if ($missingPages !== []) {
    $permissionModules[] = [
        'id' => 'outros_recursos',
        'label' => 'Outros recursos',
        'description' => 'Recursos internos ainda nao classificados.',
        'pages' => $missingPages,
    ];
}

$visibleModules = array_values(array_filter(
    $permissionModules,
    static fn (array $module): bool => empty($module['profiles']) || in_array($selectedProfile, $module['profiles'], true)
));
$visibleModuleIds = array_column($visibleModules, 'id');
$forcedPagesByProfile = app_forced_profile_access_pages();
$forcedPages = $forcedPagesByProfile[$selectedProfile] ?? [];

function permission_pages_for_modules(array $modules, array $moduleIds, array $allConfigurablePages, string $profile): array
{
    $pages = [];

    foreach ($modules as $module) {
        if (!in_array($module['id'], $moduleIds, true)) {
            continue;
        }

        if (!empty($module['profiles']) && !in_array($profile, $module['profiles'], true)) {
            continue;
        }

        foreach ($module['pages'] as $page) {
            if (!isset($allConfigurablePages[$page])) {
                continue;
            }

            if ($page === 'paciente_fichas.php' && $profile !== 'profissional') {
                continue;
            }

            $pages[$page] = true;
        }
    }

    return array_keys($pages);
}

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'reset_permissions') {
        app_stmt_execute($conn, 'DELETE FROM perfil_permissoes WHERE clinica_id = ? AND perfil = ?', 'is', [$clinicId, $selectedProfile]);
        app_flash('success', 'Permissoes restauradas para o padrao do perfil.');
        app_redirect('administrativo_permissoes.php?' . app_build_query(['perfil' => $selectedProfile, 'usuario_id' => $selectedUserId ?: null]));
    }

    if ($action === 'save_permissions') {
        $selectedModules = $_POST['modulos'] ?? [];
        $selectedModules = is_array($selectedModules) ? array_values(array_intersect(array_map('strval', $selectedModules), $visibleModuleIds)) : [];
        $selectedPages = permission_pages_for_modules($visibleModules, $selectedModules, $allConfigurablePages, $selectedProfile);

        $conn->begin_transaction();
        $ok = app_stmt_execute($conn, 'DELETE FROM perfil_permissoes WHERE clinica_id = ? AND perfil = ?', 'is', [$clinicId, $selectedProfile]);

        foreach ($allConfigurablePages as $page => $roles) {
            $allowed = in_array($page, $selectedPages, true) || in_array($page, $forcedPages, true);

            if ($page === 'paciente_fichas.php' && $selectedProfile !== 'profissional') {
                $allowed = false;
            }

            $ok = app_stmt_execute(
                $conn,
                'INSERT INTO perfil_permissoes (clinica_id, perfil, pagina, permitido) VALUES (?, ?, ?, ?)',
                'issi',
                [$clinicId, $selectedProfile, $page, $allowed ? 1 : 0]
            ) && $ok;
        }

        if ($ok) {
            $conn->commit();
            app_flash('success', 'Permissoes salvas para o perfil ' . $profiles[$selectedProfile] . '.');
        } else {
            $conn->rollback();
            app_flash('danger', 'Nao foi possivel salvar as permissoes.');
        }

        app_redirect('administrativo_permissoes.php?' . app_build_query(['perfil' => $selectedProfile, 'usuario_id' => $selectedUserId ?: null]));
    }
}

$storedRows = app_stmt_all($conn, 'SELECT pagina, permitido FROM perfil_permissoes WHERE clinica_id = ? AND perfil = ?', 'is', [$clinicId, $selectedProfile]);
$storedMap = [];

foreach ($storedRows as $row) {
    $storedMap[(string) $row['pagina']] = (int) $row['permitido'] === 1;
}

$hasCustomPermissions = $storedRows !== [];
$currentAllowedPages = [];

foreach ($allConfigurablePages as $page => $roles) {
    $allowed = $hasCustomPermissions
        ? ($storedMap[$page] ?? false)
        : in_array($selectedProfile, $roles, true);

    if (in_array($page, $forcedPages, true)) {
        $allowed = true;
    }

    if ($page === 'paciente_fichas.php' && $selectedProfile !== 'profissional') {
        $allowed = false;
    }

    $currentAllowedPages[$page] = $allowed;
}

$moduleChecks = [];

foreach ($visibleModules as $module) {
    $moduleAllowed = false;

    foreach ($module['pages'] as $page) {
        if (!empty($currentAllowedPages[$page])) {
            $moduleAllowed = true;
            break;
        }
    }

    $moduleChecks[$module['id']] = $moduleAllowed;
}

$profileCounts = array_fill_keys(array_keys($profiles), 0);

foreach ($users as $user) {
    $profile = (string) ($user['perfil'] ?? '');

    if (isset($profileCounts[$profile])) {
        $profileCounts[$profile]++;
    }
}

$allowedTotal = count(array_filter($moduleChecks));
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Permissoes</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}

.permissions-shell {
    padding: 0.7rem 0.9rem 1rem;
}

.permissions-shell .page-hero {
    padding: 0.88rem 1rem;
    border-radius: 18px;
}

.permission-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.permission-card .card-header {
    padding: 0.72rem 0.85rem;
    background: transparent;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}

.permission-card .card-body {
    padding: 0.85rem;
}

.profile-grid,
.module-grid {
    display: grid;
    gap: 0.55rem;
}

.profile-grid {
    grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
}

.module-grid {
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
}

.profile-option,
.user-option,
.module-option {
    display: block;
    border: 1px solid #dbe7ec;
    border-radius: 14px;
    color: #1d3945;
    text-decoration: none;
    background: #fff;
}

.profile-option {
    padding: 0.6rem 0.72rem;
}

.profile-option.active,
.user-option.active {
    border-color: #1f7a8c;
    background: rgba(31, 122, 140, 0.1);
}

.profile-option strong,
.user-option strong {
    display: block;
    font-size: 0.78rem;
}

.profile-option span,
.user-option span,
.module-option small {
    color: #68828f;
    font-size: 0.68rem;
}

.user-list {
    display: grid;
    gap: 0.45rem;
    max-height: 440px;
    overflow: auto;
}

.user-option {
    padding: 0.52rem 0.64rem;
}

.module-option {
    min-height: 116px;
    padding: 0.72rem 0.75rem;
}

.module-option .form-check-input {
    margin-top: 0.16rem;
}

.module-option label {
    display: block;
    padding-left: 0.2rem;
    cursor: pointer;
}

.module-option strong {
    display: block;
    color: #153944;
    font-size: 0.82rem;
    line-height: 1.08;
}

.module-option p {
    margin: 0.28rem 0 0;
    color: #506b76;
    font-size: 0.72rem;
    line-height: 1.22;
}

.permission-actions {
    position: sticky;
    bottom: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.68rem 0.82rem;
    border-top: 1px solid rgba(18, 73, 88, 0.08);
    background: rgba(255, 255, 255, 0.97);
}

.permissions-shell .btn {
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 700;
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid permissions-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Permissoes simples</h3>
                <p>Escolha um usuario para ver o perfil dele. Depois marque as telas que esse perfil pode acessar.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-light btn-sm px-3" href="administrativo_usuarios.php">Cadastrar usuarios</a>
                <a class="btn btn-outline-light btn-sm px-3" href="administrativo.php">Painel</a>
            </div>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="permission-card mb-3">
                <div class="card-header">
                    <h5 class="mb-1 fs-6">1. Escolha o perfil</h5>
                    <div class="text-muted small">O perfil define o acesso de todos os usuarios daquele tipo.</div>
                </div>
                <div class="card-body">
                    <div class="profile-grid">
                        <?php foreach ($profiles as $profile => $label): ?>
                            <a class="profile-option <?= $profile === $selectedProfile ? 'active' : '' ?>" href="administrativo_permissoes.php?<?= app_h(app_build_query(['perfil' => $profile])) ?>">
                                <strong><?= app_h($label) ?></strong>
                                <span><?= (int) ($profileCounts[$profile] ?? 0) ?> usuario(s)</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="permission-card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-1 fs-6">Usuarios do sistema</h5>
                        <div class="text-muted small"><?= count($users) ?> usuario(s)</div>
                    </div>
                    <a href="administrativo_usuarios.php" class="btn btn-sm btn-outline-primary">Editar usuarios</a>
                </div>
                <div class="card-body">
                    <div class="user-list">
                        <?php foreach ($users as $user): ?>
                            <?php
                                $name = trim((string) ($user['nome_exibicao'] ?? '')) ?: trim((string) ($user['login'] ?? ''));
                                $professionalName = trim((string) ($user['profissional_nome'] ?? ''));
                                $userProfile = (string) ($user['perfil'] ?? '');
                            ?>
                            <a class="user-option <?= $selectedUserId === (int) $user['id'] ? 'active' : '' ?>" href="administrativo_permissoes.php?<?= app_h(app_build_query(['perfil' => $userProfile, 'usuario_id' => (int) $user['id']])) ?>">
                                <strong><?= app_h($name) ?></strong>
                                <span><?= app_h((string) ($user['login'] ?? '')) ?></span>
                                <span class="d-block">Perfil: <?= app_h($profiles[$userProfile] ?? ucfirst($userProfile)) ?></span>
                                <?php if ($professionalName !== ''): ?>
                                    <span class="d-block"><?= app_h($professionalName) ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if ($users === []): ?>
                            <div class="text-muted small">Nenhum usuario cadastrado.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <form method="POST" class="permission-card" id="permissionForm">
                <input type="hidden" name="perfil" value="<?= app_h($selectedProfile) ?>">
                <input type="hidden" name="usuario_id" value="<?= (int) $selectedUserId ?>">
                <div class="card-header d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center">
                    <div>
                        <h5 class="mb-1 fs-6">2. Telas liberadas para <?= app_h($profiles[$selectedProfile]) ?></h5>
                        <div class="text-muted small">
                            <?= $allowedTotal ?> de <?= count($visibleModules) ?> tela(s) marcada(s).
                            <?= $hasCustomPermissions ? 'Configuracao personalizada.' : 'Usando padrao do sistema.' ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-permission-mark="1">Marcar tudo</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-permission-clear="1">Limpar</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($selectedProfile === 'profissional'): ?>
                        <div class="alert alert-info py-2 small">
                            Para liberar cadastro de paciente ao profissional, marque <strong>Cadastro de pacientes</strong>.
                            As fichas clinicas continuam restritas: cada profissional ve somente as fichas que ele lancou.
                        </div>
                    <?php endif; ?>
                    <div class="module-grid">
                        <?php foreach ($visibleModules as $module): ?>
                            <?php
                                $moduleId = (string) $module['id'];
                                $inputId = 'module_' . preg_replace('/[^a-z0-9]+/i', '_', $moduleId);
                                $isForced = count(array_diff($module['pages'], $forcedPages)) === 0 && array_intersect($module['pages'], $forcedPages) !== [];
                            ?>
                            <div class="form-check module-option">
                                <input class="form-check-input" type="checkbox" name="modulos[]" value="<?= app_h($moduleId) ?>" id="<?= app_h($inputId) ?>" <?= !empty($moduleChecks[$moduleId]) ? 'checked' : '' ?> <?= $isForced ? 'disabled' : '' ?>>
                                <?php if ($isForced): ?>
                                    <input type="hidden" name="modulos[]" value="<?= app_h($moduleId) ?>">
                                <?php endif; ?>
                                <label for="<?= app_h($inputId) ?>">
                                    <strong><?= app_h((string) $module['label']) ?></strong>
                                    <p><?= app_h((string) $module['description']) ?></p>
                                    <?php if ($isForced): ?>
                                        <small>Obrigatorio para este perfil.</small>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="permission-actions">
                    <span class="text-muted small">Ao salvar, todos os usuarios do perfil <?= app_h($profiles[$selectedProfile]) ?> recebem estas permissoes.</span>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <button class="btn btn-outline-secondary" type="submit" name="action" value="reset_permissions" onclick="return confirm('Restaurar o padrao deste perfil?')">Restaurar padrao</button>
                        <button class="btn btn-primary px-4" type="submit" name="action" value="save_permissions">Salvar permissoes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const permissionForm = document.getElementById('permissionForm');

document.querySelector('[data-permission-mark]')?.addEventListener('click', () => {
    permissionForm.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((input) => {
        input.checked = true;
    });
});

document.querySelector('[data-permission-clear]')?.addEventListener('click', () => {
    permissionForm.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach((input) => {
        input.checked = false;
    });
});
</script>

</body>
</html>
