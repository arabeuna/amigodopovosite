-- Banco de dados: associacao_amigo_povo
-- Criação das tabelas para o sistema da Associação Amigo do Povo

CREATE DATABASE IF NOT EXISTS associacao_amigo_povo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE associacao_amigo_povo;

-- Tabela de usuários do sistema
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin', 'funcionario') DEFAULT 'funcionario',
    ativo BOOLEAN DEFAULT TRUE,
    ultimo_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de atividades
CREATE TABLE atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    categoria VARCHAR(50),
    idade_minima INT DEFAULT 0,
    idade_maxima INT DEFAULT 100,
    capacidade_maxima INT DEFAULT 50,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de turmas
CREATE TABLE turmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    atividade_id INT NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_fim TIME NOT NULL,
    dias_semana SET('segunda', 'terca', 'quarta', 'quinta', 'sexta', 'sabado', 'domingo') NOT NULL,
    capacidade_maxima INT DEFAULT 30,
    professor VARCHAR(100),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (atividade_id) REFERENCES atividades(id) ON DELETE CASCADE
);

-- Tabela de alunos
CREATE TABLE alunos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) UNIQUE,
    rg VARCHAR(20),
    data_nascimento DATE,
    sexo ENUM('M', 'F', 'Outro'),
    telefone VARCHAR(20),
    celular VARCHAR(20),
    email VARCHAR(100),
    endereco TEXT,
    cep VARCHAR(10),
    cidade VARCHAR(50),
    estado VARCHAR(2),
    nome_responsavel VARCHAR(100),
    telefone_responsavel VARCHAR(20),
    observacoes TEXT,
    foto VARCHAR(255),
    titulo_inscricao VARCHAR(12),
    titulo_zona VARCHAR(3),
    titulo_secao VARCHAR(4),
    titulo_municipio_uf VARCHAR(100),
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de matrículas (relaciona alunos com turmas)
CREATE TABLE matriculas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    turma_id INT NOT NULL,
    data_matricula DATE NOT NULL,
    data_cancelamento DATE NULL,
    status ENUM('ativa', 'cancelada', 'suspensa') DEFAULT 'ativa',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_matricula (aluno_id, turma_id)
);

-- Tabela de presenças
CREATE TABLE presencas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_id INT NOT NULL,
    aluno_id INT NOT NULL,
    turma_id INT NOT NULL,
    data_presenca DATE NOT NULL,
    presente BOOLEAN DEFAULT FALSE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_id) REFERENCES matriculas(id) ON DELETE CASCADE,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_presenca (aluno_id, turma_id, data_presenca)
);

-- Tabela de eventos/aulas especiais
CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT,
    data_evento DATE NOT NULL,
    horario_inicio TIME,
    horario_fim TIME,
    local VARCHAR(100),
    atividade_id INT,
    turma_id INT,
    capacidade_maxima INT,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (atividade_id) REFERENCES atividades(id) ON DELETE SET NULL,
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE SET NULL
);

-- Inserir usuário administrador padrão
INSERT INTO usuarios (nome, email, senha, tipo) VALUES 
('Administrador', 'admin@associacao.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Senha: admin123

-- Inserir algumas atividades de exemplo
INSERT INTO atividades (nome, descricao, categoria, idade_minima, idade_maxima, capacidade_maxima) VALUES 
('Futebol', 'Aulas de futebol para todas as idades', 'Esporte', 6, 60, 30),
('Natação', 'Aulas de natação e hidroginástica', 'Esporte', 4, 80, 20),
('Dança', 'Aulas de dança e expressão corporal', 'Arte', 5, 70, 25),
('Informática', 'Curso básico de informática e internet', 'Educação', 10, 80, 15),
('Artesanato', 'Oficinas de artesanato e trabalhos manuais', 'Arte', 8, 90, 20);

-- Inserir algumas turmas de exemplo
INSERT INTO turmas (nome, atividade_id, horario_inicio, horario_fim, dias_semana, capacidade_maxima, professor) VALUES 
('Futebol Infantil', 1, '14:00:00', '15:30:00', 'segunda,quarta,sexta', 25, 'João Silva'),
('Futebol Juvenil', 1, '16:00:00', '17:30:00', 'terca,quinta', 30, 'João Silva'),
('Natação Iniciante', 2, '08:00:00', '09:00:00', 'segunda,quarta,sexta', 15, 'Maria Santos'),
('Dança Infantil', 3, '15:00:00', '16:00:00', 'terca,quinta', 20, 'Ana Costa'),
('Informática Básica', 4, '19:00:00', '20:30:00', 'segunda,quarta', 12, 'Carlos Oliveira');

-- Criar índices para melhor performance
CREATE INDEX idx_alunos_nome ON alunos(nome);
CREATE INDEX idx_alunos_cpf ON alunos(cpf);
CREATE INDEX idx_matriculas_aluno ON matriculas(aluno_id);
CREATE INDEX idx_matriculas_turma ON matriculas(turma_id);
CREATE INDEX idx_presencas_data ON presencas(data_presenca);
CREATE INDEX idx_presencas_aluno_turma ON presencas(aluno_id, turma_id);

-- Views úteis
CREATE VIEW view_alunos_ativos AS
SELECT 
    a.*,
    COUNT(m.id) as total_matriculas
FROM alunos a
LEFT JOIN matriculas m ON a.id = m.aluno_id AND m.status = 'ativa'
WHERE a.ativo = TRUE
GROUP BY a.id;

CREATE VIEW view_turmas_completas AS
SELECT 
    t.*,
    at.nome as atividade_nome,
    COUNT(m.id) as alunos_matriculados,
    (t.capacidade_maxima - COUNT(m.id)) as vagas_disponiveis
FROM turmas t
INNER JOIN atividades at ON t.atividade_id = at.id
LEFT JOIN matriculas m ON t.id = m.turma_id AND m.status = 'ativa'
WHERE t.ativo = TRUE
GROUP BY t.id;