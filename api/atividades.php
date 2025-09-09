<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Inicializar conexão com banco de dados
$db = new Database();

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Buscar todas as atividades ativas
        $sql = "SELECT id, nome, descricao FROM atividades WHERE ativo = 1 ORDER BY nome";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $atividades = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'atividades' => $atividades
        ]);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>