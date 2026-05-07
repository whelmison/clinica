<?php
include 'config/db.php';

header('Content-Type: application/json; charset=UTF-8');

$guideScope = app_professional_scope_sql('g.profissional_id');
$clinicId = app_active_clinic_id();
$mes = $_GET['mes'] ?? '';
$ano = $_GET['ano'] ?? '';

if (!$mes || !$ano) {
    echo json_encode([
        'total_previsto' => 0,
        'total_glosado' => 0,
        'total_faturado' => 0,
        'total_recebido' => 0,
        'total_receber' => 0,
        'planos' => [],
    ]);
    exit;
}

$guias = $conn->query("
SELECT
    g.id,
    g.valor_guia,
    g.recebido,
    pl.nome AS plano
FROM guias g
LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
WHERE g.clinica_id = {$clinicId}
AND MONTH(g.data) = '{$mes}'
AND YEAR(g.data) = '{$ano}'
{$guideScope}
");

$glosas = [];
$resGlosa = $conn->query("
SELECT
    g.id AS guia_id,
    COUNT(*) AS total_glosa,
    pl.valor_sessao
FROM atendimentos a
LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
WHERE a.clinica_id = {$clinicId}
AND a.status_atendimento = 'Glosado'
AND MONTH(a.data) = '{$mes}'
AND YEAR(a.data) = '{$ano}'
{$guideScope}
GROUP BY g.id
");

while ($g = $resGlosa->fetch_assoc()) {
    $glosas[$g['guia_id']] = $g['total_glosa'] * $g['valor_sessao'];
}

$totalPrevisto = 0;
$totalGlosado = 0;
$totalRecebido = 0;
$planos = [];

while ($g = $guias->fetch_assoc()) {
    $valor = (float) $g['valor_guia'];
    $recebido = (float) $g['recebido'];
    $glosa = $glosas[$g['id']] ?? 0;
    $plano = $g['plano'] ?? 'Sem plano';

    $totalPrevisto += $valor;
    $totalGlosado += $glosa;
    $totalRecebido += $recebido;

    if (!isset($planos[$plano])) {
        $planos[$plano] = [
            'previsto' => 0,
            'glosado' => 0,
            'recebido' => 0,
        ];
    }

    $planos[$plano]['previsto'] += $valor;
    $planos[$plano]['glosado'] += $glosa;
    $planos[$plano]['recebido'] += $recebido;
}

$planRows = [];

foreach ($planos as $nomePlano => $totaisPlano) {
    $planRows[] = [
        'plano' => $nomePlano,
        'previsto' => $totaisPlano['previsto'],
        'glosado' => $totaisPlano['glosado'],
        'recebido' => $totaisPlano['recebido'],
        'receber' => $totaisPlano['previsto'] - $totaisPlano['glosado'] - $totaisPlano['recebido'],
    ];
}

$totalFaturado = $totalPrevisto - $totalGlosado;
$totalReceber = $totalFaturado - $totalRecebido;

echo json_encode([
    'total_previsto' => $totalPrevisto,
    'total_glosado' => $totalGlosado,
    'total_faturado' => $totalFaturado,
    'total_recebido' => $totalRecebido,
    'total_receber' => $totalReceber,
    'planos' => $planRows,
]);
