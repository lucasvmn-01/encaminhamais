<?php
// Página de Edição de Clínica - Salvar como clinica_editar.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();
$errors = [];
$success = false;

// Obtém o ID da clínica
$clinicaId = intval($_GET['id'] ?? 0);

if ($clinicaId <= 0) {
    header('Location: clinicas.php');
    exit;
}

// Busca os dados da clínica
$clinica = $db->select("SELECT * FROM clinicas WHERE id = ?", [$clinicaId]);

if (!$clinica) {
    header('Location: clinicas.php?error=Clínica não encontrada');
    exit;
}

$clinica = $clinica[0];

// Verifica mensagem de sucesso
if (isset($_GET['success'])) {
    $success = true;
}

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
        // Verifica se CNPJ já existe (exceto a própria clínica)
        $existingCNPJ = $db->select("SELECT id FROM clinicas WHERE cnpj = ? AND id != ?", [$cnpj, $clinicaId]);
        if ($existingCNPJ) {
            $errors[] = 'CNPJ já cadastrado no sistema';
        }
    }
    
    // Se não há erros, processa a atualização
    if (empty($errors)) {
        try {
            // Processa upload do logo
            $logo_path = $clinica['logo_path'];
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                // Remove logo anterior se existir
                if ($logo_path && file_exists($logo_path)) {
                    unlink($logo_path);
                }
                $logo_path = uploadClinicLogo($_FILES['logo'], $clinica['codigo_clinica']);
            }
            
            // Dados da clínica
            $clinicaData = [
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
                'modificado_por_id' => $_SESSION['user_id'],
                'data_modificacao' => date('Y-m-d H:i:s')
            ];
            
            // Monta a query de atualização
            $setClause = implode(' = ?, ', array_keys($clinicaData)) . ' = ?';
            $sql = "UPDATE clinicas SET $setClause WHERE id = ?";
            $params = array_merge(array_values($clinicaData), [$clinicaId]);
            
            $updated = $db->execute($sql, $params);
            
            if ($updated) {
                $success = true;
                
                // Processa documentos anexados
                if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'][0])) {
                    uploadClinicDocuments($_FILES['documentos'], $clinicaId);
                }
                
                // Atualiza os dados da clínica para exibição
                $clinica = $db->select("SELECT * FROM clinicas WHERE id = ?", [$clinicaId]);
                $clinica = $clinica[0];
                
            } else {
                $errors[] = 'Erro ao atualizar clínica. Tente novamente.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro interno: ' . $e->getMessage();
            error_log("Erro ao atualizar clínica: " . $e->getMessage());
        }
    }
}

// Busca documentos da clínica
$documentos = $db->select(
    "SELECT * FROM documentos WHERE documentavel_id = ? AND documentavel_type = 'Clinica' ORDER BY data_anexado DESC", 
    [$clinicaId]
);

// Busca médicos da clínica
$medicos = $db->select(
    "SELECT * FROM medicos WHERE clinica_id = ? ORDER BY nome_completo", 
    [$clinicaId]
);

// Busca guias da clínica
$guias = $db->select(
    "SELECT g.*, c.nome_completo as cliente_nome 
     FROM guias g 
     LEFT JOIN clientes c ON g.cliente_id = c.id 
     WHERE g.clinica_id = ? 
     ORDER BY g.data_agendamento DESC 
     LIMIT 10", 
    [$clinicaId]
);

$pageTitle = 'Editar Clínica';
$pageSubtitle = 'Código: ' . $clinica['codigo_clinica'] . ' - ' . $clinica['nome_fantasia'];

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
                        <span class="text-green-700 dark:text-green-300">Clínica atualizada com sucesso!</span>
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
            
            <!-- Informações da Clínica -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <?php if ($clinica['logo_path'] && file_exists($clinica['logo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($clinica['logo_path']); ?>" 
                                     class="w-16 h-16 rounded-lg object-cover border-4 border-gray-200 dark:border-gray-600" 
                                     alt="Logo da clínica">
                            <?php else: ?>
                                <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center border-4 border-gray-200 dark:border-gray-600">
                                    <i data-lucide="building" class="w-8 h-8 text-blue-600 dark:text-blue-400"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($clinica['nome_fantasia']); ?>
                                </h2>
                                <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                    <span>Código: <?php echo htmlspecialchars($clinica['codigo_clinica']); ?></span>
                                    <span>CNPJ: <?php echo formatCNPJ($clinica['cnpj']); ?></span>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 <?php echo $clinica['status'] === 'Ativa' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'; ?> text-xs rounded-full">
                                        <i data-lucide="<?php echo $clinica['status'] === 'Ativa' ? 'check-circle' : 'x-circle'; ?>" class="w-3 h-3"></i>
                                        <?php echo $clinica['status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <?php if ($clinica['telefone1']): ?>
                                <a href="<?php echo getWhatsAppLink($clinica['telefone1']); ?>" 
                                   target="_blank"
                                   class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                                    WhatsApp
                                </a>
                            <?php endif; ?>
                            
                            <button onclick="toggleStatus(<?php echo $clinicaId; ?>, '<?php echo $clinica['status']; ?>')" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                                <i data-lucide="<?php echo $clinica['status'] === 'Ativa' ? 'pause' : 'play'; ?>" class="w-4 h-4"></i>
                                <?php echo $clinica['status'] === 'Ativa' ? 'Desativar' : 'Ativar'; ?>
                            </button>
                            
                            <button onclick="deleteClinica(<?php echo $clinicaId; ?>)" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                                    data-action="delete"
                                    data-item="a clínica <?php echo htmlspecialchars($clinica['nome_fantasia']); ?>">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas Rápidas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Médicos Cadastrados</p>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo count($medicos); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="user-check" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Guias Geradas</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo count($guias); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="file-text" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Documentos</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo count($documentos); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="folder" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Formulário de Edição -->
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
                                     src="<?php echo $clinica['logo_path'] && file_exists($clinica['logo_path']) ? htmlspecialchars($clinica['logo_path']) : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 24 24' fill='none' stroke='%23d1d5db' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 21h18'%3E%3C/path%3E%3Cpath d='M5 21V7l8-4v18'%3E%3C/path%3E%3Cpath d='M19 21V11l-6-4'%3E%3C/path%3E%3C/svg%3E"; ?>" 
                                     class="w-24 h-24 rounded-lg object-cover border-4 border-gray-200 dark:border-gray-600"
                                     alt="Logo da clínica">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Alterar Logo da Clínica
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
                                       value="<?php echo htmlspecialchars($clinica['nome_fantasia']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['razao_social']); ?>"
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
                                       value="<?php echo formatCNPJ($clinica['cnpj']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['inscricao_estadual']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['inscricao_municipal']); ?>"
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
                                       value="<?php echo formatPhone($clinica['telefone1']); ?>"
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
                                       value="<?php echo formatPhone($clinica['telefone2']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['email_contato']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['site']); ?>"
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
                                           value="<?php echo formatCEP($clinica['cep']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['rua']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['numero']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['bairro']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['cidade']); ?>"
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
                                        <option value="<?php echo $uf; ?>" <?php echo $clinica['estado'] === $uf ? 'selected' : ''; ?>>
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
                                       value="<?php echo htmlspecialchars($clinica['forma_atendimento']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['horario_atendimento']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['dias_atendimento']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['nome_contato_financeiro']); ?>"
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
                                       value="<?php echo htmlspecialchars($clinica['email_financeiro']); ?>"
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
                                       value="<?php echo formatPhone($clinica['telefone_financeiro']); ?>"
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
                                                        <?php echo htmlspecialchars($doc['nome_arquivo_original']); ?>
                                                    </span>
                                                </div>
                                                <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                                        class="text-red-500 hover:text-red-700"
                                                        title="Excluir documento">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                <p>Upload: <?php echo formatDateBR($doc['data_anexado'], true); ?></p>
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
                
                <!-- Informações de Auditoria -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informações de Auditoria</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">
                                <strong>Cadastrado em:</strong> <?php echo formatDateBR($clinica['data_cadastro'], true); ?>
                            </p>
                            <?php if ($clinica['cadastrado_por_id']): ?>
                                <?php 
                                $cadastradoPor = $db->select("SELECT nome_completo FROM usuarios WHERE id = ?", [$clinica['cadastrado_por_id']]);
                                if ($cadastradoPor):
                                ?>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        <strong>Cadastrado por:</strong> <?php echo htmlspecialchars($cadastradoPor[0]['nome_completo']); ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($clinica['data_modificacao']): ?>
                                <p class="text-gray-600 dark:text-gray-400">
                                    <strong>Última modificação:</strong> <?php echo formatDateBR($clinica['data_modificacao'], true); ?>
                                </p>
                                <?php if ($clinica['modificado_por_id']): ?>
                                    <?php 
                                    $modificadoPor = $db->select("SELECT nome_completo FROM usuarios WHERE id = ?", [$clinica['modificado_por_id']]);
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
                    <a href="clinicas.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
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
// Validação em tempo real do CNPJ
document.getElementById('cnpj').addEventListener('blur', function() {
    const cnpj = this.value.replace(/\D/g, '');
    
    if (cnpj.length === 14) {
        // Verifica se CNPJ já existe (exceto a própria clínica)
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=validate_cnpj&cnpj=' + cnpj + '&exclude_id=<?php echo $clinicaId; ?>'
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

// Função para alternar status da clínica
function toggleStatus(clinicaId, currentStatus) {
    const newStatus = currentStatus === 'Ativa' ? 'Inativa' : 'Ativa';
    const action = newStatus === 'Ativa' ? 'ativar' : 'desativar';
    
    if (confirm(`Tem certeza que deseja ${action} esta clínica?`)) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_clinica_status&clinica_id=${clinicaId}&new_status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Clínica ${action === 'ativar' ? 'ativada' : 'desativada'} com sucesso!`, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('Erro ao alterar status: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Erro interno. Tente novamente.', 'error');
        });
    }
}

// Função para excluir clínica
function deleteClinica(clinicaId) {
    if (confirm('Tem certeza que deseja excluir esta clínica? Esta ação não pode ser desfeita e pode afetar guias existentes.')) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_clinica&clinica_id=' + clinicaId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Clínica excluída com sucesso!', 'success');
                setTimeout(() => {
                    window.location.href = 'clinicas.php';
                }, 1500);
            } else {
                showToast('Erro ao excluir clínica: ' + data.message, 'error');
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
