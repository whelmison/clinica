<?php
include 'config/db.php';

$today = date('Y-m-d');
$currentUser = app_current_user() ?? [];
$userName = app_first_name($currentUser['nome_exibicao'] ?? 'Administrativo');
$clinicId = app_active_clinic_id();

$totalProfessionals = (int) ($conn->query("SELECT COUNT(*) AS total FROM profissionais WHERE clinica_id = {$clinicId}")->fetch_assoc()['total'] ?? 0);
$totalUsers = (int) ($conn->query("SELECT COUNT(*) AS total FROM usuarios WHERE clinica_id = {$clinicId}")->fetch_assoc()['total'] ?? 0);
$totalReceivable = (float) ($conn->query("SELECT COALESCE(SUM(valor), 0) AS total FROM contas_receber WHERE clinica_id = {$clinicId} AND status <> 'pago'")->fetch_assoc()['total'] ?? 0);
$totalPayable = (float) ($conn->query("SELECT COALESCE(SUM(valor), 0) AS total FROM contas_pagar WHERE clinica_id = {$clinicId} AND status <> 'pago'")->fetch_assoc()['total'] ?? 0);
$openBatches = (int) ($conn->query("SELECT COUNT(*) AS total FROM lotes WHERE clinica_id = {$clinicId} AND status IN ('aberto', 'enviado')")->fetch_assoc()['total'] ?? 0);
$activeGuides = (int) ($conn->query("SELECT COUNT(*) AS total FROM guias WHERE clinica_id = {$clinicId}")->fetch_assoc()['total'] ?? 0);
$balancePreview = $totalReceivable - $totalPayable;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Administrativo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
.admin-dashboard {
    padding: .75rem .9rem 1.25rem;
}

.admin-shell {
    display: grid;
    gap: .75rem;
    max-width: 1480px;
    margin: 0 auto;
}

.admin-header,
.admin-card {
    background: rgba(255, 255, 255, .94);
    border: 1px solid rgba(14, 116, 144, .12);
    border-radius: 8px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .07);
}

.admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .8rem;
    padding: .85rem 1rem;
}

.admin-title h1 {
    color: #0f172a;
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: -.03em;
    margin: 0;
}

.admin-title p {
    color: #64748b;
    font-size: .82rem;
    margin: .14rem 0 0;
}

.admin-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    justify-content: flex-end;
}

.admin-actions .btn,
.admin-action-link {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    font-size: .82rem;
    font-weight: 700;
    gap: .35rem;
    min-height: 34px;
    padding: .4rem .7rem;
}

.admin-date {
    background: #ecfeff;
    border: 1px solid rgba(14, 116, 144, .16);
    border-radius: 8px;
    color: #0f766e;
    font-size: .78rem;
    font-weight: 800;
    padding: .38rem .62rem;
    white-space: nowrap;
}

.admin-metrics {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.admin-metric {
    background: rgba(255, 255, 255, .95);
    border: 1px solid rgba(14, 116, 144, .12);
    border-left: 4px solid #0f766e;
    border-radius: 8px;
    box-shadow: 0 8px 18px rgba(15, 23, 42, .055);
    min-height: 82px;
    padding: .7rem .75rem;
}

.admin-metric:nth-child(2) {
    border-left-color: #0284c7;
}

.admin-metric:nth-child(3) {
    border-left-color: #16a34a;
}

.admin-metric:nth-child(4) {
    border-left-color: #ca8a04;
}

.admin-metric:nth-child(5) {
    border-left-color: #475569;
}

.admin-metric:nth-child(6) {
    border-left-color: #0e7490;
}

.admin-metric span {
    color: #64748b;
    display: block;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.admin-metric strong {
    color: #0f172a;
    display: block;
    font-size: 1.4rem;
    font-weight: 900;
    line-height: 1;
    margin-top: .38rem;
}

.admin-metric small {
    color: #64748b;
    display: block;
    font-size: .72rem;
    margin-top: .32rem;
}

.admin-workspace {
    display: grid;
    gap: .75rem;
    grid-template-columns: minmax(0, 1.55fr) minmax(300px, .75fr);
}

.admin-card {
    min-width: 0;
    overflow: hidden;
}

.admin-card-header {
    align-items: center;
    border-bottom: 1px solid rgba(148, 163, 184, .22);
    display: flex;
    justify-content: space-between;
    gap: .65rem;
    padding: .72rem .85rem;
}

.admin-card-header h2 {
    color: #0f172a;
    font-size: .96rem;
    font-weight: 850;
    margin: 0;
}

.admin-card-header span {
    color: #64748b;
    font-size: .76rem;
    font-weight: 700;
}

.admin-table {
    margin: 0;
}

.admin-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: .7rem;
    font-weight: 850;
    letter-spacing: .05em;
    padding: .55rem .7rem;
    text-transform: uppercase;
}

.admin-table td {
    color: #334155;
    font-size: .82rem;
    padding: .55rem .7rem;
    vertical-align: middle;
}

.admin-table tbody tr {
    cursor: pointer;
    transition: background-color .16s ease;
}

.admin-table tbody tr:hover {
    background: #ecfeff;
}

.admin-main-text {
    color: #0f172a;
    display: block;
    font-weight: 850;
}

.admin-subtext {
    color: #64748b;
    display: block;
    font-size: .72rem;
    margin-top: .08rem;
}

.status-pill {
    border-radius: 999px;
    display: inline-flex;
    font-size: .68rem;
    font-weight: 850;
    line-height: 1;
    padding: .32rem .5rem;
    white-space: nowrap;
}

.status-pill.is-success {
    background: #dcfce7;
    color: #15803d;
}

.status-pill.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.status-pill.is-warning {
    background: #fef3c7;
    color: #92400e;
}

.status-pill.is-muted {
    background: #f1f5f9;
    color: #475569;
}

.admin-side {
    display: grid;
    gap: .75rem;
}

.admin-quick-grid {
    display: grid;
    gap: .5rem;
    padding: .75rem;
}

.admin-action-link {
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, .22);
    color: #0f172a;
    justify-content: space-between;
    text-decoration: none;
    transition: background-color .16s ease, border-color .16s ease, color .16s ease;
}

.admin-action-link:hover {
    background: #ecfeff;
    border-color: rgba(14, 116, 144, .28);
    color: #0f766e;
}

.admin-alert-list {
    display: grid;
    gap: .5rem;
    padding: .75rem;
}

.admin-alert {
    align-items: center;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, .2);
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    gap: .7rem;
    padding: .58rem .65rem;
}

.admin-alert strong {
    color: #0f172a;
    display: block;
    font-size: .82rem;
}

.admin-alert span {
    color: #64748b;
    display: block;
    font-size: .72rem;
}

.admin-alert b {
    align-items: center;
    background: #0f766e;
    border-radius: 999px;
    color: #fff;
    display: inline-flex;
    font-size: .78rem;
    justify-content: center;
    min-width: 34px;
    padding: .25rem .5rem;
}

@media (max-width: 1180px) {
    .admin-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .admin-workspace {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .admin-dashboard {
        padding: .6rem;
    }

    .admin-header {
        align-items: stretch;
        flex-direction: column;
    }

    .admin-actions {
        justify-content: flex-start;
    }

    .admin-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-table th:nth-child(3),
    .admin-table td:nth-child(3) {
        display: none;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<main class="admin-dashboard">
    <div class="admin-shell">
        <section class="admin-header">
            <div class="admin-title">
                <h1>Painel administrativo</h1>
                <p>Bom trabalho, <?= app_h($userName) ?>. Controle financeiro, usuarios, profissionais e faturamento em uma tela limpa.</p>
            </div>

            <div class="admin-actions">
                <span class="admin-date"><?= app_h(app_date_br($today)) ?></span>
                <a class="btn btn-primary" href="administrativo_financeiro.php">Financeiro</a>
                <a class="btn btn-outline-primary" href="administrativo_usuarios.php">Usuarios</a>
                <a class="btn btn-outline-primary" href="administrativo_profissionais.php">Profissionais</a>
            </div>
        </section>

        <section class="admin-metrics" aria-label="Resumo administrativo">
            <article class="admin-metric">
                <span>Profissionais</span>
                <strong><?= $totalProfessionals ?></strong>
                <small>Cadastros ativos no sistema</small>
            </article>

            <article class="admin-metric">
                <span>Usuarios</span>
                <strong><?= $totalUsers ?></strong>
                <small>Acessos configurados</small>
            </article>

            <article class="admin-metric">
                <span>A receber</span>
                <strong>R$ <?= number_format($totalReceivable, 2, ',', '.') ?></strong>
                <small>Contas em aberto</small>
            </article>

            <article class="admin-metric">
                <span>A pagar</span>
                <strong>R$ <?= number_format($totalPayable, 2, ',', '.') ?></strong>
                <small>Compromissos pendentes</small>
            </article>

            <article class="admin-metric">
                <span>Lotes abertos</span>
                <strong><?= $openBatches ?></strong>
                <small>Abertos ou enviados</small>
            </article>

            <article class="admin-metric">
                <span>Saldo previsto</span>
                <strong>R$ <?= number_format($balancePreview, 2, ',', '.') ?></strong>
                <small>Receber menos pagar</small>
            </article>
        </section>

        <section class="admin-workspace">
            <article class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <h2>Modulos administrativos</h2>
                        <span>Acesse as areas principais do administrativo.</span>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="relatorios.php">Relatorios</a>
                </div>

                <div class="table-responsive">
                    <table class="table admin-table align-middle">
                        <thead>
                            <tr>
                                <th>Modulo</th>
                                <th>Uso principal</th>
                                <th>Resumo</th>
                                <th>Acao</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr onclick="window.location.href='administrativo_profissionais.php'">
                                <td>
                                    <span class="admin-main-text">Profissionais</span>
                                    <span class="admin-subtext">Servicos, permissoes e cadastro</span>
                                </td>
                                <td>Equipe clinica</td>
                                <td><span class="status-pill is-info"><?= $totalProfessionals ?> cadastro(s)</span></td>
                                <td><a class="btn btn-sm btn-primary" href="administrativo_profissionais.php">Abrir</a></td>
                            </tr>

                            <tr onclick="window.location.href='administrativo_usuarios.php'">
                                <td>
                                    <span class="admin-main-text">Usuarios</span>
                                    <span class="admin-subtext">Logins, perfis e vinculos</span>
                                </td>
                                <td>Acessos ao sistema</td>
                                <td><span class="status-pill is-muted"><?= $totalUsers ?> usuario(s)</span></td>
                                <td><a class="btn btn-sm btn-primary" href="administrativo_usuarios.php">Abrir</a></td>
                            </tr>

                            <tr onclick="window.location.href='administrativo_financeiro.php'">
                                <td>
                                    <span class="admin-main-text">Financeiro</span>
                                    <span class="admin-subtext">Contas, plano financeiro e fechamento</span>
                                </td>
                                <td>Receitas e despesas</td>
                                <td><span class="status-pill is-success">R$ <?= number_format($balancePreview, 2, ',', '.') ?></span></td>
                                <td><a class="btn btn-sm btn-primary" href="administrativo_financeiro.php">Abrir</a></td>
                            </tr>

                            <tr onclick="window.location.href='administrativo_lotes.php'">
                                <td>
                                    <span class="admin-main-text">Lotes e faturamento</span>
                                    <span class="admin-subtext">Guias por convenio e envio</span>
                                </td>
                                <td>Faturamento em lote</td>
                                <td><span class="status-pill is-warning"><?= $openBatches ?> em aberto</span></td>
                                <td><a class="btn btn-sm btn-primary" href="administrativo_lotes.php">Abrir</a></td>
                            </tr>

                            <tr onclick="window.location.href='guias.php'">
                                <td>
                                    <span class="admin-main-text">Guias</span>
                                    <span class="admin-subtext">Consulta, edicao e acompanhamento</span>
                                </td>
                                <td>Gestao de atendimentos</td>
                                <td><span class="status-pill is-info"><?= $activeGuides ?> guia(s)</span></td>
                                <td><a class="btn btn-sm btn-primary" href="guias.php">Abrir</a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <aside class="admin-side">
                <article class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2>Atalhos</h2>
                            <span>Acoes mais usadas pelo administrativo.</span>
                        </div>
                    </div>
                    <div class="admin-quick-grid">
                        <a class="admin-action-link" href="administrativo_financeiro.php">Conferir financeiro <span>Entrar</span></a>
                        <a class="admin-action-link" href="financeiro_contas_receber.php">Contas a receber <span>Entrar</span></a>
                        <a class="admin-action-link" href="financeiro_contas_pagar.php">Contas a pagar <span>Entrar</span></a>
                        <a class="admin-action-link" href="administrativo_lotes.php">Lotes e faturamento <span>Entrar</span></a>
                        <a class="admin-action-link" href="relatorios.php">Relatorios gerenciais <span>Entrar</span></a>
                    </div>
                </article>

                <article class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <h2>Pontos de atencao</h2>
                            <span>Indicadores para conferencia rapida.</span>
                        </div>
                    </div>
                    <div class="admin-alert-list">
                        <div class="admin-alert">
                            <div>
                                <strong>Contas a receber</strong>
                                <span>Valores ainda nao pagos.</span>
                            </div>
                            <b>R$ <?= number_format($totalReceivable, 2, ',', '.') ?></b>
                        </div>
                        <div class="admin-alert">
                            <div>
                                <strong>Contas a pagar</strong>
                                <span>Compromissos pendentes.</span>
                            </div>
                            <b>R$ <?= number_format($totalPayable, 2, ',', '.') ?></b>
                        </div>
                        <div class="admin-alert">
                            <div>
                                <strong>Lotes em andamento</strong>
                                <span>Abertos ou enviados ao convenio.</span>
                            </div>
                            <b><?= $openBatches ?></b>
                        </div>
                    </div>
                </article>
            </aside>
        </section>
    </div>
</main>

</body>
</html>
