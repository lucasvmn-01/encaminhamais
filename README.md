# Sistema Encaminha Mais+

Sistema completo de gestão de encaminhamentos clínicos desenvolvido em PHP com MySQL.

## 📋 Funcionalidades

### 🔐 Sistema de Autenticação
- Login seguro com validação
- Recuperação de senha
- Controle de sessões
- Diferentes níveis de acesso

### 👥 Gestão de Clientes
- Cadastro completo de clientes
- Upload de fotos e documentos
- Controle de aniversariantes
- Histórico de guias
- Validação de CPF automática
- Busca de CEP integrada

### 🏥 Gestão de Clínicas
- Cadastro de clínicas parceiras
- Upload de logos e documentos
- Controle de repasses
- Informações de contato
- Validação de CNPJ automática

### 🔬 Catálogo de Exames
- Cadastro de exames e consultas
- Categorização automática
- Controle de valores
- Instruções de preparação
- Tempo estimado de execução

### 📋 Sistema de Guias
- Criação de guias de encaminhamento
- Agendamento de consultas/exames
- Controle de status
- Impressão de guias
- Histórico completo

### 💰 Módulo Financeiro
- Controle de pagamentos
- Gestão de repasses
- Recibos automáticos
- Relatórios financeiros
- Dashboard com gráficos

### ⚙️ Configurações
- Configurações da empresa
- Parâmetros do sistema
- Gestão de usuários
- Backup automático

### 📊 Dashboard Inteligente
- Estatísticas em tempo real
- Gráficos interativos
- Alertas e notificações
- Ações rápidas
- Aniversariantes do dia

## 🚀 Instalação

### Pré-requisitos
- XAMPP (Apache + MySQL + PHP 7.4+)
- Navegador web moderno
- 50MB de espaço em disco

### Passo a Passo

1. **Baixe e instale o XAMPP**
   - Acesse: https://www.apachefriends.org/
   - Baixe a versão para seu sistema operacional
   - Instale seguindo as instruções

2. **Prepare o ambiente**
   - Abra o XAMPP Control Panel
   - Inicie os serviços Apache e MySQL
   - Clique em "Admin" na linha do MySQL para abrir o phpMyAdmin

3. **Crie o banco de dados**
   - No phpMyAdmin, clique em "Bancos de dados"
   - Digite o nome: `encaminhamais_db`
   - Clique em "Criar"

4. **Importe a estrutura do banco**
   - Selecione o banco `encaminhamais_db` criado
   - Clique na aba "Importar"
   - Clique em "Escolher arquivo" e selecione o arquivo `database.sql`
   - Clique em "Executar"

5. **Configure o sistema**
   - Copie todos os arquivos do sistema para a pasta `htdocs/encaminhamais/` do XAMPP
   - Edite o arquivo `config/database.php` se necessário (as configurações padrão já funcionam)

6. **Acesse o sistema**
   - Abra seu navegador
   - Digite: `http://localhost/encaminhamais/`
   - Faça login com as credenciais padrão:
     - **Usuário:** admin@encaminhamais.com
     - **Senha:** admin123

## 🔧 Configuração

### Configurações do Banco de Dados
Edite o arquivo `config/database.php` se necessário:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'encaminhamais_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### Configurações do Sistema
Acesse **Configurações > Sistema** no painel administrativo para:
- Configurar informações da empresa
- Definir parâmetros financeiros
- Gerenciar usuários
- Configurar notificações

## 📁 Estrutura de Arquivos

```
encaminhamais/
├── config/
│   ├── database.php          # Configurações do banco
│   └── functions.php         # Funções auxiliares
├── templates/
│   ├── header.php           # Cabeçalho das páginas
│   ├── sidebar.php          # Menu lateral
│   ├── topbar.php           # Barra superior
│   └── footer.php           # Rodapé das páginas
├── assets/
│   └── css/
│       └── style.css        # Estilos customizados
├── uploads/                 # Arquivos enviados
├── *.php                   # Páginas do sistema
├── database.sql            # Estrutura do banco
├── .htaccess              # Configurações Apache
└── README.md              # Este arquivo
```

## 🎯 Uso do Sistema

### Primeiro Acesso
1. Faça login com as credenciais padrão
2. Vá em **Configurações** e atualize os dados da sua empresa
3. Crie novos usuários se necessário
4. Cadastre suas clínicas parceiras
5. Cadastre os exames/consultas disponíveis
6. Comece a cadastrar clientes e gerar guias

### Fluxo de Trabalho
1. **Cliente liga** → Cadastre o cliente no sistema
2. **Agende o exame** → Crie uma nova guia de encaminhamento
3. **Cliente realiza** → Atualize o status da guia
4. **Receba o pagamento** → Marque como pago no sistema
5. **Faça o repasse** → Confirme o repasse para a clínica

## 🛡️ Segurança

- Senhas criptografadas com hash seguro
- Validação de dados em todas as entradas
- Proteção contra SQL Injection
- Controle de sessões
- Backup automático (configurável)

## 🎨 Interface

- Design moderno e responsivo
- Tema claro/escuro alternável
- Compatível com dispositivos móveis
- Ícones intuitivos (Lucide Icons)
- Framework Tailwind CSS

## 📱 Recursos Mobile

- Interface totalmente responsiva
- Menu lateral colapsável
- Formulários otimizados para touch
- Visualização adaptada para tablets

## 🔄 Backup e Manutenção

### Backup Manual
1. Acesse **Configurações**
2. Clique em "Backup"
3. Salve o arquivo gerado

### Backup Automático
- Configure em **Configurações > Sistema**
- Ative "Backup Automático"
- Backups serão gerados diariamente

## 🆘 Suporte

### Problemas Comuns

**Erro de conexão com banco:**
- Verifique se o MySQL está rodando no XAMPP
- Confirme as credenciais em `config/database.php`

**Página em branco:**
- Ative a exibição de erros PHP
- Verifique os logs do Apache

**Upload não funciona:**
- Verifique permissões da pasta `uploads/`
- Confirme configurações de upload no PHP

### Logs do Sistema
- Logs de erro: `logs/error.log`
- Logs de acesso: `logs/access.log`
- Logs de backup: `logs/backup.log`

## 📈 Atualizações

Para atualizar o sistema:
1. Faça backup completo
2. Substitua os arquivos (exceto `config/`)
3. Execute scripts de atualização se houver
4. Teste todas as funcionalidades

## 🤝 Contribuição

Este sistema foi desenvolvido especificamente para gestão de encaminhamentos clínicos. 

Para sugestões ou melhorias:
- Documente o problema/sugestão
- Inclua capturas de tela se relevante
- Descreva o comportamento esperado

## 📄 Licença

Sistema proprietário desenvolvido para uso interno.

## 🏆 Créditos

- **Framework CSS:** Tailwind CSS
- **Ícones:** Lucide Icons  
- **Fontes:** Inter (Google Fonts)
- **Desenvolvimento:** Sistema Encaminha Mais+

---

**Versão:** 1.0.0  
**Data:** <?php echo date('d/m/Y'); ?>  
**Desenvolvido com ❤️ para otimizar sua gestão de encaminhamentos clínicos**
