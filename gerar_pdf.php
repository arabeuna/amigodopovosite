<?php
require_once('config/database.php');
require_once('tcpdf/tcpdf.php');

// Verificar se foi passado um ID de aluno
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID do aluno não fornecido.');
}

$aluno_id = (int)$_GET['id'];

// Buscar dados do aluno e matrículas no banco de dados
try {
    $database = new Database();
    
    // Primeiro, buscar dados básicos do aluno
    $sql_aluno = "SELECT * FROM alunos WHERE id = ?";
    $stmt_aluno = $database->query($sql_aluno, [$aluno_id]);
    $aluno = $stmt_aluno->fetch(PDO::FETCH_ASSOC);
    
    if (!$aluno) {
        die('Aluno não encontrado.');
    }
    
    // Depois, buscar todas as matrículas ativas do aluno
    $sql_matriculas = "
        SELECT m.data_matricula, m.status as status_matricula,
               t.nome as turma_nome, t.horario_inicio, t.horario_fim,
               at.nome as atividade_nome, at.descricao as atividade_descricao
        FROM matriculas m
        INNER JOIN turmas t ON m.turma_id = t.id
        INNER JOIN atividades at ON t.atividade_id = at.id
        WHERE m.aluno_id = ? AND m.status = 'ativa'
        ORDER BY m.data_matricula DESC
    ";
    $stmt_matriculas = $database->query($sql_matriculas, [$aluno_id]);
    $matriculas = $stmt_matriculas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erro ao buscar dados do aluno: ' . $e->getMessage());
}

// Criar nova instância do TCPDF
class MYPDF extends TCPDF {
    // Cabeçalho personalizado
    public function Header() {
        // Exibir cabeçalho apenas na primeira página
        if ($this->getPage() == 1) {
            // Logo da associação (posicionamento melhorado)
            $logo_path = 'assets/images/logo_associacao.jpg';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 15, 8, 25, 25, '', '', '', false, 300, '', false, false, 1);
            }
            
            // Título principal (melhor posicionamento)
            $this->SetFont('helvetica', 'B', 16);
            $this->SetTextColor(0, 51, 102);
            $this->SetXY(45, 12);
            $this->Cell(0, 8, 'FICHA DE CADASTRO DE ALUNO', 0, false, 'L', 0, '', 0, false, 'M', 'M');
            
            // Subtítulo (melhor espaçamento)
            $this->SetFont('helvetica', '', 11);
            $this->SetTextColor(80, 80, 80);
            $this->SetXY(45, 20);
            $this->Cell(0, 6, 'Associação Amigo do Povo', 0, false, 'L', 0, '', 0, false, 'M', 'M');
            
            // Data de geração (canto superior direito)
            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(120, 120, 120);
            $this->SetXY(150, 10);
            $this->Cell(0, 4, 'Gerado em: ' . date('d/m/Y'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
            
            // Linha separadora (mais elegante)
            $this->SetDrawColor(0, 51, 102);
            $this->SetLineWidth(0.5);
            $this->Line(15, 33, 195, 33);
            
            $this->Ln(28);
        }
    }
    
    // Rodapé removido para evitar bugs de sobreposição entre páginas
}

// Criar PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurações do documento
$pdf->SetCreator('Sistema de Associação');
$pdf->SetAuthor('Associação');
$pdf->SetTitle('Ficha de Cadastro - ' . $aluno['nome']);
$pdf->SetSubject('Ficha de Cadastro');
$pdf->SetKeywords('cadastro, aluno, ficha');

// Configurações da página
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(0);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Adicionar página
$pdf->AddPage();

// Definir fonte principal
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

// Espaço inicial - ajustar conforme a página
if ($pdf->getPage() == 1) {
    // Primeira página tem cabeçalho, precisa de mais espaço
    $pdf->Ln(15);
} else {
    // Páginas subsequentes não têm cabeçalho
    $pdf->Ln(3);
}

// Área da foto (posicionamento melhorado)
$foto_x = 15;
$foto_y = $pdf->GetY();
$foto_width = 38;
$foto_height = 48;

// Moldura elegante para a foto
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.8);
$pdf->Rect($foto_x, $foto_y, $foto_width, $foto_height, 'D');

// Moldura interna mais sutil
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);
$pdf->Rect($foto_x + 2, $foto_y + 2, $foto_width - 4, $foto_height - 4, 'D');

// Verificar se existe foto
if (!empty($aluno['foto']) && file_exists('uploads/fotos/' . $aluno['foto'])) {
    $pdf->Image('uploads/fotos/' . $aluno['foto'], $foto_x + 3, $foto_y + 3, $foto_width - 6, $foto_height - 6, '', '', '', false, 300, '', false, false, 1);
} else {
    // Placeholder para foto mais elegante
    $pdf->SetFillColor(248, 249, 250);
    $pdf->Rect($foto_x + 3, $foto_y + 3, $foto_width - 6, $foto_height - 6, 'F');
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY($foto_x + 8, $foto_y + 20);
    $pdf->Cell($foto_width - 16, 6, 'FOTO', 0, 1, 'C');
    $pdf->SetXY($foto_x + 8, $foto_y + 26);
    $pdf->Cell($foto_width - 16, 6, '3x4', 0, 1, 'C');
}

// Informações pessoais (lado direito da foto, melhor espaçamento)
$info_x = $foto_x + $foto_width + 12;
$pdf->SetXY($info_x, $foto_y + 2);
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'INFORMAÇÕES PESSOAIS', 0, 1);

// Linha decorativa sob o título
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.4);
$pdf->Line($info_x, $pdf->GetY(), $info_x + 120, $pdf->GetY());
$pdf->Ln(3);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetX($info_x);

// Nome completo
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(40, 6, 'Nome Completo:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['nome'] ?? '', 0, 1);
$pdf->SetX($info_x);

// Data de nascimento
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(40, 6, 'Data de Nascimento:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$data_nasc = !empty($aluno['data_nascimento']) ? date('d/m/Y', strtotime($aluno['data_nascimento'])) : '';
$pdf->Cell(0, 6, $data_nasc, 0, 1);
$pdf->SetX($info_x);

// CPF
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(40, 6, 'CPF:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['cpf'] ?? '', 0, 1);
$pdf->SetX($info_x);

// RG
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(40, 6, 'RG:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['rg'] ?? '', 0, 1);
$pdf->SetX($info_x);

// Sexo
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(40, 6, 'Sexo:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['sexo'] ?? '', 0, 1);

// Pular para baixo da foto com melhor espaçamento
$pdf->SetY($foto_y + $foto_height + 15);

// Seção de Contato com design melhorado
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'INFORMAÇÕES DE CONTATO', 0, 1);

// Linha decorativa
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.4);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

// Layout em duas colunas para contato
$col1_x = 15;
$col2_x = 110;
$current_y = $pdf->GetY();

// Coluna 1
$pdf->SetXY($col1_x, $current_y);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(25, 6, 'Telefone:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(70, 6, $aluno['telefone'] ?? '', 0, 0);

// Coluna 2
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(20, 6, 'E-mail:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['email'] ?? '', 0, 1);

$pdf->Ln(8);

// Seção de Endereço com design melhorado
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'ENDEREÇO', 0, 1);

// Linha decorativa
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.4);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(25, 6, 'Endereço:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['endereco'] ?? '', 0, 1);

// Layout em três colunas para CEP, Cidade, Estado
$current_y = $pdf->GetY();

$pdf->SetXY(15, $current_y);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(15, 6, 'CEP:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(40, 6, $aluno['cep'] ?? '', 0, 0);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(18, 6, 'Cidade:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(50, 6, $aluno['cidade'] ?? '', 0, 0);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(18, 6, 'Estado:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['estado'] ?? '', 0, 1);

$pdf->Ln(5);

// Seção Título de Eleitor com design melhorado
if (!empty($aluno['titulo_inscricao'])) {
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'TÍTULO DE ELEITOR', 0, 1);
    
    // Linha decorativa
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Layout em duas linhas
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(25, 6, 'Inscrição:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(50, 6, $aluno['titulo_inscricao'] ?? '', 0, 0);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(15, 6, 'Zona:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(30, 6, $aluno['titulo_zona'] ?? '', 0, 0);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(15, 6, 'Seção:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['titulo_secao'] ?? '', 0, 1);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 6, 'Município/UF:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['titulo_municipio_uf'] ?? '', 0, 1);
    
    $pdf->Ln(5);
}

// Seção Responsável com design melhorado
if (!empty($aluno['nome_responsavel'])) {
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'INFORMAÇÕES DO RESPONSÁVEL', 0, 1);
    
    // Linha decorativa
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 6, 'Nome do Responsável:', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, $aluno['nome_responsavel'] ?? '', 0, 1);
    
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 6, 'Telefone do Responsável:', 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, $aluno['telefone_responsavel'] ?? '', 0, 1);
    
    $pdf->Ln(5);
}

// Seção Matrículas com design melhorado
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'INFORMAÇÕES DAS MATRÍCULAS', 0, 1);

// Linha decorativa
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.4);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

if (!empty($matriculas)) {
    // Percorrer todas as matrículas ativas
    foreach ($matriculas as $index => $matricula) {
        // Se há mais de uma matrícula, adicionar numeração
        if (count($matriculas) > 1) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(0, 51, 102);
            $pdf->Cell(0, 6, 'Matrícula ' . ($index + 1) . ':', 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(2);
        }
        
        // Atividade
        if (!empty($matricula['atividade_nome'])) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 6, 'Atividade:', 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $matricula['atividade_nome'], 0, 1);
            
            if (!empty($matricula['atividade_descricao'])) {
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->Cell(25, 6, 'Descrição:', 0, 0);
                $pdf->SetFont('helvetica', '', 9);
                $pdf->Cell(0, 6, $matricula['atividade_descricao'], 0, 1);
            }
        }
        
        // Turma e Horário
        if (!empty($matricula['turma_nome'])) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(25, 6, 'Turma:', 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            $turma_info = $matricula['turma_nome'];
            if (!empty($matricula['horario_inicio']) && !empty($matricula['horario_fim'])) {
                $turma_info .= ' - ' . date('H:i', strtotime($matricula['horario_inicio'])) . ' às ' . date('H:i', strtotime($matricula['horario_fim']));
            }
            $pdf->Cell(0, 6, $turma_info, 0, 1);
        }
        
        // Data de Matrícula e Status
        if (!empty($matricula['data_matricula'])) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(30, 6, 'Data da Matrícula:', 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            $data_matricula = date('d/m/Y', strtotime($matricula['data_matricula']));
            $pdf->Cell(50, 6, $data_matricula, 0, 0);
            
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(20, 6, 'Status:', 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(0, 6, $matricula['status_matricula'] ?? 'Ativo', 0, 1);
        }
        
        // Espaçamento entre matrículas
        if ($index < count($matriculas) - 1) {
            $pdf->Ln(3);
        }
    }
} else {
    // Caso não tenha matrículas ativas
    $pdf->SetFont('helvetica', 'I', 9);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(0, 6, 'Nenhuma matrícula ativa encontrada.', 0, 1);
}

$pdf->Ln(5);

// Seção Observações com design melhorado
if (!empty($aluno['observacoes'])) {
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'OBSERVAÇÕES', 0, 1);
    
    // Linha decorativa
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Caixa com fundo sutil para observações
    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetDrawColor(220, 220, 220);
    $pdf->SetLineWidth(0.2);
    
    // Calcular altura necessária para o texto
    $text_height = $pdf->getStringHeight(180, $aluno['observacoes']);
    $box_height = max($text_height + 4, 12);
    
    $pdf->Rect(15, $pdf->GetY(), 180, $box_height, 'DF');
    $pdf->SetXY(17, $pdf->GetY() + 2);
    $pdf->MultiCell(176, 6, $aluno['observacoes'], 0, 'L');
    $pdf->SetY($pdf->GetY() + $box_height - $text_height + 3);
}

// Área de assinatura melhorada
$pdf->Ln(15);

// Caixa decorativa para a declaração
$pdf->SetFillColor(245, 248, 252);
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.5);
$pdf->Rect(15, $pdf->GetY(), 180, 35, 'DF');

$pdf->SetXY(15, $pdf->GetY() + 5);
$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(180, 8, 'DECLARAÇÃO E ASSINATURA', 0, 1, 'C');

$pdf->SetX(20);
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(170, 6, 'Declaro que as informações prestadas são verdadeiras e assumo total responsabilidade pelas mesmas.', 0, 'J', false, 1);

$pdf->Ln(20);

// Linhas de assinatura mais elegantes
$pdf->SetDrawColor(0, 51, 102);
$pdf->SetLineWidth(0.6);
$pdf->Line(25, $pdf->GetY(), 95, $pdf->GetY());
$pdf->Line(115, $pdf->GetY(), 185, $pdf->GetY());

$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(70, 5, 'Assinatura do Aluno/Responsável', 0, 0, 'C');
$pdf->Cell(20, 5, '', 0, 0);
$pdf->Cell(70, 5, 'Data: ___/___/______', 0, 1, 'C');

// Gerar e enviar o PDF
$filename = 'Ficha_Cadastro_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $aluno['nome']) . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D'); // 'D' para download
?>