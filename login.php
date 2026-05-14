<?php include 'config/db.php'; ?>
<?php
app_install_schema($conn);

if (app_has_users($conn)) {
    app_ensure_default_developer_user($conn);
}

$message = null;
$messageType = 'warning';
$postedAction = '';
$clinicCnpjInput = trim((string) ($_POST['clinica_cnpj'] ?? ''));
$selectedClinicId = 0;

function login_resolve_clinic_id_from_cnpj(mysqli $conn, string $cnpj, ?string &$message): ?int
{
    $cnpj = trim($cnpj);

    if ($cnpj === '') {
        return null;
    }

    if (strlen(app_only_digits($cnpj)) !== 14) {
        $message = 'Informe o CNPJ da clinica com 14 digitos.';

        return null;
    }

    $clinics = app_active_clinics_by_cnpj($conn, $cnpj, 2);

    if (count($clinics) > 1) {
        $message = 'Este CNPJ esta vinculado a mais de uma clinica ativa. Fale com o suporte.';

        return null;
    }

    if ($clinics === []) {
        $message = 'CNPJ da clinica nao encontrado.';

        return null;
    }

    return (int) $clinics[0]['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedAction = $_POST['action'] ?? '';

    if (!app_has_users($conn) && $postedAction === 'setup') {
        $result = app_create_first_user(
            $conn,
            trim((string) ($_POST['clinica_nome'] ?? '')),
            trim((string) ($_POST['nome'] ?? '')),
            trim((string) ($_POST['login'] ?? '')),
            (string) ($_POST['senha'] ?? ''),
            (string) ($_POST['clinica_cnpj'] ?? '')
        );

        if ($result['ok']) {
            app_flash('success', $result['message'] . ' Entre com suas credenciais para continuar.');
            app_redirect('login.php');
        }

        $message = $result['message'];
    }

    if (app_has_users($conn) && $postedAction === 'login') {
        $selectedClinicId = login_resolve_clinic_id_from_cnpj($conn, $clinicCnpjInput, $message) ?? 0;

        if ($message === null) {
            $result = app_attempt_login(
                $conn,
                trim((string) ($_POST['login'] ?? '')),
                (string) ($_POST['senha'] ?? ''),
                $selectedClinicId > 0 ? $selectedClinicId : null
            );

            if ($result['ok']) {
                app_redirect(app_profile_home());
            }

            $message = $result['message'];
        }
    }

    if (app_has_users($conn) && $postedAction === 'create_account') {
        $result = app_create_clinic_account($conn, $_POST);

        if ($result['ok']) {
            app_flash('success', $result['message']);
            app_redirect('login.php');
        }

        $message = $result['message'];
    }

    if (app_has_users($conn) && $postedAction === 'reset_password') {
        $selectedClinicId = login_resolve_clinic_id_from_cnpj($conn, $clinicCnpjInput, $message) ?? 0;

        if ($message === null) {
            $result = app_reset_user_password(
                $conn,
                trim((string) ($_POST['login'] ?? '')),
                (string) ($_POST['codigo_reset'] ?? ''),
                (string) ($_POST['nova_senha'] ?? ''),
                (string) ($_POST['confirmar_senha'] ?? ''),
                $selectedClinicId > 0 ? $selectedClinicId : null
            );

            if ($result['ok']) {
                app_flash('success', $result['message']);
                app_redirect('login.php');
            }

            $message = $result['message'];
        }
    }
}

$flash = app_take_flash();
$setupMode = !app_has_users($conn);
$activeClinics = $setupMode ? [] : app_active_clinics($conn);
$defaultClinicCnpj = count($activeClinics) === 1 ? (string) ($activeClinics[0]['cnpj'] ?? '') : '';
$clinicCnpjValue = $clinicCnpjInput !== '' ? $clinicCnpjInput : $defaultClinicCnpj;

$createAccountMode = !$setupMode && (((string) ($_GET['criar_conta'] ?? '') === '1') || $postedAction === 'create_account');
$pageTitle = $setupMode ? 'Configuracao inicial' : ($createAccountMode ? 'Criar conta da clinica' : 'Acesso ao sistema');
$pageDescription = $setupMode
    ? 'Cadastre a primeira clinica e o primeiro usuario desenvolvedor deste computador.'
    : ($createAccountMode
        ? 'Cadastre uma nova clinica local com seu usuario administrativo.'
        : 'Entre com o CNPJ da clinica, seu login e sua senha para acessar o modulo do seu perfil.');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Entrar | Sistema da Clinica</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    min-height: 100vh;
    background:
        radial-gradient(circle at top left, rgba(31, 122, 140, 0.16), transparent 40%),
        linear-gradient(135deg, #f4fbfc, #eaf4f7 50%, #f6f7fb);
}

.login-shell {
    min-height: 100vh;
}

.login-card {
    border: 0;
    border-radius: 24px;
    box-shadow: 0 1.5rem 4rem rgba(15, 76, 92, 0.16);
}

.brand-badge {
    background: #0f4c5c;
    color: #fff;
    border-radius: 999px;
    display: inline-block;
    padding: 0.4rem 0.9rem;
    font-size: 0.8rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
</style>
</head>
<body>

<div class="container login-shell d-flex align-items-center justify-content-center py-5">
<div class="row justify-content-center w-100">
<div class="col-lg-5 col-md-7">
<div class="card login-card p-4 p-lg-5">
<div class="mb-4">
<span class="brand-badge">Sistema da Clinica</span>
<h1 class="h3 mt-3 mb-2"><?= app_h($pageTitle) ?></h1>
<p class="text-muted mb-0">
<?= app_h($pageDescription) ?>
</p>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= app_h($flash['type']) ?>"><?= app_h($flash['message']) ?></div>
<?php endif; ?>

<?php if ($message): ?>
<div class="alert alert-<?= app_h($messageType) ?>"><?= app_h($message) ?></div>
<?php endif; ?>

<?php if ($setupMode): ?>
<form method="POST" class="d-grid gap-3">
<input type="hidden" name="action" value="setup">

<div>
<label class="form-label">Nome da clinica</label>
<input type="text" name="clinica_nome" class="form-control" required autofocus>
</div>

<div>
<label class="form-label">CNPJ da clinica</label>
<input type="text" name="clinica_cnpj" class="form-control" data-mask-cnpj maxlength="18" value="<?= app_h((string) ($_POST['clinica_cnpj'] ?? '')) ?>">
</div>

<div>
<label class="form-label">Nome de exibicao</label>
<input type="text" name="nome" class="form-control" required value="<?= app_h((string) ($_POST['nome'] ?? app_default_developer_name())) ?>">
</div>

<div>
<label class="form-label">Login</label>
<input type="text" name="login" class="form-control" required value="<?= app_h((string) ($_POST['login'] ?? app_default_developer_login())) ?>">
</div>

<div>
<label class="form-label">Senha</label>
<input type="password" name="senha" class="form-control" minlength="6" required value="<?= app_h((string) ($_POST['senha'] ?? app_default_developer_password())) ?>">
</div>

<button class="btn btn-primary btn-lg">Criar usuario inicial</button>
</form>
<?php elseif ($createAccountMode): ?>
<form method="POST" class="d-grid gap-3">
<input type="hidden" name="action" value="create_account">

<div class="alert alert-info mb-0">
Usuario padrao do desenvolvedor sera criado automaticamente em todas as clinicas.
</div>

<div>
<label class="form-label">Nome da clinica</label>
<input type="text" name="clinica_nome" class="form-control" required autofocus value="<?= app_h((string) ($_POST['clinica_nome'] ?? '')) ?>">
</div>

<div>
<label class="form-label">CNPJ da clinica</label>
<input type="text" name="clinica_cnpj" class="form-control" data-mask-cnpj maxlength="18" value="<?= app_h((string) ($_POST['clinica_cnpj'] ?? '')) ?>">
</div>

<div class="row g-3">
<div class="col-md-6">
<label class="form-label">Telefone</label>
<input type="text" name="telefone" class="form-control" value="<?= app_h((string) ($_POST['telefone'] ?? '')) ?>">
</div>
<div class="col-md-6">
<label class="form-label">E-mail</label>
<input type="email" name="email" class="form-control" value="<?= app_h((string) ($_POST['email'] ?? '')) ?>">
</div>
</div>

<div>
<label class="form-label">Nome do usuario administrador</label>
<input type="text" name="nome" class="form-control" required value="<?= app_h((string) ($_POST['nome'] ?? '')) ?>">
</div>

<div>
<label class="form-label">Login</label>
<input type="text" name="login" class="form-control" required value="<?= app_h((string) ($_POST['login'] ?? '')) ?>">
</div>

<div>
<label class="form-label">Senha</label>
<input type="password" name="senha" class="form-control" minlength="6" required>
</div>

<button class="btn btn-primary btn-lg">Criar clinica</button>
<a class="btn btn-outline-secondary" href="login.php">Voltar para login</a>
</form>
<?php else: ?>
<form method="POST" class="d-grid gap-3">
<input type="hidden" name="action" value="login">

<div>
<label class="form-label">CNPJ da clinica</label>
<input type="text" name="clinica_cnpj" class="form-control" data-mask-cnpj maxlength="18" autofocus value="<?= app_h($clinicCnpjValue) ?>" placeholder="00.000.000/0000-00">
<div class="form-text">Informe para escolher a clinica. Deixe em branco se seu login for unico.</div>
</div>

<div>
<label class="form-label">Login</label>
<input type="text" name="login" class="form-control" required value="<?= app_h((string) ($_POST['login'] ?? '')) ?>">
</div>

<div>
<label class="form-label">Senha</label>
<input type="password" name="senha" class="form-control" required>
</div>

<button class="btn btn-primary btn-lg">Entrar</button>
</form>

<div class="d-grid mt-3">
<a class="btn btn-outline-primary" href="login.php?criar_conta=1">Criar conta para nova clinica</a>
</div>

<hr class="my-4">

<details>
<summary class="text-primary fw-semibold" style="cursor: pointer;">Esqueci minha senha</summary>
<form method="POST" class="d-grid gap-3 mt-3">
<input type="hidden" name="action" value="reset_password">

<div>
<label class="form-label">CNPJ da clinica</label>
<input type="text" name="clinica_cnpj" class="form-control" data-mask-cnpj maxlength="18" value="<?= app_h($clinicCnpjValue) ?>" placeholder="00.000.000/0000-00">
</div>

<div>
<label class="form-label">Login</label>
<input type="text" name="login" class="form-control" required value="<?= app_h((string) ($_POST['login'] ?? '')) ?>">
</div>

<div>
<label class="form-label">Codigo local de reset</label>
<input type="text" name="codigo_reset" class="form-control" inputmode="numeric" required>
<div class="form-text">O codigo fica no arquivo local de seguranca da clinica.</div>
</div>

<div>
<label class="form-label">Nova senha</label>
<input type="password" name="nova_senha" class="form-control" minlength="6" required>
</div>

<div>
<label class="form-label">Confirmar nova senha</label>
<input type="password" name="confirmar_senha" class="form-control" minlength="6" required>
</div>

<button class="btn btn-outline-primary">Redefinir senha</button>
</form>
</details>
<?php endif; ?>
</div>
</div>
</div>
</div>

<script>
function loginDigits(value) {
    return String(value || '').replace(/\D+/g, '');
}

function loginMaskCnpj(value) {
    const digits = loginDigits(value).slice(0, 14);

    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/^(\d{2})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3/$4')
        .replace(/^(\d{2})\.(\d{3})\.(\d{3})\/(\d{4})(\d)/, '$1.$2.$3/$4-$5');
}

document.querySelectorAll('[data-mask-cnpj]').forEach((input) => {
    input.value = loginMaskCnpj(input.value);
    input.addEventListener('input', () => {
        input.value = loginMaskCnpj(input.value);
    });
});
</script>

</body>
</html>
