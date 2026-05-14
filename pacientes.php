<?php
include 'config/db.php';

use Clinic\Repositories\PatientRepository;
use Clinic\Services\PatientService;

app_install_schema($conn);

$pdo = app_pdo();
$patientRepository = new PatientRepository($pdo);
$patientService = new PatientService($patientRepository);
$requestedPatientId = app_query_int('patient_id');
$selectedPatient = null;
$isPatientPost = app_request_method() === 'POST';
$patientFilters = [
    'paciente' => trim((string) ($isPatientPost ? app_request_post('filter_paciente', '') : app_request_query('paciente', ''))),
    'plano' => trim((string) ($isPatientPost ? app_request_post('filter_plano', '') : app_request_query('plano', ''))),
    'status' => trim((string) ($isPatientPost ? app_request_post('filter_status', '') : app_request_query('status', ''))),
];
$shouldLoadPatients = app_request_query('filtrar', '') === '1'
    || ($isPatientPost && app_request_post('filter_filtrar', '') === '1')
    || $requestedPatientId > 0;

if (!function_exists('app_patient_filter_query')) {
    function app_patient_filter_query(array $filters, array $extra = []): string
    {
        return app_build_query([
            'paciente' => $filters['paciente'] ?? '',
            'plano' => $filters['plano'] ?? '',
            'status' => $filters['status'] ?? '',
            'filtrar' => 1,
        ], $extra);
    }
}

if ($requestedPatientId > 0) {
    $selectedPatient = $patientRepository->find($requestedPatientId);

    if (!$selectedPatient) {
        app_flash('danger', 'Paciente nao encontrado.');
        app_redirect('pacientes.php');
    }
}

$patientDayOptions = [
    'Segunda-feira',
    'Terça-feira',
    'Quarta-feira',
    'Quinta-feira',
    'Sexta-feira',
    'Sábado',
];
$patientFormValues = [
    'patient_id' => (int) ($selectedPatient['id'] ?? 0),
    'nome' => (string) ($selectedPatient['nome'] ?? ''),
    'telefone' => (string) ($selectedPatient['telefone'] ?? ''),
    'cpf' => (string) ($selectedPatient['cpf'] ?? ''),
    'data_nascimento' => !empty($selectedPatient['data_nascimento']) ? app_date_br((string) $selectedPatient['data_nascimento']) : '',
    'cep' => (string) ($selectedPatient['cep'] ?? ''),
    'endereco' => (string) ($selectedPatient['endereco'] ?? ''),
    'numero' => (string) ($selectedPatient['numero'] ?? ''),
    'complemento' => (string) ($selectedPatient['complemento'] ?? ''),
    'bairro' => (string) ($selectedPatient['bairro'] ?? ''),
    'cidade' => (string) ($selectedPatient['cidade'] ?? ''),
    'estado' => (string) ($selectedPatient['estado'] ?? ''),
    'telefone_emergencia' => (string) ($selectedPatient['telefone_emergencia'] ?? ''),
    'observacoes' => (string) ($selectedPatient['observacoes'] ?? ''),
    'indicado_por' => (string) ($selectedPatient['indicado_por'] ?? ''),
    'prontuario' => (string) ($selectedPatient['prontuario'] ?? ''),
    'dia_preferencia' => (string) ($selectedPatient['dia_preferencia'] ?? ''),
    'horario_preferencia' => (string) ($selectedPatient['horario_preferencia'] ?? ''),
];
$autoOpenPatientModal = $selectedPatient !== null || app_query_int('open_new') === 1;
$patientSaveError = '';

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'save_patient') {
        $patientFormValues = [
            'patient_id' => app_post_int('patient_id'),
            'nome' => app_request_post('nome', '') ?? '',
            'telefone' => app_request_post('telefone', '') ?? '',
            'cpf' => app_request_post('cpf', '') ?? '',
            'data_nascimento' => app_request_post('data_nascimento', '') ?? '',
            'cep' => app_request_post('cep', '') ?? '',
            'endereco' => app_request_post('endereco', '') ?? '',
            'numero' => app_request_post('numero', '') ?? '',
            'complemento' => app_request_post('complemento', '') ?? '',
            'bairro' => app_request_post('bairro', '') ?? '',
            'cidade' => app_request_post('cidade', '') ?? '',
            'estado' => app_request_post('estado', '') ?? '',
            'telefone_emergencia' => app_request_post('telefone_emergencia', '') ?? '',
            'observacoes' => app_request_post('observacoes', '') ?? '',
            'indicado_por' => app_request_post('indicado_por', '') ?? '',
            'prontuario' => app_request_post('prontuario', '') ?? '',
            'dia_preferencia' => app_request_post('dia_preferencia', '') ?? '',
            'horario_preferencia' => app_request_post('horario_preferencia', '') ?? '',
        ];
        $result = $patientService->save(app_post_int('patient_id') ?: null, $_POST);

        if ($result['ok']) {
            app_flash('success', $result['message']);
            app_redirect('pacientes.php?' . app_patient_filter_query($patientFilters));
        }

        $patientSaveError = $result['message'];
        $autoOpenPatientModal = true;
    } else {
        $patientSaveError = 'Acao invalida.';
        $autoOpenPatientModal = true;
    }
}

$rows = $shouldLoadPatients ? $patientRepository->dashboardRows($patientFilters) : [];
$patientRows = [];
$semGuiaLista = [];
$prestesLista = [];

foreach ($rows as $row) {
    $planName = trim((string) ($row['plano'] ?? ''));
    $remaining = $row['menor_restante'] === null ? null : (int) $row['menor_restante'];
    $openGuides = (int) ($row['abertas'] ?? 0);

    if ($openGuides <= 0) {
        $statusText = 'Sem guia';
        $statusClass = 'status-inativo';
        $semGuiaLista[] = (string) $row['nome'];
    } elseif ($remaining !== null && $remaining <= 2) {
        $statusText = 'Prestes a ficar sem guia';
        $statusClass = 'status-atencao';
        $prestesLista[] = (string) $row['nome'] . ' (' . $remaining . ' restantes)';
    } else {
        $statusText = 'Ativo';
        $statusClass = 'status-ativo';
    }

    if ($patientFilters['status'] !== '' && $patientFilters['status'] !== $statusText) {
        continue;
    }

    $patientRows[] = [
        'id' => (int) $row['id'],
        'nome' => (string) $row['nome'],
        'telefone' => (string) ($row['telefone'] ?? ''),
        'cpf' => (string) ($row['cpf'] ?? ''),
        'data_nascimento' => !empty($row['data_nascimento']) ? app_date_br((string) $row['data_nascimento']) : '',
        'telefone_emergencia' => (string) ($row['telefone_emergencia'] ?? ''),
        'plano' => $planName !== '' ? $planName : '-',
        'dia_preferencia' => (string) ($row['dia_preferencia'] ?? ''),
        'horario_preferencia' => (string) ($row['horario_preferencia'] ?? ''),
        'status_text' => $statusText,
        'status_class' => $statusClass,
    ];
}

include __DIR__ . '/app/Views/patients/page.php';
