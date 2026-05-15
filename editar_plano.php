<?php
include 'config/db.php';

use Clinic\Repositories\PlanRepository;
use Clinic\Services\PlanService;

$pdo = app_pdo();
$planRepository = new PlanRepository($pdo);
$planService = new PlanService($planRepository);
$selectedPlan = $planRepository->find(app_query_int('id'));

if (!$selectedPlan) {
    app_flash('danger', 'Plano nao encontrado.');
    app_redirect('planos.php');
}

$formValues = [
    'nome' => (string) $selectedPlan['nome'],
];

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $formValues = [
        'nome' => app_request_post('nome', '') ?? '',
    ];

    if ($action === 'save_plan') {
        $result = $planService->save((int) $selectedPlan['id'], $_POST);
        app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

        if ($result['ok']) {
            app_redirect('planos.php');
        }
    } else {
        app_flash('danger', 'Acao invalida.');
    }
}

include __DIR__ . '/app/Views/plans/edit.php';
