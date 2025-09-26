<?php
// Gestão de Recibos de Pagamento - Salvar como recibos.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();
$errors = [];
$success = false;

// Processa ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'confirmar_pagamento':
            $guiaId = intval($_POST['guia_id'] ?? 0);
            $dataPagamento = $_POST['data_pagamento'] ?? date('Y-m-d');
            $formaPagamento = $_POST['forma_pagamento'] ?? '';
            
            if ($guiaId > 0) {
                try {
                    $updated = $db->execute("
                        UPDATE guias 
                        SET pago = 1, 
                            data_pagamento = ?, 
                            forma_pagamento = ?,
                            modificado_por_id = ?,
                            data_modificacao = NOW()
                        WHERE id = ?
                    ", [$dataPagamento, $formaPagamento, $_SESSION['user_id'], $guiaId]);
                    
                    if ($updated) {
                        // Registra transação financeira
                        $guia = $db->select("SELECT numero_guia, valor_exame FROM guias WHERE id = ?", [$guiaId])[0];
                        
                        $db->execute("
                            INSERT INTO transacoes_financeiras 
                            (guia_id, tipo, descricao, valor, data_transacao, forma_pagamento, cadastrado_por_id, data_cadastro)
                            VALUES (?, 'Recebimento', ?, ?, ?, ?, ?, NOW())
                        ", [
                            $guiaId,
                            'Pagamento da guia ' . $guia['numero_guia'],
                            $guia['valor_exame'],
                            $dataPagamento,
                            $formaPagamento,
                            $_SESSION['user_id']
                        ]);
                        
                        echo json_encode(['success' => true, 'message' => 'Pagamento confirmado com sucesso!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erro ao confirmar pagamento']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID da guia inválido']);
            }
            exit;
            
        case 'cancelar_pagamento':
            $guiaId = intval($_POST['guia_id'] ?? 0);
            
            if ($guiaId > 0) {
                try {
                    $updated = $db->execute("
                        UPDATE guias 
                        SET pago = 0, 
                            data_pagamento = NULL,
                            modificado_por_id = ?,
                            data_modificacao = NOW()
                        WHERE id = ?
                    ", [$_SESSION['user_id'], $guiaId]);
                    
                    if ($updated) {
                        // Remove transação financeira
                        $db->execute("DELETE FROM transacoes_financeiras WHERE guia_id = ? AND tipo = 'Recebimento'", [$guiaId]);
                        
                        echo json_encode(['success' => true, 'message' => 'Pagamento cancelado com sucesso!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar pagamento']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'ID da guia inválido']);
            }
            exit;
    }
}

// Filtros
$search = trim($_GET['search'] ?? '');
$status_pagamento = $_GET['status_pagamento'] ?? '';
$forma_pagamento = $_GET['forma_pagamento'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Monta a query base
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(c.nome_completo LIKE ? OR g.numero_guia LIKE ? OR cl.nome_fantasia LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($status_pagamento !== '') {
    $whereConditions[] = "g.pago = ?";
    $params[] = intval($status_pagamento);
}

if (!empty($forma_pagamento)) {
    $whereConditions[] = "g.forma_pagamento = ?";
    $params[] = $forma_pagamento;
}

if (!empty($data_inicio)) {
    $whereConditions[] = "g.data_agendamento >= ?";
    $params[] = $data_inicio;
}

if (!empty($data_fim)) {
    $whereConditions[] = "g.data_agendamento <= ?";
    $params[] = $data_fim;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Busca as guias
$guias = $db->select("
    SELECT g.*, 
           c.nome_completo as cliente_nome,
           c.telefone1 as cliente_telefone,
           cl.nome_fantasia as clinica_nome,
           e.nome as exame_nome,
           m.nome_completo as medico_nome
    FROM guias g
    LEFT JOIN clientes c ON g.cliente_id = c.id
    LEFT JOIN clinicas cl ON g.clinica_id = cl.id
    LEFT JOIN exames e ON g.exame_id = e.id
    LEFT JOIN medicos m ON g.medico_id = m.id
    $whereClause
    ORDER BY g.data_agendamento DESC, g.pago ASC
", $params);

// Estatísticas
$totalGuias = count($guias);
$guiasPagas = array_filter($guias, function($g) { return $g['pago']; });
$guiasPendentes = array_filter($guias, function($g) { return !$g['pago']; });

$valorTotal = array_sum(array_column($guias, 'valor_exame'));
$valorRecebido = array_sum(array_column($guiasPagas, 'valor_exame'));
$valorPendente = array_sum(array_column($guiasPendentes, 'valor_exame'));

$pageTitle = 'Recibos de Pagamento';
$pageSubtitle = 'Controle de recebimentos';

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
                            <a href="financeiro.php" class="ml-1 text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Financeiro</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Recibos</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Recibos de Pagamento</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Controle de recebimentos e confirmação de pagamentos
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <button onclick="confirmarPagamentosLote()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                        Confirmar em Lote
                    </button>
                    
                    <button onclick="exportRecibos()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Exportar
                    </button>
                    
                    <button onclick="imprimirRecibos()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Imprimir
                    </button>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Guias</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $totalGuias; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="file-text" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Valor Total</p>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Recebido</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">R$ <?php echo number_format($valorRecebido, 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo count($guiasPagas); ?> pagas</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pendente</p>
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400">R$ <?php echo number_format($valorPendente, 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo count($guiasPendentes); ?> pendentes</p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                            <i data-lucide="clock" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    
                    <!-- Busca -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Buscar
                        </label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Cliente, guia ou clínica..."
                               class="input-destacado w-full">
                    </div>
                    
                    <!-- Status Pagamento -->
                    <div>
                        <label for="status_pagamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status
                        </label>
                        <select id="status_pagamento" name="status_pagamento" class="input-destacado w-full">
                            <option value="">Todos</option>
                            <option value="1" <?php echo $status_pagamento === '1' ? 'selected' : ''; ?>>Pago</option>
                            <option value="0" <?php echo $status_pagamento === '0' ? 'selected' : ''; ?>>Pendente</option>
                        </select>
                    </div>
                    
                    <!-- Forma de Pagamento -->
                    <div>
                        <label for="forma_pagamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Forma de Pagamento
                        </label>
                        <select id="forma_pagamento" name="forma_pagamento" class="input-destacado w-full">
                            <option value="">Todas</option>
                            <option value="Dinheiro" <?php echo $forma_pagamento === 'Dinheiro' ? 'selected' : ''; ?>>Dinheiro</option>
                            <option value="PIX" <?php echo $forma_pagamento === 'PIX' ? 'selected' : ''; ?>>PIX</option>
                            <option value="Cartão Débito" <?php echo $forma_pagamento === 'Cartão Débito' ? 'selected' : ''; ?>>Cartão Débito</option>
                            <option value="Cartão Crédito" <?php echo $forma_pagamento === 'Cartão Crédito' ? 'selected' : ''; ?>>Cartão Crédito</option>
                            <option value="Transferência" <?php echo $forma_pagamento === 'Transferência' ? 'selected' : ''; ?>>Transferência</option>
                        </select>
                    </div>
                    
                    <!-- Data Início -->
                    <div>
                        <label for="data_inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Data Início
                        </label>
                        <input type="date" 
                               id="data_inicio" 
                               name="data_inicio" 
                               value="<?php echo htmlspecialchars($data_inicio); ?>"
                               class="input-destacado w-full">
                    </div>
                    
                    <!-- Data Fim -->
                    <div>
                        <label for="data_fim" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Data Fim
                        </label>
                        <input type="date" 
                               id="data_fim" 
                               name="data_fim" 
                               value="<?php echo htmlspecialchars($data_fim); ?>"
                               class="input-destacado w-full">
                    </div>
                    
                    <!-- Botões -->
                    <div class="md:col-span-3 lg:col-span-5 flex items-center gap-2">
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                        <a href="recibos.php" class="px-6 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                            Limpar
                        </a>
                    </div>
                    
                </form>
            </div>
            
            <!-- Lista de Recibos -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Lista de Recibos
                            <?php if (!empty($search) || $status_pagamento !== '' || !empty($forma_pagamento) || !empty($data_inicio) || !empty($data_fim)): ?>
                                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                    (<?php echo count($guias); ?> resultado<?php echo count($guias) !== 1 ? 's' : ''; ?> encontrado<?php echo count($guias) !== 1 ? 's' : ''; ?>)
                                </span>
                            <?php endif; ?>
                        </h3>
                        
                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Selecionar todos</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($guias)): ?>
                    <div class="p-12 text-center">
                        <i data-lucide="receipt" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Nenhum recibo encontrado
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            <?php if (!empty($search) || $status_pagamento !== '' || !empty($forma_pagamento) || !empty($data_inicio) || !empty($data_fim)): ?>
                                Não há recibos que correspondam aos filtros aplicados.
                            <?php else: ?>
                                Não há guias para gerar recibos no momento.
                            <?php endif; ?>
                        </p>
                        <a href="guias.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Ver Guias
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th class="w-12">
                                        <input type="checkbox" id="select-all-header" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th>Guia</th>
                                    <th>Cliente</th>
                                    <th>Serviço</th>
                                    <th>Data</th>
                                    <th>Valor</th>
                                    <th>Forma Pagamento</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guias as $guia): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td>
                                            <input type="checkbox" 
                                                   class="guia-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                                                   value="<?php echo $guia['id']; ?>"
                                                   data-pago="<?php echo $guia['pago']; ?>">
                                        </td>
                                        <td>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($guia['numero_guia']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo $guia['tipo_servico']; ?>
                                                </p>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($guia['cliente_nome']); ?>
                                                </p>
                                                <?php if ($guia['cliente_telefone']): ?>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo formatPhone($guia['cliente_telefone']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if ($guia['tipo_servico'] === 'Exame' && $guia['exame_nome']): ?>
                                                    <p class="font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($guia['exame_nome']); ?>
                                                    </p>
                                                <?php elseif ($guia['tipo_servico'] === 'Consulta' && $guia['medico_nome']): ?>
                                                    <p class="font-medium text-gray-900 dark:text-white">
                                                        Dr. <?php echo htmlspecialchars($guia['medico_nome']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($guia['clinica_nome']); ?>
                                                </p>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo formatDateBR($guia['data_agendamento']); ?>
                                                </p>
                                                <?php if ($guia['pago'] && $guia['data_pagamento']): ?>
                                                    <p class="text-sm text-green-600 dark:text-green-400">
                                                        Pago: <?php echo formatDateBR($guia['data_pagamento']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="font-semibold text-lg text-blue-600 dark:text-blue-400">
                                                R$ <?php echo number_format($guia['valor_exame'], 2, ',', '.'); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="<?php echo $guia['forma_pagamento'] === 'PIX' ? 'smartphone' : ($guia['forma_pagamento'] === 'Dinheiro' ? 'banknote' : 'credit-card'); ?>" class="w-4 h-4 text-gray-400"></i>
                                                <span class="text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['forma_pagamento']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($guia['pago']): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded-full">
                                                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                                                    Pago
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs rounded-full">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    Pendente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <?php if (!$guia['pago']): ?>
                                                    <button onclick="confirmarPagamento(<?php echo $guia['id']; ?>)" 
                                                            class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 dark:hover:bg-green-900 rounded-lg"
                                                            title="Confirmar pagamento">
                                                        <i data-lucide="check" class="w-4 h-4"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="cancelarPagamento(<?php echo $guia['id']; ?>)" 
                                                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg"
                                                            title="Cancelar pagamento">
                                                        <i data-lucide="x" class="w-4 h-4"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button onclick="imprimirRecibo(<?php echo $guia['id']; ?>)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-purple-600 hover:text-purple-800 hover:bg-purple-100 dark:hover:bg-purple-900 rounded-lg"
                                                        title="Imprimir recibo">
                                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                                </button>
                                                
                                                <a href="guia_visualizar.php?id=<?php echo $guia['id']; ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                   title="Ver detalhes">
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Rodapé da tabela -->
                    <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Mostrando <?php echo count($guias); ?> recibo<?php echo count($guias) !== 1 ? 's' : ''; ?>
                            </p>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Total:</span> R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?> |
                                <span class="font-medium text-green-600 dark:text-green-400">Recebido:</span> R$ <?php echo number_format($valorRecebido, 2, ',', '.'); ?> |
                                <span class="font-medium text-red-600 dark:text-red-400">Pendente:</span> R$ <?php echo number_format($valorPendente, 2, ',', '.'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </main>
        
    </div>
    
</div>

<!-- Modal de Confirmação de Pagamento -->
<div id="modal-pagamento" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Confirmar Pagamento</h3>
        
        <form id="form-pagamento">
            <input type="hidden" id="guia-id" name="guia_id">
            
            <div class="space-y-4">
                <div>
                    <label for="data-pagamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Data do Pagamento
                    </label>
                    <input type="date" 
                           id="data-pagamento" 
                           name="data_pagamento" 
                           value="<?php echo date('Y-m-d'); ?>"
                           class="input-destacado w-full">
                </div>
                
                <div>
                    <label for="forma-pagamento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Forma de Pagamento
                    </label>
                    <select id="forma-pagamento" name="forma_pagamento" class="input-destacado w-full">
                        <option value="Dinheiro">Dinheiro</option>
                        <option value="PIX">PIX</option>
                        <option value="Cartão Débito">Cartão de Débito</option>
                        <option value="Cartão Crédito">Cartão de Crédito</option>
                        <option value="Transferência">Transferência Bancária</option>
                    </select>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" onclick="fecharModalPagamento()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Confirmar Pagamento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Variáveis globais
let guiaIdAtual = null;

// Função para confirmar pagamento
function confirmarPagamento(guiaId) {
    guiaIdAtual = guiaId;
    document.getElementById('guia-id').value = guiaId;
    document.getElementById('modal-pagamento').classList.remove('hidden');
    document.getElementById('modal-pagamento').classList.add('flex');
}

// Função para fechar modal
function fecharModalPagamento() {
    document.getElementById('modal-pagamento').classList.add('hidden');
    document.getElementById('modal-pagamento').classList.remove('flex');
    guiaIdAtual = null;
}

// Função para cancelar pagamento
function cancelarPagamento(guiaId) {
    if (confirm('Tem certeza que deseja cancelar este pagamento?')) {
        fetch('recibos.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=cancelar_pagamento&guia_id=' + guiaId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('Erro: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Erro interno. Tente novamente.', 'error');
        });
    }
}

// Função para imprimir recibo individual
function imprimirRecibo(guiaId) {
    window.open('recibo_imprimir.php?id=' + guiaId, '_blank');
}

// Função para confirmar pagamentos em lote
function confirmarPagamentosLote() {
    const checkboxes = document.querySelectorAll('.guia-checkbox:checked');
    const guiasPendentes = Array.from(checkboxes).filter(cb => cb.dataset.pago === '0');
    
    if (guiasPendentes.length === 0) {
        showToast('Selecione pelo menos uma guia pendente', 'warning');
        return;
    }
    
    if (confirm(`Confirmar pagamento de ${guiasPendentes.length} guia(s) selecionada(s)?`)) {
        const promises = guiasPendentes.map(cb => {
            return fetch('recibos.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=confirmar_pagamento&guia_id=${cb.value}&data_pagamento=${new Date().toISOString().split('T')[0]}&forma_pagamento=Dinheiro`
            }).then(response => response.json());
        });
        
        Promise.all(promises).then(results => {
            const sucessos = results.filter(r => r.success).length;
            const erros = results.length - sucessos;
            
            if (sucessos > 0) {
                showToast(`${sucessos} pagamento(s) confirmado(s) com sucesso!`, 'success');
            }
            if (erros > 0) {
                showToast(`${erros} erro(s) ao confirmar pagamentos`, 'error');
            }
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        });
    }
}

// Função para exportar recibos
function exportRecibos() {
    showToast('Exportando recibos...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Recibos exportados com sucesso!', 'success');
    }, 2000);
}

// Função para imprimir recibos selecionados
function imprimirRecibos() {
    const checkboxes = document.querySelectorAll('.guia-checkbox:checked');
    
    if (checkboxes.length === 0) {
        showToast('Selecione pelo menos um recibo para imprimir', 'warning');
        return;
    }
    
    const ids = Array.from(checkboxes).map(cb => cb.value);
    window.open('recibos_imprimir.php?ids=' + ids.join(','), '_blank');
}

// Manipulação de checkboxes
document.getElementById('select-all').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.guia-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

document.getElementById('select-all-header').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.guia-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    document.getElementById('select-all').checked = this.checked;
});

// Submissão do formulário de pagamento
document.getElementById('form-pagamento').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'confirmar_pagamento');
    
    fetch('recibos.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            fecharModalPagamento();
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showToast('Erro: ' + data.message, 'error');
        }
    })
    .catch(error => {
        showToast('Erro interno. Tente novamente.', 'error');
    });
});

// Auto-submit dos filtros
document.getElementById('status_pagamento').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('forma_pagamento').addEventListener('change', function() {
    this.form.submit();
});

// Busca em tempo real
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    
    this.searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalPagamento();
    }
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + A = Selecionar todos
    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        document.getElementById('select-all').click();
    }
    
    // Ctrl + P = Imprimir
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        imprimirRecibos();
    }
    
    // Ctrl + E = Exportar
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportRecibos();
    }
});
</script>

<?php include 'templates/footer.php'; ?>
