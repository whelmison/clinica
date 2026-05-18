<?php
include 'config/db.php';

$guideId = app_query_int('id');
$query = app_build_query([
    'edit_id' => $guideId > 0 ? $guideId : null,
    'filtrar' => 1,
]);

app_redirect('guias.php' . ($query !== '' ? '?' . $query : ''));
