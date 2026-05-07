<?php include 'config/db.php'; ?>
<?php
app_logout_user();
app_flash('success', 'Sessao encerrada com sucesso.');
app_redirect('login.php');
