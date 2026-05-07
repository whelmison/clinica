<?php
include 'config/db.php';

use Clinic\Repositories\ReportRepository;

if (!function_exists('agenda_gerencial_slug')) {
    function agenda_gerencial_slug(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? 'relatorio';

        return trim($normalized, '-') ?: 'relatorio';
    }
}

$pdo = app_pdo();
$reportRepository = new ReportRepository($pdo);
$options = $reportRepository->filters();
$professionals = $options['professionals'] ?? [];
$selectedProfessionalId = max(0, app_query_int('profissional_id'));
$allowedProfessionalIds = array_map(static fn (array $professional): int => (int) $professional['id'], $professionals);

if ($selectedProfessionalId > 0 && !in_array($selectedProfessionalId, $allowedProfessionalIds, true)) {
    $selectedProfessionalId = 0;
}

$selectedPeriod = app_request_query('periodo', 'week') ?? 'week';
$referenceDate = app_request_query('data_referencia', date('Y-m-d')) ?? date('Y-m-d');
$reportRange = app_schedule_report_range($selectedPeriod, $referenceDate);
$periodOptions = app_schedule_report_periods();
$selectedProfessional = null;

foreach ($professionals as $professional) {
    if ((int) $professional['id'] === $selectedProfessionalId) {
        $selectedProfessional = $professional;
        break;
    }
}

$overview = $reportRepository->managerialScheduleOverview(
    $reportRange['start_date'],
    $reportRange['end_date'],
    $selectedProfessionalId > 0 ? $selectedProfessionalId : null
);
$ranking = $reportRepository->managerialScheduleByProfessional(
    $reportRange['start_date'],
    $reportRange['end_date'],
    $selectedProfessionalId > 0 ? $selectedProfessionalId : null
);
$topAttendance = $ranking[0] ?? null;
$rankingByCancellation = $ranking;

usort($rankingByCancellation, static function (array $left, array $right): int {
    $cancelCompare = (int) $right['total_cancelado'] <=> (int) $left['total_cancelado'];

    if ($cancelCompare !== 0) {
        return $cancelCompare;
    }

    $attendanceCompare = (int) $right['total_realizado'] <=> (int) $left['total_realizado'];

    if ($attendanceCompare !== 0) {
        return $attendanceCompare;
    }

    return strcmp((string) $left['profissional_nome'], (string) $right['profissional_nome']);
});

$topCancellation = $rankingByCancellation[0] ?? null;
$selectedProfessionalLabel = $selectedProfessional['nome'] ?? 'Todos os profissionais';
$periodLabel = $periodOptions[$reportRange['period']] ?? 'Semanal';
$generatedAt = date('d/m/Y H:i');
$reportTitle = 'Relatorio gerencial da agenda';
$reportSubtitle = $periodLabel . ' | ' . $reportRange['label'] . ' | ' . $selectedProfessionalLabel;
$fileBaseName = agenda_gerencial_slug(
    'relatorio-gerencial-agenda-' . $reportRange['period'] . '-' . $reportRange['reference_date'] . '-' . $selectedProfessionalLabel
);
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatorio gerencial da agenda</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<link href="assets/agenda-gerencial.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container agenda-gerencial-page">
    <div class="agenda-gerencial-shell">
        <section class="agenda-gerencial-panel agenda-gerencial-hero">
            <div>
                <h1><?= app_h($reportTitle) ?></h1>
                <p>Resumo executivo da agenda com volume de atendimentos, cancelamentos, pendencias e ranking por profissional.</p>
                <p class="agenda-gerencial-meta mt-2">Periodo: <?= app_h($reportSubtitle) ?> | Gerado em <?= app_h($generatedAt) ?></p>
            </div>
            <div class="agenda-gerencial-actions">
                <a class="btn btn-outline-secondary" href="secretaria_agenda.php">Voltar para agenda</a>
                <button type="button" class="btn btn-outline-dark" data-report-action="print">Imprimir</button>
                <button type="button" class="btn btn-outline-primary" data-report-action="pdf">Exportar PDF</button>
                <button type="button" class="btn btn-outline-primary" data-report-action="jpg">Exportar JPG</button>
                <button type="button" class="btn btn-success" data-report-action="whatsapp-pdf">WhatsApp PDF</button>
                <button type="button" class="btn btn-success" data-report-action="whatsapp-jpg">WhatsApp JPG</button>
            </div>
        </section>

        <section class="agenda-gerencial-panel agenda-gerencial-filters">
            <form method="GET">
                <div class="agenda-gerencial-filter-grid">
                    <div>
                        <label for="periodo">Visao</label>
                        <select name="periodo" id="periodo" class="form-select">
                            <?php foreach ($periodOptions as $value => $label): ?>
                                <option value="<?= app_h($value) ?>" <?= $reportRange['period'] === $value ? 'selected' : '' ?>><?= app_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="dataReferencia">Data de referencia</label>
                        <input type="date" name="data_referencia" id="dataReferencia" class="form-control" value="<?= app_h($reportRange['reference_date']) ?>">
                    </div>
                    <div>
                        <label for="profissionalId">Profissional</label>
                        <select name="profissional_id" id="profissionalId" class="form-select">
                            <option value="0">Todos os profissionais</option>
                            <?php foreach ($professionals as $professional): ?>
                                <option value="<?= (int) $professional['id'] ?>" <?= $selectedProfessionalId === (int) $professional['id'] ? 'selected' : '' ?>>
                                    <?= app_h($professional['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="agenda-gerencial-filter-actions">
                        <button class="btn btn-primary flex-fill" type="submit">Atualizar relatorio</button>
                        <a class="btn btn-outline-secondary flex-fill" href="agenda_relatorio_gerencial.php">Limpar</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="agenda-gerencial-panel">
            <div class="agenda-gerencial-capture" id="agendaGerencialCapture">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h4 mb-1"><?= app_h($reportTitle) ?></h2>
                        <p class="text-muted mb-0"><?= app_h($reportSubtitle) ?></p>
                    </div>
                    <div class="text-lg-end">
                        <div class="fw-semibold text-dark">Filtro atual</div>
                        <div class="text-muted"><?= app_h($selectedProfessionalLabel) ?></div>
                        <div class="text-muted"><?= app_h($generatedAt) ?></div>
                    </div>
                </div>

                <div class="agenda-gerencial-summary">
                    <article class="agenda-gerencial-metric metric-realizado">
                        <small>Atendidos</small>
                        <strong><?= (int) ($overview['total_realizado'] ?? 0) ?></strong>
                        <p>Agendamentos marcados como realizado no periodo selecionado.</p>
                    </article>
                    <article class="agenda-gerencial-metric metric-cancelado">
                        <small>Cancelados</small>
                        <strong><?= (int) ($overview['total_cancelado'] ?? 0) ?></strong>
                        <p>Quantidade total de horarios cancelados dentro da visao atual.</p>
                    </article>
                    <article class="agenda-gerencial-metric metric-nao-realizado">
                        <small>Agendado e nao realizado</small>
                        <strong><?= (int) ($overview['total_nao_realizado'] ?? 0) ?></strong>
                        <p>
                            <?= (int) ($overview['total_agendado'] ?? 0) ?> agendado(s) e
                            <?= (int) ($overview['total_confirmado'] ?? 0) ?> confirmado(s).
                        </p>
                    </article>
                    <article class="agenda-gerencial-metric metric-total">
                        <small>Total no periodo</small>
                        <strong><?= (int) ($overview['total_registros'] ?? 0) ?></strong>
                        <p>Todos os status da agenda dentro do recorte selecionado.</p>
                    </article>
                </div>

                <div class="agenda-gerencial-highlights">
                    <article class="agenda-gerencial-highlight">
                        <span>Mais atendimentos</span>
                        <strong><?= app_h($topAttendance['profissional_nome'] ?? 'Sem registros no periodo') ?></strong>
                        <p>
                            <?= isset($topAttendance['total_realizado']) ? (int) $topAttendance['total_realizado'] . ' atendimento(s) realizado(s).' : 'Nenhum atendimento realizado no periodo filtrado.' ?>
                        </p>
                    </article>
                    <article class="agenda-gerencial-highlight">
                        <span>Mais cancelamentos</span>
                        <strong><?= app_h($topCancellation['profissional_nome'] ?? 'Sem registros no periodo') ?></strong>
                        <p>
                            <?= isset($topCancellation['total_cancelado']) ? (int) $topCancellation['total_cancelado'] . ' cancelamento(s) no periodo.' : 'Nenhum cancelamento encontrado no periodo filtrado.' ?>
                        </p>
                    </article>
                </div>

                <div class="agenda-gerencial-table-card">
                    <div class="agenda-gerencial-table-head">
                        <div>
                            <h2>Ranking por profissional</h2>
                            <p>Ordenado por quantidade de atendimentos realizados.</p>
                        </div>
                        <span class="badge text-bg-light"><?= count($ranking) ?> profissional(is)</span>
                    </div>

                    <?php if ($ranking !== []): ?>
                        <div class="table-responsive">
                            <table class="table agenda-gerencial-table align-middle">
                                <thead>
                                <tr>
                                    <th>Posicao</th>
                                    <th>Profissional</th>
                                    <th>Atendidos</th>
                                    <th>Cancelados</th>
                                    <th>Agendados</th>
                                    <th>Confirmados</th>
                                    <th>Nao realizados</th>
                                    <th>Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($ranking as $index => $row): ?>
                                    <tr>
                                        <td><span class="agenda-gerencial-rank"><?= $index + 1 ?>o</span></td>
                                        <td class="fw-semibold"><?= app_h($row['profissional_nome']) ?></td>
                                        <td><?= (int) $row['total_realizado'] ?></td>
                                        <td><?= (int) $row['total_cancelado'] ?></td>
                                        <td><?= (int) $row['total_agendado'] ?></td>
                                        <td><?= (int) $row['total_confirmado'] ?></td>
                                        <td><?= (int) $row['total_nao_realizado'] ?></td>
                                        <td><?= (int) $row['total_registros'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="agenda-gerencial-empty">
                            Nenhum agendamento encontrado para este filtro.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
</div>

<div class="agenda-gerencial-notice" id="agendaGerencialNotice" hidden></div>

<script>
window.agendaGerencialConfig = <?= json_encode([
    'captureTargetId' => 'agendaGerencialCapture',
    'noticeId' => 'agendaGerencialNotice',
    'reportTitle' => $reportTitle,
    'fileBaseName' => $fileBaseName,
    'whatsappShareText' => $reportTitle . ' | ' . $reportSubtitle,
], $jsonFlags) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<script src="assets/agenda-gerencial.js"></script>

</body>
</html>
