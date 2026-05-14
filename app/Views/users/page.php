<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Usuarios do Sistema</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
html,
body {
    height: 100%;
}

body {
    display: flex;
    flex-direction: column;
}

.users-shell {
    flex: 1;
    min-height: 0;
    padding-top: 0.35rem;
    padding-bottom: 0.85rem;
}

.users-shell .page-hero {
    margin-top: 0.35rem;
    padding: 0.96rem 1.05rem 0.9rem;
    border-radius: 20px;
}

.users-shell .page-hero h3 {
    font-size: 1.08rem;
}

.users-shell .page-hero p {
    font-size: 0.76rem;
    line-height: 1.18;
}

.users-shell .page-hero .btn {
    min-height: 38px;
    font-size: 0.78rem;
}

.users-shell .soft-card {
    border-radius: 18px;
}

.users-shell .soft-card .card-header {
    padding: 0.68rem 0.82rem 0.58rem;
}

.users-shell .soft-card .card-body {
    padding: 0.78rem 0.82rem;
}

.users-shell .toolbar-grid {
    gap: 0.7rem;
}

.users-shell .toolbar-grid .form-control,
.users-shell .toolbar-grid .btn {
    min-height: 40px;
    font-size: 0.8rem;
}

.user-table {
    font-size: 0.76rem;
}

.user-table thead th {
    padding: 0.34rem 0.45rem;
    font-size: 0.62rem;
    line-height: 1.08;
}

.user-table tbody td {
    padding: 0.34rem 0.45rem;
    line-height: 1.12;
}

.user-table .user-login {
    font-weight: 700;
    color: #16333f;
    font-size: 0.84rem;
    line-height: 1.08;
}

.user-table .small {
    margin-top: 0.08rem;
    line-height: 1.08;
}

.user-table .btn {
    min-height: 28px;
    padding-top: 0.22rem;
    padding-bottom: 0.22rem;
    font-size: 0.68rem;
    line-height: 1;
}

.users-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}

.users-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}

.users-modal .modal-body {
    padding: 0.95rem 1rem 1rem;
}

.users-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}

.users-modal .btn-close {
    box-shadow: none;
}

.users-form .form-control,
.users-form .form-select {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}

.users-form .btn {
    min-height: 40px;
    border-radius: 14px;
    font-size: 0.8rem;
}

.users-form .form-check {
    margin-top: 0.1rem;
    padding: 0.62rem 0.82rem 0.62rem 2.25rem;
    border: 1px solid rgba(19, 74, 89, 0.08);
    border-radius: 14px;
    background: rgba(247, 250, 251, 0.9);
}

.users-form .form-check-input {
    margin-top: 0.18rem;
}

.users-hint {
    color: #68828f;
    font-size: 0.72rem;
}

@media (max-width: 991px) {
    body {
        display: block;
    }

    .users-shell {
        min-height: auto;
        padding-bottom: 1rem;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<?php
$isEditingUser = (int) ($userFormValues['user_id'] ?? 0) > 0;
$activeUserId = (int) ($userFormValues['user_id'] ?? ($selectedUser['id'] ?? 0));
$isDefaultUserForm = !empty($userFormValues['usuario_padrao']);
?>

<div class="container page-shell users-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Usuarios do sistema</h3>
                <p>Gerencie logins, perfis de acesso e vinculos com profissionais.</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#userFormModal">+ Novo usuario</button>
                <a href="administrativo_profissionais.php" class="btn btn-light btn-sm rounded-pill px-3">Profissionais</a>
            </div>
        </div>
    </section>

    <div class="soft-card card mt-3">
        <div class="card-header">
            <div class="panel-title">
                <h5>Usuarios cadastrados</h5>
                <span class="text-muted small"><?= $usersPagination['total'] ?> registro(s)</span>
            </div>
        </div>
        <div class="card-body">
            <form class="toolbar-grid mb-3" method="GET">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="text" name="busca_usuario" class="form-control" value="<?= app_h($userFilters['busca_usuario']) ?>" placeholder="Login, nome ou vinculo">
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="administrativo_usuarios.php" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-soft align-middle mb-0 user-table">
                    <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th class="text-end">Acoes</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usersRows as $user): ?>
                        <?php
                            $userLogin = trim((string) ($user['login'] ?? ''));
                            $displayName = trim((string) ($user['nome_exibicao'] ?? ''));
                            $professionalName = trim((string) ($user['profissional_nome'] ?? ''));
                            $showDisplayName = $displayName !== '' && strcasecmp($displayName, $userLogin) !== 0;
                            $showProfessionalName = $professionalName !== ''
                                && strcasecmp($professionalName, $userLogin) !== 0
                                && strcasecmp($professionalName, $displayName) !== 0;
                            $professionalLine = $showProfessionalName ? $professionalName : ($professionalName === '' ? 'Sem vinculo' : '');
                            $isCurrentUser = (int) ($currentUser['id'] ?? 0) === (int) $user['id'];
                            $isDefaultUser = (int) ($user['usuario_padrao'] ?? 0) === 1;
                            $deleteTitle = $isCurrentUser
                                ? 'Nao e permitido excluir o usuario logado.'
                                : ($isDefaultUser ? 'Usuario padrao sincronizado pelo painel do desenvolvedor.' : '');
                        ?>
                        <tr class="<?= $activeUserId > 0 && $activeUserId === (int) $user['id'] ? 'is-active' : '' ?>">
                            <td>
                                <div class="user-login"><?= app_h($userLogin) ?></div>
                                <?php if ($isDefaultUser): ?>
                                    <span class="badge text-bg-info">Padrao global</span>
                                <?php endif; ?>
                                <?php if ($showDisplayName): ?>
                                    <div class="small text-muted"><?= app_h($displayName) ?></div>
                                <?php endif; ?>
                                <?php if ($professionalLine !== ''): ?>
                                    <div class="small text-muted"><?= app_h($professionalLine) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= app_h(ucfirst($user['perfil'])) ?></td>
                            <td>
                                <span class="status-dot <?= (int) $user['ativo'] === 1 ? 'status-success' : 'status-danger' ?>"></span>
                                <?= (int) $user['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="administrativo_usuarios.php?<?= app_h(app_build_query(['user_id' => $user['id'], 'busca_usuario' => $userFilters['busca_usuario'], 'user_page' => $usersPagination['page'] > 1 ? $usersPagination['page'] : null])) ?>">Editar</a>
                                    <form method="POST" onsubmit="return confirm('Excluir este usuario?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="busca_usuario" value="<?= app_h($userFilters['busca_usuario']) ?>">
                                        <input type="hidden" name="user_page" value="<?= (int) $usersPagination['page'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" <?= ($isCurrentUser || $isDefaultUser) ? 'disabled title="' . app_h($deleteTitle) . '"' : '' ?>>Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($usersRows === []): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                Nenhum usuario encontrado para os filtros informados.
                                <div class="mt-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#userFormModal">Cadastrar primeiro usuario</button>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?= app_render_pagination($usersPagination) ?>
        </div>
    </div>
</div>

<div class="modal fade users-modal" id="userFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1"><?= $isEditingUser ? 'Editar usuario' : 'Novo usuario' ?></h5>
                    <div class="small text-muted">
                        <?= $isEditingUser ? 'Atualize login, perfil e vinculo profissional sem ocupar espaco da lista.' : 'Cadastro rapido de usuario em popup.' ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?php if ($isDefaultUserForm): ?>
                    <div class="alert alert-info">
                        Este usuario e o padrao global. Altere login e senha no painel do desenvolvedor para refletir em todas as clinicas.
                    </div>
                <?php endif; ?>
                <form method="POST" class="row g-3 users-form">
                    <?php if ($isEditingUser): ?>
                        <input type="hidden" name="user_id" value="<?= (int) $userFormValues['user_id'] ?>">
                    <?php endif; ?>
                    <input type="hidden" name="busca_usuario" value="<?= app_h($userFilters['busca_usuario']) ?>">
                    <input type="hidden" name="user_page" value="<?= (int) $usersPagination['page'] ?>">
                    <div class="col-12">
                        <label class="form-label small text-muted">Nome de exibicao</label>
                        <input type="text" name="nome_exibicao" class="form-control" data-page-autofocus="1" value="<?= app_h((string) $userFormValues['nome_exibicao']) ?>" required <?= $isDefaultUserForm ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Login</label>
                        <input type="text" name="login" class="form-control" value="<?= app_h((string) $userFormValues['login']) ?>" required <?= $isDefaultUserForm ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Senha</label>
                        <input type="password" name="senha" class="form-control" placeholder="<?= $isEditingUser ? 'Preencha apenas para trocar' : 'Minimo 6 caracteres' ?>" <?= $isDefaultUserForm ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Perfil</label>
                        <select name="perfil" class="form-select" required <?= $isDefaultUserForm ? 'disabled' : '' ?>>
                            <?php foreach (['profissional' => 'Profissional', 'secretaria' => 'Secretaria', 'administrativo' => 'Administrativo', 'desenvolvedor' => 'Desenvolvedor'] as $value => $label): ?>
                                <option value="<?= app_h($value) ?>" <?= $userFormValues['perfil'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Profissional vinculado</label>
                        <select name="profissional_relacionado" class="form-select" <?= $isDefaultUserForm ? 'disabled' : '' ?>>
                            <option value="">Nao vincular</option>
                            <?php foreach ($professionalOptions as $professional): ?>
                                <option value="<?= (int) $professional['id'] ?>" <?= (int) $userFormValues['profissional_relacionado'] === (int) $professional['id'] ? 'selected' : '' ?>>
                                    <?= app_h($professional['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="ativo" id="userActive" <?= !empty($userFormValues['ativo']) ? 'checked' : '' ?> <?= $isDefaultUserForm ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="userActive">Usuario ativo</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <span class="users-hint">Perfis profissionais exigem vinculo com cadastro.</span>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($isEditingUser): ?>
                                <button type="submit" name="action" value="delete_user" class="btn btn-outline-danger" onclick="return confirm('Excluir este usuario?')" <?= ((int) ($currentUser['id'] ?? 0) === (int) $userFormValues['user_id'] || $isDefaultUserForm) ? 'disabled title="' . app_h($isDefaultUserForm ? 'Usuario padrao global.' : 'Nao e permitido excluir o usuario logado.') . '"' : '' ?>>Excluir</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary px-4" name="action" value="save_user" <?= $isDefaultUserForm ? 'disabled title="Altere no painel do desenvolvedor."' : '' ?>><?= $isEditingUser ? 'Salvar alteracoes' : 'Salvar usuario' ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($autoOpenUserModal)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('userFormModal');
    if (!modalElement || typeof bootstrap === 'undefined') {
        return;
    }

    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
