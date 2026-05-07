<?php
include 'config/db.php';

$guideId = app_query_int('id');
$query = app_build_query([
    'selected' => $guideId > 0 ? $guideId : null,
]);

app_redirect('gestao_guias.php' . ($query !== '' ? '?' . $query : ''));
