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

$selectedProfile = app_request_method() === 'POST'
    ? (app_request_post('perfil', 'secretaria') ?? 'secretaria')
    : (app_request_query('perfil', 'secretaria') ?? 'secretaria');

if (!isset($profiles[$selectedProfile])) {
    $selectedProfile = 'secretaria';
}

$defaultAccessMap = app_page_access_map();
$manageablePages = [];

foreach ($defaultAccessMap as $page => $roles) {
    if (in_array('public', $roles, true) || in_array('auth', $roles, true)) {
        continue;
    }

    $manageablePages[$page] = $roles;
}

$forcedPagesByProfile = app_forced_profile_access_pages();

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'reset_permissions') {
        app_stmt_execute($conn, 'DELETE FROM perfil_permissoes WHERE clinica_id = ? AND perfil = ?', 'is', [$clinicId, $selectedProfile]);
        app_flash('success', 'Permissoes restauradas para o padrao do sistema.');
        app_redirect('administrativo_permissoes.php?' . app_build_query(['perfil' => $selectedProfile]));
    }

    if ($action === 'save_permissions') {
        $selectedPages = $_POST['paginas'] ?? [];
        $selectedPages = is_array($selectedPages) ? array_map('strval', $selectedPages) : [];
        $forcedPages = $forcedPagesByProfile[$selectedProfile] ?? [];

        $conn->begin_transaction();
        $ok = app_stmt_execute($conn, 'DELETE FROM perfil_permissoes WHERE clinica_id = ? AND perfil = ?', 'is', [$clinicId, $selectedProfile]);

        foreach ($manageablePages as $page => $roles) {
            $allowed = in_array($page, $selectedPages, true) || in_array($page, $forcedPages, true) ? 1 : 0;
            $ok = app_stmt_execute(
                $conn,
                'INSERT INTO perfil_permissoes (clinica_id, perfil, pagina, permitido) VALUES (?, ?, ?, ?)',
                'issi',
                [$clinicId, $selectedProfile, $page, $allowed]
            ) && $ok;
        }

        if ($ok) {
            $conn->commit();
            app_flash('success', 'Permissoes atualizadas com sucesso.');
        } else {
            $conn->rollback();
            app_flash('danger', 'Nao foi possivel salvar as permissoes.');
        }

        app_redirect('administrativo_permissoes.php?' . app_build_query(['perfil' => $selectedProfile]));
    }
}

$storedRows = app_stmt_all($conn, 'SELECT pagina, permitido FROM perfil_permissoes WHERE clinica_id = ? AND perfil = ?', 'is', [$clinicId, $selectedProfile]);
$storedMap = [];

foreach ($storedRows as $row) {
    $storedMap[(string) $row['pagina']] = (int) $row['permitido'] === 1;
}

$hasCustomPermissions = $storedRows !== [];
$currentAllowed = [];
$forcedPages = $forcedPagesByProfile[$selectedProfile] ?? [];

foreach ($manageablePages as $page => $roles) {
    $allowed = $hasCustomPermissions
        ? ($storedMap[$page] ?? false)
        : in_array($selectedProfile, $roles, true);

    if (in_array($page, $forcedPages, true)) {
        $allowed = true;
    }

    $currentAllowed[$page] = $allowed;
}

$pageLabels = [
    'index.php' => 'Painel profissional',
    'meu_cadastro.php' => 'Meu cadastro',
    'atendimentos.php' => 'Atendimentos',
    'novo_atendimento.php' => 'Acao: novo atendimento',
    'editar_atendimento.php' => 'Acao: editar atendimento',
    'excluir_atendimento.php' => 'Acao: excluir atendimento',
    'toggle_glosa.php' => 'Acao: marcar glosa',
    'buscar_guias.php' => 'Apoio: buscar guias',
    'guias.php' => 'Guias',
    'nova_guia.php' => 'Acao: nova guia',
    'editar_guia.php' => 'Acao: editar guia',
    'excluir_guia.php' => 'Acao: excluir guia',
    'baixar_guia.php' => 'Acao: baixar guia',
    'guia_modal_dados.php' => 'Apoio: dados da guia',
    'pacientes_busca.php' => 'Apoio: autocomplete pacientes',
    'gestao_guias.php' => 'Gestao de guias',
    'nova_guia_gestao.php' => 'Acao: nova guia gestao',
    'editar_guia_gestao.php' => 'Acao: editar guia gestao',
    'financeiro.php' => 'Financeiro profissional',
    'financeiro_mensal.php' => 'Financeiro mensal',
    'financeiro_mensal_dados.php' => 'Apoio: dados mensal',
    'financeiro_mensal_api.php' => 'Apoio: API mensal',
    'recebimentos.php' => 'Recebimentos',
    'administrativo.php' => 'Painel administrativo',
    'administrativo_profissionais.php' => 'Profissionais',
    'novo_profissional.php' => 'Acao: novo profissional',
    'editar_profissional.php' => 'Acao: editar profissional',
    'administrativo_usuarios.php' => 'Usuarios',
    'administrativo_permissoes.php' => 'Permissoes',
    'administrativo_financeiro.php' => 'Financeiro administrativo',
    'financeiro_plano_contas.php' => 'Plano de contas',
    'financeiro_centros_custo.php' => 'Centros de custo',
    'financeiro_contas_financeiras.php' => 'Contas financeiras',
    'novo_plano_contas.php' => 'Acao: novo plano de contas',
    'editar_plano_contas.php' => 'Acao: editar plano de contas',
    'financeiro_contas_pagar.php' => 'Contas a pagar',
    'nova_conta_pagar.php' => 'Acao: nova conta a pagar',
    'editar_conta_pagar.php' => 'Acao: editar conta a pagar',
    'financeiro_contas_receber.php' => 'Contas a receber',
    'nova_conta_receber.php' => 'Acao: nova conta a receber',
    'editar_conta_receber.php' => 'Acao: editar conta a receber',
    'relatorio_financeiro_fechamento.php' => 'Fechamento financeiro',
    'administrativo_lotes.php' => 'Faturamento/lotes',
    'secretaria.php' => 'Painel secretaria',
    'secretaria_agenda.php' => 'Agenda semanal',
    'agenda_liberacao.php' => 'Liberacao de agenda',
    'agenda_lista_agendamentos.php' => 'Lista de agendamentos',
    'agenda_relatorio_gerencial.php' => 'Relatorio gerencial da agenda',
    'secretaria_servicos.php' => 'Servicos',
    'novo_servico.php' => 'Acao: novo servico',
    'editar_servico.php' => 'Acao: editar servico',
    'relatorios.php' => 'Relatorios',
    'buscar_servicos_profissional.php' => 'Apoio: servicos por profissional',
    'desenvolvedor.php' => 'Painel desenvolvedor',
    'pacientes.php' => 'Pacientes',
    'novo_paciente.php' => 'Acao: novo paciente',
    'editar_paciente.php' => 'Acao: editar paciente',
    'excluir_paciente.php' => 'Acao: excluir paciente',
    'planos.php' => 'Planos',
    'editar_plano.php' => 'Acao: editar plano',
    'excluir_plano.php' => 'Acao: excluir plano',
    'buscar_plano.php' => 'Apoio: buscar plano',
    'novo_recebimento.php' => 'Acao: novo recebimento',
];

function permission_page_label(string $page, array $labels): string
{
    if (isset($labels[$page])) {
        return $labels[$page];
    }

    return ucfirst(str_replace(['_', '.php'], [' ', ''], $page));
}

function permission_page_group(string $page): string
{
    if (str_contains($page, 'agenda')) {
        return 'Agenda';
    }

    if (str_contains($page, 'guia') || str_contains($page, 'lote')) {
        return 'Guias e faturamento';
    }

    if (str_contains($page, 'financeiro') || str_contains($page, 'conta') || str_contains($page, 'recebimento') || str_contains($page, 'plano_contas') || str_contains($page, 'centros_custo')) {
        return 'Financeiro';
    }

    if (str_contains($page, 'paciente')) {
        return 'Pacientes';
    }

    if (str_contains($page, 'servico')) {
        return 'Servicos';
    }

    if (str_contains($page, 'profissional') || str_contains($page, 'usuario') || str_contains($page, 'permissoes')) {
        return 'Administrativo';
    }

    if (str_contains($page, 'relatorio')) {
        return 'Relatorios';
    }

    return 'Painel e acesso';
}

$groupedPages = [];

foreach (array_keys($manageablePages) as $page) {
    $groupedPages[permission_page_group($page)][] = $page;
}

ksort($groupedPages);

$users = app_stmt_all(
    $conn,
    'SELECT u.id, u.login, u.nome_exibicao, u.perfil, u.ativo, p.nome AS profissional_nome
     FROM usuarios u
     LEFT JOIN profissionais p ON p.id = u.profissional_id
     ORDER BY FIELD(u.perfil, "desenvolvedor", "administrativo", "secretaria", "profissional"), u.login'
);

$profileCounts = array_fill_keys(array_keys($profiles), 0);

foreach ($users as $user) {
    $profile = (string) ($user['perfil'] ?? '');

    if (isset($profileCounts[$profile])) {
        $profileCounts[$profile]++;
    }
}

$allowedTotal = count(array_filter($currentAllowed));
$totalPages = count($manageablePages);
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
    background:
        radial-gradient(circle at 8% 4%, rgba(226, 244, 239, 0.9), transparent 28%),
        linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}

.permissions-shell {
    padding: 0.7rem 0.9rem 1rem;
}

.permissions-shell .page-hero {
    padding: 0.88rem 1rem;
    border-radius: 18px;
}

.permissions-shell .page-hero h3 {
    font-size: 1.1rem;
}

.permissions-shell .page-hero p {
    font-size: 0.76rem;
}

.permission-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.94);
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}

.permission-card .card-header {
    padding: 0.68rem 0.82rem;
    background: transparent;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}

.permission-card .card-body {
    padding: 0.82rem;
}

.permission-title {
    margin: 0;
    color: #143b49;
    font-size: 0.9rem;
}

.profile-grid {
    display: grid;
    gap: 0.5rem;
    grid-template-columns: repeat(auto-fit, minmax(145px, 1fr));
}

.profile-option {
    display: block;
    padding: 0.58rem 0.68rem;
    border: 1px solid #dbe7ec;
    border-radius: 14px;
    color: #1d3945;
    text-decoration: none;
    background: #fff;
}

.profile-option.active {
    border-color: #1f7a8c;
    background: rgba(31, 122, 140, 0.1);
}

.profile-option strong {
    display: block;
    font-size: 0.78rem;
}

.profile-option span {
    color: #68828f;
    font-size: 0.68rem;
}

.user-table {
    font-size: 0.74rem;
}

.user-table td,
.user-table th {
    padding: 0.36rem 0.42rem;
    vertical-align: middle;
}

.permission-group {
    margin-bottom: 0.72rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 16px;
    overflow: hidden;
}

.permission-group-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.7rem;
    padding: 0.5rem 0.65rem;
    background: #f5f9fa;
}

.permission-group-head h6 {
    margin: 0;
    color: #143b49;
    font-size: 0.78rem;
}

.permission-list {
    display: grid;
    gap: 0.38rem;
    padding: 0.58rem;
    grid-template-columns: repeat(auto-fit, minmax(215px, 1fr));
}

.permission-check {
    min-height: 42px;
    margin: 0;
    padding: 0.48rem 0.58rem 0.45rem 2.05rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 12px;
    background: #fff;
}

.permission-check .form-check-input {
    margin-left: -1.45rem;
}

.permission-check label {
    display: block;
    color: #1d3945;
    font-size: 0.74rem;
    font-weight: 700;
    line-height: 1.1;
}

.permission-check small {
    color: #6b8591;
    font-size: 0.62rem;
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
    background: rgba(255, 255, 255, 0.96);
}

.permission-actions .btn,
.permissions-shell .page-hero .btn {
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
                <h3 class="mb-2">Permissoes por perfil</h3>
                <p>Escolha o perfil e marque as telas e acoes que ele pode usar.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-light btn-sm px-3" href="administrativo_usuarios.php">Usuarios</a>
                <a class="btn btn-outline-light btn-sm px-3" href="administrativo.php">Painel</a>
            </div>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="permission-card mb-3">
                <div class="card-header">
                    <h5 class="permission-title">Perfis</h5>
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
                    <h5 class="permission-title">Usuarios</h5>
                    <span class="text-muted small"><?= count($users) ?> registro(s)</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft user-table mb-0">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Perfil</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                        $profile = (string) ($user['perfil'] ?? '');
                                        $displayName = trim((string) ($user['nome_exibicao'] ?? ''));
                                        $login = trim((string) ($user['login'] ?? ''));
                                    ?>
                                    <tr class="<?= $profile === $selectedProfile ? 'is-active' : '' ?>">
                                        <td>
                                            <strong><?= app_h($displayName !== '' ? $displayName : $login) ?></strong>
                                            <div class="small text-muted"><?= app_h($login) ?></div>
                                        </td>
                                        <td><?= app_h($profiles[$profile] ?? ucfirst($profile)) ?></td>
                                        <td>
                                            <span class="status-dot <?= (int) ($user['ativo'] ?? 0) === 1 ? 'status-success' : 'status-danger' ?>"></span>
                                            <?= (int) ($user['ativo'] ?? 0) === 1 ? 'Ativo' : 'Inativo' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($users === []): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Nenhum usuario cadastrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <form method="POST" class="permission-card" id="permissionForm">
                <input type="hidden" name="perfil" value="<?= app_h($selectedProfile) ?>">
                <div class="card-header d-flex flex-column flex-lg-row justify-content-between gap-2 align-items-lg-center">
                    <div>
                        <h5 class="permission-title"><?= app_h($profiles[$selectedProfile]) ?></h5>
                        <div class="text-muted small">
                            <?= $allowedTotal ?> de <?= $totalPages ?> item(ns) liberados
                            <?= $hasCustomPermissions ? ' | configuracao personalizada' : ' | usando padrao do sistema' ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-permission-mark="1">Marcar todos</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-permission-clear="1">Limpar selecao</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php foreach ($groupedPages as $group => $pages): ?>
                        <div class="permission-group">
                            <div class="permission-group-head">
                                <h6><?= app_h($group) ?></h6>
                                <span class="text-muted small"><?= count($pages) ?> item(ns)</span>
                            </div>
                            <div class="permission-list">
                                <?php foreach ($pages as $page): ?>
                                    <?php
                                        $isForced = in_array($page, $forcedPages, true);
                                        $id = 'perm_' . preg_replace('/[^a-z0-9]+/i', '_', $page);
                                    ?>
                                    <div class="form-check permission-check">
                                        <input class="form-check-input" type="checkbox" name="paginas[]" value="<?= app_h($page) ?>" id="<?= app_h($id) ?>" <?= !empty($currentAllowed[$page]) ? 'checked' : '' ?> <?= $isForced ? 'disabled' : '' ?>>
                                        <?php if ($isForced): ?>
                                            <input type="hidden" name="paginas[]" value="<?= app_h($page) ?>">
                                        <?php endif; ?>
                                        <label for="<?= app_h($id) ?>" title="Arquivo interno: <?= app_h($page) ?>">
                                            <?= app_h(permission_page_label($page, $pageLabels)) ?>
                                            <small><?= app_h($page) ?></small>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="permission-actions">
                    <span class="text-muted small">Alteracao vale para todos os usuarios desse perfil.</span>
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
