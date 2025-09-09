<?php
/**
 * Middleware de Logging para rastrear ações dos usuários
 * Este arquivo deve ser incluído em todas as páginas do sistema
 */

require_once __DIR__ . '/auth.php';

class LoggingMiddleware {
    private $auth;
    private $database;
    private $excluded_files = [
        'login.php',
        'logout.php',
        'export_logs.php',
        'api'
    ];
    
    public function __construct($database) {
        $this->database = $database;
        $this->auth = new AuthSystem($database);
    }
    
    /**
     * Inicializar o middleware de logging
     */
    public function init() {
        // Verificar se deve fazer log desta página
        if ($this->shouldLog()) {
            $this->logPageAccess();
        }
        
        // Registrar shutdown function para log de saída
        register_shutdown_function([$this, 'logPageExit']);
    }
    
    /**
     * Verificar se deve fazer log da página atual
     */
    private function shouldLog() {
        $current_file = basename($_SERVER['PHP_SELF']);
        
        // Não fazer log de arquivos excluídos
        foreach ($this->excluded_files as $excluded) {
            if (strpos($current_file, $excluded) !== false || strpos($_SERVER['REQUEST_URI'], $excluded) !== false) {
                return false;
            }
        }
        
        // Não fazer log se não estiver logado
        if (!$this->auth->isLoggedIn()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Fazer log do acesso à página
     */
    private function logPageAccess() {
        $page_info = $this->getPageInfo();
        
        // Só fazer log se o usuário estiver logado
        if (isset($_SESSION['user_id'])) {
            $this->auth->logAction(
                $_SESSION['user_id'],
                'view',
                $page_info['modulo'],
                "Acesso à página: {$page_info['pagina']}",
                null,
                [
                    'url' => $_SERVER['REQUEST_URI'],
                    'metodo' => $_SERVER['REQUEST_METHOD'],
                    'parametros' => $this->getRequestParameters()
                ]
            );
        }
    }
    
    /**
     * Fazer log da saída da página (chamado no shutdown)
     */
    public function logPageExit() {
        if (!$this->shouldLog()) {
            return;
        }
        
        $page_info = $this->getPageInfo();
        
        // Calcular tempo de permanência na página (se disponível)
        $tempo_permanencia = null;
        if (isset($_SESSION['page_start_time'])) {
            $tempo_permanencia = time() - $_SESSION['page_start_time'];
        }
        $_SESSION['page_start_time'] = time();
        
        $detalhes = "Saída da página: {$page_info['pagina']}";
        if ($tempo_permanencia !== null) {
            $detalhes .= " (permanência: {$tempo_permanencia}s)";
        }
        
        // Só fazer log se o usuário estiver logado
        if (isset($_SESSION['user_id'])) {
            $this->auth->logAction(
                $_SESSION['user_id'],
                'exit',
                $page_info['modulo'],
                $detalhes,
                null,
                [
                    'tempo_permanencia' => $tempo_permanencia,
                    'url' => $_SERVER['REQUEST_URI']
                ]
            );
        }
    }
    
    /**
     * Obter informações da página atual
     */
    private function getPageInfo() {
        $current_file = basename($_SERVER['PHP_SELF']);
        $current_dir = basename(dirname($_SERVER['PHP_SELF']));
        
        // Mapear páginas para módulos
        $page_modules = [
            'alunos.php' => 'alunos',
            'cadastro_aluno.php' => 'alunos',
            'editar_aluno.php' => 'alunos',
            'turmas.php' => 'turmas',
            'cadastro_turma.php' => 'turmas',
            'editar_turma.php' => 'turmas',
            'atividades.php' => 'atividades',
            'cadastro_atividade.php' => 'atividades',
            'editar_atividade.php' => 'atividades',
            'presencas.php' => 'presencas',
            'marcar_presenca.php' => 'presencas',
            'relatorios.php' => 'relatorios',
            'aniversariantes.php' => 'relatorios',
            'exportar_aniversariantes.php' => 'relatorios',
            'dashboard.php' => 'dashboard',
            'dashboard_master.php' => 'dashboard',
            'gerenciar_usuarios.php' => 'usuarios',
            'logs_auditoria.php' => 'auditoria',
            'index.php' => 'dashboard'
        ];
        
        $modulo = $page_modules[$current_file] ?? $current_dir;
        
        return [
            'pagina' => $current_file,
            'modulo' => $modulo,
            'diretorio' => $current_dir
        ];
    }
    
    /**
     * Obter parâmetros da requisição (GET e POST) de forma segura
     */
    private function getRequestParameters() {
        $params = [];
        
        // Parâmetros GET
        if (!empty($_GET)) {
            $params['GET'] = $this->sanitizeParameters($_GET);
        }
        
        // Parâmetros POST (excluindo senhas)
        if (!empty($_POST)) {
            $params['POST'] = $this->sanitizeParameters($_POST);
        }
        
        return $params;
    }
    
    /**
     * Sanitizar parâmetros removendo informações sensíveis
     */
    private function sanitizeParameters($params) {
        $sensitive_fields = ['password', 'senha', 'token', 'csrf_token', 'api_key'];
        
        foreach ($params as $key => $value) {
            // Remover campos sensíveis
            if (in_array(strtolower($key), $sensitive_fields)) {
                $params[$key] = '[HIDDEN]';
            }
            // Limitar tamanho de valores muito grandes
            elseif (is_string($value) && strlen($value) > 200) {
                $params[$key] = substr($value, 0, 200) . '...';
            }
        }
        
        return $params;
    }
    
    /**
     * Log de ação específica (para ser chamado manualmente nas páginas)
     */
    public function logAction($acao, $detalhes, $dados_adicionais = []) {
        if (!$this->shouldLog()) {
            return;
        }
        
        $page_info = $this->getPageInfo();
        
        // Só fazer log se o usuário estiver logado
        if (isset($_SESSION['user_id'])) {
            $this->auth->logAction(
                $_SESSION['user_id'],
                $acao,
                $page_info['modulo'],
                $detalhes,
                null,
                array_merge($dados_adicionais, [
                    'pagina' => $page_info['pagina'],
                    'url' => $_SERVER['REQUEST_URI']
                ])
            );
        }
    }
    
    /**
     * Log de operações CRUD
     */
    public function logCRUD($operacao, $tabela, $id = null, $dados = []) {
        $operacoes = [
            'C' => 'create',
            'R' => 'read',
            'U' => 'update',
            'D' => 'delete'
        ];
        
        $acao = $operacoes[$operacao] ?? $operacao;
        $detalhes = ucfirst($acao) . " em {$tabela}";
        
        if ($id) {
            $detalhes .= " (ID: {$id})";
        }
        
        $this->logAction($acao, $detalhes, [
            'tabela' => $tabela,
            'registro_id' => $id,
            'dados' => $this->sanitizeParameters($dados)
        ]);
    }
}

// Função global para facilitar o uso
function initLoggingMiddleware($database) {
    $middleware = new LoggingMiddleware($database);
    $middleware->init();
    return $middleware;
}

// Função global para log de ações
function logUserAction($database, $acao, $detalhes, $dados_adicionais = []) {
    $middleware = new LoggingMiddleware($database);
    $middleware->logAction($acao, $detalhes, $dados_adicionais);
}

// Função global para log de CRUD
function logCRUDAction($database, $operacao, $tabela, $id = null, $dados = []) {
    $middleware = new LoggingMiddleware($database);
    $middleware->logCRUD($operacao, $tabela, $id, $dados);
}
?>