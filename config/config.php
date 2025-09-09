<?php
// Configurações gerais do sistema

// Configurações do sistema
define('SITE_NAME', 'Associação Amigo do Povo');
define('SITE_VERSION', '1.0.0');

// Detecta ambiente (desenvolvimento ou produção)
define('IS_PRODUCTION', isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost');

// URL base do site
if (IS_PRODUCTION) {
    define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
} else {
    define('SITE_URL', 'http://localhost:8080');
}

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro
if (IS_PRODUCTION) {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Configurações de sessão seguras
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', IS_PRODUCTION ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    ini_set('session.name', 'ASSOC_SESSID');
    
    // Regenera ID da sessão periodicamente
    if (isset($_SESSION['last_regeneration'])) {
        if (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutos
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    } else {
        $_SESSION['last_regeneration'] = time();
    }
}

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Configurações de paginação
define('ITEMS_PER_PAGE', 20);

// Configurações de segurança
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos
define('SESSION_TIMEOUT', 3600); // 1 hora

// Carrega classe de segurança
require_once __DIR__ . '/../includes/Security.php';

// Define headers de segurança
Security::setSecurityHeaders();

// Funções auxiliares
function sanitize_input($data) {
    return Security::sanitizeInput($data);
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function flash_message($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash_message($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Função para log de segurança
function security_log($message, $level = 'INFO') {
    $log_file = __DIR__ . '/../logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = "[{$timestamp}] [{$level}] IP: {$ip} | UA: {$user_agent} | {$message}" . PHP_EOL;
    
    // Cria diretório de logs se não existir
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Função para verificar se arquivo é seguro
function is_safe_file($filename, $content = null) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    // Verifica extensão
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return false;
    }
    
    // Verifica conteúdo se fornecido
    if ($content !== null) {
        // Verifica por código PHP em arquivos que não deveriam ter
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif']) && 
            (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false)) {
            return false;
        }
    }
    
    return true;
}
?>