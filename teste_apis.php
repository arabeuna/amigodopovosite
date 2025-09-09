<?php
require_once 'config/config.php';
require_once 'config/database.php';
session_start();

// Simular login do usuário administrador
$_SESSION['user_id'] = 2;
$_SESSION['user_name'] = 'Administrador';
$_SESSION['user_email'] = 'admin@associacao.com';

echo "<h2>Teste das APIs</h2>";

// Testar API de atividades
echo "<h3>Testando API de Atividades:</h3>";
ob_start();
include 'api/atividades.php';
$atividades_result = ob_get_clean();
echo "<pre>" . htmlspecialchars($atividades_result) . "</pre>";

// Reiniciar sessão para próximo teste
session_start();
$_SESSION['user_id'] = 2;
$_SESSION['user_name'] = 'Administrador';
$_SESSION['user_email'] = 'admin@associacao.com';

// Testar API de turmas
echo "<h3>Testando API de Turmas:</h3>";
ob_start();
include 'api/turmas.php';
$turmas_result = ob_get_clean();
echo "<pre>" . htmlspecialchars($turmas_result) . "</pre>";

echo "<hr>";
echo "<p><a href='pages/alunos.php'>Ir para página de alunos</a></p>";
?>