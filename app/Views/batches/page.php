<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Faturamento de Guias</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/menu.php'; ?>

<div class="container page-shell">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <h5 class="mb-2">Faturamento por lotes</h5>
                <p class="small text-muted">Secretaria e administrativo podem montar lotes, selecionar guias e acompanhar o envio ao plano.</p>
            </div>
            <div class="selection-chip bg-white text-dark">Lotes com numero proprio e guias vinculadas</div>
        </div>
    </section>

    <div class="row g-3 mt-2">
        <div class="col-xl-5">
            <div class="soft-card card">
                <div class="card-header pb-2">
                    <div class="panel-title">
                        <h6 class="mb-0"><?= $selectedBatch ? 'Editar lote' : 'Novo lote' ?></h6>
                        <?php if ($selectedBatch): ?>
                            <span class="badge bg-secondary"><?= app_h($selectedBatch['numero_lote']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <?php if ($selectedBatch): ?>
                            <input type="hidden" name="batch_id" value="<?= (int) $selectedBatch['id'] ?>">
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label">Numero do lote</label>
                            <input type="text" name="numero_lote" class="form-control" value="<?= app_h((string) ($selectedBatch['numero_lote'] ?? '')) ?>" placeholder="Gerado automaticamente">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data</label>
                            <input type="date" name="data" class="form-control" value="<?= app_h((string) ($selectedBatch['data'] ?? date('Y-m-d'))) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <?php foreach ($batchStatuses as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= ($selectedBatch['status'] ?? 'aberto') === $value ? 'selected' : '' ?>>
                                        <?= app_h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="panel-title mb-2">
                                <h6>Guias vinculadas</h6>
                                <span class="text-muted small">filtradas pela barra ao lado</span>
                            </div>
                            <div class="checkbox-list">
                                <?php foreach ($availableGuides as $guide): ?>
                                    <?php $checked = in_array((int) $guide['id'], $selectedGuideIds, true); ?>
                                    <label class="checkbox-card<?= $checked ? ' is-selected' : '' ?>">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="guias[]" value="<?= (int) $guide['id'] ?>" <?= $checked ? 'checked' : '' ?>>
                                            <span class="fw-semibold"><?= app_h($guide['codigo'] ?: ('GUIA #' . $guide['id'])) ?></span>
                                        </div>
                                        <div class="small text-muted mt-2">
                                            <?= app_h($guide['paciente_nome']) ?> | <?= app_h($guide['profissional_nome']) ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?= app_date_br($guide['data']) ?> | <?= app_money_br((float) $guide['valor_guia']) ?>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12 d-flex flex-wrap justify-content-between gap-2">
                            <?php if ($selectedBatch): ?>
                                <button type="submit" name="action" value="delete_batch" class="btn btn-outline-danger" onclick="return confirm('Excluir este lote?')">Excluir</button>
                            <?php else: ?>
                                <span class="text-muted small align-self-center">Escolha uma ou mais guias do tipo convenio por lote.</span>
                            <?php endif; ?>
                            <button class="btn btn-primary px-4" name="action" value="save_batch"><?= $selectedBatch ? 'Editar' : 'Salvar' ?></button>
                        </div>
                    </form>

                    <?php if ($linkedGuides !== []): ?>
                        <div class="section-divider"></div>
                        <h6 class="mb-3">Guias atualmente no lote</h6>
                        <div class="table-responsive">
                            <table class="table table-soft align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Guia</th>
                                    <th>Paciente</th>
                                    <th>Valor</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($linkedGuides as $guide): ?>
                                    <tr>
                                        <td><?= app_h($guide['codigo'] ?: ('GUIA #' . $guide['id'])) ?></td>
                                        <td><?= app_h($guide['paciente_nome']) ?></td>
                                        <td><?= app_money_br((float) $guide['valor_guia']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="soft-card card mb-3">
                <div class="card-body">
                    <form class="toolbar-grid" method="GET">
                        <div>
                            <label class="form-label small text-muted">Guia</label>
                            <select name="guia_id" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($options['guides'] as $guide): ?>
                                    <option value="<?= (int) $guide['id'] ?>" <?= (int) $filters['guia_id'] === (int) $guide['id'] ? 'selected' : '' ?>>
                                        <?= app_h($guide['codigo'] ?: ('GUIA #' . $guide['id'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Paciente</label>
                            <select name="paciente_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($options['patients'] as $patient): ?>
                                    <option value="<?= (int) $patient['id'] ?>" <?= (int) $filters['paciente_id'] === (int) $patient['id'] ? 'selected' : '' ?>>
                                        <?= app_h($patient['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Profissional</label>
                            <select name="profissional_id" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($options['professionals'] as $professional): ?>
                                    <option value="<?= (int) $professional['id'] ?>" <?= (int) $filters['profissional_id'] === (int) $professional['id'] ? 'selected' : '' ?>>
                                        <?= app_h($professional['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small text-muted">Status</label>
                            <select name="status" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($batchStatuses as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= app_h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex align-items-end">
                            <button class="btn btn-primary w-100">Filtrar</button>
                        </div>
                        <div class="d-flex align-items-end">
                            <a href="administrativo_lotes.php" class="btn btn-outline-secondary w-100">Listar</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="soft-card card">
                <div class="card-header pb-2">
                    <div class="panel-title">
                        <h6 class="mb-0">Lotes cadastrados</h6>
                        <span class="text-muted small">editar, excluir, listar e dar baixa</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-soft align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Lote</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th>Guias</th>
                                <th>Valor</th>
                                <th class="text-end">Acoes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($batches as $batch): ?>
                                <tr class="<?= $selectedBatch && (int) $selectedBatch['id'] === (int) $batch['id'] ? 'is-active' : '' ?>">
                                    <td>
                                        <?= app_h($batch['numero_lote'] ?: ('LOTE #' . $batch['id'])) ?>
                                        <div class="small text-muted"><?= app_h($batch['convenio']) ?></div>
                                    </td>
                                    <td><?= app_date_br($batch['data']) ?></td>
                                    <td><?= app_h(app_lote_statuses()[$batch['status']] ?? $batch['status']) ?></td>
                                    <td><?= (int) $batch['total_guias'] ?></td>
                                    <td><?= app_money_br((float) $batch['total_valor']) ?></td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <?php if ($batch['status'] !== 'pago'): ?>
                                                <form method="POST" class="m-0 p-0 d-inline">
                                                    <input type="hidden" name="batch_id" value="<?= (int) $batch['id'] ?>">
                                                    <button type="submit" name="action" value="mark_paid" class="btn btn-sm btn-outline-success" onclick="return confirm('Confirmar o recebimento e dar baixa neste lote?')">Dar Baixa</button>
                                                </form>
                                            <?php endif; ?>
                                            <a class="btn btn-sm btn-outline-primary" href="administrativo_lotes.php?<?= app_h(app_build_query(array_merge($filters, ['batch_id' => $batch['id']]))) ?>">Editar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?= app_render_pagination($pagination) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.checkbox-card input[type="checkbox"]').forEach((checkbox) => {
    checkbox.addEventListener('change', () => {
        checkbox.closest('.checkbox-card').classList.toggle('is-selected', checkbox.checked);
    });
});
</script>

</body>
</html>
