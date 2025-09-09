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
$export = $_GET['export'] ?? '';

// Função para obter aniversariantes (mesma da página principal)
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

// Função para calcular idade
function calcularIdade($data_nascimento) {
    if (!$data_nascimento) return null;
    $hoje = new DateTime();
    $nascimento = new DateTime($data_nascimento);
    return $hoje->diff($nascimento)->y;
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

if ($export === 'csv') {
    // Obter aniversariantes
    $aniversariantes = obterAniversariantes($pdo, $filtro_tipo, $filtro_valor);
    
    // Definir nome do arquivo baseado no filtro
    $nome_arquivo = 'aniversariantes_';
    switch ($filtro_tipo) {
        case 'mes':
            $nome_mes = [
                '01' => 'janeiro', '02' => 'fevereiro', '03' => 'marco', '04' => 'abril',
                '05' => 'maio', '06' => 'junho', '07' => 'julho', '08' => 'agosto',
                '09' => 'setembro', '10' => 'outubro', '11' => 'novembro', '12' => 'dezembro'
            ];
            $nome_arquivo .= $nome_mes[str_pad($filtro_valor, 2, '0', STR_PAD_LEFT)] ?? 'mes_' . $filtro_valor;
            break;
        case 'dia':
            $nome_arquivo .= 'dia_' . str_replace('-', '_', $filtro_valor);
            break;
        case 'semana':
            $nome_arquivo .= 'semana_' . str_replace('-', '_', $filtro_valor);
            break;
        case 'hoje':
            $nome_arquivo .= 'hoje_' . date('Y_m_d');
            break;
        default:
            $nome_arquivo .= 'filtro_personalizado';
    }
    
    $nome_arquivo .= '_' . date('Y_m_d_H_i_s') . '.csv';
    
    // Configurar headers para download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Criar arquivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (para Excel reconhecer acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeçalhos do CSV
    fputcsv($output, [
        'Nome',
        'Data de Nascimento',
        'Idade',
        'Telefone',
        'Celular',
        'Email',
        'Próximo Aniversário',
        'Dias para Aniversário',
        'CPF',
        'Endereço',
        'Cidade',
        'Estado'
    ], ';');
    
    // Dados dos aniversariantes
    foreach ($aniversariantes as $aniversariante) {
        $idade = calcularIdade($aniversariante['data_nascimento']);
        $proximo = proximoAniversario($aniversariante['data_nascimento']);
        $dias_para_aniversario = null;
        $data_proximo_aniversario = '';
        
        if ($proximo) {
            $dias_para_aniversario = ceil(($proximo->getTimestamp() - time()) / (24 * 60 * 60));
            $data_proximo_aniversario = $proximo->format('d/m/Y');
            
            if ($dias_para_aniversario == 0) {
                $dias_para_aniversario = 'Hoje';
            } elseif ($dias_para_aniversario == 1) {
                $dias_para_aniversario = 'Amanhã';
            } else {
                $dias_para_aniversario = $dias_para_aniversario . ' dias';
            }
        }
        
        fputcsv($output, [
            $aniversariante['nome'],
            $aniversariante['data_nascimento'] ? date('d/m/Y', strtotime($aniversariante['data_nascimento'])) : '',
            $idade ?? '',
            $aniversariante['telefone'] ?? '',
            $aniversariante['celular'] ?? '',
            $aniversariante['email'] ?? '',
            $data_proximo_aniversario,
            $dias_para_aniversario ?? '',
            $aniversariante['cpf'] ?? '',
            $aniversariante['endereco'] ?? '',
            $aniversariante['cidade'] ?? '',
            $aniversariante['estado'] ?? ''
        ], ';');
    }
    
    fclose($output);
    exit;
} else {
    // Redirecionar de volta para a página de aniversariantes se não for exportação
    header('Location: aniversariantes.php');
    exit;
}
?>