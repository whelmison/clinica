<?php
include 'config/db.php';

app_ensure_column($conn, 'clinicas', 'logotipo', 'VARCHAR(255) NULL');

function admin_clinic_logo_web_path(?string $path): string
{
    return ltrim(str_replace('\\', '/', trim((string) $path)), '/');
}

function admin_clinic_logo_full_path(?string $path): string
{
    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, admin_clinic_logo_web_path($path));

    return __DIR__ . DIRECTORY_SEPARATOR . $normalized;
}

function admin_clinic_delete_logo(?string $path): void
{
    $webPath = admin_clinic_logo_web_path($path);

    if ($webPath === '' || !str_starts_with($webPath, 'uploads/clinicas/')) {
        return;
    }

    $fullPath = admin_clinic_logo_full_path($webPath);

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function admin_clinic_store_logo(array $file, int $clinicId): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }

    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Nao foi possivel enviar a logo. Tente selecionar a imagem novamente.'];
    }

    if ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
        return ['ok' => false, 'message' => 'A logo deve ter no maximo 3 MB.'];
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');

    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        return ['ok' => false, 'message' => 'Arquivo de logo invalido.'];
    }

    $imageInfo = @getimagesize($tmpPath);
    $mime = (string) ($imageInfo['mime'] ?? '');
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        return ['ok' => false, 'message' => 'Envie a logo em JPG, PNG ou WEBP.'];
    }

    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'clinicas';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        return ['ok' => false, 'message' => 'Nao foi possivel criar a pasta de logos.'];
    }

    $filename = 'clinica_' . $clinicId . '_logo_' . date('YmdHis') . '.' . $extensions[$mime];
    $relativePath = 'uploads/clinicas/' . $filename;
    $targetPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (!move_uploaded_file($tmpPath, $targetPath)) {
        return ['ok' => false, 'message' => 'Nao foi possivel salvar a logo da clinica.'];
    }

    return ['ok' => true, 'path' => $relativePath];
}

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
$currentLogo = admin_clinic_logo_web_path($clinic['logotipo'] ?? '');
$currentLogoExists = $currentLogo !== '' && is_file(admin_clinic_logo_full_path($currentLogo));

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
        $logoUpload = admin_clinic_store_logo($_FILES['logotipo'] ?? [], $clinicId);

        if (!$logoUpload['ok']) {
            app_flash('danger', $logoUpload['message']);
        } else {
            $uploadedLogo = admin_clinic_logo_web_path($logoUpload['path'] ?? '');
            $removeLogo = isset($_POST['remover_logotipo']);
            $nextLogo = $uploadedLogo !== '' ? $uploadedLogo : ($removeLogo ? null : ($currentLogo ?: null));

            $ok = app_stmt_execute(
                $conn,
                'UPDATE clinicas
                 SET nome_fantasia = ?, razao_social = ?, cnpj = ?, cnpj_digits = ?, telefone = ?, whatsapp = ?, email = ?, endereco = ?, cidade = ?, estado = ?, logotipo = ?
                 WHERE id = ?',
                'sssssssssssi',
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
                    $nextLogo,
                    $clinicId,
                ]
            );

            if ($ok) {
                $_SESSION['app_user']['clinica_nome'] = $form['nome_fantasia'];

                if (($uploadedLogo !== '' || $removeLogo) && $currentLogo !== $nextLogo) {
                    admin_clinic_delete_logo($currentLogo);
                }
            } elseif ($uploadedLogo !== '') {
                admin_clinic_delete_logo($uploadedLogo);
            }

            app_flash($ok ? 'success' : 'danger', $ok ? 'Cadastro da clinica atualizado.' : 'Nao foi possivel atualizar a clinica.');
            app_redirect('administrativo_clinica.php');
        }
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
<style>
.clinic-logo-preview {
    width: 160px;
    height: 86px;
    border: 1px solid #d7e5ea;
    border-radius: 12px;
    background: #f7fbfc;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    color: #6b8490;
    font-size: 0.82rem;
    text-align: center;
}
.clinic-logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
</style>
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
        <form method="POST" enctype="multipart/form-data" class="row g-3">
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
            <div class="col-12">
                <label class="form-label">Logotipo da empresa</label>
                <div class="d-flex flex-column flex-md-row gap-3 align-items-md-center">
                    <div class="clinic-logo-preview">
                        <?php if ($currentLogoExists): ?>
                            <img src="<?= app_h($currentLogo) ?>" alt="Logotipo da clinica">
                        <?php elseif ($currentLogo !== ''): ?>
                            Logo cadastrada nao localizada
                        <?php else: ?>
                            Sem logo
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <input type="file" name="logotipo" class="form-control" accept="image/png,image/jpeg,image/webp" title="Imagem usada nos relatorios impressos da clinica.">
                        <div class="form-text">Use PNG, JPG ou WEBP com ate 3 MB. Esta logo aparece no cabecalho dos relatorios impressos.</div>
                        <?php if ($currentLogo !== ''): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remover_logotipo" id="removerLogotipo" value="1">
                                <label class="form-check-label" for="removerLogotipo">Remover logo atual</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
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
