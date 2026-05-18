<?php
include 'config/db.php';

use Clinic\Repositories\ServiceCatalogRepository;
use Clinic\Services\ServiceCatalogService;

$pdo = app_pdo();
$serviceRepository = new ServiceCatalogRepository($pdo);
$serviceCatalog = new ServiceCatalogService($serviceRepository);
$serviceMessage = '';
$activeTab = app_request_query('tab', 'servicos') === 'precos' ? 'precos' : 'servicos';
$autoOpenServiceModal = app_query_int('open_new') === 1;
$serviceFormValues = [
    'nome' => '',
    'tempo_minutos' => 50,
    'tipo_agendamento' => 'individual',
    'capacidade_agendamento' => 1,
    'ativo' => 1,
];

if (app_request_method() === 'POST') {
    $action = app_request_post('action', '') ?? '';

    if ($action === 'save_service') {
        $activeTab = 'servicos';
        $serviceFormValues = [
            'nome' => app_request_post('nome', '') ?? '',
            'tempo_minutos' => app_post_int('tempo_minutos', 50),
            'tipo_agendamento' => app_request_post('tipo_agendamento', 'individual') ?? 'individual',
            'capacidade_agendamento' => app_post_int('capacidade_agendamento', 1),
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
$priceReportRows = $serviceRepository->priceReport($filters);
$priceReport = [];

foreach ($priceReportRows as $row) {
    $serviceId = (int) $row['service_id'];

    if (!isset($priceReport[$serviceId])) {
        $priceReport[$serviceId] = [
            'id' => $serviceId,
            'nome' => $row['service_name'],
            'tempo_minutos' => $row['tempo_minutos'],
            'tipo_agendamento' => $row['tipo_agendamento'],
            'capacidade_agendamento' => $row['capacidade_agendamento'],
            'ativo' => $row['service_active'],
            'precos' => [],
        ];
    }

    if (!empty($row['price_id'])) {
        $priceReport[$serviceId]['precos'][] = [
            'id' => $row['price_id'],
            'plano_id' => $row['plano_id'],
            'plano_nome' => $row['plano_nome'],
            'valor' => $row['valor'],
            'ativo' => $row['price_active'],
            'permite_alterar_guia' => $row['permite_alterar_guia'],
            'observacoes' => $row['observacoes'],
        ];
    }
}

$clinicLogoColumn = app_column_exists($conn, 'clinicas', 'logotipo') ? 'logotipo' : 'NULL AS logotipo';
$priceReportClinic = app_stmt_one(
    $conn,
    'SELECT nome_fantasia, razao_social, cnpj, telefone, whatsapp, email, endereco, cidade, estado, ' . $clinicLogoColumn . '
     FROM clinicas
     WHERE id = ?
     LIMIT 1',
    'i',
    [app_active_clinic_id()]
) ?? [];
$priceReportIssuedAt = (new DateTimeImmutable('now', new DateTimeZone('America/Araguaina')))->format('d/m/Y H:i');
$priceReportFilterText = trim((string) $filters['busca_servico']) !== ''
    ? 'Busca: ' . trim((string) $filters['busca_servico'])
    : 'Todos os servicos';
$priceReportTotals = [
    'servicos' => count($priceReport),
    'precos' => array_sum(array_map(static fn (array $service): int => count($service['precos']), $priceReport)),
];

include __DIR__ . '/app/Views/services/page.php';
