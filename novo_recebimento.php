<?php
include 'config/db.php';

$guideId = app_request_method() === 'POST'
    ? (int) ($_POST['guia'] ?? 0)
    : app_query_int('guia');

if ($guideId <= 0) {
    app_flash('warning', 'Selecione uma guia para registrar a baixa.');
    app_redirect('guias.php');
}

app_flash('info', 'Use a tela de baixa da guia para respeitar valor faturado, valor da guia e multiplo do atendimento.');
app_redirect('baixar_guia.php?id=' . $guideId);
