<?php
include 'config/db.php';

use Clinic\Repositories\ProfessionalRepository;
use Clinic\Services\ProfessionalService;

$pdo = app_pdo();
$professionalRepository = new ProfessionalRepository($pdo);
$professionalService = new ProfessionalService($pdo, $professionalRepository);
$requestMethod = app_request_method();
$professionalFilters = [
    'busca_profissional' => $requestMethod === 'POST'
        ? (app_request_post('busca_profissional', '') ?? '')
        : (app_request_query('busca_profissional', '') ?? ''),
];
$requestedPage = $requestMethod === 'POST'
    ? max(1, app_post_int('professional_page', 1))
    : max(1, app_query_int('professional_page', 1));
$services = $professionalRepository->services();
$selectedProfessional = app_query_int('professional_id') ? $professionalRepository->findProfessional(app_query_int('professional_id')) : null;

if (app_query_int('professional_id') && !$selectedProfessional) {
    app_flash('danger', 'Profissional nao encontrado.');
    app_redirect('administrativo_profissionais.php');
}

$selectedProfessionalServiceMap = $selectedProfessional ? $professionalRepository->professionalServiceMap((int) $selectedProfessional['id']) : [];
$selectedServiceIds = array_map('intval', array_keys($selectedProfessionalServiceMap));
$serviceDurations = [];

foreach ($services as $service) {
    $serviceId = (int) $service['id'];
    $serviceDurations[$serviceId] = (int) ($selectedProfessionalServiceMap[$serviceId] ?? $service['tempo_minutos']);
}

$professionalFormValues = [
    'professional_id' => (int) ($selectedProfessional['id'] ?? 0),
    'nome' => (string) ($selectedProfessional['nome'] ?? ''),
    'profissao' => (string) ($selectedProfessional['profissao'] ?? ''),
    'telefone' => (string) ($selectedProfessional['telefone'] ?? ''),
    'endereco' => (string) ($selectedProfessional['endereco'] ?? ''),
    'permite_editar_guias' => !empty($selectedProfessional['permite_editar_guias']),
    'permite_secretaria_liberar_agenda' => !empty($selectedProfessional['permite_secretaria_liberar_agenda']),
    'servicos' => $selectedServiceIds,
    'duracoes' => $serviceDurations,
];
$autoOpenProfessionalModal = $selectedProfessional !== null || app_query_int('open_new') === 1;

if ($requestMethod === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $professionalId = app_post_int('professional_id');
    $professionalFormValues = [
        'professional_id' => $professionalId,
        'nome' => app_request_post('nome', '') ?? '',
        'profissao' => app_request_post('profissao', '') ?? '',
        'telefone' => app_request_post('telefone', '') ?? '',
        'endereco' => app_request_post('endereco', '') ?? '',
        'permite_editar_guias' => isset($_POST['permite_editar_guias']),
        'permite_secretaria_liberar_agenda' => isset($_POST['permite_secretaria_liberar_agenda']),
        'servicos' => array_map('intval', $_POST['servicos'] ?? []),
        'duracoes' => array_map(
            static fn ($value): int => max(1, (int) $value),
            $_POST['duracoes'] ?? []
        ),
    ];
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'save_professional') {
        $result = $professionalService->saveProfessional($professionalId ?: null, $_POST);
        $autoOpenProfessionalModal = !$result['ok'];
    } elseif ($action === 'delete_professional') {
        $result = $professionalService->deleteProfessional($professionalId);
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok'] || $action === 'delete_professional') {
        app_redirect('administrativo_profissionais.php?' . app_build_query([
            'busca_profissional' => $professionalFilters['busca_profissional'],
            'professional_page' => $requestedPage > 1 ? $requestedPage : null,
        ]));
    }
}

$professionalsData = $professionalRepository->professionals($requestedPage, 10, $professionalFilters);
$professionalsRows = $professionalsData['items'];
$professionalsPagination = $professionalsData['pagination'];

include __DIR__ . '/app/Views/professionals/page.php';
