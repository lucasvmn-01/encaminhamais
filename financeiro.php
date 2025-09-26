<?php
// Dashboard Financeiro - Salvar como financeiro.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Filtros de período
$periodo = $_GET['periodo'] ?? 'mes_atual';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Define datas baseado no período
switch ($periodo) {
    case 'hoje':
        $data_inicio = $data_fim = date('Y-m-d');
        break;
    case 'semana_atual':
        $data_inicio = date('Y-m-d', strtotime('monday this week'));
        $data_fim = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'mes_atual':
        $data_inicio = date('Y-m-01');
        $data_fim = date('Y-m-t');
        break;
    case 'mes_passado':
        $data_inicio = date('Y-m-01', strtotime('first day of last month'));
        $data_fim = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'ano_atual':
        $data_inicio = date('Y-01-01');
        $data_fim = date('Y-12-31');
        break;
    case 'personalizado':
        // Usa as datas fornecidas
        break;
    default:
        $data_inicio = date('Y-m-01');
        $data_fim = date('Y-m-t');
}

// Busca dados financeiros das guias
$whereClause = '';
$params = [];

if ($data_inicio && $data_fim) {
    $whereClause = 'WHERE g.data_agendamento BETWEEN ? AND ?';
    $params = [$data_inicio, $data_fim];
}

// Estatísticas gerais
$stats = $db->select("
    SELECT 
        COUNT(*) as total_guias,
        SUM(g.valor_exame) as valor_total,
        SUM(g.valor_repasse) as valor_repasse,
        SUM(g.valor_exame - g.valor_repasse) as lucro_total,
        SUM(CASE WHEN g.pago = 1 THEN g.valor_exame ELSE 0 END) as valor_recebido,
        SUM(CASE WHEN g.pago = 0 THEN g.valor_exame ELSE 0 END) as valor_pendente,
        SUM(CASE WHEN g.repasse_pago = 1 THEN g.valor_repasse ELSE 0 END) as repasse_efetuado,
        SUM(CASE WHEN g.repasse_pago = 0 THEN g.valor_repasse ELSE 0 END) as repasse_pendente,
        COUNT(CASE WHEN g.pago = 1 THEN 1 END) as guias_pagas,
        COUNT(CASE WHEN g.pago = 0 THEN 1 END) as guias_pendentes
    FROM guias g
    $whereClause
", $params);

$stats = $stats[0] ?? [
    'total_guias' => 0,
    'valor_total' => 0,
    'valor_repasse' => 0,
    'lucro_total' => 0,
    'valor_recebido' => 0,
    'valor_pendente' => 0,
    'repasse_efetuado' => 0,
    'repasse_pendente' => 0,
    'guias_pagas' => 0,
    'guias_pendentes' => 0
];

// Estatísticas por status
$statusStats = $db->select("
    SELECT 
        g.status,
        COUNT(*) as quantidade,
        SUM(g.valor_exame) as valor_total
    FROM guias g
    $whereClause
    GROUP BY g.status
    ORDER BY quantidade DESC
", $params);

// Estatísticas por forma de pagamento
$pagamentoStats = $db->select("
    SELECT 
        g.forma_pagamento,
        COUNT(*) as quantidade,
        SUM(g.valor_exame) as valor_total
    FROM guias g
    $whereClause
    AND g.pago = 1
    GROUP BY g.forma_pagamento
    ORDER BY valor_total DESC
", $params);

// Top clínicas por faturamento
$topClinicas = $db->select("
    SELECT 
        cl.nome_fantasia,
        COUNT(g.id) as total_guias,
        SUM(g.valor_exame) as valor_total,
        SUM(g.valor_repasse) as valor_repasse,
        SUM(g.valor_exame - g.valor_repasse) as lucro
    FROM guias g
    JOIN clinicas cl ON g.clinica_id = cl.id
    $whereClause
    GROUP BY g.clinica_id, cl.nome_fantasia
    ORDER BY valor_total DESC
    LIMIT 10
", $params);

// Evolução mensal (últimos 12 meses)
$evolucaoMensal = $db->select("
    SELECT 
        DATE_FORMAT(g.data_agendamento, '%Y-%m') as mes,
        COUNT(*) as total_guias,
        SUM(g.valor_exame) as valor_total,
        SUM(g.valor_repasse) as valor_repasse,
        SUM(g.valor_exame - g.valor_repasse) as lucro
    FROM guias g
    WHERE g.data_agendamento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(g.data_agendamento, '%Y-%m')
    ORDER BY mes ASC
");

// Guias com pagamento em atraso (mais de 30 dias)
$guiasAtrasadas = $db->select("
    SELECT 
        g.numero_guia,
        g.data_agendamento,
        g.valor_exame,
        c.nome_completo as cliente_nome,
        cl.nome_fantasia as clinica_nome,
        DATEDIFF(CURDATE(), g.data_agendamento) as dias_atraso
    FROM guias g
    JOIN clientes c ON g.cliente_id = c.id
    JOIN clinicas cl ON g.clinica_id = cl.id
    WHERE g.pago = 0 
    AND g.status IN ('Realizado', 'Confirmado')
    AND DATEDIFF(CURDATE(), g.data_agendamento) > 30
    ORDER BY dias_atraso DESC
    LIMIT 10
");

// Repasses pendentes por clínica
$repassesPendentes = $db->select("
    SELECT 
        cl.nome_fantasia,
        cl.telefone_financeiro,
        cl.email_financeiro,
        COUNT(g.id) as total_guias,
        SUM(g.valor_repasse) as valor_pendente,
        MIN(g.data_agendamento) as data_mais_antiga
    FROM guias g
    JOIN clinicas cl ON g.clinica_id = cl.id
    WHERE g.repasse_pago = 0 
    AND g.pago = 1
    AND g.status = 'Realizado'
    GROUP BY g.clinica_id, cl.nome_fantasia, cl.telefone_financeiro, cl.email_financeiro
    HAVING valor_pendente > 0
    ORDER BY valor_pendente DESC
");

$pageTitle = 'Dashboard Financeiro';
$pageSubtitle = 'Controle financeiro completo';

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
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Financeiro</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard Financeiro</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Controle completo das finanças do sistema
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <a href="recibos.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="receipt" class="w-4 h-4"></i>
                        Recibos
                    </a>
                    
                    <a href="repasses.php" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i data-lucide="arrow-right-left" class="w-4 h-4"></i>
                        Repasses
                    </a>
                    
                    <button onclick="exportFinanceiro()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Exportar
                    </button>
                </div>
            </div>
            
            <!-- Filtros de Período -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    
                    <div>
                        <label for="periodo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Período
                        </label>
                        <select id="periodo" name="periodo" class="input-destacado" onchange="toggleCustomDates()">
                            <option value="hoje" <?php echo $periodo === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                            <option value="semana_atual" <?php echo $periodo === 'semana_atual' ? 'selected' : ''; ?>>Semana Atual</option>
                            <option value="mes_atual" <?php echo $periodo === 'mes_atual' ? 'selected' : ''; ?>>Mês Atual</option>
                            <option value="mes_passado" <?php echo $periodo === 'mes_passado' ? 'selected' : ''; ?>>Mês Passado</option>
                            <option value="ano_atual" <?php echo $periodo === 'ano_atual' ? 'selected' : ''; ?>>Ano Atual</option>
                            <option value="personalizado" <?php echo $periodo === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>
                    
                    <div id="custom-dates" style="display: <?php echo $periodo === 'personalizado' ? 'flex' : 'none'; ?>;" class="gap-4">
                        <div>
                            <label for="data_inicio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Data Início
                            </label>
                            <input type="date" 
                                   id="data_inicio" 
                                   name="data_inicio" 
                                   value="<?php echo htmlspecialchars($data_inicio); ?>"
                                   class="input-destacado">
                        </div>
                        
                        <div>
                            <label for="data_fim" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Data Fim
                            </label>
                            <input type="date" 
                                   id="data_fim" 
                                   name="data_fim" 
                                   value="<?php echo htmlspecialchars($data_fim); ?>"
                                   class="input-destacado">
                        </div>
                    </div>
                    
                    <div class="flex items-end">
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                    </div>
                    
                </form>
            </div>
            
            <!-- Estatísticas Principais -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Faturamento Total</p>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">R$ <?php echo number_format($stats['valor_total'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo $stats['total_guias']; ?> guias</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="trending-up" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Recebido</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">R$ <?php echo number_format($stats['valor_recebido'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo $stats['guias_pagas']; ?> pagas</p>
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
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400">R$ <?php echo number_format($stats['valor_pendente'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo $stats['guias_pendentes']; ?> pendentes</p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                            <i data-lucide="clock" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Lucro Líquido</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">R$ <?php echo number_format($stats['lucro_total'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <?php 
                                $margem = $stats['valor_total'] > 0 ? (($stats['lucro_total'] / $stats['valor_total']) * 100) : 0;
                                echo number_format($margem, 1); ?>% margem
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Estatísticas de Repasse -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Controle de Repasses</h3>
                        <i data-lucide="arrow-right-left" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Total a Repassar:</span>
                            <span class="font-semibold text-gray-900 dark:text-white">R$ <?php echo number_format($stats['valor_repasse'], 2, ',', '.'); ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Já Repassado:</span>
                            <span class="font-semibold text-green-600 dark:text-green-400">R$ <?php echo number_format($stats['repasse_efetuado'], 2, ',', '.'); ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-400">Pendente de Repasse:</span>
                            <span class="font-semibold text-red-600 dark:text-red-400">R$ <?php echo number_format($stats['repasse_pendente'], 2, ',', '.'); ?></span>
                        </div>
                        
                        <div class="pt-2">
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <?php 
                                $percentualRepasse = $stats['valor_repasse'] > 0 ? (($stats['repasse_efetuado'] / $stats['valor_repasse']) * 100) : 0;
                                ?>
                                <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $percentualRepasse; ?>%"></div>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                <?php echo number_format($percentualRepasse, 1); ?>% dos repasses efetuados
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Taxa de Recebimento</h3>
                        <i data-lucide="percent" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    
                    <div class="space-y-4">
                        <?php 
                        $taxaRecebimento = $stats['total_guias'] > 0 ? (($stats['guias_pagas'] / $stats['total_guias']) * 100) : 0;
                        $taxaPendente = 100 - $taxaRecebimento;
                        ?>
                        
                        <div class="text-center">
                            <div class="text-4xl font-bold text-blue-600 dark:text-blue-400 mb-2">
                                <?php echo number_format($taxaRecebimento, 1); ?>%
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">Taxa de Recebimento</p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400"><?php echo $stats['guias_pagas']; ?></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pagas</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-red-600 dark:text-red-400"><?php echo $stats['guias_pendentes']; ?></p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Pendentes</p>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Gráficos e Análises -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Distribuição por Status -->
                <?php if (!empty($statusStats)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Distribuição por Status</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php 
                                $statusColors = [
                                    'Agendado' => 'blue',
                                    'Confirmado' => 'green',
                                    'Realizado' => 'purple',
                                    'Cancelado' => 'red',
                                    'Faltou' => 'yellow'
                                ];
                                foreach ($statusStats as $stat): 
                                    $color = $statusColors[$stat['status']] ?? 'gray';
                                    $percentual = $stats['total_guias'] > 0 ? (($stat['quantidade'] / $stats['total_guias']) * 100) : 0;
                                ?>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-4 h-4 bg-<?php echo $color; ?>-500 rounded-full"></div>
                                            <span class="text-gray-900 dark:text-white"><?php echo $stat['status']; ?></span>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-900 dark:text-white"><?php echo $stat['quantidade']; ?></p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">R$ <?php echo number_format($stat['valor_total'], 2, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-<?php echo $color; ?>-500 h-2 rounded-full" style="width: <?php echo $percentual; ?>%"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Formas de Pagamento -->
                <?php if (!empty($pagamentoStats)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Formas de Pagamento</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php 
                                $totalPago = array_sum(array_column($pagamentoStats, 'valor_total'));
                                foreach ($pagamentoStats as $stat): 
                                    $percentual = $totalPago > 0 ? (($stat['valor_total'] / $totalPago) * 100) : 0;
                                ?>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <i data-lucide="<?php echo $stat['forma_pagamento'] === 'PIX' ? 'smartphone' : ($stat['forma_pagamento'] === 'Dinheiro' ? 'banknote' : 'credit-card'); ?>" class="w-4 h-4 text-gray-400"></i>
                                            <span class="text-gray-900 dark:text-white"><?php echo $stat['forma_pagamento']; ?></span>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-900 dark:text-white"><?php echo $stat['quantidade']; ?></p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">R$ <?php echo number_format($stat['valor_total'], 2, ',', '.'); ?></p>
                                        </div>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $percentual; ?>%"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
            
            <!-- Top Clínicas -->
            <?php if (!empty($topClinicas)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Clínicas por Faturamento</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Clínica</th>
                                    <th>Guias</th>
                                    <th>Faturamento</th>
                                    <th>Repasse</th>
                                    <th>Lucro</th>
                                    <th>Margem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topClinicas as $clinica): ?>
                                    <tr>
                                        <td>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($clinica['nome_fantasia']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <span class="text-gray-900 dark:text-white"><?php echo $clinica['total_guias']; ?></span>
                                        </td>
                                        <td>
                                            <span class="font-semibold text-blue-600 dark:text-blue-400">
                                                R$ <?php echo number_format($clinica['valor_total'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="font-semibold text-green-600 dark:text-green-400">
                                                R$ <?php echo number_format($clinica['valor_repasse'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="font-semibold text-purple-600 dark:text-purple-400">
                                                R$ <?php echo number_format($clinica['lucro'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $margem = $clinica['valor_total'] > 0 ? (($clinica['lucro'] / $clinica['valor_total']) * 100) : 0;
                                            ?>
                                            <span class="text-gray-900 dark:text-white">
                                                <?php echo number_format($margem, 1); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Alertas Financeiros -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Guias em Atraso -->
                <?php if (!empty($guiasAtrasadas)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <i data-lucide="alert-triangle" class="w-5 h-5 text-red-500"></i>
                                Pagamentos em Atraso
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <?php foreach ($guiasAtrasadas as $guia): ?>
                                    <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($guia['numero_guia']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo htmlspecialchars($guia['cliente_nome']); ?>
                                            </p>
                                            <p class="text-xs text-red-600 dark:text-red-400">
                                                <?php echo $guia['dias_atraso']; ?> dias de atraso
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-red-600 dark:text-red-400">
                                                R$ <?php echo number_format($guia['valor_exame'], 2, ',', '.'); ?>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo formatDateBR($guia['data_agendamento']); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($guiasAtrasadas) >= 10): ?>
                                <div class="mt-4 text-center">
                                    <a href="guias.php?pago=0&status=Realizado" class="text-blue-600 dark:text-blue-400 hover:underline">
                                        Ver todas as guias em atraso
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Repasses Pendentes -->
                <?php if (!empty($repassesPendentes)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                <i data-lucide="clock" class="w-5 h-5 text-yellow-500"></i>
                                Repasses Pendentes
                            </h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <?php foreach ($repassesPendentes as $repasse): ?>
                                    <div class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($repasse['nome_fantasia']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo $repasse['total_guias']; ?> guia<?php echo $repasse['total_guias'] > 1 ? 's' : ''; ?>
                                            </p>
                                            <p class="text-xs text-yellow-600 dark:text-yellow-400">
                                                Desde <?php echo formatDateBR($repasse['data_mais_antiga']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-yellow-600 dark:text-yellow-400">
                                                R$ <?php echo number_format($repasse['valor_pendente'], 2, ',', '.'); ?>
                                            </p>
                                            <?php if ($repasse['telefone_financeiro']): ?>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    <?php echo formatPhone($repasse['telefone_financeiro']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <a href="repasses.php" class="text-blue-600 dark:text-blue-400 hover:underline">
                                    Gerenciar todos os repasses
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
// Função para alternar datas personalizadas
function toggleCustomDates() {
    const periodo = document.getElementById('periodo').value;
    const customDates = document.getElementById('custom-dates');
    
    if (periodo === 'personalizado') {
        customDates.style.display = 'flex';
    } else {
        customDates.style.display = 'none';
        // Auto-submit quando não é personalizado
        document.querySelector('form').submit();
    }
}

// Função para exportar dados financeiros
function exportFinanceiro() {
    showToast('Exportando dados financeiros...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Dados exportados com sucesso!', 'success');
    }, 2000);
}

// Auto-submit do formulário quando mudar período
document.getElementById('periodo').addEventListener('change', function() {
    if (this.value !== 'personalizado') {
        this.form.submit();
    }
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // R = Recibos
    if (e.key === 'r' || e.key === 'R') {
        window.location.href = 'recibos.php';
    }
    
    // P = Repasses
    if (e.key === 'p' || e.key === 'P') {
        window.location.href = 'repasses.php';
    }
    
    // Ctrl + E = Exportar
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportFinanceiro();
    }
});

// Atualização automática a cada 5 minutos
setInterval(function() {
    if (document.visibilityState === 'visible') {
        window.location.reload();
    }
}, 300000); // 5 minutos
</script>

<?php include 'templates/footer.php'; ?>
