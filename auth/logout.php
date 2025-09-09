<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

// Inicializar sistema de autenticação
$database = new Database();
$auth = new AuthSystem($database);

// Realizar logout com log de auditoria
$auth->logout();

// Redirecionar para login
header('Location: login.php');
exit();
?>