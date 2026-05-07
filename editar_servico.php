<?php
include 'config/db.php';

use Clinic\Repositories\ServiceCatalogRepository;
use Clinic\Services\ServiceCatalogService;

$pdo = app_pdo();
$serviceRepository = new ServiceCatalogRepository($pdo);
$serviceCatalog = new ServiceCatalogService($serviceRepository);
$serviceId = app_query_int('id') ?: app_post_int('service_id');
$selectedService = $serviceId ? $serviceRepository->find($serviceId) : null;

if (!$selectedService) {
    app_flash('danger', 'Servico nao encontrado.');
    app_redirect('secretaria_servicos.php');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'save_service') {
        $result = $serviceCatalog->save((int) $selectedService['id'], $_POST);
    } elseif ($action === 'delete_service') {
        $result = $serviceCatalog->delete((int) $selectedService['id']);
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok'] && $action === 'delete_service') {
        app_redirect('secretaria_servicos.php');
    }

    if ($result['ok'] && $action === 'save_service') {
        app_redirect('editar_servico.php?' . app_build_query(['id' => $selectedService['id']]));
    }
}

include __DIR__ . '/app/Views/services/form.php';
