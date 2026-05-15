<?php
include 'config/db.php';

use Clinic\Repositories\ServiceCatalogRepository;
use Clinic\Services\ServiceCatalogService;

$pdo = app_pdo();
$serviceRepository = new ServiceCatalogRepository($pdo);
$serviceCatalog = new ServiceCatalogService($serviceRepository);
$selectedService = null;
$activeServiceTab = 'dados';
$servicePrices = [];
$planPriceOptions = [];
$priceMessage = '';
$priceFormValues = [
    'preco_id' => 0,
    'servico_id' => 0,
    'plano_id' => 0,
    'valor' => '',
    'ativo' => 1,
    'permite_alterar_guia' => 0,
    'observacoes' => '',
];

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $result = $action === 'save_service'
        ? $serviceCatalog->save(null, $_POST)
        : ['ok' => false, 'message' => 'Acao invalida.'];

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok']) {
        app_redirect('editar_servico.php?' . app_build_query(['id' => (int) $result['id']]));
    }
}

include __DIR__ . '/app/Views/services/form.php';
