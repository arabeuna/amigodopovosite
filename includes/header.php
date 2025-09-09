<?php
// Verificar se a sessão já foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticação
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$auth = new AuthSystem($database);
requireAuth();

$user = [
    'id' => $_SESSION['user_id'] ?? null,
    'nome' => $_SESSION['user_name'] ?? null,
    'email' => $_SESSION['user_email'] ?? null,
    'tipo' => $_SESSION['user_type'] ?? null
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>Associação Amigo do Povo</title>
    
    <!-- Tailwind CSS -->
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link href="../assets/css/custom.css" rel="stylesheet">
    
    <style>
        .btn-primary {
            @apply bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 inline-flex items-center;
        }
        .btn-secondary {
            @apply bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 inline-flex items-center;
        }
        .btn-success {
            @apply bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 inline-flex items-center;
        }
        .btn-danger {
            @apply bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition-colors duration-200 inline-flex items-center;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <h1 class="text-xl font-bold text-gray-800">
                            <i class="fas fa-heart text-red-500 mr-2"></i>
                            Associação Amigo do Povo
                        </h1>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="flex items-center space-x-4">
                        <?php if ($user['tipo'] === 'master'): ?>
                            <a href="dashboard_master.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                            </a>
                            <a href="gerenciar_usuarios.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-users-cog mr-1"></i>Usuários
                            </a>
                            <a href="logs_auditoria.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-clipboard-list mr-1"></i>Logs
                            </a>
                        <?php elseif ($user['tipo'] === 'admin'): ?>
                            <a href="dashboard.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-1"></i>Dashboard
                            </a>
                        <?php endif; ?>
                        
                        <a href="alunos.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-users mr-1"></i>Alunos
                        </a>
                        <a href="turmas.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-chalkboard-teacher mr-1"></i>Turmas
                        </a>
                        <a href="atividades.php" class="text-gray-700 hover:text-blue-600 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-calendar-alt mr-1"></i>Atividades
                        </a>
                    </div>
                    
                    <div class="flex items-center space-x-3">
                        <div class="text-sm">
                            <span class="text-gray-700">Olá, </span>
                            <span class="font-medium text-gray-900"><?= htmlspecialchars($user['nome']) ?></span>
                            <span class="text-xs text-gray-500 ml-1">(<?= ucfirst($user['tipo']) ?>)</span>
                        </div>
                        <a href="../auth/logout.php" class="text-gray-700 hover:text-red-600 px-3 py-2 rounded-md text-sm font-medium">
                            <i class="fas fa-sign-out-alt mr-1"></i>Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <main class="min-h-screen">