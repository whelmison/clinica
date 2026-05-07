<?php
include 'config/db.php';

use Clinic\Repositories\ReportRepository;

$pdo = app_pdo();
$reportRepository = new ReportRepository($pdo);
$options = $reportRepository->filters();
$startDate = app_request_query('data_inicial', date('Y-m-01')) ?? date('Y-m-01');
$endDate = app_request_query('data_final', date('Y-m-d')) ?? date('Y-m-d');
$filters = [
    'guia_id' => app_query_int('guia_id'),
    'paciente_id' => app_query_int('paciente_id'),
    'profissional_id' => app_query_int('profissional_id'),
];

$attendanceReport = $reportRepository->attendanceByPeriod($startDate, $endDate, $filters);
$guideReport = $reportRepository->guidesByProfessional($filters);
$billingReport = $reportRepository->billingSummary($startDate, $endDate, $filters);
$scheduleReport = $reportRepository->scheduleSummary($startDate, $endDate, $filters);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Relatorios</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2">Relatorios</h3>
                <p>Painel unico para atendimentos por periodo, guias por profissional, faturamento e agenda.</p>
            </div>
            <div class="selection-chip bg-white text-dark">Filtros globais por guia, paciente e profissional</div>
        </div>
    </section>

    <div class="soft-card card mt-4">
        <div class="card-body">
            <form class="toolbar-grid" method="GET">
                <div>
                    <label class="form-label small text-muted">Data inicial</label>
                    <input type="date" name="data_inicial" class="form-control" data-page-autofocus="1" value="<?= app_h($startDate) ?>">
                </div>
                <div>
                    <label class="form-label small text-muted">Data final</label>
                    <input type="date" name="data_final" class="form-control" value="<?= app_h($endDate) ?>">
                </div>
                <div>
                    <label class="form-label small text-muted">Guia</label>
                    <select name="guia_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($options['guides'] as $guide): ?>
                            <option value="<?= (int) $guide['id'] ?>" <?= (int) $filters['guia_id'] === (int) $guide['id'] ? 'selected' : '' ?>>
                                <?= app_h($guide['codigo'] ?: ('GUIA #' . $guide['id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Paciente</label>
                    <select name="paciente_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($options['patients'] as $patient): ?>
                            <option value="<?= (int) $patient['id'] ?>" <?= (int) $filters['paciente_id'] === (int) $patient['id'] ? 'selected' : '' ?>>
                                <?= app_h($patient['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label small text-muted">Profissional</label>
                    <select name="profissional_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($options['professionals'] as $professional): ?>
                            <option value="<?= (int) $professional['id'] ?>" <?= (int) $filters['profissional_id'] === (int) $professional['id'] ? 'selected' : '' ?>>
                                <?= app_h($professional['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="relatorios.php" class="btn btn-outline-secondary w-100">Listar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mt-2">
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header"><h5 class="mb-0">Atendimentos por periodo</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead><tr><th>Data</th><th>Total</th><th>Valor</th></tr></thead>
                            <tbody>
                            <?php foreach ($attendanceReport as $row): ?>
                                <tr>
                                    <td><?= app_date_br($row['data']) ?></td>
                                    <td><?= (int) $row['total_atendimentos'] ?></td>
                                    <td><?= app_money_br((float) $row['total_valor']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header"><h5 class="mb-0">Guias por profissional</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead><tr><th>Profissional</th><th>Guias</th><th>Total</th><th>Recebido</th></tr></thead>
                            <tbody>
                            <?php foreach ($guideReport as $row): ?>
                                <tr>
                                    <td><?= app_h($row['profissional_nome']) ?></td>
                                    <td><?= (int) $row['total_guias'] ?></td>
                                    <td><?= app_money_br((float) $row['total_valor']) ?></td>
                                    <td><?= app_money_br((float) $row['total_recebido']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header"><h5 class="mb-0">Faturamento</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead><tr><th>Status</th><th>Registros</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($billingReport as $row): ?>
                                <tr>
                                    <td><?= app_h(ucfirst($row['status'])) ?></td>
                                    <td><?= (int) $row['total_registros'] ?></td>
                                    <td><?= app_money_br((float) $row['total_valor']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="soft-card card h-100">
                <div class="card-header"><h5 class="mb-0">Agenda</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead><tr><th>Profissional</th><th>Status</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php foreach ($scheduleReport as $row): ?>
                                <tr>
                                    <td><?= app_h($row['profissional_nome']) ?></td>
                                    <td><?= app_h(ucfirst($row['status'])) ?></td>
                                    <td><?= (int) $row['total_agendamentos'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
