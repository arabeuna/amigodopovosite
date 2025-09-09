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
        // Buscar turmas por atividade
        if (isset($_GET['atividade_id'])) {
            $atividade_id = (int)$_GET['atividade_id'];
            
            $sql = "SELECT t.id, t.nome, t.horario_inicio, t.horario_fim, t.dias_semana, t.vagas_total,
                           (SELECT COUNT(*) FROM matriculas m WHERE m.turma_id = t.id AND m.status = 'ativa') as vagas_ocupadas
                    FROM turmas t 
                    WHERE t.atividade_id = ? AND t.status = 'ativa'
                    ORDER BY t.nome";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$atividade_id]);
            $turmas = $stmt->fetchAll();
            
            // Calcular vagas disponíveis e formatar horários
            foreach ($turmas as &$turma) {
                $turma['vagas_disponiveis'] = $turma['vagas_total'] - $turma['vagas_ocupadas'];
                $turma['horario_formatado'] = date('H:i', strtotime($turma['horario_inicio'])) . ' às ' . date('H:i', strtotime($turma['horario_fim']));
            }
            
            echo json_encode([
                'success' => true,
                'turmas' => $turmas
            ]);
        } else {
            // Buscar todas as turmas ativas
            $sql = "SELECT t.id, t.nome, t.horario_inicio, t.horario_fim, t.dias_semana, t.vagas_total,
                           a.nome as atividade_nome,
                           (SELECT COUNT(*) FROM matriculas m WHERE m.turma_id = t.id AND m.status = 'ativa') as vagas_ocupadas
                    FROM turmas t 
                    LEFT JOIN atividades a ON t.atividade_id = a.id
                    WHERE t.status = 'ativa'
                    ORDER BY a.nome, t.nome";
            
            $stmt = $db->prepare($sql);
            $stmt->execute();
            $turmas = $stmt->fetchAll();
            
            // Calcular vagas disponíveis e formatar horários
            foreach ($turmas as &$turma) {
                $turma['vagas_disponiveis'] = $turma['vagas_total'] - $turma['vagas_ocupadas'];
                $turma['horario_formatado'] = date('H:i', strtotime($turma['horario_inicio'])) . ' às ' . date('H:i', strtotime($turma['horario_fim']));
            }
            
            echo json_encode([
                'success' => true,
                'turmas' => $turmas
            ]);
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