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
            $atividade_id = (int)$_POST['atividade_id'];
            $horario_inicio = $_POST['horario_inicio'];
            $horario_fim = $_POST['horario_fim'];
            $dias_semana = implode(',', $_POST['dias_semana'] ?? []);
            $capacidade_maxima = (int)$_POST['capacidade_maxima'];
            $professor = sanitize_input($_POST['professor']);
            
            if (empty($nome) || empty($atividade_id)) {
                $error = 'Nome da turma e atividade são obrigatórios';
            } else {
                $sql = "INSERT INTO turmas (nome, atividade_id, horario_inicio, horario_fim, dias_semana, capacidade_maxima, professor) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$nome, $atividade_id, $horario_inicio, $horario_fim, $dias_semana, $capacidade_maxima, $professor])) {
                    $message = 'Turma cadastrada com sucesso!';
                } else {
                    $error = 'Erro ao cadastrar turma';
                }
            }
            break;
            
        case 'update':
            $id = (int)$_POST['id'];
            $nome = sanitize_input($_POST['nome']);
            $atividade_id = (int)$_POST['atividade_id'];
            $horario_inicio = $_POST['horario_inicio'];
            $horario_fim = $_POST['horario_fim'];
            $dias_semana = implode(',', $_POST['dias_semana'] ?? []);
            $capacidade_maxima = (int)$_POST['capacidade_maxima'];
            $professor = sanitize_input($_POST['professor']);
            
            if (empty($nome) || empty($atividade_id)) {
                $error = 'Nome da turma e atividade são obrigatórios';
            } else {
                $sql = "UPDATE turmas SET nome=?, atividade_id=?, horario_inicio=?, horario_fim=?, dias_semana=?, capacidade_maxima=?, professor=? WHERE id=?";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$nome, $atividade_id, $horario_inicio, $horario_fim, $dias_semana, $capacidade_maxima, $professor, $id])) {
                    $message = 'Turma atualizada com sucesso!';
                } else {
                    $error = 'Erro ao atualizar turma';
                }
            }
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            $sql = "UPDATE turmas SET ativo = FALSE WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$id])) {
                $message = 'Turma desativada com sucesso!';
            } else {
                $error = 'Erro ao desativar turma';
            }
            break;
    }
}

// Buscar atividades para o select
$atividadesStmt = $db->query("SELECT id, nome FROM atividades WHERE ativo = TRUE ORDER BY nome");
$atividades = $atividadesStmt->fetchAll();

// Filtros
$search = $_GET['search'] ?? '';
$atividade_filter = $_GET['atividade'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$where = "WHERE t.ativo = TRUE";
$params = [];

if (!empty($search)) {
    $where .= " AND (t.nome LIKE ? OR t.professor LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($atividade_filter)) {
    $where .= " AND t.atividade_id = ?";
    $params[] = $atividade_filter;
}

$sql = "
    SELECT 
        t.*,
        a.nome as atividade_nome,
        COUNT(m.id) as alunos_matriculados
    FROM turmas t
    INNER JOIN atividades a ON t.atividade_id = a.id
    LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'ativa'
    $where
    GROUP BY t.id
    ORDER BY t.nome 
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$turmas = $stmt->fetchAll();

// Contar total de turmas
$countSql = "SELECT COUNT(*) FROM turmas t INNER JOIN atividades a ON t.atividade_id = a.id $where";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalTurmas = $countStmt->fetchColumn();
$totalPages = ceil($totalTurmas / $limit);

// Buscar turma para edição
$editTurma = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM turmas WHERE id = ?");
    $stmt->execute([$editId]);
    $editTurma = $stmt->fetch();
    if ($editTurma) {
        $editTurma['dias_semana_array'] = explode(',', $editTurma['dias_semana']);
    }
}

// Função para formatar dias da semana
function formatarDiasSemana($dias) {
    $diasMap = [
        'segunda' => 'Seg',
        'terca' => 'Ter',
        'quarta' => 'Qua',
        'quinta' => 'Qui',
        'sexta' => 'Sex',
        'sabado' => 'Sáb',
        'domingo' => 'Dom'
    ];
    
    $diasArray = explode(',', $dias);
    $diasFormatados = [];
    
    foreach ($diasArray as $dia) {
        if (isset($diasMap[$dia])) {
            $diasFormatados[] = $diasMap[$dia];
        }
    }
    
    return implode(', ', $diasFormatados);
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Turmas - <?php echo SITE_NAME; ?></title>
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
                    <span class="text-blue-200">Gestão de Turmas</span>
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
                <a href="turmas.php" class="flex items-center space-x-3 text-white bg-gray-700 p-3 rounded-lg transition duration-200">
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

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?php echo $editTurma ? 'Editar Turma' : 'Cadastrar Nova Turma'; ?>
                    </h2>
                    <?php if ($editTurma): ?>
                        <a href="turmas.php" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition">
                            <i class="fas fa-times mr-2"></i>Cancelar Edição
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Formulário -->
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                    <input type="hidden" name="action" value="<?php echo $editTurma ? 'update' : 'create'; ?>">
                    <?php if ($editTurma): ?>
                        <input type="hidden" name="id" value="<?php echo $editTurma['id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Turma *</label>
                        <input type="text" name="nome" value="<?php echo $editTurma ? htmlspecialchars($editTurma['nome']) : ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Atividade *</label>
                        <select name="atividade_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Selecione uma atividade</option>
                            <?php foreach ($atividades as $atividade): ?>
                                <option value="<?php echo $atividade['id']; ?>" 
                                        <?php echo ($editTurma && $editTurma['atividade_id'] == $atividade['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($atividade['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Professor</label>
                        <input type="text" name="professor" value="<?php echo $editTurma ? htmlspecialchars($editTurma['professor']) : ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Horário de Início</label>
                        <input type="time" name="horario_inicio" value="<?php echo $editTurma ? $editTurma['horario_inicio'] : ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Horário de Término</label>
                        <input type="time" name="horario_fim" value="<?php echo $editTurma ? $editTurma['horario_fim'] : ''; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Capacidade Máxima</label>
                        <input type="number" name="capacidade_maxima" value="<?php echo $editTurma ? $editTurma['capacidade_maxima'] : '30'; ?>" 
                               min="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div class="lg:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Dias da Semana</label>
                        <div class="grid grid-cols-4 md:grid-cols-7 gap-2">
                            <?php 
                            $dias = [
                                'segunda' => 'Segunda-feira',
                                'terca' => 'Terça-feira',
                                'quarta' => 'Quarta-feira',
                                'quinta' => 'Quinta-feira',
                                'sexta' => 'Sexta-feira',
                                'sabado' => 'Sábado',
                                'domingo' => 'Domingo'
                            ];
                            
                            foreach ($dias as $value => $label): 
                                $checked = $editTurma && in_array($value, $editTurma['dias_semana_array']) ? 'checked' : '';
                            ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="dias_semana[]" value="<?php echo $value; ?>" <?php echo $checked; ?>
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm text-gray-700"><?php echo $label; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo $editTurma ? 'Atualizar Turma' : 'Cadastrar Turma'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de Turmas -->
            <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">Lista de Turmas</h2>
                    <div class="flex items-center space-x-4">
                        <form method="GET" class="flex space-x-2">
                            <select name="atividade" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Todas as atividades</option>
                                <?php foreach ($atividades as $atividade): ?>
                                    <option value="<?php echo $atividade['id']; ?>" 
                                            <?php echo $atividade_filter == $atividade['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($atividade['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar por nome ou professor..." 
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
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horário</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dias</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($turmas as $turma): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($turma['nome']); ?></div>
                                        <div class="text-sm text-gray-500">Capacidade: <?php echo $turma['capacidade_maxima']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?php echo htmlspecialchars($turma['atividade_nome']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($turma['horario_inicio'] && $turma['horario_fim']): ?>
                                            <?php echo date('H:i', strtotime($turma['horario_inicio'])); ?> - 
                                            <?php echo date('H:i', strtotime($turma['horario_fim'])); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo formatarDiasSemana($turma['dias_semana']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($turma['professor']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <span class="text-sm font-medium text-gray-900">
                                                <?php echo $turma['alunos_matriculados']; ?>/<?php echo $turma['capacidade_maxima']; ?>
                                            </span>
                                            <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                <?php 
                                                $percentual = $turma['capacidade_maxima'] > 0 ? ($turma['alunos_matriculados'] / $turma['capacidade_maxima']) * 100 : 0;
                                                $cor = $percentual >= 90 ? 'bg-red-500' : ($percentual >= 70 ? 'bg-yellow-500' : 'bg-green-500');
                                                ?>
                                                <div class="<?php echo $cor; ?> h-2 rounded-full" style="width: <?php echo min($percentual, 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="?edit=<?php echo $turma['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="matriculas.php?turma=<?php echo $turma['id']; ?>" class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-users"></i> Alunos
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja desativar esta turma?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $turma['id']; ?>">
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
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $atividade_filter ? '&atividade=' . $atividade_filter : ''; ?>" 
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