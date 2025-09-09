<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

// Verificar se o usuário está logado e é master
$database = new Database();
$auth = new AuthSystem($database);

if (!$auth->isLoggedIn() || !$auth->hasUserType('master')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_id'])) {
        $user_id = (int)$_GET['user_id'];
        
        // Buscar permissões do usuário
        $stmt = $database->query("
            SELECT permissao_id 
            FROM usuario_permissoes 
            WHERE usuario_id = ? AND ativa = TRUE
        ", [$user_id]);
        
        $permissions = [];
        while ($row = $stmt->fetch()) {
            $permissions[] = $row['permissao_id'];
        }
        
        echo json_encode([
            'success' => true,
            'permissions' => $permissions
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido ou parâmetros inválidos'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>