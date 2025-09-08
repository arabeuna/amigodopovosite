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

// Processar filtros
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$atividade_id = $_GET['atividade_id'] ?? '';
$turma_id = $_GET['turma_id'] ?? '';
$relatorio_tipo = $_GET['relatorio_tipo'] ?? 'matriculas';

// Buscar atividades para o filtro
$atividadesStmt = $db->query("SELECT id, nome FROM atividades WHERE ativo = TRUE ORDER BY nome");
$atividades = $atividadesStmt->fetchAll();

// Buscar turmas para o filtro
$turmasWhere = $atividade_id ? "WHERE t.atividade_id = $atividade_id AND" : "WHERE";
$turmasStmt = $db->query("
    SELECT 
        t.id, 
        t.nome,
        a.nome as atividade_nome
    FROM turmas t
    INNER JOIN atividades a ON t.atividade_id = a.id
    $turmasWhere t.ativo = TRUE
    ORDER BY a.nome, t.nome
");
$turmas = $turmasStmt->fetchAll();

// Função para gerar relatório de matrículas
function getRelatorioMatriculas($db, $data_inicio, $data_fim, $atividade_id = null, $turma_id = null) {
    $where = "WHERE m.data_matricula BETWEEN ? AND ?";
    $params = [$data_inicio, $data_fim];
    
    if ($atividade_id) {
        $where .= " AND a.id = ?";
        $params[] = $atividade_id;
    }
    
    if ($turma_id) {
        $where .= " AND t.id = ?";
        $params[] = $turma_id;
    }
    
    $sql = "
        SELECT 
            m.*,
            al.nome as aluno_nome,
            al.cpf as aluno_cpf,
            al.data_nascimento,
            t.nome as turma_nome,
            a.nome as atividade_nome,
            TIMESTAMPDIFF(YEAR, al.data_nascimento, CURDATE()) as idade
        FROM matriculas m
        INNER JOIN alunos al ON m.aluno_id = al.id
        INNER JOIN turmas t ON m.turma_id = t.id
        INNER JOIN atividades a ON t.atividade_id = a.id
        $where
        ORDER BY m.data_matricula DESC, al.nome
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Função para gerar estatísticas gerais
function getEstatisticasGerais($db, $data_inicio, $data_fim, $atividade_id = null) {
    $where = "WHERE m.data_matricula BETWEEN ? AND ?";
    $params = [$data_inicio, $data_fim];
    
    if ($atividade_id) {
        $where .= " AND a.id = ?";
        $params[] = $atividade_id;
    }
    
    $sql = "
        SELECT 
            COUNT(*) as total_matriculas,
            SUM(CASE WHEN m.status = 'ativa' THEN 1 ELSE 0 END) as matriculas_ativas,
            SUM(CASE WHEN m.status = 'cancelada' THEN 1 ELSE 0 END) as matriculas_canceladas,
            SUM(CASE WHEN m.status = 'suspensa' THEN 1 ELSE 0 END) as matriculas_suspensas,
            COUNT(DISTINCT m.aluno_id) as alunos_unicos,
            COUNT(DISTINCT t.id) as turmas_utilizadas,
            COUNT(DISTINCT a.id) as atividades_utilizadas
        FROM matriculas m
        INNER JOIN alunos al ON m.aluno_id = al.id
        INNER JOIN turmas t ON m.turma_id = t.id
        INNER JOIN atividades a ON t.atividade_id = a.id
        $where
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Função para gerar relatório por atividade
function getRelatorioPorAtividade($db, $data_inicio, $data_fim) {
    $sql = "
        SELECT 
            a.nome as atividade_nome,
            COUNT(*) as total_matriculas,
            SUM(CASE WHEN m.status = 'ativa' THEN 1 ELSE 0 END) as matriculas_ativas,
            COUNT(DISTINCT m.aluno_id) as alunos_unicos,
            COUNT(DISTINCT t.id) as turmas_ativas
        FROM matriculas m
        INNER JOIN turmas t ON m.turma_id = t.id
        INNER JOIN atividades a ON t.atividade_id = a.id
        WHERE m.data_matricula BETWEEN ? AND ?
        GROUP BY a.id, a.nome
        ORDER BY total_matriculas DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$data_inicio, $data_fim]);
    return $stmt->fetchAll();
}

// Função para gerar relatório de faixa etária
function getRelatorioFaixaEtaria($db, $data_inicio, $data_fim, $atividade_id = null) {
    $where = "WHERE m.data_matricula BETWEEN ? AND ?";
    $params = [$data_inicio, $data_fim];
    
    if ($atividade_id) {
        $where .= " AND a.id = ?";
        $params[] = $atividade_id;
    }
    
    $sql = "
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(YEAR, al.data_nascimento, CURDATE()) < 18 THEN 'Menor de 18 anos'
                WHEN TIMESTAMPDIFF(YEAR, al.data_nascimento, CURDATE()) BETWEEN 18 AND 25 THEN '18-25 anos'
                WHEN TIMESTAMPDIFF(YEAR, al.data_nascimento, CURDATE()) BETWEEN 26 AND 35 THEN '26-35 anos'
                WHEN TIMESTAMPDIFF(YEAR, al.data_nascimento, CURDATE()) BETWEEN 36 AND 50 THEN '36-50 anos'
                WHEN TIMESTAMPDIFF(YEAR, al.data_nascimento, CURDATE()) BETWEEN 51 AND 65 THEN '51-65 anos'
                ELSE 'Acima de 65 anos'
            END as faixa_etaria,
            COUNT(*) as total_alunos
        FROM matriculas m
        INNER JOIN alunos al ON m.aluno_id = al.id
        INNER JOIN turmas t ON m.turma_id = t.id
        INNER JOIN atividades a ON t.atividade_id = a.id
        $where
        AND m.status = 'ativa'
        GROUP BY faixa_etaria
        ORDER BY 
            CASE faixa_etaria
                WHEN 'Menor de 18 anos' THEN 1
                WHEN '18-25 anos' THEN 2
                WHEN '26-35 anos' THEN 3
                WHEN '36-50 anos' THEN 4
                WHEN '51-65 anos' THEN 5
                ELSE 6
            END
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Função para gerar relatório de presença
function getRelatorioPresenca($db, $data_inicio, $data_fim, $atividade_id = null, $turma_id = null) {
    $params = [$data_inicio, $data_fim];
    $whereAtividade = '';
    $whereTurma = '';
    
    if ($atividade_id) {
        $whereAtividade = ' AND t.atividade_id = ?';
        $params[] = $atividade_id;
    }
    
    if ($turma_id) {
        $whereTurma = ' AND p.turma_id = ?';
        $params[] = $turma_id;
    }
    
    $sql = "
        SELECT 
            al.nome as aluno_nome,
            al.cpf as aluno_cpf,
            a.nome as atividade_nome,
            t.nome as turma_nome,
            COUNT(p.id) as total_aulas,
            SUM(CASE WHEN p.presente = 1 THEN 1 ELSE 0 END) as presencas,
            ROUND((SUM(CASE WHEN p.presente = 1 THEN 1 ELSE 0 END) / COUNT(p.id)) * 100, 1) as percentual_presenca
        FROM presencas p
        INNER JOIN alunos al ON p.aluno_id = al.id
        INNER JOIN turmas t ON p.turma_id = t.id
        INNER JOIN atividades a ON t.atividade_id = a.id
        WHERE p.data_presenca BETWEEN ? AND ?
        $whereAtividade
        $whereTurma
        GROUP BY p.aluno_id, p.turma_id
        ORDER BY al.nome, a.nome, t.nome
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Função para gerar relatório de desempenho das turmas
function getRelatorioDesempenhoTurmas($db, $data_inicio, $data_fim, $atividade_id = null) {
    $params = [$data_inicio, $data_fim];
    $whereAtividade = '';
    
    if ($atividade_id) {
        $whereAtividade = ' AND t.atividade_id = ?';
        $params[] = $atividade_id;
    }
    
    $sql = "
        SELECT 
            a.nome as atividade_nome,
            t.nome as turma_nome,
            t.capacidade_maxima,
            COUNT(DISTINCT m.aluno_id) as alunos_matriculados,
            ROUND((COUNT(DISTINCT m.aluno_id) / t.capacidade_maxima) * 100, 1) as ocupacao_percentual,
            COUNT(DISTINCT p.data_presenca) as aulas_realizadas,
            COALESCE(AVG(CASE WHEN p.presente = 1 THEN 1 ELSE 0 END) * 100, 0) as media_presenca
        FROM turmas t
        INNER JOIN atividades a ON t.atividade_id = a.id
        LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'ativa'
        LEFT JOIN presencas p ON t.id = p.turma_id AND p.data_presenca BETWEEN ? AND ?
        WHERE t.ativo = TRUE
        $whereAtividade
        GROUP BY t.id
        ORDER BY a.nome, t.nome
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Função para gerar relatório mensal de evolução
function getRelatorioEvolucaoMensal($db, $data_inicio, $data_fim) {
    $sql = "
        SELECT 
            DATE_FORMAT(m.data_matricula, '%Y-%m') as mes_ano,
            COUNT(*) as novas_matriculas,
            COUNT(DISTINCT m.aluno_id) as novos_alunos,
            COUNT(DISTINCT t.atividade_id) as atividades_utilizadas
        FROM matriculas m
        INNER JOIN turmas t ON m.turma_id = t.id
        WHERE m.data_matricula BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(m.data_matricula, '%Y-%m')
        ORDER BY mes_ano
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$data_inicio, $data_fim]);
    return $stmt->fetchAll();
}

// Gerar dados baseado no tipo de relatório
$dados = [];
$estatisticas = getEstatisticasGerais($db, $data_inicio, $data_fim, $atividade_id ?: null);

switch ($relatorio_tipo) {
    case 'matriculas':
        $dados = getRelatorioMatriculas($db, $data_inicio, $data_fim, $atividade_id ?: null, $turma_id ?: null);
        break;
    case 'atividades':
        $dados = getRelatorioPorAtividade($db, $data_inicio, $data_fim);
        break;
    case 'faixa_etaria':
        $dados = getRelatorioFaixaEtaria($db, $data_inicio, $data_fim, $atividade_id ?: null);
        break;
    case 'presenca':
        $dados = getRelatorioPresenca($db, $data_inicio, $data_fim, $atividade_id ?: null, $turma_id ?: null);
        break;
    case 'desempenho_turmas':
        $dados = getRelatorioDesempenhoTurmas($db, $data_inicio, $data_fim, $atividade_id ?: null);
        break;
    case 'evolucao_mensal':
        $dados = getRelatorioEvolucaoMensal($db, $data_inicio, $data_fim);
        break;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios - <?php echo SITE_NAME; ?></title>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/tailwind.min.css">
    <link rel="stylesheet" href="../assets/css/enhanced.css">
    <link rel="stylesheet" href="../assets/css/desktop.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold"><?php echo SITE_NAME; ?></h1>
                    <span class="text-blue-200">Relatórios</span>
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
                <img src="../assets/images/icon-192x192 (1).png" alt="Logo" class="logo logo-sm logo-sidebar mr-3">
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
                <a href="matriculas.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Matrículas</span>
                </a>
                <a href="relatorios.php" class="flex items-center space-x-3 text-white bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <!-- Filtros -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Filtros de Relatório</h2>
                
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Relatório</label>
                        <select name="relatorio_tipo" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="this.form.submit()">
                            <option value="matriculas" <?php echo $relatorio_tipo === 'matriculas' ? 'selected' : ''; ?>>Matrículas</option>
                            <option value="atividades" <?php echo $relatorio_tipo === 'atividades' ? 'selected' : ''; ?>>Por Atividade</option>
                            <option value="faixa_etaria" <?php echo $relatorio_tipo === 'faixa_etaria' ? 'selected' : ''; ?>>Faixa Etária</option>
                            <option value="presenca" <?php echo $relatorio_tipo === 'presenca' ? 'selected' : ''; ?>>Frequência e Presença</option>
                            <option value="desempenho_turmas" <?php echo $relatorio_tipo === 'desempenho_turmas' ? 'selected' : ''; ?>>Desempenho das Turmas</option>
                            <option value="evolucao_mensal" <?php echo $relatorio_tipo === 'evolucao_mensal' ? 'selected' : ''; ?>>Evolução Mensal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                        <input type="date" name="data_inicio" value="<?php echo $data_inicio; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                        <input type="date" name="data_fim" value="<?php echo $data_fim; ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Atividade</label>
                        <select name="atividade_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas as atividades</option>
                            <?php foreach ($atividades as $atividade): ?>
                                <option value="<?php echo $atividade['id']; ?>" 
                                        <?php echo $atividade_id == $atividade['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($atividade['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($relatorio_tipo === 'matriculas' || $relatorio_tipo === 'presenca'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
                        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Todas as turmas</option>
                            <?php foreach ($turmas as $turma): ?>
                                <option value="<?php echo $turma['id']; ?>" 
                                        <?php echo $turma_id == $turma['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($turma['atividade_nome'] . ' - ' . $turma['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition">
                            <i class="fas fa-search mr-2"></i>Gerar Relatório
                        </button>
                    </div>
                </form>
            </div>

            <!-- Estatísticas Gerais -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-clipboard-list text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total de Matrículas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $estatisticas['total_matriculas']; ?></p>
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
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $estatisticas['matriculas_ativas']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-users text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Alunos Únicos</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $estatisticas['alunos_unicos']; ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                            <i class="fas fa-chalkboard-teacher text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Turmas Utilizadas</p>
                            <p class="text-2xl font-semibold text-gray-900"><?php echo $estatisticas['turmas_utilizadas']; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Conteúdo do Relatório -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        <?php 
                        $titulos = [
                            'matriculas' => 'Relatório de Matrículas',
                            'atividades' => 'Relatório por Atividade',
                            'faixa_etaria' => 'Relatório por Faixa Etária',
                            'presenca' => 'Relatório de Frequência e Presença',
                            'desempenho_turmas' => 'Relatório de Desempenho das Turmas',
                            'evolucao_mensal' => 'Relatório de Evolução Mensal'
                        ];
                        echo $titulos[$relatorio_tipo];
                        ?>
                    </h2>
                    <div class="flex space-x-2">
                        <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md transition">
                            <i class="fas fa-print mr-2"></i>Imprimir
                        </button>
                        <button onclick="exportarCSV()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition">
                            <i class="fas fa-file-csv mr-2"></i>Exportar CSV
                        </button>
                    </div>
                </div>

                <?php if ($relatorio_tipo === 'matriculas'): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto" id="tabela-relatorio">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Idade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data Matrícula</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($dados as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($item['aluno_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['aluno_cpf'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $item['idade'] ? $item['idade'] . ' anos' : 'N/A'; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['atividade_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['turma_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('d/m/Y', strtotime($item['data_matricula'])); ?>
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
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusColors[$item['status']]; ?>">
                                                <?php echo $statusLabels[$item['status']]; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($relatorio_tipo === 'atividades'): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto" id="tabela-relatorio">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Matrículas</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matrículas Ativas</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos Únicos</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turmas Ativas</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($dados as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($item['atividade_nome']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['total_matriculas']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['matriculas_ativas']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['alunos_unicos']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['turmas_ativas']; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div>
                            <canvas id="graficoAtividades" width="400" height="200"></canvas>
                        </div>
                    </div>

                <?php elseif ($relatorio_tipo === 'faixa_etaria'): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto" id="tabela-relatorio">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faixa Etária</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total de Alunos</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentual</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    $totalGeral = array_sum(array_column($dados, 'total_alunos'));
                                    foreach ($dados as $item): 
                                        $percentual = $totalGeral > 0 ? round(($item['total_alunos'] / $totalGeral) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($item['faixa_etaria']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['total_alunos']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $percentual; ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div>
                            <canvas id="graficoFaixaEtaria" width="400" height="200"></canvas>
                        </div>
                    </div>

                <?php elseif ($relatorio_tipo === 'presenca'): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto" id="tabela-relatorio">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CPF</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Aulas</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presenças</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% Presença</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($dados as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($item['aluno_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['aluno_cpf'] ?: 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['atividade_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['turma_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $item['total_aulas']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $item['presencas']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $percentual = $item['percentual_presenca'];
                                            $cor = $percentual >= 80 ? 'text-green-600' : ($percentual >= 60 ? 'text-yellow-600' : 'text-red-600');
                                            ?>
                                            <span class="font-semibold <?php echo $cor; ?>">
                                                <?php echo $percentual; ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($relatorio_tipo === 'desempenho_turmas'): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full table-auto" id="tabela-relatorio">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacidade</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matriculados</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">% Ocupação</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aulas Realizadas</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Média Presença</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($dados as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($item['atividade_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($item['turma_nome']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $item['capacidade_maxima']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $item['alunos_matriculados']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $ocupacao = $item['ocupacao_percentual'];
                                            $cor = $ocupacao >= 80 ? 'text-green-600' : ($ocupacao >= 50 ? 'text-yellow-600' : 'text-red-600');
                                            ?>
                                            <span class="font-semibold <?php echo $cor; ?>">
                                                <?php echo $ocupacao; ?>%
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo $item['aulas_realizadas']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo round($item['media_presenca'], 1); ?>%
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php elseif ($relatorio_tipo === 'evolucao_mensal'): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto" id="tabela-relatorio">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mês/Ano</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Novas Matrículas</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Novos Alunos</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atividades Utilizadas</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($dados as $item): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo date('m/Y', strtotime($item['mes_ano'] . '-01')); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['novas_matriculas']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['novos_alunos']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $item['atividades_utilizadas']; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div>
                            <canvas id="graficoEvolucao" width="400" height="200"></canvas>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($dados)): ?>
                    <div class="text-center py-8">
                        <i class="fas fa-chart-bar text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg">Nenhum dado encontrado para os filtros selecionados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Função para exportar CSV
        function exportarCSV() {
            const tabela = document.getElementById('tabela-relatorio');
            if (!tabela) return;
            
            let csv = [];
            const linhas = tabela.querySelectorAll('tr');
            
            for (let i = 0; i < linhas.length; i++) {
                const linha = [];
                const colunas = linhas[i].querySelectorAll('td, th');
                
                for (let j = 0; j < colunas.length; j++) {
                    linha.push('"' + colunas[j].innerText.replace(/"/g, '""') + '"');
                }
                
                csv.push(linha.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'relatorio_<?php echo $relatorio_tipo; ?>_<?php echo date('Y-m-d'); ?>.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Gráficos
        <?php if ($relatorio_tipo === 'atividades' && !empty($dados)): ?>
        const ctxAtividades = document.getElementById('graficoAtividades').getContext('2d');
        new Chart(ctxAtividades, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($dados, 'atividade_nome')); ?>,
                datasets: [{
                    label: 'Matrículas Ativas',
                    data: <?php echo json_encode(array_column($dados, 'matriculas_ativas')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Matrículas Ativas por Atividade'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($relatorio_tipo === 'faixa_etaria' && !empty($dados)): ?>
        const ctxFaixaEtaria = document.getElementById('graficoFaixaEtaria').getContext('2d');
        new Chart(ctxFaixaEtaria, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($dados, 'faixa_etaria')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($dados, 'total_alunos')); ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 205, 86, 0.8)',
                        'rgba(75, 192, 192, 0.8)',
                        'rgba(153, 102, 255, 0.8)',
                        'rgba(255, 159, 64, 0.8)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Distribuição por Faixa Etária'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>

        <?php if ($relatorio_tipo === 'evolucao_mensal' && !empty($dados)): ?>
        const ctxEvolucao = document.getElementById('graficoEvolucao').getContext('2d');
        new Chart(ctxEvolucao, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($item) { return date('m/Y', strtotime($item['mes_ano'] . '-01')); }, $dados)); ?>,
                datasets: [{
                    label: 'Novas Matrículas',
                    data: <?php echo json_encode(array_column($dados, 'novas_matriculas')); ?>,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Novos Alunos',
                    data: <?php echo json_encode(array_column($dados, 'novos_alunos')); ?>,
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Evolução Mensal de Matrículas'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
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