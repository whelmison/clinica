<?php include 'config/db.php'; ?>
<?php
$guideScope = app_professional_scope_sql('g.profissional_id');
$clinicId = app_active_clinic_id();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Recebimentos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
table { font-size: 13px; }
.valor { text-align: right; white-space: nowrap; }
</style>
</head>

<body>

<?php include 'partials/menu.php'; ?>

<div class="container mt-4">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
<div>
<h4 class="mb-1">Recebimentos</h4>
<p class="text-muted mb-0">Acompanhamento dos valores recebidos por guia.</p>
</div>
<a href="guias.php" class="btn btn-primary">Ir para guias</a>
</div>

<div class="card p-3">

<table class="table table-bordered table-hover align-middle mb-0">
<thead>
<tr>
<th>Guia</th>
<th>Paciente</th>
<th>Data</th>
<th class="valor">Valor da guia</th>
<th class="valor">Recebido</th>
<th class="valor">Saldo</th>
<th>Situação</th>
</tr>
</thead>

<tbody>
<?php
$res = $conn->query("
SELECT
    g.id,
    g.codigo,
    g.data,
    g.valor_guia,
    g.recebido,
    p.nome AS paciente_nome
FROM guias g
LEFT JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
WHERE g.clinica_id = {$clinicId}
AND g.recebido > 0
{$guideScope}
ORDER BY g.data DESC, g.id DESC
");

$totalGuia = 0;
$totalRecebido = 0;
$totalSaldo = 0;

if ($res && $res->num_rows > 0):
    while ($r = $res->fetch_assoc()):
        $valorGuia = floatval($r['valor_guia']);
        $recebido = floatval($r['recebido']);
        $saldo = $valorGuia - $recebido;

        $totalGuia += $valorGuia;
        $totalRecebido += $recebido;
        $totalSaldo += $saldo;

        if ($saldo <= 0) {
            $situacao = '<span class="badge text-bg-success">Pago</span>';
        } else {
            $situacao = '<span class="badge text-bg-warning">Parcial</span>';
        }
?>
<tr>
<td><?= htmlspecialchars($r['codigo'] ?: ('Guia #' . $r['id'])) ?></td>
<td><?= htmlspecialchars($r['paciente_nome'] ?? 'Sem paciente') ?></td>
<td><?= date('d/m/Y', strtotime($r['data'])) ?></td>
<td class="valor">R$ <?= number_format($valorGuia, 2, ',', '.') ?></td>
<td class="valor text-success">R$ <?= number_format($recebido, 2, ',', '.') ?></td>
<td class="valor">R$ <?= number_format($saldo, 2, ',', '.') ?></td>
<td><?= $situacao ?></td>
</tr>
<?php
    endwhile;
else:
?>
<tr>
<td colspan="7" class="text-center text-muted py-4">Nenhum recebimento encontrado.</td>
</tr>
<?php endif; ?>
</tbody>

<tfoot>
<tr class="fw-bold">
<td colspan="3">Totais</td>
<td class="valor">R$ <?= number_format($totalGuia, 2, ',', '.') ?></td>
<td class="valor text-success">R$ <?= number_format($totalRecebido, 2, ',', '.') ?></td>
<td class="valor">R$ <?= number_format($totalSaldo, 2, ',', '.') ?></td>
<td>-</td>
</tr>
</tfoot>
</table>

</div>

</div>

</body>
</html>
