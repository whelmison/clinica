<?php include 'config/db.php'; ?>
<?php
$guideScope = app_professional_scope_sql('g.profissional_id');
$clinicId = app_active_clinic_id();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Financeiro</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
table { font-size:13px; }
</style>

</head>

<body>

<?php include 'partials/menu.php'; ?>

<div class="container mt-4">

<h4>Financeiro</h4>

<div class="card p-3">

<table class="table table-bordered">

<thead>
<tr>
<th>Tipo</th>
<th>Previsto</th>
<th>Recebido</th>
<th>Pendente</th>
</tr>
</thead>

<tbody>

<?php

$res = $conn->query("
SELECT pl.nome as plano,
SUM(g.valor_guia) as previsto,
SUM(g.recebido) as recebido
FROM guias g
LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
WHERE g.clinica_id = {$clinicId} {$guideScope}
GROUP BY pl.nome
");

$totalPrev=0;
$totalRec=0;

while($r=$res->fetch_assoc()){

$prev = $r['previsto'] ?? 0;
$rec = $r['recebido'] ?? 0;
$pend = $prev - $rec;

$totalPrev += $prev;
$totalRec += $rec;

echo "<tr>
<td>{$r['plano']}</td>
<td>R$ ".number_format($prev,2,',','.')."</td>
<td>R$ ".number_format($rec,2,',','.')."</td>
<td>R$ ".number_format($pend,2,',','.')."</td>
</tr>";
}

?>

<tr style="font-weight:bold;">
<td>Total Geral</td>
<td>R$ <?= number_format($totalPrev,2,',','.') ?></td>
<td>R$ <?= number_format($totalRec,2,',','.') ?></td>
<td>R$ <?= number_format($totalPrev-$totalRec,2,',','.') ?></td>
</tr>

</tbody>

</table>

</div>

</div>

</body>
</html>
