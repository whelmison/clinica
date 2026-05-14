<?php
include 'config/db.php';

app_install_schema($conn);

$clinicId = app_active_clinic_id();
$patientId = app_request_method() === 'POST' ? app_post_int('paciente_id') : app_query_int('paciente_id');
$activeTab = app_request_query('tab', 'avaliacao') ?? 'avaliacao';

if ($patientId <= 0) {
    app_flash('danger', 'Paciente nao informado.');
    app_redirect('pacientes.php');
}

$patient = app_stmt_one(
    $conn,
    'SELECT p.*
     FROM pacientes p
     WHERE p.clinica_id = ?
       AND p.id = ?
       ' . app_professional_scope_exists_for_patient('p.id') . '
     LIMIT 1',
    'ii',
    [$clinicId, $patientId]
);

if (!$patient) {
    app_flash('danger', 'Paciente nao encontrado ou sem permissao.');
    app_redirect('pacientes.php');
}

function patient_sheet_text(string $key): string
{
    return trim((string) app_request_post($key, ''));
}

function patient_sheet_date(string $key): ?string
{
    $value = trim((string) app_request_post($key, ''));

    if ($value === '') {
        return date('Y-m-d');
    }

    return app_parse_date_br($value);
}

function patient_sheet_summary(?string $value, int $limit): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $limit, '...');
    }

    return strlen($value) > $limit ? substr($value, 0, max(0, $limit - 3)) . '...' : $value;
}

function patient_evaluation_groups(): array
{
    return [
        'postura' => [
            'title' => 'Avaliacao da postura',
            'legacy' => 'avaliacao_postura',
            'fields' => [
                'postura_observacoes_gerais' => ['label' => 'Observacoes gerais', 'options' => ['Normal', 'Alterada', 'Outra']],
                'postura_cabeca' => ['label' => 'Alinhamento da cabeca', 'options' => ['Normal', 'Anteriorizada', 'Inclinada para um lado', 'Outro']],
                'postura_ombros' => ['label' => 'Alinhamento dos ombros', 'options' => ['Normal', 'Elevados', 'Rotacao interna', 'Rotacao externa', 'Outro']],
                'postura_coluna' => ['label' => 'Alinhamento da coluna vertebral', 'options' => ['Normal', 'Hiperlordose', 'Hipercifose', 'Escoliose', 'Outro']],
                'postura_pelve' => ['label' => 'Alinhamento da pelve', 'options' => ['Normal', 'Inclinacao anterior', 'Inclinacao posterior', 'Inclinacao lateral', 'Outro']],
                'postura_membros' => ['label' => 'Alinhamento dos membros superiores e inferiores', 'options' => ['Normal', 'Desvio', 'Outro']],
            ],
        ],
        'amplitude' => [
            'title' => 'Avaliacao da amplitude de movimento (AM)',
            'legacy' => 'amplitude_movimento',
            'fields' => [
                'amplitude_cervical' => ['label' => 'Cervical', 'options' => ['Normal', 'Restrita', 'Outra']],
                'amplitude_ombro' => ['label' => 'Ombro', 'options' => ['Normal', 'Restrita', 'Outra']],
                'amplitude_cotovelo' => ['label' => 'Cotovelo', 'options' => ['Normal', 'Restrita', 'Outra']],
                'amplitude_punho_mao' => ['label' => 'Punho e mao', 'options' => ['Normal', 'Restrita', 'Outra']],
                'amplitude_coluna' => ['label' => 'Coluna vertebral', 'options' => ['Normal', 'Restrita', 'Outra']],
                'amplitude_quadril' => ['label' => 'Quadril', 'options' => ['Normal', 'Restrita', 'Outra']],
                'amplitude_joelho' => ['label' => 'Joelho', 'options' => ['Normal', 'Restrita', 'Outra']],
                'amplitude_tornozelo_pe' => ['label' => 'Tornozelo e pe', 'options' => ['Normal', 'Restrita', 'Outra']],
            ],
        ],
        'forca' => [
            'title' => 'Avaliacao da forca muscular',
            'legacy' => 'forca_muscular',
            'fields' => [
                'forca_cervicais' => ['label' => 'Musculos cervicais', 'options' => ['Normal', 'Fraca', 'Outra']],
                'forca_ombro' => ['label' => 'Musculos do ombro', 'options' => ['Normal', 'Fraca', 'Outra']],
                'forca_cotovelo' => ['label' => 'Musculos do cotovelo', 'options' => ['Normal', 'Fraca', 'Outra']],
                'forca_punho_mao' => ['label' => 'Musculos do punho e mao', 'options' => ['Normal', 'Fraca', 'Outra']],
                'forca_coluna' => ['label' => 'Musculos da coluna vertebral', 'options' => ['Normal', 'Fraca', 'Outra']],
                'forca_quadril' => ['label' => 'Musculos do quadril', 'options' => ['Normal', 'Fraca', 'Outra']],
                'forca_joelho' => ['label' => 'Musculos do joelho', 'options' => ['Normal', 'Fraca', 'Outra']],
                'forca_tornozelo_pe' => ['label' => 'Musculos do tornozelo e pe', 'options' => ['Normal', 'Fraca', 'Outra']],
            ],
        ],
        'sensibilidade' => [
            'title' => 'Avaliacao da sensibilidade',
            'legacy' => 'sensibilidade',
            'fields' => [
                'sensibilidade_tatil' => ['label' => 'Sensibilidade tatil', 'options' => ['Normal', 'Alterada', 'Outra']],
                'sensibilidade_termica' => ['label' => 'Sensibilidade termica', 'options' => ['Normal', 'Alterada', 'Outra']],
                'sensibilidade_dolorosa' => ['label' => 'Sensibilidade dolorosa', 'options' => ['Normal', 'Alterada', 'Outra']],
            ],
        ],
        'equilibrio' => [
            'title' => 'Avaliacao do equilibrio e marcha',
            'legacy' => 'equilibrio_marcha',
            'fields' => [
                'equilibrio_estatico' => ['label' => 'Equilibrio estatico', 'options' => ['Normal', 'Alterado', 'Outra']],
                'equilibrio_dinamico' => ['label' => 'Equilibrio dinamico', 'options' => ['Normal', 'Alterado', 'Outra']],
                'marcha' => ['label' => 'Marcha', 'options' => ['Normal', 'Alterada', 'Outra']],
            ],
        ],
        'avds' => [
            'title' => 'Avaliacao das atividades de vida diaria (AVDs)',
            'legacy' => 'avds',
            'fields' => [
                'avd_higiene' => ['label' => 'Higiene pessoal', 'options' => ['Independente', 'Dependente', 'Assistencia parcial', 'Outra']],
                'avd_vestir' => ['label' => 'Vestir-se', 'options' => ['Independente', 'Dependente', 'Assistencia parcial', 'Outra']],
                'avd_alimentacao' => ['label' => 'Alimentacao', 'options' => ['Independente', 'Dependente', 'Assistencia parcial', 'Outra']],
                'avd_locomocao' => ['label' => 'Locomocao', 'options' => ['Independente', 'Dependente', 'Assistencia parcial', 'Outra']],
                'avd_outras' => ['label' => 'Outras atividades', 'free_text' => true, 'placeholder' => 'Descreva outras atividades'],
            ],
        ],
    ];
}

function patient_evaluation_detail_fields(): array
{
    $fields = [];

    foreach (patient_evaluation_groups() as $group) {
        foreach ($group['fields'] as $field => $meta) {
            $fields[$field] = $meta;
        }
    }

    return $fields;
}

function patient_evaluation_detail_field_names(): array
{
    return array_keys(patient_evaluation_detail_fields());
}

function patient_sheet_exam_value(string $field, array $meta): string
{
    if (!empty($meta['free_text'])) {
        return patient_sheet_text($field);
    }

    $value = patient_sheet_text($field);

    if ($value === '') {
        return '';
    }

    $options = $meta['options'] ?? [];

    if ($options !== [] && !in_array($value, $options, true)) {
        return '';
    }

    if (in_array($value, ['Outra', 'Outro'], true)) {
        $other = patient_sheet_text($field . '_outro');

        return $other !== '' ? $value . ': ' . $other : $value;
    }

    return $value;
}

function patient_sheet_group_summary(array $group): string
{
    $lines = [];

    foreach ($group['fields'] as $field => $meta) {
        $value = patient_sheet_exam_value($field, $meta);

        if ($value !== '') {
            $lines[] = $meta['label'] . ': ' . $value;
        }
    }

    return implode("\n", $lines);
}

function patient_print_value(?string $value): string
{
    $value = trim((string) $value);

    return $value !== '' ? app_h($value) : '<span class="print-muted">Nao informado</span>';
}

function patient_sheet_has_other_option(array $meta): bool
{
    return array_intersect(($meta['options'] ?? []), ['Outra', 'Outro']) !== [];
}

function patient_sheet_other_option(array $meta): string
{
    foreach (($meta['options'] ?? []) as $option) {
        if (in_array($option, ['Outra', 'Outro'], true)) {
            return (string) $option;
        }
    }

    return '';
}

function patient_sheet_saved_choice(?string $value, array $meta): string
{
    $value = trim((string) $value);

    if ($value === '' || !empty($meta['free_text'])) {
        return '';
    }

    foreach (($meta['options'] ?? []) as $option) {
        $option = (string) $option;

        if ($value === $option || (in_array($option, ['Outra', 'Outro'], true) && str_starts_with($value, $option . ':'))) {
            return $option;
        }
    }

    return patient_sheet_has_other_option($meta) ? patient_sheet_other_option($meta) : '';
}

function patient_sheet_saved_other_text(?string $value, array $meta): string
{
    $value = trim((string) $value);
    $choice = patient_sheet_saved_choice($value, $meta);

    if ($value === '' || !in_array($choice, ['Outra', 'Outro'], true)) {
        return '';
    }

    $prefix = $choice . ':';

    if (str_starts_with($value, $prefix)) {
        return trim(substr($value, strlen($prefix)));
    }

    return $value !== $choice ? $value : '';
}

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'delete_avaliacao') {
        $evaluationId = app_post_int('avaliacao_id');
        $ok = $evaluationId > 0 && app_stmt_execute(
            $conn,
            'DELETE FROM paciente_fichas_avaliacao WHERE clinica_id = ? AND paciente_id = ? AND id = ?',
            'iii',
            [$clinicId, $patientId, $evaluationId]
        );

        app_flash($ok ? 'success' : 'danger', $ok ? 'Ficha de avaliacao excluida.' : 'Nao foi possivel excluir a ficha de avaliacao.');
        app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao']));
    }

    if ($action === 'save_avaliacao') {
        $dataAvaliacao = patient_sheet_date('data_avaliacao');

        if ($dataAvaliacao === null) {
            app_flash('danger', 'Informe a data da avaliacao no formato dd/mm/aaaa.');
            app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao']));
        }

        $profissionalId = app_post_int('profissional_id') ?: null;
        $evaluationId = app_post_int('avaliacao_id');
        $detailFieldMetas = patient_evaluation_detail_fields();
        $detailFields = array_keys($detailFieldMetas);
        $legacySummaries = [];

        foreach (patient_evaluation_groups() as $group) {
            $legacySummaries[$group['legacy']] = patient_sheet_group_summary($group);
        }

        $columns = array_merge(
            [
                'clinica_id',
                'paciente_id',
                'profissional_id',
                'data_avaliacao',
                'sexo',
                'queixa_principal',
                'historia_pregressa',
                'historia_atual',
                'lesoes_previas',
                'historia_cirurgica',
                'avaliacao_postura',
                'amplitude_movimento',
                'forca_muscular',
                'sensibilidade',
                'equilibrio_marcha',
                'avds',
                'objetivos',
                'condutas',
                'observacoes_finais',
            ],
            $detailFields
        );
        $values = [
            $clinicId,
            $patientId,
            $profissionalId,
            $dataAvaliacao,
            patient_sheet_text('sexo'),
            patient_sheet_text('queixa_principal'),
            patient_sheet_text('historia_pregressa'),
            patient_sheet_text('historia_atual'),
            patient_sheet_text('lesoes_previas'),
            patient_sheet_text('historia_cirurgica'),
            $legacySummaries['avaliacao_postura'] ?? '',
            $legacySummaries['amplitude_movimento'] ?? '',
            $legacySummaries['forca_muscular'] ?? '',
            $legacySummaries['sensibilidade'] ?? '',
            $legacySummaries['equilibrio_marcha'] ?? '',
            $legacySummaries['avds'] ?? '',
            patient_sheet_text('objetivos'),
            patient_sheet_text('condutas'),
            patient_sheet_text('observacoes_finais'),
        ];

        foreach ($detailFields as $field) {
            $values[] = patient_sheet_exam_value($field, $detailFieldMetas[$field] ?? []);
        }

        if ($evaluationId > 0) {
            $existingEvaluation = app_stmt_one(
                $conn,
                'SELECT id FROM paciente_fichas_avaliacao WHERE clinica_id = ? AND paciente_id = ? AND id = ? LIMIT 1',
                'iii',
                [$clinicId, $patientId, $evaluationId]
            );

            if (!$existingEvaluation) {
                app_flash('danger', 'Ficha de avaliacao nao encontrada para edicao.');
                app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao']));
            }

            $updateColumns = array_slice($columns, 2);
            $updateValues = array_slice($values, 2);
            $setSql = implode(', ', array_map(static fn (string $column): string => $column . ' = ?', $updateColumns));
            $ok = app_stmt_execute(
                $conn,
                'UPDATE paciente_fichas_avaliacao SET ' . $setSql . ' WHERE clinica_id = ? AND paciente_id = ? AND id = ?',
                'i' . str_repeat('s', count($updateValues) - 1) . 'iii',
                array_merge($updateValues, [$clinicId, $patientId, $evaluationId])
            );
        } else {
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $ok = app_stmt_execute(
                $conn,
                'INSERT INTO paciente_fichas_avaliacao (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')',
                'iii' . str_repeat('s', count($values) - 3),
                $values
            );
        }

        app_flash($ok ? 'success' : 'danger', $ok ? ($evaluationId > 0 ? 'Ficha de avaliacao atualizada.' : 'Ficha de avaliacao salva.') : 'Nao foi possivel salvar a ficha de avaliacao.');
        app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao']));
    }

    if ($action === 'save_evolucao') {
        $dataEvolucao = patient_sheet_date('data_evolucao');

        if ($dataEvolucao === null) {
            app_flash('danger', 'Informe a data da evolucao no formato dd/mm/aaaa.');
            app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'evolucao']));
        }

        $atendimentoId = app_post_int('atendimento_id') ?: null;
        $profissionalId = app_post_int('profissional_id') ?: null;
        $condutas = patient_sheet_text('condutas_observacoes');

        if ($condutas === '') {
            app_flash('danger', 'Informe as condutas realizadas e observacoes.');
            app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'evolucao']));
        }

        $ok = app_stmt_execute(
            $conn,
            'INSERT INTO paciente_fichas_evolucao (
                clinica_id,
                paciente_id,
                atendimento_id,
                profissional_id,
                data_evolucao,
                condutas_observacoes
            ) VALUES (?, ?, ?, ?, ?, ?)',
            'iiiiss',
            [
                $clinicId,
                $patientId,
                $atendimentoId,
                $profissionalId,
                $dataEvolucao,
                $condutas,
            ]
        );

        app_flash($ok ? 'success' : 'danger', $ok ? 'Evolucao diaria salva.' : 'Nao foi possivel salvar a evolucao diaria.');
        app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'evolucao']));
    }
}

$professionals = app_fetch_profissionais($conn);
$currentProfessionalId = app_current_professional_id();

if ($currentProfessionalId !== null && app_is_professional_user()) {
    $professionals = array_values(array_filter($professionals, static fn (array $row): bool => (int) $row['id'] === $currentProfessionalId));
}

$attendanceOptions = app_stmt_all(
    $conn,
    'SELECT a.id,
            a.data,
            COALESCE(pr.nome, pr_ag.nome, "Nao informado") AS profissional_nome,
            COALESCE(s.nome, pl.nome, a.tipo, "Atendimento") AS servico_nome
     FROM atendimentos a
     LEFT JOIN guias g ON g.id = a.guia_id AND g.clinica_id = a.clinica_id
     LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
     LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
     LEFT JOIN agenda ag ON ag.id = a.agenda_id AND ag.clinica_id = a.clinica_id
     LEFT JOIN profissionais pr_ag ON pr_ag.id = ag.profissional_id AND pr_ag.clinica_id = ag.clinica_id
     LEFT JOIN servicos s ON s.id = ag.servico_id AND s.clinica_id = ag.clinica_id
     WHERE a.clinica_id = ?
       AND a.paciente_id = ?
     ORDER BY a.data DESC, a.id DESC
     LIMIT 60',
    'ii',
    [$clinicId, $patientId]
);

$evaluations = app_stmt_all(
    $conn,
    'SELECT fa.*, pr.nome AS profissional_nome
     FROM paciente_fichas_avaliacao fa
     LEFT JOIN profissionais pr ON pr.id = fa.profissional_id AND pr.clinica_id = fa.clinica_id
     WHERE fa.clinica_id = ?
       AND fa.paciente_id = ?
     ORDER BY fa.data_avaliacao DESC, fa.id DESC',
    'ii',
    [$clinicId, $patientId]
);

$editEvaluationId = app_query_int('avaliacao_id');
$editingEvaluation = null;

if ($editEvaluationId > 0) {
    foreach ($evaluations as $evaluation) {
        if ((int) ($evaluation['id'] ?? 0) === $editEvaluationId) {
            $editingEvaluation = $evaluation;
            break;
        }
    }

    if ($editingEvaluation === null) {
        app_flash('danger', 'Ficha de avaliacao nao encontrada.');
        app_redirect('paciente_fichas.php?' . app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao']));
    }
}

$evaluationFormValues = [
    'id' => (int) ($editingEvaluation['id'] ?? 0),
    'data_avaliacao' => !empty($editingEvaluation['data_avaliacao']) ? app_date_br((string) $editingEvaluation['data_avaliacao']) : date('d/m/Y'),
    'profissional_id' => (int) ($editingEvaluation['profissional_id'] ?? ($currentProfessionalId ?? 0)),
    'sexo' => (string) ($editingEvaluation['sexo'] ?? ''),
    'queixa_principal' => (string) ($editingEvaluation['queixa_principal'] ?? ''),
    'historia_pregressa' => (string) ($editingEvaluation['historia_pregressa'] ?? ''),
    'historia_atual' => (string) ($editingEvaluation['historia_atual'] ?? ''),
    'lesoes_previas' => (string) ($editingEvaluation['lesoes_previas'] ?? ''),
    'historia_cirurgica' => (string) ($editingEvaluation['historia_cirurgica'] ?? ''),
    'objetivos' => (string) ($editingEvaluation['objetivos'] ?? ''),
    'condutas' => (string) ($editingEvaluation['condutas'] ?? ''),
    'observacoes_finais' => (string) ($editingEvaluation['observacoes_finais'] ?? ''),
];

foreach (patient_evaluation_detail_field_names() as $field) {
    $evaluationFormValues[$field] = (string) ($editingEvaluation[$field] ?? '');
}

$evolutions = app_stmt_all(
    $conn,
    'SELECT fe.*, pr.nome AS profissional_nome
     FROM paciente_fichas_evolucao fe
     LEFT JOIN profissionais pr ON pr.id = fe.profissional_id AND pr.clinica_id = fe.clinica_id
     WHERE fe.clinica_id = ?
       AND fe.paciente_id = ?
     ORDER BY fe.data_evolucao DESC, fe.id DESC',
    'ii',
    [$clinicId, $patientId]
);

$activeTab = in_array($activeTab, ['avaliacao', 'evolucao'], true) ? $activeTab : 'avaliacao';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Fichas do paciente</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
.patient-sheets-shell {
    padding: 0.7rem 0.9rem 1.2rem;
}

.patient-sheets-shell .page-hero {
    padding: 0.88rem 1rem;
    border-radius: 8px;
    background: #0f5c4a;
}

.sheet-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbfd 100%);
    box-shadow: 0 20px 44px rgba(24, 56, 69, 0.1);
    overflow: hidden;
}

.sheet-card .card-header {
    padding: 0.68rem 0.82rem;
    background: #ffffff;
    border-bottom: 1px solid rgba(18, 73, 88, 0.08);
}

.sheet-card .card-body {
    padding: 0.82rem;
}

.sheet-form .form-control,
.sheet-form .form-select {
    min-height: 38px;
    border-radius: 12px;
    border-color: #d7e3e7;
    font-size: 0.82rem;
    background: #ffffff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
}

.sheet-form textarea.form-control {
    min-height: 82px;
}

.sheet-form .btn,
.patient-sheets-shell .page-hero .btn {
    border-radius: 8px;
    font-size: 0.76rem;
    font-weight: 700;
}

.sheet-card > .card-header > .nav .nav-link {
    min-height: 38px;
    border-radius: 6px;
    color: #315a68;
    font-size: 0.78rem;
    font-weight: 900;
    background: #eef7f3;
}

.sheet-card > .card-header > .nav .nav-link.active {
    background: #0f5c4a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 92, 74, 0.22);
}

.sheet-inner-tabs {
    grid-column: 1 / -1;
    display: grid;
    gap: 0.42rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 0.3rem;
    padding: 0.42rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: #ffffff;
    box-shadow: 0 12px 26px rgba(24, 56, 69, 0.07);
}

.sheet-inner-tabs .nav-link {
    width: 100%;
    min-height: 42px;
    border: 1px solid transparent;
    border-radius: 6px;
    color: #4f6c78;
    font-size: 0.74rem;
    font-weight: 900;
    background: #eef7f3;
}

.sheet-inner-tabs .nav-link.active {
    border-color: #0f5c4a;
    background: #0f5c4a;
    color: #ffffff;
    box-shadow: 0 8px 18px rgba(15, 92, 74, 0.2);
}

.sheet-inner-content {
    grid-column: 1 / -1;
    width: 100%;
}

.sheet-pane {
    padding: 0.75rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 4px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}

.sheet-block-title {
    display: block;
    width: 100%;
    margin: 0;
    padding: 0;
    border-bottom: 0;
    color: #0f5c4a;
    font-size: 0.68rem;
    font-weight: 800;
    text-align: left;
}

.sheet-field-grid {
    row-gap: 0.55rem;
}

.sheet-choice-field {
    display: grid;
    gap: 0.35rem;
}

.sheet-choice-options {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.sheet-choice-options .btn {
    min-height: 32px;
    border-color: #cddfe5;
    border-radius: 6px;
    color: #315a68;
    font-size: 0.72rem;
    font-weight: 800;
    background: #ffffff;
}

.sheet-choice-options .btn-check:checked + .btn {
    border-color: #0f5c4a;
    color: #ffffff;
    background: #0f5c4a;
    box-shadow: 0 7px 14px rgba(15, 92, 74, 0.18);
}

.sheet-choice-other {
    min-height: 34px !important;
}

.sheet-choice-other[hidden] {
    display: none !important;
}

.sheet-print-template {
    display: none;
}

.print-sheet {
    color: #111827;
    font-family: Arial, sans-serif;
    font-size: 12px;
}

.print-sheet h1 {
    font-size: 18px;
    margin: 0 0 12px;
    text-align: center;
}

.print-sheet h2 {
    border-bottom: 1px solid #111827;
    font-size: 13px;
    margin: 14px 0 8px;
    padding-bottom: 3px;
}

.print-sheet .print-grid {
    display: grid;
    gap: 6px 14px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.print-sheet .print-field {
    border-bottom: 1px solid #d1d5db;
    min-height: 22px;
    padding: 3px 0;
}

.print-sheet .print-field.full {
    grid-column: 1 / -1;
}

.print-sheet .print-label {
    font-weight: 700;
}

.print-muted {
    color: #6b7280;
}

.sheet-list {
    display: grid;
    gap: 0.55rem;
}

.sheet-list-item {
    padding: 0.62rem 0.72rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 5px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}

.sheet-list-item small {
    color: #68828f;
}

.sheet-list-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.35rem;
}

.sheet-list-actions form {
    display: inline-flex;
}

.sheet-list-details {
    border-top: 1px solid rgba(18, 73, 88, 0.08);
    color: #315a68;
    font-size: 0.78rem;
    padding-top: 0.45rem;
}

.sheet-list-details summary {
    color: #0f5c4a;
    cursor: pointer;
    font-weight: 800;
}

.sheet-detail-grid {
    display: grid;
    gap: 0.35rem 0.7rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.sheet-detail-grid .full {
    grid-column: 1 / -1;
}

.sheet-detail-section {
    margin-top: 0.55rem;
    color: #0f5c4a;
    font-size: 0.68rem;
    font-weight: 900;
    text-transform: uppercase;
}

.sheet-side-panel {
    padding: 0.75rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 8px;
    background: #f3faf7;
}

#evolucao .sheet-form {
    padding: 0.75rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-left: 4px solid #0f5c4a;
    border-radius: 8px;
    background: #ffffff;
}

@media (max-width: 900px) {
    .sheet-inner-tabs {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container-fluid patient-sheets-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <p class="mb-1 small text-white-50 text-uppercase fw-bold">Fisioterapia</p>
                <h3 class="mb-1">Fichas de <?= app_h((string) $patient['nome']) ?></h3>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-light btn-sm px-3" href="paciente_historico.php?paciente_id=<?= (int) $patientId ?>">Historico</a>
                <a class="btn btn-outline-light btn-sm px-3" href="pacientes.php?<?= app_h(app_build_query(['paciente' => (string) $patient['nome'], 'filtrar' => 1, 'patient_id' => $patientId])) ?>">Editar paciente</a>
                <a class="btn btn-outline-light btn-sm px-3" href="pacientes.php">Voltar</a>
            </div>
        </div>
    </section>

    <div class="sheet-card mt-3">
        <div class="card-header">
            <ul class="nav nav-pills gap-2" id="sheetTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'avaliacao' ? 'active' : '' ?>" id="avaliacao-tab" data-bs-toggle="pill" data-bs-target="#avaliacao" type="button" role="tab">Avaliacao</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link <?= $activeTab === 'evolucao' ? 'active' : '' ?>" id="evolucao-tab" data-bs-toggle="pill" data-bs-target="#evolucao" type="button" role="tab">Evolucao diaria</button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <div class="tab-pane fade <?= $activeTab === 'avaliacao' ? 'show active' : '' ?>" id="avaliacao" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-xl-7">
                            <form method="POST" class="row g-2 sheet-form">
                                <input type="hidden" name="action" value="save_avaliacao">
                                <input type="hidden" name="paciente_id" value="<?= (int) $patientId ?>">
                                <input type="hidden" name="avaliacao_id" value="<?= (int) $evaluationFormValues['id'] ?>">
                                <?php if ((int) $evaluationFormValues['id'] > 0): ?>
                                    <div class="col-12">
                                        <div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 py-2 mb-1">
                                            <span>Editando avaliacao de <?= app_h($evaluationFormValues['data_avaliacao']) ?>.</span>
                                            <a class="btn btn-sm btn-outline-primary" href="paciente_fichas.php?<?= app_h(app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao'])) ?>">Nova avaliacao</a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Data da avaliacao</label>
                                    <input type="text" name="data_avaliacao" class="form-control" data-mask-date value="<?= app_h((string) $evaluationFormValues['data_avaliacao']) ?>" title="Data da avaliacao no formato dd/mm/aaaa.">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Profissional</label>
                                    <select name="profissional_id" class="form-select" title="Fisioterapeuta responsavel pela avaliacao.">
                                        <option value="">Nao informado</option>
                                        <?php foreach ($professionals as $professional): ?>
                                            <option value="<?= (int) $professional['id'] ?>" <?= (int) $professional['id'] === (int) $evaluationFormValues['profissional_id'] ? 'selected' : '' ?>><?= app_h((string) $professional['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Sexo</label>
                                    <input type="text" name="sexo" class="form-control" value="<?= app_h((string) $evaluationFormValues['sexo']) ?>" title="Sexo informado na ficha de avaliacao.">
                                </div>

                                <ul class="nav nav-pills sheet-inner-tabs" id="evaluationInnerTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="evalHistoryTab" data-bs-toggle="pill" data-bs-target="#evalHistoryPane" type="button" role="tab">Historico</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="evalExamTab" data-bs-toggle="pill" data-bs-target="#evalExamPane" type="button" role="tab">Exame fisico</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="evalPlanTab" data-bs-toggle="pill" data-bs-target="#evalPlanPane" type="button" role="tab">Plano</button>
                                    </li>
                                </ul>
                                <div class="sheet-inner-content tab-content">
                                    <div class="tab-pane fade show active sheet-pane" id="evalHistoryPane" role="tabpanel" aria-labelledby="evalHistoryTab">
                                        <div class="row g-2">
                                <div class="col-12 sheet-block-title">Historico</div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Queixa principal</label>
                                    <textarea name="queixa_principal" class="form-control" title="Motivo principal relatado pelo paciente."><?= app_h((string) $evaluationFormValues['queixa_principal']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Historia pregressa</label>
                                    <textarea name="historia_pregressa" class="form-control" title="Historico anterior relevante para o tratamento."><?= app_h((string) $evaluationFormValues['historia_pregressa']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Historia atual</label>
                                    <textarea name="historia_atual" class="form-control" title="Evolucao atual da queixa e sintomas."><?= app_h((string) $evaluationFormValues['historia_atual']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Lesoes previas</label>
                                    <textarea name="lesoes_previas" class="form-control" title="Lesoes anteriores relatadas pelo paciente."><?= app_h((string) $evaluationFormValues['lesoes_previas']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Historia cirurgica</label>
                                    <textarea name="historia_cirurgica" class="form-control" title="Cirurgias anteriores relevantes."><?= app_h((string) $evaluationFormValues['historia_cirurgica']) ?></textarea>
                                </div>

                                        </div>
                                    </div>
                                    <div class="tab-pane fade sheet-pane" id="evalExamPane" role="tabpanel" aria-labelledby="evalExamTab">
                                        <div class="row g-2 sheet-field-grid">
                                <div class="col-12 sheet-block-title">Exame fisico</div>
                                <?php foreach (patient_evaluation_groups() as $group): ?>
                                    <div class="col-12 sheet-block-title mt-2"><?= app_h($group['title']) ?></div>
                                    <?php foreach ($group['fields'] as $field => $meta): ?>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted"><?= app_h($meta['label']) ?></label>
                                            <?php if (!empty($meta['free_text'])): ?>
                                                <input type="text" name="<?= app_h($field) ?>" class="form-control" value="<?= app_h((string) ($evaluationFormValues[$field] ?? '')) ?>" placeholder="<?= app_h((string) ($meta['placeholder'] ?? '')) ?>">
                                            <?php else: ?>
                                                <?php
                                                $savedChoice = patient_sheet_saved_choice((string) ($evaluationFormValues[$field] ?? ''), $meta);
                                                $savedOther = patient_sheet_saved_other_text((string) ($evaluationFormValues[$field] ?? ''), $meta);
                                                $showOther = in_array($savedChoice, ['Outra', 'Outro'], true);
                                                ?>
                                                <div class="sheet-choice-field">
                                                    <div class="sheet-choice-options" role="group" aria-label="<?= app_h($meta['label']) ?>">
                                                        <?php foreach (($meta['options'] ?? []) as $optionIndex => $option): ?>
                                                            <?php $choiceId = 'exam-' . $field . '-' . (int) $optionIndex; ?>
                                                            <input type="radio" class="btn-check" name="<?= app_h($field) ?>" id="<?= app_h($choiceId) ?>" value="<?= app_h((string) $option) ?>" <?= $savedChoice === (string) $option ? 'checked' : '' ?>>
                                                            <label class="btn btn-outline-secondary btn-sm" for="<?= app_h($choiceId) ?>"><?= app_h((string) $option) ?></label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php if (array_intersect(($meta['options'] ?? []), ['Outra', 'Outro']) !== []): ?>
                                                        <input type="text" name="<?= app_h($field . '_outro') ?>" class="form-control sheet-choice-other" data-other-for="<?= app_h($field) ?>" value="<?= app_h($savedOther) ?>" placeholder="Descreva se marcar Outra/Outro" <?= $showOther ? '' : 'hidden disabled' ?>>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>

                                        </div>
                                    </div>
                                    <div class="tab-pane fade sheet-pane" id="evalPlanPane" role="tabpanel" aria-labelledby="evalPlanTab">
                                        <div class="row g-2">
                                <div class="col-12 sheet-block-title">Plano terapeutico</div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Objetivos</label>
                                    <textarea name="objetivos" class="form-control" title="Objetivos terapeuticos definidos para o paciente."><?= app_h((string) $evaluationFormValues['objetivos']) ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Condutas</label>
                                    <textarea name="condutas" class="form-control" title="Condutas planejadas para o tratamento."><?= app_h((string) $evaluationFormValues['condutas']) ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Observacoes finais</label>
                                    <textarea name="observacoes_finais" class="form-control" title="Observacoes e consideracoes finais da avaliacao."><?= app_h((string) $evaluationFormValues['observacoes_finais']) ?></textarea>
                                </div>
                                <div class="col-12 d-flex align-items-end justify-content-end">
                                    <button class="btn btn-primary px-4"><?= (int) $evaluationFormValues['id'] > 0 ? 'Atualizar avaliacao' : 'Salvar avaliacao' ?></button>
                                </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-5">
                            <div class="sheet-side-panel">
                            <h6 class="mb-2">Avaliacoes salvas</h6>
                            <div class="sheet-list">
                                <?php foreach ($evaluations as $item): ?>
                                    <?php $printId = 'print-avaliacao-' . (int) $item['id']; ?>
                                    <div class="sheet-list-item">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <strong><?= app_h(app_date_br((string) $item['data_avaliacao'])) ?></strong>
                                                <small class="d-block"><?= app_h((string) ($item['profissional_nome'] ?: 'Profissional nao informado')) ?></small>
                                            </div>
                                            <div class="sheet-list-actions">
                                                <a class="btn btn-sm btn-outline-primary" href="paciente_fichas.php?<?= app_h(app_build_query(['paciente_id' => $patientId, 'tab' => 'avaliacao', 'avaliacao_id' => (int) $item['id']])) ?>">Editar</a>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" data-print-sheet="#<?= app_h($printId) ?>">Imprimir</button>
                                                <form method="POST" onsubmit="return confirm('Excluir esta avaliacao?')">
                                                    <input type="hidden" name="action" value="delete_avaliacao">
                                                    <input type="hidden" name="paciente_id" value="<?= (int) $patientId ?>">
                                                    <input type="hidden" name="avaliacao_id" value="<?= (int) $item['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="small mt-1"><?= app_h(patient_sheet_summary((string) ($item['queixa_principal'] ?: $item['observacoes_finais'] ?: 'Sem resumo'), 170)) ?></div>
                                        <details class="sheet-list-details mt-2">
                                            <summary>Ver detalhes</summary>
                                            <div class="sheet-detail-grid mt-2">
                                                <div><strong>Queixa:</strong> <?= patient_print_value((string) ($item['queixa_principal'] ?? '')) ?></div>
                                                <div><strong>Historia atual:</strong> <?= patient_print_value((string) ($item['historia_atual'] ?? '')) ?></div>
                                                <div><strong>Objetivos:</strong> <?= patient_print_value((string) ($item['objetivos'] ?? '')) ?></div>
                                                <div><strong>Condutas:</strong> <?= patient_print_value((string) ($item['condutas'] ?? '')) ?></div>
                                                <div class="full"><strong>Observacoes finais:</strong> <?= patient_print_value((string) ($item['observacoes_finais'] ?? '')) ?></div>
                                            </div>
                                            <?php foreach (patient_evaluation_groups() as $group): ?>
                                                <div class="sheet-detail-section"><?= app_h($group['title']) ?></div>
                                                <div class="sheet-detail-grid">
                                                    <?php foreach ($group['fields'] as $field => $meta): ?>
                                                        <div><strong><?= app_h($meta['label']) ?>:</strong> <?= patient_print_value((string) ($item[$field] ?? '')) ?></div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </details>
                                        <div class="sheet-print-template" id="<?= app_h($printId) ?>">
                                            <article class="print-sheet">
                                                <h1>FICHA DE AVALIACAO FISIOTERAPEUTICA DETALHADA</h1>
                                                <h2>Dados do paciente</h2>
                                                <div class="print-grid">
                                                    <div class="print-field"><span class="print-label">Nome:</span> <?= patient_print_value((string) $patient['nome']) ?></div>
                                                    <div class="print-field"><span class="print-label">Data de nascimento:</span> <?= patient_print_value(!empty($patient['data_nascimento']) ? app_date_br((string) $patient['data_nascimento']) : '') ?></div>
                                                    <div class="print-field"><span class="print-label">Sexo:</span> <?= patient_print_value((string) ($item['sexo'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Data da avaliacao:</span> <?= patient_print_value(app_date_br((string) $item['data_avaliacao'])) ?></div>
                                                    <div class="print-field"><span class="print-label">CPF/CNPJ:</span> <?= patient_print_value((string) ($patient['cpf'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Profissional:</span> <?= patient_print_value((string) ($item['profissional_nome'] ?? '')) ?></div>
                                                </div>
                                                <h2>Historico</h2>
                                                <div class="print-grid">
                                                    <div class="print-field full"><span class="print-label">Queixa principal:</span> <?= patient_print_value((string) ($item['queixa_principal'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Historia pregressa:</span> <?= patient_print_value((string) ($item['historia_pregressa'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Historia atual:</span> <?= patient_print_value((string) ($item['historia_atual'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Lesoes previas:</span> <?= patient_print_value((string) ($item['lesoes_previas'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Historia cirurgica:</span> <?= patient_print_value((string) ($item['historia_cirurgica'] ?? '')) ?></div>
                                                </div>
                                                <h2>Exame fisico</h2>
                                                <?php foreach (patient_evaluation_groups() as $group): ?>
                                                    <h2><?= app_h($group['title']) ?></h2>
                                                    <div class="print-grid">
                                                        <?php $hasGroupDetail = false; ?>
                                                        <?php foreach ($group['fields'] as $field => $meta): ?>
                                                            <?php $hasGroupDetail = $hasGroupDetail || trim((string) ($item[$field] ?? '')) !== ''; ?>
                                                            <div class="print-field"><span class="print-label"><?= app_h($meta['label']) ?>:</span> <?= patient_print_value((string) ($item[$field] ?? '')) ?></div>
                                                        <?php endforeach; ?>
                                                        <?php if (!$hasGroupDetail && trim((string) ($item[$group['legacy']] ?? '')) !== ''): ?>
                                                            <div class="print-field full"><span class="print-label">Resumo anterior:</span> <?= nl2br(app_h((string) $item[$group['legacy']])) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                <h2>Objetivos, condutas e observacoes</h2>
                                                <div class="print-grid">
                                                    <div class="print-field"><span class="print-label">Objetivos:</span> <?= patient_print_value((string) ($item['objetivos'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Condutas:</span> <?= patient_print_value((string) ($item['condutas'] ?? '')) ?></div>
                                                    <div class="print-field full"><span class="print-label">Observacoes finais:</span> <?= patient_print_value((string) ($item['observacoes_finais'] ?? '')) ?></div>
                                                </div>
                                            </article>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($evaluations === []): ?>
                                    <div class="text-muted small">Nenhuma avaliacao cadastrada para este paciente.</div>
                                <?php endif; ?>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade <?= $activeTab === 'evolucao' ? 'show active' : '' ?>" id="evolucao" role="tabpanel">
                    <div class="row g-3">
                        <div class="col-xl-6">
                            <form method="POST" class="row g-2 sheet-form">
                                <input type="hidden" name="action" value="save_evolucao">
                                <input type="hidden" name="paciente_id" value="<?= (int) $patientId ?>">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Data</label>
                                    <input type="text" name="data_evolucao" class="form-control" data-mask-date value="<?= app_h(date('d/m/Y')) ?>" title="Data da evolucao no formato dd/mm/aaaa.">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted">Atendimento vinculado</label>
                                    <select name="atendimento_id" class="form-select" title="Opcional. Vincule esta evolucao a um atendimento ja realizado.">
                                        <option value="">Sem vinculo direto</option>
                                        <?php foreach ($attendanceOptions as $attendance): ?>
                                            <option value="<?= (int) $attendance['id'] ?>">
                                                <?= app_h(app_date_br((string) $attendance['data']) . ' - ' . (string) $attendance['servico_nome'] . ' - ' . (string) $attendance['profissional_nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted">Profissional</label>
                                    <select name="profissional_id" class="form-select" title="Fisioterapeuta responsavel por esta evolucao.">
                                        <option value="">Nao informado</option>
                                        <?php foreach ($professionals as $professional): ?>
                                            <option value="<?= (int) $professional['id'] ?>" <?= $currentProfessionalId !== null && (int) $professional['id'] === $currentProfessionalId ? 'selected' : '' ?>><?= app_h((string) $professional['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted">Condutas realizadas e observacoes</label>
                                    <textarea name="condutas_observacoes" class="form-control" rows="6" required title="Registre as condutas realizadas, evolucao do paciente e observacoes do dia."></textarea>
                                </div>
                                <div class="col-12 d-flex align-items-end justify-content-end">
                                    <button class="btn btn-primary px-4">Salvar evolucao</button>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-6">
                            <div class="sheet-side-panel">
                            <h6 class="mb-2">Evolucoes salvas</h6>
                            <div class="sheet-list">
                                <?php foreach ($evolutions as $item): ?>
                                    <?php $printId = 'print-evolucao-' . (int) $item['id']; ?>
                                    <div class="sheet-list-item">
                                        <div class="d-flex justify-content-between align-items-start gap-2">
                                            <div>
                                                <strong><?= app_h(app_date_br((string) $item['data_evolucao'])) ?></strong>
                                                <small class="d-block"><?= app_h((string) ($item['profissional_nome'] ?: 'Profissional nao informado')) ?></small>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-print-sheet="#<?= app_h($printId) ?>">Imprimir</button>
                                        </div>
                                        <div class="small mt-1"><?= app_h(patient_sheet_summary((string) ($item['condutas_observacoes'] ?: 'Sem resumo'), 220)) ?></div>
                                        <div class="sheet-print-template" id="<?= app_h($printId) ?>">
                                            <article class="print-sheet">
                                                <h1>FICHA DE EVOLUCAO DIARIA DE ATENDIMENTO FISIOTERAPEUTICO</h1>
                                                <h2>Dados do paciente</h2>
                                                <div class="print-grid">
                                                    <div class="print-field"><span class="print-label">Nome do paciente:</span> <?= patient_print_value((string) $patient['nome']) ?></div>
                                                    <div class="print-field"><span class="print-label">Data:</span> <?= patient_print_value(app_date_br((string) $item['data_evolucao'])) ?></div>
                                                    <div class="print-field"><span class="print-label">Profissional:</span> <?= patient_print_value((string) ($item['profissional_nome'] ?? '')) ?></div>
                                                    <div class="print-field"><span class="print-label">Atendimento vinculado:</span> <?= patient_print_value(!empty($item['atendimento_id']) ? '#' . (string) $item['atendimento_id'] : '') ?></div>
                                                </div>
                                                <h2>Condutas realizadas e observacoes</h2>
                                                <div class="print-grid">
                                                    <div class="print-field full"><?= nl2br(app_h((string) ($item['condutas_observacoes'] ?? ''))) ?></div>
                                                </div>
                                            </article>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($evolutions === []): ?>
                                    <div class="text-muted small">Nenhuma evolucao cadastrada para este paciente.</div>
                                <?php endif; ?>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function sheetDigits(value) {
    return String(value || '').replace(/\D+/g, '');
}

document.querySelectorAll('[data-mask-date]').forEach((input) => {
    input.addEventListener('input', () => {
        const digits = sheetDigits(input.value).slice(0, 8);
        input.value = digits
            .replace(/^(\d{2})(\d)/, '$1/$2')
            .replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
    });
});

document.querySelectorAll('[data-other-for]').forEach((input) => {
    const fieldName = input.dataset.otherFor || '';
    const radios = Array.from(document.getElementsByName(fieldName))
        .filter((field) => field instanceof HTMLInputElement && field.type === 'radio');

    function syncOtherInput() {
        const selected = radios.find((radio) => radio.checked);
        const enablesOther = selected && ['Outra', 'Outro'].includes(selected.value);
        input.disabled = !enablesOther;
        input.hidden = !enablesOther;

        if (!enablesOther) {
            input.value = '';
        }
    }

    radios.forEach((radio) => radio.addEventListener('change', syncOtherInput));
    syncOtherInput();
});

document.querySelectorAll('[data-print-sheet]').forEach((button) => {
    button.addEventListener('click', () => {
        const template = document.querySelector(button.dataset.printSheet || '');

        if (!template) {
            return;
        }

        const printWindow = window.open('', '_blank', 'width=900,height=700');

        if (!printWindow) {
            window.print();
            return;
        }

        printWindow.document.write(`<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Imprimir ficha</title>
<style>
body { margin: 24px; color: #111827; font-family: Arial, sans-serif; }
.print-sheet { font-size: 12px; }
.print-sheet h1 { font-size: 18px; margin: 0 0 12px; text-align: center; }
.print-sheet h2 { border-bottom: 1px solid #111827; font-size: 13px; margin: 14px 0 8px; padding-bottom: 3px; }
.print-grid { display: grid; gap: 6px 14px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.print-field { border-bottom: 1px solid #d1d5db; min-height: 22px; padding: 3px 0; white-space: pre-wrap; }
.print-field.full { grid-column: 1 / -1; }
.print-label { font-weight: 700; }
.print-muted { color: #6b7280; }
@media print { body { margin: 12mm; } }
</style>
</head>
<body>${template.innerHTML}</body>
</html>`);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    });
});
</script>

</body>
</html>
