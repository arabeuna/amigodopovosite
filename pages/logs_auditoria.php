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

// Parâmetros de paginação
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filtros
$filtros = [];
if (!empty($_GET['data_inicio'])) $filtros['data_inicio'] = $_GET['data_inicio'];
if (!empty($_GET['data_fim'])) $filtros['data_fim'] = $_GET['data_fim'];
if (!empty($_GET['usuario_id'])) $filtros['usuario_id'] = $_GET['usuario_id'];
if (!empty($_GET['acao'])) $filtros['acao'] = $_GET['acao'];
if (!empty($_GET['modulo'])) $filtros['modulo'] = $_GET['modulo'];
if (!empty($_GET['ip'])) $filtros['ip'] = $_GET['ip'];

// Buscar logs com paginação
$logs = $auth->getAuditLogs($filtros, $per_page, $offset);

// Contar total de registros para paginação
$where_conditions = [];
$params = [];

if (!empty($filtros['data_inicio'])) {
    $where_conditions[] = "DATE(data_acao) >= ?";
    $params[] = $filtros['data_inicio'];
}
if (!empty($filtros['data_fim'])) {
    $where_conditions[] = "DATE(data_acao) <= ?";
    $params[] = $filtros['data_fim'];
}
if (!empty($filtros['usuario_id'])) {
    $where_conditions[] = "usuario_id = ?";
    $params[] = $filtros['usuario_id'];
}
if (!empty($filtros['acao'])) {
    $where_conditions[] = "acao = ?";
    $params[] = $filtros['acao'];
}
if (!empty($filtros['modulo'])) {
    $where_conditions[] = "modulo = ?";
    $params[] = $filtros['modulo'];
}
if (!empty($filtros['ip'])) {
    $where_conditions[] = "ip_address LIKE ?";
    $params[] = '%' . $filtros['ip'] . '%';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$total_logs = $database->query("SELECT COUNT(*) as total FROM logs_auditoria $where_clause", $params)->fetch()['total'];
$total_pages = ceil($total_logs / $per_page);

// Dados para filtros
$usuarios = $database->query("SELECT id, nome FROM usuarios WHERE ativo = TRUE ORDER BY nome")->fetchAll();
$acoes = $database->query("SELECT DISTINCT acao FROM logs_auditoria ORDER BY acao")->fetchAll();
$modulos = $database->query("SELECT DISTINCT modulo FROM logs_auditoria ORDER BY modulo")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs de Auditoria - <?= SITE_NAME ?></title>
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
                <i class="fas fa-clipboard-list mr-3"></i>Logs de Auditoria
            </h1>
            <div class="flex space-x-2">
                <a href="dashboard_master.php" class="btn-secondary">
                    <i class="fas fa-arrow-left mr-2"></i>Voltar ao Dashboard
                </a>
                <button onclick="exportarLogs()" class="btn-primary">
                    <i class="fas fa-download mr-2"></i>Exportar CSV
                </button>
            </div>
        </div>
        
        <!-- Filtros Avançados -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-filter mr-2"></i>Filtros Avançados
            </h3>
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                    <input type="date" name="data_inicio" value="<?= $_GET['data_inicio'] ?? '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                    <input type="date" name="data_fim" value="<?= $_GET['data_fim'] ?? '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                    <select name="usuario_id" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?= $usuario['id'] ?>" <?= ($_GET['usuario_id'] ?? '') == $usuario['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($usuario['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ação</label>
                    <select name="acao" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Todas</option>
                        <?php foreach ($acoes as $acao): ?>
                            <option value="<?= $acao['acao'] ?>" <?= ($_GET['acao'] ?? '') == $acao['acao'] ? 'selected' : '' ?>>
                                <?= ucfirst($acao['acao']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Módulo</label>
                    <select name="modulo" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                        <option value="">Todos</option>
                        <?php foreach ($modulos as $modulo): ?>
                            <option value="<?= $modulo['modulo'] ?>" <?= ($_GET['modulo'] ?? '') == $modulo['modulo'] ? 'selected' : '' ?>>
                                <?= ucfirst($modulo['modulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">IP</label>
                    <input type="text" name="ip" value="<?= htmlspecialchars($_GET['ip'] ?? '') ?>" placeholder="Ex: 192.168.1.1" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
                <div class="md:col-span-3 lg:col-span-6 flex space-x-2">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search mr-2"></i>Filtrar
                    </button>
                    <a href="logs_auditoria.php" class="btn-secondary">
                        <i class="fas fa-times mr-2"></i>Limpar
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Informações dos Resultados -->
        <div class="bg-white rounded-lg shadow-md p-4 mb-6">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    Mostrando <?= count($logs) ?> de <?= number_format($total_logs) ?> registros
                    <?php if ($page > 1): ?>
                        (Página <?= $page ?> de <?= $total_pages ?>)
                    <?php endif; ?>
                </div>
                <div class="text-sm text-gray-600">
                    <?= $per_page ?> registros por página
                </div>
            </div>
        </div>
        
        <!-- Tabela de Logs -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Hora</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Módulo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalhes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User Agent</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-search text-4xl mb-4"></i>
                                    <p>Nenhum log encontrado com os filtros aplicados.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="font-medium"><?= date('d/m/Y', strtotime($log['data_acao'])) ?></div>
                                        <div class="text-gray-500"><?= date('H:i:s', strtotime($log['data_acao'])) ?></div>
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
                                                case 'view': echo 'bg-indigo-100 text-indigo-800'; break;
                                                default: echo 'bg-purple-100 text-purple-800';
                                            }
                                            ?>">
                                            <?= ucfirst($log['acao']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                            <?= ucfirst($log['modulo']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 max-w-xs">
                                        <div class="truncate" title="<?= htmlspecialchars($log['detalhes']) ?>">
                                            <?= htmlspecialchars($log['detalhes']) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <code class="bg-gray-100 px-2 py-1 rounded text-xs">
                                            <?= $log['ip_address'] ?>
                                        </code>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 max-w-xs">
                                        <div class="truncate" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                            <?= htmlspecialchars(substr($log['user_agent'], 0, 50)) ?><?= strlen($log['user_agent']) > 50 ? '...' : '' ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6 mt-6 rounded-lg shadow-md">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn-secondary">
                            Anterior
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn-primary">
                            Próximo
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Mostrando
                            <span class="font-medium"><?= $offset + 1 ?></span>
                            até
                            <span class="font-medium"><?= min($offset + $per_page, $total_logs) ?></span>
                            de
                            <span class="font-medium"><?= number_format($total_logs) ?></span>
                            resultados
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                   <?= $i == $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="relative inline-flex items-center px-2 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function exportarLogs() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.open('export_logs.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>