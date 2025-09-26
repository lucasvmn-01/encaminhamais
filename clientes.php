<?php
// Página de Clientes - Salvar como clientes.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Processa filtros
$search = trim($_GET['search'] ?? '');
$dataInicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$dataFim = $_GET['data_fim'] ?? date('Y-m-d');
$tipoData = $_GET['tipo_data'] ?? 'modificacao';

// Monta a query base
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(nome_completo LIKE ? OR cpf LIKE ? OR telefone1 LIKE ? OR telefone2 LIKE ? OR email LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($dataInicio) && !empty($dataFim)) {
    $dateField = $tipoData === 'cadastro' ? 'data_cadastro' : 'data_modificacao';
    $whereConditions[] = "DATE($dateField) BETWEEN ? AND ?";
    $params[] = $dataInicio;
    $params[] = $dataFim;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Busca os clientes
$clientes = $db->select("
    SELECT c.*, 
           u1.nome_completo as cadastrado_por_nome,
           u2.nome_completo as modificado_por_nome
    FROM clientes c
    LEFT JOIN usuarios u1 ON c.cadastrado_por_id = u1.id
    LEFT JOIN usuarios u2 ON c.modificado_por_id = u2.id
    $whereClause
    ORDER BY c.data_modificacao DESC, c.data_cadastro DESC
", $params);

// Estatísticas para o mini-dashboard
$totalClientes = $db->count("SELECT COUNT(*) FROM clientes");
$clientesMasculino = $db->count("SELECT COUNT(*) FROM clientes WHERE sexo = 'Masculino'");
$clientesFeminino = $db->count("SELECT COUNT(*) FROM clientes WHERE sexo = 'Feminino'");
$clientesNaoInformado = $db->count("SELECT COUNT(*) FROM clientes WHERE sexo = 'Não Informado'");

// Estatísticas por faixa etária
$faixasEtarias = [
    'Berçário' => [0, 2],
    'Maternal' => [3, 4],
    'Jardim de Infância' => [5, 6],
    'Primários' => [7, 8],
    'Juniores' => [9, 10],
    'Pré-adolescentes' => [11, 12],
    'Adolescentes' => [13, 14],
    'Juvenis' => [15, 17],
    'Jovens' => [18, 20],
    'Adulto (Fase Inicial)' => [21, 40],
    'Adulto (Fase Intermediária)' => [41, 50],
    'Adulto (Fase Maduro)' => [51, 60],
    'Melhor Idade' => [61, 150]
];

$estatisticasFaixaEtaria = [];
foreach ($faixasEtarias as $nome => $range) {
    $count = $db->count("
        SELECT COUNT(*) FROM clientes 
        WHERE TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) BETWEEN ? AND ?
    ", [$range[0], $range[1]]);
    $estatisticasFaixaEtaria[$nome] = $count;
}

$pageTitle = 'Clientes';
$pageSubtitle = 'Gerenciamento de clientes do sistema';

include 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100 dark:bg-gray-900">
    
    <?php include 'templates/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <?php include 'templates/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-900 p-6">
            
            <!-- Submenu -->
            <div class="mb-6">
                <nav class="flex space-x-4">
                    <a href="clientes.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium">
                        Lista de Clientes
                    </a>
                    <a href="aniversario_cliente.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                        Aniversariantes
                    </a>
                    <a href="documentos_clientes.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                        Documentos
                    </a>
                </nav>
            </div>
            
            <!-- Mini Dashboard -->
            <div id="mini-dashboard" class="mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Estatísticas de Clientes</h2>
                    <button onclick="toggleDashboard()" class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
                        <i data-lucide="eye-off" id="dashboard-toggle-icon" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <div id="dashboard-content">
                    <!-- Cards principais -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="dashboard-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Clientes</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($totalClientes); ?></p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <i data-lucide="users" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dashboard-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Masculino</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($clientesMasculino); ?></p>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <i data-lucide="user" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dashboard-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Feminino</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($clientesFeminino); ?></p>
                                </div>
                                <div class="w-12 h-12 bg-pink-100 dark:bg-pink-900 rounded-full flex items-center justify-center">
                                    <i data-lucide="user" class="w-6 h-6 text-pink-600 dark:text-pink-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="dashboard-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Não Informado</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($clientesNaoInformado); ?></p>
                                </div>
                                <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center">
                                    <i data-lucide="help-circle" class="w-6 h-6 text-gray-600 dark:text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Faixas Etárias -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Distribuição por Faixa Etária</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach ($estatisticasFaixaEtaria as $faixa => $quantidade): ?>
                                <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $quantidade; ?></p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400"><?php echo $faixa; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Barra de Ferramentas -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    
                    <!-- Busca -->
                    <div class="flex-1 max-w-md">
                        <form method="GET" class="relative">
                            <input type="hidden" name="data_inicio" value="<?php echo htmlspecialchars($dataInicio); ?>">
                            <input type="hidden" name="data_fim" value="<?php echo htmlspecialchars($dataFim); ?>">
                            <input type="hidden" name="tipo_data" value="<?php echo htmlspecialchars($tipoData); ?>">
                            
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                                </div>
                                <input type="text" 
                                       name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       placeholder="Buscar por nome, CPF, telefone ou e-mail..."
                                       class="input-destacado w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </form>
                    </div>
                    
                    <!-- Botões de Ação -->
                    <div class="flex items-center gap-3">
                        <button onclick="openFilterModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="filter" class="w-4 h-4"></i>
                            Filtros
                        </button>
                        
                        <button onclick="openColumnModal()" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">
                            <i data-lucide="columns" class="w-4 h-4"></i>
                            Personalizar
                        </button>
                        
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Exportar
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </button>
                            
                            <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg border dark:border-gray-700 z-50">
                                <button onclick="exportToExcel('clientes-table', 'clientes')" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Tabela Visível (.xlsx)
                                </button>
                                <button onclick="exportToCSV('clientes-table', 'clientes')" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Tabela Visível (.csv)
                                </button>
                                <button onclick="exportToPDF('clientes-table', 'clientes')" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Tabela Visível (.pdf)
                                </button>
                                <hr class="border-gray-200 dark:border-gray-600">
                                <button onclick="exportFullReport()" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    Relatório Completo (.xlsx)
                                </button>
                            </div>
                        </div>
                        
                        <a href="cliente_formulario.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Cadastrar Cliente
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Tabela de Clientes -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="clientes-table" class="table-custom">
                        <thead>
                            <tr>
                                <th data-sort="0">Ações</th>
                                <th data-sort="1">Código</th>
                                <th data-sort="2">Nome Completo</th>
                                <th data-sort="3">CPF</th>
                                <th data-sort="4">Idade</th>
                                <th data-sort="5">Telefone 1</th>
                                <th data-sort="6" class="column-optional" style="display: none;">Faixa Etária</th>
                                <th data-sort="7" class="column-optional" style="display: none;">Cidade</th>
                                <th data-sort="8" class="column-optional" style="display: none;">UF</th>
                                <th data-sort="9">Data Cadastro</th>
                                <th data-sort="10" class="column-optional" style="display: none;">Últ. Modificação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                        <i data-lucide="users" class="w-12 h-12 mx-auto mb-4 opacity-50"></i>
                                        <p>Nenhum cliente encontrado</p>
                                        <a href="cliente_formulario.php" class="text-blue-600 dark:text-blue-400 hover:underline">
                                            Cadastrar primeiro cliente
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <?php 
                                    $idade = calculateAge($cliente['data_nascimento']);
                                    $faixaEtaria = getAgeGroup($idade);
                                    ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td>
                                            <a href="cliente_editar.php?id=<?php echo $cliente['id']; ?>" 
                                               class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                               title="Editar cliente">
                                                <i data-lucide="edit" class="w-4 h-4"></i>
                                            </a>
                                        </td>
                                        <td data-value="codigo_cliente">
                                            <span class="font-mono text-sm"><?php echo htmlspecialchars($cliente['codigo_cliente']); ?></span>
                                        </td>
                                        <td data-value="nome_completo">
                                            <div class="flex items-center gap-3">
                                                <?php if ($cliente['foto_path'] && file_exists($cliente['foto_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($cliente['foto_path']); ?>" 
                                                         class="w-8 h-8 rounded-full object-cover" 
                                                         alt="Foto">
                                                <?php else: ?>
                                                    <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                                                            <?php echo strtoupper(substr($cliente['nome_completo'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="font-medium"><?php echo htmlspecialchars($cliente['nome_completo']); ?></span>
                                            </div>
                                        </td>
                                        <td data-value="cpf">
                                            <span class="font-mono text-sm"><?php echo formatCPF($cliente['cpf']); ?></span>
                                        </td>
                                        <td data-value="idade">
                                            <?php echo $idade; ?> anos
                                        </td>
                                        <td data-value="telefone1">
                                            <?php if ($cliente['telefone1']): ?>
                                                <a href="<?php echo getWhatsAppLink($cliente['telefone1']); ?>" 
                                                   target="_blank"
                                                   class="text-green-600 hover:text-green-800 hover:underline">
                                                    <?php echo formatPhone($cliente['telefone1']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-value="faixa_etaria" class="column-optional" style="display: none;">
                                            <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                                                <?php echo $faixaEtaria; ?>
                                            </span>
                                        </td>
                                        <td data-value="cidade" class="column-optional" style="display: none;">
                                            <?php echo htmlspecialchars($cliente['cidade'] ?? '-'); ?>
                                        </td>
                                        <td data-value="estado" class="column-optional" style="display: none;">
                                            <?php echo htmlspecialchars($cliente['estado'] ?? '-'); ?>
                                        </td>
                                        <td data-value="data_cadastro">
                                            <?php echo formatDateBR($cliente['data_cadastro'], true); ?>
                                        </td>
                                        <td data-value="data_modificacao" class="column-optional" style="display: none;">
                                            <?php echo $cliente['data_modificacao'] ? formatDateBR($cliente['data_modificacao'], true) : '-'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Rodapé da tabela -->
                <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Mostrando <?php echo count($clientes); ?> de <?php echo $totalClientes; ?> clientes
                        </p>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Última atualização: <?php echo date('d/m/Y H:i'); ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
        
    </div>
    
</div>

<!-- Modal de Filtros -->
<div id="filter-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content max-w-md">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Filtros</h3>
            <button onclick="closeFilterModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <form method="GET" class="space-y-4">
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tipo de Data
                </label>
                <select name="tipo_data" class="input-destacado w-full">
                    <option value="modificacao" <?php echo $tipoData === 'modificacao' ? 'selected' : ''; ?>>Data de Modificação</option>
                    <option value="cadastro" <?php echo $tipoData === 'cadastro' ? 'selected' : ''; ?>>Data de Cadastro</option>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Data Início
                    </label>
                    <input type="date" name="data_inicio" value="<?php echo $dataInicio; ?>" class="input-destacado w-full">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Data Fim
                    </label>
                    <input type="date" name="data_fim" value="<?php echo $dataFim; ?>" class="input-destacado w-full">
                </div>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                    Aplicar Filtros
                </button>
                <a href="clientes.php" class="flex-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 text-center">
                    Limpar
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Personalização de Colunas -->
<div id="column-modal" class="modal-overlay" style="display: none;">
    <div class="modal-content max-w-md">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Personalizar Colunas</h3>
            <button onclick="closeColumnModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="space-y-3">
            <label class="flex items-center">
                <input type="checkbox" checked disabled class="mr-3">
                <span class="text-gray-500">Ações (sempre visível)</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" checked disabled class="mr-3">
                <span class="text-gray-500">Código (sempre visível)</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" checked disabled class="mr-3">
                <span class="text-gray-500">Nome Completo (sempre visível)</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" checked disabled class="mr-3">
                <span class="text-gray-500">CPF (sempre visível)</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" checked disabled class="mr-3">
                <span class="text-gray-500">Idade (sempre visível)</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" checked disabled class="mr-3">
                <span class="text-gray-500">Telefone 1 (sempre visível)</span>
            </label>
            
            <hr class="border-gray-200 dark:border-gray-600">
            
            <label class="flex items-center">
                <input type="checkbox" id="col-faixa-etaria" class="mr-3" onchange="toggleColumn(6, this.checked)">
                <span>Faixa Etária</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" id="col-cidade" class="mr-3" onchange="toggleColumn(7, this.checked)">
                <span>Cidade</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" id="col-uf" class="mr-3" onchange="toggleColumn(8, this.checked)">
                <span>UF</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" id="col-modificacao" class="mr-3" onchange="toggleColumn(10, this.checked)">
                <span>Última Modificação</span>
            </label>
        </div>
        
        <div class="flex gap-3 pt-6">
            <button onclick="saveColumnPreferences()" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                Salvar Preferências
            </button>
            <button onclick="closeColumnModal()" class="flex-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
// Controle do dashboard
function toggleDashboard() {
    const content = document.getElementById('dashboard-content');
    const icon = document.getElementById('dashboard-toggle-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        content.style.display = 'none';
        icon.setAttribute('data-lucide', 'eye');
    }
    
    lucide.createIcons();
    
    // Salva a preferência
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=hide_dashboard&page=clientes&hidden=' + (content.style.display === 'none' ? '1' : '0')
    });
}

// Modais
function openFilterModal() {
    document.getElementById('filter-modal').style.display = 'flex';
}

function closeFilterModal() {
    document.getElementById('filter-modal').style.display = 'none';
}

function openColumnModal() {
    document.getElementById('column-modal').style.display = 'flex';
}

function closeColumnModal() {
    document.getElementById('column-modal').style.display = 'none';
}

// Controle de colunas
function toggleColumn(columnIndex, show) {
    const table = document.getElementById('clientes-table');
    const headers = table.querySelectorAll('th');
    const rows = table.querySelectorAll('tbody tr');
    
    // Toggle header
    if (headers[columnIndex]) {
        headers[columnIndex].style.display = show ? '' : 'none';
    }
    
    // Toggle cells
    rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells[columnIndex]) {
            cells[columnIndex].style.display = show ? '' : 'none';
        }
    });
}

function saveColumnPreferences() {
    const preferences = {
        faixa_etaria: document.getElementById('col-faixa-etaria').checked,
        cidade: document.getElementById('col-cidade').checked,
        uf: document.getElementById('col-uf').checked,
        modificacao: document.getElementById('col-modificacao').checked
    };
    
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=save_table_columns&table=clientes&columns=' + JSON.stringify(preferences)
    }).then(() => {
        showToast('Preferências salvas com sucesso!', 'success');
        closeColumnModal();
    });
}

// Exportação de relatório completo
function exportFullReport() {
    // Simula a exportação do relatório completo
    showToast('Gerando relatório completo...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Relatório exportado com sucesso!', 'success');
    }, 2000);
}

// Busca em tempo real
document.querySelector('input[name="search"]').addEventListener('input', function() {
    const form = this.closest('form');
    clearTimeout(this.searchTimeout);
    
    this.searchTimeout = setTimeout(() => {
        form.submit();
    }, 500);
});
</script>

<?php include 'templates/footer.php'; ?>
