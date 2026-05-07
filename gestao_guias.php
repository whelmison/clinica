<?php
include 'config/db.php';

use Clinic\Repositories\GuideRepository;
use Clinic\Services\GuideService;

require_once __DIR__ . '/app/Views/guides/render.php';

$pdo = app_pdo();
$guideRepository = new GuideRepository($pdo);
$guideService = new GuideService($pdo, $guideRepository);
$currentUser = app_current_user() ?? [];
$scopeProfessionalId = app_is_professional_user() ? app_current_professional_id() : null;
$canCreateGuides = $guideService->canCreate($currentUser);
$canDeleteGuides = $guideService->canDelete($currentUser);
$requestMethod = app_request_method();

$filterDefaults = [
    'guia_id' => $requestMethod === 'POST' ? app_post_int('filter_guia_id') : app_query_int('guia_id'),
    'paciente_id' => $requestMethod === 'POST' ? app_post_int('filter_paciente_id') : app_query_int('paciente_id'),
    'profissional_id' => $requestMethod === 'POST' ? app_post_int('filter_profissional_id') : app_query_int('profissional_id'),
    'busca' => $requestMethod === 'POST'
        ? (app_request_post('filter_busca', '') ?? '')
        : (app_request_query('busca', '') ?? ''),
];

if ($scopeProfessionalId !== null) {
    $filterDefaults['profissional_id'] = $scopeProfessionalId;
}

$selectedId = $requestMethod === 'POST'
    ? (app_post_int('selected') ?: app_post_int('guide_id') ?: null)
    : (app_query_int('selected') ?: null);
$page = $requestMethod === 'POST'
    ? max(1, app_post_int('filter_page', 1))
    : max(1, app_query_int('page', 1));
$guideOptions = $guideRepository->optionLists($scopeProfessionalId);
$createDefaults = [
    'data' => date('Y-m-d'),
    'total_sessoes' => 1,
    'tipo_guia' => 'particular',
];
$selectedGuide = $selectedId ? $guideRepository->find($selectedId, $scopeProfessionalId) : null;

if ($selectedId !== null && !$selectedGuide) {
    app_flash('danger', 'Guia nao encontrada.');
    app_redirect('gestao_guias.php?' . app_build_query([
        'guia_id' => $filterDefaults['guia_id'] ?: null,
        'paciente_id' => $filterDefaults['paciente_id'] ?: null,
        'profissional_id' => $scopeProfessionalId ?? ($filterDefaults['profissional_id'] ?: null),
        'busca' => $filterDefaults['busca'] !== '' ? $filterDefaults['busca'] : null,
        'page' => $page > 1 ? $page : null,
    ]));
}

$canEditSelectedGuide = $selectedGuide ? $guideService->canEdit($currentUser, $selectedGuide) : false;

if ($selectedGuide && !$canEditSelectedGuide) {
    app_flash('danger', 'Sem permissao para editar esta guia.');
    app_redirect('gestao_guias.php?' . app_build_query([
        'guia_id' => $filterDefaults['guia_id'] ?: null,
        'paciente_id' => $filterDefaults['paciente_id'] ?: null,
        'profissional_id' => $scopeProfessionalId ?? ($filterDefaults['profissional_id'] ?: null),
        'busca' => $filterDefaults['busca'] !== '' ? $filterDefaults['busca'] : null,
        'page' => $page > 1 ? $page : null,
    ]));
}

$guideFormValues = [
    'guide_id' => (int) ($selectedGuide['id'] ?? 0),
    'paciente_id' => (int) ($selectedGuide['paciente_id'] ?? 0),
    'profissional_id' => (int) ($selectedGuide['profissional_id'] ?? ($scopeProfessionalId ?? 0)),
    'plano_id' => (int) ($selectedGuide['plano_id'] ?? 0),
    'tipo_guia' => (string) ($selectedGuide['tipo_guia'] ?? $createDefaults['tipo_guia']),
    'data' => (string) ($selectedGuide['data'] ?? $createDefaults['data']),
    'total_sessoes' => (int) ($selectedGuide['total_sessoes'] ?? $createDefaults['total_sessoes']),
    'codigo' => (string) ($selectedGuide['codigo'] ?? ''),
    'valor_guia' => $selectedGuide
        ? number_format((float) $selectedGuide['valor_guia'], 2, ',', '.')
        : '',
    'convenio' => (string) ($selectedGuide['convenio'] ?? ''),
    'observacoes' => (string) ($selectedGuide['observacoes'] ?? ''),
    'lote_id' => (int) ($selectedGuide['lote_id'] ?? 0),
];
$autoOpenGuideModal = ($selectedGuide !== null && $canEditSelectedGuide) || app_query_int('open_new') === 1;

$baseGuideQuery = static function () use ($filterDefaults, $page, $scopeProfessionalId): string {
    return app_build_query([
        'guia_id' => $filterDefaults['guia_id'] ?: null,
        'paciente_id' => $filterDefaults['paciente_id'] ?: null,
        'profissional_id' => $scopeProfessionalId ?? ($filterDefaults['profissional_id'] ?: null),
        'busca' => $filterDefaults['busca'] !== '' ? $filterDefaults['busca'] : null,
        'page' => $page > 1 ? $page : null,
    ]);
};

if ($requestMethod === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'save_guide') {
        $guideId = app_post_int('guide_id');
        $guideFormValues = [
            'guide_id' => $guideId,
            'paciente_id' => app_post_int('paciente_id'),
            'profissional_id' => $scopeProfessionalId ?? app_post_int('profissional_id'),
            'plano_id' => app_post_int('plano_id'),
            'tipo_guia' => app_request_post('tipo_guia', $createDefaults['tipo_guia']) ?? $createDefaults['tipo_guia'],
            'data' => app_request_post('data', $createDefaults['data']) ?? $createDefaults['data'],
            'total_sessoes' => max(1, app_post_int('total_sessoes', $createDefaults['total_sessoes'])),
            'codigo' => app_request_post('codigo', '') ?? '',
            'valor_guia' => app_request_post('valor_guia', '') ?? '',
            'convenio' => app_request_post('convenio', '') ?? '',
            'observacoes' => app_request_post('observacoes', '') ?? '',
            'lote_id' => app_post_int('lote_id'),
        ];
        $result = $guideId > 0
            ? $guideService->update($guideId, $_POST, $currentUser)
            : $guideService->create($_POST, $currentUser);
        $selectedId = $guideId > 0 ? $guideId : null;
        $autoOpenGuideModal = !$result['ok'];
    } elseif ($action === 'delete_guide') {
        $result = $guideService->delete(app_post_int('guide_id'), $currentUser);
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok'] || $action === 'delete_guide') {
        $redirectQuery = $baseGuideQuery();
        app_redirect('gestao_guias.php' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
    }

    if (!$result['ok']) {
        $selectedGuide = $guideFormValues['guide_id'] > 0 ? $guideRepository->find((int) $guideFormValues['guide_id'], $scopeProfessionalId) : null;
        $canEditSelectedGuide = $selectedGuide ? $guideService->canEdit($currentUser, $selectedGuide) : false;
    }
}

$guidePage = $guideRepository->paginate($filterDefaults, $page, 18, $scopeProfessionalId);
$guides = $guidePage['items'];
$pagination = $guidePage['pagination'];
$activeGuideId = (int) ($guideFormValues['guide_id'] ?? ($selectedGuide['id'] ?? 0));
$guideCardsHtml = render_guide_cards($guides, $activeGuideId > 0 ? $activeGuideId : null, $filterDefaults, $pagination);
$guideMetricsHtml = render_guide_metrics($guides);

if (app_is_ajax_request()) {
    app_json([
        'cards' => $guideCardsHtml,
        'metrics' => $guideMetricsHtml,
        'listLabel' => (int) ($pagination['total'] ?? 0) . ' guia(s) encontradas | ' . (int) ($pagination['per_page'] ?? 18) . ' por pagina',
    ]);
}

if ($selectedGuide) {
    $guideFormValues['lote_id'] = (int) ($selectedGuide['lote_id'] ?? $guideFormValues['lote_id']);
}

include __DIR__ . '/app/Views/guides/page.php';
