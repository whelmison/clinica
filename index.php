<?php include 'config/db.php'; ?>
<?php
$professionalId = app_current_professional_id();
$guideFilter = app_is_professional_user()
    ? ($professionalId !== null ? "g.profissional_id = {$professionalId}" : '1 = 0')
    : '1 = 1';
$attendanceGuideFilter = app_is_professional_user()
    ? ($professionalId !== null ? "g.profissional_id = {$professionalId}" : '1 = 0')
    : '1 = 1';
$clinicId = app_active_clinic_id();
$clinicName = app_current_clinic_name();
$professionalPatientScope = app_is_professional_user()
    ? app_professional_scope_exists_for_patient('p.id')
    : '';

$profileCard = [
    'nome' => $clinicName,
    'registro' => app_is_developer() ? 'Visao completa' : 'Sem vinculo profissional',
    'profissao' => app_is_developer() ? 'Desenvolvedor' : 'Profissional',
    'foto' => 'assets/Gisele.jpg',
];

function dashboard_professional_photo_src(?string $path): string
{
    $default = 'assets/Gisele.jpg';
    $photo = ltrim(str_replace('\\', '/', trim((string) $path)), '/');

    if ($photo === '' || str_contains($photo, '..')) {
        return $default;
    }

    $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $photo);

    return is_file($fullPath) ? $photo : $default;
}

if ($professionalId !== null) {
    $professionalProfile = app_professional_profile($conn, $professionalId);

    if ($professionalProfile) {
        $profileCard = [
            'nome' => $professionalProfile['nome'],
            'registro' => $professionalProfile['telefone'] ?: 'Telefone nao informado',
            'profissao' => $professionalProfile['profissao'] ?: 'Profissional',
            'foto' => dashboard_professional_photo_src($professionalProfile['foto'] ?? ''),
        ];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= app_h($clinicName) ?> Gestao</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.card-profile {
    text-align: center;
}

.card-profile img {
    width: 180px;
    height: 180px;
    object-fit: cover;
    border-radius: 50%;
    margin-bottom: 15px;
}

.alert-card ul {
    padding-left: 18px;
    margin-bottom: 0;
}
</style>

</head>

<body>

<?php include 'partials/menu.php'; ?>

<div class="container mt-4">

<h3 class="mb-4">Dashboard</h3>

<div class="row">

<div class="col-md-4">
<div class="card p-3 card-profile">

<img src="<?= app_h($profileCard['foto']) ?>" alt="Foto de <?= app_h($profileCard['nome']) ?>">

<h5><?= app_h($profileCard['nome']) ?></h5>
<p class="mb-1"><?= app_h($profileCard['registro']) ?></p>
<p class="text-muted"><?= app_h($profileCard['profissao']) ?></p>

</div>
</div>

<div class="col-md-8">

<div class="row">

<div class="col-md-4">
<div class="card p-3 text-center">
<h6>Pacientes</h6>
<?php
$p = app_is_professional_user()
    ? $conn->query("SELECT COUNT(*) t FROM pacientes p WHERE p.clinica_id = {$clinicId} {$professionalPatientScope}")->fetch_assoc()
    : $conn->query("SELECT COUNT(*) t FROM pacientes WHERE clinica_id = {$clinicId}")->fetch_assoc();
?>
<h4><?= $p['t'] ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="card p-3 text-center">
<h6>Atendimentos</h6>
<?php
$a = app_is_professional_user()
    ? $conn->query("SELECT COUNT(*) t FROM atendimentos a INNER JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id WHERE a.clinica_id = {$clinicId} AND {$attendanceGuideFilter}")->fetch_assoc()
    : $conn->query("SELECT COUNT(*) t FROM atendimentos WHERE clinica_id = {$clinicId}")->fetch_assoc();
?>
<h4><?= $a['t'] ?></h4>
</div>
</div>

<div class="col-md-4">
<div class="card p-3 text-center">
<h6>Guias</h6>
<?php
$g = app_is_professional_user()
    ? $conn->query("SELECT COUNT(*) t FROM guias g WHERE g.clinica_id = {$clinicId} AND {$guideFilter}")->fetch_assoc()
    : $conn->query("SELECT COUNT(*) t FROM guias WHERE clinica_id = {$clinicId}")->fetch_assoc();
?>
<h4><?= $g['t'] ?></h4>
</div>
</div>

</div>

</div>

</div>

<?php
$vencendo = $conn->query("
SELECT
    g.id,
    g.total_sessoes,
    p.nome,
    (
        SELECT COUNT(*)
        FROM atendimentos a
        WHERE a.clinica_id = g.clinica_id
        AND a.guia_id = g.id
    ) as usadas
FROM guias g
JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
WHERE g.clinica_id = {$clinicId}
AND {$guideFilter}
HAVING (g.total_sessoes - usadas) <= 2
AND usadas < g.total_sessoes
ORDER BY (g.total_sessoes - usadas), p.nome
");

if (app_is_professional_user()) {
    $professionalPatientFilter = $professionalId !== null ? $professionalId : 0;
    $semGuia = $conn->query("
    SELECT DISTINCT p.nome
    FROM pacientes p
    WHERE p.clinica_id = {$clinicId}
    {$professionalPatientScope}
    AND NOT EXISTS (
        SELECT 1
        FROM guias g
        WHERE g.paciente_id = p.id
        AND g.clinica_id = p.clinica_id
        AND g.profissional_id = {$professionalPatientFilter}
        AND (
            SELECT COUNT(*)
            FROM atendimentos a
            WHERE a.clinica_id = g.clinica_id
            AND a.guia_id = g.id
        ) < g.total_sessoes
    )
    ORDER BY p.nome
    ");
} else {
    $semGuia = $conn->query("
    SELECT p.nome
    FROM pacientes p
    WHERE p.clinica_id = {$clinicId}
    AND NOT EXISTS (
        SELECT 1
        FROM guias g
        WHERE g.paciente_id = p.id
        AND g.clinica_id = p.clinica_id
        AND (
            SELECT COUNT(*)
            FROM atendimentos a
            WHERE a.clinica_id = g.clinica_id
            AND a.guia_id = g.id
        ) < g.total_sessoes
    )
    ORDER BY p.nome
    ");
}

$atrasado = $conn->query("
SELECT g.*, p.nome
FROM guias g
JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
WHERE g.clinica_id = {$clinicId}
AND {$guideFilter}
AND (g.valor_guia - g.recebido) > 0
ORDER BY (g.valor_guia - g.recebido) DESC
");
?>

<div class="row mt-4">

<div class="col-md-6 mb-3">
<div class="card p-3 border-warning alert-card">
<h5>Guias prestes a terminar</h5>

<?php if ($vencendo->num_rows == 0): ?>
<p class="text-muted">Nenhuma guia proxima do fim</p>
<?php else: ?>
<ul>
<?php while ($item = $vencendo->fetch_assoc()): ?>
<li><?= htmlspecialchars($item['nome']) ?> - faltam <?= (int) $item['total_sessoes'] - (int) $item['usadas'] ?> sessoes</li>
<?php endwhile; ?>
</ul>
<?php endif; ?>
</div>
</div>

<div class="col-md-6 mb-3">
<div class="card p-3 border-danger alert-card">
<h5>Pacientes sem guia</h5>

<?php if ($semGuia->num_rows == 0): ?>
<p class="text-muted">Todos os pacientes estao com guia ativa</p>
<?php else: ?>
<ul>
<?php while ($item = $semGuia->fetch_assoc()): ?>
<li><?= htmlspecialchars($item['nome']) ?></li>
<?php endwhile; ?>
</ul>
<?php endif; ?>
</div>
</div>

</div>

<div class="row">
<div class="col-md-12">
<div class="card p-3 border-success alert-card">
<h5>Pagamentos pendentes</h5>

<?php if ($atrasado->num_rows == 0): ?>
<p class="text-muted">Nenhum valor pendente</p>
<?php else: ?>
<ul>
<?php while ($item = $atrasado->fetch_assoc()):
$saldo = $item['valor_guia'] - $item['recebido'];
?>
<li><?= htmlspecialchars($item['nome']) ?> - R$ <?= number_format($saldo, 2, ',', '.') ?></li>
<?php endwhile; ?>
</ul>
<?php endif; ?>
</div>
</div>
</div>

</div>

</body>
</html>
