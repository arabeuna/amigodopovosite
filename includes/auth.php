<?php
/**
 * Sistema de Autenticação e Controle de Permissões
 * Inclui logs de auditoria e gerenciamento de sessões
 */

class AuthSystem {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Realizar login do usuário
     */
    public function login($email, $password, $ip_address = null, $user_agent = null) {
        try {
            // Buscar usuário no banco com tipo
            $stmt = $this->db->query(
                "SELECT id, nome, email, senha, tipo, ativo FROM usuarios WHERE email = ? AND ativo = 1",
                [$email]
            );
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['tipo'];
                
                // Carregar permissões do usuário
                $this->loadUserPermissions($user['id']);
                
                // Criar sessão no banco
                $session_id = session_id();
                $this->createUserSession($user['id'], $session_id, $ip_address, $user_agent);
                
                // Atualizar último login
                $this->db->query(
                    "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?",
                    [$user['id']]
                );
                
                // Log de auditoria
                $this->logAction($user['id'], 'login', 'sistema', null, null, [
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent
                ]);
                
                return ['success' => true, 'user' => $user];
            } else {
                // Log de tentativa de login falhada
                $this->logAction(null, 'login_failed', 'sistema', null, null, [
                    'email' => $email,
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent
                ]);
                
                return ['success' => false, 'message' => 'Email ou senha incorretos.'];
            }
        } catch (Exception $e) {
            error_log('Erro no login: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno do sistema.'];
        }
    }
    
    /**
     * Realizar logout do usuário
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $session_id = session_id();
            
            // Finalizar sessão no banco
            $this->endUserSession($session_id);
            
            // Log de auditoria
            $this->logAction($user_id, 'logout', 'sistema');
            
            // Limpar sessão
            session_destroy();
            
            return true;
        }
        return false;
    }
    
    /**
     * Verificar se usuário está logado
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Verificar se usuário tem permissão específica
     */
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Master tem todas as permissões
        if ($_SESSION['user_type'] === 'master') {
            return true;
        }
        
        // Verificar permissões específicas
        return isset($_SESSION['permissions']) && in_array($permission, $_SESSION['permissions']);
    }
    
    /**
     * Verificar se usuário tem tipo específico
     */
    public function hasUserType($type) {
        return $this->isLoggedIn() && $_SESSION['user_type'] === $type;
    }
    
    /**
     * Carregar permissões do usuário
     */
    private function loadUserPermissions($user_id) {
        $stmt = $this->db->query("
            SELECT p.nome 
            FROM permissoes p
            INNER JOIN usuario_permissoes up ON p.id = up.permissao_id
            WHERE up.usuario_id = ? AND up.ativa = TRUE
        ", [$user_id]);
        
        $permissions = [];
        while ($row = $stmt->fetch()) {
            $permissions[] = $row['nome'];
        }
        
        $_SESSION['permissions'] = $permissions;
    }
    
    /**
     * Criar sessão do usuário no banco
     */
    private function createUserSession($user_id, $session_id, $ip_address, $user_agent) {
        $this->db->query("
            INSERT INTO sessoes_usuario (usuario_id, session_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?)
        ", [$user_id, $session_id, $ip_address, $user_agent]);
    }
    
    /**
     * Finalizar sessão do usuário no banco
     */
    private function endUserSession($session_id) {
        $this->db->query("
            UPDATE sessoes_usuario 
            SET data_logout = NOW(), ativa = FALSE 
            WHERE session_id = ?
        ", [$session_id]);
    }
    
    /**
     * Registrar ação no log de auditoria
     */
    public function logAction($user_id, $acao, $modulo, $tabela_afetada = null, $registro_id = null, $detalhes = [], $dados_anteriores = null, $dados_novos = null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Se detalhes foram passados, usar eles
        if (isset($detalhes['ip_address'])) {
            $ip_address = $detalhes['ip_address'];
        }
        if (isset($detalhes['user_agent'])) {
            $user_agent = $detalhes['user_agent'];
        }
        
        $this->db->query("
            INSERT INTO logs_auditoria 
            (usuario_id, acao, modulo, tabela_afetada, registro_id, dados_anteriores, dados_novos, ip_address, user_agent, detalhes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $user_id,
            $acao,
            $modulo,
            $tabela_afetada,
            $registro_id,
            $dados_anteriores ? json_encode($dados_anteriores) : null,
            $dados_novos ? json_encode($dados_novos) : null,
            $ip_address,
            $user_agent,
            !empty($detalhes) ? json_encode($detalhes) : null
        ]);
    }
    
    /**
     * Obter estatísticas de usuário para o dashboard master
     */
    public function getUserStats($user_id = null, $data_inicio = null, $data_fim = null) {
        $where_user = $user_id ? "AND usuario_id = $user_id" : "";
        $where_date = "";
        
        if ($data_inicio && $data_fim) {
            $where_date = "AND DATE(data_acao) BETWEEN '$data_inicio' AND '$data_fim'";
        }
        
        $stmt = $this->db->query("
            SELECT 
                usuario_id,
                u.nome as usuario_nome,
                COUNT(*) as total_acoes,
                SUM(CASE WHEN acao = 'login' THEN 1 ELSE 0 END) as total_logins,
                SUM(CASE WHEN acao = 'create' THEN 1 ELSE 0 END) as total_cadastros,
                SUM(CASE WHEN acao = 'update' THEN 1 ELSE 0 END) as total_atualizacoes,
                SUM(CASE WHEN acao = 'delete' THEN 1 ELSE 0 END) as total_exclusoes,
                SUM(CASE WHEN modulo = 'presencas' THEN 1 ELSE 0 END) as total_presencas,
                MIN(data_acao) as primeira_acao,
                MAX(data_acao) as ultima_acao
            FROM logs_auditoria l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE 1=1 $where_user $where_date
            GROUP BY usuario_id, u.nome
            ORDER BY total_acoes DESC
        ");
        
        return $stmt->fetchAll();
    }
    
    /**
     * Obter logs detalhados para auditoria
     */
    public function getAuditLogs($filtros = []) {
        $where = ["1=1"];
        $params = [];
        
        if (!empty($filtros['usuario_id'])) {
            $where[] = "l.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }
        
        if (!empty($filtros['acao'])) {
            $where[] = "l.acao = ?";
            $params[] = $filtros['acao'];
        }
        
        if (!empty($filtros['modulo'])) {
            $where[] = "l.modulo = ?";
            $params[] = $filtros['modulo'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where[] = "DATE(l.data_acao) >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where[] = "DATE(l.data_acao) <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $stmt = $this->db->query("
            SELECT 
                l.*,
                u.nome as usuario_nome,
                u.email as usuario_email
            FROM logs_auditoria l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE $where_clause
            ORDER BY l.data_acao DESC
            LIMIT 1000
        ", $params);
        
        return $stmt->fetchAll();
    }
}

/**
 * Função para verificar autenticação (compatibilidade)
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        redirect('../auth/login.php');
    }
}

/**
 * Função para verificar permissão específica
 */
function requirePermission($permission) {
    global $database;
    $auth = new AuthSystem($database);
    
    if (!$auth->hasPermission($permission)) {
        http_response_code(403);
        die('Acesso negado. Você não tem permissão para esta ação.');
    }
}

/**
 * Função para verificar se é usuário master
 */
function requireMaster() {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'master') {
        http_response_code(403);
        die('Acesso negado. Apenas usuários master podem acessar esta área.');
    }
}

?>