<?php
// Teste da funcionalidade de matrículas após correção
session_start();

// Simular login de usuário administrador
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'Admin';
$_SESSION['user_type'] = 'admin';

require_once 'config/config.php';
require_once 'config/database.php';

echo "<h2>Teste da API de Matrículas</h2>";

// Testar busca de matrículas para um aluno
echo "<h3>1. Testando busca de matrículas (GET)</h3>";
$url = 'http://localhost/associacao-php/api/matriculas.php?aluno_id=12';
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'Cookie: ' . session_name() . '=' . session_id()
    ]
]);

$response = file_get_contents($url, false, $context);
echo "<strong>Resposta:</strong> " . htmlspecialchars($response) . "<br><br>";

// Testar criação de matrícula
echo "<h3>2. Testando criação de matrícula (POST)</h3>";
$postData = http_build_query([
    'action' => 'create',
    'aluno_id' => 12,
    'turma_id' => 1
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                   'Cookie: ' . session_name() . '=' . session_id(),
        'content' => $postData
    ]
]);

$url = 'http://localhost/associacao-php/api/matriculas.php';
$response = file_get_contents($url, false, $context);
echo "<strong>Resposta:</strong> " . htmlspecialchars($response) . "<br><br>";

// Verificar se existem turmas disponíveis
echo "<h3>3. Verificando turmas disponíveis</h3>";
$db = new Database();
$stmt = $db->prepare("SELECT t.id, t.nome, a.nome as atividade_nome FROM turmas t LEFT JOIN atividades a ON t.atividade_id = a.id WHERE t.ativo = 1 LIMIT 5");
$stmt->execute();
$turmas = $stmt->fetchAll();

echo "<strong>Turmas encontradas:</strong><br>";
foreach ($turmas as $turma) {
    echo "ID: {$turma['id']}, Nome: {$turma['nome']}, Atividade: {$turma['atividade_nome']}<br>";
}

echo "<br><a href='pages/alunos.php?edit=12'>Voltar para a página do aluno</a>";
?>