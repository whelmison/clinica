<?php
$isPriceTab = ($activeTab ?? 'servicos') === 'precos';
$priceReportClinic = $priceReportClinic ?? [];
$priceReportIssuedAt = $priceReportIssuedAt ?? date('d/m/Y H:i');
$priceReportFilterText = $priceReportFilterText ?? 'Todos os servicos';
$priceReportTotals = $priceReportTotals ?? ['servicos' => count($priceReport ?? []), 'precos' => 0];
$reportClinicName = trim((string) ($priceReportClinic['nome_fantasia'] ?? app_current_clinic_name()));
$reportClinicLegalName = trim((string) ($priceReportClinic['razao_social'] ?? ''));
$reportContacts = [];
$reportAddress = [];
$reportLogoSrc = trim((string) ($priceReportClinic['logotipo'] ?? ''));
$reportInitials = '';

foreach (preg_split('/\s+/', $reportClinicName) ?: [] as $part) {
    if ($part === '') {
        continue;
    }

    $reportInitials .= strtoupper(substr($part, 0, 1));

    if (strlen($reportInitials) >= 2) {
        break;
    }
}

$reportInitials = substr($reportInitials !== '' ? $reportInitials : 'CL', 0, 3);

if (!empty($priceReportClinic['cnpj'])) {
    $reportContacts[] = 'CNPJ ' . app_format_cnpj((string) $priceReportClinic['cnpj']);
}

if (!empty($priceReportClinic['telefone'])) {
    $reportContacts[] = 'Tel. ' . app_format_phone_br((string) $priceReportClinic['telefone']);
}

if (!empty($priceReportClinic['whatsapp']) && (string) $priceReportClinic['whatsapp'] !== (string) ($priceReportClinic['telefone'] ?? '')) {
    $reportContacts[] = 'WhatsApp ' . app_format_phone_br((string) $priceReportClinic['whatsapp']);
}

if (!empty($priceReportClinic['email'])) {
    $reportContacts[] = (string) $priceReportClinic['email'];
}

if (!empty($priceReportClinic['endereco'])) {
    $reportAddress[] = (string) $priceReportClinic['endereco'];
}

$reportCityState = trim(implode('-', array_filter([
    trim((string) ($priceReportClinic['cidade'] ?? '')),
    trim((string) ($priceReportClinic['estado'] ?? '')),
])));

if ($reportCityState !== '') {
    $reportAddress[] = $reportCityState;
}

if ($reportLogoSrc !== '' && !preg_match('/^(?:https?:)?\/\//i', $reportLogoSrc)) {
    $appRoot = dirname(__DIR__, 3);
    $reportLogoSrc = ltrim(str_replace('\\', '/', $reportLogoSrc), '/');
    $normalizedLogo = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $reportLogoSrc);
    $logoPath = $appRoot . DIRECTORY_SEPARATOR . $normalizedLogo;

    if (!is_file($logoPath)) {
        $reportLogoSrc = '';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Servicos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
html,
body {
    height: 100%;
    overflow: hidden;
}
body {
    display: flex;
    flex-direction: column;
}
.services-shell {
    flex: 1;
    min-height: 0;
    padding-top: 0.2rem;
    padding-bottom: 0.35rem !important;
}
.services-layout {
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 0.45rem;
}
.services-hero {
    margin-top: 0.2rem;
    padding: 0.78rem 0.92rem 0.74rem;
}
.services-hero h3 {
    font-size: 1.02rem;
}
.services-hero p {
    font-size: 0.72rem;
}
.services-card {
    border-radius: 18px;
}
.services-card .card-body,
.services-card .card-header {
    padding: 0.72rem 0.82rem;
}
.services-table-card {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}
.services-table-card .card-body {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
}
.services-table-wrap {
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.services-table-wrap .table {
    font-size: 0.74rem;
}
.services-table-wrap .table thead th {
    padding: 0.42rem 0.48rem;
    font-size: 0.6rem;
}
.services-table-wrap .table tbody td {
    padding: 0.42rem 0.48rem;
}
.services-table-wrap .btn {
    min-height: 28px;
    font-size: 0.68rem;
}
.services-modal .modal-content {
    border: 0;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 42px rgba(22, 51, 63, 0.2);
}
.services-modal .modal-header {
    padding: 0.88rem 1rem 0.78rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.08);
}
.services-modal .modal-title {
    font-size: 1rem;
    color: #16333f;
}
.services-form .form-control,
.services-form .form-select {
    min-height: 40px;
    border-radius: 14px;
    border-color: #dbe7ec;
    font-size: 0.82rem;
}
.services-form .btn,
.services-modal .btn {
    min-height: 40px;
    border-radius: 14px;
    font-size: 0.8rem;
}
.services-hint {
    color: #68828f;
    font-size: 0.72rem;
}
.services-tabs {
    display: flex;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.services-tabs .btn {
    min-height: 34px;
    border-radius: 999px;
    font-size: 0.76rem;
    padding: 0.38rem 0.8rem;
}
.service-price-report {
    flex: 1;
    min-height: 0;
    overflow: auto;
}
.service-prices-print-area {
    background: #fff;
}
.service-print-header {
    display: none;
    grid-template-columns: minmax(320px, 1fr) minmax(280px, 0.86fr);
    gap: 1rem;
    padding: 0.95rem 1rem 1rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.16);
    margin-bottom: 0.4rem;
}
.service-print-brand {
    display: flex;
    gap: 0.82rem;
    align-items: center;
    min-width: 0;
}
.service-print-logo {
    width: 118px;
    height: 64px;
    flex: 0 0 auto;
    border: 1px solid rgba(19, 74, 89, 0.16);
    border-radius: 8px;
    background: #f5fafb;
    color: #1f7a8c;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-weight: 800;
    font-size: 1.1rem;
}
.service-print-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.service-print-company,
.service-print-title {
    min-width: 0;
}
.service-print-company strong {
    display: block;
    color: #173642;
    font-size: 0.9rem;
    line-height: 1.18;
    text-transform: uppercase;
}
.service-print-company span,
.service-print-title p {
    display: block;
    color: #526b76;
    font-size: 0.72rem;
    line-height: 1.35;
    overflow-wrap: anywhere;
}
.service-print-title {
    align-self: center;
}
.service-print-title h2 {
    color: #102f3a;
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1.12;
    margin: 0 0 0.45rem;
}
.service-print-title p {
    margin: 0;
}
.service-price-report-table {
    margin: 0;
    min-width: 760px;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.76rem;
    line-height: 1.35;
}
.service-price-report-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    background: #f5fafb;
    border-bottom: 1px solid rgba(31, 122, 140, 0.16);
    color: #5f7885;
    font-size: 0.62rem;
    letter-spacing: 0;
    text-transform: uppercase;
}
.service-price-report-table th,
.service-price-report-table td {
    padding: 0.48rem 0.62rem;
    vertical-align: middle;
}
.service-price-report-table tbody td {
    border-bottom: 1px solid rgba(19, 74, 89, 0.07);
}
.service-price-service-row td {
    background: #edf8fa;
    border-top: 1px solid rgba(31, 122, 140, 0.18);
    border-bottom: 1px solid rgba(31, 122, 140, 0.12);
}
.service-price-master {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    align-items: center;
}
.service-price-master strong {
    display: block;
    color: #173642;
    font-size: 0.84rem;
}
.service-price-master span,
.service-price-empty {
    color: #68828f;
    font-size: 0.7rem;
}
.service-price-report > .service-price-empty {
    padding: 0.75rem;
}
.service-price-actions {
    white-space: nowrap;
}
.service-price-detail-empty td {
    color: #68828f;
    font-size: 0.72rem;
    padding-left: 1.25rem;
}
.service-price-note {
    max-width: 320px;
    white-space: normal;
}
.services-price-page {
    overflow: auto;
}
.services-price-page .services-shell,
.services-price-page .services-layout,
.services-price-page .services-table-card,
.services-price-page .services-table-card .card-body {
    height: auto;
    min-height: 0;
    overflow: visible;
}
.services-price-page .services-table-card {
    flex: none;
}
.services-price-page .service-price-report {
    overflow: visible;
}
@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
    }
    html,
    body.services-price-page {
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
    }
    body.services-price-page {
        display: block !important;
        color: #111 !important;
        font-family: Arial, Helvetica, sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body.services-price-page nav,
    body.services-price-page .services-hero,
    body.services-price-page .services-tabs,
    body.services-price-page .services-layout > .soft-card:not(.services-table-card),
    body.services-price-page .services-table-card > .card-header,
    body.services-price-page .services-modal,
    body.services-price-page script {
        display: none !important;
    }
    body.services-price-page .services-shell,
    body.services-price-page .services-layout,
    body.services-price-page .services-table-card,
    body.services-price-page .services-table-card .card-body {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
    }
    body.services-price-page .services-table-card {
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    body.services-price-page .services-table-card::before,
    body.services-price-page .services-table-card::after {
        display: none !important;
    }
    body.services-price-page #servicePricesExportArea {
        display: block !important;
        position: static !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
    }
    body.services-price-page .service-print-header {
        display: grid !important;
        grid-template-columns: 45% 1fr;
        align-items: stretch;
        gap: 7mm;
        padding: 0 0 6mm;
        margin: 0 0 5mm;
        border-bottom: 2px solid #12333e;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    body.services-price-page .service-print-logo {
        width: 38mm;
        height: 22mm;
        border: 1px solid #8aa9b2;
        border-radius: 3mm;
        background: #fff;
        color: #12333e;
        font-size: 13pt;
    }
    body.services-price-page .service-print-company strong {
        color: #12333e;
        font-size: 11pt;
        margin-bottom: 1.2mm;
    }
    body.services-price-page .service-print-company span,
    body.services-price-page .service-print-title p {
        color: #213f49;
        font-size: 8.4pt;
        line-height: 1.32;
    }
    body.services-price-page .service-print-title h2 {
        color: #12333e;
        font-size: 16pt;
        text-transform: uppercase;
        margin-bottom: 2.4mm;
    }
    body.services-price-page .service-print-title {
        border-left: 1px solid #c9d9de;
        padding-left: 7mm;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    body.services-price-page .service-price-report {
        overflow: visible !important;
        width: 100% !important;
    }
    body.services-price-page .service-price-report-table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        border-spacing: 0 !important;
        table-layout: fixed;
        font-size: 8.6pt;
        line-height: 1.25;
    }
    body.services-price-page .service-price-report-table th:nth-child(1),
    body.services-price-page .service-price-report-table td:nth-child(1) {
        width: 29%;
    }
    body.services-price-page .service-price-report-table th:nth-child(2),
    body.services-price-page .service-price-report-table td:nth-child(2) {
        width: 13%;
    }
    body.services-price-page .service-price-report-table th:nth-child(3),
    body.services-price-page .service-price-report-table td:nth-child(3),
    body.services-price-page .service-price-report-table th:nth-child(4),
    body.services-price-page .service-price-report-table td:nth-child(4) {
        width: 10%;
    }
    body.services-price-page .service-price-report-table th:nth-child(5),
    body.services-price-page .service-price-report-table td:nth-child(5) {
        width: 38%;
    }
    body.services-price-page .service-price-report-table thead {
        display: table-header-group;
    }
    body.services-price-page .service-price-report-table thead th {
        position: static !important;
        background: #12333e !important;
        color: #fff !important;
        border: 1px solid #12333e !important;
        font-size: 8.2pt;
        font-weight: 700;
        text-transform: uppercase;
    }
    body.services-price-page .service-price-report-table th,
    body.services-price-page .service-price-report-table td {
        padding: 5px 7px !important;
        border: 1px solid #d6e0e4 !important;
        overflow-wrap: anywhere;
    }
    body.services-price-page .service-price-report-table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    body.services-price-page .service-price-service-row td {
        background: #e4f1f4 !important;
        border-color: #a8ccd4 !important;
    }
    body.services-price-page .service-price-master {
        display: block;
    }
    body.services-price-page .service-price-master strong {
        color: #12333e;
        font-size: 9.6pt;
    }
    body.services-price-page .service-price-master span,
    body.services-price-page .service-price-empty,
    body.services-price-page .service-price-detail-empty td {
        color: #213f49;
        font-size: 8pt;
    }
    body.services-price-page .service-price-note {
        max-width: none;
    }
    body.services-price-page .service-price-actions,
    body.services-price-page .service-price-master .btn {
        display: none !important;
    }
    body.services-price-page #servicePricesExportArea::after {
        content: "Relatorio gerado pelo sistema Clinica Fisiolife";
        display: block;
        margin-top: 5mm;
        padding-top: 2mm;
        border-top: 1px solid #d6e0e4;
        color: #526b76;
        font-size: 7.6pt;
        text-align: right;
    }
}
@media (max-width: 991px) {
    html,
    body {
        overflow: auto;
    }
    .services-shell,
    .services-layout,
    .services-table-card,
    .services-table-card .card-body,
    .services-table-wrap {
        height: auto;
        min-height: auto;
        overflow: visible;
    }
    .service-price-master {
        align-items: flex-start;
        flex-direction: column;
    }
    .service-print-header {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body class="<?= $isPriceTab ? 'services-price-page' : 'services-list-page' ?>">

<?php include 'partials/menu.php'; ?>

<div class="container page-shell services-shell">
    <div class="services-layout">
    <section class="page-hero services-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h3 class="mb-2"><?= $isPriceTab ? 'Precos por servico' : 'Catalogo de servicos' ?></h3>
                <p><?= $isPriceTab ? 'Relatorio mestre-detalhe com cada servico e seus valores por plano.' : 'Lista central de servicos com filtro, duracao padrao, status e acesso separado para novo e editar.' ?></p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($isPriceTab): ?>
                    <button type="button" class="btn btn-light btn-sm rounded-pill px-3" onclick="window.print()">Imprimir relatorio</button>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" data-export-list onclick="appExportList('servicesExportArea', 'jpg', 'servicos')">Exportar JPG</button>
                    <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" data-export-list onclick="appExportList('servicesExportArea', 'pdf', 'servicos')">Exportar PDF</button>
                    <a class="btn btn-light btn-sm rounded-pill px-3" href="novo_servico.php">+ Novo servico</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="services-tabs">
        <a class="btn <?= $isPriceTab ? 'btn-outline-primary' : 'btn-primary' ?>" href="secretaria_servicos.php">Servicos</a>
        <a class="btn <?= $isPriceTab ? 'btn-primary' : 'btn-outline-primary' ?>" href="secretaria_servicos.php?tab=precos">Relatorio de precos</a>
    </div>

    <div class="soft-card card services-card">
        <div class="card-body">
            <form class="toolbar-grid" method="GET">
                <input type="hidden" name="tab" value="<?= $isPriceTab ? 'precos' : 'servicos' ?>">
                <div>
                    <label class="form-label small text-muted">Busca</label>
                    <input type="text" name="busca_servico" class="form-control" data-page-autofocus="1" value="<?= app_h($filters['busca_servico']) ?>" placeholder="Nome do servico">
                </div>
                <div class="d-flex align-items-end">
                    <button class="btn btn-primary w-100">Filtrar</button>
                </div>
                <div class="d-flex align-items-end">
                    <a href="secretaria_servicos.php<?= $isPriceTab ? '?tab=precos' : '' ?>" class="btn btn-outline-secondary w-100">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (!$isPriceTab): ?>
        <div class="soft-card card services-card services-table-card" id="servicesExportArea">
            <div class="card-header">
                <div class="panel-title">
                    <h5>Servicos cadastrados</h5>
                    <span class="text-muted small">novo, editar, listar e filtrar</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive services-table-wrap">
                    <table class="table table-soft align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Servico</th>
                            <th>Duracao</th>
                            <th>Agenda</th>
                            <th>Capacidade</th>
                            <th>Profissionais</th>
                            <th>Status</th>
                            <th class="text-end">Acoes</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($servicesRows as $service): ?>
                            <tr>
                                <td><?= app_h((string) $service['nome']) ?></td>
                                <td><?= (int) $service['tempo_minutos'] ?> min</td>
                                <td><?= ($service['tipo_agendamento'] ?? 'individual') === 'grupo' ? 'Grupo' : 'Individual' ?></td>
                                <td><?= (int) ($service['capacidade_agendamento'] ?? 1) ?></td>
                                <td><?= (int) $service['total_profissionais'] ?></td>
                                <td><?= (int) $service['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="editar_servico.php?<?= app_h(app_build_query(['id' => $service['id']])) ?>">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?= app_render_pagination($pagination) ?>
            </div>
        </div>
    <?php else: ?>
        <div class="soft-card card services-card services-table-card">
            <div class="card-header">
                <div class="panel-title">
                    <h5>Relatorio de precos</h5>
                    <span class="text-muted small">servico, plano e valor</span>
                </div>
            </div>
            <div class="card-body">
                <div class="service-prices-print-area" id="servicePricesExportArea">
                    <div class="service-print-header">
                        <div class="service-print-brand">
                            <div class="service-print-logo">
                                <?php if ($reportLogoSrc !== ''): ?>
                                    <img src="<?= app_h($reportLogoSrc) ?>" alt="Logotipo">
                                <?php else: ?>
                                    <span><?= app_h($reportInitials) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="service-print-company">
                                <strong><?= app_h($reportClinicName) ?></strong>
                                <?php if ($reportClinicLegalName !== ''): ?>
                                    <span><?= app_h($reportClinicLegalName) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($reportContacts)): ?>
                                    <span><?= app_h(implode(' | ', $reportContacts)) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($reportAddress)): ?>
                                    <span><?= app_h(implode(', ', $reportAddress)) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="service-print-title">
                            <h2>Relatorio de precos por servico</h2>
                            <p>Filtros: <?= app_h((string) $priceReportFilterText) ?></p>
                            <p>Servicos: <?= (int) ($priceReportTotals['servicos'] ?? 0) ?> | Precos: <?= (int) ($priceReportTotals['precos'] ?? 0) ?></p>
                            <p>Emissao: <?= app_h((string) $priceReportIssuedAt) ?></p>
                        </div>
                    </div>

                    <div class="service-price-report table-responsive">
                        <?php if (empty($priceReport)): ?>
                            <div class="service-price-empty">Nenhum servico encontrado para os filtros informados.</div>
                        <?php else: ?>
                            <table class="table table-soft service-price-report-table align-middle">
                                <thead>
                                <tr>
                                    <th>Plano</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Guia</th>
                                    <th>Observacoes</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($priceReport as $service): ?>
                                    <tr class="service-price-service-row">
                                        <td colspan="5">
                                            <div class="service-price-master">
                                                <div>
                                                    <strong><?= app_h((string) $service['nome']) ?></strong>
                                                    <span>
                                                        <?= (int) $service['tempo_minutos'] ?> min |
                                                        <?= ($service['tipo_agendamento'] ?? 'individual') === 'grupo' ? 'Grupo' : 'Individual' ?> |
                                                        <?= (int) $service['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                                    </span>
                                                </div>
                                                <a class="btn btn-sm btn-outline-primary" href="editar_servico.php?<?= app_h(app_build_query(['id' => $service['id'], 'tab' => 'precos'])) ?>">Abrir precos</a>
                                            </div>
                                        </td>
                                    </tr>

                                    <?php if (empty($service['precos'])): ?>
                                        <tr class="service-price-detail-empty">
                                            <td colspan="5">Sem preco cadastrado para este servico.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($service['precos'] as $price): ?>
                                            <tr>
                                                <td><?= app_h((string) ($price['plano_nome'] ?: 'Plano nao informado')) ?></td>
                                                <td><strong><?= app_money_br((float) $price['valor']) ?></strong></td>
                                                <td><?= (int) $price['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></td>
                                                <td><?= !empty($price['permite_alterar_guia']) ? 'Editavel' : 'Fixo' ?></td>
                                                <td class="service-price-note"><?= app_h((string) ($price['observacoes'] ?: '-')) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    </div>
</div>

<div class="modal fade services-modal" id="serviceFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Novo servico</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($serviceMessage)): ?>
                    <div class="alert alert-warning"><?= app_h($serviceMessage) ?></div>
                <?php endif; ?>

                <form method="POST" class="row g-3 services-form" id="serviceForm">
                    <input type="hidden" name="action" value="save_service">

                    <div class="col-12">
                        <label class="form-label small text-muted">Nome do servico</label>
                        <input type="text" name="nome" class="form-control" data-page-autofocus="1" value="<?= app_h((string) ($serviceFormValues['nome'] ?? '')) ?>" required title="Nome que aparecera na agenda e nos vinculos com profissionais.">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small text-muted">Duracao em minutos</label>
                        <input type="number" name="tempo_minutos" class="form-control" min="1" value="<?= (int) ($serviceFormValues['tempo_minutos'] ?? 50) ?>" required title="Tempo padrao usado para calcular o horario final dos atendimentos.">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small text-muted">Tipo de agenda</label>
                        <select name="tipo_agendamento" id="serviceScheduleTypeNew" class="form-select" title="Individual ocupa um horario por paciente. Grupo permite varios pacientes no mesmo horario.">
                            <option value="individual" <?= ($serviceFormValues['tipo_agendamento'] ?? 'individual') === 'individual' ? 'selected' : '' ?>>Individual</option>
                            <option value="grupo" <?= ($serviceFormValues['tipo_agendamento'] ?? 'individual') === 'grupo' ? 'selected' : '' ?>>Grupo</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small text-muted">Capacidade por horario</label>
                        <input type="number" name="capacidade_agendamento" id="serviceCapacityNew" class="form-control" min="1" value="<?= (int) ($serviceFormValues['capacidade_agendamento'] ?? 1) ?>" required title="Quantidade maxima de pacientes permitidos no mesmo horario quando o tipo for Grupo.">
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check ps-1 pb-2">
                            <input class="form-check-input" type="checkbox" name="ativo" id="serviceActiveNew" <?= !isset($serviceFormValues['ativo']) || (int) $serviceFormValues['ativo'] === 1 ? 'checked' : '' ?> title="Servico ativo fica disponivel para novos agendamentos e vinculos.">
                            <label class="form-check-label" for="serviceActiveNew">Servico ativo</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <span class="services-hint">Atalhos: <strong>Alt+S</strong> salvar | <strong>Esc</strong> cancelar</span>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar <span class="small text-muted">(Esc)</span></button>
                            <button class="btn btn-primary px-4">Salvar servico <span class="small">(Alt+S)</span></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="assets/list-export.js"></script>
<script>
document.addEventListener('keydown', function (event) {
    const modalElement = document.getElementById('serviceFormModal');
    const form = document.getElementById('serviceForm');

    if (!modalElement || !form || !modalElement.classList.contains('show')) return;

    if (event.altKey && event.key.toLowerCase() === 's') {
        event.preventDefault();
        form.requestSubmit();
    }
});

const serviceScheduleTypeNew = document.getElementById('serviceScheduleTypeNew');
const serviceCapacityNew = document.getElementById('serviceCapacityNew');
function syncServiceCapacityNew() {
    if (!serviceScheduleTypeNew || !serviceCapacityNew) return;
    if (serviceScheduleTypeNew.value === 'individual') {
        serviceCapacityNew.value = '1';
        serviceCapacityNew.readOnly = true;
    } else {
        serviceCapacityNew.readOnly = false;
    }
}
serviceScheduleTypeNew?.addEventListener('change', syncServiceCapacityNew);
syncServiceCapacityNew();
</script>

<?php if (!empty($autoOpenServiceModal)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('serviceFormModal');
    if (!modalElement || typeof bootstrap === 'undefined') return;
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
});
</script>
<?php endif; ?>

</body>
</html>
