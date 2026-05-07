<?php include 'config/db.php'; ?>

<?php

if($_POST){

$guia = (int) $_POST['guia'];
$valor = $_POST['valor'];
$clinicId = app_active_clinic_id();

$valor = str_replace(['R$', ' ', '.'], '', $valor);
$valor = str_replace(',', '.', $valor);
$valor = floatval($valor);

// 🔒 VERIFICA SE GUIA FINALIZOU
$g = app_stmt_one(
    $conn,
    'SELECT total_sessoes, sessoes_usadas FROM guias WHERE clinica_id = ? AND id = ? LIMIT 1',
    'ii',
    [$clinicId, $guia]
);

if (!$g) {
    echo "<script>alert('Guia nao encontrada.'); window.history.back();</script>";
    exit;
}

if($g['sessoes_usadas'] < $g['total_sessoes']){

echo "<script>
alert('Não é possível receber: guia ainda não finalizada.');
window.history.back();
</script>";

exit;
}

// segue fluxo normal
app_stmt_execute(
    $conn,
    'UPDATE guias SET recebido = recebido + ? WHERE clinica_id = ? AND id = ?',
    'dii',
    [$valor, $clinicId, $guia]
);

echo "<script>
alert('Recebimento realizado!');
window.location='financeiro.php';
</script>";

}
