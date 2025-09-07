<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

// Inicializar conexão com banco de dados
$database = new Database();
$db = $database;

$message = '';
$error = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $nome = sanitize_input($_POST['nome']);
            $descricao = sanitize_input($_POST['descricao']);
            $categoria = sanitize_input($_POST['categoria']);
            $idade_minima = (int)$_POST['idade_minima'];
            $idade_maxima = (int)$_POST['idade_maxima'];
            $capacidade_maxima = (int)$_POST['capacidade_maxima'];
            
            if (empty($nome)) {
                $error = 'Nome da atividade é obrigatório';
            } else {
                $sql = "INSERT INTO atividades (nome, descricao, categoria, idade_minima, idade_maxima, capacidade_maxima) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$nome, $descricao, $categoria, $idade_minima, $idade_maxima, $capacidade_maxima])) {
                    $message = 'Atividade cadastrada com sucesso!';
                } else {
                    $error = 'Erro ao cadastrar atividade';
                }
            }
            break;
            
        case 'update':
            $id = (int)$_POST['id'];
            $nome = sanitize_input($_POST['nome']);
            $descricao = sanitize_input($_POST['descricao']);
            $categoria = sanitize_input($_POST['categoria']);
            $idade_minima = (int)$_POST['idade_minima'];
            $idade_maxima = (int)$_POST['idade_maxima'];
            $capacidade_maxima = (int)$_POST['capacidade_maxima'];
            
            if (empty($nome)) {
                $error = 'Nome da atividade é obrigatório';
            } else {
                $sql = "UPDATE atividades SET nome=?, descricao=?, categoria=?, idade_minima=?, idade_maxima=?, capacidade_maxima=? WHERE id=?";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$nome, $descricao, $categoria, $idade_minima, $idade_maxima, $capacidade_maxima, $id])) {
                    $message = 'Atividade atualizada com sucesso!';
                } else {
                    $error = 'Erro ao atualizar atividade';
                }
            }
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            $sql = "UPDATE atividades SET ativo = FALSE WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$id])) {
                $message = 'Atividade desativada com sucesso!';
            } else {
                $error = 'Erro ao desativar atividade';
            }
            break;
    }
}

// Buscar atividades
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$where = "WHERE ativo = TRUE";
$params = [];

if (!empty($search)) {
    $where .= " AND (nome LIKE ? OR categoria LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
}

$sql = "SELECT * FROM atividades $where ORDER BY nome LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$atividades = $stmt->fetchAll();

// Contar total de atividades
$countSql = "SELECT COUNT(*) FROM atividades $where";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalAtividades = $countStmt->fetchColumn();
$totalPages = ceil($totalAtividades / $limit);

// Buscar atividade para edição
$editAtividade = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM atividades WHERE id = ?");
    $stmt->execute([$editId]);
    $editAtividade = $stmt->fetch();
}

// Buscar estatísticas das atividades
$statsStmt = $db->query("
    SELECT 
        a.id,
        a.nome,
        COUNT(t.id) as total_turmas,
        COALESCE(SUM(CASE WHEN t.ativo = TRUE THEN 1 ELSE 0 END), 0) as turmas_ativas,
        COALESCE(COUNT(m.id), 0) as total_matriculas
    FROM atividades a
    LEFT JOIN turmas t ON a.id = t.atividade_id
    LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'ativa'
    WHERE a.ativo = TRUE
    GROUP BY a.id, a.nome
    ORDER BY a.nome
");
$estatisticas = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Atividades - <?php echo SITE_NAME; ?></title>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/tailwind.min.css">
    <link rel="stylesheet" href="../assets/css/enhanced.css">
    <link rel="stylesheet" href="../assets/css/desktop.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold"><?php echo SITE_NAME; ?></h1>
                    <span class="text-blue-200">Gestão de Atividades</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span>Olá, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="../auth/logout.php" class="bg-blue-700 hover:bg-blue-800 px-4 py-2 rounded transition">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar -->
        <aside class="sidebar-enhanced text-white w-64 min-h-screen p-4" id="sidebar">
            <!-- Toggle Button -->
            <button id="sidebarToggle" class="sidebar-toggle-btn" title="Recolher/Expandir Menu">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <div class="flex items-center mb-8">
                <img src="../assets/images/icon-192x192.png" alt="Logo" class="logo logo-sm logo-sidebar mr-3">
                <h2 class="text-xl font-bold sidebar-title"><?php echo SITE_NAME; ?></h2>
            </div>
            <nav class="sidebar-nav space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="alunos.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-user-graduate"></i>
                    <span>Alunos</span>
                </a>
                <a href="atividades.php" class="flex items-center space-x-3 text-white bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-dumbbell"></i>
                    <span>Atividades</span>
                </a>
                <a href="turmas.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-users"></i>
                    <span>Turmas</span>
                </a>
                <a href="presencas.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-check-circle"></i>
                    <span>Presenças</span>
                </a>
                <a href="matriculas.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Matrículas</span>
                </a>
                <a href="relatorios.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-dumbbell text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total de Atividades</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo count($atividades); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-chalkboard-teacher text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total de Turmas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo array_sum(array_column($estatisticas, 'total_turmas')); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total de Matrículas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo array_sum(array_column($estatisticas, 'total_matriculas')); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-chart-line text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Turmas Ativas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo array_sum(array_column($estatisticas, 'turmas_ativas')); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?php echo $editAtividade ? 'Editar Atividade' : 'Cadastrar Nova Atividade'; ?>
                    </h2>
                    <?php if ($editAtividade): ?>
                        <a href="atividades.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition">
                            <i class="fas fa-times mr-2"></i>Cancelar Edição
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Formulário -->
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                    <input type="hidden" name="action" value="<?php echo $editAtividade ? 'update' : 'create'; ?>">
                    <?php if ($editAtividade): ?>
                        <input type="hidden" name="id" value="<?php echo $editAtividade['id']; ?>">
                    <?php endif; ?>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Atividade *</label>
                        <input type="text" name="nome" value="<?php echo $editAtividade ? htmlspecialchars($editAtividade['nome']) : ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Categoria</label>
                        <select name="categoria" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Selecione uma categoria</option>
                            <option value="Esporte" <?php echo ($editAtividade && $editAtividade['categoria'] === 'Esporte') ? 'selected' : ''; ?>>Esporte</option>
                            <option value="Arte" <?php echo ($editAtividade && $editAtividade['categoria'] === 'Arte') ? 'selected' : ''; ?>>Arte</option>
                            <option value="Educação" <?php echo ($editAtividade && $editAtividade['categoria'] === 'Educação') ? 'selected' : ''; ?>>Educação</option>
                            <option value="Cultura" <?php echo ($editAtividade && $editAtividade['categoria'] === 'Cultura') ? 'selected' : ''; ?>>Cultura</option>
                            <option value="Saúde" <?php echo ($editAtividade && $editAtividade['categoria'] === 'Saúde') ? 'selected' : ''; ?>>Saúde</option>
                            <option value="Tecnologia" <?php echo ($editAtividade && $editAtividade['categoria'] === 'Tecnologia') ? 'selected' : ''; ?>>Tecnologia</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Idade Mínima</label>
                        <input type="number" name="idade_minima" value="<?php echo $editAtividade ? $editAtividade['idade_minima'] : '0'; ?>" 
                               min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Idade Máxima</label>
                        <input type="number" name="idade_maxima" value="<?php echo $editAtividade ? $editAtividade['idade_maxima'] : '100'; ?>" 
                               min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Capacidade Máxima</label>
                        <input type="number" name="capacidade_maxima" value="<?php echo $editAtividade ? $editAtividade['capacidade_maxima'] : '50'; ?>" 
                               min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                        <textarea name="descricao" rows="3" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $editAtividade ? htmlspecialchars($editAtividade['descricao']) : ''; ?></textarea>
                    </div>

                    <div class="lg:col-span-3">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo $editAtividade ? 'Atualizar Atividade' : 'Cadastrar Atividade'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Atividades -->
            <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Lista de Atividades</h2>
                    <div class="flex items-center space-x-4">
                        <form method="GET" class="flex">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar por nome ou categoria..." 
                                   class="px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-r-md transition">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faixa Etária</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacidade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turmas</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($atividades as $atividade): ?>
                                <?php 
                                $stats = array_filter($estatisticas, function($stat) use ($atividade) {
                                    return $stat['id'] == $atividade['id'];
                                });
                                $stats = reset($stats);
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($atividade['nome']); ?></div>
                                        <?php if ($atividade['descricao']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars(substr($atividade['descricao'], 0, 50)) . (strlen($atividade['descricao']) > 50 ? '...' : ''); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($atividade['categoria']): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($atividade['categoria']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $atividade['idade_minima']; ?> - <?php echo $atividade['idade_maxima']; ?> anos
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $atividade['capacidade_maxima']; ?> pessoas
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div class="flex items-center space-x-2">
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs">
                                                <?php echo $stats ? $stats['turmas_ativas'] : 0; ?> ativas
                                            </span>
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs">
                                                <?php echo $stats ? $stats['total_matriculas'] : 0; ?> alunos
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?edit=<?php echo $atividade['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="turmas.php?atividade=<?php echo $atividade['id']; ?>" class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-chalkboard-teacher"></i> Turmas
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja desativar esta atividade?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $atividade['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Desativar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex justify-center mt-6">
                        <nav class="flex space-x-2">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                                   class="px-3 py-2 rounded <?php echo $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/mobile.js"></script>
    
    <script>
        // Toggle do Sidebar
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('main');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                    
                    // Salvar estado no localStorage
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                });
            }
            
            // Restaurar estado do sidebar ao carregar a página
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>