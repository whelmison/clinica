<?php include 'config/db.php'; ?>
<?php
$professionalId = app_current_professional_id();
$profile = $professionalId ? app_professional_profile($conn, $professionalId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $professionalId) {
    $ok = app_stmt_execute(
        $conn,
        'UPDATE profissionais SET nome = ?, endereco = ?, telefone = ?, profissao = ? WHERE clinica_id = ? AND id = ?',
        'ssssii',
        [
            trim((string) ($_POST['nome'] ?? '')),
            trim((string) ($_POST['endereco'] ?? '')),
            trim((string) ($_POST['telefone'] ?? '')),
            trim((string) ($_POST['profissao'] ?? '')),
            app_active_clinic_id(),
            $professionalId,
        ]
    );

    app_flash($ok ? 'success' : 'danger', $ok ? 'Cadastro atualizado com sucesso.' : 'Nao foi possivel atualizar o cadastro.');
    app_redirect('meu_cadastro.php');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Meu Cadastro</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container mt-4">
<h4 class="mb-3">Meu cadastro</h4>

<?php if (!$professionalId || !$profile): ?>
<div class="alert alert-warning">Seu usuario ainda nao esta vinculado a um cadastro profissional.</div>
<?php else: ?>
<div class="card p-3">
<form method="POST">
<div class="row">
<div class="col-md-6">
<label class="form-label">Nome</label>
<input type="text" name="nome" class="form-control mb-3" value="<?= app_h($profile['nome']) ?>" required>
</div>
<div class="col-md-6">
<label class="form-label">Profissao</label>
<input type="text" name="profissao" class="form-control mb-3" value="<?= app_h($profile['profissao']) ?>">
</div>
</div>

<div class="row">
<div class="col-md-6">
<label class="form-label">Telefone</label>
<input type="text" name="telefone" class="form-control mb-3" value="<?= app_h($profile['telefone']) ?>">
</div>
<div class="col-md-6">
<label class="form-label">Endereco</label>
<input type="text" name="endereco" class="form-control mb-3" value="<?= app_h($profile['endereco']) ?>">
</div>
</div>

<button class="btn btn-primary">Salvar alteracoes</button>
</form>
</div>
<?php endif; ?>
</div>

</body>
</html>
