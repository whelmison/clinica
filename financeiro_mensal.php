<?php include 'config/db.php'; ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Financeiro Mensal</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.total-box { font-size:14px; }
.valor { text-align:right; }
.glosa { color:red; font-weight:bold; }
.recebido { color:green; font-weight:bold; }
</style>

</head>

<body>

<?php include 'partials/menu.php'; ?>

<div class="container mt-4">

<h4>Financeiro Mensal</h4>

<div class="card p-3 mb-3">

<div class="row">

<div class="col-auto">
<select id="mes" class="form-control">
<option value="">Mês</option>
<?php
$meses = [
'01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril',
'05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto',
'09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'
];
foreach($meses as $n=>$nome){
echo "<option value='$n'>$nome</option>";
}
?>
</select>
</div>

<div class="col-auto">
<select id="ano" class="form-control">
<option value="">Ano</option>
<?php for($a=date('Y');$a>=2020;$a--){
echo "<option value='$a'>$a</option>";
} ?>
</select>
</div>

<div class="col-auto">
<button onclick="buscar()" class="btn btn-primary">Filtrar</button>
</div>

<div class="col-auto">
<button onclick="window.print()" class="btn btn-dark">🖨 Imprimir</button>
</div>

</div>

</div>

<!-- TOTAIS -->
<div class="card p-3 mb-3 total-box">

<div class="row">

<div class="col">Previsto: <b id="previsto">R$ 0,00</b></div>
<div class="col glosa">Glosado: <span id="glosado">R$ 0,00</span></div>
<div class="col">Faturado: <b id="faturado">R$ 0,00</b></div>
<div class="col recebido">Recebido: <span id="recebido">R$ 0,00</span></div>
<div class="col">A Receber: <b id="receber">R$ 0,00</b></div>

</div>

</div>

<!-- TABELA -->
<div class="card p-3">

<table class="table table-bordered">

<thead>
<tr>
<th>Plano</th>
<th class="valor">Previsto</th>
<th class="valor">Recebido</th>
<th class="valor">Saldo</th>
</tr>
</thead>

<tbody id="tabela"></tbody>

</table>

</div>

</div>

<script>

function formatar(v){
return "R$ " + v.toFixed(2).replace('.',',');
}

function buscar(){

let mes = document.getElementById("mes").value;
let ano = document.getElementById("ano").value;

if(!mes || !ano){
alert("Selecione mês e ano");
return;
}

fetch("financeiro_mensal_api.php?mes="+mes+"&ano="+ano)
.then(r=>r.json())
.then(d=>{

document.getElementById("previsto").innerText = formatar(d.total_previsto);
document.getElementById("glosado").innerText = formatar(d.total_glosado);
document.getElementById("faturado").innerText = formatar(d.total_faturado);
document.getElementById("recebido").innerText = formatar(d.total_recebido);
document.getElementById("receber").innerText = formatar(d.total_receber);

let html="";

d.planos.forEach(p=>{
html += `
<tr>
<td>${p.plano}</td>
<td class="valor">${formatar(p.previsto)}</td>
<td class="valor">${formatar(p.recebido)}</td>
<td class="valor">${formatar(p.receber)}</td>
</tr>`;
});

document.getElementById("tabela").innerHTML = html;

});
}

</script>

</body>
</html>
