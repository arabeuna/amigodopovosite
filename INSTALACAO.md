# 📋 Guia de Instalação - Sistema Associação Amigo do Povo

## 🎯 Pré-requisitos

### Servidor Web
- **PHP 7.4+** (Recomendado: PHP 8.0+)
- **MySQL 5.7+** ou **MariaDB 10.3+**
- **Apache** ou **Nginx**
- **Extensões PHP necessárias:**
  - `pdo_mysql`
  - `mbstring`
  - `json`
  - `session`
  - `gd` (para geração de PDFs)

### Hospedagem Hostinger
- Plano Premium ou superior
- Acesso ao painel de controle
- Acesso ao phpMyAdmin
- Suporte a PHP 8.0+

---

## 🚀 Instalação Passo a Passo

### 1️⃣ **Preparação dos Arquivos**

#### No seu computador local:
```bash
# 1. Faça download de todos os arquivos do projeto
# 2. Compacte em um arquivo ZIP
# 3. Exclua a pasta tcpdf.zip se existir (muito grande)
```

#### Estrutura de arquivos essenciais:
```
associacao-php/
├── api/
├── assets/
├── auth/
├── config/
├── database/
├── includes/
├── install/
├── pages/
├── tcpdf/
├── index.php
├── .htaccess
└── README.md
```

### 2️⃣ **Upload para Hostinger**

1. **Acesse o File Manager** da Hostinger
2. **Navegue até a pasta `public_html`**
3. **Remova arquivos do WordPress** (se existirem):
   ```
   - wp-admin/
   - wp-content/
   - wp-includes/
   - wp-config.php
   - index.php (do WordPress)
   - .htaccess (do WordPress)
   ```
4. **Faça upload do arquivo ZIP** do projeto
5. **Extraia o arquivo** na pasta `public_html`
6. **Mova todos os arquivos** da pasta `associacao-php` para a raiz `public_html`

### 3️⃣ **Configuração do Banco de Dados**

#### Criar Banco de Dados:
1. **Acesse o phpMyAdmin** no painel Hostinger
2. **Clique em "Criar banco de dados"**
3. **Nome:** `u123456789_associacao` (substitua pelo seu prefixo)
4. **Codificação:** `utf8mb4_unicode_ci`
5. **Clique em "Criar"**

#### Importar Estrutura:
1. **Selecione o banco criado**
2. **Clique na aba "Importar"**
3. **Escolha o arquivo:** `database/schema.sql`
4. **Clique em "Executar"**

#### Importar Dados Iniciais:
1. **Clique novamente em "Importar"**
2. **Escolha o arquivo:** `database/auth_tables.sql`
3. **Clique em "Executar"**

### 4️⃣ **Configuração de Conexão**

#### Criar arquivo de configuração:
1. **Copie o arquivo:** `config/database.example.php`
2. **Renomeie para:** `config/database.php`
3. **Edite com os dados da Hostinger:**

```php
<?php
// Configurações do banco de dados

// Dados fornecidos pela Hostinger
define('DB_HOST', 'localhost'); // ou o host fornecido
define('DB_NAME', 'u123456789_associacao'); // seu banco
define('DB_USER', 'u123456789_user'); // seu usuário
define('DB_PASS', 'SuaSenhaSegura123!'); // sua senha
define('DB_CHARSET', 'utf8mb4');

// ... resto do código permanece igual
```

### 5️⃣ **Configuração de Segurança**

#### Arquivo .htaccess:
```apache
# Segurança básica
RewriteEngine On

# Bloquear acesso a arquivos sensíveis
<Files ~ "\.(sql|md|json|lock)$">
    Order allow,deny
    Deny from all
</Files>

# Bloquear acesso a pastas de configuração
RewriteRule ^(config|database|install)/ - [F,L]

# Redirecionamento para HTTPS (se disponível)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Página inicial
DirectoryIndex index.php
```

#### Configurar permissões:
```bash
# Pastas: 755
# Arquivos: 644
# Arquivo config/database.php: 600 (mais restritivo)
```

### 6️⃣ **Primeiro Acesso**

#### Credenciais padrão:
- **Master Admin:**
  - Email: `master@associacao.com`
  - Senha: `admin123`

- **Admin Geral:**
  - Email: `admin@associacao.com`
  - Senha: `admin123`

#### ⚠️ **IMPORTANTE - Altere as senhas imediatamente após o primeiro login!**

### 7️⃣ **Configurações Pós-Instalação**

#### Alterar senhas padrão:
1. **Faça login** com as credenciais padrão
2. **Vá em:** Configurações → Gerenciar Usuários
3. **Altere as senhas** de todos os usuários
4. **Crie novos usuários** conforme necessário

#### Configurar dados da associação:
1. **Edite o arquivo:** `config/config.php`
2. **Altere:**
   ```php
   define('SITE_NAME', 'Nome da Sua Associação');
   define('SITE_URL', 'https://seudominio.com');
   ```

#### Configurar email (opcional):
```php
// No config/config.php
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_USER', 'noreply@seudominio.com');
define('SMTP_PASS', 'suasenha');
define('SMTP_PORT', 587);
```

---

## 🔧 Solução de Problemas

### ❌ Erro "Conexão com banco de dados falhou"
**Solução:**
1. Verifique os dados em `config/database.php`
2. Confirme se o banco foi criado
3. Teste a conexão no phpMyAdmin

### ❌ Erro "Página não encontrada"
**Solução:**
1. Verifique se o arquivo `.htaccess` existe
2. Confirme se o mod_rewrite está ativo
3. Verifique as permissões dos arquivos

### ❌ Erro "Permissão negada"
**Solução:**
1. Ajuste as permissões:
   - Pastas: `755`
   - Arquivos: `644`
2. Verifique o proprietário dos arquivos

### ❌ Erro "Função não encontrada"
**Solução:**
1. Verifique se todas as extensões PHP estão instaladas
2. Confirme a versão do PHP (mínimo 7.4)

---

## 📊 Verificação da Instalação

### ✅ Checklist Final:
- [ ] Site carrega sem erros
- [ ] Login funciona corretamente
- [ ] Dashboard é exibido
- [ ] Cadastro de alunos funciona
- [ ] Relatórios são gerados
- [ ] Senhas padrão foram alteradas
- [ ] Backup do banco foi criado

### 🔍 URLs para testar:
- `https://seudominio.com/` - Página inicial
- `https://seudominio.com/auth/login.php` - Login
- `https://seudominio.com/pages/dashboard.php` - Dashboard
- `https://seudominio.com/pages/alunos.php` - Gestão de alunos

---

## 🛡️ Segurança em Produção

### Configurações obrigatórias:
1. **SSL/HTTPS ativo**
2. **Senhas fortes** para todos os usuários
3. **Backup automático** configurado
4. **Atualizações regulares** do sistema
5. **Monitoramento** de logs de acesso

### Arquivos a proteger:
- `config/database.php` - Credenciais do banco
- `database/` - Scripts SQL
- `install/` - Arquivos de instalação

---

## 📞 Suporte

Em caso de dúvidas:
1. **Verifique os logs** de erro do servidor
2. **Consulte a documentação** da Hostinger
3. **Teste em ambiente local** primeiro

---

## 📝 Notas Importantes

- **Sempre faça backup** antes de atualizações
- **Teste em ambiente de desenvolvimento** primeiro
- **Mantenha as credenciais seguras**
- **Monitore os logs** regularmente
- **Atualize o sistema** periodicamente

---

**✨ Instalação concluída com sucesso!**

Seu sistema está pronto para uso em produção. Lembre-se de alterar as senhas padrão e configurar backups regulares.