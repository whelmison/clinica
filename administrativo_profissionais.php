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
    'profissional_id' => $requestMethod === 'POST'
        ? app_post_int('profissional_id')
        : app_query_int('profissional_id'),
];
$professionalModalTab = app_request_query('tab', 'dados') ?? 'dados';
$professionalModalTab = in_array($professionalModalTab, ['dados', 'permissoes', 'servicos'], true) ? $professionalModalTab : 'dados';
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
$serviceChargeTypes = [];
$serviceChargeValues = [];
$serviceTaxEnabled = [];
$serviceTaxPercentages = [];

foreach ($services as $service) {
    $serviceId = (int) $service['id'];
    $serviceConfig = $selectedProfessionalServiceMap[$serviceId] ?? [];
    $serviceDurations[$serviceId] = (int) ($serviceConfig['tempo_minutos'] ?? $service['tempo_minutos']);
    $serviceChargeTypes[$serviceId] = (string) ($serviceConfig['cobranca_tipo'] ?? 'percentual');
    $serviceChargeValues[$serviceId] = number_format((float) ($serviceConfig['cobranca_valor'] ?? ($selectedProfessional['comissao_percentual'] ?? 0)), 2, ',', '.');
    $serviceTaxEnabled[$serviceId] = (int) ($serviceConfig['cobra_imposto'] ?? (!empty($selectedProfessional['imposto_percentual']) || !empty($selectedProfessional['imposto_fixo']) ? 1 : 0));
    $serviceTaxPercentages[$serviceId] = number_format((float) ($serviceConfig['imposto_percentual'] ?? ($selectedProfessional['imposto_percentual'] ?? 0)), 2, ',', '.');
}

$professionalFormValues = [
    'professional_id' => (int) ($selectedProfessional['id'] ?? 0),
    'nome' => (string) ($selectedProfessional['nome'] ?? ''),
    'profissao' => (string) ($selectedProfessional['profissao'] ?? ''),
    'telefone' => (string) ($selectedProfessional['telefone'] ?? ''),
    'endereco' => (string) ($selectedProfessional['endereco'] ?? ''),
    'foto' => (string) ($selectedProfessional['foto'] ?? ''),
    'permite_editar_guias' => !empty($selectedProfessional['permite_editar_guias']),
    'permite_secretaria_liberar_agenda' => !empty($selectedProfessional['permite_secretaria_liberar_agenda']),
    'servicos' => $selectedServiceIds,
    'duracoes' => $serviceDurations,
    'cobranca_tipo' => $serviceChargeTypes,
    'cobranca_valor' => $serviceChargeValues,
    'cobra_imposto' => $serviceTaxEnabled,
    'imposto_percentual' => $serviceTaxPercentages,
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
        'foto' => (string) ($selectedProfessional['foto'] ?? ''),
        'permite_editar_guias' => isset($_POST['permite_editar_guias']),
        'permite_secretaria_liberar_agenda' => isset($_POST['permite_secretaria_liberar_agenda']),
        'servicos' => array_map('intval', $_POST['servicos'] ?? []),
        'duracoes' => array_map(
            static fn ($value): int => max(1, (int) $value),
            $_POST['duracoes'] ?? []
        ),
        'cobranca_tipo' => $_POST['cobranca_tipo'] ?? [],
        'cobranca_valor' => $_POST['cobranca_valor'] ?? [],
        'cobra_imposto' => $_POST['cobra_imposto'] ?? [],
        'imposto_percentual' => $_POST['imposto_percentual'] ?? [],
    ];
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'save_professional') {
        $result = $professionalService->saveProfessional($professionalId ?: null, $_POST, $_FILES['foto'] ?? null);
        $autoOpenProfessionalModal = !$result['ok'];
    } elseif ($action === 'delete_professional') {
        $result = $professionalService->deleteProfessional($professionalId);
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok'] || $action === 'delete_professional') {
        app_redirect('administrativo_profissionais.php?' . app_build_query([
            'busca_profissional' => $professionalFilters['busca_profissional'],
            'profissional_id' => (int) ($professionalFilters['profissional_id'] ?? 0) > 0 ? (int) $professionalFilters['profissional_id'] : null,
            'professional_page' => $requestedPage > 1 ? $requestedPage : null,
        ]));
    }
}

$professionalsData = $professionalRepository->professionals($requestedPage, 10, $professionalFilters);
$professionalsRows = $professionalsData['items'];
$professionalsPagination = $professionalsData['pagination'];

include __DIR__ . '/app/Views/professionals/page.php';
