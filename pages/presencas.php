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
        case 'marcar_presenca':
            $data_presenca = $_POST['data_presenca'] ?? date('Y-m-d');
            $turma_id = (int)$_POST['turma_id'];
            $presencas = $_POST['presencas'] ?? [];
            
            if (empty($turma_id)) {
                $error = 'Turma é obrigatória';
            } else {
                try {
                    $db->beginTransaction();
                    
                    // Buscar matrículas ativas da turma
                    $matriculasStmt = $db->prepare("
                        SELECT m.id as matricula_id, m.aluno_id, al.nome as aluno_nome
                        FROM matriculas m
                        INNER JOIN alunos al ON m.aluno_id = al.id
                        WHERE m.turma_id = ? AND m.status = 'ativa'
                        ORDER BY al.nome
                    ");
                    $matriculasStmt->execute([$turma_id]);
                    $matriculas = $matriculasStmt->fetchAll();
                    
                    foreach ($matriculas as $matricula) {
                        $presente = isset($presencas[$matricula['aluno_id']]);
                        
                        // Verificar se já existe registro de presença
                        $checkStmt = $db->prepare("SELECT id FROM presencas WHERE aluno_id = ? AND turma_id = ? AND data_presenca = ?");
                        $checkStmt->execute([$matricula['aluno_id'], $turma_id, $data_presenca]);
                        
                        if ($checkStmt->fetch()) {
                            // Atualizar presença existente
                            $updateStmt = $db->prepare("UPDATE presencas SET presente = ? WHERE aluno_id = ? AND turma_id = ? AND data_presenca = ?");
                            $updateStmt->execute([$presente, $matricula['aluno_id'], $turma_id, $data_presenca]);
                        } else {
                            // Inserir nova presença
                            $insertStmt = $db->prepare("INSERT INTO presencas (matricula_id, aluno_id, turma_id, data_presenca, presente) VALUES (?, ?, ?, ?, ?)");
                            $insertStmt->execute([$matricula['matricula_id'], $matricula['aluno_id'], $turma_id, $data_presenca, $presente]);
                        }
                    }
                    
                    $db->commit();
                    $message = 'Presenças registradas com sucesso!';
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Erro ao registrar presenças: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Buscar turmas para o select
$turmasStmt = $db->query("
    SELECT 
        t.id, 
        t.nome,
        a.nome as atividade_nome,
        COUNT(m.id) as alunos_matriculados
    FROM turmas t
    INNER JOIN atividades a ON t.atividade_id = a.id
    LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'ativa'
    WHERE t.ativo = TRUE
    GROUP BY t.id
    ORDER BY a.nome, t.nome
");
$turmas = $turmasStmt->fetchAll();

// Filtros
$turma_selecionada = $_GET['turma'] ?? '';
$data_selecionada = $_GET['data'] ?? date('Y-m-d');

// Buscar alunos da turma selecionada
$alunos = [];
$presencas_existentes = [];
if ($turma_selecionada) {
    $alunosStmt = $db->prepare("
        SELECT 
            m.id as matricula_id,
            m.aluno_id,
            al.nome as aluno_nome,
            al.cpf
        FROM matriculas m
        INNER JOIN alunos al ON m.aluno_id = al.id
        WHERE m.turma_id = ? AND m.status = 'ativa'
        ORDER BY al.nome
    ");
    $alunosStmt->execute([$turma_selecionada]);
    $alunos = $alunosStmt->fetchAll();
    
    // Buscar presenças já registradas para a data
    $presencasStmt = $db->prepare("
        SELECT aluno_id, presente
        FROM presencas
        WHERE turma_id = ? AND data_presenca = ?
    ");
    $presencasStmt->execute([$turma_selecionada, $data_selecionada]);
    $presencas_result = $presencasStmt->fetchAll();
    
    foreach ($presencas_result as $presenca) {
        $presencas_existentes[$presenca['aluno_id']] = $presenca['presente'];
    }
}

// Buscar estatísticas de presença
$estatisticas = [];
if ($turma_selecionada) {
    $statsStmt = $db->prepare("
        SELECT 
            al.nome as aluno_nome,
            COUNT(p.id) as total_aulas,
            SUM(CASE WHEN p.presente = 1 THEN 1 ELSE 0 END) as presencas,
            ROUND((SUM(CASE WHEN p.presente = 1 THEN 1 ELSE 0 END) / COUNT(p.id)) * 100, 1) as percentual
        FROM matriculas m
        INNER JOIN alunos al ON m.aluno_id = al.id
        LEFT JOIN presencas p ON m.aluno_id = p.aluno_id AND p.turma_id = m.turma_id
        WHERE m.turma_id = ? AND m.status = 'ativa'
        GROUP BY m.aluno_id, al.nome
        ORDER BY al.nome
    ");
    $statsStmt->execute([$turma_selecionada]);
    $estatisticas = $statsStmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Controle de Presenças - <?php echo SITE_NAME; ?></title>
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
                    <h1 class="text-xl font-semibold"><?php echo SITE_NAME; ?></h1>
                    <span class="text-blue-200 ml-4">Controle de Presenças</span>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm">
                        <i class="fas fa-user mr-1"></i>
                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
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
                <a href="presencas.php" class="flex items-center space-x-3 text-white bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-check-circle"></i>
                    <span>Presenças</span>
                </a>
                <a href="matriculas.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Matrículas</span>
                </a>
                    </li>
                    <li>
                        <a href="relatorios.php" class="flex items-center px-4 py-2 text-gray-300 hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-chart-bar mr-3"></i>
                            Relatórios
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 p-6">
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Controle de Presenças</h2>
                <p class="text-gray-600">Registre a presença dos alunos nas turmas</p>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Selecionar Turma e Data</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
                        <select name="turma" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                            <option value="">Selecione uma turma</option>
                            <?php foreach ($turmas as $turma): ?>
                                <option value="<?php echo $turma['id']; ?>" <?php echo ($turma_selecionada == $turma['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($turma['atividade_nome'] . ' - ' . $turma['nome']); ?>
                                    (<?php echo $turma['alunos_matriculados']; ?> alunos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data</label>
                        <input type="date" name="data" value="<?php echo $data_selecionada; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               onchange="this.form.submit()">
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-search mr-2"></i>Filtrar
                        </button>
                    </div>
                </form>
            </div>

            <?php if ($turma_selecionada && !empty($alunos)): ?>
                <!-- Lista de Chamada -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        Lista de Chamada - <?php echo date('d/m/Y', strtotime($data_selecionada)); ?>
                    </h3>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="marcar_presenca">
                        <input type="hidden" name="turma_id" value="<?php echo $turma_selecionada; ?>">
                        <input type="hidden" name="data_presenca" value="<?php echo $data_selecionada; ?>">
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Presente</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($alunos as $aluno): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($aluno['aluno_nome']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($aluno['cpf']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <label class="inline-flex items-center">
                                                    <input type="checkbox" 
                                                           name="presencas[<?php echo $aluno['aluno_id']; ?>]" 
                                                           value="1"
                                                           <?php echo (isset($presencas_existentes[$aluno['aluno_id']]) && $presencas_existentes[$aluno['aluno_id']]) ? 'checked' : ''; ?>
                                                           class="form-checkbox h-5 w-5 text-green-600 rounded focus:ring-green-500">
                                                    <span class="ml-2 text-sm text-gray-700">Presente</span>
                                                </label>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 flex justify-between items-center">
                            <div class="text-sm text-gray-600">
                                Total de alunos: <?php echo count($alunos); ?>
                            </div>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save mr-2"></i>Salvar Presenças
                            </button>
                        </div>
                    </form>
                </div>
            <?php elseif ($turma_selecionada): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Nenhum aluno matriculado nesta turma.
                </div>
            <?php endif; ?>

            <?php if (!empty($estatisticas)): ?>
                <!-- Estatísticas de Presença -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Estatísticas de Presença</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total de Aulas</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Presenças</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Percentual</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($estatisticas as $stat): ?>
                                    <?php 
                                    $percentual = $stat['percentual'] ?? 0;
                                    $statusClass = '';
                                    $statusText = '';
                                    
                                    if ($percentual >= 75) {
                                        $statusClass = 'bg-green-100 text-green-800';
                                        $statusText = 'Excelente';
                                    } elseif ($percentual >= 60) {
                                        $statusClass = 'bg-yellow-100 text-yellow-800';
                                        $statusText = 'Bom';
                                    } elseif ($percentual >= 40) {
                                        $statusClass = 'bg-orange-100 text-orange-800';
                                        $statusText = 'Regular';
                                    } else {
                                        $statusClass = 'bg-red-100 text-red-800';
                                        $statusText = 'Baixo';
                                    }
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($stat['aluno_nome']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                            <?php echo $stat['total_aulas']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                            <?php echo $stat['presencas']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900">
                                            <?php echo $percentual; ?>%
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Marcar/desmarcar todos
        function toggleAll() {
            const checkboxes = document.querySelectorAll('input[name^="presencas["]');
            const masterCheckbox = document.getElementById('toggle-all');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = masterCheckbox.checked;
            });
        }
        
        // Adicionar botão para marcar/desmarcar todos
        document.addEventListener('DOMContentLoaded', function() {
            const tableHeader = document.querySelector('thead tr');
            if (tableHeader) {
                const lastTh = tableHeader.querySelector('th:last-child');
                if (lastTh) {
                    lastTh.innerHTML = `
                        <div class="flex items-center justify-center">
                            <label class="inline-flex items-center">
                                <input type="checkbox" id="toggle-all" onchange="toggleAll()" 
                                       class="form-checkbox h-4 w-4 text-blue-600 rounded focus:ring-blue-500">
                                <span class="ml-1 text-xs">Todos</span>
                            </label>
                        </div>
                    `;
                }
            }
        });
    </script>
    <script src="../assets/js/mobile.js"></script>
</body>
</html>