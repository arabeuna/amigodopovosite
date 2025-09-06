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
            $aluno_id = (int)$_POST['aluno_id'];
            $turma_id = (int)$_POST['turma_id'];
            $data_matricula = $_POST['data_matricula'] ?: date('Y-m-d');
            $observacoes = sanitize_input($_POST['observacoes']);
            
            if (empty($aluno_id) || empty($turma_id)) {
                $error = 'Aluno e turma são obrigatórios';
            } else {
                // Verificar se já existe matrícula ativa
                $checkStmt = $db->prepare("SELECT id FROM matriculas WHERE aluno_id = ? AND turma_id = ? AND status = 'ativa'");
                $checkStmt->execute([$aluno_id, $turma_id]);
                
                if ($checkStmt->fetch()) {
                    $error = 'Aluno já está matriculado nesta turma';
                } else {
                    // Verificar capacidade da turma
                    $capacidadeStmt = $db->prepare("
                        SELECT 
                            t.capacidade_maxima,
                            COUNT(m.id) as matriculados
                        FROM turmas t
                        LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'ativa'
                        WHERE t.id = ?
                        GROUP BY t.id
                    ");
                    $capacidadeStmt->execute([$turma_id]);
                    $capacidade = $capacidadeStmt->fetch();
                    
                    if ($capacidade && $capacidade['matriculados'] >= $capacidade['capacidade_maxima']) {
                        $error = 'Turma já atingiu a capacidade máxima';
                    } else {
                        $sql = "INSERT INTO matriculas (aluno_id, turma_id, data_matricula, observacoes) VALUES (?, ?, ?, ?)";
                        $stmt = $db->prepare($sql);
                        if ($stmt->execute([$aluno_id, $turma_id, $data_matricula, $observacoes])) {
                            $message = 'Matrícula realizada com sucesso!';
                        } else {
                            $error = 'Erro ao realizar matrícula';
                        }
                    }
                }
            }
            break;
            
        case 'update_status':
            $id = (int)$_POST['id'];
            $status = $_POST['status'];
            $data_cancelamento = ($status === 'cancelada') ? date('Y-m-d') : null;
            
            $sql = "UPDATE matriculas SET status = ?, data_cancelamento = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$status, $data_cancelamento, $id])) {
                $message = 'Status da matrícula atualizado com sucesso!';
            } else {
                $error = 'Erro ao atualizar status da matrícula';
            }
            break;
    }
}

// Buscar dados para os selects
$alunosStmt = $db->query("SELECT id, nome FROM alunos WHERE ativo = TRUE ORDER BY nome");
$alunos = $alunosStmt->fetchAll();

$turmasStmt = $db->query("
    SELECT 
        t.id, 
        t.nome,
        a.nome as atividade_nome,
        COUNT(m.id) as matriculados,
        t.capacidade_maxima
    FROM turmas t
    INNER JOIN atividades a ON t.atividade_id = a.id
    LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'ativa'
    WHERE t.ativo = TRUE
    GROUP BY t.id
    ORDER BY a.nome, t.nome
");
$turmas = $turmasStmt->fetchAll();

// Filtros
$search = $_GET['search'] ?? '';
$turma_filter = $_GET['turma'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (al.nome LIKE ? OR al.cpf LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($turma_filter)) {
    $where .= " AND m.turma_id = ?";
    $params[] = $turma_filter;
}

if (!empty($status_filter)) {
    $where .= " AND m.status = ?";
    $params[] = $status_filter;
}

$sql = "
    SELECT 
        m.*,
        al.nome as aluno_nome,
        al.cpf as aluno_cpf,
        al.telefone as aluno_telefone,
        al.celular as aluno_celular,
        t.nome as turma_nome,
        at.nome as atividade_nome
    FROM matriculas m
    INNER JOIN alunos al ON m.aluno_id = al.id
    INNER JOIN turmas t ON m.turma_id = t.id
    INNER JOIN atividades at ON t.atividade_id = at.id
    $where
    ORDER BY m.data_matricula DESC, al.nome
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$matriculas = $stmt->fetchAll();

// Contar total de matrículas
$countSql = "
    SELECT COUNT(*)
    FROM matriculas m
    INNER JOIN alunos al ON m.aluno_id = al.id
    INNER JOIN turmas t ON m.turma_id = t.id
    INNER JOIN atividades at ON t.atividade_id = at.id
    $where
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalMatriculas = $countStmt->fetchColumn();
$totalPages = ceil($totalMatriculas / $limit);

// Estatísticas
$statsStmt = $db->query("
    SELECT 
        COUNT(*) as total_matriculas,
        SUM(CASE WHEN status = 'ativa' THEN 1 ELSE 0 END) as matriculas_ativas,
        SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) as matriculas_canceladas,
        SUM(CASE WHEN status = 'suspensa' THEN 1 ELSE 0 END) as matriculas_suspensas
    FROM matriculas
");
$stats = $statsStmt->fetch();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Matrículas - <?php echo SITE_NAME; ?></title>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <link rel="stylesheet" href="../assets/css/enhanced.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold"><?php echo SITE_NAME; ?></h1>
                    <span class="text-blue-200">Gestão de Matrículas</span>
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
        <aside class="sidebar-enhanced text-white w-64 min-h-screen p-4">
            <div class="flex items-center mb-8">
                <img src="../assets/images/icon-192x192.png" alt="Logo" class="logo logo-sm logo-sidebar mr-3">
                <h2 class="text-xl font-bold"><?php echo SITE_NAME; ?></h2>
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
                <a href="atividades.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
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
                <a href="matriculas.php" class="flex items-center space-x-3 text-white bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Matrículas</span>
                </a>
                    <i class="fas fa-clipboard-list mr-3"></i> Matrículas
                </a>
                <a href="relatorios.php" class="flex items-center px-6 py-3 text-gray-700 hover:bg-blue-50 hover:text-blue-600">
                    <i class="fas fa-chart-bar mr-3"></i> Relatórios
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
                            <i class="fas fa-clipboard-list text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total de Matrículas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['total_matriculas']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Matrículas Ativas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['matriculas_ativas']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-pause-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Matrículas Suspensas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['matriculas_suspensas']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-red-100 text-red-600">
                            <i class="fas fa-times-circle text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Matrículas Canceladas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $stats['matriculas_canceladas']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Nova Matrícula</h2>
                </div>

                <!-- Formulário -->
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <input type="hidden" name="action" value="create">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Aluno *</label>
                        <select name="aluno_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Selecione um aluno</option>
                            <?php foreach ($alunos as $aluno): ?>
                                <option value="<?php echo $aluno['id']; ?>">
                                    <?php echo htmlspecialchars($aluno['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Turma *</label>
                        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Selecione uma turma</option>
                            <?php foreach ($turmas as $turma): ?>
                                <option value="<?php echo $turma['id']; ?>" 
                                        <?php echo ($turma['matriculados'] >= $turma['capacidade_maxima']) ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($turma['atividade_nome'] . ' - ' . $turma['nome']); ?>
                                    (<?php echo $turma['matriculados']; ?>/<?php echo $turma['capacidade_maxima']; ?>)
                                    <?php echo ($turma['matriculados'] >= $turma['capacidade_maxima']) ? ' - LOTADA' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data da Matrícula</label>
                        <input type="date" name="data_matricula" value="<?php echo date('Y-m-d'); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                        <input type="text" name="observacoes" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="lg:col-span-4">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition">
                            <i class="fas fa-plus mr-2"></i>Realizar Matrícula
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Matrículas -->
            <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Lista de Matrículas</h2>
                    <div class="flex items-center space-x-4">
                        <form method="GET" class="flex space-x-2">
                            <select name="turma" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todas as turmas</option>
                                <?php foreach ($turmas as $turma): ?>
                                    <option value="<?php echo $turma['id']; ?>" 
                                            <?php echo $turma_filter == $turma['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($turma['atividade_nome'] . ' - ' . $turma['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todos os status</option>
                                <option value="ativa" <?php echo $status_filter === 'ativa' ? 'selected' : ''; ?>>Ativa</option>
                                <option value="suspensa" <?php echo $status_filter === 'suspensa' ? 'selected' : ''; ?>>Suspensa</option>
                                <option value="cancelada" <?php echo $status_filter === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar por aluno ou CPF..." 
                                   class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full table-auto">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Matrícula</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($matriculas as $matricula): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($matricula['aluno_nome']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?php if ($matricula['aluno_cpf']): ?>
                                                CPF: <?php echo htmlspecialchars($matricula['aluno_cpf']); ?>
                                            <?php endif; ?>
                                            <?php if ($matricula['aluno_celular'] || $matricula['aluno_telefone']): ?>
                                                <br>Tel: <?php echo htmlspecialchars($matricula['aluno_celular'] ?: $matricula['aluno_telefone']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($matricula['turma_nome']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($matricula['atividade_nome']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d/m/Y', strtotime($matricula['data_matricula'])); ?>
                                        <?php if ($matricula['data_cancelamento']): ?>
                                            <br><span class="text-red-600">Cancelada: <?php echo date('d/m/Y', strtotime($matricula['data_cancelamento'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $statusColors = [
                                            'ativa' => 'bg-green-100 text-green-800',
                                            'suspensa' => 'bg-yellow-100 text-yellow-800',
                                            'cancelada' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusLabels = [
                                            'ativa' => 'Ativa',
                                            'suspensa' => 'Suspensa',
                                            'cancelada' => 'Cancelada'
                                        ];
                                        ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusColors[$matricula['status']]; ?>">
                                            <?php echo $statusLabels[$matricula['status']]; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($matricula['status'] === 'ativa'): ?>
                                            <form method="POST" class="inline mr-2">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $matricula['id']; ?>">
                                                <input type="hidden" name="status" value="suspensa">
                                                <button type="submit" class="text-yellow-600 hover:text-yellow-900" 
                                                        onclick="return confirm('Tem certeza que deseja suspender esta matrícula?')">
                                                    <i class="fas fa-pause"></i> Suspender
                                                </button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $matricula['id']; ?>">
                                                <input type="hidden" name="status" value="cancelada">
                                                <button type="submit" class="text-red-600 hover:text-red-900" 
                                                        onclick="return confirm('Tem certeza que deseja cancelar esta matrícula?')">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </button>
                                            </form>
                                        <?php elseif ($matricula['status'] === 'suspensa'): ?>
                                            <form method="POST" class="inline mr-2">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $matricula['id']; ?>">
                                                <input type="hidden" name="status" value="ativa">
                                                <button type="submit" class="text-green-600 hover:text-green-900">
                                                    <i class="fas fa-play"></i> Reativar
                                                </button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="id" value="<?php echo $matricula['id']; ?>">
                                                <input type="hidden" name="status" value="cancelada">
                                                <button type="submit" class="text-red-600 hover:text-red-900" 
                                                        onclick="return confirm('Tem certeza que deseja cancelar esta matrícula?')">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-gray-500">Matrícula cancelada</span>
                                        <?php endif; ?>
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
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $turma_filter ? '&turma=' . $turma_filter : ''; ?><?php echo $status_filter ? '&status=' . $status_filter : ''; ?>" 
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
</body>
</html>