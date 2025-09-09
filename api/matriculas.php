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
        // Buscar matrículas de um aluno
        if (isset($_GET['aluno_id'])) {
            $aluno_id = (int)$_GET['aluno_id'];
            
            $sql = "SELECT m.id, m.status, m.data_matricula, 
                           a.nome as atividade_nome, 
                           t.nome as turma_nome, t.horario_inicio, t.horario_fim
                    FROM matriculas m
                    LEFT JOIN atividades a ON m.atividade_id = a.id
                    LEFT JOIN turmas t ON m.turma_id = t.id
                    WHERE m.aluno_id = ? AND m.status = 'ativa'
                    ORDER BY m.data_matricula DESC";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$aluno_id]);
            $matriculas = $stmt->fetchAll();
            
            // Formatar datas
            foreach ($matriculas as &$matricula) {
                $matricula['data_matricula'] = date('d/m/Y', strtotime($matricula['data_matricula']));
            }
            
            echo json_encode([
                'success' => true,
                'matriculas' => $matriculas
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID do aluno não fornecido']);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $aluno_id = (int)($_POST['aluno_id'] ?? 0);
                $atividade_id = (int)($_POST['atividade_id'] ?? 0);
                $turma_id = (int)($_POST['turma_id'] ?? 0);
                
                if ($aluno_id <= 0 || $turma_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
                    break;
                }
                
                // Verificar se já existe matrícula ativa para esta turma
                $check_sql = "SELECT id FROM matriculas WHERE aluno_id = ? AND turma_id = ? AND status = 'ativa'";
                $check_stmt = $db->prepare($check_sql);
                $check_stmt->execute([$aluno_id, $turma_id]);
                
                if ($check_stmt->rowCount() > 0) {
                    echo json_encode(['success' => false, 'message' => 'Aluno já possui matrícula ativa nesta turma']);
                    break;
                }
                
                // Inserir nova matrícula
                $sql = "INSERT INTO matriculas (aluno_id, atividade_id, turma_id, data_matricula, status) VALUES (?, ?, ?, NOW(), 'ativa')";
                $stmt = $db->prepare($sql);
                
                if ($stmt->execute([$aluno_id, $atividade_id, $turma_id])) {
                    echo json_encode(['success' => true, 'message' => 'Matrícula criada com sucesso']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao criar matrícula']);
                }
                break;
                
            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? '';
                
                if ($id <= 0 || !in_array($status, ['ativa', 'suspensa', 'cancelada'])) {
                    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
                    break;
                }
                
                $sql = "UPDATE matriculas SET status = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                
                if ($stmt->execute([$status, $id])) {
                    echo json_encode(['success' => true, 'message' => 'Matrícula atualizada com sucesso']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao atualizar matrícula']);
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'ID inválido']);
                    break;
                }
                
                // Ao invés de deletar, marcar como cancelada
                $sql = "UPDATE matriculas SET status = 'cancelada' WHERE id = ?";
                $stmt = $db->prepare($sql);
                
                if ($stmt->execute([$id])) {
                    echo json_encode(['success' => true, 'message' => 'Matrícula cancelada com sucesso']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao cancelar matrícula']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Ação não reconhecida']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>