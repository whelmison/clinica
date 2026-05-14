<?php
include 'config/db.php';

$clinicId = app_active_clinic_id();
$clinic = app_stmt_one($conn, 'SELECT * FROM clinicas WHERE id = ? LIMIT 1', 'i', [$clinicId]);

if (!$clinic) {
    app_flash('danger', 'Clinica nao encontrada.');
    app_redirect(app_profile_home());
}

$form = [
    'nome_fantasia' => trim((string) ($_POST['nome_fantasia'] ?? $clinic['nome_fantasia'] ?? '')),
    'razao_social' => trim((string) ($_POST['razao_social'] ?? $clinic['razao_social'] ?? '')),
    'cnpj' => trim((string) ($_POST['cnpj'] ?? $clinic['cnpj'] ?? '')),
    'telefone' => trim((string) ($_POST['telefone'] ?? $clinic['telefone'] ?? '')),
    'whatsapp' => trim((string) ($_POST['whatsapp'] ?? $clinic['whatsapp'] ?? '')),
    'email' => trim((string) ($_POST['email'] ?? $clinic['email'] ?? '')),
    'endereco' => trim((string) ($_POST['endereco'] ?? $clinic['endereco'] ?? '')),
    'cidade' => trim((string) ($_POST['cidade'] ?? $clinic['cidade'] ?? '')),
    'estado' => trim((string) ($_POST['estado'] ?? $clinic['estado'] ?? '')),
];

if (app_request_method() === 'POST') {
    if ($form['nome_fantasia'] === '') {
        app_flash('danger', 'Informe o nome da clinica.');
    } elseif ($form['cnpj'] !== '' && !app_cnpj_valid($form['cnpj'])) {
        app_flash('danger', 'Informe um CNPJ valido.');
    } elseif ($form['cnpj'] !== '' && app_clinic_cnpj_conflict($conn, $form['cnpj'], $clinicId) !== null) {
        app_flash('danger', 'Ja existe uma clinica com este CNPJ.');
    } else {
        $cnpj = $form['cnpj'] !== '' ? app_format_cnpj($form['cnpj']) : null;
        $cnpjDigits = $form['cnpj'] !== '' ? app_only_digits($form['cnpj']) : null;
        $ok = app_stmt_execute(
            $conn,
            'UPDATE clinicas
             SET nome_fantasia = ?, razao_social = ?, cnpj = ?, cnpj_digits = ?, telefone = ?, whatsapp = ?, email = ?, endereco = ?, cidade = ?, estado = ?
             WHERE id = ?',
            'ssssssssssi',
            [
                $form['nome_fantasia'],
                $form['razao_social'] ?: null,
                $cnpj,
                $cnpjDigits,
                $form['telefone'] ?: null,
                $form['whatsapp'] ?: null,
                $form['email'] ?: null,
                $form['endereco'] ?: null,
                $form['cidade'] ?: null,
                $form['estado'] ?: null,
                $clinicId,
            ]
        );

        if ($ok) {
            $_SESSION['app_user']['clinica_nome'] = $form['nome_fantasia'];
        }

        app_flash($ok ? 'success' : 'danger', $ok ? 'Cadastro da clinica atualizado.' : 'Nao foi possivel atualizar a clinica.');
        app_redirect('administrativo_clinica.php');
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cadastro da Clinica</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>
<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="page-kicker">Gerencia da aplicacao</div>
                <h3 class="mb-2">Cadastro da clinica</h3>
                <p>Essas informacoes identificam a clinica ativa e definem o nome exibido no sistema.</p>
            </div>
            <a href="administrativo.php" class="btn btn-light btn-sm rounded-pill px-3">Voltar</a>
        </div>
    </section>

    <div class="content-card p-3">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nome da clinica</label>
                <input type="text" name="nome_fantasia" class="form-control" value="<?= app_h($form['nome_fantasia']) ?>" required title="Nome que aparece no topo do sistema.">
            </div>
            <div class="col-md-6">
                <label class="form-label">Razao social</label>
                <input type="text" name="razao_social" class="form-control" value="<?= app_h($form['razao_social']) ?>" title="Nome legal ou fiscal da clinica, quando houver.">
            </div>
            <div class="col-md-3">
                <label class="form-label">CNPJ</label>
                <input type="text" name="cnpj" class="form-control" value="<?= app_h($form['cnpj']) ?>" title="Documento da clinica.">
            </div>
            <div class="col-md-3">
                <label class="form-label">Telefone</label>
                <input type="text" name="telefone" class="form-control" value="<?= app_h($form['telefone']) ?>" title="Telefone principal da clinica.">
            </div>
            <div class="col-md-3">
                <label class="form-label">WhatsApp</label>
                <input type="text" name="whatsapp" class="form-control" value="<?= app_h($form['whatsapp']) ?>" title="WhatsApp usado para contato.">
            </div>
            <div class="col-md-3">
                <label class="form-label">E-mail</label>
                <input type="email" name="email" class="form-control" value="<?= app_h($form['email']) ?>" title="E-mail administrativo da clinica.">
            </div>
            <div class="col-md-6">
                <label class="form-label">Endereco</label>
                <input type="text" name="endereco" class="form-control" value="<?= app_h($form['endereco']) ?>" title="Endereco da unidade.">
            </div>
            <div class="col-md-4">
                <label class="form-label">Cidade</label>
                <input type="text" name="cidade" class="form-control" value="<?= app_h($form['cidade']) ?>" title="Cidade da clinica.">
            </div>
            <div class="col-md-2">
                <label class="form-label">UF</label>
                <input type="text" name="estado" class="form-control" maxlength="2" value="<?= app_h($form['estado']) ?>" title="Estado em duas letras.">
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="administrativo.php" class="btn btn-outline-secondary">Cancelar</a>
                <button class="btn btn-primary">Salvar clinica</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
