<?php
include 'config/db.php';

use Clinic\Repositories\PayrollRepository;

$pdo = app_pdo();
$payrollRepository = new PayrollRepository($pdo);
$currentUser = app_current_user() ?? [];

if (!app_has_any_role(['secretaria', 'administrativo', 'desenvolvedor'])) {
    app_flash('warning', 'Sem permissao para acessar este relatorio.');
    app_redirect('index.php');
}

$startDate = app_request_query('start_date', date('Y-m-01'));
$endDate = app_request_query('end_date', date('Y-m-t'));
$filter = app_request_query('filter', 'mensal');

if ($filter === 'diario') {
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d');
} elseif ($filter === 'semanal') {
    $startDate = date('Y-m-d', strtotime('monday this week'));
    $endDate = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filter === 'mensal') {
    if (app_request_query('start_date') === null) {
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
    }
}

$reportData = $payrollRepository->generateReport($startDate, $endDate);
$totalGeral = array_sum(array_column($reportData, 'liquido_pagar'));

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Relatorio de Pagamento de Profissionais</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
@media print {
    body { background: #fff !important; margin: 0; padding: 0; }
    .page-shell { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    .no-print { display: none !important; }
    .soft-card { border: none !important; box-shadow: none !important; }
    .table th { background-color: #f8f9fa !important; color: #000 !important; }
    h3 { margin-bottom: 1rem !important; }
}
</style>
</head>
<body>

<div class="no-print">
    <?php include 'partials/menu.php'; ?>
</div>

<div class="container page-shell my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h3>Relatorio de Pagamento de Profissionais</h3>
        <button class="btn btn-outline-secondary" onclick="window.print()">Imprimir PDF</button>
    </div>

    <div class="soft-card card mb-4 no-print">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Periodo Rapido</label>
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="mensal" <?= $filter === 'mensal' ? 'selected' : '' ?>>Mensal</option>
                        <option value="semanal" <?= $filter === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                        <option value="diario" <?= $filter === 'diario' ? 'selected' : '' ?>>Diario</option>
                        <option value="personalizado" <?= $filter === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="start_date" class="form-control" value="<?= app_h($startDate) ?>" <?= $filter !== 'personalizado' ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="end_date" class="form-control" value="<?= app_h($endDate) ?>" <?= $filter !== 'personalizado' ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit">Atualizar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="d-none d-print-block mb-4">
        <h2>Relatorio de Pagamento de Profissionais</h2>
        <p>Periodo: <?= app_date_br($startDate) ?> a <?= app_date_br($endDate) ?></p>
    </div>

    <div class="soft-card card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Profissional</th>
                            <th class="text-center">Atendimentos</th>
                            <th class="text-end">Produzido</th>
                            <th class="text-end">Comissao</th>
                            <th class="text-end">Salario Fixo</th>
                            <th class="text-end">Descontos</th>
                            <th class="text-end">Liquido a Pagar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">Nenhum dado encontrado no periodo.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reportData as $row): ?>
                                <tr>
                                    <td class="fw-semibold"><?= app_h($row['nome']) ?></td>
                                    <td class="text-center"><?= $row['total_atendimentos'] ?></td>
                                    <td class="text-end"><?= app_money_br($row['total_produzido']) ?></td>
                                    <td class="text-end">
                                        <?= app_money_br($row['valor_comissao']) ?>
                                        <br><small class="text-muted">(<?= $row['comissao_percentual'] ?>%)</small>
                                    </td>
                                    <td class="text-end"><?= app_money_br($row['salario_fixo']) ?></td>
                                    <td class="text-end text-danger">- <?= app_money_br($row['desconto_imposto']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= app_money_br($row['liquido_pagar']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="6" class="text-end">Total Geral a Pagar:</td>
                                <td class="text-end text-success fs-5"><?= app_money_br($totalGeral) ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.querySelector('select[name="filter"]').addEventListener('change', function() {
        if(this.value === 'personalizado') {
            document.querySelector('input[name="start_date"]').readOnly = false;
            document.querySelector('input[name="end_date"]').readOnly = false;
        } else {
            this.form.submit();
        }
    });
</script>

</body>
</html>
