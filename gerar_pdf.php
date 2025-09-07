<?php
require_once('config/database.php');
require_once('tcpdf/tcpdf.php');

// Verificar se foi passado um ID de aluno
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('ID do aluno não fornecido.');
}

$aluno_id = (int)$_GET['id'];

// Buscar dados do aluno no banco de dados
try {
    $database = new Database();
    $stmt = $database->query("SELECT * FROM alunos WHERE id = ?", [$aluno_id]);
    $aluno = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$aluno) {
        die('Aluno não encontrado.');
    }
} catch (PDOException $e) {
    die('Erro ao buscar dados do aluno: ' . $e->getMessage());
}

// Criar nova instância do TCPDF
class MYPDF extends TCPDF {
    // Cabeçalho personalizado
    public function Header() {
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
    
    // Rodapé personalizado
    public function Footer() {
        $this->SetY(-18);
        
        // Linha separadora (mais elegante)
        $this->SetDrawColor(0, 51, 102);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        // Logo pequena no footer (melhor posicionamento)
        $logo_footer_path = 'assets/images/logo_associacao.jpg';
        if (file_exists($logo_footer_path)) {
            $this->Image($logo_footer_path, 15, $this->GetY() + 1, 8, 8, '', '', '', false, 300, '', false, false, 1);
        }
        
        // Informações do documento (melhor formatação)
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(90, 90, 90);
        $this->SetXY(45, $this->GetY() + 3);
        $this->Cell(0, 4, 'Documento gerado em: ' . date('d/m/Y H:i:s'), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        
        // Número da página (melhor posicionamento)
        $this->SetFont('helvetica', 'B', 7);
        $this->SetTextColor(0, 51, 102);
        $this->SetXY(150, $this->GetY());
        $this->Cell(0, 4, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
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
$pdf->SetMargins(15, 30, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Adicionar página
$pdf->AddPage();

// Definir fonte principal
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

// Espaço inicial (título já está no cabeçalho)
$pdf->Ln(3);

// Área da foto (posicionamento igual ao modelo)
$foto_x = 15;
$foto_y = $pdf->GetY();
$foto_width = 35;
$foto_height = 45;

// Moldura da foto removida para evitar borda preta

// Verificar se existe foto
if (!empty($aluno['foto']) && file_exists('uploads/fotos/' . $aluno['foto'])) {
    $pdf->Image('uploads/fotos/' . $aluno['foto'], $foto_x + 1, $foto_y + 1, $foto_width - 2, $foto_height - 2, '', '', '', false, 300, '', false, false, 1);
} else {
    // Placeholder para foto
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->SetXY($foto_x + 5, $foto_y + 20);
    $pdf->Cell($foto_width - 10, 5, 'FOTO', 0, 1, 'C');
    $pdf->SetXY($foto_x + 5, $foto_y + 25);
    $pdf->Cell($foto_width - 10, 5, '3x4', 0, 1, 'C');
}

// Informações pessoais (lado direito da foto, igual ao modelo)
$info_x = $foto_x + $foto_width + 10;
$pdf->SetXY($info_x, $foto_y);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'INFORMAÇÕES PESSOAIS', 0, 1);

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

// Pular para baixo da foto
$pdf->SetY($foto_y + $foto_height + 10);

// Seção de Contato
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'INFORMAÇÕES DE CONTATO', 0, 1);
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

$pdf->Ln(5);

// Seção de Endereço
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'ENDEREÇO', 0, 1);
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

// Seção Título de Eleitor
if (!empty($aluno['titulo_inscricao'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'TÍTULO DE ELEITOR', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Layout em duas linhas
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(25, 6, 'Inscrição:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(50, 6, $aluno['titulo_eleitor'] ?? '', 0, 0);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(15, 6, 'Zona:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(30, 6, $aluno['zona_eleitoral'] ?? '', 0, 0);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(15, 6, 'Seção:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['secao_eleitoral'] ?? '', 0, 1);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(35, 6, 'Município/UF:', 0, 0);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, $aluno['municipio_titulo'] ?? '', 0, 1);
    
    $pdf->Ln(5);
}

// Seção Responsável
if (!empty($aluno['nome_responsavel'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'INFORMAÇÕES DO RESPONSÁVEL', 0, 1);
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

// Seção Observações
if (!empty($aluno['observacoes'])) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColor(0, 51, 102);
    $pdf->Cell(0, 8, 'OBSERVAÇÕES', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Usar MultiCell para texto longo
    $pdf->MultiCell(0, 6, $aluno['observacoes'], 0, 'L');
    $pdf->Ln(5);
}

// Área de assinatura
$pdf->Ln(12);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColor(0, 51, 102);
$pdf->Cell(0, 8, 'DECLARAÇÃO E ASSINATURA', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->MultiCell(0, 6, 'Declaro que as informações prestadas são verdadeiras e assumo total responsabilidade pelas mesmas.', 0, 'J', false, 1);
$pdf->Ln(18);

// Linhas de assinatura
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, $pdf->GetY(), 95, $pdf->GetY());
$pdf->Line(110, $pdf->GetY(), 190, $pdf->GetY());

$pdf->Ln(6);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(80, 5, 'Assinatura do Aluno/Responsável', 0, 0, 'C');
$pdf->Cell(15, 5, '', 0, 0);
$pdf->Cell(80, 5, 'Data: ___/___/______', 0, 1, 'C');

// Gerar e enviar o PDF
$filename = 'Ficha_Cadastro_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $aluno['nome']) . '_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D'); // 'D' para download
?>