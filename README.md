# Sistema de Gestão - Associação Amigo do Povo

Sistema web desenvolvido em PHP para gestão de atividades, alunos, turmas e matrículas da Associação Amigo do Povo.

## 🚀 Funcionalidades

### Sistema de Autenticação
- Sistema de login seguro com níveis de permissão
- Controle de sessão avançado
- Logout automático
- **Níveis de acesso**: Master, Admin, User
- **Dashboard Master**: Interface completa de administração
- **Logs de Auditoria**: Rastreamento de todas as ações
- **Gerenciamento de Usuários**: Criação e edição de contas

### Gestão de Alunos
- Cadastro completo de alunos
- Edição de informações
- Busca e filtros
- Controle de status (ativo/inativo)
- Paginação de resultados

### Gestão de Atividades
- Cadastro de atividades esportivas
- Controle de capacidade e horários
- Edição e desativação
- Estatísticas por atividade

### Gestão de Turmas
- Criação de turmas por atividade
- Controle de capacidade máxima
- Horários e dias da semana
- Filtros por atividade

### Sistema de Matrículas
- Matrícula de alunos em turmas
- Controle de capacidade
- Status: ativa, suspensa, cancelada
- Verificação de duplicatas
- Histórico de matrículas

### Relatórios
- Relatório de matrículas
- Relatório por atividade
- Relatório por faixa etária
- Gráficos interativos
- Exportação para CSV
- Função de impressão

### Dashboard
- Visão geral do sistema
- Estatísticas principais
- Ações rápidas
- Navegação intuitiva

## 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 7.4+
- **Banco de Dados**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework CSS**: Tailwind CSS (local)
- **Ícones**: Font Awesome
- **Gráficos**: Chart.js

## 📋 Pré-requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor web (Apache/Nginx)
- Extensões PHP: PDO, PDO_MySQL

## 🔧 Instalação

1. **Clone ou baixe o projeto**
   ```bash
   git clone [url-do-repositorio]
   cd associacao-php
   ```

2. **Configure o banco de dados**
   - Crie um banco de dados MySQL
   - Execute o script `database/schema.sql`
   - Atualize as configurações em `config/database.php`

3. **Configure o sistema**
   - Edite o arquivo `config/config.php`
   - Ajuste as constantes conforme necessário

4. **Configure o servidor web**
   - Aponte o DocumentRoot para a pasta do projeto
   - Certifique-se que o PHP está funcionando

## ⚙️ Configuração

### Banco de Dados
Edite o arquivo `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'associacao_db');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

### Configurações Gerais
Edite o arquivo `config/config.php` para ajustar:
- Nome do site
- Timezone
- Configurações de upload
- Paginação

## 👤 Usuário Padrão

O sistema vem com um usuário administrador pré-configurado:
- **Usuário**: admin
- **Senha**: admin123

**⚠️ IMPORTANTE**: Altere a senha padrão após o primeiro acesso!

## 📁 Estrutura do Projeto

```
associacao-php/
├── auth/
│   ├── login.php
│   └── logout.php
├── config/
│   ├── config.php
│   └── database.php
├── database/
│   └── schema.sql
├── pages/
│   ├── dashboard.php
│   ├── alunos.php
│   ├── atividades.php
│   ├── turmas.php
│   ├── matriculas.php
│   └── relatorios.php
├── index.php
└── README.md
```

## 🔐 Segurança

- Sanitização de entradas
- Prepared statements (PDO)
- Controle de sessão
- Validação de dados
- Proteção contra SQL Injection
- Controle de acesso por sessão

## 📊 Banco de Dados

### Tabelas Principais
- `usuarios`: Usuários do sistema
- `alunos`: Cadastro de alunos
- `atividades`: Atividades oferecidas
- `turmas`: Turmas por atividade
- `matriculas`: Matrículas dos alunos
- `presencas`: Controle de presença (estrutura preparada)
- `eventos`: Eventos da associação (estrutura preparada)

### Views
- `view_matriculas_ativas`: Matrículas ativas com dados completos
- `view_estatisticas_turmas`: Estatísticas por turma

## 🎨 Interface

- Design responsivo
- Interface moderna com Tailwind CSS
- Navegação intuitiva
- Feedback visual para ações
- Confirmações para ações críticas

## 📈 Relatórios Disponíveis

1. **Relatório de Matrículas**
   - Lista completa de matrículas
   - Filtros por período, atividade e turma
   - Status das matrículas

2. **Relatório por Atividade**
   - Estatísticas por atividade
   - Gráfico de barras
   - Comparativo de matrículas

3. **Relatório por Faixa Etária**
   - Distribuição etária dos alunos
   - Gráfico de pizza
   - Percentuais por faixa

## 🔄 Funcionalidades Futuras

- Controle de presença
- Gestão de eventos
- Notificações por email
- API REST
- App mobile
- Relatórios avançados
- Sistema de mensagens

## 🔧 Instalação e Configuração

### Pré-requisitos
- XAMPP (Apache + MySQL + PHP 7.4+)
- Navegador web moderno
- Git (para clonagem do repositório)

### 1. Clone o repositório
```bash
git clone [URL_DO_REPOSITORIO]
cd associacao-php
```

### 2. Configure o ambiente
1. Inicie o XAMPP (Apache e MySQL)
2. Copie o projeto para `C:\xampp\htdocs\associacao-php`

### 3. Configure o banco de dados

1. **Copie o arquivo de configuração:**
```bash
cp config/database.example.php config/database.php
```

2. **Edite `config/database.php` com suas configurações:**
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'associacao_amigo_povo');
define('DB_USER', 'root');
define('DB_PASS', '');
```

3. **Crie o banco de dados:**
```sql
CREATE DATABASE associacao_amigo_povo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

4. **Execute os scripts SQL:**
```bash
# Estrutura principal
mysql -u root -p associacao_amigo_povo < database/schema.sql

# Tabelas de autenticação
mysql -u root -p associacao_amigo_povo < database/auth_tables.sql
```

### 4. Acesse o sistema
Abra o navegador e acesse: `http://localhost/associacao-php`

### 👤 Usuário Padrão
Após a instalação, use as credenciais padrão:
- **Email**: master@associacao.com
- **Senha**: master123
- **Tipo**: Master (acesso total)

⚠️ **Importante**: Altere a senha padrão após o primeiro acesso!

### 📁 Estrutura do Projeto
```
associacao-php/
├── api/                    # APIs REST
├── assets/                 # Recursos estáticos
│   ├── css/               # Estilos CSS
│   ├── js/                # Scripts JavaScript
│   └── images/            # Imagens
├── auth/                   # Sistema de autenticação
├── config/                 # Configurações
├── database/               # Scripts SQL
├── includes/               # Arquivos de inclusão
├── pages/                  # Páginas do sistema
├── tcpdf/                  # Biblioteca PDF
└── index.php              # Página inicial
```

## 🐛 Solução de Problemas

### Erro de Conexão com Banco
- Verifique as credenciais em `config/database.php`
- Certifique-se que o MySQL está rodando
- Verifique se o banco de dados existe

### Erro de Sessão
- Verifique se as sessões PHP estão habilitadas
- Limpe o cache do navegador
- Verifique permissões da pasta de sessões

### Problemas de Layout
- Verifique se o arquivo `assets/css/tailwind.min.css` existe
- Limpe o cache do navegador
- Verifique se o caminho para o CSS está correto nos arquivos PHP

## 🔄 Migração do Tailwind CSS

O sistema foi migrado do CDN do Tailwind CSS para uma versão local para melhor performance e adequação à produção:

### ✅ Alterações Realizadas
- Removido: `<script src="https://cdn.tailwindcss.com"></script>`
- Adicionado: `<link href="../assets/css/tailwind.min.css" rel="stylesheet">`
- Arquivo CSS local: `assets/css/tailwind.min.css` (407KB)

### 🚀 Benefícios
- ✅ Adequado para produção
- ✅ Melhor performance (sem dependência de CDN)
- ✅ Funciona offline
- ✅ Carregamento mais rápido

## 📝 Licença

Este projeto foi desenvolvido para a Associação Amigo do Povo.

## 👥 Suporte

Para suporte técnico ou dúvidas sobre o sistema, entre em contato com a equipe de desenvolvimento.

---

**Desenvolvido com ❤️ para a Associação Amigo do Povo**