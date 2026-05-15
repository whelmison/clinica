<?php
include 'config/db.php';

use Clinic\Repositories\PlanRepository;
use Clinic\Services\PlanService;

$pdo = app_pdo();
$planRepository = new PlanRepository($pdo);
$planService = new PlanService($planRepository);

$planFilters = [
    'busca_plano' => app_request_query('busca_plano', '') ?? '',
];
$formValues = [
    'nome' => '',
];
$autoOpenCreateModal = false;

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $formValues = [
        'nome' => app_request_post('nome', '') ?? '',
    ];

    if ($action === 'save_plan') {
        $result = $planService->save(null, $_POST);
        app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

        if ($result['ok']) {
            app_redirect('planos.php');
        }

        $autoOpenCreateModal = true;
    } else {
        app_flash('danger', 'Acao invalida.');
        $autoOpenCreateModal = true;
    }
}

$pageData = $planRepository->paginate($planFilters, max(1, app_query_int('plan_page', 1)), 12);
$plansRows = $pageData['items'];
$plansPagination = $pageData['pagination'];

include __DIR__ . '/app/Views/plans/page.php';
