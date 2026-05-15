<?php
include 'config/db.php';

use Clinic\Repositories\ServiceCatalogRepository;
use Clinic\Services\ServiceCatalogService;

$pdo = app_pdo();
$serviceRepository = new ServiceCatalogRepository($pdo);
$serviceCatalog = new ServiceCatalogService($serviceRepository);
$serviceId = app_query_int('id') ?: app_post_int('service_id');
$selectedService = $serviceId ? $serviceRepository->find($serviceId) : null;
$activeServiceTab = app_request_query('tab', 'dados') === 'precos' ? 'precos' : 'dados';
$priceMessage = '';
$priceFormValues = [
    'preco_id' => 0,
    'servico_id' => $serviceId,
    'plano_id' => 0,
    'valor' => '',
    'ativo' => 1,
    'permite_alterar_guia' => 0,
    'observacoes' => '',
];

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
    } elseif ($action === 'save_service_price') {
        $activeServiceTab = 'precos';
        $priceId = app_post_int('preco_id');
        $priceFormValues = [
            'preco_id' => $priceId,
            'servico_id' => (int) $selectedService['id'],
            'plano_id' => app_post_int('plano_id'),
            'valor' => app_request_post('valor', '') ?? '',
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
            'permite_alterar_guia' => isset($_POST['permite_alterar_guia']) ? 1 : 0,
            'observacoes' => app_request_post('observacoes', '') ?? '',
        ];
        $_POST['servico_id'] = (string) $selectedService['id'];
        $price = $priceId > 0 ? $serviceRepository->findPrice($priceId) : null;

        if ($priceId > 0 && (!$price || (int) $price['servico_id'] !== (int) $selectedService['id'])) {
            $result = ['ok' => false, 'message' => 'Preco nao encontrado para este servico.'];
        } else {
            $result = $serviceCatalog->savePrice($priceId > 0 ? $priceId : null, $_POST);
        }
    } elseif ($action === 'delete_service_price') {
        $activeServiceTab = 'precos';
        $priceId = app_post_int('preco_id');
        $price = $priceId > 0 ? $serviceRepository->findPrice($priceId) : null;

        if (!$price || (int) $price['servico_id'] !== (int) $selectedService['id']) {
            $result = ['ok' => false, 'message' => 'Preco nao encontrado para este servico.'];
        } else {
            $result = $serviceCatalog->deletePrice($priceId);
        }
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok'] && $action === 'delete_service') {
        app_redirect('secretaria_servicos.php');
    }

    if ($result['ok'] && $action === 'save_service') {
        app_redirect('editar_servico.php?' . app_build_query(['id' => $selectedService['id']]));
    }

    if ($result['ok'] && in_array($action, ['save_service_price', 'delete_service_price'], true)) {
        app_redirect('editar_servico.php?' . app_build_query(['id' => $selectedService['id'], 'tab' => 'precos']));
    }

    if (!$result['ok'] && in_array($action, ['save_service_price', 'delete_service_price'], true)) {
        $priceMessage = $result['message'];
    }
}

$planPriceOptions = $serviceRepository->planOptions();
$servicePrices = $serviceRepository->pricesForService((int) $selectedService['id']);

include __DIR__ . '/app/Views/services/form.php';
