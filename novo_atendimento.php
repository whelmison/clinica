<?php
include 'config/db.php';

app_redirect('atendimentos.php?' . app_build_query(['open_new' => 1]));
