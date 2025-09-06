<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

// Inicializar conexão com banco de dados
$database = new Database();
$db = $database;

// Buscar estatísticas
try {
    $total_alunos = $db->fetch("SELECT COUNT(*) as total FROM alunos WHERE ativo = 1")['total'] ?? 0;
    $total_atividades = $db->fetch("SELECT COUNT(*) as total FROM atividades WHERE ativo = 1")['total'] ?? 0;
    $total_turmas = $db->fetch("SELECT COUNT(*) as total FROM turmas WHERE ativo = 1")['total'] ?? 0;
    $alunos_ativos_mes = $db->fetch(
        "SELECT COUNT(DISTINCT aluno_id) as total FROM presencas 
         WHERE MONTH(data_presenca) = MONTH(CURRENT_DATE()) 
         AND YEAR(data_presenca) = YEAR(CURRENT_DATE())"
    )['total'] ?? 0;
} catch (Exception $e) {
    $total_alunos = $total_atividades = $total_turmas = $alunos_ativos_mes = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../assets/images/icon-192x192.png">
    <link rel="apple-touch-icon" href="../assets/images/icon-192x192.png">
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="../assets/css/enhanced.css" rel="stylesheet">
    <link href="../assets/css/desktop.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="header-enhanced text-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <img src="../assets/images/icon-192x192.png" alt="Logo" class="logo logo-md logo-header mr-3">
                    <h1 class="text-xl font-semibold"><?= SITE_NAME ?></h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">
                        <i class="fas fa-user mr-1"></i>
                        <?= htmlspecialchars($_SESSION['user_name']) ?>
                    </span>
                    <a href="../auth/logout.php" class="btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar -->
        <nav class="sidebar-enhanced">
            <div class="nav-menu">
                <ul class="space-y-1">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link active">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="alunos.php" class="nav-link">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            Alunos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="atividades.php" class="nav-link">
                            <i class="nav-icon fas fa-dumbbell"></i>
                            Atividades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="turmas.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            Turmas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="presencas.php" class="nav-link">
                            <i class="nav-icon fas fa-check-circle"></i>
                            Presenças
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="relatorios.php" class="nav-link">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            Relatórios
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content fade-in">
            <div class="page-header">
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Bem-vindo ao sistema de gestão da associação</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid slide-up">
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?= $total_alunos ?></div>
                            <div class="stat-label">Total de Alunos</div>
                        </div>
                        <div class="p-3 rounded-full" style="background: var(--desktop-gradient-primary); opacity: 0.1;">
                            <i class="fas fa-user-graduate text-2xl" style="color: #667eea;"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?= $total_atividades ?></div>
                            <div class="stat-label">Atividades</div>
                        </div>
                        <div class="p-3 rounded-full" style="background: var(--desktop-gradient-success); opacity: 0.1;">
                            <i class="fas fa-dumbbell text-2xl" style="color: #4facfe;"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?= $total_turmas ?></div>
                            <div class="stat-label">Turmas</div>
                        </div>
                        <div class="p-3 rounded-full" style="background: var(--desktop-gradient-secondary); opacity: 0.1;">
                            <i class="fas fa-users text-2xl" style="color: #f093fb;"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="stat-number"><?= $alunos_ativos_mes ?></div>
                            <div class="stat-label">Ativos este Mês</div>
                        </div>
                        <div class="p-3 rounded-full" style="background: var(--desktop-gradient-primary); opacity: 0.1;">
                            <i class="fas fa-check-circle text-2xl" style="color: #667eea;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ações Rápidas -->
            <div class="card-enhanced slide-up">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Ações Rápidas</h3>
                <div class="desktop-grid-3">
                    <a href="alunos.php?action=new" class="action-card hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center p-6 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-plus text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="font-semibold text-gray-900">Novo Aluno</p>
                                <p class="text-sm text-gray-600">Cadastrar novo aluno</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="presencas.php" class="action-card hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center p-6 rounded-lg bg-gradient-to-br from-green-50 to-green-100 hover:from-green-100 hover:to-green-200">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-green-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-check text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="font-semibold text-gray-900">Registrar Presença</p>
                                <p class="text-sm text-gray-600">Marcar presença dos alunos</p>
                            </div>
                        </div>
                    </a>
                    
                    <a href="relatorios.php" class="action-card hover:shadow-lg transition-all duration-300">
                        <div class="flex items-center p-6 rounded-lg bg-gradient-to-br from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-file-alt text-white text-xl"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="font-semibold text-gray-900">Gerar Relatório</p>
                                <p class="text-sm text-gray-600">Relatórios e estatísticas</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="desktop-grid-2">
                <div class="card-enhanced slide-up">
                    <div class="border-b border-gray-200 pb-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Atividades Recentes</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center p-3 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-plus text-white"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Novo aluno cadastrado</p>
                                <p class="text-xs text-gray-500">João Silva - há 2 horas</p>
                            </div>
                        </div>
                        <div class="flex items-center p-3 rounded-lg bg-green-50 hover:bg-green-100 transition-colors">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-green-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Presença registrada</p>
                                <p class="text-xs text-gray-500">Turma de Natação - há 3 horas</p>
                            </div>
                        </div>
                        <div class="flex items-center p-3 rounded-lg bg-purple-50 hover:bg-purple-100 transition-colors">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-purple-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-plus text-white"></i>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-900">Nova turma criada</p>
                                <p class="text-xs text-gray-500">Yoga Matinal - há 5 horas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-enhanced slide-up">
                    <div class="border-b border-gray-200 pb-4 mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Próximas Atividades</h3>
                    </div>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gradient-to-r from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 transition-all">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Natação Infantil</p>
                                <p class="text-xs text-gray-500 flex items-center"><i class="fas fa-clock mr-1"></i>Hoje às 14:00</p>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-500 text-white">
                                15 alunos
                            </span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gradient-to-r from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 transition-all">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Futebol Juvenil</p>
                                <p class="text-xs text-gray-500 flex items-center"><i class="fas fa-clock mr-1"></i>Hoje às 16:00</p>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-500 text-white">
                                22 alunos
                            </span>
                        </div>
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gradient-to-r from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 transition-all">
                            <div>
                                <p class="text-sm font-medium text-gray-900">Yoga Matinal</p>
                                <p class="text-xs text-gray-500 flex items-center"><i class="fas fa-clock mr-1"></i>Amanhã às 07:00</p>
                            </div>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-purple-500 text-white">
                                8 alunos
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/vite-blocker.js"></script>
    <script src="../assets/js/mobile.js"></script>
</body>
</html>