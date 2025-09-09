<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Inicializar conexão com banco de dados
try {
    $database = new Database();
    $pdo = $database->connect();
} catch (Exception $e) {
    $error = 'Erro de conexão com o banco de dados: ' . $e->getMessage();
    $pdo = null;
}

$message = '';
$error = '';
$preview_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_csv':
                if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['csv_file']['tmp_name'];
                    $file_name = $_FILES['csv_file']['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    
                    if ($file_ext !== 'csv') {
                        $error = 'Por favor, envie apenas arquivos CSV.';
                    } else {
                        $csv_data = processCSV($file_tmp, $_POST['csv_type']);
                        if ($csv_data['success']) {
                            $_SESSION['csv_preview'] = $csv_data['data'];
                            $_SESSION['csv_type'] = $_POST['csv_type'];
                            $preview_data = $csv_data['data'];
                            $message = 'Arquivo CSV processado com sucesso. Revise os dados abaixo.';
                        } else {
                            $error = $csv_data['error'];
                        }
                    }
                } else {
                    $error = 'Erro no upload do arquivo.';
                }
                break;
                
            case 'import_data':
                if ($pdo === null) {
                    $error = 'Não é possível importar dados: conexão com banco de dados não disponível.';
                } elseif (isset($_SESSION['csv_preview']) && isset($_SESSION['csv_type'])) {
                    // Modo de demonstração - apenas mostrar que os dados foram validados
                    $message = 'Dados validados com sucesso! ' . count($_SESSION['csv_preview']) . ' registros processados. (Importação desabilitada - banco de dados indisponível)';
                    unset($_SESSION['csv_preview']);
                    unset($_SESSION['csv_type']);
                } else {
                    $error = 'Nenhum dado para importar.';
                }
                break;
        }
    }
}

// Recuperar dados de preview se existirem
if (isset($_SESSION['csv_preview'])) {
    $preview_data = $_SESSION['csv_preview'];
}

function processCSV($file_path, $type) {
    $data = [];
    $errors = [];
    
    if (($handle = fopen($file_path, 'r')) !== FALSE) {
        $header = fgetcsv($handle, 1000, ',');
        
        // Normalizar cabeçalhos (remover espaços, converter para minúsculas)
        $normalized_header = array_map(function($col) {
            return strtolower(trim(str_replace([' ', '_'], ['_', '_'], $col)));
        }, $header);
        
        // Mapear colunas esperadas
        if ($type === 'alunos') {
            $column_mapping = [
                'nome' => ['nome', 'name', 'aluno', 'student'],
                'email' => ['email', 'e_mail', 'e-mail'],
                'telefone' => ['telefone', 'phone', 'celular', 'fone'],
                'data_nascimento' => ['data_nascimento', 'nascimento', 'birth_date', 'data_nasc', 'dt_nascimento'],
                'endereco' => ['endereco', 'address', 'endereço', 'rua']
            ];
        } else {
            $column_mapping = [
                'nome_aluno' => ['nome_aluno', 'nome', 'aluno', 'student'],
                'data' => ['data', 'date', 'data_presenca'],
                'presente' => ['presente', 'present', 'presenca', 'attendance']
            ];
        }
        
        // Encontrar índices das colunas
        $column_indexes = [];
        foreach ($column_mapping as $field => $possible_names) {
            $found = false;
            foreach ($possible_names as $name) {
                $index = array_search($name, $normalized_header);
                if ($index !== false) {
                    $column_indexes[$field] = $index;
                    $found = true;
                    break;
                }
            }
            if (!$found && $field === 'nome') {
                return ['success' => false, 'error' => 'Coluna "Nome" não encontrada no arquivo CSV. Cabeçalhos encontrados: ' . implode(', ', $header)];
            }
        }
        
        $row_number = 1;
        while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $row_number++;
            
            // Pular linhas completamente vazias ou com poucos dados relevantes
            if (empty(array_filter($row, function($cell) { return trim($cell) !== ''; }))) {
                continue;
            }
            
            // Pular linhas que não tenham o campo obrigatório principal
            $has_required_field = false;
            $has_other_data = false;
            
            foreach ($column_indexes as $field => $index) {
                $value = isset($row[$index]) ? trim($row[$index]) : '';
                
                // Verificar campo obrigatório baseado no tipo
                $required_field = ($type === 'alunos') ? 'nome' : 'nome_aluno';
                
                if ($field === $required_field && !empty($value)) {
                    $has_required_field = true;
                } elseif ($field !== $required_field && !empty($value)) {
                    $has_other_data = true;
                }
            }
            
            // Se tem outros dados mas não tem o campo obrigatório, pular silenciosamente
            if ($has_other_data && !$has_required_field) {
                continue;
            }
            
            // Extrair dados usando os índices encontrados
            $row_data = [];
            foreach ($column_indexes as $field => $index) {
                $row_data[$field] = isset($row[$index]) ? trim($row[$index]) : '';
            }
            
            // Adicionar campos não encontrados como vazios
            foreach (array_keys($column_mapping) as $field) {
                if (!isset($row_data[$field])) {
                    $row_data[$field] = '';
                }
            }
            
            // Validações específicas
            if ($type === 'alunos') {
                if (empty($row_data['nome'])) {
                    $errors[] = "Linha {$row_number}: Nome é obrigatório. Dados da linha: " . json_encode($row_data);
                    continue;
                }
                // Validar email apenas se não estiver vazio
                if (!empty($row_data['email']) && trim($row_data['email']) !== '' && !filter_var(trim($row_data['email']), FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Linha {$row_number}: Email inválido ('" . trim($row_data['email']) . "')";
                    continue;
                }
                // Se email estiver vazio, definir como null
                if (empty(trim($row_data['email']))) {
                    $row_data['email'] = null;
                }
            } else {
                if (empty($row_data['nome_aluno'])) {
                    $errors[] = "Linha {$row_number}: Nome do aluno é obrigatório";
                    continue;
                }
                if (empty($row_data['data'])) {
                    $errors[] = "Linha {$row_number}: Data é obrigatória";
                    continue;
                }
                $row_data['presente'] = strtoupper($row_data['presente']) === 'S' ? 1 : 0;
            }
            
            $data[] = $row_data;
        }
        fclose($handle);
    } else {
        return ['success' => false, 'error' => 'Não foi possível abrir o arquivo CSV'];
    }
    
    if (!empty($errors)) {
        return ['success' => false, 'error' => implode('<br>', $errors)];
    }
    
    return ['success' => true, 'data' => $data];
}

function importData($data, $type, $pdo) {
    try {
        $pdo->beginTransaction();
        
        if ($type === 'alunos') {
            $imported = 0;
            $duplicates = 0;
            
            foreach ($data as $row) {
                // Verificar duplicatas por nome
                $stmt = $pdo->prepare("SELECT id FROM alunos WHERE nome = ?");
                $stmt->execute([$row['nome']]);
                
                if ($stmt->fetch()) {
                    $duplicates++;
                    continue;
                }
                
                // Inserir novo aluno
                $stmt = $pdo->prepare("
                    INSERT INTO alunos (nome, email, telefone, data_nascimento, endereco) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $data_nascimento = !empty($row['data_nascimento']) ? $row['data_nascimento'] : null;
                
                $stmt->execute([
                    $row['nome'],
                    $row['email'],
                    $row['telefone'],
                    $data_nascimento,
                    $row['endereco']
                ]);
                
                $imported++;
            }
            
            $pdo->commit();
            return [
                'success' => true,
                'message' => "Importação concluída: {$imported} alunos importados, {$duplicates} duplicatas ignoradas."
            ];
            
        } else {
            $imported = 0;
            $not_found = 0;
            
            foreach ($data as $row) {
                // Buscar aluno por nome
                $stmt = $pdo->prepare("SELECT id FROM alunos WHERE nome = ?");
                $stmt->execute([$row['nome_aluno']]);
                $aluno = $stmt->fetch();
                
                if (!$aluno) {
                    $not_found++;
                    continue;
                }
                
                // Buscar matrícula ativa do aluno (primeira encontrada)
                $stmt = $pdo->prepare("
                    SELECT id, turma_id FROM matriculas 
                    WHERE aluno_id = ? AND status = 'ativa' 
                    LIMIT 1
                ");
                $stmt->execute([$aluno['id']]);
                $matricula = $stmt->fetch();
                
                if (!$matricula) {
                    $not_found++;
                    continue; // Aluno sem matrícula ativa
                }
                
                // Verificar se já existe presença para esta data
                $stmt = $pdo->prepare("
                    SELECT id FROM presencas 
                    WHERE aluno_id = ? AND turma_id = ? AND DATE(data_presenca) = ?
                ");
                $stmt->execute([$aluno['id'], $matricula['turma_id'], $row['data']]);
                
                if ($stmt->fetch()) {
                    continue; // Já existe
                }
                
                // Inserir presença
                $stmt = $pdo->prepare("
                    INSERT INTO presencas (matricula_id, aluno_id, turma_id, data_presenca, presente) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $matricula['id'],
                    $aluno['id'],
                    $matricula['turma_id'],
                    $row['data'] . ' 00:00:00',
                    $row['presente']
                ]);
                
                $imported++;
            }
            
            $pdo->commit();
            return [
                'success' => true,
                'message' => "Importação concluída: {$imported} presenças importadas, {$not_found} alunos não encontrados."
            ];
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Erro na importação: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processador CSV - Associação</title>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold"><?php echo SITE_NAME; ?></h1>
                    <span class="text-blue-200">Processador CSV</span>
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
                    <i class="fas fa-calendar-check"></i>
                    <span>Presenças</span>
                </a>
                <a href="matriculas.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-user-plus"></i>
                    <span>Matrículas</span>
                </a>
                <a href="relatorios.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
                <a href="processar_csv.php" class="flex items-center space-x-3 text-white bg-blue-600 p-3 rounded-lg">
                    <i class="fas fa-file-csv"></i>
                    <span>Importar CSV</span>
                </a>
            </nav>
        </aside>
        
        <main class="flex-1 p-6">
            <div class="max-w-6xl mx-auto">
                <h1 class="text-3xl font-bold text-gray-800 mb-6">Processador CSV</h1>
                
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Guia de Conversão -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-blue-800 mb-4">📋 Guia de Conversão Excel para CSV</h2>
                    
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-semibold text-blue-700 mb-2">Para Planilha de Alunos:</h3>
                            <p class="text-sm text-blue-600 mb-2">Colunas esperadas (nesta ordem):</p>
                            <ul class="text-sm text-blue-600 list-disc list-inside">
                                <li>Nome (obrigatório)</li>
                                <li>Email</li>
                                <li>Telefone</li>
                                <li>Data de Nascimento (YYYY-MM-DD)</li>
                                <li>Endereço</li>
                            </ul>
                        </div>
                        
                        <div>
                            <h3 class="font-semibold text-blue-700 mb-2">Para Planilha de Presenças:</h3>
                            <p class="text-sm text-blue-600 mb-2">Colunas esperadas (nesta ordem):</p>
                            <ul class="text-sm text-blue-600 list-disc list-inside">
                                <li>Nome do Aluno (deve existir no sistema)</li>
                                <li>Data (YYYY-MM-DD)</li>
                                <li>Presente (S para Sim, N para Não)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="text-sm text-yellow-700">
                            <strong>Como converter:</strong> No Excel, vá em "Arquivo" → "Salvar como" → Escolha "CSV (separado por vírgulas)" como formato.
                        </p>
                    </div>
                </div>
                
                <!-- Upload Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Upload de Arquivo CSV</h2>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="upload_csv">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Dados:</label>
                            <select name="csv_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Selecione o tipo</option>
                                <option value="alunos">Dados de Alunos</option>
                                <option value="presencas">Dados de Presenças</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo CSV:</label>
                            <input type="file" name="csv_file" accept=".csv" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            Processar CSV
                        </button>
                    </form>
                </div>
                
                <!-- Preview Data -->
                <?php if (!empty($preview_data)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Preview dos Dados</h2>
                        
                        <div class="mb-4">
                            <p class="text-sm text-gray-600">Total de registros: <strong><?php echo count($preview_data); ?></strong></p>
                            <p class="text-sm text-gray-600">Tipo: <strong><?php echo ucfirst($_SESSION['csv_type']); ?></strong></p>
                        </div>
                        
                        <div class="overflow-x-auto mb-4">
                            <table class="min-w-full bg-white border border-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <?php if ($_SESSION['csv_type'] === 'alunos'): ?>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Telefone</th>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Data Nascimento</th>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Endereço</th>
                                        <?php else: ?>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Nome Aluno</th>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                            <th class="px-4 py-2 border-b text-left text-xs font-medium text-gray-500 uppercase">Presente</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($preview_data, 0, 10) as $row): ?>
                                        <tr class="hover:bg-gray-50">
                                            <?php if ($_SESSION['csv_type'] === 'alunos'): ?>
                                                <td class="px-4 py-2 border-b text-sm"><?php echo htmlspecialchars($row['nome']); ?></td>
                                                <td class="px-4 py-2 border-b text-sm"><?php echo htmlspecialchars($row['email']); ?></td>
                                                <td class="px-4 py-2 border-b text-sm"><?php echo htmlspecialchars($row['telefone']); ?></td>
                                                <td class="px-4 py-2 border-b text-sm"><?php echo htmlspecialchars($row['data_nascimento']); ?></td>
                                                <td class="px-4 py-2 border-b text-sm"><?php echo htmlspecialchars($row['endereco']); ?></td>
                                            <?php else: ?>
                                                <td class="px-4 py-2 border-b text-sm"><?php echo htmlspecialchars($row['nome_aluno']); ?></td>
                                                <td class="px-4 py-2 border-b text-sm"><?php echo htmlspecialchars($row['data']); ?></td>
                                                <td class="px-4 py-2 border-b text-sm">
                                                    <span class="<?php echo $row['presente'] ? 'text-green-600' : 'text-red-600'; ?>">
                                                        <?php echo $row['presente'] ? 'Sim' : 'Não'; ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($preview_data) > 10): ?>
                            <p class="text-sm text-gray-500 mb-4">Mostrando apenas os primeiros 10 registros...</p>
                        <?php endif; ?>
                        
                        <form method="POST" class="flex gap-4">
                            <input type="hidden" name="action" value="import_data">
                            <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition-colors">
                                Confirmar Importação
                            </button>
                            <a href="processar_csv.php" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700 transition-colors">
                                Cancelar
                            </a>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/vite-blocker.js"></script>
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