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

$clinicId = app_active_clinic_id();
$id = app_request_method() === 'POST' ? app_post_int('id') : app_query_int('id');
$returnTo = app_safe_guide_return_to(
    app_request_method() === 'POST'
        ? (app_request_post('return_to', '') ?? '')
        : (app_request_query('return_to', '') ?? '')
);

$guia = app_stmt_one(
    $conn,
    'SELECT g.id, g.codigo, g.valor_guia, g.recebido, g.total_sessoes, g.data,
            p.nome AS paciente_nome,
            pr.nome AS profissional_nome,
            pl.valor_sessao
     FROM guias g
     LEFT JOIN pacientes p ON p.id = g.paciente_id AND p.clinica_id = g.clinica_id
     LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
     LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
     WHERE g.clinica_id = ? AND g.id = ?
     LIMIT 1',
    'ii',
    [$clinicId, $id]
);

if (!$guia) {
    app_flash('danger', 'Guia nao encontrada.');
    app_redirect($returnTo !== '' ? $returnTo : 'guias.php');
}

$valorGuia = (float) $guia['valor_guia'];
$recebido = (float) $guia['recebido'];
$saldo = max(0, $valorGuia - $recebido);
$totalSessoes = max(1, (int) $guia['total_sessoes']);
$valorSessao = (float) ($guia['valor_sessao'] ?? 0);

if ($valorSessao <= 0 && $valorGuia > 0) {
    $valorSessao = $valorGuia / $totalSessoes;
}

$defaultRedirect = 'guias.php?' . app_build_query([
    'guia' => (string) $guia['codigo'],
    'filtrar' => 1,
]);
$returnTo = $returnTo !== '' ? $returnTo : $defaultRedirect;
$message = '';
$valorBaixaInput = number_format($saldo, 2, ',', '.');

if ($saldo <= 0) {
    app_flash('warning', 'Esta guia ja esta paga.');
    app_redirect($returnTo);
}

if (app_request_method() === 'POST') {
    $valorBaixaInput = (string) ($_POST['valor_baixa'] ?? '');
    $valorBaixa = app_parse_money($valorBaixaInput);

    if ($valorBaixa <= 0) {
        $message = 'Informe um valor maior que zero.';
    } elseif ($valorBaixa - $saldo > 0.01) {
        $message = 'O valor da baixa nao pode ser maior que o saldo em aberto.';
    } else {
        $ok = app_stmt_execute(
            $conn,
            'UPDATE guias SET recebido = LEAST(valor_guia, recebido + ?) WHERE clinica_id = ? AND id = ?',
            'dii',
            [$valorBaixa, $clinicId, $id]
        );

        if ($ok) {
            $atendimentosPagos = $valorSessao > 0 ? (int) floor(($valorBaixa + 0.0001) / $valorSessao) : 0;
            app_flash('success', 'Baixa registrada: ' . app_money_br($valorBaixa) . ' em ' . $atendimentosPagos . ' atendimento(s).');
            app_redirect($returnTo);
        }

        $message = 'Nao foi possivel registrar a baixa.';
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
            <p>Informe o valor recebido. O saldo em aberto ja vem preenchido.</p>
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
                    <span>Saldo em aberto</span>
                    <strong><?= app_money_br($saldo) ?></strong>
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
                           autocomplete="off"
                           required
                           title="Valor recebido nesta baixa. Se for parcial, digite o valor menor.">
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

function atualizarCalculoBaixa() {
    if (!valorBaixa || !baixaCalculo) return;

    const valor = parseMoneyBr(valorBaixa.value);
    const saldo = Number(valorBaixa.dataset.saldo || 0);
    const valorSessao = Number(valorBaixa.dataset.valorSessao || 0);
    const atendimentos = valorSessao > 0 ? Math.floor((valor + 0.0001) / valorSessao) : 0;
    const restante = Math.max(0, saldo - valor);

    baixaCalculo.textContent = 'Com este valor, ' + atendimentos + ' atendimento(s) ficam pagos. Saldo restante: ' + formatMoneyBr(restante) + '.';
}

valorBaixa?.addEventListener('input', atualizarCalculoBaixa);
atualizarCalculoBaixa();
</script>
</body>
</html>
