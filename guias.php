<?php
// Página Principal de Guias - Salvar como guias.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Processa filtros
$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$clinica_id = intval($_GET['clinica_id'] ?? 0);
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';
$tipo_servico = $_GET['tipo_servico'] ?? '';
$pago = $_GET['pago'] ?? '';

// Monta a query base
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(c.nome_completo LIKE ? OR g.numero_guia LIKE ? OR cl.nome_fantasia LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $whereConditions[] = "g.status = ?";
    $params[] = $status;
}

if ($clinica_id > 0) {
    $whereConditions[] = "g.clinica_id = ?";
    $params[] = $clinica_id;
}

if (!empty($data_inicio)) {
    $whereConditions[] = "g.data_agendamento >= ?";
    $params[] = $data_inicio;
}

if (!empty($data_fim)) {
    $whereConditions[] = "g.data_agendamento <= ?";
    $params[] = $data_fim;
}

if (!empty($tipo_servico)) {
    $whereConditions[] = "g.tipo_servico = ?";
    $params[] = $tipo_servico;
}

if ($pago !== '') {
    $whereConditions[] = "g.pago = ?";
    $params[] = intval($pago);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Busca as guias
$guias = $db->select("
    SELECT g.*, 
           c.nome_completo as cliente_nome,
           c.telefone1 as cliente_telefone,
           cl.nome_fantasia as clinica_nome,
           cl.telefone1 as clinica_telefone,
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
    $whereClause
    ORDER BY g.data_agendamento DESC, g.hora_agendamento DESC
", $params);

// Estatísticas
$totalGuias = count($guias);
$guiasHoje = array_filter($guias, function($g) { return $g['data_agendamento'] === date('Y-m-d'); });
$totalHoje = count($guiasHoje);

$statusStats = [];
foreach ($guias as $guia) {
    $status = $guia['status'];
    if (!isset($statusStats[$status])) {
        $statusStats[$status] = 0;
    }
    $statusStats[$status]++;
}

$valorTotal = array_sum(array_column($guias, 'valor_exame'));
$valorRepasse = array_sum(array_column($guias, 'valor_repasse'));
$valorLucro = $valorTotal - $valorRepasse;

// Busca clínicas para filtros
$clinicas = $db->select("SELECT id, nome_fantasia FROM clinicas WHERE status = 'Ativa' ORDER BY nome_fantasia");

$pageTitle = 'Guias de Encaminhamento';
$pageSubtitle = 'Gestão de guias e agendamentos';

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
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Guias</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Guias de Encaminhamento</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Gestão completa de guias e agendamentos
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <a href="guia_formulario.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Nova Guia
                    </a>
                    
                    <button onclick="exportGuias()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Exportar
                    </button>
                    
                    <button onclick="imprimirGuias()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
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
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Agendadas Hoje</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo $totalHoje; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="calendar" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Valor Total</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Lucro Líquido</p>
                            <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">R$ <?php echo number_format($valorLucro, 2, ',', '.'); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                            <i data-lucide="trending-up" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Distribuição por Status -->
            <?php if (!empty($statusStats)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Distribuição por Status</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <?php 
                            $statusColors = [
                                'Agendado' => 'blue',
                                'Confirmado' => 'green',
                                'Realizado' => 'purple',
                                'Cancelado' => 'red',
                                'Faltou' => 'yellow'
                            ];
                            foreach ($statusStats as $status => $total): 
                                $color = $statusColors[$status] ?? 'gray';
                            ?>
                                <div class="text-center p-3 bg-<?php echo $color; ?>-50 dark:bg-<?php echo $color; ?>-900/20 rounded-lg">
                                    <p class="text-2xl font-bold text-<?php echo $color; ?>-600 dark:text-<?php echo $color; ?>-400"><?php echo $total; ?></p>
                                    <p class="text-xs text-<?php echo $color; ?>-600 dark:text-<?php echo $color; ?>-400"><?php echo $status; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    
                    <!-- Busca -->
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Buscar
                        </label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Cliente, número da guia ou clínica..."
                               class="input-destacado w-full">
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status
                        </label>
                        <select id="status" name="status" class="input-destacado w-full">
                            <option value="">Todos</option>
                            <option value="Agendado" <?php echo $status === 'Agendado' ? 'selected' : ''; ?>>Agendado</option>
                            <option value="Confirmado" <?php echo $status === 'Confirmado' ? 'selected' : ''; ?>>Confirmado</option>
                            <option value="Realizado" <?php echo $status === 'Realizado' ? 'selected' : ''; ?>>Realizado</option>
                            <option value="Cancelado" <?php echo $status === 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                            <option value="Faltou" <?php echo $status === 'Faltou' ? 'selected' : ''; ?>>Faltou</option>
                        </select>
                    </div>
                    
                    <!-- Clínica -->
                    <div>
                        <label for="clinica_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Clínica
                        </label>
                        <select id="clinica_id" name="clinica_id" class="input-destacado w-full">
                            <option value="">Todas</option>
                            <?php foreach ($clinicas as $clinica): ?>
                                <option value="<?php echo $clinica['id']; ?>" 
                                        <?php echo $clinica_id === $clinica['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($clinica['nome_fantasia']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Tipo de Serviço -->
                    <div>
                        <label for="tipo_servico" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Tipo
                        </label>
                        <select id="tipo_servico" name="tipo_servico" class="input-destacado w-full">
                            <option value="">Todos</option>
                            <option value="Consulta" <?php echo $tipo_servico === 'Consulta' ? 'selected' : ''; ?>>Consulta</option>
                            <option value="Exame" <?php echo $tipo_servico === 'Exame' ? 'selected' : ''; ?>>Exame</option>
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
                    
                    <!-- Situação Pagamento -->
                    <div>
                        <label for="pago" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Pagamento
                        </label>
                        <select id="pago" name="pago" class="input-destacado w-full">
                            <option value="">Todos</option>
                            <option value="1" <?php echo $pago === '1' ? 'selected' : ''; ?>>Pago</option>
                            <option value="0" <?php echo $pago === '0' ? 'selected' : ''; ?>>Pendente</option>
                        </select>
                    </div>
                    
                    <!-- Botões -->
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                        <a href="guias.php" class="flex-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 text-center">
                            Limpar
                        </a>
                    </div>
                    
                </form>
            </div>
            
            <!-- Lista de Guias -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Lista de Guias
                        <?php if (!empty($search) || !empty($status) || $clinica_id > 0 || !empty($data_inicio) || !empty($data_fim) || !empty($tipo_servico) || $pago !== ''): ?>
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                (<?php echo count($guias); ?> resultado<?php echo count($guias) !== 1 ? 's' : ''; ?> encontrado<?php echo count($guias) !== 1 ? 's' : ''; ?>)
                            </span>
                        <?php endif; ?>
                    </h3>
                </div>
                
                <?php if (empty($guias)): ?>
                    <div class="p-12 text-center">
                        <i data-lucide="file-text" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Nenhuma guia encontrada
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            <?php if (!empty($search) || !empty($status) || $clinica_id > 0 || !empty($data_inicio) || !empty($data_fim) || !empty($tipo_servico) || $pago !== ''): ?>
                                Não há guias que correspondam aos filtros aplicados.
                            <?php else: ?>
                                Comece criando sua primeira guia de encaminhamento.
                            <?php endif; ?>
                        </p>
                        <a href="guia_formulario.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Criar Primeira Guia
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Guia</th>
                                    <th>Cliente</th>
                                    <th>Clínica</th>
                                    <th>Serviço</th>
                                    <th>Data/Hora</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Pagamento</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guias as $guia): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
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
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($guia['clinica_nome']); ?>
                                                </p>
                                                <?php if ($guia['clinica_telefone']): ?>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo formatPhone($guia['clinica_telefone']); ?>
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
                                                <?php else: ?>
                                                    <p class="text-gray-500 dark:text-gray-400">-</p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo formatDateBR($guia['data_agendamento']); ?>
                                                </p>
                                                <?php if ($guia['hora_agendamento']): ?>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo date('H:i', strtotime($guia['hora_agendamento'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <p class="font-semibold text-green-600 dark:text-green-400">
                                                    R$ <?php echo number_format($guia['valor_exame'], 2, ',', '.'); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    Repasse: R$ <?php echo number_format($guia['valor_repasse'], 2, ',', '.'); ?>
                                                </p>
                                            </div>
                                        </td>
                                        <td>
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
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-<?php echo $color; ?>-100 dark:bg-<?php echo $color; ?>-900 text-<?php echo $color; ?>-800 dark:text-<?php echo $color; ?>-200 text-xs rounded-full">
                                                <i data-lucide="<?php echo $guia['status'] === 'Realizado' ? 'check-circle' : ($guia['status'] === 'Cancelado' ? 'x-circle' : 'clock'); ?>" class="w-3 h-3"></i>
                                                <?php echo $guia['status']; ?>
                                            </span>
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
                                                <a href="guia_visualizar.php?id=<?php echo $guia['id']; ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                   title="Visualizar guia">
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                </a>
                                                
                                                <a href="guia_editar.php?id=<?php echo $guia['id']; ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 dark:hover:bg-green-900 rounded-lg"
                                                   title="Editar guia">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </a>
                                                
                                                <button onclick="imprimirGuia(<?php echo $guia['id']; ?>)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-purple-600 hover:text-purple-800 hover:bg-purple-100 dark:hover:bg-purple-900 rounded-lg"
                                                        title="Imprimir guia">
                                                    <i data-lucide="printer" class="w-4 h-4"></i>
                                                </button>
                                                
                                                <button onclick="deleteGuia(<?php echo $guia['id']; ?>)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg"
                                                        title="Excluir guia">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
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
                                Mostrando <?php echo count($guias); ?> guia<?php echo count($guias) !== 1 ? 's' : ''; ?>
                            </p>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-medium">Total:</span> R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?> |
                                <span class="font-medium">Repasse:</span> R$ <?php echo number_format($valorRepasse, 2, ',', '.'); ?> |
                                <span class="font-medium">Lucro:</span> R$ <?php echo number_format($valorLucro, 2, ',', '.'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
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
                    window.location.reload();
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

// Função para imprimir guia individual
function imprimirGuia(guiaId) {
    window.open('guia_visualizar.php?id=' + guiaId + '&print=1', '_blank');
}

// Função para imprimir múltiplas guias
function imprimirGuias() {
    const params = new URLSearchParams(window.location.search);
    params.set('print', '1');
    window.open('guias.php?' + params.toString(), '_blank');
}

// Função para exportar guias
function exportGuias() {
    showToast('Exportando lista de guias...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Lista exportada com sucesso!', 'success');
    }, 2000);
}

// Auto-submit do formulário quando mudar filtros
document.getElementById('status').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('clinica_id').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('tipo_servico').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('pago').addEventListener('change', function() {
    this.form.submit();
});

// Busca em tempo real
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    
    this.searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});

// Filtros de data
document.getElementById('data_inicio').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('data_fim').addEventListener('change', function() {
    this.form.submit();
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + N = Nova guia
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'guia_formulario.php';
    }
    
    // Ctrl + P = Imprimir
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        imprimirGuias();
    }
    
    // Ctrl + E = Exportar
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportGuias();
    }
});
</script>

<?php include 'templates/footer.php'; ?>
