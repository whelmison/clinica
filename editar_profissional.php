<?php
include 'config/db.php';

$professionalId = app_query_int('id');

if ($professionalId > 0) {
    app_redirect('administrativo_profissionais.php?' . app_build_query(['professional_id' => $professionalId]));
}

app_redirect('administrativo_profissionais.php');
