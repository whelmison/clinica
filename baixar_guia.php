<?php
include 'config/db.php';

function app_safe_guide_return_to(?string $returnTo): string
{
    $returnTo = trim((string) $returnTo);

    if ($returnTo === '') {
        return '';
    }

    $parts = parse_url($returnTo);

    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
        return '';
    }

    $path = ltrim((string) ($parts['path'] ?? ''), '/');

    if ($path !== 'guias.php' && $path !== 'clinica_fisiolife/guias.php') {
        return '';
    }

    $query = isset($parts['query']) && (string) $parts['query'] !== '' ? '?' . $parts['query'] : '';

    return 'guias.php' . $query;
}

function app_guide_money_cents(float $value): int
{
    return (int) round($value * 100);
}

$clinicId = app_active_clinic_id();
$id = app_request_method() === 'POST' ? app_post_int('id') : app_query_int('id');
$returnTo = app_safe_guide_return_to(
    app_request_method() === 'POST'
        ? (app_request_post('return_to', '') ?? '')
        : (app_request_query('return_to', '') ?? '')
);

$guia = app_stmt_one(
    $conn,
    'SELECT g.id, g.codigo, g.valor_guia, g.recebido, g.total_sessoes, g.data, g.autorizada, g.status_operacional,
            p.nome AS paciente_nome,
            pr.nome AS profissional_nome,
            pl.valor_sessao,
            COALESCE(a.usadas, 0) AS usadas,
            COALESCE(a.glosas, 0) AS glosas
     FROM guias g
     LEFT JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
     LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
     LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
     LEFT JOIN (
        SELECT guia_id,
               COUNT(*) AS usadas,
               SUM(CASE WHEN status_atendimento = "Glosado" THEN 1 ELSE 0 END) AS glosas
        FROM atendimentos
        WHERE clinica_id = ?
        GROUP BY guia_id
     ) a ON a.guia_id = g.id
     WHERE g.clinica_id = ? AND g.id = ?
     LIMIT 1',
    'iii',
    [$clinicId, $clinicId, $id]
);

if (!$guia) {
    app_flash('danger', 'Guia nao encontrada.');
    app_redirect($returnTo !== '' ? $returnTo : 'guias.php');
}

$valorGuia = (float) $guia['valor_guia'];
$recebido = (float) $guia['recebido'];
$totalSessoes = max(1, (int) $guia['total_sessoes']);
$valorSessao = (float) ($guia['valor_sessao'] ?? 0);

if ($valorSessao <= 0 && $valorGuia > 0) {
    $valorSessao = $valorGuia / $totalSessoes;
}

$usadas = (int) ($guia['usadas'] ?? 0);
$glosas = (int) ($guia['glosas'] ?? 0);
$faturadas = max(0, $usadas - $glosas);
$statusData = app_guide_operational_status_data([
    ...$guia,
    'usadas' => $usadas,
    'total_atendimentos' => $usadas,
]);
$statusGuia = (string) $statusData['value'];
$statusLabel = (string) $statusData['label'];
$canReceiveStatuses = ['em_uso', 'ultimas_sessoes', 'finalizada'];
$isFinalizedGuide = $statusGuia === 'finalizada';

$valorGuiaCents = app_guide_money_cents($valorGuia);
$recebidoCents = app_guide_money_cents($recebido);
$valorSessaoCents = app_guide_money_cents($valorSessao);
$valorFaturadoCents = $valorSessaoCents > 0 ? min($valorGuiaCents, $faturadas * $valorSessaoCents) : 0;
$limiteRecebivelCents = min($valorGuiaCents, $valorFaturadoCents);
$saldoRecebivelCents = max(0, $limiteRecebivelCents - $recebidoCents);
$saldoGuiaCents = max(0, $valorGuiaCents - $recebidoCents);

$valorFaturado = $valorFaturadoCents / 100;
$limiteRecebivel = $limiteRecebivelCents / 100;
$saldo = $saldoRecebivelCents / 100;
$saldoGuia = $saldoGuiaCents / 100;

$defaultRedirect = 'guias.php?' . app_build_query([
    'guia' => (string) $guia['codigo'],
    'filtrar' => 1,
]);
$returnTo = $returnTo !== '' ? $returnTo : $defaultRedirect;
$message = '';
$valorBaixaInput = number_format($saldo, 2, ',', '.');

if (!in_array($statusGuia, $canReceiveStatuses, true)) {
    app_flash('warning', 'A baixa so pode ser feita a partir do status Em uso.');
    app_redirect($returnTo);
}

if ($valorSessaoCents <= 0) {
    app_flash('warning', 'Informe o valor do atendimento no plano antes de dar baixa na guia.');
    app_redirect($returnTo);
}

if ($saldoGuiaCents <= 0) {
    app_flash('warning', 'Esta guia ja esta paga.');
    app_redirect($returnTo);
}

if ($saldoRecebivelCents <= 0) {
    app_flash('warning', 'Nao ha valor faturado disponivel para nova baixa nesta guia.');
    app_redirect($returnTo);
}

if (app_request_method() === 'POST') {
    $valorBaixaInput = (string) ($_POST['valor_baixa'] ?? '');
    $valorBaixa = app_parse_money($valorBaixaInput);
    $valorBaixaCents = app_guide_money_cents($valorBaixa);
    $novoRecebidoCents = $recebidoCents + $valorBaixaCents;

    if ($valorBaixaCents <= 0) {
        $message = 'Informe um valor maior que zero.';
    } elseif ($valorBaixaCents % $valorSessaoCents !== 0) {
        $message = 'O valor da baixa precisa ser multiplo do valor do atendimento (' . app_money_br($valorSessao) . ').';
    } elseif ($valorBaixaCents > $saldoRecebivelCents) {
        $message = 'O valor da baixa nao pode passar do valor faturado disponivel: ' . app_money_br($saldo) . '.';
    } elseif ($novoRecebidoCents > $valorGuiaCents) {
        $message = 'O valor recebido nunca pode ser maior que o valor da guia.';
    } elseif ($novoRecebidoCents > $valorFaturadoCents) {
        $message = 'O valor recebido nunca pode ser maior que o valor faturado.';
    } elseif (!$isFinalizedGuide && $novoRecebidoCents >= $valorGuiaCents) {
        $message = 'A baixa total so pode ser feita quando a guia estiver finalizada.';
    } else {
        $stmt = $conn->prepare(
            'UPDATE guias
             SET recebido = recebido + ?
             WHERE clinica_id = ?
               AND id = ?
               AND ROUND((recebido + ?) * 100) <= ?
               AND ROUND((recebido + ?) * 100) <= ?'
        );
        $updatedRows = 0;
        $ok = false;

        if ($stmt) {
            $stmt->bind_param('diididi', $valorBaixa, $clinicId, $id, $valorBaixa, $limiteRecebivelCents, $valorBaixa, $valorGuiaCents);
            $ok = $stmt->execute();
            $updatedRows = $stmt->affected_rows;
            $stmt->close();
        }

        if ($ok && $updatedRows > 0) {
            $atendimentosPagos = (int) ($valorBaixaCents / $valorSessaoCents);
            app_flash('success', 'Baixa registrada: ' . app_money_br($valorBaixa) . ' em ' . $atendimentosPagos . ' atendimento(s).');
            app_redirect($returnTo);
        }

        $message = 'Nao foi possivel registrar a baixa. Atualize a guia e confira o saldo faturado.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Baixar guia</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
body {
    background:
        radial-gradient(circle at 8% 4%, rgba(226, 244, 239, 0.9), transparent 28%),
        linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
}
.receipt-shell {
    max-width: 760px;
    padding-top: 1rem;
}
.receipt-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 18px 36px rgba(18, 51, 62, 0.12);
}
.receipt-head {
    padding: 0.85rem 1rem;
    border-radius: 18px 18px 0 0;
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
    color: #fff;
}
.receipt-head h3 {
    margin: 0;
    font-size: 1.05rem;
}
.receipt-head p {
    margin: 0.18rem 0 0;
    font-size: 0.76rem;
    opacity: 0.86;
}
.receipt-body {
    padding: 1rem;
}
.receipt-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.65rem;
}
.receipt-info {
    padding: 0.62rem 0.72rem;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 14px;
    background: #f7fbfc;
}
.receipt-info span {
    display: block;
    color: #6b8591;
    font-size: 0.68rem;
    font-weight: 800;
    text-transform: uppercase;
}
.receipt-info strong {
    color: #16333f;
    font-size: 0.88rem;
}
.receipt-calc {
    border-radius: 14px;
    background: rgba(31, 122, 140, 0.09);
    color: #143b49;
    font-size: 0.82rem;
}
@media (max-width: 700px) {
    .receipt-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<?php include 'partials/menu.php'; ?>

<div class="container receipt-shell">
    <div class="receipt-card">
        <div class="receipt-head">
            <h3>Baixar guia <?= app_h((string) $guia['codigo']) ?></h3>
            <p>Informe o valor recebido. Antes de finalizar a guia, a baixa deve ser parcial e limitada ao valor ja faturado.</p>
        </div>
        <div class="receipt-body">
            <?php if ($message !== ''): ?>
                <div class="alert alert-warning"><?= app_h($message) ?></div>
            <?php endif; ?>

            <div class="receipt-grid mb-3">
                <div class="receipt-info">
                    <span>Paciente</span>
                    <strong><?= app_h((string) ($guia['paciente_nome'] ?? '-')) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Profissional</span>
                    <strong><?= app_h((string) ($guia['profissional_nome'] ?? '-')) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Valor da guia</span>
                    <strong><?= app_money_br($valorGuia) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Status da guia</span>
                    <strong><?= app_h($statusLabel) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Valor do atendimento</span>
                    <strong><?= app_money_br($valorSessao) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Atendimentos faturados</span>
                    <strong><?= (int) $faturadas ?> de <?= (int) $totalSessoes ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Valor faturado</span>
                    <strong><?= app_money_br($valorFaturado) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Ja recebido</span>
                    <strong><?= app_money_br($recebido) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Disponivel para baixa</span>
                    <strong><?= app_money_br($saldo) ?></strong>
                </div>
                <div class="receipt-info">
                    <span>Saldo total da guia</span>
                    <strong><?= app_money_br($saldoGuia) ?></strong>
                </div>
            </div>

            <form method="POST" id="receiptForm">
                <input type="hidden" name="id" value="<?= (int) $guia['id'] ?>">
                <input type="hidden" name="return_to" value="<?= app_h($returnTo) ?>">

                <div class="mb-3">
                    <label class="form-label small text-muted">Valor da baixa</label>
                    <input type="text"
                           name="valor_baixa"
                           id="valorBaixa"
                           class="form-control form-control-lg"
                           value="<?= app_h($valorBaixaInput) ?>"
                           data-valor-sessao="<?= app_h((string) $valorSessao) ?>"
                           data-saldo="<?= app_h((string) $saldo) ?>"
                           data-saldo-guia="<?= app_h((string) $saldoGuia) ?>"
                           data-valor-guia="<?= app_h((string) $valorGuia) ?>"
                           data-recebido="<?= app_h((string) $recebido) ?>"
                           data-finalizada="<?= $isFinalizedGuide ? 1 : 0 ?>"
                           autocomplete="off"
                           required
                           title="Valor recebido nesta baixa. Precisa ser multiplo do valor do atendimento.">
                </div>

                <div class="receipt-calc p-3 mb-3" id="baixaCalculo"></div>

                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <a href="<?= app_h($returnTo) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4">Confirmar baixa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const valorBaixa = document.getElementById('valorBaixa');
const baixaCalculo = document.getElementById('baixaCalculo');

function parseMoneyBr(value) {
    return Number(String(value || '')
        .replace(/[^\d,.-]/g, '')
        .replace(/\./g, '')
        .replace(',', '.')) || 0;
}

function formatMoneyBr(value) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function moneyCents(value) {
    return Math.round((Number(value) || 0) * 100);
}

function atualizarCalculoBaixa() {
    if (!valorBaixa || !baixaCalculo) return;

    const valor = parseMoneyBr(valorBaixa.value);
    const saldo = Number(valorBaixa.dataset.saldo || 0);
    const saldoGuia = Number(valorBaixa.dataset.saldoGuia || 0);
    const valorSessao = Number(valorBaixa.dataset.valorSessao || 0);
    const finalizada = valorBaixa.dataset.finalizada === '1';
    const valorCents = moneyCents(valor);
    const saldoCents = moneyCents(saldo);
    const saldoGuiaCents = moneyCents(saldoGuia);
    const valorSessaoCents = moneyCents(valorSessao);
    const atendimentos = valorSessaoCents > 0 ? Math.floor(valorCents / valorSessaoCents) : 0;
    const restante = Math.max(0, saldo - valor);
    const avisos = [];

    if (valorCents > 0 && valorSessaoCents > 0 && valorCents % valorSessaoCents !== 0) {
        avisos.push('O valor precisa ser multiplo de ' + formatMoneyBr(valorSessao) + '.');
    }

    if (valorCents > saldoCents) {
        avisos.push('O valor passa do saldo faturado disponivel.');
    }

    if (!finalizada && valorCents >= saldoGuiaCents) {
        avisos.push('Baixa total so depois que a guia estiver finalizada.');
    }

    baixaCalculo.innerHTML = 'Com este valor, <strong>' + atendimentos + '</strong> atendimento(s) ficam pagos. Saldo faturado restante: <strong>' + formatMoneyBr(restante) + '</strong>.' +
        (avisos.length ? '<div class="text-danger fw-semibold mt-2">' + avisos.join(' ') + '</div>' : '');
}

valorBaixa?.addEventListener('input', atualizarCalculoBaixa);
atualizarCalculoBaixa();
</script>
</body>
</html>
