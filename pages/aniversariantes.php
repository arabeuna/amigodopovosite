<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

$database = new Database();
$pdo = $database->connect();

// Parâmetros de filtro
$filtro_tipo = $_GET['filtro_tipo'] ?? 'mes';
$filtro_valor = $_GET['filtro_valor'] ?? date('m');
$mes_atual = date('m');
$ano_atual = date('Y');

// Função para calcular idade
function calcularIdade($data_nascimento) {
    if (!$data_nascimento) return null;
    $hoje = new DateTime();
    $nascimento = new DateTime($data_nascimento);
    return $hoje->diff($nascimento)->y;
}

// Função para obter aniversariantes
function obterAniversariantes($pdo, $tipo, $valor) {
    $sql = "";
    $params = [];
    
    switch ($tipo) {
        case 'mes':
            $sql = "SELECT * FROM alunos WHERE MONTH(data_nascimento) = ? AND ativo = 1 ORDER BY DAY(data_nascimento), nome";
            $params = [$valor];
            break;
            
        case 'dia':
            $sql = "SELECT * FROM alunos WHERE DATE_FORMAT(data_nascimento, '%m-%d') = ? AND ativo = 1 ORDER BY nome";
            $params = [$valor];
            break;
            
        case 'semana':
            // Calcular início e fim da semana
            $inicio_semana = date('Y-m-d', strtotime($valor));
            $fim_semana = date('Y-m-d', strtotime($valor . ' +6 days'));
            
            $sql = "SELECT * FROM alunos WHERE 
                    DATE_FORMAT(CONCAT(YEAR(CURDATE()), '-', MONTH(data_nascimento), '-', DAY(data_nascimento)), '%Y-%m-%d') 
                    BETWEEN ? AND ? AND ativo = 1 ORDER BY data_nascimento, nome";
            $params = [$inicio_semana, $fim_semana];
            break;
            
        case 'hoje':
            $sql = "SELECT * FROM alunos WHERE MONTH(data_nascimento) = MONTH(CURDATE()) AND DAY(data_nascimento) = DAY(CURDATE()) AND ativo = 1 ORDER BY nome";
            $params = [];
            break;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obter aniversariantes baseado no filtro
$aniversariantes = obterAniversariantes($pdo, $filtro_tipo, $filtro_valor);

// Função para formatar data de nascimento
function formatarDataNascimento($data) {
    if (!$data) return '-';
    return date('d/m/Y', strtotime($data));
}

// Função para obter próximo aniversário
function proximoAniversario($data_nascimento) {
    if (!$data_nascimento) return null;
    
    $hoje = new DateTime();
    $nascimento = new DateTime($data_nascimento);
    $proximo = new DateTime($hoje->format('Y') . '-' . $nascimento->format('m-d'));
    
    if ($proximo < $hoje) {
        $proximo->add(new DateInterval('P1Y'));
    }
    
    return $proximo;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aniversariantes - Associação Amigo do Povo</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <style>
        .birthday-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            color: white;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .birthday-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }
        
        .birthday-today {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(255, 107, 107, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 107, 107, 0); }
        }
        
        .filter-tabs {
            display: flex;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            flex: 1;
            padding: 10px 15px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #6b7280;
        }
        
        .filter-tab.active {
            background: #667eea;
            color: white;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .export-btn {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.2s ease;
        }
        
        .export-btn:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <div class="flex items-center">
                    <a href="dashboard.php" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg mr-4 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar ao Menu
                    </a>
                    <h1 class="text-3xl font-bold text-gray-800">
                        <i class="fas fa-birthday-cake text-pink-500 mr-3"></i>
                        Aniversariantes
                    </h1>
                </div>
                <a href="#" onclick="exportarAniversariantes()" class="export-btn">
                    <i class="fas fa-download"></i>
                    Exportar Lista
                </a>
            </div>
            
            <!-- Estatísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($aniversariantes); ?></div>
                    <div class="text-gray-600">Aniversariantes</div>
                </div>
                <div class="stat-card">
                    <?php 
                    $hoje = date('m-d');
                    $aniversariantes_hoje = array_filter($aniversariantes, function($a) use ($hoje) {
                        return date('m-d', strtotime($a['data_nascimento'])) === $hoje;
                    });
                    ?>
                    <div class="stat-number"><?php echo count($aniversariantes_hoje); ?></div>
                    <div class="text-gray-600">Hoje</div>
                </div>
                <div class="stat-card">
                    <?php 
                    $proximos_7_dias = 0;
                    $hoje_timestamp = time();
                    foreach ($aniversariantes as $aniversariante) {
                        $proximo = proximoAniversario($aniversariante['data_nascimento']);
                        if ($proximo && $proximo->getTimestamp() - $hoje_timestamp <= 7 * 24 * 60 * 60) {
                            $proximos_7_dias++;
                        }
                    }
                    ?>
                    <div class="stat-number"><?php echo $proximos_7_dias; ?></div>
                    <div class="text-gray-600">Próximos 7 dias</div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filter-tabs">
                <a href="?filtro_tipo=hoje" class="filter-tab <?php echo $filtro_tipo === 'hoje' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day mr-2"></i>Hoje
                </a>
                <a href="?filtro_tipo=mes&filtro_valor=<?php echo $mes_atual; ?>" class="filter-tab <?php echo $filtro_tipo === 'mes' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt mr-2"></i>Este Mês
                </a>
                <a href="#" onclick="mostrarFiltroPersonalizado('mes')" class="filter-tab">
                    <i class="fas fa-filter mr-2"></i>Filtrar por Mês
                </a>
                <a href="#" onclick="mostrarFiltroPersonalizado('dia')" class="filter-tab">
                    <i class="fas fa-calendar-check mr-2"></i>Filtrar por Data
                </a>
            </div>
            
            <!-- Filtro Personalizado -->
            <div id="filtro-personalizado" class="mb-6" style="display: none;">
                <form method="GET" class="bg-gray-50 p-4 rounded-lg">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Filtro</label>
                            <select name="filtro_tipo" id="tipo-filtro" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="mes">Por Mês</option>
                                <option value="dia">Por Data Específica</option>
                                <option value="semana">Por Semana</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Valor</label>
                            <input type="text" name="filtro_valor" id="valor-filtro" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Ex: 01 ou 01-15">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-search mr-2"></i>Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Lista de Aniversariantes -->
            <div class="space-y-4">
                <?php if (empty($aniversariantes)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-birthday-cake fa-4x text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">Nenhum aniversariante encontrado</h3>
                        <p class="text-gray-500">Tente ajustar os filtros ou verificar se há alunos cadastrados com data de nascimento.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($aniversariantes as $aniversariante): ?>
                        <?php 
                        $idade = calcularIdade($aniversariante['data_nascimento']);
                        $data_nascimento = $aniversariante['data_nascimento'];
                        $eh_hoje = date('m-d', strtotime($data_nascimento)) === date('m-d');
                        $proximo = proximoAniversario($data_nascimento);
                        $dias_para_aniversario = $proximo ? ceil(($proximo->getTimestamp() - time()) / (24 * 60 * 60)) : null;
                        ?>
                        <div class="birthday-card <?php echo $eh_hoje ? 'birthday-today' : ''; ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-bold"><?php echo htmlspecialchars($aniversariante['nome']); ?></h3>
                                        <p class="opacity-90">
                                            <i class="fas fa-birthday-cake mr-2"></i>
                                            <?php echo formatarDataNascimento($data_nascimento); ?>
                                            <?php if ($idade): ?>
                                                - <?php echo $idade; ?> anos
                                            <?php endif; ?>
                                        </p>
                                        <?php if ($aniversariante['telefone'] || $aniversariante['celular']): ?>
                                            <p class="opacity-75 text-sm">
                                                <i class="fas fa-phone mr-2"></i>
                                                <?php echo $aniversariante['celular'] ?: $aniversariante['telefone']; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <?php if ($eh_hoje): ?>
                                        <div class="bg-white bg-opacity-20 px-4 py-2 rounded-full">
                                            <i class="fas fa-gift mr-2"></i>
                                            <span class="font-bold">HOJE!</span>
                                        </div>
                                    <?php elseif ($dias_para_aniversario !== null): ?>
                                        <div class="bg-white bg-opacity-20 px-3 py-1 rounded-full text-sm">
                                            <?php if ($dias_para_aniversario == 0): ?>
                                                Hoje
                                            <?php elseif ($dias_para_aniversario == 1): ?>
                                                Amanhã
                                            <?php else: ?>
                                                Em <?php echo $dias_para_aniversario; ?> dias
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function mostrarFiltroPersonalizado(tipo) {
            const filtroDiv = document.getElementById('filtro-personalizado');
            const tipoSelect = document.getElementById('tipo-filtro');
            const valorInput = document.getElementById('valor-filtro');
            
            filtroDiv.style.display = 'block';
            tipoSelect.value = tipo;
            
            // Ajustar placeholder baseado no tipo
            if (tipo === 'mes') {
                valorInput.placeholder = 'Ex: 01 (Janeiro), 12 (Dezembro)';
            } else if (tipo === 'dia') {
                valorInput.placeholder = 'Ex: 01-15 (15 de Janeiro)';
            } else if (tipo === 'semana') {
                valorInput.placeholder = 'Ex: 2024-01-15 (início da semana)';
            }
            
            valorInput.focus();
        }
        
        function exportarAniversariantes() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'exportar_aniversariantes.php?' + params.toString();
        }
        
        // Atualizar placeholder do input baseado no tipo selecionado
        document.getElementById('tipo-filtro').addEventListener('change', function() {
            const valorInput = document.getElementById('valor-filtro');
            const tipo = this.value;
            
            if (tipo === 'mes') {
                valorInput.placeholder = 'Ex: 01 (Janeiro), 12 (Dezembro)';
            } else if (tipo === 'dia') {
                valorInput.placeholder = 'Ex: 01-15 (15 de Janeiro)';
            } else if (tipo === 'semana') {
                valorInput.placeholder = 'Ex: 2024-01-15 (início da semana)';
            }
        });
    </script>
</body>
</html>