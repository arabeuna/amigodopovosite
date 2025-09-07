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

// Função para processar upload de foto
function processarUploadFoto($arquivo) {
    $uploadDir = '../uploads/fotos/';
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    // Verificar se é uma imagem válida
    if (!in_array($arquivo['type'], $allowedTypes)) {
        return false;
    }
    
    // Verificar tamanho
    if ($arquivo['size'] > $maxSize) {
        return false;
    }
    
    // Gerar nome único para o arquivo
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    $nomeArquivo = uniqid('foto_') . '.' . $extensao;
    $caminhoCompleto = $uploadDir . $nomeArquivo;
    
    // Mover arquivo para diretório de upload
    if (move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
        return $nomeArquivo;
    }
    
    return false;
}

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
            $titulo_inscricao = sanitize_input($_POST['titulo_inscricao']);
            $titulo_zona = sanitize_input($_POST['titulo_zona']);
            $titulo_secao = sanitize_input($_POST['titulo_secao']);
            $titulo_municipio_uf = sanitize_input($_POST['titulo_municipio_uf']);
            
            // Processar upload da foto
            $foto_nome = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto_nome = processarUploadFoto($_FILES['foto']);
                if ($foto_nome === false) {
                    $error = 'Erro ao fazer upload da foto';
                }
            }
            
            if (empty($nome)) {
                $error = 'Nome é obrigatório';
            } elseif ($foto_nome !== false) {
                // Verificar se CPF já existe
                if (!empty($cpf)) {
                    $check_sql = "SELECT id FROM alunos WHERE cpf = ?";
                    $check_stmt = $db->prepare($check_sql);
                    $check_stmt->execute([$cpf]);
                    if ($check_stmt->rowCount() > 0) {
                        $error = 'CPF já cadastrado no sistema';
                    }
                }
                
                if (!isset($error)) {
                    $sql = "INSERT INTO alunos (nome, cpf, rg, data_nascimento, sexo, telefone, celular, email, endereco, cep, cidade, estado, nome_responsavel, telefone_responsavel, observacoes, foto, titulo_inscricao, titulo_zona, titulo_secao, titulo_municipio_uf, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    try {
                        if ($stmt->execute([$nome, $cpf, $rg, $data_nascimento, $sexo, $telefone, $celular, $email, $endereco, $cep, $cidade, $estado, $nome_responsavel, $telefone_responsavel, $observacoes, $foto_nome, $titulo_inscricao, $titulo_zona, $titulo_secao, $titulo_municipio_uf, TRUE])) {
                            $message = 'Aluno cadastrado com sucesso!';
                        } else {
                            $error = 'Erro ao cadastrar aluno';
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $error = 'CPF já cadastrado no sistema';
                        } else {
                            $error = 'Erro ao cadastrar aluno: ' . $e->getMessage();
                        }
                    }
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
            $titulo_inscricao = sanitize_input($_POST['titulo_inscricao']);
            $titulo_zona = sanitize_input($_POST['titulo_zona']);
            $titulo_secao = sanitize_input($_POST['titulo_secao']);
            $titulo_municipio_uf = sanitize_input($_POST['titulo_municipio_uf']);
            
            // Processar upload da foto (se houver)
            $foto_nome = null;
            $update_foto = false;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $foto_nome = processarUploadFoto($_FILES['foto']);
                if ($foto_nome === false) {
                    $error = 'Erro ao fazer upload da foto';
                } else {
                    $update_foto = true;
                    // Remover foto antiga se existir
                    $stmt_old = $db->prepare("SELECT foto FROM alunos WHERE id = ?");
                    $stmt_old->execute([$id]);
                    $old_foto = $stmt_old->fetchColumn();
                    if ($old_foto && file_exists("../uploads/fotos/" . $old_foto)) {
                        unlink("../uploads/fotos/" . $old_foto);
                    }
                }
            }
            
            if (empty($nome)) {
                $error = 'Nome é obrigatório';
            } elseif ($foto_nome !== false) {
                if ($update_foto) {
                    $sql = "UPDATE alunos SET nome=?, cpf=?, rg=?, data_nascimento=?, sexo=?, telefone=?, celular=?, email=?, endereco=?, cep=?, cidade=?, estado=?, nome_responsavel=?, telefone_responsavel=?, observacoes=?, foto=?, titulo_inscricao=?, titulo_zona=?, titulo_secao=?, titulo_municipio_uf=? WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $success = $stmt->execute([$nome, $cpf, $rg, $data_nascimento, $sexo, $telefone, $celular, $email, $endereco, $cep, $cidade, $estado, $nome_responsavel, $telefone_responsavel, $observacoes, $foto_nome, $titulo_inscricao, $titulo_zona, $titulo_secao, $titulo_municipio_uf, $id]);
                } else {
                    $sql = "UPDATE alunos SET nome=?, cpf=?, rg=?, data_nascimento=?, sexo=?, telefone=?, celular=?, email=?, endereco=?, cep=?, cidade=?, estado=?, nome_responsavel=?, telefone_responsavel=?, observacoes=?, titulo_inscricao=?, titulo_zona=?, titulo_secao=?, titulo_municipio_uf=? WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $success = $stmt->execute([$nome, $cpf, $rg, $data_nascimento, $sexo, $telefone, $celular, $email, $endereco, $cep, $cidade, $estado, $nome_responsavel, $telefone_responsavel, $observacoes, $titulo_inscricao, $titulo_zona, $titulo_secao, $titulo_municipio_uf, $id]);
                }
                
                if ($success) {
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
    <link rel="icon" type="image/png" href="../assets/images/logo_associacao.svg">
    <link rel="apple-touch-icon" href="../assets/images/logo_associacao.svg">
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/theme.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="../assets/css/enhanced.css" rel="stylesheet">
    <link href="../assets/css/desktop.css" rel="stylesheet">
    <link href="../assets/css/mobile.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Estilos de Impressão -->
    <style>
        @media print {
            /* Ocultar elementos não necessários na impressão */
            .no-print, header, aside, .sidebar-enhanced, .search-container, .data-table-header .flex, .btn, button, .alert {
                display: none !important;
            }
            
            /* Mostrar apenas elementos de impressão */
            .print-only {
                display: block !important;
            }
            
            /* Ajustar layout para impressão - Layout ultra compacto */
            body {
                background: white !important;
                color: black !important;
                font-size: 8pt !important;
                line-height: 1.0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            main {
                margin: 0 !important;
                padding: 5px !important;
                width: 100% !important;
            }
            
            .form-container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            
            /* Layout em colunas para campos */
            .grid {
                display: grid !important;
                gap: 2px !important;
            }
            
            .grid-cols-1 {
                grid-template-columns: 1fr !important;
            }
            
            .grid-cols-2 {
                grid-template-columns: 1fr 1fr !important;
            }
            
            .grid-cols-3 {
                grid-template-columns: 1fr 1fr 1fr !important;
            }
            
            .grid-cols-4 {
                grid-template-columns: 1fr 1fr 1fr 1fr !important;
            }
            
            /* Reduzir espaçamentos drasticamente */
            .mb-4, .mb-6, .mb-8 {
                margin-bottom: 3px !important;
            }
            
            .p-4, .p-6, .p-8 {
                padding: 3px !important;
            }
            
            .py-2, .py-3, .py-4 {
                padding-top: 1px !important;
                padding-bottom: 1px !important;
            }
            
            .px-3, .px-4, .px-6 {
                padding-left: 2px !important;
                padding-right: 2px !important;
            }
            
            /* Estilos para formulário ultra compactos */
            .bg-blue-50, .bg-yellow-50, .bg-gray-50 {
                background: #f8f9fa !important;
                border: 1px solid #dee2e6 !important;
                margin-bottom: 2px !important;
            }
            
            .text-blue-800, .text-yellow-800, .text-gray-800 {
                color: #333 !important;
                font-weight: bold !important;
                font-size: 9pt !important;
            }
            
            input, select, textarea {
                border: 1px solid #333 !important;
                background: white !important;
                color: black !important;
                font-size: 7pt !important;
                padding: 1px 2px !important;
                height: auto !important;
                min-height: 14px !important;
                line-height: 1.0 !important;
            }
            
            label {
                font-size: 7pt !important;
                font-weight: bold !important;
                margin-bottom: 0px !important;
                display: block !important;
                line-height: 1.0 !important;
            }
            
            /* Títulos das seções */
            h2, h3 {
                font-size: 9pt !important;
                margin: 2px 0 1px 0 !important;
                page-break-after: avoid !important;
                line-height: 1.0 !important;
            }
            
            /* Otimizações específicas para impressão compacta */
            .signature-area {
                margin-top: 15px !important;
                page-break-inside: avoid !important;
            }
            
            /* Forçar quebra de página apenas se necessário */
            @page {
                margin: 0.2in !important;
                size: A4 !important;
            }
            
            /* Compactar ainda mais o layout */
            .space-y-6 > * + * {
                margin-top: 2px !important;
            }
            
            /* Reduzir altura dos campos de texto */
            textarea {
                min-height: 25px !important;
                max-height: 35px !important;
                font-size: 7pt !important;
                line-height: 1.0 !important;
            }
            
            /* Otimizar foto para impressão */
            #photo-preview {
                width: 40px !important;
                height: 55px !important;
            }
            
            /* Compactar seções */
            .bg-blue-50, .bg-yellow-50, .bg-gray-50, .bg-green-50 {
                padding: 2px !important;
            }
            
            /* Área de assinatura */
            .signature-area {
                margin-top: 40px;
                page-break-inside: avoid;
            }
            
            .signature-area .border-t {
                border-top: 1px solid black !important;
            }
            
            /* Informações de impressão */
            .print-info {
                text-align: right;
                font-size: 10pt;
                color: #666;
                margin-bottom: 20px;
            }
            
            /* Quebra de página */
            .page-break {
                page-break-before: always;
            }
        }
        
        /* Ocultar por padrão elementos de impressão */
        .print-only {
            display: none;
        }
    </style>
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
        <aside class="sidebar-enhanced text-white w-64 min-h-screen p-4" id="sidebar">
            <!-- Toggle Button -->
            <button id="sidebarToggle" class="sidebar-toggle-btn" title="Recolher/Expandir Menu">
                <i class="fas fa-chevron-left"></i>
            </button>
            
            <div class="flex items-center mb-8">
                    <img src="../assets/images/logo_associacao.svg" alt="Logo" class="logo logo-sm logo-sidebar mr-3">
                    <h2 class="text-xl font-bold sidebar-title"><?php echo SITE_NAME; ?></h2>
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
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="<?php echo $editAluno ? 'update' : 'create'; ?>">
                    <?php if ($editAluno): ?>
                        <input type="hidden" name="id" value="<?php echo $editAluno['id']; ?>">
                    <?php endif; ?>
                    
                    <!-- Informações Pessoais -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-blue-800 mb-4 flex items-center">
                            <i class="fas fa-user mr-2"></i>
                            Informações Pessoais
                        </h3>
                        
                        <!-- Layout compacto para impressão -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                                <input type="text" name="nome" value="<?php echo $editAluno ? htmlspecialchars($editAluno['nome']) : ''; ?>" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" value="<?php echo $editAluno ? $editAluno['data_nascimento'] : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                <input type="text" name="cpf" value="<?php echo $editAluno ? htmlspecialchars($editAluno['cpf']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                                <input type="text" name="rg" value="<?php echo $editAluno ? htmlspecialchars($editAluno['rg']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                                <select name="sexo" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione</option>
                                    <option value="M" <?php echo ($editAluno && $editAluno['sexo'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="F" <?php echo ($editAluno && $editAluno['sexo'] === 'F') ? 'selected' : ''; ?>>Feminino</option>
                                    <option value="Outro" <?php echo ($editAluno && $editAluno['sexo'] === 'Outro') ? 'selected' : ''; ?>>Outro</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                <input type="text" name="telefone" value="<?php echo $editAluno ? htmlspecialchars($editAluno['telefone']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Celular</label>
                                <input type="text" name="celular" value="<?php echo $editAluno ? htmlspecialchars($editAluno['celular']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                <input type="email" name="email" value="<?php echo $editAluno ? htmlspecialchars($editAluno['email']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Campo de Foto -->
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Foto 3x4</label>
                                <div class="flex items-start space-x-4">
                                    <!-- Preview da foto -->
                                    <div class="flex-shrink-0">
                                        <div id="photo-preview" class="w-24 h-32 border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center bg-gray-50 overflow-hidden">
                                            <?php if ($editAluno && !empty($editAluno['foto'])): ?>
                                                <img src="../uploads/fotos/<?php echo htmlspecialchars($editAluno['foto']); ?>" alt="Foto do aluno" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="text-center text-gray-400">
                                                    <i class="fas fa-camera text-2xl mb-1"></i>
                                                    <p class="text-xs">Foto 3x4</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Input de upload -->
                                    <div class="flex-1">
                                        <input type="file" name="foto" id="foto-input" accept="image/*" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <p class="text-xs text-gray-500 mt-1">Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB</p>
                                        <?php if ($editAluno && !empty($editAluno['foto'])): ?>
                                            <p class="text-xs text-green-600 mt-1">Foto atual: <?php echo htmlspecialchars($editAluno['foto']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="md:col-span-3">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Endereço Completo</label>
                                <input type="text" name="endereco" value="<?php echo $editAluno ? htmlspecialchars($editAluno['endereco']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CEP</label>
                                <input type="text" name="cep" value="<?php echo $editAluno ? htmlspecialchars($editAluno['cep']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cidade</label>
                                <input type="text" name="cidade" value="<?php echo $editAluno ? htmlspecialchars($editAluno['cidade']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <input type="text" name="estado" value="<?php echo $editAluno ? htmlspecialchars($editAluno['estado']) : ''; ?>" 
                                       maxlength="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Título de Eleitor -->
                    <div class="bg-green-50 p-4 rounded-lg border-l-4 border-green-400">
                        <h3 class="text-lg font-medium text-green-800 mb-4 flex items-center">
                            <i class="fas fa-vote-yea mr-2"></i>
                            Título de Eleitor
                            <span class="text-sm font-normal text-green-600 ml-2">(Opcional)</span>
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Número de Inscrição</label>
                                <input type="text" name="titulo_inscricao" value="<?php echo $editAluno ? htmlspecialchars($editAluno['titulo_inscricao']) : ''; ?>" 
                                       placeholder="Ex: 123456789012" maxlength="12"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Zona</label>
                                <input type="text" name="titulo_zona" value="<?php echo $editAluno ? htmlspecialchars($editAluno['titulo_zona']) : ''; ?>" 
                                       placeholder="Ex: 001" maxlength="3"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Seção</label>
                                <input type="text" name="titulo_secao" value="<?php echo $editAluno ? htmlspecialchars($editAluno['titulo_secao']) : ''; ?>" 
                                       placeholder="Ex: 0123" maxlength="4"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Município/UF</label>
                                <input type="text" name="titulo_municipio_uf" value="<?php echo $editAluno ? htmlspecialchars($editAluno['titulo_municipio_uf']) : ''; ?>" 
                                       placeholder="Ex: São Paulo/SP"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                        </div>
                    </div>

                    <!-- Responsável -->
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-yellow-800 mb-4 flex items-center">
                            <i class="fas fa-user-tie mr-2"></i>
                            Informações do Responsável
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Responsável</label>
                                <input type="text" name="nome_responsavel" value="<?php echo $editAluno ? htmlspecialchars($editAluno['nome_responsavel']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone do Responsável</label>
                                <input type="text" name="telefone_responsavel" value="<?php echo $editAluno ? htmlspecialchars($editAluno['telefone_responsavel']) : ''; ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Observações -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-sticky-note mr-2"></i>
                            Observações
                        </h3>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="observacoes" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo $editAluno ? htmlspecialchars($editAluno['observacoes']) : ''; ?></textarea>
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="pt-6 no-print">
                        <!-- Botão Imprimir - Destacado -->
                        <div class="mb-4 text-center">
                            <button type="button" class="bg-green-600 text-white px-8 py-3 rounded-lg hover:bg-green-700 transition-colors shadow-lg transform hover:scale-105" onclick="gerarPDF()">
                                <i class="fas fa-file-pdf mr-2"></i>Gerar PDF
                            </button>
                            <p class="text-sm text-gray-600 mt-2">Clique para gerar um PDF personalizado do cadastro</p>
                        </div>
                        
                        <!-- Botões de Ação -->
                        <div class="flex justify-between items-center">
                            <div>
                                <?php if ($editAluno): ?>
                                    <a href="alunos.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition-colors">
                                        <i class="fas fa-times mr-2"></i>Cancelar
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700 transition-colors shadow-md">
                                    <i class="fas fa-save mr-2"></i>
                                    <?php echo $editAluno ? 'Atualizar Aluno' : 'Cadastrar Aluno'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Área de Assinatura (apenas na impressão) -->
                <div class="print-only signature-area">
                    <p class="mb-8">Declaro que as informações fornecidas são verdadeiras.</p>
                    <div class="flex justify-between items-end">
                        <div class="text-center">
                            <div class="border-t border-black w-64 mb-2"></div>
                            <p class="text-sm">Assinatura do Aluno/Responsável</p>
                        </div>
                        <div class="text-center">
                            <div class="border-t border-black w-64 mb-2"></div>
                            <p class="text-sm">Data: ___/___/______</p>
                        </div>
                    </div>
                </div>
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
        
        // Função para gerar PDF personalizado
        function gerarPDF() {
            try {
                console.log('Função gerarPDF() chamada');
                
                // Verifica se há um aluno sendo editado
                const editId = new URLSearchParams(window.location.search).get('edit');
                if (!editId) {
                    alert('Selecione um aluno para gerar o PDF.');
                    return;
                }
                
                // Redireciona para o gerador de PDF
                window.open('../gerar_pdf.php?id=' + editId, '_blank');
                
            } catch (error) {
                console.error('Erro na função gerarPDF():', error);
                alert('Erro ao tentar gerar o PDF. Verifique o console para mais detalhes.');
            }
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
            
            // Preview da foto
            const fotoInput = document.getElementById('foto-input');
            const photoPreview = document.getElementById('photo-preview');
            
            if (fotoInput && photoPreview) {
                fotoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    
                    if (file) {
                        // Validar tipo de arquivo
                        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!validTypes.includes(file.type)) {
                            alert('Por favor, selecione um arquivo de imagem válido (JPG, PNG ou GIF).');
                            this.value = '';
                            return;
                        }
                        
                        // Validar tamanho do arquivo (2MB)
                        if (file.size > 2 * 1024 * 1024) {
                            alert('O arquivo deve ter no máximo 2MB.');
                            this.value = '';
                            return;
                        }
                        
                        // Criar preview
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            photoPreview.innerHTML = `<img src="${e.target.result}" alt="Preview da foto" class="w-full h-full object-cover">`;
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Toggle do Sidebar
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