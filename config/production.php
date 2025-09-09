<?php
/**
 * Configurações específicas para produção na Hostinger
 * Este arquivo deve ser incluído apenas em ambiente de produção
 */

// Detectar se está em produção
if (!defined('IS_PRODUCTION')) {
    define('IS_PRODUCTION', !in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:8080']));
}

if (IS_PRODUCTION) {
    // Configurações de erro para produção
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    
    // Criar diretório de logs se não existir
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    ini_set('error_log', $log_dir . '/php_errors.log');
    
    // Configurações de sessão seguras
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    
    // Configurações de upload seguras
    ini_set('file_uploads', 1);
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size', '12M');
    ini_set('max_execution_time', 30);
    ini_set('memory_limit', '128M');
    
    // Desabilitar funções perigosas
    if (function_exists('ini_set')) {
        ini_set('disable_functions', 'exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source');
    }
}

/**
 * Configurações específicas da Hostinger
 */
class HostingerConfig {
    
    /**
     * Detectar configurações automáticas da Hostinger
     */
    public static function detectHostingerSettings() {
        $settings = [];
        
        // Detectar host do banco de dados
        if (isset($_SERVER['HTTP_HOST'])) {
            $domain = $_SERVER['HTTP_HOST'];
            $settings['db_host'] = 'localhost'; // Padrão Hostinger
            
            // Gerar sugestões baseadas no domínio
            $domain_parts = explode('.', $domain);
            $main_domain = $domain_parts[0];
            
            $settings['suggested_db_name'] = 'u' . substr(md5($domain), 0, 9) . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $main_domain);
            $settings['suggested_db_user'] = 'u' . substr(md5($domain), 0, 9) . '_user';
        }
        
        return $settings;
    }
    
    /**
     * Validar configurações do banco de dados
     */
    public static function validateDatabaseConfig() {
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            throw new Exception('Configurações do banco de dados não encontradas. Verifique o arquivo config/database.php');
        }
        
        // Testar conexão
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('Erro de conexão com banco: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configurar timezone baseado na localização
     */
    public static function setTimezone() {
        // Brasil como padrão
        date_default_timezone_set('America/Sao_Paulo');
        
        // Detectar timezone se possível
        if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $country = $_SERVER['HTTP_CF_IPCOUNTRY'];
            switch ($country) {
                case 'BR':
                    date_default_timezone_set('America/Sao_Paulo');
                    break;
                case 'US':
                    date_default_timezone_set('America/New_York');
                    break;
                case 'PT':
                    date_default_timezone_set('Europe/Lisbon');
                    break;
                default:
                    date_default_timezone_set('UTC');
            }
        }
    }
    
    /**
     * Configurar cache de arquivos
     */
    public static function setupFileCache() {
        $cache_dir = __DIR__ . '/../cache';
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        // Criar .htaccess para proteger cache
        $htaccess_content = "Order deny,allow\nDeny from all";
        file_put_contents($cache_dir . '/.htaccess', $htaccess_content);
    }
    
    /**
     * Limpar logs antigos
     */
    public static function cleanOldLogs($days = 30) {
        $log_dir = __DIR__ . '/../logs';
        if (!is_dir($log_dir)) return;
        
        $files = glob($log_dir . '/*.log');
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
    
    /**
     * Verificar requisitos do sistema
     */
    public static function checkSystemRequirements() {
        $requirements = [
            'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
            'MBString Extension' => extension_loaded('mbstring'),
            'JSON Extension' => extension_loaded('json'),
            'Session Support' => function_exists('session_start'),
            'GD Extension' => extension_loaded('gd'),
        ];
        
        $missing = [];
        foreach ($requirements as $requirement => $met) {
            if (!$met) {
                $missing[] = $requirement;
            }
        }
        
        return [
            'all_met' => empty($missing),
            'missing' => $missing,
            'requirements' => $requirements
        ];
    }
}

// Executar configurações automáticas se em produção
if (IS_PRODUCTION) {
    HostingerConfig::setTimezone();
    HostingerConfig::setupFileCache();
    
    // Limpar logs antigos uma vez por dia
    if (rand(1, 100) === 1) {
        HostingerConfig::cleanOldLogs();
    }
}
?>