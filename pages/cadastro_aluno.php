<?php
require_once '../config/config.php';
require_once '../config/database.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    redirect('../auth/login.php');
}

// Inicializar conexão com banco de dados
$db = new Database();

$message = '';
$error = '';
$aluno_id = null;

// Buscar atividades para o dropdown
$atividades = $db->query("SELECT id, nome FROM atividades WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Buscar turmas para o dropdown
$turmas = $db->query("SELECT id, nome, horario_inicio, horario_fim FROM turmas WHERE ativo = 1 ORDER BY nome")->fetchAll();

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = sanitize_input($_POST['nome'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $cpf = sanitize_input($_POST['cpf'] ?? '');
    $rg = sanitize_input($_POST['rg'] ?? '');
    $titulo_eleitor = sanitize_input($_POST['titulo_eleitor'] ?? '');
    $zona_eleitoral = sanitize_input($_POST['zona_eleitoral'] ?? '');
    $municipio = sanitize_input($_POST['municipio'] ?? '');
    $sexo = $_POST['sexo'] ?? '';
    $estado_civil = $_POST['estado_civil'] ?? '';
    $telefone = sanitize_input($_POST['telefone'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $endereco_completo = sanitize_input($_POST['endereco'] ?? '');
    $estado = sanitize_input($_POST['estado'] ?? '');
    $atividade_id = (int)($_POST['atividade_id'] ?? 0);
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $data_inicio = $_POST['data_inicio'] ?? '';
    $status = $_POST['status'] ?? 'ativo';
    $nome_responsavel = sanitize_input($_POST['nome_responsavel'] ?? '');
    $telefone_responsavel = sanitize_input($_POST['telefone_responsavel'] ?? '');
    
    if (empty($nome)) {
        $error = 'Nome completo é obrigatório';
    } elseif ($turma_id <= 0) {
        $error = 'Selecione uma turma para matrícula';
    } else {
        // Processar upload da foto
        $foto_nome = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['foto']['tmp_name'];
            $foto_original = $_FILES['foto']['name'];
            $foto_size = $_FILES['foto']['size'];
            $foto_type = $_FILES['foto']['type'];
            
            // Validar tipo de arquivo
            $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($foto_type, $tipos_permitidos)) {
                $error = 'Tipo de arquivo não permitido. Use JPG, PNG ou GIF.';
            }
            // Validar tamanho (2MB máximo)
            elseif ($foto_size > 2 * 1024 * 1024) {
                $error = 'Arquivo muito grande. Tamanho máximo: 2MB.';
            }
            else {
                // Gerar nome único para o arquivo
                $extensao = pathinfo($foto_original, PATHINFO_EXTENSION);
                $foto_nome = uniqid('foto_') . '.' . $extensao;
                $foto_destino = 'uploads/fotos/' . $foto_nome;
                
                // Mover arquivo para pasta de destino
                if (!move_uploaded_file($foto_tmp, '../' . $foto_destino)) {
                    $error = 'Erro ao fazer upload da foto.';
                    $foto_nome = null;
                }
            }
        }
        
        if (empty($error)) {
            try {
                // Inserir aluno
                $sql = "INSERT INTO alunos (nome, cpf, rg, data_nascimento, sexo, telefone, email, endereco, estado, nome_responsavel, telefone_responsavel, foto, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$nome, $cpf, $rg, $data_nascimento, $sexo, $telefone, $email, $endereco_completo, $estado, $nome_responsavel, $telefone_responsavel, $foto_nome]);
                
                $aluno_id = $db->lastInsertId();
                
                // Inserir matrícula
                if ($turma_id > 0) {
                    $sql_matricula = "INSERT INTO matriculas (aluno_id, turma_id, data_matricula, status) VALUES (?, ?, NOW(), ?)";
                    $stmt_matricula = $db->prepare($sql_matricula);
                    $result_matricula = $stmt_matricula->execute([$aluno_id, $turma_id, $status]);
                }
                
                $message = 'Aluno cadastrado com sucesso!';
            } catch (Exception $e) {
                $error = 'Erro ao cadastrar aluno: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Aluno - <?php echo SITE_NAME; ?></title>
    <link href="../assets/css/tailwind.min.css" rel="stylesheet">
    <link href="../assets/css/enhanced.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }
            
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .sidebar, .navbar, .btn, .no-print {
                display: none !important;
            }
            
            body {
                font-family: Arial, sans-serif !important;
                font-size: 11px !important;
                line-height: 1.2 !important;
                color: #000 !important;
            }
            
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            
            .container-fluid {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .card {
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .card-header {
                background: none !important;
                border: none !important;
                padding: 0 0 10px 0 !important;
                text-align: center !important;
            }
            
            .card-body {
                padding: 0 !important;
            }
            
            h2 {
                font-size: 16px !important;
                font-weight: bold !important;
                margin: 0 0 15px 0 !important;
                text-align: center !important;
                text-transform: uppercase !important;
            }
            
            .form-group {
                margin-bottom: 6px !important;
                page-break-inside: avoid !important;
            }
            
            .form-group label {
                font-size: 10px !important;
                font-weight: bold !important;
                margin-bottom: 2px !important;
                display: block !important;
            }
            
            .form-control, .form-select {
                border: 1px solid #000 !important;
                border-radius: 0 !important;
                font-size: 10px !important;
                padding: 2px 4px !important;
                height: auto !important;
                min-height: 20px !important;
                background: white !important;
                width: 100% !important;
            }
            
            .row {
                margin: 0 !important;
                display: flex !important;
                flex-wrap: wrap !important;
            }
            
            .col-md-6 {
                width: 50% !important;
                padding: 0 3px !important;
                flex: 0 0 50% !important;
            }
            
            .col-md-4 {
                width: 33.333% !important;
                padding: 0 3px !important;
                flex: 0 0 33.333% !important;
            }
            
            .col-md-3 {
                width: 25% !important;
                padding: 0 3px !important;
                flex: 0 0 25% !important;
            }
            
            .col-12 {
                width: 100% !important;
                padding: 0 3px !important;
            }
            
            .photo-area {
                border: 2px solid #000 !important;
                width: 80px !important;
                height: 100px !important;
                float: right !important;
                margin: 0 0 10px 10px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 9px !important;
                text-align: center !important;
            }
            
            .signature-area {
                border: 1px solid #000 !important;
                height: 50px !important;
                margin-top: 15px !important;
                position: relative !important;
                page-break-inside: avoid !important;
            }
            
            .signature-area::after {
                content: "Assinatura do Responsável: ____________________________";
                position: absolute;
                bottom: 5px;
                left: 10px;
                font-size: 10px;
                font-weight: bold;
            }
            
            .print-info {
                margin-top: 10px !important;
                font-size: 9px !important;
                text-align: center !important;
                border-top: 1px solid #ccc !important;
                padding-top: 5px !important;
            }
            
            .print-only { display: block !important; }
            body { font-size: 12px; }
            .form-container { max-width: none; margin: 0; padding: 20px; }
        }
        .print-only { display: none; }
        .photo-placeholder {
            width: 120px;
            height: 150px;
            border: 2px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body class="bg-gray-100">
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
                <a href="dashboard.php" class="sidebar-link">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="alunos.php" class="sidebar-link active">
                    <i class="fas fa-users"></i>
                    <span>Alunos</span>
                </a>
                <a href="atividades.php" class="sidebar-link">
                    <i class="fas fa-dumbbell"></i>
                    <span>Atividades</span>
                </a>
                <a href="turmas.php" class="sidebar-link">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Turmas</span>
                </a>
                <a href="presencas.php" class="sidebar-link">
                    <i class="fas fa-calendar-check"></i>
                    <span>Presenças</span>
                </a>
                <a href="matriculas.php" class="sidebar-link">
                    <i class="fas fa-user-plus"></i>
                    <span>Matrículas</span>
                </a>
                <a href="relatorios.php" class="sidebar-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="form-container max-w-4xl mx-auto bg-white rounded-lg shadow-lg p-8">
                <!-- Header -->
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center">
                        <img src="../assets/images/icon-192x192 (1).png" alt="Logo" class="w-12 h-12 mr-4">
                        <h1 class="text-2xl font-bold text-gray-800">Associação Amigo do Povo</h1>
                    </div>
                    <div class="photo-placeholder">
                        FOTO 3x4
                    </div>
                </div>

                <!-- Título da Seção -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-700 flex items-center">
                        <i class="fas fa-edit mr-2"></i>
                        Dados do Aluno
                    </h2>
                </div>

                <!-- Mensagens -->
                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 no-print">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 no-print">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Formulário -->
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Informações Pessoais -->
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <h3 class="text-lg font-medium text-blue-800 mb-4 flex items-center">
                            <i class="fas fa-user mr-2"></i>
                            Informações Pessoais
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                                <input type="text" name="nome" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Nascimento *</label>
                                <input type="date" name="data_nascimento" required 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                <input type="text" name="cpf" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">RG</label>
                                <input type="text" name="rg" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Título de Eleitor</label>
                                <input type="text" name="titulo_eleitor" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Zona Eleitoral</label>
                                <input type="text" name="zona_eleitoral" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Município</label>
                                <input type="text" name="municipio" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sexo</label>
                                <select name="sexo" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione</option>
                                    <option value="M">Masculino</option>
                                    <option value="F">Feminino</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado Civil</label>
                                <select name="estado_civil" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione</option>
                                    <option value="Solteiro">Solteiro</option>
                                    <option value="Casado">Casado</option>
                                    <option value="Divorciado">Divorciado</option>
                                    <option value="Viúvo">Viúvo</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone *</label>
                                <input type="text" name="telefone" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                                <input type="email" name="email" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Endereço Completo</label>
                                <input type="text" name="endereco" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                                <input type="text" name="estado" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Foto 3x4</label>
                                <input type="file" name="foto" accept="image/*" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Formatos aceitos: JPG, PNG, GIF (máx. 2MB)</p>
                            </div>
                        </div>
                    </div>

                    <!-- Informações da Atividade -->
                    <div class="bg-green-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Atividade *</label>
                                <select name="atividade_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione uma atividade</option>
                                    <?php foreach ($atividades as $atividade): ?>
                                        <option value="<?php echo $atividade['id']; ?>"><?php echo $atividade['nome']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Turma/Horário *</label>
                                <select name="turma_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione uma turma</option>
                                    <?php foreach ($turmas as $turma): ?>
                                        <option value="<?php echo $turma['id']; ?>"><?php echo $turma['nome'] . ' - ' . date('H:i', strtotime($turma['horario_inicio'])) . ' às ' . date('H:i', strtotime($turma['horario_fim'])); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data de Início *</label>
                                <input type="date" name="data_inicio" value="<?php echo date('Y-m-d'); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                                <select name="status" 
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="Ativo">Ativo</option>
                                    <option value="Inativo">Inativo</option>
                                    <option value="Suspenso">Suspenso</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Responsável -->
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do responsável</label>
                                <input type="text" name="nome_responsavel" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Telefone do Responsável</label>
                                <input type="text" name="telefone_responsavel" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Botões -->
                    <div class="flex justify-between items-center pt-6 no-print">
                        <a href="alunos.php" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Voltar
                        </a>
                        
                        <button type="submit" class="bg-blue-600 text-white px-8 py-2 rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>Cadastrar Aluno
                        </button>
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