-- Script para adicionar tabelas de autenticação e auditoria
-- Execute este script após o schema.sql principal

USE associacao_amigo_povo;

-- Modificar tabela usuarios para incluir tipo 'master'
ALTER TABLE usuarios MODIFY COLUMN tipo ENUM('master', 'admin', 'funcionario') DEFAULT 'funcionario';

-- Tabela de permissões do sistema
CREATE TABLE IF NOT EXISTS permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) UNIQUE NOT NULL,
    descricao TEXT,
    modulo VARCHAR(50),
    ativa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de relacionamento usuário-permissões
CREATE TABLE IF NOT EXISTS usuario_permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    permissao_id INT NOT NULL,
    ativa BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (usuario_id, permissao_id)
);

-- Tabela de sessões de usuário
CREATE TABLE IF NOT EXISTS sessoes_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_logout TIMESTAMP NULL,
    ativa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_session_id (session_id),
    INDEX idx_usuario_ativa (usuario_id, ativa)
);

-- Tabela de logs de auditoria
CREATE TABLE IF NOT EXISTS logs_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    acao VARCHAR(50) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    tabela_afetada VARCHAR(50) NULL,
    registro_id INT NULL,
    dados_anteriores JSON NULL,
    dados_novos JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    detalhes JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario_acao (usuario_id, acao),
    INDEX idx_modulo_data (modulo, created_at),
    INDEX idx_tabela_registro (tabela_afetada, registro_id)
);

-- Inserir permissões básicas do sistema
INSERT INTO permissoes (nome, descricao, modulo) VALUES 
('gerenciar_usuarios', 'Criar, editar e excluir usuários', 'usuarios'),
('visualizar_usuarios', 'Visualizar lista de usuários', 'usuarios'),
('gerenciar_alunos', 'Criar, editar e excluir alunos', 'alunos'),
('visualizar_alunos', 'Visualizar lista de alunos', 'alunos'),
('gerenciar_turmas', 'Criar, editar e excluir turmas', 'turmas'),
('visualizar_turmas', 'Visualizar lista de turmas', 'turmas'),
('gerenciar_atividades', 'Criar, editar e excluir atividades', 'atividades'),
('visualizar_atividades', 'Visualizar lista de atividades', 'atividades'),
('gerenciar_matriculas', 'Criar, editar e excluir matrículas', 'matriculas'),
('visualizar_matriculas', 'Visualizar lista de matrículas', 'matriculas'),
('gerenciar_presencas', 'Registrar e editar presenças', 'presencas'),
('visualizar_presencas', 'Visualizar relatórios de presença', 'presencas'),
('visualizar_relatorios', 'Acessar relatórios do sistema', 'relatorios'),
('visualizar_logs', 'Visualizar logs de auditoria', 'auditoria'),
('dashboard_master', 'Acessar dashboard master', 'dashboard');

-- Criar usuário master padrão
INSERT INTO usuarios (nome, email, senha, tipo) VALUES 
('Master Admin', 'master@associacao.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'master')
ON DUPLICATE KEY UPDATE tipo = 'master';
-- Senha: admin123

-- Atualizar usuário admin existente se necessário
UPDATE usuarios SET 
    nome = 'Administrador Geral',
    tipo = 'admin'
WHERE email = 'admin@associacao.com';

COMMIT;