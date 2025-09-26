<?php
// Formulário de Cadastro de Clínica - Salvar como clinica_formulario.php

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
    // Validação dos campos obrigatórios
    $nome_fantasia = trim($_POST['nome_fantasia'] ?? '');
    $razao_social = trim($_POST['razao_social'] ?? '');
    $cnpj = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
    
    // Validações
    if (empty($nome_fantasia)) {
        $errors[] = 'Nome fantasia é obrigatório';
    }
    
    if (empty($razao_social)) {
        $errors[] = 'Razão social é obrigatória';
    }
    
    if (empty($cnpj)) {
        $errors[] = 'CNPJ é obrigatório';
    } elseif (!validateCNPJ($cnpj)) {
        $errors[] = 'CNPJ inválido';
    } else {
        // Verifica se CNPJ já existe
        $existingCNPJ = $db->select("SELECT id FROM clinicas WHERE cnpj = ?", [$cnpj]);
        if ($existingCNPJ) {
            $errors[] = 'CNPJ já cadastrado no sistema';
        }
    }
    
    // Se não há erros, processa o cadastro
    if (empty($errors)) {
        try {
            // Gera código único da clínica
            $codigo_clinica = generateUniqueCode('clinicas', 'codigo_clinica', 'CLI');
            
            // Processa upload do logo
            $logo_path = null;
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logo_path = uploadClinicLogo($_FILES['logo'], $codigo_clinica);
            }
            
            // Dados da clínica
            $clinicaData = [
                'codigo_clinica' => $codigo_clinica,
                'nome_fantasia' => $nome_fantasia,
                'razao_social' => $razao_social,
                'cnpj' => $cnpj,
                'inscricao_estadual' => trim($_POST['inscricao_estadual'] ?? ''),
                'inscricao_municipal' => trim($_POST['inscricao_municipal'] ?? ''),
                'telefone1' => preg_replace('/\D/', '', $_POST['telefone1'] ?? ''),
                'telefone2' => preg_replace('/\D/', '', $_POST['telefone2'] ?? ''),
                'email_contato' => trim($_POST['email_contato'] ?? ''),
                'site' => trim($_POST['site'] ?? ''),
                'cep' => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
                'rua' => trim($_POST['rua'] ?? ''),
                'numero' => trim($_POST['numero'] ?? ''),
                'bairro' => trim($_POST['bairro'] ?? ''),
                'cidade' => trim($_POST['cidade'] ?? ''),
                'estado' => $_POST['estado'] ?? '',
                'forma_atendimento' => trim($_POST['forma_atendimento'] ?? ''),
                'horario_atendimento' => trim($_POST['horario_atendimento'] ?? ''),
                'dias_atendimento' => trim($_POST['dias_atendimento'] ?? ''),
                'nome_contato_financeiro' => trim($_POST['nome_contato_financeiro'] ?? ''),
                'email_financeiro' => trim($_POST['email_financeiro'] ?? ''),
                'telefone_financeiro' => preg_replace('/\D/', '', $_POST['telefone_financeiro'] ?? ''),
                'logo_path' => $logo_path,
                'status' => 'Ativa',
                'cadastrado_por_id' => $_SESSION['user_id'],
                'data_cadastro' => date('Y-m-d H:i:s')
            ];
            
            // Monta a query de inserção
            $columns = implode(', ', array_keys($clinicaData));
            $placeholders = implode(', ', array_fill(0, count($clinicaData), '?'));
            $sql = "INSERT INTO clinicas ($columns) VALUES ($placeholders)";
            
            $inserted = $db->execute($sql, array_values($clinicaData));
            
            if ($inserted) {
                $clinicaId = $db->lastInsertId();
                $success = true;
                
                // Processa documentos anexados
                if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
                    uploadClinicDocuments($_FILES['documentos'], $clinicaId);
                }
                
                // Redireciona para a página de edição
                header("Location: clinica_editar.php?id=$clinicaId&success=1");
                exit;
                
            } else {
                $errors[] = 'Erro ao cadastrar clínica. Tente novamente.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro interno: ' . $e->getMessage();
            error_log("Erro ao cadastrar clínica: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Nova Clínica';
$pageSubtitle = 'Cadastro de nova clínica ou laboratório parceiro';

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
                            <a href="clinicas.php" class="ml-1 text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Clínicas</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Nova Clínica</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Mensagens -->
            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700 dark:text-green-300">Clínica cadastrada com sucesso!</span>
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
            
            <!-- Cabeçalho -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Nova Clínica</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Cadastre uma nova clínica ou laboratório parceiro
                </p>
            </div>
            
            <!-- Formulário -->
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <!-- Dados Básicos -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="building" class="w-5 h-5"></i>
                            Dados Básicos
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <!-- Logo da Clínica -->
                        <div class="flex items-center gap-6">
                            <div class="flex-shrink-0">
                                <img id="logo-preview" 
                                     src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%23d1d5db' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 21h18'%3E%3C/path%3E%3Cpath d='M5 21V7l8-4v18'%3E%3C/path%3E%3Cpath d='M19 21V11l-6-4'%3E%3C/path%3E%3C/svg%3E" 
                                     class="w-24 h-24 rounded-lg object-cover border-4 border-gray-200 dark:border-gray-600"
                                     alt="Logo da clínica">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Logo da Clínica
                                </label>
                                <input type="file" 
                                       name="logo" 
                                       accept="image/*"
                                       data-preview="logo-preview"
                                       class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900 dark:file:text-blue-300">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    PNG, JPG ou JPEG. Máximo 2MB. Recomendado: 200x200px.
                                </p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nome Fantasia -->
                            <div>
                                <label for="nome_fantasia" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome Fantasia <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="nome_fantasia" 
                                       name="nome_fantasia" 
                                       required
                                       value="<?php echo htmlspecialchars($_POST['nome_fantasia'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome comercial da clínica">
                            </div>
                            
                            <!-- Razão Social -->
                            <div>
                                <label for="razao_social" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Razão Social <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="razao_social" 
                                       name="razao_social" 
                                       required
                                       value="<?php echo htmlspecialchars($_POST['razao_social'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Razão social da empresa">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- CNPJ -->
                            <div>
                                <label for="cnpj" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    CNPJ <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="cnpj" 
                                       name="cnpj" 
                                       required
                                       data-mask="cnpj"
                                       maxlength="18"
                                       value="<?php echo formatCNPJ($_POST['cnpj'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="00.000.000/0000-00">
                            </div>
                            
                            <!-- Inscrição Estadual -->
                            <div>
                                <label for="inscricao_estadual" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Inscrição Estadual
                                </label>
                                <input type="text" 
                                       id="inscricao_estadual" 
                                       name="inscricao_estadual" 
                                       value="<?php echo htmlspecialchars($_POST['inscricao_estadual'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Número da IE">
                            </div>
                            
                            <!-- Inscrição Municipal -->
                            <div>
                                <label for="inscricao_municipal" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Inscrição Municipal
                                </label>
                                <input type="text" 
                                       id="inscricao_municipal" 
                                       name="inscricao_municipal" 
                                       value="<?php echo htmlspecialchars($_POST['inscricao_municipal'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Número da IM">
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
                                       value="<?php echo formatPhone($_POST['telefone1'] ?? ''); ?>"
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
                                       value="<?php echo formatPhone($_POST['telefone2'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="+55 (11) 99999-9999">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- E-mail -->
                            <div>
                                <label for="email_contato" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    E-mail de Contato
                                </label>
                                <input type="email" 
                                       id="email_contato" 
                                       name="email_contato" 
                                       value="<?php echo htmlspecialchars($_POST['email_contato'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="contato@clinica.com">
                            </div>
                            
                            <!-- Site -->
                            <div>
                                <label for="site" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Site
                                </label>
                                <input type="url" 
                                       id="site" 
                                       name="site" 
                                       value="<?php echo htmlspecialchars($_POST['site'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="https://www.clinica.com">
                            </div>
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
                                           value="<?php echo formatCEP($_POST['cep'] ?? ''); ?>"
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
                                       value="<?php echo htmlspecialchars($_POST['rua'] ?? ''); ?>"
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
                                       value="<?php echo htmlspecialchars($_POST['numero'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="123">
                            </div>
                            
                            <!-- Bairro -->
                            <div>
                                <label for="bairro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Bairro
                                </label>
                                <input type="text" 
                                       id="bairro" 
                                       name="bairro" 
                                       value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome do bairro">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Cidade -->
                            <div>
                                <label for="cidade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cidade
                                </label>
                                <input type="text" 
                                       id="cidade" 
                                       name="cidade" 
                                       value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>"
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
                                        <option value="<?php echo $uf; ?>" <?php echo ($_POST['estado'] ?? '') === $uf ? 'selected' : ''; ?>>
                                            <?php echo $nome; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Informações Operacionais -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="clock" class="w-5 h-5"></i>
                            Informações Operacionais
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Forma de Atendimento -->
                            <div>
                                <label for="forma_atendimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Forma de Atendimento
                                </label>
                                <input type="text" 
                                       id="forma_atendimento" 
                                       name="forma_atendimento" 
                                       value="<?php echo htmlspecialchars($_POST['forma_atendimento'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Ex: Particular, Convênios, SUS">
                            </div>
                            
                            <!-- Horário de Atendimento -->
                            <div>
                                <label for="horario_atendimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Horário de Atendimento
                                </label>
                                <input type="text" 
                                       id="horario_atendimento" 
                                       name="horario_atendimento" 
                                       value="<?php echo htmlspecialchars($_POST['horario_atendimento'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Ex: 08:00 às 18:00">
                            </div>
                            
                            <!-- Dias de Atendimento -->
                            <div>
                                <label for="dias_atendimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Dias de Atendimento
                                </label>
                                <input type="text" 
                                       id="dias_atendimento" 
                                       name="dias_atendimento" 
                                       value="<?php echo htmlspecialchars($_POST['dias_atendimento'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Ex: Segunda a Sexta">
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Contato Financeiro -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                            Contato Financeiro
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Nome do Contato Financeiro -->
                            <div>
                                <label for="nome_contato_financeiro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome do Responsável
                                </label>
                                <input type="text" 
                                       id="nome_contato_financeiro" 
                                       name="nome_contato_financeiro" 
                                       value="<?php echo htmlspecialchars($_POST['nome_contato_financeiro'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome do responsável financeiro">
                            </div>
                            
                            <!-- E-mail Financeiro -->
                            <div>
                                <label for="email_financeiro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    E-mail Financeiro
                                </label>
                                <input type="email" 
                                       id="email_financeiro" 
                                       name="email_financeiro" 
                                       value="<?php echo htmlspecialchars($_POST['email_financeiro'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="financeiro@clinica.com">
                            </div>
                            
                            <!-- Telefone Financeiro -->
                            <div>
                                <label for="telefone_financeiro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Telefone Financeiro
                                </label>
                                <input type="text" 
                                       id="telefone_financeiro" 
                                       name="telefone_financeiro" 
                                       data-mask="phone"
                                       value="<?php echo formatPhone($_POST['telefone_financeiro'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="+55 (11) 99999-9999">
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
                        
                        <!-- Anexar Documentos -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Anexar Documentos
                            </label>
                            <input type="file" 
                                   name="documentos[]" 
                                   multiple
                                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                                   class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900 dark:file:text-blue-300">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                PDF, DOC, DOCX, JPG, JPEG, PNG. Máximo 5MB por arquivo.
                                <br>Sugestões: Contrato social, CNPJ, Alvará de funcionamento, etc.
                            </p>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="flex items-center justify-between">
                    <a href="clinicas.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Voltar
                    </a>
                    
                    <div class="flex gap-3">
                        <button type="reset" class="px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                            Limpar Formulário
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Cadastrar Clínica
                        </button>
                    </div>
                </div>
                
            </form>
            
        </main>
        
    </div>
    
</div>

<script>
// Validação em tempo real do CNPJ
document.getElementById('cnpj').addEventListener('blur', function() {
    const cnpj = this.value.replace(/\D/g, '');
    
    if (cnpj.length === 14) {
        // Verifica se CNPJ já existe
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=validate_cnpj&cnpj=' + cnpj
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
document.getElementById('cnpj').addEventListener('input', function() {
    this.setCustomValidity('');
    this.classList.remove('border-red-500');
});

// Animação de loading no botão ao submeter
document.querySelector('form').addEventListener('submit', function() {
    const button = this.querySelector('button[type="submit"]');
    const originalContent = button.innerHTML;
    
    button.innerHTML = `
        <div class="flex items-center gap-2">
            <div class="spinner"></div>
            Cadastrando...
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
