-- Extensão do schema para sistema de usuários master e auditoria
-- Associação Amigo do Povo - Sistema de Gerenciamento de Usuários

USE associacao_amigo_povo;

-- Atualizar tabela de usuários existente para incluir novos tipos
ALTER TABLE usuarios 
MODIFY COLUMN tipo ENUM('master', 'admin', 'funcionario') DEFAULT 'funcionario';

-- Tabela de permissões do sistema
CREATE TABLE permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE,
    descricao VARCHAR(200),
    modulo VARCHAR(50) NOT NULL, -- alunos, turmas, matriculas, presencas, relatorios, usuarios
    acao VARCHAR(50) NOT NULL,   -- create, read, update, delete, export
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de relacionamento usuário-permissões
CREATE TABLE usuario_permissoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    permissao_id INT NOT NULL,
    concedida_por INT NOT NULL, -- ID do usuário que concedeu a permissão
    data_concessao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ativa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (permissao_id) REFERENCES permissoes(id) ON DELETE CASCADE,
    FOREIGN KEY (concedida_por) REFERENCES usuarios(id),
    UNIQUE KEY unique_user_permission (usuario_id, permissao_id)
);

-- Tabela de logs de auditoria
CREATE TABLE logs_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL,        -- login, logout, create, update, delete, export, view
    modulo VARCHAR(50) NOT NULL,      -- alunos, turmas, matriculas, presencas, usuarios, sistema
    tabela_afetada VARCHAR(50),       -- nome da tabela afetada
    registro_id INT,                  -- ID do registro afetado
    dados_anteriores JSON,            -- dados antes da alteração
    dados_novos JSON,                 -- dados após a alteração
    ip_address VARCHAR(45),           -- IP do usuário
    user_agent TEXT,                  -- navegador/dispositivo
    detalhes TEXT,                    -- detalhes adicionais da ação
    data_acao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de sessões de usuário
CREATE TABLE sessoes_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    data_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_logout TIMESTAMP NULL,
    ativa BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Inserir permissões básicas do sistema
INSERT INTO permissoes (nome, descricao, modulo, acao) VALUES
-- Permissões de Alunos
('alunos_create', 'Criar novos alunos', 'alunos', 'create'),
('alunos_read', 'Visualizar alunos', 'alunos', 'read'),
('alunos_update', 'Editar dados de alunos', 'alunos', 'update'),
('alunos_delete', 'Excluir alunos', 'alunos', 'delete'),
('alunos_export', 'Exportar dados de alunos', 'alunos', 'export'),

-- Permissões de Turmas
('turmas_create', 'Criar novas turmas', 'turmas', 'create'),
('turmas_read', 'Visualizar turmas', 'turmas', 'read'),
('turmas_update', 'Editar turmas', 'turmas', 'update'),
('turmas_delete', 'Excluir turmas', 'turmas', 'delete'),

-- Permissões de Matrículas
('matriculas_create', 'Criar matrículas', 'matriculas', 'create'),
('matriculas_read', 'Visualizar matrículas', 'matriculas', 'read'),
('matriculas_update', 'Editar matrículas', 'matriculas', 'update'),
('matriculas_delete', 'Cancelar matrículas', 'matriculas', 'delete'),
('matriculas_export', 'Exportar dados de matrículas', 'matriculas', 'export'),

-- Permissões de Presenças
('presencas_create', 'Registrar presenças', 'presencas', 'create'),
('presencas_read', 'Visualizar presenças', 'presencas', 'read'),
('presencas_update', 'Editar presenças', 'presencas', 'update'),
('presencas_delete', 'Excluir registros de presença', 'presencas', 'delete'),
('presencas_export', 'Exportar dados de presença', 'presencas', 'export'),

-- Permissões de Relatórios
('relatorios_read', 'Visualizar relatórios', 'relatorios', 'read'),
('relatorios_export', 'Exportar relatórios', 'relatorios', 'export'),
('aniversariantes_read', 'Visualizar aniversariantes', 'aniversariantes', 'read'),
('aniversariantes_export', 'Exportar aniversariantes', 'aniversariantes', 'export'),

-- Permissões de Usuários (apenas para master e admin)
('usuarios_create', 'Criar novos usuários', 'usuarios', 'create'),
('usuarios_read', 'Visualizar usuários', 'usuarios', 'read'),
('usuarios_update', 'Editar usuários', 'usuarios', 'update'),
('usuarios_delete', 'Excluir usuários', 'usuarios', 'delete'),
('usuarios_permissions', 'Gerenciar permissões de usuários', 'usuarios', 'permissions'),

-- Permissões de Auditoria (apenas para master)
('auditoria_read', 'Visualizar logs de auditoria', 'auditoria', 'read'),
('auditoria_export', 'Exportar logs de auditoria', 'auditoria', 'export'),
('sistema_monitor', 'Monitorar atividades do sistema', 'sistema', 'monitor');

-- Criar usuário master padrão
INSERT INTO usuarios (nome, email, senha, tipo) VALUES 
('Master Admin', 'master@associacao.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'master');
-- Senha: admin123

-- Conceder todas as permissões ao usuário master
SET @master_id = LAST_INSERT_ID();
INSERT INTO usuario_permissoes (usuario_id, permissao_id, concedida_por)
SELECT @master_id, id, @master_id FROM permissoes;

-- Conceder permissões básicas ao admin existente
SET @admin_id = (SELECT id FROM usuarios WHERE email = 'admin@associacao.com' LIMIT 1);
INSERT INTO usuario_permissoes (usuario_id, permissao_id, concedida_por)
SELECT @admin_id, id, @master_id FROM permissoes 
WHERE nome NOT IN ('usuarios_create', 'usuarios_delete', 'usuarios_permissions', 'auditoria_read', 'auditoria_export', 'sistema_monitor');

-- Criar índices para melhor performance
CREATE INDEX idx_logs_usuario ON logs_auditoria(usuario_id);
CREATE INDEX idx_logs_data ON logs_auditoria(data_acao);
CREATE INDEX idx_logs_modulo ON logs_auditoria(modulo);
CREATE INDEX idx_logs_acao ON logs_auditoria(acao);
CREATE INDEX idx_sessoes_usuario ON sessoes_usuario(usuario_id);
CREATE INDEX idx_sessoes_ativa ON sessoes_usuario(ativa);

-- View para relatório de atividades dos usuários
CREATE VIEW view_atividades_usuarios AS
SELECT 
    u.id,
    u.nome,
    u.email,
    u.tipo,
    COUNT(CASE WHEN l.acao = 'login' THEN 1 END) as total_logins,
    COUNT(CASE WHEN l.acao = 'create' AND l.modulo = 'alunos' THEN 1 END) as alunos_cadastrados,
    COUNT(CASE WHEN l.acao = 'update' AND l.modulo = 'alunos' THEN 1 END) as alunos_atualizados,
    COUNT(CASE WHEN l.acao = 'delete' AND l.modulo = 'alunos' THEN 1 END) as alunos_excluidos,
    COUNT(CASE WHEN l.acao = 'create' AND l.modulo = 'presencas' THEN 1 END) as presencas_registradas,
    MAX(l.data_acao) as ultima_atividade,
    u.ultimo_login
FROM usuarios u
LEFT JOIN logs_auditoria l ON u.id = l.usuario_id
WHERE u.ativo = TRUE
GROUP BY u.id, u.nome, u.email, u.tipo, u.ultimo_login;

-- View para sessões ativas
CREATE VIEW view_sessoes_ativas AS
SELECT 
    s.id,
    u.nome,
    u.email,
    s.ip_address,
    s.data_login,
    TIMESTAMPDIFF(MINUTE, s.data_login, NOW()) as minutos_online
FROM sessoes_usuario s
INNER JOIN usuarios u ON s.usuario_id = u.id
WHERE s.ativa = TRUE
ORDER BY s.data_login DESC;