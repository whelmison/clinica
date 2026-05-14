<?php
include 'config/db.php';

use Clinic\Repositories\ProfessionalRepository;
use Clinic\Services\ProfessionalService;

$pdo = app_pdo();
$professionalRepository = new ProfessionalRepository($pdo);
$professionalService = new ProfessionalService($pdo, $professionalRepository);
$currentUser = app_current_user() ?? [];
$requestMethod = app_request_method();
$userFilters = [
    'busca_usuario' => $requestMethod === 'POST'
        ? (app_request_post('busca_usuario', '') ?? '')
        : (app_request_query('busca_usuario', '') ?? ''),
];
$requestedPage = $requestMethod === 'POST'
    ? max(1, app_post_int('user_page', 1))
    : max(1, app_query_int('user_page', 1));
$selectedUser = app_query_int('user_id') ? $professionalRepository->findUser(app_query_int('user_id')) : null;
$userFormValues = [
    'user_id' => (int) ($selectedUser['id'] ?? 0),
    'nome_exibicao' => (string) ($selectedUser['nome_exibicao'] ?? ''),
    'login' => (string) ($selectedUser['login'] ?? ''),
    'perfil' => (string) ($selectedUser['perfil'] ?? 'profissional'),
    'profissional_relacionado' => (int) ($selectedUser['profissional_id'] ?? 0),
    'ativo' => !isset($selectedUser['ativo']) || (int) ($selectedUser['ativo'] ?? 1) === 1,
    'usuario_padrao' => (int) ($selectedUser['usuario_padrao'] ?? 0) === 1,
];
$autoOpenUserModal = $selectedUser !== null;

if ($requestMethod === 'POST') {
    $action = app_request_post('action', '') ?? '';
    $result = ['ok' => false, 'message' => 'Acao invalida.'];

    if ($action === 'save_user') {
        $userFormValues = [
            'user_id' => app_post_int('user_id'),
            'nome_exibicao' => app_request_post('nome_exibicao', '') ?? '',
            'login' => app_request_post('login', '') ?? '',
            'perfil' => app_request_post('perfil', 'profissional') ?? 'profissional',
            'profissional_relacionado' => app_post_int('profissional_relacionado'),
            'ativo' => isset($_POST['ativo']),
            'usuario_padrao' => (int) ($selectedUser['usuario_padrao'] ?? 0) === 1,
        ];
        $result = $professionalService->saveUser(app_post_int('user_id') ?: null, $_POST);
        $autoOpenUserModal = !$result['ok'];
    } elseif ($action === 'delete_user') {
        $result = $professionalService->deleteUser(app_post_int('user_id'), (int) ($currentUser['id'] ?? 0));
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);

    if ($result['ok'] || $action === 'delete_user') {
        app_redirect('administrativo_usuarios.php?' . app_build_query([
            'busca_usuario' => $userFilters['busca_usuario'],
            'user_page' => $requestedPage > 1 ? $requestedPage : null,
        ]));
    }
}

$usersData = $professionalRepository->users($requestedPage, 10, $userFilters);
$usersRows = $usersData['items'];
$usersPagination = $usersData['pagination'];
$professionalOptions = app_fetch_profissionais($conn);

include __DIR__ . '/app/Views/users/page.php';
