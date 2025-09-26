<?php
// Página de Edição de Cliente - Salvar como cliente_editar.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();
$errors = [];
$success = false;

// Obtém o ID do cliente
$clientId = intval($_GET['id'] ?? 0);

if ($clientId <= 0) {
    header('Location: clientes.php');
    exit;
}

// Busca os dados do cliente
$cliente = $db->select("SELECT * FROM clientes WHERE id = ?", [$clientId]);

if (!$cliente) {
    header('Location: clientes.php?error=Cliente não encontrado');
    exit;
}

$cliente = $cliente[0];

// Verifica mensagem de sucesso
if (isset($_GET['success'])) {
    $success = true;
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação dos campos obrigatórios
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    
    // Validações
    if (empty($nome_completo)) {
        $errors[] = 'Nome completo é obrigatório';
    }
    
    if (empty($cpf)) {
        $errors[] = 'CPF é obrigatório';
    } elseif (!validateCPF($cpf)) {
        $errors[] = 'CPF inválido';
    } else {
        // Verifica se CPF já existe (exceto o próprio cliente)
        $existingCPF = $db->select("SELECT id FROM clientes WHERE cpf = ? AND id != ?", [$cpf, $clientId]);
        if ($existingCPF) {
            $errors[] = 'CPF já cadastrado no sistema';
        }
    }
    
    if (empty($data_nascimento)) {
        $errors[] = 'Data de nascimento é obrigatória';
    }
    
    if (empty($sexo)) {
        $errors[] = 'Sexo é obrigatório';
    }
    
    // Se não há erros, processa a atualização
    if (empty($errors)) {
        try {
            // Processa upload da foto
            $foto_path = $cliente['foto_path'];
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                // Remove foto anterior se existir
                if ($foto_path && file_exists($foto_path)) {
                    unlink($foto_path);
                }
                $foto_path = uploadClientPhoto($_FILES['foto'], $cliente['codigo_cliente']);
            }
            
            // Dados do cliente
            $clientData = [
                'nome_completo' => $nome_completo,
                'cpf' => $cpf,
                'data_nascimento' => $data_nascimento,
                'sexo' => $sexo,
                'telefone1' => preg_replace('/\D/', '', $_POST['telefone1'] ?? ''),
                'telefone2' => preg_replace('/\D/', '', $_POST['telefone2'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'cep' => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
                'rua' => trim($_POST['rua'] ?? ''),
                'numero' => trim($_POST['numero'] ?? ''),
                'complemento' => trim($_POST['complemento'] ?? ''),
                'bairro' => trim($_POST['bairro'] ?? ''),
                'cidade' => trim($_POST['cidade'] ?? ''),
                'estado' => $_POST['estado'] ?? '',
                'nome_mae' => trim($_POST['nome_mae'] ?? ''),
                'nome_pai' => trim($_POST['nome_pai'] ?? ''),
                'estado_civil' => $_POST['estado_civil'] ?? '',
                'profissao' => trim($_POST['profissao'] ?? ''),
                'renda_familiar' => floatval($_POST['renda_familiar'] ?? 0),
                'observacoes' => trim($_POST['observacoes'] ?? ''),
                'aceita_parabens' => isset($_POST['aceita_parabens']) ? 1 : 0,
                'foto_path' => $foto_path,
                'modificado_por_id' => $_SESSION['user_id'],
                'data_modificacao' => date('Y-m-d H:i:s')
            ];
            
            // Monta a query de atualização
            $setClause = implode(' = ?, ', array_keys($clientData)) . ' = ?';
            $sql = "UPDATE clientes SET $setClause WHERE id = ?";
            $params = array_merge(array_values($clientData), [$clientId]);
            
            $updated = $db->execute($sql, $params);
            
            if ($updated) {
                $success = true;
                
                // Processa documentos anexados
                if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
                    uploadClientDocuments($_FILES['documentos'], $clientId);
                }
                
                // Atualiza os dados do cliente para exibição
                $cliente = $db->select("SELECT * FROM clientes WHERE id = ?", [$clientId]);
                $cliente = $cliente[0];
                
            } else {
                $errors[] = 'Erro ao atualizar cliente. Tente novamente.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro interno: ' . $e->getMessage();
            error_log("Erro ao atualizar cliente: " . $e->getMessage());
        }
    }
}

// Busca documentos do cliente
$documentos = $db->select(
    "SELECT * FROM documentos WHERE documentavel_id = ? AND documentavel_type = 'Cliente' ORDER BY data_upload DESC", 
    [$clientId]
);

$pageTitle = 'Editar Cliente';
$pageSubtitle = 'Código: ' . $cliente['codigo_cliente'] . ' - ' . $cliente['nome_completo'];

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
                    <li>
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <a href="clientes.php" class="ml-1 text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Clientes</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Editar</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Mensagens -->
            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700 dark:text-green-300">Cliente atualizado com sucesso!</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex items-start">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2 mt-0.5 flex-shrink-0"></i>
                        <div>
                            <h3 class="text-sm font-medium text-red-800 dark:text-red-200 mb-2">
                                Corrija os seguintes erros:
                            </h3>
                            <ul class="text-sm text-red-700 dark:text-red-300 space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li>• <?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Informações do Cliente -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <?php if ($cliente['foto_path'] && file_exists($cliente['foto_path'])): ?>
                                <img src="<?php echo htmlspecialchars($cliente['foto_path']); ?>" 
                                     class="w-16 h-16 rounded-full object-cover border-4 border-gray-200 dark:border-gray-600" 
                                     alt="Foto do cliente">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center border-4 border-gray-200 dark:border-gray-600">
                                    <span class="text-xl font-semibold text-gray-600 dark:text-gray-300">
                                        <?php echo strtoupper(substr($cliente['nome_completo'], 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($cliente['nome_completo']); ?>
                                </h2>
                                <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                    <span>Código: <?php echo htmlspecialchars($cliente['codigo_cliente']); ?></span>
                                    <span>CPF: <?php echo formatCPF($cliente['cpf']); ?></span>
                                    <span>Idade: <?php echo calculateAge($cliente['data_nascimento']); ?> anos</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <?php if ($cliente['telefone1']): ?>
                                <a href="<?php echo getWhatsAppLink($cliente['telefone1']); ?>" 
                                   target="_blank"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                                    WhatsApp
                                </a>
                            <?php endif; ?>
                            
                            <button onclick="deleteClient(<?php echo $clientId; ?>)" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                                    data-action="delete"
                                    data-item="o cliente <?php echo htmlspecialchars($cliente['nome_completo']); ?>">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulário -->
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <!-- Dados Pessoais -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="user" class="w-5 h-5"></i>
                            Dados Pessoais
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <!-- Foto do Cliente -->
                        <div class="flex items-center gap-6">
                            <div class="flex-shrink-0">
                                <img id="foto-preview" 
                                     src="<?php echo $cliente['foto_path'] && file_exists($cliente['foto_path']) ? htmlspecialchars($cliente['foto_path']) : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%23d1d5db' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'%3E%3C/path%3E%3Ccircle cx='12' cy='7' r='4'%3E%3C/circle%3E%3C/svg%3E"; ?>" 
                                     class="w-24 h-24 rounded-full object-cover border-4 border-gray-200 dark:border-gray-600"
                                     alt="Foto do cliente">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Alterar Foto do Cliente
                                </label>
                                <input type="file" 
                                       name="foto" 
                                       accept="image/*"
                                       data-preview="foto-preview"
                                       class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900 dark:file:text-blue-300">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    PNG, JPG ou JPEG. Máximo 2MB.
                                </p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nome Completo -->
                            <div class="md:col-span-2">
                                <label for="nome_completo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome Completo <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="nome_completo" 
                                       name="nome_completo" 
                                       required
                                       value="<?php echo htmlspecialchars($cliente['nome_completo']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Digite o nome completo">
                            </div>
                            
                            <!-- CPF -->
                            <div>
                                <label for="cpf" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    CPF <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="cpf" 
                                       name="cpf" 
                                       required
                                       data-mask="cpf"
                                       maxlength="14"
                                       value="<?php echo formatCPF($cliente['cpf']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="000.000.000-00">
                            </div>
                            
                            <!-- Data de Nascimento -->
                            <div>
                                <label for="data_nascimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Data de Nascimento <span class="text-red-500">*</span>
                                </label>
                                <input type="date" 
                                       id="data_nascimento" 
                                       name="data_nascimento" 
                                       required
                                       value="<?php echo $cliente['data_nascimento']; ?>"
                                       class="input-destacado w-full">
                            </div>
                            
                            <!-- Sexo -->
                            <div>
                                <label for="sexo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Sexo <span class="text-red-500">*</span>
                                </label>
                                <select id="sexo" name="sexo" required class="input-destacado w-full">
                                    <option value="">Selecione...</option>
                                    <option value="Masculino" <?php echo $cliente['sexo'] === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="Feminino" <?php echo $cliente['sexo'] === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                                    <option value="Não Informado" <?php echo $cliente['sexo'] === 'Não Informado' ? 'selected' : ''; ?>>Não Informado</option>
                                </select>
                            </div>
                            
                            <!-- Estado Civil -->
                            <div>
                                <label for="estado_civil" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Estado Civil
                                </label>
                                <select id="estado_civil" name="estado_civil" class="input-destacado w-full">
                                    <option value="">Selecione...</option>
                                    <option value="Solteiro(a)" <?php echo $cliente['estado_civil'] === 'Solteiro(a)' ? 'selected' : ''; ?>>Solteiro(a)</option>
                                    <option value="Casado(a)" <?php echo $cliente['estado_civil'] === 'Casado(a)' ? 'selected' : ''; ?>>Casado(a)</option>
                                    <option value="Divorciado(a)" <?php echo $cliente['estado_civil'] === 'Divorciado(a)' ? 'selected' : ''; ?>>Divorciado(a)</option>
                                    <option value="Viúvo(a)" <?php echo $cliente['estado_civil'] === 'Viúvo(a)' ? 'selected' : ''; ?>>Viúvo(a)</option>
                                    <option value="União Estável" <?php echo $cliente['estado_civil'] === 'União Estável' ? 'selected' : ''; ?>>União Estável</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nome da Mãe -->
                            <div>
                                <label for="nome_mae" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome da Mãe
                                </label>
                                <input type="text" 
                                       id="nome_mae" 
                                       name="nome_mae" 
                                       value="<?php echo htmlspecialchars($cliente['nome_mae']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome completo da mãe">
                            </div>
                            
                            <!-- Nome do Pai -->
                            <div>
                                <label for="nome_pai" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome do Pai
                                </label>
                                <input type="text" 
                                       id="nome_pai" 
                                       name="nome_pai" 
                                       value="<?php echo htmlspecialchars($cliente['nome_pai']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome completo do pai">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Profissão -->
                            <div>
                                <label for="profissao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Profissão
                                </label>
                                <input type="text" 
                                       id="profissao" 
                                       name="profissao" 
                                       value="<?php echo htmlspecialchars($cliente['profissao']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Profissão ou ocupação">
                            </div>
                            
                            <!-- Renda Familiar -->
                            <div>
                                <label for="renda_familiar" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Renda Familiar
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 dark:text-gray-400">R$</span>
                                    </div>
                                    <input type="number" 
                                           id="renda_familiar" 
                                           name="renda_familiar" 
                                           step="0.01"
                                           min="0"
                                           value="<?php echo $cliente['renda_familiar']; ?>"
                                           class="input-destacado w-full pl-10"
                                           placeholder="0,00">
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Contato -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="phone" class="w-5 h-5"></i>
                            Informações de Contato
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Telefone 1 -->
                            <div>
                                <label for="telefone1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Telefone Principal
                                </label>
                                <input type="text" 
                                       id="telefone1" 
                                       name="telefone1" 
                                       data-mask="phone"
                                       value="<?php echo formatPhone($cliente['telefone1']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="+55 (11) 99999-9999">
                            </div>
                            
                            <!-- Telefone 2 -->
                            <div>
                                <label for="telefone2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Telefone Secundário
                                </label>
                                <input type="text" 
                                       id="telefone2" 
                                       name="telefone2" 
                                       data-mask="phone"
                                       value="<?php echo formatPhone($cliente['telefone2']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="+55 (11) 99999-9999">
                            </div>
                        </div>
                        
                        <!-- E-mail -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                E-mail
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($cliente['email']); ?>"
                                   class="input-destacado w-full"
                                   placeholder="cliente@email.com">
                        </div>
                        
                    </div>
                </div>
                
                <!-- Endereço -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5"></i>
                            Endereço
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <!-- CEP -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="cep" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    CEP
                                </label>
                                <div class="flex gap-2">
                                    <input type="text" 
                                           id="cep" 
                                           name="cep" 
                                           data-mask="cep"
                                           maxlength="9"
                                           value="<?php echo formatCEP($cliente['cep']); ?>"
                                           class="input-destacado flex-1"
                                           placeholder="00000-000">
                                    <button type="button" 
                                            data-action="buscar-cep"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                        <i data-lucide="search" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <!-- Rua -->
                            <div class="md:col-span-2">
                                <label for="rua" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Rua/Logradouro
                                </label>
                                <input type="text" 
                                       id="rua" 
                                       name="rua" 
                                       value="<?php echo htmlspecialchars($cliente['rua']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome da rua">
                            </div>
                            
                            <!-- Número -->
                            <div>
                                <label for="numero" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Número
                                </label>
                                <input type="text" 
                                       id="numero" 
                                       name="numero" 
                                       value="<?php echo htmlspecialchars($cliente['numero']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="123">
                            </div>
                            
                            <!-- Complemento -->
                            <div>
                                <label for="complemento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Complemento
                                </label>
                                <input type="text" 
                                       id="complemento" 
                                       name="complemento" 
                                       value="<?php echo htmlspecialchars($cliente['complemento']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Apto, Bloco, etc.">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Bairro -->
                            <div>
                                <label for="bairro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Bairro
                                </label>
                                <input type="text" 
                                       id="bairro" 
                                       name="bairro" 
                                       value="<?php echo htmlspecialchars($cliente['bairro']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome do bairro">
                            </div>
                            
                            <!-- Cidade -->
                            <div>
                                <label for="cidade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cidade
                                </label>
                                <input type="text" 
                                       id="cidade" 
                                       name="cidade" 
                                       value="<?php echo htmlspecialchars($cliente['cidade']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome da cidade">
                            </div>
                            
                            <!-- Estado -->
                            <div>
                                <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Estado
                                </label>
                                <select id="estado" name="estado" class="input-destacado w-full">
                                    <option value="">Selecione...</option>
                                    <?php 
                                    $estados = [
                                        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                                        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                                        'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                                        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                                        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
                                    ];
                                    foreach ($estados as $uf => $nome):
                                    ?>
                                        <option value="<?php echo $uf; ?>" <?php echo $cliente['estado'] === $uf ? 'selected' : ''; ?>>
                                            <?php echo $nome; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Documentos -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="file-text" class="w-5 h-5"></i>
                            Documentos
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <!-- Documentos Existentes -->
                        <?php if (!empty($documentos)): ?>
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Documentos Anexados</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <?php foreach ($documentos as $doc): ?>
                                        <div class="border dark:border-gray-600 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center gap-2">
                                                    <i data-lucide="file" class="w-4 h-4 text-gray-500"></i>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                                        <?php echo htmlspecialchars($doc['nome_original']); ?>
                                                    </span>
                                                </div>
                                                <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                                        class="text-red-500 hover:text-red-700"
                                                        title="Excluir documento">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                <p>Tipo: <?php echo htmlspecialchars($doc['tipo_documento']); ?></p>
                                                <p>Upload: <?php echo formatDateBR($doc['data_upload'], true); ?></p>
                                            </div>
                                            <div class="mt-2">
                                                <a href="<?php echo htmlspecialchars($doc['path_arquivo']); ?>" 
                                                   target="_blank"
                                                   class="text-blue-600 dark:text-blue-400 hover:underline text-xs">
                                                    Visualizar
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Adicionar Novos Documentos -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Anexar Novos Documentos
                            </label>
                            <input type="file" 
                                   name="documentos[]" 
                                   multiple
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                   class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900 dark:file:text-blue-300">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                PDF, DOC, DOCX, JPG, JPEG, PNG. Máximo 5MB por arquivo.
                            </p>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Observações e Preferências -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="message-square" class="w-5 h-5"></i>
                            Observações e Preferências
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <!-- Observações -->
                        <div>
                            <label for="observacoes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Observações
                            </label>
                            <textarea id="observacoes" 
                                      name="observacoes" 
                                      rows="4"
                                      class="input-destacado w-full"
                                      placeholder="Informações adicionais sobre o cliente..."><?php echo htmlspecialchars($cliente['observacoes']); ?></textarea>
                        </div>
                        
                        <!-- Preferências -->
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="aceita_parabens" 
                                       value="1"
                                       <?php echo $cliente['aceita_parabens'] ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Cliente aceita receber mensagens de parabéns no aniversário
                                </span>
                            </label>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Informações de Auditoria -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informações de Auditoria</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">
                                <strong>Cadastrado em:</strong> <?php echo formatDateBR($cliente['data_cadastro'], true); ?>
                            </p>
                            <?php if ($cliente['cadastrado_por_id']): ?>
                                <?php 
                                $cadastradoPor = $db->select("SELECT nome_completo FROM usuarios WHERE id = ?", [$cliente['cadastrado_por_id']]);
                                if ($cadastradoPor):
                                ?>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        <strong>Cadastrado por:</strong> <?php echo htmlspecialchars($cadastradoPor[0]['nome_completo']); ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($cliente['data_modificacao']): ?>
                                <p class="text-gray-600 dark:text-gray-400">
                                    <strong>Última modificação:</strong> <?php echo formatDateBR($cliente['data_modificacao'], true); ?>
                                </p>
                                <?php if ($cliente['modificado_por_id']): ?>
                                    <?php 
                                    $modificadoPor = $db->select("SELECT nome_completo FROM usuarios WHERE id = ?", [$cliente['modificado_por_id']]);
                                    if ($modificadoPor):
                                    ?>
                                        <p class="text-gray-600 dark:text-gray-400">
                                            <strong>Modificado por:</strong> <?php echo htmlspecialchars($modificadoPor[0]['nome_completo']); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="flex items-center justify-between">
                    <a href="clientes.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Voltar
                    </a>
                    
                    <div class="flex gap-3">
                        <button type="button" onclick="window.location.reload()" class="px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                            Cancelar Alterações
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Salvar Alterações
                        </button>
                    </div>
                </div>
                
            </form>
            
        </main>
        
    </div>
    
</div>

<script>
// Validação em tempo real do CPF
document.getElementById('cpf').addEventListener('blur', function() {
    const cpf = this.value.replace(/\D/g, '');
    
    if (cpf.length === 11) {
        // Verifica se CPF já existe (exceto o próprio cliente)
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=validate_cpf&cpf=' + cpf + '&exclude_id=<?php echo $clientId; ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                this.setCustomValidity(data.message);
                this.classList.add('border-red-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
            }
        });
    }
});

// Limpa validação ao digitar
document.getElementById('cpf').addEventListener('input', function() {
    this.setCustomValidity('');
    this.classList.remove('border-red-500');
});

// Função para excluir cliente
function deleteClient(clientId) {
    if (confirm('Tem certeza que deseja excluir este cliente? Esta ação não pode ser desfeita.')) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_client&client_id=' + clientId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Cliente excluído com sucesso!', 'success');
                setTimeout(() => {
                    window.location.href = 'clientes.php';
                }, 1500);
            } else {
                showToast('Erro ao excluir cliente: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Erro interno. Tente novamente.', 'error');
        });
    }
}

// Função para excluir documento
function deleteDocument(documentId) {
    if (confirm('Tem certeza que deseja excluir este documento?')) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_document&document_id=' + documentId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Documento excluído com sucesso!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('Erro ao excluir documento: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Erro interno. Tente novamente.', 'error');
        });
    }
}

// Animação de loading no botão ao submeter
document.querySelector('form').addEventListener('submit', function() {
    const button = this.querySelector('button[type="submit"]');
    const originalContent = button.innerHTML;
    
    button.innerHTML = `
        <div class="flex items-center gap-2">
            <div class="spinner"></div>
            Salvando...
        </div>
    `;
    button.disabled = true;
    
    // Se houver erro, restaura o botão (isso será feito pelo reload da página)
    setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
        lucide.createIcons();
    }, 10000);
});
</script>

<?php include 'templates/footer.php'; ?>
