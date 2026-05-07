<?php
include 'config/db.php';

$patientId = app_query_int('id');

if ($patientId > 0) {
    app_redirect('pacientes.php?' . app_build_query(['patient_id' => $patientId]));
}

app_redirect('pacientes.php');
