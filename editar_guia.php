<?php
include 'config/db.php';

$id = app_query_int('id');

app_redirect('guias.php?' . app_build_query([
    'edit_id' => $id > 0 ? $id : null,
    'filtrar' => 1,
]));
