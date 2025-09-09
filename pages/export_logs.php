<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
session_start();

// Verificar se o usuário está logado e é master
$database = new Database();
$auth = new AuthSystem($database);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    die('Não autorizado');
}

if (!$auth->hasUserType('master')) {
    http_response_code(403);
    die('Acesso negado. Apenas usuários master podem exportar logs.');
}

// Verificar se é uma requisição de exportação
if (!isset($_GET['export']) || $_GET['export'] !== 'csv') {
    http_response_code(400);
    die('Parâmetro de exportação inválido');
}

// Aplicar os mesmos filtros da página de logs
$filtros = [];
if (!empty($_GET['data_inicio'])) $filtros['data_inicio'] = $_GET['data_inicio'];
if (!empty($_GET['data_fim'])) $filtros['data_fim'] = $_GET['data_fim'];
if (!empty($_GET['usuario_id'])) $filtros['usuario_id'] = $_GET['usuario_id'];
if (!empty($_GET['acao'])) $filtros['acao'] = $_GET['acao'];
if (!empty($_GET['modulo'])) $filtros['modulo'] = $_GET['modulo'];
if (!empty($_GET['ip'])) $filtros['ip'] = $_GET['ip'];

// Buscar todos os logs (sem paginação para exportação)
$logs = $auth->getAuditLogs($filtros, 10000); // Limite de 10k registros para evitar timeout

// Log da exportação
$auth->logAction('export', 'logs', 'Exportação de logs de auditoria', [
    'total_registros' => count($logs),
    'filtros' => $filtros
]);

// Configurar headers para download do CSV
$filename = 'logs_auditoria_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Criar o arquivo CSV
$output = fopen('php://output', 'w');

// Adicionar BOM para UTF-8 (para Excel reconhecer acentos)
fputs($output, "\xEF\xBB\xBF");

// Cabeçalhos do CSV
$headers = [
    'Data',
    'Hora',
    'Usuário',
    'Email',
    'Ação',
    'Módulo',
    'Detalhes',
    'IP',
    'User Agent'
];
fputcsv($output, $headers, ';');

// Dados dos logs
foreach ($logs as $log) {
    $row = [
        date('d/m/Y', strtotime($log['data_acao'])),
        date('H:i:s', strtotime($log['data_acao'])),
        $log['usuario_nome'] ?? 'Sistema',
        $log['usuario_email'] ?? '',
        ucfirst($log['acao']),
        ucfirst($log['modulo']),
        $log['detalhes'],
        $log['ip_address'],
        $log['user_agent']
    ];
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
?>