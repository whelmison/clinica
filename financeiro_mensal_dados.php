<?php
include 'config/db.php';
$guideScope = app_professional_scope_sql('g.profissional_id');
$clinicId = app_active_clinic_id();

// 🔒 evita erro quando não vem parâmetro
$mes = $_GET['mes'] ?? '';
$ano = $_GET['ano'] ?? '';

// se não selecionar, retorna vazio
if(!$mes || !$ano){
echo json_encode([
"total_previsto"=>0,
"total_glosado"=>0,
"total_faturado"=>0,
"total_recebido"=>0,
"total_receber"=>0,
"planos"=>[]
]);
exit;
}

// 🔥 DADOS DAS GUIAS (previsto e recebido)
$guias = $conn->query("
SELECT 
g.id,
g.valor_guia,
g.recebido,
pl.nome as plano
FROM guias g
LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
WHERE g.clinica_id = {$clinicId}
AND MONTH(g.data) = '$mes'
AND YEAR(g.data) = '$ano'
{$guideScope}
");

// 🔥 DADOS DE GLOSA (ATENDIMENTOS)
$glosas = [];
$resGlosa = $conn->query("
SELECT 
g.id as guia_id,
COUNT(*) as total_glosa,
CASE WHEN COALESCE(g.total_sessoes, 0) > 0 THEN g.valor_guia / g.total_sessoes ELSE 0 END as valor_sessao
FROM atendimentos a
LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
WHERE a.clinica_id = {$clinicId}
AND a.status_atendimento = 'Glosado'
AND MONTH(a.data) = '$mes'
AND YEAR(a.data) = '$ano'
{$guideScope}
GROUP BY g.id, g.valor_guia, g.total_sessoes
");

while($g = $resGlosa->fetch_assoc()){
$glosas[$g['guia_id']] = $g['total_glosa'] * $g['valor_sessao'];
}

// 🔥 CALCULO
$totalPrevisto = 0;
$totalGlosado = 0;
$totalRecebido = 0;

$planos = [];

while($g = $guias->fetch_assoc()){

$valor = floatval($g['valor_guia']);
$recebido = floatval($g['recebido']);
$glosa = $glosas[$g['id']] ?? 0;

$faturado = $valor - $glosa;

$totalPrevisto += $valor;
$totalGlosado += $glosa;
$totalRecebido += $recebido;

// por plano
$plano = $g['plano'] ?? 'Sem plano';

if(!isset($planos[$plano])){
$planos[$plano] = [
"previsto"=>0,
"glosado"=>0,
"recebido"=>0
];
}

$planos[$plano]['previsto'] += $valor;
$planos[$plano]['glosado'] += $glosa;
$planos[$plano]['recebido'] += $recebido;

}

$totalFaturado = $totalPrevisto - $totalGlosado;
$totalReceber = $totalFaturado - $totalRecebido;

// 🔥 RESPOSTA FINAL
echo json_encode([
"total_previsto"=>$totalPrevisto,
"total_glosado"=>$totalGlosado,
"total_faturado"=>$totalFaturado,
"total_recebido"=>$totalRecebido,
"total_receber"=>$totalReceber,
"planos"=>$planos
]);
