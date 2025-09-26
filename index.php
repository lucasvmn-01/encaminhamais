<?php
// Página inicial - Redireciona para login ou dashboard
session_start();

// Se já estiver logado, vai para o dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_name'])) {
    header('Location: dashboard.php');
    exit;
}

// Senão, vai para o login
header('Location: login.php');
exit;
?>
