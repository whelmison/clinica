<?php

declare(strict_types=1);

return [
    'login_profile_home' => static function (): void {
        test_assert_same('secretaria.php', app_profile_home('secretaria'), 'O perfil secretaria deve abrir a home correta.');
        test_assert_same('administrativo.php', app_profile_home('administrativo'), 'O perfil administrativo deve abrir a home correta.');
    },
    'login_access_map' => static function (): void {
        $accessMap = app_page_access_map();
        test_assert_true(in_array('public', $accessMap['login.php'] ?? [], true), 'A tela de login deve continuar publica.');
        test_assert_true(in_array('secretaria', $accessMap['secretaria_agenda.php'] ?? [], true), 'A agenda da secretaria precisa seguir protegida por perfil.');
    },
    'login_session_helpers' => static function (): void {
        app_login_user([
            'id' => 99,
            'login' => 'teste',
            'perfil' => 'desenvolvedor',
            'profissional_id' => null,
            'nome_exibicao' => 'Teste',
        ]);
        test_assert_true(app_is_logged_in(), 'O helper de login deve refletir usuario autenticado.');
        app_logout_user();
        test_assert_true(!app_is_logged_in(), 'O helper de logout deve limpar a sessao.');
    },
];
