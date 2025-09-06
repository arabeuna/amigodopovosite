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
            $cpf = sanitize_input($_POST['cpf']);
            $rg = sanitize_input($_POST['rg']);
            $data_nascimento = $_POST['data_nascimento'];
            $sexo = $_POST['sexo'];
            $telefone = sanitize_input($_POST['telefone']);
            $celular = sanitize_input($_POST['celular']);
            $email = sanitize_input($_POST['email']);
            $endereco = sanitize_input($_POST['endereco']);
            $cep = sanitize_input($_POST['cep']);
            $cidade = sanitize_input($_POST['cidade']);
            $estado = sanitize_input($_POST['estado']);
            $nome_responsavel = sanitize_input($_POST['nome_responsavel']);
            $telefone_responsavel = sanitize_input($_POST['telefone_responsavel']);
            $observacoes = sanitize_input($_POST['observacoes']);
            
            if (empty($nome)) {
                $error = 'Nome é obrigatório';
            } else {
                $sql = "INSERT INTO alunos (nome, cpf, rg, data_nascimento, sexo, telefone, celular, email, endereco, cep, cidade, estado, nome_responsavel, telefone_responsavel, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$nome, $cpf, $rg, $data_nascimento, $sexo, $telefone, $celular, $email, $endereco, $cep, $cidade, $estado, $nome_responsavel, $telefone_responsavel, $observacoes])) {
                    $message = 'Aluno cadastrado com sucesso!';
                } else {
                    $error = 'Erro ao cadastrar aluno';
                }
            }
            break;
            
        case 'update':
            $id = (int)$_POST['id'];
            $nome = sanitize_input($_POST['nome']);
            $cpf = sanitize_input($_POST['cpf']);
            $rg = sanitize_input($_POST['rg']);
            $data_nascimento = $_POST['data_nascimento'];
            $sexo = $_POST['sexo'];
            $telefone = sanitize_input($_POST['telefone']);
            $celular = sanitize_input($_POST['celular']);
            $email = sanitize_input($_POST['email']);
            $endereco = sanitize_input($_POST['endereco']);
            $cep = sanitize_input($_POST['cep']);
            $cidade = sanitize_input($_POST['cidade']);
            $estado = sanitize_input($_POST['estado']);
            $nome_responsavel = sanitize_input($_POST['nome_responsavel']);
            $telefone_responsavel = sanitize_input($_POST['telefone_responsavel']);
            $observacoes = sanitize_input($_POST['observacoes']);
            
            if (empty($nome)) {
                $error = 'Nome é obrigatório';
            } else {
                $sql = "UPDATE alunos SET nome=?, cpf=?, rg=?, data_nascimento=?, sexo=?, telefone=?, celular=?, email=?, endereco=?, cep=?, cidade=?, estado=?, nome_responsavel=?, telefone_responsavel=?, observacoes=? WHERE id=?";
                $stmt = $db->prepare($sql);
                if ($stmt->execute([$nome, $cpf, $rg, $data_nascimento, $sexo, $telefone, $celular, $email, $endereco, $cep, $cidade, $estado, $nome_responsavel, $telefone_responsavel, $observacoes, $id])) {
                    $message = 'Aluno atualizado com sucesso!';
                } else {
                    $error = 'Erro ao atualizar aluno';
                }
            }
            break;
            
        case 'delete':
            $id = (int)$_POST['id'];
            $sql = "UPDATE alunos SET ativo = FALSE WHERE id = ?";
            $stmt = $db->prepare($sql);
            if ($stmt->execute([$id])) {
                $message = 'Aluno desativado com sucesso!';
            } else {
                $error = 'Erro ao desativar aluno';
            }
            break;
    }
}

// Buscar alunos
$search = $_GET['search'] ?? '';
$sexo = $_GET['sexo'] ?? '';
$cidade = $_GET['cidade'] ?? '';
$orderby = $_GET['orderby'] ?? 'nome';
$order = $_GET['order'] ?? 'ASC';
$page = (int)($_GET['page'] ?? 1);
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

$where = "WHERE ativo = TRUE";
$params = [];

if (!empty($search)) {
    $where .= " AND (nome LIKE ? OR cpf LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($sexo)) {
    $where .= " AND sexo = ?";
    $params[] = $sexo;
}

if (!empty($cidade)) {
    $where .= " AND cidade = ?";
    $params[] = $cidade;
}

// Validar campos de ordenação
$allowedOrderBy = ['nome', 'data_nascimento', 'cidade', 'created_at'];
$allowedOrder = ['ASC', 'DESC'];

if (!in_array($orderby, $allowedOrderBy)) {
    $orderby = 'nome';
}

if (!in_array($order, $allowedOrder)) {
    $order = 'ASC';
}

$sql = "SELECT * FROM alunos $where ORDER BY $orderby $order LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$alunos = $stmt->fetchAll();

// Contar total de alunos
$countSql = "SELECT COUNT(*) FROM alunos $where";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalAlunos = $countStmt->fetchColumn();
$totalPages = ceil($totalAlunos / $limit);

// Buscar aluno para edição
$editAluno = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM alunos WHERE id = ?");
    $stmt->execute([$editId]);
    $editAluno = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Alunos - <?php echo SITE_NAME; ?></title>
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
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <h1 class="text-2xl font-bold"><?php echo SITE_NAME; ?></h1>
                    <span class="text-blue-200">Gestão de Alunos</span>
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
                <a href="alunos.php" class="flex items-center space-x-3 text-white bg-gray-700 p-3 rounded-lg transition duration-200">
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
                <a href="relatorios.php" class="flex items-center space-x-3 text-gray-300 hover:bg-gray-700 p-3 rounded-lg transition duration-200">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <?php if ($message): ?>
                <div class="alert alert-success slide-up">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error slide-up">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="form-container bg-white mb-6">
                <div class="data-table-header">
                    <h2 class="data-table-title">
                        <i class="fas fa-user-plus"></i>
                        <?php echo $editAluno ? 'Editar Aluno' : 'Cadastrar Novo Aluno'; ?>
                    </h2>
                </div>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="<?php echo $editAluno ? 'update' : 'create'; ?>">
                    <?php if ($editAluno): ?>
                        <input type="hidden" name="id" value="<?php echo $editAluno['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-field full-width">
                        <label class="form-label required">
                            <i class="fas fa-user"></i>Nome Completo
                        </label>
                        <input type="text" name="nome" value="<?php echo $editAluno ? htmlspecialchars($editAluno['nome']) : ''; ?>" class="form-input" required>
                        <div class="form-help">Digite o nome completo do aluno</div>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i>CPF
                        </label>
                        <input type="text" name="cpf" value="<?php echo $editAluno ? htmlspecialchars($editAluno['cpf']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-address-card"></i>RG
                        </label>
                        <input type="text" name="rg" value="<?php echo $editAluno ? htmlspecialchars($editAluno['rg']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-calendar"></i>Data de Nascimento
                        </label>
                        <input type="date" name="data_nascimento" value="<?php echo $editAluno ? $editAluno['data_nascimento'] : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-venus-mars"></i>Sexo
                        </label>
                        <select name="sexo" class="form-select">
                            <option value="">Selecione</option>
                            <option value="M" <?php echo ($editAluno && $editAluno['sexo'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                            <option value="F" <?php echo ($editAluno && $editAluno['sexo'] === 'F') ? 'selected' : ''; ?>>Feminino</option>
                            <option value="Outro" <?php echo ($editAluno && $editAluno['sexo'] === 'Outro') ? 'selected' : ''; ?>>Outro</option>
                        </select>
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-phone"></i>Telefone
                        </label>
                        <input type="text" name="telefone" value="<?php echo $editAluno ? htmlspecialchars($editAluno['telefone']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-mobile-alt"></i>Celular
                        </label>
                        <input type="text" name="celular" value="<?php echo $editAluno ? htmlspecialchars($editAluno['celular']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i>Email
                        </label>
                        <input type="email" name="email" value="<?php echo $editAluno ? htmlspecialchars($editAluno['email']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field full-width">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i>Endereço
                        </label>
                        <input type="text" name="endereco" value="<?php echo $editAluno ? htmlspecialchars($editAluno['endereco']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-mail-bulk"></i>CEP
                        </label>
                        <input type="text" name="cep" value="<?php echo $editAluno ? htmlspecialchars($editAluno['cep']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-city"></i>Cidade
                        </label>
                        <input type="text" name="cidade" value="<?php echo $editAluno ? htmlspecialchars($editAluno['cidade']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-flag"></i>Estado
                        </label>
                        <input type="text" name="estado" value="<?php echo $editAluno ? htmlspecialchars($editAluno['estado']) : ''; ?>" 
                               maxlength="2" class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-user-tie"></i>Nome do Responsável
                        </label>
                        <input type="text" name="nome_responsavel" value="<?php echo $editAluno ? htmlspecialchars($editAluno['nome_responsavel']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field">
                        <label class="form-label">
                            <i class="fas fa-phone-alt"></i>Telefone do Responsável
                        </label>
                        <input type="text" name="telefone_responsavel" value="<?php echo $editAluno ? htmlspecialchars($editAluno['telefone_responsavel']) : ''; ?>" 
                               class="form-input">
                    </div>

                    <div class="form-field full-width">
                        <label class="form-label">
                            <i class="fas fa-sticky-note"></i>Observações
                        </label>
                        <textarea name="observacoes" rows="3" 
                                  class="form-textarea"><?php echo $editAluno ? htmlspecialchars($editAluno['observacoes']) : ''; ?></textarea>
                    </div>

                    <div class="form-field full-width">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save mr-2"></i>
                            <?php echo $editAluno ? 'Atualizar Aluno' : 'Cadastrar Aluno'; ?>
                        </button>
                        <?php if ($editAluno): ?>
                            <a href="alunos.php" class="btn-secondary ml-3">
                                <i class="fas fa-times mr-2"></i>Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Lista de Alunos -->
            <div class="data-table-container">
                <div class="data-table-header">
                    <h2 class="data-table-title">
                        <i class="fas fa-users"></i>Lista de Alunos
                    </h2>
                    <div class="search-container">
                        <form method="GET" class="search-form">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="search-input" placeholder="Buscar por nome, CPF ou email...">
                            <button type="submit" class="search-button">
                                <i class="fas fa-search"></i>
                            </button>
                            
                            <div class="filters-row">
                                <div class="filter-group">
                                    <label for="sexo">Sexo:</label>
                                    <select name="sexo" id="sexo" class="filter-select">
                                        <option value="">Todos</option>
                                        <option value="M" <?php echo ($_GET['sexo'] ?? '') === 'M' ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo ($_GET['sexo'] ?? '') === 'F' ? 'selected' : ''; ?>>Feminino</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="cidade">Cidade:</label>
                                    <select name="cidade" id="cidade" class="filter-select">
                                        <option value="">Todas</option>
                                        <?php
                                        $cidadesSql = "SELECT DISTINCT cidade FROM alunos WHERE ativo = TRUE AND cidade IS NOT NULL AND cidade != '' ORDER BY cidade";
                                        $cidadesStmt = $db->query($cidadesSql);
                                        $cidades = $cidadesStmt->fetchAll();
                                        foreach ($cidades as $cidadeRow):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cidadeRow['cidade']); ?>" 
                                                    <?php echo ($_GET['cidade'] ?? '') === $cidadeRow['cidade'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cidadeRow['cidade']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="orderby">Ordenar por:</label>
                                    <select name="orderby" id="orderby" class="filter-select">
                                        <option value="nome" <?php echo ($_GET['orderby'] ?? 'nome') === 'nome' ? 'selected' : ''; ?>>Nome</option>
                                        <option value="data_nascimento" <?php echo ($_GET['orderby'] ?? '') === 'data_nascimento' ? 'selected' : ''; ?>>Data Nascimento</option>
                                        <option value="cidade" <?php echo ($_GET['orderby'] ?? '') === 'cidade' ? 'selected' : ''; ?>>Cidade</option>
                                        <option value="created_at" <?php echo ($_GET['orderby'] ?? '') === 'created_at' ? 'selected' : ''; ?>>Data Cadastro</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label for="order">Ordem:</label>
                                    <select name="order" id="order" class="filter-select">
                                        <option value="ASC" <?php echo ($_GET['order'] ?? 'ASC') === 'ASC' ? 'selected' : ''; ?>>Crescente</option>
                                        <option value="DESC" <?php echo ($_GET['order'] ?? '') === 'DESC' ? 'selected' : ''; ?>>Decrescente</option>
                                    </select>
                                </div>
                            </div>
                            
                            <?php if (!empty($search) || !empty($_GET['sexo']) || !empty($_GET['cidade']) || (!empty($_GET['orderby']) && $_GET['orderby'] !== 'nome') || (!empty($_GET['order']) && $_GET['order'] !== 'ASC')): ?>
                                <a href="alunos.php" class="btn-secondary ml-2">
                                    <i class="fas fa-times"></i> Limpar Filtros
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Toggle de visualização mobile -->
                <div class="mobile-view-toggle">
                    <button type="button" id="toggleView" class="btn-secondary">
                        <i class="fas fa-th-large"></i> Visualização em Cards
                    </button>
                </div>

                <!-- Visualização em Cards (Mobile) -->
                <div class="mobile-card-view" id="cardView" style="display: none;">
                    <?php if (empty($alunos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users fa-3x mb-4 text-gray-300"></i>
                            <p class="text-lg">Nenhum aluno encontrado</p>
                            <?php if (!empty($search)): ?>
                                <p class="text-sm">Tente ajustar os termos de busca</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alunos as $aluno): ?>
                            <div class="student-card">
                                <div class="student-card-header">
                                    <h3 class="student-name"><?php echo htmlspecialchars($aluno['nome']); ?></h3>
                                    <div class="student-badges">
                                        <span class="status-badge active">
                                            <i class="fas fa-check-circle"></i> Ativo
                                        </span>
                                        <?php if (!empty($aluno['sexo'])): ?>
                                            <span class="gender-badge gender-<?php echo strtolower($aluno['sexo']); ?>">
                                                <i class="fas fa-<?php echo $aluno['sexo'] === 'M' ? 'mars' : 'venus'; ?>"></i>
                                                <?php echo $aluno['sexo'] === 'M' ? 'M' : 'F'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="student-info-grid">
                                    <?php if ($aluno['data_nascimento']): ?>
                                        <?php $idade = date_diff(date_create($aluno['data_nascimento']), date_create('today'))->y; ?>
                                        <div class="info-item">
                                            <i class="fas fa-birthday-cake"></i>
                                            <span><?php echo $idade; ?> anos</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($aluno['cpf'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-id-card"></i>
                                            <span><?php echo htmlspecialchars($aluno['cpf']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($aluno['celular'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-mobile-alt"></i>
                                            <span><?php echo htmlspecialchars($aluno['celular']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($aluno['email'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-envelope"></i>
                                            <span><?php echo htmlspecialchars($aluno['email']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($aluno['cidade'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <span><?php echo htmlspecialchars($aluno['cidade']); ?><?php if (!empty($aluno['estado'])): ?> - <?php echo htmlspecialchars($aluno['estado']); ?><?php endif; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($aluno['nome_responsavel'])): ?>
                                        <div class="info-item">
                                            <i class="fas fa-user-friends"></i>
                                            <span><?php echo htmlspecialchars($aluno['nome_responsavel']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="student-actions">
                                    <a href="?edit=<?php echo $aluno['id']; ?>" class="action-btn edit">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <button type="button" class="action-btn view" onclick="viewStudent(<?php echo $aluno['id']; ?>)">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja desativar este aluno?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $aluno['id']; ?>">
                                        <button type="submit" class="action-btn delete">
                                            <i class="fas fa-user-slash"></i> Desativar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Visualização em Tabela (Desktop) -->
                <div class="table-responsive" id="tableView">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nome <i class="fas fa-sort"></i></th>
                                <th>CPF</th>
                                <th>Contato</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th class="table-actions">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alunos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-8 text-gray-500">
                                        <i class="fas fa-users fa-3x mb-4 text-gray-300"></i>
                                        <p class="text-lg">Nenhum aluno encontrado</p>
                                        <?php if (!empty($search)): ?>
                                            <p class="text-sm">Tente ajustar os termos de busca</p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($alunos as $aluno): ?>
                                    <tr>
                                        <td>
                                            <div class="student-info">
                                                <div class="student-name table-cell-primary"><?php echo htmlspecialchars($aluno['nome']); ?></div>
                                                <?php if ($aluno['data_nascimento']): ?>
                                                    <?php 
                                                    $idade = date_diff(date_create($aluno['data_nascimento']), date_create('today'))->y;
                                                    ?>
                                                    <div class="student-age table-cell-secondary"><?php echo $idade; ?> anos - Nascido em <?php echo date('d/m/Y', strtotime($aluno['data_nascimento'])); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <div class="table-cell-primary"><?php echo $aluno['cpf'] ? htmlspecialchars($aluno['cpf']) : '-'; ?></div>
                                                <?php if (!empty($aluno['rg'])): ?>
                                                    <div class="table-cell-secondary">RG: <?php echo htmlspecialchars($aluno['rg']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-phones">
                                                <?php if (!empty($aluno['celular'])): ?>
                                                    <div class="phone-item table-cell-primary">
                                                        <i class="fas fa-mobile-alt"></i>
                                                        <?php echo htmlspecialchars($aluno['celular']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($aluno['telefone'])): ?>
                                                    <div class="phone-item table-cell-secondary">
                                                        <i class="fas fa-phone"></i>
                                                        <?php echo htmlspecialchars($aluno['telefone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (empty($aluno['telefone']) && empty($aluno['celular'])): ?>
                                                    <div class="table-cell-primary">-</div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($aluno['email'])): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($aluno['email']); ?>" class="email-link table-cell-primary">
                                                    <i class="fas fa-envelope"></i>
                                                    <?php echo htmlspecialchars($aluno['email']); ?>
                                                </a>
                                            <?php else: ?>
                                                <div class="table-cell-primary">-</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="status-badges">
                                                <span class="table-badge success">
                                                    <i class="fas fa-check-circle"></i> Ativo
                                                </span>
                                                <?php if (!empty($aluno['sexo'])): ?>
                                                    <span class="info-badge gender-<?php echo strtolower($aluno['sexo']); ?>">
                                                        <i class="fas fa-<?php echo $aluno['sexo'] === 'M' ? 'mars' : 'venus'; ?>"></i>
                                                        <?php echo $aluno['sexo'] === 'M' ? 'Masculino' : ($aluno['sexo'] === 'F' ? 'Feminino' : 'Outro'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($aluno['nome_responsavel'])): ?>
                                                    <span class="info-badge has-responsible" title="Responsável: <?php echo htmlspecialchars($aluno['nome_responsavel']); ?>">
                                                        <i class="fas fa-user-friends"></i> Responsável
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (!empty($aluno['cidade'])): ?>
                                                    <span class="info-badge location">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <?php echo htmlspecialchars($aluno['cidade']); ?>
                                                        <?php if (!empty($aluno['estado'])): ?>
                                                            - <?php echo htmlspecialchars($aluno['estado']); ?>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-actions">
                                                <a href="?edit=<?php echo $aluno['id']; ?>" class="action-link" title="Editar aluno">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <button type="button" class="action-link info" onclick="viewStudent(<?php echo $aluno['id']; ?>)" title="Ver detalhes">
                                                    <i class="fas fa-eye"></i> Ver
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja desativar este aluno?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $aluno['id']; ?>">
                                                    <button type="submit" class="action-link danger" title="Desativar aluno">
                                                        <i class="fas fa-user-slash"></i> Desativar
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            <span class="text-sm text-gray-600">
                                Mostrando <?php echo (($page - 1) * $limit) + 1; ?> a <?php echo min($page * $limit, $totalAlunos); ?> de <?php echo $totalAlunos; ?> alunos
                            </span>
                        </div>
                        <nav class="pagination">
                            <?php 
                            $queryParams = [];
                            if (!empty($search)) $queryParams['search'] = $search;
                            if (!empty($sexo)) $queryParams['sexo'] = $sexo;
                            if (!empty($cidade)) $queryParams['cidade'] = $cidade;
                            if ($orderby !== 'nome') $queryParams['orderby'] = $orderby;
                            if ($order !== 'ASC') $queryParams['order'] = $order;
                            $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>" class="pagination-link">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $queryString; ?>" 
                                   class="pagination-link <?php echo $i === $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>" class="pagination-link">
                                    Próxima <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/vite-blocker.js"></script>
    <script src="../assets/js/mobile.js"></script>
    <script>
        function viewStudent(id) {
            // Por enquanto, redireciona para edição - pode ser expandido para modal
            window.location.href = '?edit=' + id;
        }
        
        // Gerenciamento de visualizações
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleView');
            const cardView = document.getElementById('cardView');
            const tableView = document.getElementById('tableView');
            const mobileToggle = document.querySelector('.mobile-view-toggle');
            
            let isCardView = false;
            
            // Função para detectar se é mobile
            function isMobileDevice() {
                return window.innerWidth <= 768;
            }
            
            // Função para mostrar/esconder toggle baseado no tamanho da tela
            function updateToggleVisibility() {
                if (isMobileDevice()) {
                    mobileToggle.style.display = 'block';
                    // Em mobile, iniciar com cards se ainda não foi definido
                    if (!localStorage.getItem('viewPreference')) {
                        showCardView();
                    }
                } else {
                    mobileToggle.style.display = 'none';
                    // Em desktop, sempre mostrar tabela
                    showTableView();
                }
            }
            
            // Função para mostrar visualização em cards
            function showCardView() {
                cardView.style.display = 'block';
                tableView.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-table"></i> Visualização em Tabela';
                isCardView = true;
                localStorage.setItem('viewPreference', 'cards');
            }
            
            // Função para mostrar visualização em tabela
            function showTableView() {
                cardView.style.display = 'none';
                tableView.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-th-large"></i> Visualização em Cards';
                isCardView = false;
                localStorage.setItem('viewPreference', 'table');
            }
            
            // Event listener para o botão de toggle
            toggleBtn.addEventListener('click', function() {
                if (isCardView) {
                    showTableView();
                } else {
                    showCardView();
                }
            });
            
            // Inicializar visualização baseada na preferência salva ou tamanho da tela
            const savedPreference = localStorage.getItem('viewPreference');
            if (savedPreference === 'cards' && isMobileDevice()) {
                showCardView();
            } else if (savedPreference === 'table' && isMobileDevice()) {
                showTableView();
            } else {
                updateToggleVisibility();
            }
            
            // Atualizar quando a tela for redimensionada
            window.addEventListener('resize', function() {
                updateToggleVisibility();
            });
            
            // Auto-submit dos filtros quando alterados
            const filterSelects = document.querySelectorAll('.filter-select');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });
        });
    </script>
</body>
</html>