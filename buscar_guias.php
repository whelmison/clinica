<?php
include 'config/db.php';

$pacienteId = app_query_int('paciente_id');
$profissionalIdFiltro = app_query_int('profissional_id');
$ignorarAtendimentoId = app_query_int('ignorar_atendimento_id');
$incluirFinalizadas = app_request_query('incluir_finalizadas', '') === '1';

if ($pacienteId <= 0) {
    echo "<option value=''>Selecione um paciente</option>";
    exit;
}

$where = ['g.clinica_id = ?', 'g.paciente_id = ?', 'g.autorizada = 1', "COALESCE(g.status_operacional, 'aguardando_autorizacao') <> 'cancelada'"];
$whereTypes = 'ii';
$whereParams = [app_active_clinic_id(), $pacienteId];
$joinWhere = '';
$joinTypes = '';
$joinParams = [];

if ($ignorarAtendimentoId > 0) {
    $joinWhere = 'WHERE clinica_id = ? AND id <> ?';
    $joinTypes = 'ii';
    $joinParams[] = app_active_clinic_id();
    $joinParams[] = $ignorarAtendimentoId;
} else {
    $joinWhere = 'WHERE clinica_id = ?';
    $joinTypes = 'i';
    $joinParams[] = app_active_clinic_id();
}

if (app_is_professional_user()) {
    $professionalId = app_current_professional_id();

    if ($professionalId === null) {
        echo "<option value=''>Nenhuma guia disponivel</option>";
        exit;
    }

    $where[] = 'g.profissional_id = ?';
    $whereTypes .= 'i';
    $whereParams[] = $professionalId;
} elseif ($profissionalIdFiltro > 0) {
    $where[] = '(g.profissional_id = ? OR g.profissional_id IS NULL)';
    $whereTypes .= 'i';
    $whereParams[] = $profissionalIdFiltro;
}

$types = $joinTypes . $whereTypes;
$params = array_merge($joinParams, $whereParams);

$rows = app_stmt_all(
    $conn,
    'SELECT
        g.id,
        g.codigo,
        g.total_sessoes,
        g.status_operacional,
        g.autorizada,
        COALESCE(a.usadas, 0) AS usadas
     FROM guias g
     LEFT JOIN (
        SELECT guia_id, COUNT(*) AS usadas
        FROM atendimentos
        ' . $joinWhere . '
        GROUP BY guia_id
     ) a ON a.guia_id = g.id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY g.id DESC',
    $types,
    $params
);

if ($rows === []) {
    echo "<option value=''>Nenhuma guia encontrada</option>";
    exit;
}

$hasGuideOption = false;
echo "<option value=''>Selecione</option>";

foreach ($rows as $guide) {
    $statusData = app_guide_operational_status_data($guide);
    $remaining = max(0, (int) $guide['total_sessoes'] - (int) $guide['usadas']);

    if (($remaining <= 0 || $statusData['value'] === 'finalizada') && !$incluirFinalizadas) {
        continue;
    }

    $hasGuideOption = true;
    $code = trim((string) ($guide['codigo'] ?? ''));
    $suffix = $remaining > 0
        ? $remaining . ' ' . ($remaining === 1 ? 'sessao restante' : 'sessoes restantes')
        : 'finalizada';
    $label = ($code !== '' ? $code : 'Guia') . ' (' . $suffix . ')';
    $style = $remaining <= 1 ? " style='color:red'" : '';

    echo "<option value='" . (int) $guide['id'] . "'{$style}>" . app_h($label) . "</option>";
}

if (!$hasGuideOption) {
    echo "<option value=''>Nenhuma guia disponivel</option>";
}
