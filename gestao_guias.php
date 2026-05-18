<?php
include 'config/db.php';

$query = app_build_query([
    'paciente' => app_request_query('busca', '') ?: null,
    'profissional_id' => app_query_int('profissional_id') ?: null,
    'status_guia' => app_request_query('status_operacional', '') ?: null,
    'edit_id' => app_query_int('selected') ?: null,
    'open_new' => app_query_int('open_new') === 1 ? 1 : null,
    'filtrar' => 1,
]);

app_redirect('guias.php' . ($query !== '' ? '?' . $query : ''));
