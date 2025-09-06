<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();

// Inicializar conexão com banco de dados
$database = new Database();
$db = $database;

// Se já estiver logado, redirecionar para dashboard
if (isset($_SESSION['user_id'])) {
    redirect('../pages/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        try {
            // Buscar usuário no banco
            $stmt = $db->query(
                "SELECT id, nome, email, senha, ativo FROM usuarios WHERE email = ? AND ativo = 1",
                [$email]
            );
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['senha'])) {
                // Login bem-sucedido
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_email'] = $user['email'];
                
                // Atualizar último login
                $db->query(
                    "UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?",
                    [$user['id']]
                );
                
                redirect('../pages/dashboard.php');
            } else {
                $error = 'Email ou senha incorretos.';
            }
        } catch (Exception $e) {
            $error = 'Erro interno do sistema. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" href="../assets/images/icon-192x192.png">
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-500 to-purple-600 min-h-screen flex items-center justify-center">
    <div class="form-container white w-full max-w-md">
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                    <img src="../assets/images/icon-192x192 (1).png" alt="Logo" class="logo logo-lg logo-login">
                </div>
            <h1 class="text-2xl font-bold text-gray-800"><?= SITE_NAME ?></h1>
            <p class="text-gray-600">Sistema de Gestão</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div class="form-field">
                <label for="email" class="form-label required">
                    <i class="fas fa-envelope"></i>Email
                </label>
                <input type="email" id="email" name="email" required
                       class="form-input"
                       placeholder="seu@email.com"
                       value="<?= htmlspecialchars($email ?? '') ?>">
            </div>
            
            <div class="form-field">
                <label for="password" class="form-label required">
                    <i class="fas fa-lock"></i>Senha
                </label>
                <input type="password" id="password" name="password" required
                       class="form-input"
                       placeholder="Sua senha">
            </div>
            
            <button type="submit" class="btn-primary w-full">
                <i class="fas fa-sign-in-alt mr-2"></i>Entrar
            </button>
        </form>
        
        <div class="mt-6 text-center text-sm text-gray-600">
            <p>Usuário padrão: admin@associacao.com</p>
            <p>Senha padrão: admin123</p>
        </div>
    </div>
</body>
</html>