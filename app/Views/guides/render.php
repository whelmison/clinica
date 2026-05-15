<?php

function guide_status_data(array $guide): array
{
    return app_guide_operational_status_data($guide);
}

function guide_billing_label(array $guide): string
{
    if (in_array($guide['tipo_guia'], ['particular', 'convenio_direto'], true)) {
        return !empty($guide['conta_receber_id']) ? 'Conta gerada' : 'Conta pendente';
    }

    if (!empty($guide['numero_lote'])) {
        return 'Lote ' . $guide['numero_lote'];
    }

    return 'Aguardando lote';
}

function guide_type_label(array $guide): string
{
    $type = trim((string) ($guide['tipo_guia'] ?? ''));

    if ($type === '') {
        return 'Sem tipo';
    }

    return app_guide_types()[$type] ?? $type;
}

function guide_filters_query(array $filters, array $extra = []): string
{
    return app_build_query(array_merge($filters, $extra));
}

function render_guide_metrics(array $guides): string
{
    $summary = [
        'total' => count($guides),
        'autorizadas' => 0,
        'em_uso' => 0,
        'ultimas' => 0,
        'finalizadas' => 0,
    ];

    foreach ($guides as $guide) {
        $status = guide_status_data($guide)['value'];

        if ($status === 'autorizada') {
            $summary['autorizadas']++;
        } elseif ($status === 'em_uso') {
            $summary['em_uso']++;
        } elseif ($status === 'ultimas_sessoes') {
            $summary['ultimas']++;
        } elseif ($status === 'finalizada') {
            $summary['finalizadas']++;
        }
    }

    ob_start();
    ?>
    <div class="guide-summary-strip">
        <span><strong><?= $summary['total'] ?></strong> guias na pagina</span>
        <span><strong><?= $summary['autorizadas'] ?></strong> autorizadas</span>
        <span><strong><?= $summary['em_uso'] ?></strong> em uso</span>
        <span><strong><?= $summary['ultimas'] ?></strong> nas ultimas sessoes</span>
        <span><strong><?= $summary['finalizadas'] ?></strong> finalizadas</span>
    </div>
    <?php

    return (string) ob_get_clean();
}

function render_guide_cards(array $guides, ?int $selectedId, array $filters, array $pagination): string
{
    ob_start();

    if ($guides === []): ?>
        <div class="guide-empty-state">
            <strong>Nenhuma guia encontrada.</strong>
            <span>Ajuste a busca ou limpe os filtros para ver mais resultados.</span>
        </div>
    <?php else: ?>
        <div class="guide-table">
            <div class="guide-row guide-row-head">
                <div>Guia / paciente</div>
                <div>Profissional</div>
                <div>Sessoes</div>
                <div>Faturamento</div>
                <div class="text-end">Valor</div>
                <div class="text-end">Acao</div>
            </div>
        <?php
        foreach ($guides as $guide):
            $isSelected = $selectedId !== null && (int) $guide['id'] === $selectedId;
            $status = guide_status_data($guide);
            $usedSessions = (int) ($guide['total_atendimentos'] ?? 0);
            $lockedForEdit = $usedSessions > 0 || $status['value'] === 'finalizada';
            $totalSessions = max(0, (int) ($guide['total_sessoes'] ?? 0));
            $sessionsLabel = $usedSessions . '/' . $totalSessions;
            $editQuery = guide_filters_query($filters, [
                'selected' => (int) $guide['id'],
                'page' => (int) ($pagination['page'] ?? 1) > 1 ? (int) $pagination['page'] : null,
            ]);
            ?>
            <a href="gestao_guias.php<?= $editQuery !== '' ? '?' . app_h($editQuery) : '' ?>" class="guide-row<?= $isSelected ? ' is-selected' : '' ?>">
                <div class="guide-main-cell">
                    <strong><?= app_h($guide['codigo'] ?: ('GUIA #' . $guide['id'])) ?></strong>
                    <span><?= app_h($guide['paciente_nome'] ?: 'Paciente nao informado') ?></span>
                </div>
                <div class="guide-muted-cell"><?= app_h($guide['profissional_nome'] ?: 'Profissional nao informado') ?></div>
                <div>
                    <span class="guide-pill <?= app_h($status['class']) ?>"><?= app_h($sessionsLabel) ?></span>
                    <small><?= app_h($guide['servico_nome'] ?: 'Servico nao informado') ?></small>
                    <small><?= app_h($status['label']) ?> | <?= (int) ($guide['autorizada'] ?? 0) === 1 ? 'Autorizada' : 'Nao autorizada' ?></small>
                </div>
                <div>
                    <span class="guide-pill"><?= app_h(guide_type_label($guide)) ?></span>
                    <small><?= app_h(guide_billing_label($guide)) ?></small>
                </div>
                <div class="guide-value-cell">
                    <strong><?= app_money_br((float) $guide['valor_guia']) ?></strong>
                    <span><?= app_date_br($guide['data']) ?></span>
                </div>
                <div class="guide-action-cell">
                    <span><?= $lockedForEdit ? 'Bloqueada' : 'Editar' ?></span>
                </div>
            </a>
        <?php endforeach; ?>
        </div>
        <?php
    endif;

    echo app_render_pagination($pagination);

    return (string) ob_get_clean();
}
