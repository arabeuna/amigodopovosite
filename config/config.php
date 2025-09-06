<?php
// Configurações gerais do sistema

// Configurações do sistema
define('SITE_NAME', 'Associação Amigo do Povo');
define('SITE_VERSION', '1.0.0');
define('SITE_URL', 'http://localhost:8080');

// Configurações de timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro (desenvolvimento)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configurações de sessão (devem ser definidas ANTES de session_start())
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Mudar para 1 em produção com HTTPS
}

// Configurações de upload
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Configurações de paginação
define('ITEMS_PER_PAGE', 20);

// Funções auxiliares
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
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
?>