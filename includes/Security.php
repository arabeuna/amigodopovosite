<?php
/**
 * Classe de Segurança para proteção do sistema
 * Implementa proteção CSRF, validação de entrada e sanitização
 */
class Security {
    
    /**
     * Gera token CSRF
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Valida token CSRF
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitiza entrada de dados
     */
    public static function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $data);
        }
        
        $data = trim($data);
        
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            case 'sql':
                return addslashes($data);
            default:
                return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * Valida entrada de dados
     */
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : null;
            
            // Verifica se é obrigatório
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = "O campo {$field} é obrigatório";
                continue;
            }
            
            // Se não é obrigatório e está vazio, pula validação
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                continue;
            }
            
            // Validação de tipo
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = "O campo {$field} deve ser um email válido";
                        }
                        break;
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field] = "O campo {$field} deve ser uma URL válida";
                        }
                        break;
                    case 'int':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field] = "O campo {$field} deve ser um número inteiro";
                        }
                        break;
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[$field] = "O campo {$field} deve ser um número decimal";
                        }
                        break;
                }
            }
            
            // Validação de tamanho mínimo
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = "O campo {$field} deve ter pelo menos {$rule['min_length']} caracteres";
            }
            
            // Validação de tamanho máximo
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = "O campo {$field} deve ter no máximo {$rule['max_length']} caracteres";
            }
            
            // Validação de padrão (regex)
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = isset($rule['pattern_message']) ? $rule['pattern_message'] : "O campo {$field} não atende ao padrão exigido";
            }
        }
        
        return $errors;
    }
    
    /**
     * Previne ataques XSS
     */
    public static function preventXSS($data) {
        if (is_array($data)) {
            return array_map([self::class, 'preventXSS'], $data);
        }
        
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Gera senha hash segura
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterações
            'threads' => 3          // 3 threads
        ]);
    }
    
    /**
     * Verifica senha hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Limita tentativas de login
     */
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) { // 15 minutos
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => $now];
        }
        
        $data = $_SESSION[$key];
        
        // Reset se passou do tempo limite
        if ($now - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => $now];
            $data = $_SESSION[$key];
        }
        
        return $data['count'] < $maxAttempts;
    }
    
    /**
     * Registra tentativa de login
     */
    public static function recordAttempt($identifier) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
        }
        
        $_SESSION[$key]['count']++;
    }
    
    /**
     * Gera nonce para CSP
     */
    public static function generateNonce() {
        return base64_encode(random_bytes(16));
    }
    
    /**
     * Define headers de segurança
     */
    public static function setSecurityHeaders($nonce = null) {
        // Previne clickjacking
        header('X-Frame-Options: DENY');
        
        // Previne MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Ativa proteção XSS do browser
        header('X-XSS-Protection: 1; mode=block');
        
        // Força HTTPS (apenas em produção)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Content Security Policy
        $csp = "default-src 'self'; ";
        $csp .= "script-src 'self' 'unsafe-inline'" . ($nonce ? " 'nonce-{$nonce}'" : '') . "; ";
        $csp .= "style-src 'self' 'unsafe-inline'; ";
        $csp .= "img-src 'self' data: https:; ";
        $csp .= "font-src 'self'; ";
        $csp .= "connect-src 'self'; ";
        $csp .= "frame-ancestors 'none';";
        
        header("Content-Security-Policy: {$csp}");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
?>