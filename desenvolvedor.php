<?php include 'config/db.php'; ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Painel Desenvolvedor</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.card-link {
    text-decoration: none;
    color: inherit;
}

.card-link .card {
    border-radius: 20px;
    border: 0;
    box-shadow: 0 1rem 2.5rem rgba(15, 76, 92, 0.08);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
}

.card-link:hover .card {
    transform: translateY(-4px);
    box-shadow: 0 1.2rem 2.8rem rgba(15, 76, 92, 0.14);
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<?php
$clinicId = app_active_clinic_id();
$totalUsers = (int) ($conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE clinica_id = {$clinicId}")->fetch_assoc()['total'] ?? 0);
$totalProfessionals = (int) ($conn->query("SELECT COUNT(*) AS total FROM profissionais WHERE clinica_id = {$clinicId}")->fetch_assoc()['total'] ?? 0);
$totalAppointments = (int) ($conn->query("SELECT COUNT(*) AS total FROM agenda WHERE clinica_id = {$clinicId}")->fetch_assoc()['total'] ?? 0);
$openReceivables = (float) ($conn->query("SELECT COALESCE(SUM(valor), 0) AS total FROM contas_receber WHERE clinica_id = {$clinicId} AND status <> 'pago'")->fetch_assoc()['total'] ?? 0);
?>

<div class="container mt-4">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
<div>
<h3 class="mb-1">Painel do desenvolvedor</h3>
<p class="text-muted mb-0">Visao geral dos modulos, dos usuarios e dos pontos de expansao do sistema.</p>
</div>
</div>

<div class="row g-3 mb-4">
<div class="col-md-3">
<div class="card p-3">
<div class="text-muted small">Usuarios</div>
<div class="fs-3 fw-bold"><?= $totalUsers ?></div>
</div>
</div>
<div class="col-md-3">
<div class="card p-3">
<div class="text-muted small">Profissionais</div>
<div class="fs-3 fw-bold"><?= $totalProfessionals ?></div>
</div>
</div>
<div class="col-md-3">
<div class="card p-3">
<div class="text-muted small">Agenda</div>
<div class="fs-3 fw-bold"><?= $totalAppointments ?></div>
</div>
</div>
<div class="col-md-3">
<div class="card p-3">
<div class="text-muted small">Receber em aberto</div>
<div class="fs-5 fw-bold">R$ <?= number_format($openReceivables, 2, ',', '.') ?></div>
</div>
</div>
</div>

<div class="row g-3">
<div class="col-md-4">
<a class="card-link" href="index.php">
<div class="card p-4 h-100">
<h5>Modulo profissional</h5>
<p class="text-muted mb-0">Entrar na visao filtrada do profissional com dashboard, atendimentos, guias e financeiro.</p>
</div>
</a>
</div>
<div class="col-md-4">
<a class="card-link" href="secretaria.php">
<div class="card p-4 h-100">
<h5>Modulo secretaria</h5>
<p class="text-muted mb-0">Agenda inteligente, servicos e lancamento de guias com foco operacional.</p>
</div>
</a>
</div>
<div class="col-md-4">
<a class="card-link" href="administrativo.php">
<div class="card p-4 h-100">
<h5>Modulo administrativo</h5>
<p class="text-muted mb-0">Profissionais, usuarios, financeiro estruturado, contas e lotes de faturamento.</p>
</div>
</a>
</div>
</div>
</div>

</body>
</html>
