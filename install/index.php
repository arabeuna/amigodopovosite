<?php
/**
 * Sistema de Instalação Automatizada
 * Para instalação na Hostinger ou outros servidores
 */

// Verifica se já foi instalado
if (file_exists('../config/database.php') && file_exists('../config/.installed')) {
    die('Sistema já instalado. Para reinstalar, remova os arquivos de configuração.');
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success = [];

// Processa formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            // Validação dos dados do banco
            $db_host = trim($_POST['db_host'] ?? '');
            $db_name = trim($_POST['db_name'] ?? '');
            $db_user = trim($_POST['db_user'] ?? '');
            $db_pass = $_POST['db_pass'] ?? '';
            
            if (empty($db_host) || empty($db_name) || empty($db_user)) {
                $errors[] = 'Todos os campos do banco de dados são obrigatórios (exceto senha)';
            } else {
                // Testa conexão
                try {
                    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Salva dados na sessão
                    session_start();
                    $_SESSION['install_data'] = [
                        'db_host' => $db_host,
                        'db_name' => $db_name,
                        'db_user' => $db_user,
                        'db_pass' => $db_pass
                    ];
                    
                    header('Location: ?step=2');
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Erro ao conectar com o banco de dados: ' . $e->getMessage();
                }
            }
            break;
            
        case 2:
            // Validação dos dados do administrador
            $admin_name = trim($_POST['admin_name'] ?? '');
            $admin_email = trim($_POST['admin_email'] ?? '');
            $admin_password = $_POST['admin_password'] ?? '';
            $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
            
            if (empty($admin_name) || empty($admin_email) || empty($admin_password)) {
                $errors[] = 'Todos os campos são obrigatórios';
            } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido';
            } elseif (strlen($admin_password) < 8) {
                $errors[] = 'A senha deve ter pelo menos 8 caracteres';
            } elseif ($admin_password !== $admin_password_confirm) {
                $errors[] = 'As senhas não coincidem';
            } else {
                session_start();
                $_SESSION['install_data']['admin_name'] = $admin_name;
                $_SESSION['install_data']['admin_email'] = $admin_email;
                $_SESSION['install_data']['admin_password'] = $admin_password;
                
                header('Location: ?step=3');
                exit;
            }
            break;
            
        case 3:
            // Executa instalação
            session_start();
            if (!isset($_SESSION['install_data'])) {
                header('Location: ?step=1');
                exit;
            }
            
            $install_data = $_SESSION['install_data'];
            
            try {
                // Conecta ao banco
                $pdo = new PDO(
                    "mysql:host={$install_data['db_host']};dbname={$install_data['db_name']};charset=utf8mb4",
                    $install_data['db_user'],
                    $install_data['db_pass']
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Executa SQL de criação das tabelas
                $sql = file_get_contents('database.sql');
                $pdo->exec($sql);
                
                // Cria usuário administrador
                require_once '../includes/Security.php';
                $hashed_password = Security::hashPassword($install_data['admin_password']);
                
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (nome, email, senha, tipo, status, data_cadastro) 
                    VALUES (?, ?, ?, 'admin', 'ativo', NOW())
                ");
                $stmt->execute([
                    $install_data['admin_name'],
                    $install_data['admin_email'],
                    $hashed_password
                ]);
                
                // Cria arquivo de configuração do banco
                $db_config = "<?php\n";
                $db_config .= "// Configurações do banco de dados\n";
                $db_config .= "define('DB_HOST', '{$install_data['db_host']}');\n";
                $db_config .= "define('DB_NAME', '{$install_data['db_name']}');\n";
                $db_config .= "define('DB_USER', '{$install_data['db_user']}');\n";
                $db_config .= "define('DB_PASS', '{$install_data['db_pass']}');\n";
                $db_config .= "define('DB_CHARSET', 'utf8mb4');\n";
                $db_config .= "?>";
                
                file_put_contents('../config/database.php', $db_config);
                
                // Cria arquivo de instalação concluída
                file_put_contents('../config/.installed', date('Y-m-d H:i:s'));
                
                // Cria diretórios necessários
                $dirs = ['../uploads', '../logs', '../backups'];
                foreach ($dirs as $dir) {
                    if (!is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                }
                
                // Cria arquivo .htaccess de segurança
                $htaccess_content = file_get_contents('htaccess_template.txt');
                file_put_contents('../.htaccess', $htaccess_content);
                
                $success[] = 'Instalação concluída com sucesso!';
                $step = 4;
                
            } catch (Exception $e) {
                $errors[] = 'Erro durante a instalação: ' . $e->getMessage();
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação - Associação Amigo do Povo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .step {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background: #2196F3;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #1976D2;
        }
        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .progress {
            background: #f0f0f0;
            height: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .progress-bar {
            background: #2196F3;
            height: 100%;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalação do Sistema</h1>
        
        <div class="progress">
            <div class="progress-bar" style="width: <?= ($step / 4) * 100 ?>%"></div>
        </div>
        
        <div class="step">
            Passo <?= $step ?> de 4
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success">
                <?php foreach ($success as $msg): ?>
                    <p><?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 1): ?>
            <h2>Configuração do Banco de Dados</h2>
            <p>Insira os dados de conexão com o banco de dados MySQL:</p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Host do Banco:</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Nome do Banco:</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Usuário:</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Senha:</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <button type="submit">Próximo</button>
            </form>
            
        <?php elseif ($step === 2): ?>
            <h2>Criar Administrador</h2>
            <p>Crie a conta do administrador do sistema:</p>
            
            <form method="POST">
                <div class="form-group">
                    <label for="admin_name">Nome Completo:</label>
                    <input type="text" id="admin_name" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">Email:</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="admin_password">Senha:</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                    <small>Mínimo 8 caracteres</small>
                </div>
                
                <div class="form-group">
                    <label for="admin_password_confirm">Confirmar Senha:</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                </div>
                
                <button type="submit">Próximo</button>
            </form>
            
        <?php elseif ($step === 3): ?>
            <h2>Confirmar Instalação</h2>
            <p>Clique em "Instalar" para criar o banco de dados e configurar o sistema:</p>
            
            <form method="POST">
                <button type="submit">Instalar Sistema</button>
            </form>
            
        <?php elseif ($step === 4): ?>
            <h2>Instalação Concluída!</h2>
            <p>O sistema foi instalado com sucesso. Você pode agora:</p>
            
            <ul>
                <li><a href="../pages/login.php">Fazer login no sistema</a></li>
                <li><a href="../index.php">Acessar a página inicial</a></li>
            </ul>
            
            <p><strong>Importante:</strong> Por segurança, remova a pasta "install" do servidor após a instalação.</p>
        <?php endif; ?>
    </div>
</body>
</html>