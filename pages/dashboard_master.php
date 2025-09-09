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

// Filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$usuario_filtro = $_GET['usuario_id'] ?? '';
$acao_filtro = $_GET['acao'] ?? '';

// Estatísticas gerais
$stats = $auth->getUserStats(null, $data_inicio, $data_fim);

// Estatísticas do sistema
$sistema_stats = $database->query("
    SELECT 
        COUNT(DISTINCT usuario_id) as usuarios_ativos,
        COUNT(*) as total_acoes,
        COUNT(DISTINCT DATE(data_acao)) as dias_ativos
    FROM logs_auditoria 
    WHERE DATE(data_acao) BETWEEN ? AND ?
", [$data_inicio, $data_fim])->fetch();

// Ações mais recentes
$filtros = [
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim
];
if ($usuario_filtro) $filtros['usuario_id'] = $usuario_filtro;
if ($acao_filtro) $filtros['acao'] = $acao_filtro;

$logs_recentes = $auth->getAuditLogs($filtros);

// Usuários para filtro
$usuarios = $database->query("
    SELECT id, nome FROM usuarios 
    WHERE ativo = TRUE 
    ORDER BY nome
")->fetchAll();

// Ações para filtro
$acoes = $database->query("
    SELECT DISTINCT acao FROM logs_auditoria 
    ORDER BY acao
")->fetchAll();

// Sessões ativas
$sessoes_ativas = $database->query("
    SELECT 
        s.*,
        u.nome as usuario_nome,
        u.email as usuario_email,
        TIMESTAMPDIFF(MINUTE, s.data_login, NOW()) as minutos_online
    FROM sessoes_usuario s
    INNER JOIN usuarios u ON s.usuario_id = u.id
    WHERE s.ativa = TRUE
    ORDER BY s.data_login DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Master - <?= SITE_NAME ?></title>
    <link rel="icon" type="image/png" href="../assets/images/icon-192x192.png">
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <?php include '../includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">
                <i class="fas fa-tachometer-alt mr-3"></i>Dashboard Master
            </h1>
            <div class="flex space-x-2">
                <a href="gerenciar_usuarios.php" class="btn-primary">
                    <i class="fas fa-users-cog mr-2"></i>Gerenciar Usuários
                </a>
                <a href="logs_auditoria.php" class="btn-secondary">
                    <i class="fas fa-clipboard-list mr-2"></i>Logs Completos
                </a>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                    <input type="date" name="data_inicio" value="<?= $data_inicio ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                    <input type="date" name="data_fim" value="<?= $data_fim ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                    <select name="usuario_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Todos os usuários</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario['id'] ?>" <?= $usuario_filtro == $usuario['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($usuario['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ação</label>
                    <select name="acao" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Todas as ações</option>
                        <?php foreach ($acoes as $acao): ?>
                            <option value="<?= $acao['acao'] ?>" <?= $acao_filtro == $acao['acao'] ? 'selected' : '' ?>>
                                <?= ucfirst($acao['acao']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-4">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-filter mr-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Cards de Estatísticas -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-users text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Usuários Ativos</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $sistema_stats['usuarios_ativos'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total de Ações</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($sistema_stats['total_acoes']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-calendar-day text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Dias Ativos</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $sistema_stats['dias_ativos'] ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-clock text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Sessões Ativas</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= count($sessoes_ativas) ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Estatísticas por Usuário -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-user-chart mr-2"></i>Atividade por Usuário
                </h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Usuário</th>
                                <th class="text-center py-2">Logins</th>
                                <th class="text-center py-2">Cadastros</th>
                                <th class="text-center py-2">Atualizações</th>
                                <th class="text-center py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($stats, 0, 10) as $stat): ?>
                                <tr class="border-b">
                                    <td class="py-2">
                                        <div class="font-medium"><?= htmlspecialchars($stat['usuario_nome'] ?? 'Sistema') ?></div>
                                    </td>
                                    <td class="text-center py-2"><?= $stat['total_logins'] ?></td>
                                    <td class="text-center py-2"><?= $stat['total_cadastros'] ?></td>
                                    <td class="text-center py-2"><?= $stat['total_atualizacoes'] ?></td>
                                    <td class="text-center py-2 font-semibold"><?= $stat['total_acoes'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Sessões Ativas -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-users-online mr-2"></i>Sessões Ativas
                </h3>
                <div class="space-y-3">
                    <?php if (empty($sessoes_ativas)): ?>
                        <p class="text-gray-500 text-center py-4">Nenhuma sessão ativa no momento</p>
                    <?php else: ?>
                        <?php foreach ($sessoes_ativas as $sessao): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                    <div>
                                        <div class="font-medium"><?= htmlspecialchars($sessao['usuario_nome']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($sessao['usuario_email']) ?></div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium"><?= $sessao['minutos_online'] ?> min</div>
                                    <div class="text-xs text-gray-500"><?= date('H:i', strtotime($sessao['data_login'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Logs Recentes -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-history mr-2"></i>Atividades Recentes
            </h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Módulo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach (array_slice($logs_recentes, 0, 20) as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('d/m/Y H:i:s', strtotime($log['data_acao'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($log['usuario_nome'] ?? 'Sistema') ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($log['usuario_email'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        switch($log['acao']) {
                                            case 'login': echo 'bg-green-100 text-green-800'; break;
                                            case 'logout': echo 'bg-gray-100 text-gray-800'; break;
                                            case 'create': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'update': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'delete': echo 'bg-red-100 text-red-800'; break;
                                            default: echo 'bg-purple-100 text-purple-800';
                                        }
                                        ?>">
                                        <?= ucfirst($log['acao']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= ucfirst($log['modulo']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $log['ip_address'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($logs_recentes) > 20): ?>
                <div class="mt-4 text-center">
                    <a href="logs_auditoria.php" class="text-blue-600 hover:text-blue-800">
                        Ver todos os logs (<?= count($logs_recentes) ?> registros)
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh da página a cada 30 segundos
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>