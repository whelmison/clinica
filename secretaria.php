<?php
include 'config/db.php';

$today = date('Y-m-d');
$weekEnd = date('Y-m-d', strtotime($today . ' +6 days'));
$currentUser = app_current_user() ?? [];
$userName = app_first_name($currentUser['nome_exibicao'] ?? 'Secretaria');
$clinicId = app_active_clinic_id();

function secretaria_count(mysqli $conn, string $sql): int
{
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }

    return (int) ($result->fetch_assoc()['total'] ?? 0);
}

function secretaria_fetch_all(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function secretaria_status_class(string $status): string
{
    return match ($status) {
        'confirmado' => 'is-success',
        'em_atendimento' => 'is-info',
        'concluido' => 'is-muted',
        'cancelado' => 'is-danger',
        'faltou' => 'is-warning',
        default => 'is-pending',
    };
}

$statusLabels = app_schedule_statuses();

$todayAppointments = secretaria_count($conn, "SELECT COUNT(*) AS total FROM agenda WHERE clinica_id = {$clinicId} AND data_agendamento = '{$today}'");
$confirmedAppointments = secretaria_count($conn, "SELECT COUNT(*) AS total FROM agenda WHERE clinica_id = {$clinicId} AND data_agendamento = '{$today}' AND status = 'confirmado'");
$pendingAppointments = secretaria_count($conn, "SELECT COUNT(*) AS total FROM agenda WHERE clinica_id = {$clinicId} AND data_agendamento = '{$today}' AND status = 'agendado'");
$weekAppointments = secretaria_count($conn, "SELECT COUNT(*) AS total FROM agenda WHERE clinica_id = {$clinicId} AND data_agendamento BETWEEN '{$today}' AND '{$weekEnd}' AND status <> 'cancelado'");
$totalServices = secretaria_count($conn, "SELECT COUNT(*) AS total FROM servicos WHERE clinica_id = {$clinicId} AND ativo = 1");
$todayGuides = secretaria_count($conn, "SELECT COUNT(*) AS total FROM guias WHERE clinica_id = {$clinicId} AND data = '{$today}'");
$professionalsWithService = secretaria_count($conn, "SELECT COUNT(DISTINCT profissional_id) AS total FROM profissional_servico WHERE clinica_id = {$clinicId}");
$patientsWithoutGuide = secretaria_count($conn, "
    SELECT COUNT(*) AS total
    FROM pacientes p
    WHERE p.clinica_id = {$clinicId}
    AND NOT EXISTS (
        SELECT 1
        FROM guias g
        WHERE g.paciente_id = p.id
        AND g.clinica_id = p.clinica_id
        AND (
            SELECT COUNT(*)
            FROM atendimentos a
            WHERE a.clinica_id = g.clinica_id
            AND a.guia_id = g.id
        ) < g.total_sessoes
    )
");
$guidesNearEnd = secretaria_count($conn, "
    SELECT COUNT(DISTINCT g.id) AS total
    FROM guias g
    WHERE g.clinica_id = {$clinicId}
    AND (
        g.total_sessoes - (
            SELECT COUNT(*)
            FROM atendimentos a
            WHERE a.clinica_id = g.clinica_id
            AND a.guia_id = g.id
        )
    ) BETWEEN 1 AND 2
");

$todayAgenda = secretaria_fetch_all($conn, "
    SELECT
        a.id,
        a.hora_inicio,
        a.hora_fim,
        a.status,
        COALESCE(pa.nome, a.cliente_nome, 'Paciente nao informado') AS paciente_nome,
        COALESCE(pr.nome, 'Profissional nao informado') AS profissional_nome,
        COALESCE(s.nome, 'Servico nao informado') AS servico_nome
    FROM agenda a
    LEFT JOIN pacientes pa ON pa.id = a.cliente_id AND pa.clinica_id = a.clinica_id
    LEFT JOIN profissionais pr ON pr.id = a.profissional_id AND pr.clinica_id = a.clinica_id
    LEFT JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
    WHERE a.clinica_id = {$clinicId}
    AND a.data_agendamento = '{$today}'
    ORDER BY a.hora_inicio ASC
    LIMIT 8
");

$todayGuidesList = secretaria_fetch_all($conn, "
    SELECT
        g.id,
        g.codigo,
        COALESCE(pa.nome, 'Paciente nao informado') AS paciente_nome,
        COALESCE(pr.nome, 'Profissional nao informado') AS profissional_nome,
        COALESCE(pl.nome, 'Plano nao informado') AS plano_nome
    FROM guias g
    LEFT JOIN pacientes pa ON pa.id = g.paciente_id AND pa.clinica_id = g.clinica_id
    LEFT JOIN profissionais pr ON pr.id = g.profissional_id AND pr.clinica_id = g.clinica_id
    LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
    WHERE g.clinica_id = {$clinicId}
    AND g.data = '{$today}'
    ORDER BY g.id DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel Secretaria</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
.secretary-dashboard {
    padding: .75rem .9rem 1.25rem;
}

.secretary-shell {
    display: grid;
    gap: .75rem;
    max-width: 1480px;
    margin: 0 auto;
}

.secretary-header,
.secretary-card {
    background: rgba(255, 255, 255, .94);
    border: 1px solid rgba(14, 116, 144, .12);
    border-radius: 8px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .07);
}

.secretary-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .8rem;
    padding: .85rem 1rem;
}

.secretary-title h1 {
    color: #0f172a;
    font-size: 1.25rem;
    font-weight: 800;
    letter-spacing: -.03em;
    margin: 0;
}

.secretary-title p {
    color: #64748b;
    font-size: .82rem;
    margin: .14rem 0 0;
}

.secretary-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    justify-content: flex-end;
}

.secretary-actions .btn,
.secretary-action-link {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    font-size: .82rem;
    font-weight: 700;
    gap: .35rem;
    min-height: 34px;
    padding: .4rem .7rem;
}

.secretary-date {
    background: #ecfeff;
    border: 1px solid rgba(14, 116, 144, .16);
    border-radius: 8px;
    color: #0f766e;
    font-size: .78rem;
    font-weight: 800;
    padding: .38rem .62rem;
    white-space: nowrap;
}

.secretary-metrics {
    display: grid;
    gap: .65rem;
    grid-template-columns: repeat(6, minmax(0, 1fr));
}

.secretary-metric {
    background: rgba(255, 255, 255, .95);
    border: 1px solid rgba(14, 116, 144, .12);
    border-left: 4px solid #0f766e;
    border-radius: 8px;
    box-shadow: 0 8px 18px rgba(15, 23, 42, .055);
    min-height: 82px;
    padding: .7rem .75rem;
}

.secretary-metric:nth-child(2) {
    border-left-color: #0284c7;
}

.secretary-metric:nth-child(3) {
    border-left-color: #16a34a;
}

.secretary-metric:nth-child(4) {
    border-left-color: #ca8a04;
}

.secretary-metric:nth-child(5) {
    border-left-color: #475569;
}

.secretary-metric:nth-child(6) {
    border-left-color: #0e7490;
}

.secretary-metric span {
    color: #64748b;
    display: block;
    font-size: .7rem;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
}

.secretary-metric strong {
    color: #0f172a;
    display: block;
    font-size: 1.55rem;
    font-weight: 900;
    line-height: 1;
    margin-top: .38rem;
}

.secretary-metric small {
    color: #64748b;
    display: block;
    font-size: .72rem;
    margin-top: .32rem;
}

.secretary-workspace {
    display: grid;
    gap: .75rem;
    grid-template-columns: minmax(0, 1.55fr) minmax(300px, .75fr);
}

.secretary-card {
    min-width: 0;
    overflow: hidden;
}

.secretary-card-header {
    align-items: center;
    border-bottom: 1px solid rgba(148, 163, 184, .22);
    display: flex;
    justify-content: space-between;
    gap: .65rem;
    padding: .72rem .85rem;
}

.secretary-card-header h2 {
    color: #0f172a;
    font-size: .96rem;
    font-weight: 850;
    margin: 0;
}

.secretary-card-header span {
    color: #64748b;
    font-size: .76rem;
    font-weight: 700;
}

.secretary-table {
    margin: 0;
}

.secretary-table th {
    background: #f8fafc;
    color: #64748b;
    font-size: .7rem;
    font-weight: 850;
    letter-spacing: .05em;
    padding: .55rem .7rem;
    text-transform: uppercase;
}

.secretary-table td {
    color: #334155;
    font-size: .82rem;
    padding: .55rem .7rem;
    vertical-align: middle;
}

.secretary-table tbody tr {
    cursor: pointer;
    transition: background-color .16s ease, transform .16s ease;
}

.secretary-table tbody tr:hover {
    background: #ecfeff;
}

.secretary-main-text {
    color: #0f172a;
    font-weight: 850;
}

.secretary-subtext {
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

.status-pill.is-pending {
    background: #fff7ed;
    color: #c2410c;
}

.status-pill.is-success {
    background: #dcfce7;
    color: #15803d;
}

.status-pill.is-info {
    background: #e0f2fe;
    color: #0369a1;
}

.status-pill.is-muted {
    background: #f1f5f9;
    color: #475569;
}

.status-pill.is-danger {
    background: #fee2e2;
    color: #b91c1c;
}

.status-pill.is-warning {
    background: #fef3c7;
    color: #92400e;
}

.secretary-side {
    display: grid;
    gap: .75rem;
}

.secretary-quick-grid {
    display: grid;
    gap: .5rem;
    padding: .75rem;
}

.secretary-action-link {
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, .22);
    color: #0f172a;
    justify-content: space-between;
    text-decoration: none;
    transition: background-color .16s ease, border-color .16s ease, color .16s ease;
}

.secretary-action-link:hover {
    background: #ecfeff;
    border-color: rgba(14, 116, 144, .28);
    color: #0f766e;
}

.secretary-alert-list {
    display: grid;
    gap: .5rem;
    padding: .75rem;
}

.secretary-alert {
    align-items: center;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, .2);
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    gap: .7rem;
    padding: .58rem .65rem;
}

.secretary-alert strong {
    color: #0f172a;
    display: block;
    font-size: .82rem;
}

.secretary-alert span {
    color: #64748b;
    display: block;
    font-size: .72rem;
}

.secretary-alert b {
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

.secretary-empty {
    color: #64748b;
    font-size: .83rem;
    padding: 1.1rem;
    text-align: center;
}

.secretary-guide-list {
    display: grid;
    gap: .45rem;
    padding: .75rem;
}

.secretary-guide-item {
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, .18);
    border-radius: 8px;
    padding: .55rem .65rem;
}

.secretary-guide-item strong {
    color: #0f172a;
    display: block;
    font-size: .8rem;
}

.secretary-guide-item span {
    color: #64748b;
    display: block;
    font-size: .72rem;
    margin-top: .08rem;
}

@media (max-width: 1180px) {
    .secretary-metrics {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .secretary-workspace {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 760px) {
    .secretary-dashboard {
        padding: .6rem;
    }

    .secretary-header {
        align-items: stretch;
        flex-direction: column;
    }

    .secretary-actions {
        justify-content: flex-start;
    }

    .secretary-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .secretary-table th:nth-child(4),
    .secretary-table td:nth-child(4) {
        display: none;
    }
}
</style>
</head>
<body>

<?php include 'partials/menu.php'; ?>

<main class="secretary-dashboard">
    <div class="secretary-shell">
        <section class="secretary-header">
            <div class="secretary-title">
                <h1>Painel da secretaria</h1>
                <p>Bom trabalho, <?= app_h($userName) ?>. Tudo que a recepcao precisa para tocar o dia em uma tela limpa.</p>
            </div>

            <div class="secretary-actions">
                <span class="secretary-date"><?= app_h(app_date_br($today)) ?></span>
                <a class="btn btn-primary" href="secretaria_agenda.php">Agenda</a>
                <a class="btn btn-outline-primary" href="novo_paciente.php">Novo paciente</a>
                <a class="btn btn-outline-primary" href="nova_guia_gestao.php">Nova guia</a>
            </div>
        </section>

        <section class="secretary-metrics" aria-label="Resumo operacional">
            <article class="secretary-metric">
                <span>Agenda hoje</span>
                <strong><?= $todayAppointments ?></strong>
                <small><?= $confirmedAppointments ?> confirmado(s)</small>
            </article>

            <article class="secretary-metric">
                <span>Pendentes hoje</span>
                <strong><?= $pendingAppointments ?></strong>
                <small>Precisam confirmacao</small>
            </article>

            <article class="secretary-metric">
                <span>Semana</span>
                <strong><?= $weekAppointments ?></strong>
                <small>Agendamentos ativos</small>
            </article>

            <article class="secretary-metric">
                <span>Sem guia ativa</span>
                <strong><?= $patientsWithoutGuide ?></strong>
                <small>Pacientes para regularizar</small>
            </article>

            <article class="secretary-metric">
                <span>Servicos ativos</span>
                <strong><?= $totalServices ?></strong>
                <small><?= $professionalsWithService ?> profissional(is)</small>
            </article>

            <article class="secretary-metric">
                <span>Guias hoje</span>
                <strong><?= $todayGuides ?></strong>
                <small><?= $guidesNearEnd ?> perto do fim</small>
            </article>
        </section>

        <section class="secretary-workspace">
            <article class="secretary-card">
                <div class="secretary-card-header">
                    <div>
                        <h2>Agenda de hoje</h2>
                        <span>Proximos horarios, paciente, profissional e status.</span>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="secretaria_agenda.php">Abrir grade</a>
                </div>

                <?php if ($todayAgenda): ?>
                    <div class="table-responsive">
                        <table class="table secretary-table align-middle">
                            <thead>
                                <tr>
                                    <th>Horario</th>
                                    <th>Paciente</th>
                                    <th>Profissional</th>
                                    <th>Servico</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todayAgenda as $item): ?>
                                    <tr onclick="window.location.href='secretaria_agenda.php?data=<?= urlencode($today) ?>'">
                                        <td>
                                            <span class="secretary-main-text"><?= app_h(app_time_br($item['hora_inicio'])) ?></span>
                                            <span class="secretary-subtext"><?= app_h(app_time_br($item['hora_fim'])) ?></span>
                                        </td>
                                        <td>
                                            <span class="secretary-main-text"><?= app_h($item['paciente_nome']) ?></span>
                                        </td>
                                        <td>
                                            <span class="secretary-main-text"><?= app_h(app_first_name($item['profissional_nome'])) ?></span>
                                        </td>
                                        <td><?= app_h($item['servico_nome']) ?></td>
                                        <td>
                                            <span class="status-pill <?= app_h(secretaria_status_class($item['status'])) ?>">
                                                <?= app_h($statusLabels[$item['status']] ?? $item['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="secretary-empty">Nenhum agendamento para hoje. Use a agenda para criar ou liberar novos horarios.</div>
                <?php endif; ?>
            </article>

            <aside class="secretary-side">
                <article class="secretary-card">
                    <div class="secretary-card-header">
                        <div>
                            <h2>Atalhos</h2>
                            <span>Acoes mais usadas pela secretaria.</span>
                        </div>
                    </div>
                    <div class="secretary-quick-grid">
                        <a class="secretary-action-link" href="secretaria_agenda.php">Agendar, confirmar ou remarcar <span>Entrar</span></a>
                        <a class="secretary-action-link" href="pacientes.php">Consultar pacientes <span>Entrar</span></a>
                        <a class="secretary-action-link" href="guias.php">Gerenciar guias <span>Entrar</span></a>
                        <a class="secretary-action-link" href="secretaria_servicos.php">Servicos por profissional <span>Entrar</span></a>
                        <a class="secretary-action-link" href="administrativo_lotes.php">Lotes e faturamento <span>Entrar</span></a>
                    </div>
                </article>

                <article class="secretary-card">
                    <div class="secretary-card-header">
                        <div>
                            <h2>Pontos de atencao</h2>
                            <span>O que merece conferencia rapida.</span>
                        </div>
                    </div>
                    <div class="secretary-alert-list">
                        <div class="secretary-alert">
                            <div>
                                <strong>Pacientes sem guia ativa</strong>
                                <span>Regularizar antes do atendimento.</span>
                            </div>
                            <b><?= $patientsWithoutGuide ?></b>
                        </div>
                        <div class="secretary-alert">
                            <div>
                                <strong>Guias perto do fim</strong>
                                <span>Restam 1 ou 2 sessoes.</span>
                            </div>
                            <b><?= $guidesNearEnd ?></b>
                        </div>
                        <div class="secretary-alert">
                            <div>
                                <strong>Agendamentos pendentes</strong>
                                <span>Confirmar presenca do dia.</span>
                            </div>
                            <b><?= $pendingAppointments ?></b>
                        </div>
                    </div>
                </article>

                <article class="secretary-card">
                    <div class="secretary-card-header">
                        <div>
                            <h2>Guias lancadas hoje</h2>
                            <span>Ultimos registros do dia.</span>
                        </div>
                    </div>

                    <?php if ($todayGuidesList): ?>
                        <div class="secretary-guide-list">
                            <?php foreach ($todayGuidesList as $guide): ?>
                                <div class="secretary-guide-item">
                                    <strong><?= app_h($guide['paciente_nome']) ?></strong>
                                    <span><?= app_h($guide['codigo'] ?: 'Sem codigo') ?> - <?= app_h(app_first_name($guide['profissional_nome'])) ?> - <?= app_h($guide['plano_nome']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="secretary-empty">Nenhuma guia lancada hoje.</div>
                    <?php endif; ?>
                </article>
            </aside>
        </section>
    </div>
</main>

</body>
</html>
