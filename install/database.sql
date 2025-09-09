-- Banco de dados para Associação Amigo do Povo
-- Versão com melhorias de segurança

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Tabela de usuários com campos de segurança
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','operador','visualizador') NOT NULL DEFAULT 'visualizador',
  `status` enum('ativo','inativo','bloqueado') NOT NULL DEFAULT 'ativo',
  `ultimo_login` datetime DEFAULT NULL,
  `tentativas_login` int(11) DEFAULT 0,
  `bloqueado_ate` datetime DEFAULT NULL,
  `token_reset` varchar(255) DEFAULT NULL,
  `token_reset_expira` datetime DEFAULT NULL,
  `data_cadastro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email_status` (`email`, `status`),
  KEY `idx_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de alunos
CREATE TABLE IF NOT EXISTS `alunos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `data_nascimento` date NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `rg` varchar(20) DEFAULT NULL,
  `endereco` text,
  `telefone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `nome_responsavel` varchar(100) DEFAULT NULL,
  `telefone_responsavel` varchar(20) DEFAULT NULL,
  `observacoes` text,
  `status` enum('ativo','inativo','transferido','desistente') NOT NULL DEFAULT 'ativo',
  `data_cadastro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `usuario_cadastro` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nome` (`nome`),
  KEY `idx_status` (`status`),
  KEY `idx_data_nascimento` (`data_nascimento`),
  KEY `fk_aluno_usuario` (`usuario_cadastro`),
  CONSTRAINT `fk_aluno_usuario` FOREIGN KEY (`usuario_cadastro`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de turmas
CREATE TABLE IF NOT EXISTS `turmas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `descricao` text,
  `professor` varchar(100) DEFAULT NULL,
  `horario` varchar(100) DEFAULT NULL,
  `dias_semana` varchar(50) DEFAULT NULL,
  `vagas_total` int(11) DEFAULT NULL,
  `vagas_ocupadas` int(11) DEFAULT 0,
  `status` enum('ativa','inativa','suspensa') NOT NULL DEFAULT 'ativa',
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `data_cadastro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `usuario_cadastro` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_nome` (`nome`),
  KEY `idx_status` (`status`),
  KEY `fk_turma_usuario` (`usuario_cadastro`),
  CONSTRAINT `fk_turma_usuario` FOREIGN KEY (`usuario_cadastro`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de matrículas
CREATE TABLE IF NOT EXISTS `matriculas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `data_matricula` date NOT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `status` enum('ativa','inativa','transferida','cancelada') NOT NULL DEFAULT 'ativa',
  `observacoes` text,
  `data_cadastro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `usuario_cadastro` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_aluno_turma_ativa` (`aluno_id`, `turma_id`, `status`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_status` (`status`),
  KEY `fk_matricula_usuario` (`usuario_cadastro`),
  CONSTRAINT `fk_matricula_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matricula_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_matricula_usuario` FOREIGN KEY (`usuario_cadastro`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de atividades
CREATE TABLE IF NOT EXISTS `atividades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turma_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text,
  `data_atividade` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fim` time DEFAULT NULL,
  `local` varchar(100) DEFAULT NULL,
  `tipo` enum('aula','evento','avaliacao','reuniao','outros') NOT NULL DEFAULT 'aula',
  `status` enum('agendada','realizada','cancelada','adiada') NOT NULL DEFAULT 'agendada',
  `observacoes` text,
  `data_cadastro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `usuario_cadastro` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_turma` (`turma_id`),
  KEY `idx_data` (`data_atividade`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_status` (`status`),
  KEY `fk_atividade_usuario` (`usuario_cadastro`),
  CONSTRAINT `fk_atividade_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_atividade_usuario` FOREIGN KEY (`usuario_cadastro`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de presenças
CREATE TABLE IF NOT EXISTS `presencas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `atividade_id` int(11) NOT NULL,
  `aluno_id` int(11) NOT NULL,
  `presente` tinyint(1) NOT NULL DEFAULT 0,
  `justificativa` text,
  `data_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_registro` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_atividade_aluno` (`atividade_id`, `aluno_id`),
  KEY `idx_atividade` (`atividade_id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_presente` (`presente`),
  KEY `fk_presenca_usuario` (`usuario_registro`),
  CONSTRAINT `fk_presenca_atividade` FOREIGN KEY (`atividade_id`) REFERENCES `atividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presenca_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presenca_usuario` FOREIGN KEY (`usuario_registro`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de logs de auditoria
CREATE TABLE IF NOT EXISTS `logs_auditoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `dados_anteriores` json DEFAULT NULL,
  `dados_novos` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `data_acao` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_acao` (`acao`),
  KEY `idx_tabela` (`tabela`),
  KEY `idx_data` (`data_acao`),
  KEY `idx_ip` (`ip_address`),
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text,
  `descricao` text,
  `tipo` enum('string','int','bool','json') NOT NULL DEFAULT 'string',
  `data_atualizacao` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `usuario_atualizacao` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`),
  KEY `fk_config_usuario` (`usuario_atualizacao`),
  CONSTRAINT `fk_config_usuario` FOREIGN KEY (`usuario_atualizacao`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir configurações padrão
INSERT INTO `configuracoes` (`chave`, `valor`, `descricao`, `tipo`) VALUES
('site_name', 'Associação Amigo do Povo', 'Nome do site', 'string'),
('max_login_attempts', '5', 'Máximo de tentativas de login', 'int'),
('session_timeout', '3600', 'Timeout da sessão em segundos', 'int'),
('backup_enabled', '1', 'Backup automático habilitado', 'bool'),
('maintenance_mode', '0', 'Modo de manutenção', 'bool');

-- Triggers para auditoria
DELIMITER //

CREATE TRIGGER `audit_usuarios_insert` AFTER INSERT ON `usuarios`
FOR EACH ROW BEGIN
    INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, dados_novos, ip_address, user_agent)
    VALUES (NEW.id, 'INSERT', 'usuarios', NEW.id, JSON_OBJECT('nome', NEW.nome, 'email', NEW.email, 'tipo', NEW.tipo), 
            COALESCE(@user_ip, ''), COALESCE(@user_agent, ''));
END//

CREATE TRIGGER `audit_usuarios_update` AFTER UPDATE ON `usuarios`
FOR EACH ROW BEGIN
    INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, dados_anteriores, dados_novos, ip_address, user_agent)
    VALUES (NEW.id, 'UPDATE', 'usuarios', NEW.id, 
            JSON_OBJECT('nome', OLD.nome, 'email', OLD.email, 'tipo', OLD.tipo, 'status', OLD.status),
            JSON_OBJECT('nome', NEW.nome, 'email', NEW.email, 'tipo', NEW.tipo, 'status', NEW.status),
            COALESCE(@user_ip, ''), COALESCE(@user_agent, ''));
END//

CREATE TRIGGER `audit_alunos_insert` AFTER INSERT ON `alunos`
FOR EACH ROW BEGIN
    INSERT INTO logs_auditoria (usuario_id, acao, tabela, registro_id, dados_novos, ip_address, user_agent)
    VALUES (NEW.usuario_cadastro, 'INSERT', 'alunos', NEW.id, JSON_OBJECT('nome', NEW.nome, 'cpf', NEW.cpf), 
            COALESCE(@user_ip, ''), COALESCE(@user_agent, ''));
END//

DELIMITER ;

COMMIT;