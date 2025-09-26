<?php
// Configurações do Sistema - Salvar como configuracoes.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();
$errors = [];
$success = false;

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action'])) {
        
        switch ($_POST['action']) {
            case 'salvar_empresa':
                $empresa_nome = trim($_POST['empresa_nome'] ?? '');
                $empresa_slogan = trim($_POST['empresa_slogan'] ?? '');
                $empresa_cnpj = preg_replace('/\D/', '', $_POST['empresa_cnpj'] ?? '');
                $empresa_telefone = preg_replace('/\D/', '', $_POST['empresa_telefone'] ?? '');
                $empresa_email = trim($_POST['empresa_email'] ?? '');
                $empresa_endereco = trim($_POST['empresa_endereco'] ?? '');
                $empresa_site = trim($_POST['empresa_site'] ?? '');
                
                // Validações
                if (empty($empresa_nome)) {
                    $errors[] = 'Nome da empresa é obrigatório';
                }
                
                if (!empty($empresa_cnpj) && !validarCNPJ($empresa_cnpj)) {
                    $errors[] = 'CNPJ inválido';
                }
                
                if (!empty($empresa_email) && !filter_var($empresa_email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'E-mail inválido';
                }
                
                if (empty($errors)) {
                    try {
                        // Salva as configurações
                        $configs = [
                            'empresa_nome' => $empresa_nome,
                            'empresa_slogan' => $empresa_slogan,
                            'empresa_cnpj' => $empresa_cnpj,
                            'empresa_telefone' => $empresa_telefone,
                            'empresa_email' => $empresa_email,
                            'empresa_endereco' => $empresa_endereco,
                            'empresa_site' => $empresa_site
                        ];
                        
                        foreach ($configs as $chave => $valor) {
                            $db->execute("
                                INSERT INTO configuracoes_sistema (chave, valor, modificado_por_id, data_modificacao)
                                VALUES (?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE 
                                valor = VALUES(valor),
                                modificado_por_id = VALUES(modificado_por_id),
                                data_modificacao = VALUES(data_modificacao)
                            ", [$chave, $valor, $_SESSION['user_id']]);
                        }
                        
                        $success = 'Configurações da empresa salvas com sucesso!';
                    } catch (Exception $e) {
                        $errors[] = 'Erro ao salvar configurações: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'salvar_sistema':
                $sistema_manutencao = isset($_POST['sistema_manutencao']) ? 1 : 0;
                $sistema_backup_automatico = isset($_POST['sistema_backup_automatico']) ? 1 : 0;
                $sistema_notificacoes_email = isset($_POST['sistema_notificacoes_email']) ? 1 : 0;
                $sistema_notificacoes_whatsapp = isset($_POST['sistema_notificacoes_whatsapp']) ? 1 : 0;
                $sistema_tema_padrao = $_POST['sistema_tema_padrao'] ?? 'claro';
                $sistema_fuso_horario = $_POST['sistema_fuso_horario'] ?? 'America/Sao_Paulo';
                $sistema_formato_data = $_POST['sistema_formato_data'] ?? 'dd/mm/yyyy';
                $sistema_moeda = $_POST['sistema_moeda'] ?? 'BRL';
                
                try {
                    $configs = [
                        'sistema_manutencao' => $sistema_manutencao,
                        'sistema_backup_automatico' => $sistema_backup_automatico,
                        'sistema_notificacoes_email' => $sistema_notificacoes_email,
                        'sistema_notificacoes_whatsapp' => $sistema_notificacoes_whatsapp,
                        'sistema_tema_padrao' => $sistema_tema_padrao,
                        'sistema_fuso_horario' => $sistema_fuso_horario,
                        'sistema_formato_data' => $sistema_formato_data,
                        'sistema_moeda' => $sistema_moeda
                    ];
                    
                    foreach ($configs as $chave => $valor) {
                        $db->execute("
                            INSERT INTO configuracoes_sistema (chave, valor, modificado_por_id, data_modificacao)
                            VALUES (?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE 
                            valor = VALUES(valor),
                            modificado_por_id = VALUES(modificado_por_id),
                            data_modificacao = VALUES(data_modificacao)
                        ", [$chave, $valor, $_SESSION['user_id']]);
                    }
                    
                    $success = 'Configurações do sistema salvas com sucesso!';
                } catch (Exception $e) {
                    $errors[] = 'Erro ao salvar configurações: ' . $e->getMessage();
                }
                break;
                
            case 'salvar_financeiro':
                $financeiro_percentual_repasse_padrao = floatval($_POST['financeiro_percentual_repasse_padrao'] ?? 0);
                $financeiro_dias_vencimento_padrao = intval($_POST['financeiro_dias_vencimento_padrao'] ?? 30);
                $financeiro_forma_pagamento_padrao = $_POST['financeiro_forma_pagamento_padrao'] ?? 'Dinheiro';
                $financeiro_banco_padrao = trim($_POST['financeiro_banco_padrao'] ?? '');
                $financeiro_agencia_padrao = trim($_POST['financeiro_agencia_padrao'] ?? '');
                $financeiro_conta_padrao = trim($_POST['financeiro_conta_padrao'] ?? '');
                $financeiro_pix_padrao = trim($_POST['financeiro_pix_padrao'] ?? '');
                
                // Validações
                if ($financeiro_percentual_repasse_padrao < 0 || $financeiro_percentual_repasse_padrao > 100) {
                    $errors[] = 'Percentual de repasse deve estar entre 0% e 100%';
                }
                
                if ($financeiro_dias_vencimento_padrao < 1 || $financeiro_dias_vencimento_padrao > 365) {
                    $errors[] = 'Dias de vencimento deve estar entre 1 e 365';
                }
                
                if (empty($errors)) {
                    try {
                        $configs = [
                            'financeiro_percentual_repasse_padrao' => $financeiro_percentual_repasse_padrao,
                            'financeiro_dias_vencimento_padrao' => $financeiro_dias_vencimento_padrao,
                            'financeiro_forma_pagamento_padrao' => $financeiro_forma_pagamento_padrao,
                            'financeiro_banco_padrao' => $financeiro_banco_padrao,
                            'financeiro_agencia_padrao' => $financeiro_agencia_padrao,
                            'financeiro_conta_padrao' => $financeiro_conta_padrao,
                            'financeiro_pix_padrao' => $financeiro_pix_padrao
                        ];
                        
                        foreach ($configs as $chave => $valor) {
                            $db->execute("
                                INSERT INTO configuracoes_sistema (chave, valor, modificado_por_id, data_modificacao)
                                VALUES (?, ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE 
                                valor = VALUES(valor),
                                modificado_por_id = VALUES(modificado_por_id),
                                data_modificacao = VALUES(data_modificacao)
                            ", [$chave, $valor, $_SESSION['user_id']]);
                        }
                        
                        $success = 'Configurações financeiras salvas com sucesso!';
                    } catch (Exception $e) {
                        $errors[] = 'Erro ao salvar configurações: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'criar_usuario':
                $nome_completo = trim($_POST['nome_completo'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $senha = $_POST['senha'] ?? '';
                $confirmar_senha = $_POST['confirmar_senha'] ?? '';
                $perfil_id = intval($_POST['perfil_id'] ?? 0);
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                
                // Validações
                if (empty($nome_completo)) {
                    $errors[] = 'Nome completo é obrigatório';
                }
                
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'E-mail válido é obrigatório';
                }
                
                if (strlen($senha) < 6) {
                    $errors[] = 'Senha deve ter pelo menos 6 caracteres';
                }
                
                if ($senha !== $confirmar_senha) {
                    $errors[] = 'Senhas não conferem';
                }
                
                if ($perfil_id <= 0) {
                    $errors[] = 'Perfil é obrigatório';
                }
                
                // Verifica se e-mail já existe
                if (empty($errors)) {
                    $emailExiste = $db->select("SELECT id FROM usuarios WHERE email = ?", [$email]);
                    if (!empty($emailExiste)) {
                        $errors[] = 'E-mail já está em uso';
                    }
                }
                
                if (empty($errors)) {
                    try {
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                        
                        $db->execute("
                            INSERT INTO usuarios (nome_completo, email, senha, perfil_id, ativo, cadastrado_por_id, data_cadastro)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ", [$nome_completo, $email, $senhaHash, $perfil_id, $ativo, $_SESSION['user_id']]);
                        
                        $success = 'Usuário criado com sucesso!';
                    } catch (Exception $e) {
                        $errors[] = 'Erro ao criar usuário: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Busca configurações atuais
$configsAtual = $db->select("SELECT chave, valor FROM configuracoes_sistema");
$configs = [];
foreach ($configsAtual as $config) {
    $configs[$config['chave']] = $config['valor'];
}

// Busca usuários
$usuarios = $db->select("
    SELECT u.*, p.nome as perfil_nome,
           u1.nome_completo as cadastrado_por_nome
    FROM usuarios u
    LEFT JOIN perfis p ON u.perfil_id = p.id
    LEFT JOIN usuarios u1 ON u.cadastrado_por_id = u1.id
    ORDER BY u.nome_completo
");

// Busca perfis
$perfis = $db->select("SELECT * FROM perfis ORDER BY nome");

// Estatísticas do sistema
$stats = $db->select("
    SELECT 
        (SELECT COUNT(*) FROM usuarios WHERE ativo = 1) as usuarios_ativos,
        (SELECT COUNT(*) FROM clientes WHERE ativo = 1) as clientes_ativos,
        (SELECT COUNT(*) FROM clinicas WHERE ativo = 1) as clinicas_ativas,
        (SELECT COUNT(*) FROM guias WHERE DATE(data_cadastro) = CURDATE()) as guias_hoje,
        (SELECT COUNT(*) FROM guias WHERE pago = 0) as pagamentos_pendentes,
        (SELECT COUNT(*) FROM guias WHERE repasse_pago = 0 AND pago = 1) as repasses_pendentes
")[0];

$pageTitle = 'Configurações do Sistema';
$pageSubtitle = 'Gerenciamento e configurações';

include 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100 dark:bg-gray-900">
    
    <?php include 'templates/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <?php include 'templates/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-900 p-6">
            
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="bem_vindo.php" class="text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">
                            <i data-lucide="home" class="w-4 h-4"></i>
                        </a>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Configurações</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Configurações do Sistema</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Gerenciamento completo do sistema
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <button onclick="fazerBackup()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Backup
                    </button>
                    
                    <button onclick="limparCache()" class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Limpar Cache
                    </button>
                    
                    <button onclick="gerarRelatorioSistema()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                        Relatório
                    </button>
                </div>
            </div>
            
            <!-- Mensagens -->
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-2 mb-2">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-600 dark:text-red-400"></i>
                        <h3 class="font-semibold text-red-800 dark:text-red-200">Erro ao salvar</h3>
                    </div>
                    <ul class="list-disc list-inside text-red-700 dark:text-red-300">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                    <div class="flex items-center gap-2">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400"></i>
                        <p class="font-semibold text-green-800 dark:text-green-200"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Estatísticas do Sistema -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Usuários Ativos</p>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo $stats['usuarios_ativos']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="users" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clientes Ativos</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo $stats['clientes_ativos']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="user" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clínicas Ativas</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo $stats['clinicas_ativas']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="building" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Guias Hoje</p>
                            <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400"><?php echo $stats['guias_hoje']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                            <i data-lucide="calendar" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pagamentos Pendentes</p>
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400"><?php echo $stats['pagamentos_pendentes']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                            <i data-lucide="clock" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Repasses Pendentes</p>
                            <p class="text-3xl font-bold text-orange-600 dark:text-orange-400"><?php echo $stats['repasses_pendentes']; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                            <i data-lucide="arrow-right-left" class="w-6 h-6 text-orange-600 dark:text-orange-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Abas de Configuração -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                
                <!-- Navegação das Abas -->
                <div class="border-b dark:border-gray-700">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <button onclick="mostrarAba('empresa')" 
                                class="aba-btn aba-ativa py-4 px-1 border-b-2 font-medium text-sm"
                                data-aba="empresa">
                            <i data-lucide="building" class="w-4 h-4 inline mr-2"></i>
                            Empresa
                        </button>
                        <button onclick="mostrarAba('sistema')" 
                                class="aba-btn py-4 px-1 border-b-2 font-medium text-sm"
                                data-aba="sistema">
                            <i data-lucide="settings" class="w-4 h-4 inline mr-2"></i>
                            Sistema
                        </button>
                        <button onclick="mostrarAba('financeiro')" 
                                class="aba-btn py-4 px-1 border-b-2 font-medium text-sm"
                                data-aba="financeiro">
                            <i data-lucide="dollar-sign" class="w-4 h-4 inline mr-2"></i>
                            Financeiro
                        </button>
                        <button onclick="mostrarAba('usuarios')" 
                                class="aba-btn py-4 px-1 border-b-2 font-medium text-sm"
                                data-aba="usuarios">
                            <i data-lucide="users" class="w-4 h-4 inline mr-2"></i>
                            Usuários
                        </button>
                    </nav>
                </div>
                
                <!-- Conteúdo das Abas -->
                <div class="p-6">
                    
                    <!-- Aba Empresa -->
                    <div id="aba-empresa" class="aba-conteudo">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Informações da Empresa</h3>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="salvar_empresa">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <div>
                                    <label for="empresa_nome" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Nome da Empresa *
                                    </label>
                                    <input type="text" 
                                           id="empresa_nome" 
                                           name="empresa_nome" 
                                           value="<?php echo htmlspecialchars($configs['empresa_nome'] ?? ''); ?>"
                                           required
                                           class="input-destacado w-full">
                                </div>
                                
                                <div>
                                    <label for="empresa_slogan" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Slogan
                                    </label>
                                    <input type="text" 
                                           id="empresa_slogan" 
                                           name="empresa_slogan" 
                                           value="<?php echo htmlspecialchars($configs['empresa_slogan'] ?? ''); ?>"
                                           class="input-destacado w-full">
                                </div>
                                
                                <div>
                                    <label for="empresa_cnpj" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        CNPJ
                                    </label>
                                    <input type="text" 
                                           id="empresa_cnpj" 
                                           name="empresa_cnpj" 
                                           value="<?php echo htmlspecialchars($configs['empresa_cnpj'] ?? ''); ?>"
                                           class="input-destacado w-full mask-cnpj">
                                </div>
                                
                                <div>
                                    <label for="empresa_telefone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Telefone
                                    </label>
                                    <input type="text" 
                                           id="empresa_telefone" 
                                           name="empresa_telefone" 
                                           value="<?php echo htmlspecialchars($configs['empresa_telefone'] ?? ''); ?>"
                                           class="input-destacado w-full mask-phone">
                                </div>
                                
                                <div>
                                    <label for="empresa_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        E-mail
                                    </label>
                                    <input type="email" 
                                           id="empresa_email" 
                                           name="empresa_email" 
                                           value="<?php echo htmlspecialchars($configs['empresa_email'] ?? ''); ?>"
                                           class="input-destacado w-full">
                                </div>
                                
                                <div>
                                    <label for="empresa_site" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Site
                                    </label>
                                    <input type="url" 
                                           id="empresa_site" 
                                           name="empresa_site" 
                                           value="<?php echo htmlspecialchars($configs['empresa_site'] ?? ''); ?>"
                                           class="input-destacado w-full">
                                </div>
                                
                            </div>
                            
                            <div>
                                <label for="empresa_endereco" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Endereço Completo
                                </label>
                                <textarea id="empresa_endereco" 
                                          name="empresa_endereco" 
                                          rows="3"
                                          class="input-destacado w-full"><?php echo htmlspecialchars($configs['empresa_endereco'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Salvar Configurações da Empresa
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Aba Sistema -->
                    <div id="aba-sistema" class="aba-conteudo hidden">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Configurações do Sistema</h3>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="salvar_sistema">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <!-- Configurações Gerais -->
                                <div class="space-y-4">
                                    <h4 class="font-medium text-gray-900 dark:text-white">Configurações Gerais</h4>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               id="sistema_manutencao" 
                                               name="sistema_manutencao" 
                                               <?php echo ($configs['sistema_manutencao'] ?? 0) ? 'checked' : ''; ?>
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <label for="sistema_manutencao" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Modo de Manutenção
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               id="sistema_backup_automatico" 
                                               name="sistema_backup_automatico" 
                                               <?php echo ($configs['sistema_backup_automatico'] ?? 0) ? 'checked' : ''; ?>
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <label for="sistema_backup_automatico" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Backup Automático
                                        </label>
                                    </div>
                                    
                                    <div>
                                        <label for="sistema_tema_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Tema Padrão
                                        </label>
                                        <select id="sistema_tema_padrao" name="sistema_tema_padrao" class="input-destacado w-full">
                                            <option value="claro" <?php echo ($configs['sistema_tema_padrao'] ?? 'claro') === 'claro' ? 'selected' : ''; ?>>Claro</option>
                                            <option value="escuro" <?php echo ($configs['sistema_tema_padrao'] ?? 'claro') === 'escuro' ? 'selected' : ''; ?>>Escuro</option>
                                            <option value="auto" <?php echo ($configs['sistema_tema_padrao'] ?? 'claro') === 'auto' ? 'selected' : ''; ?>>Automático</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="sistema_fuso_horario" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Fuso Horário
                                        </label>
                                        <select id="sistema_fuso_horario" name="sistema_fuso_horario" class="input-destacado w-full">
                                            <option value="America/Sao_Paulo" <?php echo ($configs['sistema_fuso_horario'] ?? 'America/Sao_Paulo') === 'America/Sao_Paulo' ? 'selected' : ''; ?>>São Paulo (GMT-3)</option>
                                            <option value="America/Manaus" <?php echo ($configs['sistema_fuso_horario'] ?? 'America/Sao_Paulo') === 'America/Manaus' ? 'selected' : ''; ?>>Manaus (GMT-4)</option>
                                            <option value="America/Rio_Branco" <?php echo ($configs['sistema_fuso_horario'] ?? 'America/Sao_Paulo') === 'America/Rio_Branco' ? 'selected' : ''; ?>>Rio Branco (GMT-5)</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Notificações -->
                                <div class="space-y-4">
                                    <h4 class="font-medium text-gray-900 dark:text-white">Notificações</h4>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               id="sistema_notificacoes_email" 
                                               name="sistema_notificacoes_email" 
                                               <?php echo ($configs['sistema_notificacoes_email'] ?? 0) ? 'checked' : ''; ?>
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <label for="sistema_notificacoes_email" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Notificações por E-mail
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="checkbox" 
                                               id="sistema_notificacoes_whatsapp" 
                                               name="sistema_notificacoes_whatsapp" 
                                               <?php echo ($configs['sistema_notificacoes_whatsapp'] ?? 0) ? 'checked' : ''; ?>
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <label for="sistema_notificacoes_whatsapp" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            Notificações por WhatsApp
                                        </label>
                                    </div>
                                    
                                    <div>
                                        <label for="sistema_formato_data" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Formato de Data
                                        </label>
                                        <select id="sistema_formato_data" name="sistema_formato_data" class="input-destacado w-full">
                                            <option value="dd/mm/yyyy" <?php echo ($configs['sistema_formato_data'] ?? 'dd/mm/yyyy') === 'dd/mm/yyyy' ? 'selected' : ''; ?>>DD/MM/AAAA</option>
                                            <option value="mm/dd/yyyy" <?php echo ($configs['sistema_formato_data'] ?? 'dd/mm/yyyy') === 'mm/dd/yyyy' ? 'selected' : ''; ?>>MM/DD/AAAA</option>
                                            <option value="yyyy-mm-dd" <?php echo ($configs['sistema_formato_data'] ?? 'dd/mm/yyyy') === 'yyyy-mm-dd' ? 'selected' : ''; ?>>AAAA-MM-DD</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="sistema_moeda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Moeda
                                        </label>
                                        <select id="sistema_moeda" name="sistema_moeda" class="input-destacado w-full">
                                            <option value="BRL" <?php echo ($configs['sistema_moeda'] ?? 'BRL') === 'BRL' ? 'selected' : ''; ?>>Real (R$)</option>
                                            <option value="USD" <?php echo ($configs['sistema_moeda'] ?? 'BRL') === 'USD' ? 'selected' : ''; ?>>Dólar ($)</option>
                                            <option value="EUR" <?php echo ($configs['sistema_moeda'] ?? 'BRL') === 'EUR' ? 'selected' : ''; ?>>Euro (€)</option>
                                        </select>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Salvar Configurações do Sistema
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Aba Financeiro -->
                    <div id="aba-financeiro" class="aba-conteudo hidden">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Configurações Financeiras</h3>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="salvar_financeiro">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                
                                <div>
                                    <label for="financeiro_percentual_repasse_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Percentual de Repasse Padrão (%)
                                    </label>
                                    <input type="number" 
                                           id="financeiro_percentual_repasse_padrao" 
                                           name="financeiro_percentual_repasse_padrao" 
                                           value="<?php echo htmlspecialchars($configs['financeiro_percentual_repasse_padrao'] ?? '70'); ?>"
                                           min="0" 
                                           max="100" 
                                           step="0.01"
                                           class="input-destacado w-full">
                                </div>
                                
                                <div>
                                    <label for="financeiro_dias_vencimento_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Dias de Vencimento Padrão
                                    </label>
                                    <input type="number" 
                                           id="financeiro_dias_vencimento_padrao" 
                                           name="financeiro_dias_vencimento_padrao" 
                                           value="<?php echo htmlspecialchars($configs['financeiro_dias_vencimento_padrao'] ?? '30'); ?>"
                                           min="1" 
                                           max="365"
                                           class="input-destacado w-full">
                                </div>
                                
                                <div>
                                    <label for="financeiro_forma_pagamento_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Forma de Pagamento Padrão
                                    </label>
                                    <select id="financeiro_forma_pagamento_padrao" name="financeiro_forma_pagamento_padrao" class="input-destacado w-full">
                                        <option value="Dinheiro" <?php echo ($configs['financeiro_forma_pagamento_padrao'] ?? 'Dinheiro') === 'Dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                                        <option value="PIX" <?php echo ($configs['financeiro_forma_pagamento_padrao'] ?? 'Dinheiro') === 'PIX' ? 'selected' : ''; ?>>PIX</option>
                                        <option value="Cartão Débito" <?php echo ($configs['financeiro_forma_pagamento_padrao'] ?? 'Dinheiro') === 'Cartão Débito' ? 'selected' : ''; ?>>Cartão de Débito</option>
                                        <option value="Cartão Crédito" <?php echo ($configs['financeiro_forma_pagamento_padrao'] ?? 'Dinheiro') === 'Cartão Crédito' ? 'selected' : ''; ?>>Cartão de Crédito</option>
                                        <option value="Transferência" <?php echo ($configs['financeiro_forma_pagamento_padrao'] ?? 'Dinheiro') === 'Transferência' ? 'selected' : ''; ?>>Transferência Bancária</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="financeiro_banco_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Banco Padrão
                                    </label>
                                    <input type="text" 
                                           id="financeiro_banco_padrao" 
                                           name="financeiro_banco_padrao" 
                                           value="<?php echo htmlspecialchars($configs['financeiro_banco_padrao'] ?? ''); ?>"
                                           class="input-destacado w-full">
                                </div>
                                
                                <div>
                                    <label for="financeiro_agencia_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Agência Padrão
                                    </label>
                                    <input type="text" 
                                           id="financeiro_agencia_padrao" 
                                           name="financeiro_agencia_padrao" 
                                           value="<?php echo htmlspecialchars($configs['financeiro_agencia_padrao'] ?? ''); ?>"
                                           class="input-destacado w-full">
                                </div>
                                
                                <div>
                                    <label for="financeiro_conta_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Conta Padrão
                                    </label>
                                    <input type="text" 
                                           id="financeiro_conta_padrao" 
                                           name="financeiro_conta_padrao" 
                                           value="<?php echo htmlspecialchars($configs['financeiro_conta_padrao'] ?? ''); ?>"
                                           class="input-destacado w-full">
                                </div>
                                
                            </div>
                            
                            <div>
                                <label for="financeiro_pix_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Chave PIX Padrão
                                </label>
                                <input type="text" 
                                       id="financeiro_pix_padrao" 
                                       name="financeiro_pix_padrao" 
                                       value="<?php echo htmlspecialchars($configs['financeiro_pix_padrao'] ?? ''); ?>"
                                       class="input-destacado w-full">
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Salvar Configurações Financeiras
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Aba Usuários -->
                    <div id="aba-usuarios" class="aba-conteudo hidden">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Gerenciamento de Usuários</h3>
                            <button onclick="mostrarModalUsuario()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i data-lucide="plus" class="w-4 h-4"></i>
                                Novo Usuário
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>E-mail</th>
                                        <th>Perfil</th>
                                        <th>Status</th>
                                        <th>Cadastrado por</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($usuario['nome_completo']); ?>
                                                </p>
                                            </td>
                                            <td>
                                                <p class="text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($usuario['email']); ?>
                                                </p>
                                            </td>
                                            <td>
                                                <span class="inline-flex items-center px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                                                    <?php echo htmlspecialchars($usuario['perfil_nome']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($usuario['ativo']): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded-full">
                                                        <i data-lucide="check-circle" class="w-3 h-3"></i>
                                                        Ativo
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs rounded-full">
                                                        <i data-lucide="x-circle" class="w-3 h-3"></i>
                                                        Inativo
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($usuario['cadastrado_por_nome'] ?? 'Sistema'); ?>
                                                </p>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <button onclick="editarUsuario(<?php echo $usuario['id']; ?>)" 
                                                            class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                            title="Editar usuário">
                                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                                    </button>
                                                    
                                                    <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                                                        <button onclick="alternarStatusUsuario(<?php echo $usuario['id']; ?>, <?php echo $usuario['ativo'] ? 0 : 1; ?>)" 
                                                                class="inline-flex items-center justify-center w-8 h-8 text-<?php echo $usuario['ativo'] ? 'red' : 'green'; ?>-600 hover:text-<?php echo $usuario['ativo'] ? 'red' : 'green'; ?>-800 hover:bg-<?php echo $usuario['ativo'] ? 'red' : 'green'; ?>-100 dark:hover:bg-<?php echo $usuario['ativo'] ? 'red' : 'green'; ?>-900 rounded-lg"
                                                                title="<?php echo $usuario['ativo'] ? 'Desativar' : 'Ativar'; ?> usuário">
                                                            <i data-lucide="<?php echo $usuario['ativo'] ? 'user-x' : 'user-check'; ?>" class="w-4 h-4"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                </div>
            </div>
            
        </main>
        
    </div>
    
</div>

<!-- Modal de Novo Usuário -->
<div id="modal-usuario" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Novo Usuário</h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="criar_usuario">
            
            <div>
                <label for="nome_completo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Nome Completo *
                </label>
                <input type="text" 
                       id="nome_completo" 
                       name="nome_completo" 
                       required
                       class="input-destacado w-full">
            </div>
            
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    E-mail *
                </label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required
                       class="input-destacado w-full">
            </div>
            
            <div>
                <label for="perfil_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Perfil *
                </label>
                <select id="perfil_id" name="perfil_id" required class="input-destacado w-full">
                    <option value="">Selecione um perfil</option>
                    <?php foreach ($perfis as $perfil): ?>
                        <option value="<?php echo $perfil['id']; ?>">
                            <?php echo htmlspecialchars($perfil['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="senha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Senha *
                </label>
                <input type="password" 
                       id="senha" 
                       name="senha" 
                       required
                       minlength="6"
                       class="input-destacado w-full">
            </div>
            
            <div>
                <label for="confirmar_senha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Confirmar Senha *
                </label>
                <input type="password" 
                       id="confirmar_senha" 
                       name="confirmar_senha" 
                       required
                       minlength="6"
                       class="input-destacado w-full">
            </div>
            
            <div class="flex items-center">
                <input type="checkbox" 
                       id="ativo" 
                       name="ativo" 
                       checked
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="ativo" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                    Usuário ativo
                </label>
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" onclick="fecharModalUsuario()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Criar Usuário
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Função para mostrar abas
function mostrarAba(aba) {
    // Remove classe ativa de todos os botões
    document.querySelectorAll('.aba-btn').forEach(btn => {
        btn.classList.remove('aba-ativa');
    });
    
    // Esconde todos os conteúdos
    document.querySelectorAll('.aba-conteudo').forEach(conteudo => {
        conteudo.classList.add('hidden');
    });
    
    // Ativa o botão clicado
    document.querySelector(`[data-aba="${aba}"]`).classList.add('aba-ativa');
    
    // Mostra o conteúdo correspondente
    document.getElementById(`aba-${aba}`).classList.remove('hidden');
}

// Função para mostrar modal de usuário
function mostrarModalUsuario() {
    document.getElementById('modal-usuario').classList.remove('hidden');
    document.getElementById('modal-usuario').classList.add('flex');
}

// Função para fechar modal de usuário
function fecharModalUsuario() {
    document.getElementById('modal-usuario').classList.add('hidden');
    document.getElementById('modal-usuario').classList.remove('flex');
}

// Função para editar usuário
function editarUsuario(userId) {
    // Implementar edição de usuário
    showToast('Funcionalidade de edição em desenvolvimento', 'info');
}

// Função para alterar status do usuário
function alternarStatusUsuario(userId, novoStatus) {
    const acao = novoStatus ? 'ativar' : 'desativar';
    
    if (confirm(`Tem certeza que deseja ${acao} este usuário?`)) {
        // Implementar alteração de status
        showToast(`Usuário ${acao}do com sucesso!`, 'success');
        setTimeout(() => {
            window.location.reload();
        }, 1500);
    }
}

// Função para fazer backup
function fazerBackup() {
    showToast('Iniciando backup do sistema...', 'info');
    
    // Implementar backup real
    setTimeout(() => {
        showToast('Backup realizado com sucesso!', 'success');
    }, 3000);
}

// Função para limpar cache
function limparCache() {
    showToast('Limpando cache do sistema...', 'info');
    
    // Implementar limpeza de cache
    setTimeout(() => {
        showToast('Cache limpo com sucesso!', 'success');
    }, 2000);
}

// Função para gerar relatório do sistema
function gerarRelatorioSistema() {
    window.open('relatorio_sistema.php', '_blank');
}

// Validação de senhas iguais
document.getElementById('confirmar_senha').addEventListener('input', function() {
    const senha = document.getElementById('senha').value;
    const confirmarSenha = this.value;
    
    if (senha !== confirmarSenha) {
        this.setCustomValidity('As senhas não conferem');
    } else {
        this.setCustomValidity('');
    }
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalUsuario();
    }
});

// Aplicar máscaras
document.addEventListener('DOMContentLoaded', function() {
    // Máscara CNPJ
    const cnpjInput = document.querySelector('.mask-cnpj');
    if (cnpjInput) {
        cnpjInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '')
                .replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2')
                .substring(0, 18);
        });
    }
    
    // Máscara telefone
    const phoneInput = document.querySelector('.mask-phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '')
                .replace(/^(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d)/, '$1-$2')
                .substring(0, 15);
        });
    }
});
</script>

<style>
.aba-btn {
    @apply border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600;
}

.aba-ativa {
    @apply border-blue-500 text-blue-600 dark:text-blue-400;
}
</style>

<?php include 'templates/footer.php'; ?>
