<?php
// Página de Edição de Guia - Salvar como guia_editar.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();
$errors = [];
$success = false;

// Verifica se foi passado um ID
$guiaId = intval($_GET['id'] ?? 0);
if ($guiaId <= 0) {
    header('Location: guias.php');
    exit;
}

// Busca a guia
$guia = $db->select("
    SELECT g.*, 
           c.nome_completo as cliente_nome,
           cl.nome_fantasia as clinica_nome,
           e.nome as exame_nome,
           m.nome_completo as medico_nome,
           u1.nome_completo as cadastrado_por_nome,
           u2.nome_completo as modificado_por_nome
    FROM guias g
    LEFT JOIN clientes c ON g.cliente_id = c.id
    LEFT JOIN clinicas cl ON g.clinica_id = cl.id
    LEFT JOIN exames e ON g.exame_id = e.id
    LEFT JOIN medicos m ON g.medico_id = m.id
    LEFT JOIN usuarios u1 ON g.cadastrado_por_id = u1.id
    LEFT JOIN usuarios u2 ON g.modificado_por_id = u2.id
    WHERE g.id = ?
", [$guiaId]);

if (empty($guia)) {
    header('Location: guias.php');
    exit;
}

$guia = $guia[0];

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação dos campos obrigatórios
    $cliente_id = intval($_POST['cliente_id'] ?? 0);
    $clinica_id = intval($_POST['clinica_id'] ?? 0);
    $tipo_servico = $_POST['tipo_servico'] ?? '';
    $data_agendamento = $_POST['data_agendamento'] ?? '';
    $valor_exame = floatval($_POST['valor_exame'] ?? 0);
    $valor_repasse = floatval($_POST['valor_repasse'] ?? 0);
    
    // Validações
    if ($cliente_id <= 0) {
        $errors[] = 'Cliente é obrigatório';
    }
    
    if ($clinica_id <= 0) {
        $errors[] = 'Clínica é obrigatória';
    }
    
    if (empty($tipo_servico)) {
        $errors[] = 'Tipo de serviço é obrigatório';
    }
    
    if (empty($data_agendamento)) {
        $errors[] = 'Data de agendamento é obrigatória';
    }
    
    if ($valor_exame <= 0) {
        $errors[] = 'Valor do serviço deve ser maior que zero';
    }
    
    if ($valor_repasse < 0) {
        $errors[] = 'Valor do repasse não pode ser negativo';
    }
    
    if ($valor_repasse > $valor_exame) {
        $errors[] = 'Valor do repasse não pode ser maior que o valor do serviço';
    }
    
    // Validações específicas por tipo de serviço
    if ($tipo_servico === 'Exame') {
        $exame_id = intval($_POST['exame_id'] ?? 0);
        if ($exame_id <= 0) {
            $errors[] = 'Exame é obrigatório para serviços do tipo Exame';
        }
    } elseif ($tipo_servico === 'Consulta') {
        $medico_id = intval($_POST['medico_id'] ?? 0);
        if ($medico_id <= 0) {
            $errors[] = 'Médico é obrigatório para serviços do tipo Consulta';
        }
    }
    
    // Se não há erros, processa a atualização
    if (empty($errors)) {
        try {
            // Calcula percentual de repasse
            $percentual_repasse = $valor_exame > 0 ? ($valor_repasse / $valor_exame) * 100 : 0;
            
            // Dados da guia
            $guiaData = [
                'cliente_id' => $cliente_id,
                'clinica_id' => $clinica_id,
                'tipo_servico' => $tipo_servico,
                'exame_id' => $tipo_servico === 'Exame' ? intval($_POST['exame_id'] ?? 0) : null,
                'medico_id' => $tipo_servico === 'Consulta' ? intval($_POST['medico_id'] ?? 0) : null,
                'data_agendamento' => $data_agendamento,
                'hora_agendamento' => !empty($_POST['hora_agendamento']) ? $_POST['hora_agendamento'] : null,
                'valor_exame' => $valor_exame,
                'valor_repasse' => $valor_repasse,
                'percentual_repasse' => $percentual_repasse,
                'status' => $_POST['status'] ?? 'Agendado',
                'forma_pagamento' => $_POST['forma_pagamento'] ?? 'Dinheiro',
                'pago' => isset($_POST['pago']) ? 1 : 0,
                'data_pagamento' => !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null,
                'repasse_pago' => isset($_POST['repasse_pago']) ? 1 : 0,
                'data_repasse' => !empty($_POST['data_repasse']) ? $_POST['data_repasse'] : null,
                'observacoes' => trim($_POST['observacoes'] ?? ''),
                'modificado_por_id' => $_SESSION['user_id'],
                'data_modificacao' => date('Y-m-d H:i:s')
            ];
            
            // Remove campos nulos
            $guiaData = array_filter($guiaData, function($value) {
                return $value !== null;
            });
            
            // Monta a query de atualização
            $setParts = [];
            $values = [];
            foreach ($guiaData as $column => $value) {
                $setParts[] = "$column = ?";
                $values[] = $value;
            }
            $values[] = $guiaId; // Para o WHERE
            
            $sql = "UPDATE guias SET " . implode(', ', $setParts) . " WHERE id = ?";
            
            $updated = $db->execute($sql, $values);
            
            if ($updated) {
                $success = true;
                
                // Atualiza os dados da guia para exibição
                $guia = array_merge($guia, $guiaData);
                
                // Redireciona após sucesso
                header("Location: guia_editar.php?id=$guiaId&success=1");
                exit;
                
            } else {
                $errors[] = 'Erro ao atualizar guia. Tente novamente.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro interno: ' . $e->getMessage();
            error_log("Erro ao atualizar guia: " . $e->getMessage());
        }
    }
}

// Verifica se há mensagem de sucesso
if (isset($_GET['success'])) {
    $success = true;
}

// Busca dados para os selects
$clientes = $db->select("SELECT id, codigo_cliente, nome_completo FROM clientes ORDER BY nome_completo");
$clinicas = $db->select("SELECT id, codigo_clinica, nome_fantasia FROM clinicas WHERE status = 'Ativa' ORDER BY nome_fantasia");
$exames = $db->select("SELECT id, codigo_exame, nome, valor_padrao FROM exames WHERE ativo = 1 ORDER BY categoria, nome");
$medicos = $db->select("SELECT m.id, m.codigo_medico, m.nome_completo, m.especialidade, m.valor_consulta, c.nome_fantasia as clinica_nome FROM medicos m LEFT JOIN clinicas c ON m.clinica_id = c.id WHERE m.ativo = 1 ORDER BY m.nome_completo");

// Busca histórico de transações financeiras relacionadas
$transacoes = $db->select("
    SELECT tf.*, u.nome_completo as cadastrado_por_nome
    FROM transacoes_financeiras tf
    LEFT JOIN usuarios u ON tf.cadastrado_por_id = u.id
    WHERE tf.guia_id = ?
    ORDER BY tf.data_cadastro DESC
", [$guiaId]);

$pageTitle = 'Editar Guia';
$pageSubtitle = 'Editar guia de encaminhamento';

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
                            <a href="guias.php" class="ml-1 text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Guias</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Editar Guia</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Mensagens -->
            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700 dark:text-green-300">Guia atualizada com sucesso!</span>
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
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Editar Guia <?php echo htmlspecialchars($guia['numero_guia']); ?>
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Cliente: <?php echo htmlspecialchars($guia['cliente_nome']); ?> | 
                        Clínica: <?php echo htmlspecialchars($guia['clinica_nome']); ?>
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <a href="guia_visualizar.php?id=<?php echo $guiaId; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        Visualizar
                    </a>
                    
                    <a href="guia_visualizar.php?id=<?php echo $guiaId; ?>&print=1" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Imprimir
                    </a>
                </div>
            </div>
            
            <!-- Informações de Auditoria -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                <div class="flex items-start gap-3">
                    <i data-lucide="info" class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0"></i>
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <p><strong>Cadastrado por:</strong> <?php echo htmlspecialchars($guia['cadastrado_por_nome'] ?? 'Sistema'); ?> em <?php echo formatDateTimeBR($guia['data_cadastro']); ?></p>
                        <?php if ($guia['modificado_por_nome']): ?>
                            <p><strong>Última modificação:</strong> <?php echo htmlspecialchars($guia['modificado_por_nome']); ?> em <?php echo formatDateTimeBR($guia['data_modificacao']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Formulário Principal -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <form method="POST" id="guia-form">
                        
                        <!-- Informações Básicas -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="user" class="w-5 h-5"></i>
                                    Informações Básicas
                                </h3>
                            </div>
                            <div class="p-6 space-y-6">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Cliente -->
                                    <div>
                                        <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Cliente <span class="text-red-500">*</span>
                                        </label>
                                        <select id="cliente_id" name="cliente_id" required class="input-destacado w-full">
                                            <option value="">Selecione o cliente...</option>
                                            <?php foreach ($clientes as $cliente): ?>
                                                <option value="<?php echo $cliente['id']; ?>" 
                                                        <?php echo ($guia['cliente_id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cliente['nome_completo']); ?> (<?php echo htmlspecialchars($cliente['codigo_cliente']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Clínica -->
                                    <div>
                                        <label for="clinica_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Clínica <span class="text-red-500">*</span>
                                        </label>
                                        <select id="clinica_id" name="clinica_id" required class="input-destacado w-full">
                                            <option value="">Selecione a clínica...</option>
                                            <?php foreach ($clinicas as $clinica): ?>
                                                <option value="<?php echo $clinica['id']; ?>" 
                                                        <?php echo ($guia['clinica_id'] == $clinica['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($clinica['nome_fantasia']); ?> (<?php echo htmlspecialchars($clinica['codigo_clinica']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Tipo de Serviço -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                        Tipo de Serviço <span class="text-red-500">*</span>
                                    </label>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <label class="relative flex items-center p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-900/20">
                                            <input type="radio" name="tipo_servico" value="Exame" 
                                                   <?php echo ($guia['tipo_servico'] === 'Exame') ? 'checked' : ''; ?>
                                                   class="sr-only" onchange="toggleServicoType()">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                                    <i data-lucide="clipboard-list" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-white">Exame</p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">Procedimentos diagnósticos</p>
                                                </div>
                                            </div>
                                        </label>
                                        
                                        <label class="relative flex items-center p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 dark:has-[:checked]:bg-green-900/20">
                                            <input type="radio" name="tipo_servico" value="Consulta" 
                                                   <?php echo ($guia['tipo_servico'] === 'Consulta') ? 'checked' : ''; ?>
                                                   class="sr-only" onchange="toggleServicoType()">
                                            <div class="flex items-center gap-3">
                                                <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                                                    <i data-lucide="user-check" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-white">Consulta</p>
                                                    <p class="text-sm text-gray-600 dark:text-gray-400">Consultas médicas</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        
                        <!-- Seleção de Exame -->
                        <div id="exame-section" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700" style="display: <?php echo ($guia['tipo_servico'] === 'Exame') ? 'block' : 'none'; ?>;">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                                    Seleção de Exame
                                </h3>
                            </div>
                            <div class="p-6">
                                <div>
                                    <label for="exame_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Exame <span class="text-red-500">*</span>
                                    </label>
                                    <select id="exame_id" name="exame_id" class="input-destacado w-full">
                                        <option value="">Selecione o exame...</option>
                                        <?php 
                                        $currentCategory = '';
                                        foreach ($exames as $exame): 
                                            if ($exame['categoria'] !== $currentCategory) {
                                                if ($currentCategory !== '') echo '</optgroup>';
                                                echo '<optgroup label="' . htmlspecialchars($exame['categoria'] ?: 'Sem categoria') . '">';
                                                $currentCategory = $exame['categoria'];
                                            }
                                        ?>
                                            <option value="<?php echo $exame['id']; ?>" 
                                                    <?php echo ($guia['exame_id'] == $exame['id']) ? 'selected' : ''; ?>
                                                    data-valor="<?php echo $exame['valor_padrao']; ?>">
                                                <?php echo htmlspecialchars($exame['nome']); ?> - R$ <?php echo number_format($exame['valor_padrao'], 2, ',', '.'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if ($currentCategory !== '') echo '</optgroup>'; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Seleção de Médico -->
                        <div id="medico-section" class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700" style="display: <?php echo ($guia['tipo_servico'] === 'Consulta') ? 'block' : 'none'; ?>;">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="user-check" class="w-5 h-5"></i>
                                    Seleção de Médico
                                </h3>
                            </div>
                            <div class="p-6">
                                <div>
                                    <label for="medico_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Médico <span class="text-red-500">*</span>
                                    </label>
                                    <select id="medico_id" name="medico_id" class="input-destacado w-full">
                                        <option value="">Selecione o médico...</option>
                                        <?php foreach ($medicos as $medico): ?>
                                            <option value="<?php echo $medico['id']; ?>" 
                                                    <?php echo ($guia['medico_id'] == $medico['id']) ? 'selected' : ''; ?>
                                                    data-valor="<?php echo $medico['valor_consulta']; ?>">
                                                Dr. <?php echo htmlspecialchars($medico['nome_completo']); ?> 
                                                <?php if ($medico['especialidade']): ?>
                                                    - <?php echo htmlspecialchars($medico['especialidade']); ?>
                                                <?php endif; ?>
                                                - R$ <?php echo number_format($medico['valor_consulta'], 2, ',', '.'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Agendamento -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-5 h-5"></i>
                                    Agendamento
                                </h3>
                            </div>
                            <div class="p-6 space-y-6">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Data -->
                                    <div>
                                        <label for="data_agendamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Data do Agendamento <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" 
                                               id="data_agendamento" 
                                               name="data_agendamento" 
                                               required
                                               value="<?php echo htmlspecialchars($guia['data_agendamento']); ?>"
                                               class="input-destacado w-full">
                                    </div>
                                    
                                    <!-- Hora -->
                                    <div>
                                        <label for="hora_agendamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Hora do Agendamento
                                        </label>
                                        <input type="time" 
                                               id="hora_agendamento" 
                                               name="hora_agendamento" 
                                               value="<?php echo htmlspecialchars($guia['hora_agendamento'] ?? ''); ?>"
                                               class="input-destacado w-full">
                                    </div>
                                </div>
                                
                                <!-- Status -->
                                <div>
                                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Status da Guia
                                    </label>
                                    <select id="status" name="status" class="input-destacado w-full">
                                        <option value="Agendado" <?php echo ($guia['status'] === 'Agendado') ? 'selected' : ''; ?>>Agendado</option>
                                        <option value="Confirmado" <?php echo ($guia['status'] === 'Confirmado') ? 'selected' : ''; ?>>Confirmado</option>
                                        <option value="Realizado" <?php echo ($guia['status'] === 'Realizado') ? 'selected' : ''; ?>>Realizado</option>
                                        <option value="Cancelado" <?php echo ($guia['status'] === 'Cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                                        <option value="Faltou" <?php echo ($guia['status'] === 'Faltou') ? 'selected' : ''; ?>>Faltou</option>
                                    </select>
                                </div>
                                
                            </div>
                        </div>
                        
                        <!-- Valores -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                                    Valores Financeiros
                                </h3>
                            </div>
                            <div class="p-6 space-y-6">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- Valor do Serviço -->
                                    <div>
                                        <label for="valor_exame" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Valor do Serviço (R$) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400">R$</span>
                                            <input type="number" 
                                                   id="valor_exame" 
                                                   name="valor_exame" 
                                                   required
                                                   min="0.01"
                                                   step="0.01"
                                                   value="<?php echo number_format($guia['valor_exame'], 2, '.', ''); ?>"
                                                   class="input-destacado w-full pl-10"
                                                   onchange="calculateRepasse()">
                                        </div>
                                    </div>
                                    
                                    <!-- Valor do Repasse -->
                                    <div>
                                        <label for="valor_repasse" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Valor do Repasse (R$) <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400">R$</span>
                                            <input type="number" 
                                                   id="valor_repasse" 
                                                   name="valor_repasse" 
                                                   required
                                                   min="0"
                                                   step="0.01"
                                                   value="<?php echo number_format($guia['valor_repasse'], 2, '.', ''); ?>"
                                                   class="input-destacado w-full pl-10"
                                                   onchange="calculatePercentual()">
                                        </div>
                                    </div>
                                    
                                    <!-- Percentual -->
                                    <div>
                                        <label for="percentual_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Percentual de Repasse
                                        </label>
                                        <div class="relative">
                                            <input type="number" 
                                                   id="percentual_display" 
                                                   min="0"
                                                   max="100"
                                                   step="0.01"
                                                   value="<?php echo number_format($guia['percentual_repasse'], 2, '.', ''); ?>"
                                                   class="input-destacado w-full pr-10"
                                                   onchange="calculateRepasseFromPercentual()">
                                            <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400">%</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Resumo Financeiro -->
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">Resumo Financeiro</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Valor Total:</span>
                                            <span id="resumo-total" class="font-semibold text-blue-600 dark:text-blue-400 ml-2">R$ <?php echo number_format($guia['valor_exame'], 2, ',', '.'); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Repasse:</span>
                                            <span id="resumo-repasse" class="font-semibold text-green-600 dark:text-green-400 ml-2">R$ <?php echo number_format($guia['valor_repasse'], 2, ',', '.'); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Lucro:</span>
                                            <span id="resumo-lucro" class="font-semibold text-purple-600 dark:text-purple-400 ml-2">R$ <?php echo number_format($guia['valor_exame'] - $guia['valor_repasse'], 2, ',', '.'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        
                        <!-- Pagamento -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="credit-card" class="w-5 h-5"></i>
                                    Controle de Pagamento
                                </h3>
                            </div>
                            <div class="p-6 space-y-6">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Forma de Pagamento -->
                                    <div>
                                        <label for="forma_pagamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Forma de Pagamento
                                        </label>
                                        <select id="forma_pagamento" name="forma_pagamento" class="input-destacado w-full">
                                            <option value="Dinheiro" <?php echo ($guia['forma_pagamento'] === 'Dinheiro') ? 'selected' : ''; ?>>Dinheiro</option>
                                            <option value="PIX" <?php echo ($guia['forma_pagamento'] === 'PIX') ? 'selected' : ''; ?>>PIX</option>
                                            <option value="Cartão Débito" <?php echo ($guia['forma_pagamento'] === 'Cartão Débito') ? 'selected' : ''; ?>>Cartão de Débito</option>
                                            <option value="Cartão Crédito" <?php echo ($guia['forma_pagamento'] === 'Cartão Crédito') ? 'selected' : ''; ?>>Cartão de Crédito</option>
                                            <option value="Transferência" <?php echo ($guia['forma_pagamento'] === 'Transferência') ? 'selected' : ''; ?>>Transferência Bancária</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Data de Pagamento -->
                                    <div>
                                        <label for="data_pagamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Data do Pagamento
                                        </label>
                                        <input type="date" 
                                               id="data_pagamento" 
                                               name="data_pagamento" 
                                               value="<?php echo htmlspecialchars($guia['data_pagamento'] ?? ''); ?>"
                                               class="input-destacado w-full">
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Status Pagamento -->
                                    <div>
                                        <label class="flex items-center gap-3 p-4 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <input type="checkbox" 
                                                   name="pago" 
                                                   <?php echo $guia['pago'] ? 'checked' : ''; ?>
                                                   class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">Pagamento Recebido</p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">Marque se o pagamento foi recebido</p>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <!-- Data de Repasse -->
                                    <div>
                                        <label for="data_repasse" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Data do Repasse
                                        </label>
                                        <input type="date" 
                                               id="data_repasse" 
                                               name="data_repasse" 
                                               value="<?php echo htmlspecialchars($guia['data_repasse'] ?? ''); ?>"
                                               class="input-destacado w-full">
                                    </div>
                                </div>
                                
                                <!-- Status Repasse -->
                                <div>
                                    <label class="flex items-center gap-3 p-4 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <input type="checkbox" 
                                               name="repasse_pago" 
                                               <?php echo $guia['repasse_pago'] ? 'checked' : ''; ?>
                                               class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 dark:focus:ring-green-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">Repasse Efetuado</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Marque se o repasse foi efetuado para a clínica</p>
                                        </div>
                                    </label>
                                </div>
                                
                            </div>
                        </div>
                        
                        <!-- Observações -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <i data-lucide="message-square" class="w-5 h-5"></i>
                                    Observações
                                </h3>
                            </div>
                            <div class="p-6">
                                <div>
                                    <label for="observacoes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Observações Adicionais
                                    </label>
                                    <textarea id="observacoes" 
                                              name="observacoes" 
                                              rows="4"
                                              class="input-destacado w-full"
                                              placeholder="Informações adicionais sobre a guia..."><?php echo htmlspecialchars($guia['observacoes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botões de Ação -->
                        <div class="flex items-center justify-between">
                            <a href="guias.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                                Voltar
                            </a>
                            
                            <div class="flex gap-3">
                                <button type="button" onclick="deleteGuia(<?php echo $guiaId; ?>)" class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                    <i data-lucide="trash-2" class="w-4 h-4 inline mr-2"></i>
                                    Excluir Guia
                                </button>
                                <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    <i data-lucide="save" class="w-4 h-4"></i>
                                    Salvar Alterações
                                </button>
                            </div>
                        </div>
                        
                    </form>
                    
                </div>
                
                <!-- Sidebar com Informações Adicionais -->
                <div class="space-y-6">
                    
                    <!-- Status da Guia -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Status da Guia</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <?php 
                            $statusColors = [
                                'Agendado' => 'blue',
                                'Confirmado' => 'green',
                                'Realizado' => 'purple',
                                'Cancelado' => 'red',
                                'Faltou' => 'yellow'
                            ];
                            $color = $statusColors[$guia['status']] ?? 'gray';
                            ?>
                            <div class="text-center">
                                <span class="inline-flex items-center gap-2 px-4 py-2 bg-<?php echo $color; ?>-100 dark:bg-<?php echo $color; ?>-900 text-<?php echo $color; ?>-800 dark:text-<?php echo $color; ?>-200 text-lg font-semibold rounded-full">
                                    <i data-lucide="<?php echo $guia['status'] === 'Realizado' ? 'check-circle' : ($guia['status'] === 'Cancelado' ? 'x-circle' : 'clock'); ?>" class="w-5 h-5"></i>
                                    <?php echo $guia['status']; ?>
                                </span>
                            </div>
                            
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Número:</span>
                                    <span class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['numero_guia']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Tipo:</span>
                                    <span class="font-medium text-gray-900 dark:text-white"><?php echo $guia['tipo_servico']; ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Data:</span>
                                    <span class="font-medium text-gray-900 dark:text-white"><?php echo formatDateBR($guia['data_agendamento']); ?></span>
                                </div>
                                <?php if ($guia['hora_agendamento']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Hora:</span>
                                        <span class="font-medium text-gray-900 dark:text-white"><?php echo date('H:i', strtotime($guia['hora_agendamento'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Resumo Financeiro -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Resumo Financeiro</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">Valor Total:</span>
                                <span class="text-xl font-bold text-blue-600 dark:text-blue-400">R$ <?php echo number_format($guia['valor_exame'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">Repasse:</span>
                                <span class="text-lg font-semibold text-green-600 dark:text-green-400">R$ <?php echo number_format($guia['valor_repasse'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600 dark:text-gray-400">Lucro:</span>
                                <span class="text-lg font-semibold text-purple-600 dark:text-purple-400">R$ <?php echo number_format($guia['valor_exame'] - $guia['valor_repasse'], 2, ',', '.'); ?></span>
                            </div>
                            
                            <hr class="dark:border-gray-600">
                            
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <?php if ($guia['pago']): ?>
                                        <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                        <span class="text-green-600 dark:text-green-400">Pagamento Recebido</span>
                                    <?php else: ?>
                                        <i data-lucide="clock" class="w-4 h-4 text-red-500"></i>
                                        <span class="text-red-600 dark:text-red-400">Pagamento Pendente</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <?php if ($guia['repasse_pago']): ?>
                                        <i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>
                                        <span class="text-green-600 dark:text-green-400">Repasse Efetuado</span>
                                    <?php else: ?>
                                        <i data-lucide="clock" class="w-4 h-4 text-yellow-500"></i>
                                        <span class="text-yellow-600 dark:text-yellow-400">Repasse Pendente</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Histórico de Transações -->
                    <?php if (!empty($transacoes)): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                            <div class="p-6 border-b dark:border-gray-700">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transações Financeiras</h3>
                            </div>
                            <div class="p-6">
                                <div class="space-y-3">
                                    <?php foreach ($transacoes as $transacao): ?>
                                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($transacao['descricao']); ?></p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <?php echo formatDateBR($transacao['data_transacao']); ?> - 
                                                    <?php echo htmlspecialchars($transacao['cadastrado_por_nome']); ?>
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-semibold <?php echo $transacao['tipo'] === 'Recebimento' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                                                    <?php echo $transacao['tipo'] === 'Recebimento' ? '+' : '-'; ?>R$ <?php echo number_format($transacao['valor'], 2, ',', '.'); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo $transacao['tipo']; ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                </div>
                
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
// Função para alternar entre tipos de serviço
function toggleServicoType() {
    const tipoServico = document.querySelector('input[name="tipo_servico"]:checked')?.value;
    const exameSection = document.getElementById('exame-section');
    const medicoSection = document.getElementById('medico-section');
    
    if (tipoServico === 'Exame') {
        exameSection.style.display = 'block';
        medicoSection.style.display = 'none';
        document.getElementById('medico_id').value = '';
    } else if (tipoServico === 'Consulta') {
        exameSection.style.display = 'none';
        medicoSection.style.display = 'block';
        document.getElementById('exame_id').value = '';
    }
}

// Função para calcular repasse baseado no percentual
function calculateRepasse() {
    const valorExame = parseFloat(document.getElementById('valor_exame').value) || 0;
    const percentual = parseFloat(document.getElementById('percentual_display').value) || 0;
    
    if (valorExame > 0 && percentual > 0) {
        const valorRepasse = (valorExame * percentual) / 100;
        document.getElementById('valor_repasse').value = valorRepasse.toFixed(2);
    }
    
    updateResumo();
}

// Função para calcular percentual baseado no repasse
function calculatePercentual() {
    const valorExame = parseFloat(document.getElementById('valor_exame').value) || 0;
    const valorRepasse = parseFloat(document.getElementById('valor_repasse').value) || 0;
    
    if (valorExame > 0) {
        const percentual = (valorRepasse / valorExame) * 100;
        document.getElementById('percentual_display').value = percentual.toFixed(2);
    }
    
    updateResumo();
}

// Função para calcular repasse baseado no percentual digitado
function calculateRepasseFromPercentual() {
    const valorExame = parseFloat(document.getElementById('valor_exame').value) || 0;
    const percentual = parseFloat(document.getElementById('percentual_display').value) || 0;
    
    if (valorExame > 0 && percentual >= 0 && percentual <= 100) {
        const valorRepasse = (valorExame * percentual) / 100;
        document.getElementById('valor_repasse').value = valorRepasse.toFixed(2);
    }
    
    updateResumo();
}

// Função para atualizar o resumo financeiro
function updateResumo() {
    const valorExame = parseFloat(document.getElementById('valor_exame').value) || 0;
    const valorRepasse = parseFloat(document.getElementById('valor_repasse').value) || 0;
    const lucro = valorExame - valorRepasse;
    
    document.getElementById('resumo-total').textContent = 'R$ ' + valorExame.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('resumo-repasse').textContent = 'R$ ' + valorRepasse.toLocaleString('pt-BR', {minimumFractionDigits: 2});
    document.getElementById('resumo-lucro').textContent = 'R$ ' + lucro.toLocaleString('pt-BR', {minimumFractionDigits: 2});
}

// Função para excluir guia
function deleteGuia(guiaId) {
    if (confirm('Tem certeza que deseja excluir esta guia? Esta ação não pode ser desfeita e pode afetar o controle financeiro.')) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_guia&guia_id=' + guiaId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Guia excluída com sucesso!', 'success');
                setTimeout(() => {
                    window.location.href = 'guias.php';
                }, 1500);
            } else {
                showToast('Erro ao excluir guia: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Erro interno. Tente novamente.', 'error');
        });
    }
}

// Validação antes do envio
document.getElementById('guia-form').addEventListener('submit', function(e) {
    const tipoServico = document.querySelector('input[name="tipo_servico"]:checked')?.value;
    
    if (tipoServico === 'Exame') {
        const exameId = document.getElementById('exame_id').value;
        if (!exameId) {
            e.preventDefault();
            showToast('Selecione um exame para continuar', 'error');
            return;
        }
    } else if (tipoServico === 'Consulta') {
        const medicoId = document.getElementById('medico_id').value;
        if (!medicoId) {
            e.preventDefault();
            showToast('Selecione um médico para continuar', 'error');
            return;
        }
    }
    
    const valorExame = parseFloat(document.getElementById('valor_exame').value) || 0;
    const valorRepasse = parseFloat(document.getElementById('valor_repasse').value) || 0;
    
    if (valorRepasse > valorExame) {
        e.preventDefault();
        showToast('O valor do repasse não pode ser maior que o valor do serviço', 'error');
        return;
    }
    
    // Animação de loading no botão
    const button = this.querySelector('button[type="submit"]');
    const originalContent = button.innerHTML;
    
    button.innerHTML = `
        <div class="flex items-center gap-2">
            <div class="spinner"></div>
            Salvando...
        </div>
    `;
    button.disabled = true;
});

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Atualiza resumo inicial
    updateResumo();
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + S = Salvar
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        document.getElementById('guia-form').submit();
    }
    
    // Esc = Voltar
    if (e.key === 'Escape') {
        window.location.href = 'guias.php';
    }
});
</script>

<?php include 'templates/footer.php'; ?>
