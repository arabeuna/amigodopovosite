<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

// Verificar se o usuário está logado e é master
$database = new Database();
$auth = new AuthSystem($database);

if (!$auth->isLoggedIn()) {
    redirect('../auth/login.php');
}

if (!$auth->hasUserType('master')) {
    http_response_code(403);
    die('Acesso negado. Apenas usuários master podem acessar esta área.');
}

$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $nome = sanitize_input($_POST['nome'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $tipo = $_POST['tipo'] ?? 'user';
        $permissoes = $_POST['permissoes'] ?? [];
        
        if (empty($nome) || empty($email) || empty($senha)) {
            $error = 'Todos os campos são obrigatórios.';
        } else {
            try {
                // Verificar se email já existe
                $stmt = $database->query("SELECT id FROM usuarios WHERE email = ?", [$email]);
                if ($stmt->fetch()) {
                    $error = 'Este email já está em uso.';
                } else {
                    // Criar usuário
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $database->query(
                        "INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?, ?, ?, ?)",
                        [$nome, $email, $senha_hash, $tipo]
                    );
                    
                    $user_id = $database->connect()->lastInsertId();
                    
                    // Atribuir permissões
                    if (!empty($permissoes)) {
                        foreach ($permissoes as $permissao_id) {
                            $database->query(
                                "INSERT INTO usuario_permissoes (usuario_id, permissao_id, concedida_por) VALUES (?, ?, ?)",
                                [$user_id, $permissao_id, $_SESSION['user_id']]
                            );
                        }
                    }
                    
                    // Log de auditoria
                    $auth->logAction($_SESSION['user_id'], 'create', 'usuarios', 'usuarios', $user_id, [], null, [
                        'nome' => $nome,
                        'email' => $email,
                        'tipo' => $tipo
                    ]);
                    
                    $message = 'Usuário criado com sucesso!';
                }
            } catch (Exception $e) {
                $error = 'Erro ao criar usuário: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'update_permissions') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $permissoes = $_POST['permissoes'] ?? [];
        
        try {
            // Remover permissões existentes
            $database->query("DELETE FROM usuario_permissoes WHERE usuario_id = ?", [$user_id]);
            
            // Adicionar novas permissões
            foreach ($permissoes as $permissao_id) {
                $database->query(
                    "INSERT INTO usuario_permissoes (usuario_id, permissao_id, concedida_por) VALUES (?, ?, ?)",
                    [$user_id, $permissao_id, $_SESSION['user_id']]
                );
            }
            
            // Log de auditoria
            $auth->logAction($_SESSION['user_id'], 'update', 'usuarios', 'usuario_permissoes', $user_id);
            
            $message = 'Permissões atualizadas com sucesso!';
        } catch (Exception $e) {
            $error = 'Erro ao atualizar permissões: ' . $e->getMessage();
        }
    } elseif ($action === 'toggle_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        
        try {
            $database->query("UPDATE usuarios SET ativo = ? WHERE id = ?", [$status, $user_id]);
            
            // Log de auditoria
            $auth->logAction($_SESSION['user_id'], 'update', 'usuarios', 'usuarios', $user_id, [], null, ['ativo' => $status]);
            
            $message = $status ? 'Usuário ativado com sucesso!' : 'Usuário desativado com sucesso!';
        } catch (Exception $e) {
            $error = 'Erro ao alterar status: ' . $e->getMessage();
        }
    }
}

// Buscar usuários
$usuarios = $database->query("
    SELECT u.*, 
           COUNT(up.permissao_id) as total_permissoes,
           GROUP_CONCAT(p.nome) as permissoes_nomes
    FROM usuarios u
    LEFT JOIN usuario_permissoes up ON u.id = up.usuario_id AND up.ativa = TRUE
    LEFT JOIN permissoes p ON up.permissao_id = p.id
    GROUP BY u.id
    ORDER BY u.nome
")->fetchAll();

// Buscar todas as permissões
$permissoes = $database->query("
    SELECT * FROM permissoes 
    ORDER BY modulo, nome
")->fetchAll();

// Agrupar permissões por módulo
$permissoes_por_modulo = [];
foreach ($permissoes as $permissao) {
    $permissoes_por_modulo[$permissao['modulo']][] = $permissao;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../assets/images/icon-192x192.png">
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-users-cog mr-3"></i>Gerenciar Usuários
            </h1>
            <button onclick="openCreateUserModal()" class="btn-primary">
                <i class="fas fa-plus mr-2"></i>Novo Usuário
            </button>
        </div>
        
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle mr-2"></i><?= $message ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-triangle mr-2"></i><?= $error ?>
            </div>
        <?php endif; ?>
        
        <!-- Lista de Usuários -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissões</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Último Login</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold">
                                                <?= strtoupper(substr($usuario['nome'], 0, 2)) ?>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['nome']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($usuario['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $usuario['tipo'] === 'master' ? 'bg-purple-100 text-purple-800' : 
                                            ($usuario['tipo'] === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800') ?>">
                                        <?= ucfirst($usuario['tipo']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?= $usuario['total_permissoes'] ?> permissões
                                    </div>
                                    <?php if ($usuario['permissoes_nomes']): ?>
                                        <div class="text-xs text-gray-500 max-w-xs truncate" title="<?= htmlspecialchars($usuario['permissoes_nomes']) ?>">
                                            <?= htmlspecialchars($usuario['permissoes_nomes']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?= $usuario['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button onclick="openPermissionsModal(<?= $usuario['id'] ?>, '<?= htmlspecialchars($usuario['nome']) ?>')" 
                                                class="text-blue-600 hover:text-blue-900" title="Gerenciar Permissões">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('Tem certeza?')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $usuario['id'] ?>">
                                                <input type="hidden" name="status" value="<?= $usuario['ativo'] ? 0 : 1 ?>">
                                                <button type="submit" class="<?= $usuario['ativo'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' ?>" 
                                                        title="<?= $usuario['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="fas fa-<?= $usuario['ativo'] ? 'ban' : 'check' ?>"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Criar Usuário -->
    <div id="createUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">Criar Novo Usuário</h3>
                </div>
                <form method="POST" class="px-6 py-4">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome Completo</label>
                        <input type="text" name="nome" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Senha</label>
                        <input type="password" name="senha" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Usuário</label>
                        <select name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="user">Usuário</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Permissões</label>
                        <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-md p-2">
                            <?php foreach ($permissoes_por_modulo as $modulo => $perms): ?>
                                <div class="mb-2">
                                    <div class="font-semibold text-sm text-gray-700 mb-1"><?= ucfirst($modulo) ?></div>
                                    <?php foreach ($perms as $perm): ?>
                                        <label class="flex items-center mb-1">
                                            <input type="checkbox" name="permissoes[]" value="<?= $perm['id'] ?>" class="mr-2">
                                            <span class="text-sm"><?= $perm['descricao'] ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeCreateUserModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Criar Usuário
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Gerenciar Permissões -->
    <div id="permissionsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-medium text-gray-900">Gerenciar Permissões</h3>
                    <p class="text-sm text-gray-600" id="permissionsUserName"></p>
                </div>
                <form method="POST" class="px-6 py-4">
                    <input type="hidden" name="action" value="update_permissions">
                    <input type="hidden" name="user_id" id="permissionsUserId">
                    
                    <div class="max-h-60 overflow-y-auto">
                        <?php foreach ($permissoes_por_modulo as $modulo => $perms): ?>
                            <div class="mb-4">
                                <div class="font-semibold text-sm text-gray-700 mb-2"><?= ucfirst($modulo) ?></div>
                                <?php foreach ($perms as $perm): ?>
                                    <label class="flex items-center mb-2">
                                        <input type="checkbox" name="permissoes[]" value="<?= $perm['id'] ?>" class="mr-2 permission-checkbox">
                                        <span class="text-sm"><?= $perm['descricao'] ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closePermissionsModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                            Cancelar
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                            Salvar Permissões
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openCreateUserModal() {
            document.getElementById('createUserModal').classList.remove('hidden');
        }
        
        function closeCreateUserModal() {
            document.getElementById('createUserModal').classList.add('hidden');
        }
        
        function openPermissionsModal(userId, userName) {
            document.getElementById('permissionsUserId').value = userId;
            document.getElementById('permissionsUserName').textContent = 'Usuário: ' + userName;
            
            // Limpar checkboxes
            document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = false);
            
            // Carregar permissões atuais do usuário
            fetch(`../api/user_permissions.php?user_id=${userId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.permissions.forEach(permId => {
                            const checkbox = document.querySelector(`input[value="${permId}"]`);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                });
            
            document.getElementById('permissionsModal').classList.remove('hidden');
        }
        
        function closePermissionsModal() {
            document.getElementById('permissionsModal').classList.add('hidden');
        }
        
        // Fechar modais ao clicar fora
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('bg-opacity-50')) {
                closeCreateUserModal();
                closePermissionsModal();
            }
        });
    </script>
</body>
</html>