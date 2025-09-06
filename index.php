<?php
// Associação Amigo do Povo - Sistema de Gestão
// Arquivo principal de entrada

// Configurações iniciais
require_once 'config/config.php';
require_once 'config/database.php';

// Iniciar sessão (configurações já definidas em config.php)
session_start();

// Se não estiver logado, redirecionar para login
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

// Redirecionar para dashboard
header('Location: pages/dashboard.php');
exit();
?>