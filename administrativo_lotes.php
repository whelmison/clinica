<?php
include 'config/db.php';

use Clinic\Repositories\BatchRepository;
use Clinic\Services\BatchService;

$pdo = app_pdo();
$batchRepository = new BatchRepository($pdo);
$batchService = new BatchService($pdo, $batchRepository);
$currentUser = app_current_user() ?? [];
$filters = [
    'guia_id' => app_query_int('guia_id'),
    'paciente_id' => app_query_int('paciente_id'),
    'profissional_id' => app_query_int('profissional_id'),
    'status' => app_request_query('status', '') ?? '',
];
$selectedBatchId = app_query_int('batch_id') ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'save_batch') {
        $result = $batchService->save(app_post_int('batch_id') ?: null, $_POST, $currentUser);
    } elseif ($action === 'delete_batch') {
        $result = $batchService->delete(app_post_int('batch_id'), $currentUser);
    } elseif ($action === 'mark_paid') {
        $result = $batchService->markAsPaid(app_post_int('batch_id'), $currentUser);
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    $redirectQuery = $filters;
    $redirectQuery['batch_id'] = ($result['ok'] ?? false) && $action === 'save_batch' ? ($result['id'] ?? null) : null;
    app_redirect('administrativo_lotes.php?' . app_build_query($redirectQuery));
}

$options = $batchRepository->options();
$batchStatuses = app_lote_statuses();
$pageData = $batchRepository->paginate($filters, max(1, app_query_int('page', 1)), 10);
$batches = $pageData['items'];
$pagination = $pageData['pagination'];
$pagination['query'] = array_merge($pagination['query'], ['batch_id' => $selectedBatchId]);
$selectedBatch = $selectedBatchId ? $batchRepository->find($selectedBatchId) : null;
$linkedGuides = $selectedBatch ? $batchRepository->linkedGuides((int) $selectedBatch['id']) : [];
$selectedGuideIds = array_map(static fn (array $guide): int => (int) $guide['id'], $linkedGuides);
$availableGuides = $batchRepository->availableGuides([], $selectedBatchId);

include __DIR__ . '/app/Views/batches/page.php';
