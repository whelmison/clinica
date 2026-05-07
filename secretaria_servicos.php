<?php
include 'config/db.php';

use Clinic\Repositories\ServiceCatalogRepository;
use Clinic\Services\ServiceCatalogService;

$pdo = app_pdo();
$serviceRepository = new ServiceCatalogRepository($pdo);
$serviceCatalog = new ServiceCatalogService($serviceRepository);
$serviceMessage = '';
$autoOpenServiceModal = app_query_int('open_new') === 1;
$serviceFormValues = [
    'nome' => '',
    'tempo_minutos' => 50,
    'ativo' => 1,
];

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'save_service') {
        $serviceFormValues = [
            'nome' => app_request_post('nome', '') ?? '',
            'tempo_minutos' => app_post_int('tempo_minutos', 50),
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];
        $result = $serviceCatalog->save(null, $_POST);
        app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

        if ($result['ok']) {
            app_redirect('secretaria_servicos.php');
        }

        $serviceMessage = $result['message'];
        $autoOpenServiceModal = true;
    } else {
        app_flash('danger', 'Acao invalida.');
        $autoOpenServiceModal = true;
    }
}

$filters = [
    'busca_servico' => app_request_query('busca_servico', '') ?? '',
];
$pageData = $serviceRepository->paginate($filters, max(1, app_query_int('service_page', 1)), 8);
$servicesRows = $pageData['items'];
$pagination = $pageData['pagination'];

include __DIR__ . '/app/Views/services/page.php';
