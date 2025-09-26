<?php
// Dashboard Principal - Salvar como dashboard.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Período para análise (padrão: mês atual)
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

// Estatísticas principais
$stats = $db->select("
    SELECT 
        COUNT(*) as total_guias,
        SUM(g.valor_exame) as faturamento_total,
        SUM(CASE WHEN g.pago = 1 THEN g.valor_exame ELSE 0 END) as valor_recebido,
        SUM(CASE WHEN g.pago = 0 THEN g.valor_exame ELSE 0 END) as valor_pendente,
        SUM(g.valor_exame - g.valor_repasse) as lucro_total,
        COUNT(CASE WHEN g.status = 'Agendado' THEN 1 END) as guias_agendadas,
        COUNT(CASE WHEN g.status = 'Confirmado' THEN 1 END) as guias_confirmadas,
        COUNT(CASE WHEN g.status = 'Realizado' THEN 1 END) as guias_realizadas,
        COUNT(CASE WHEN g.status = 'Cancelado' THEN 1 END) as guias_canceladas,
        COUNT(CASE WHEN g.status = 'Faltou' THEN 1 END) as guias_faltou
    FROM guias g
    WHERE g.data_agendamento BETWEEN ? AND ?
", [$data_inicio, $data_fim])[0] ?? [
    'total_guias' => 0,
    'faturamento_total' => 0,
    'valor_recebido' => 0,
    'valor_pendente' => 0,
    'lucro_total' => 0,
    'guias_agendadas' => 0,
    'guias_confirmadas' => 0,
    'guias_realizadas' => 0,
    'guias_canceladas' => 0,
    'guias_faltou' => 0
];

// Estatísticas gerais do sistema
$statsGerais = $db->select("
    SELECT 
        (SELECT COUNT(*) FROM clientes WHERE ativo = 1) as total_clientes,
        (SELECT COUNT(*) FROM clinicas WHERE ativo = 1) as total_clinicas,
        (SELECT COUNT(*) FROM exames WHERE ativo = 1) as total_exames,
        (SELECT COUNT(*) FROM usuarios WHERE ativo = 1) as total_usuarios
")[0];

// Guias de hoje
$guiasHoje = $db->select("
    SELECT g.*, 
           c.nome_completo as cliente_nome,
           cl.nome_fantasia as clinica_nome,
           e.nome as exame_nome,
           m.nome_completo as medico_nome
    FROM guias g
    LEFT JOIN clientes c ON g.cliente_id = c.id
    LEFT JOIN clinicas cl ON g.clinica_id = cl.id
    LEFT JOIN exames e ON g.exame_id = e.id
    LEFT JOIN medicos m ON g.medico_id = m.id
    WHERE DATE(g.data_agendamento) = CURDATE()
    ORDER BY g.hora_agendamento ASC, g.data_cadastro DESC
    LIMIT 10
");

// Aniversariantes do mês
$aniversariantes = $db->select("
    SELECT nome_completo, data_nascimento, telefone1, email
    FROM clientes 
    WHERE ativo = 1 
    AND MONTH(data_nascimento) = MONTH(CURDATE())
    AND DAY(data_nascimento) >= DAY(CURDATE())
    ORDER BY DAY(data_nascimento) ASC
    LIMIT 5
");

// Top clínicas por faturamento
$topClinicas = $db->select("
    SELECT 
        cl.nome_fantasia,
        COUNT(g.id) as total_guias,
        SUM(g.valor_exame) as faturamento,
        SUM(g.valor_exame - g.valor_repasse) as lucro
    FROM guias g
    JOIN clinicas cl ON g.clinica_id = cl.id
    WHERE g.data_agendamento BETWEEN ? AND ?
    GROUP BY g.clinica_id, cl.nome_fantasia
    ORDER BY faturamento DESC
    LIMIT 5
", [$data_inicio, $data_fim]);

// Evolução diária (últimos 30 dias)
$evolucaoDiaria = $db->select("
    SELECT 
        DATE(g.data_agendamento) as data,
        COUNT(*) as total_guias,
        SUM(g.valor_exame) as faturamento
    FROM guias g
    WHERE g.data_agendamento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(g.data_agendamento)
    ORDER BY data ASC
");

// Distribuição por tipo de serviço
$tiposServico = $db->select("
    SELECT 
        g.tipo_servico,
        COUNT(*) as quantidade,
        SUM(g.valor_exame) as valor_total
    FROM guias g
    WHERE g.data_agendamento BETWEEN ? AND ?
    GROUP BY g.tipo_servico
    ORDER BY quantidade DESC
", [$data_inicio, $data_fim]);

// Alertas e notificações
$alertas = [];

// Pagamentos em atraso
$pagamentosAtraso = $db->select("
    SELECT COUNT(*) as total
    FROM guias 
    WHERE pago = 0 
    AND status IN ('Realizado', 'Confirmado')
    AND DATEDIFF(CURDATE(), data_agendamento) > 30
")[0]['total'];

if ($pagamentosAtraso > 0) {
    $alertas[] = [
        'tipo' => 'warning',
        'icone' => 'alert-triangle',
        'titulo' => 'Pagamentos em Atraso',
        'mensagem' => "$pagamentosAtraso guia(s) com pagamento em atraso há mais de 30 dias",
        'link' => 'recibos.php?status_pagamento=0'
    ];
}

// Repasses pendentes
$repassesPendentes = $db->select("
    SELECT COUNT(*) as total, SUM(valor_repasse) as valor
    FROM guias 
    WHERE repasse_pago = 0 
    AND pago = 1
    AND status = 'Realizado'
")[0];

if ($repassesPendentes['total'] > 0) {
    $alertas[] = [
        'tipo' => 'info',
        'icone' => 'arrow-right-left',
        'titulo' => 'Repasses Pendentes',
        'mensagem' => $repassesPendentes['total'] . " repasse(s) pendente(s) - R$ " . number_format($repassesPendentes['valor'], 2, ',', '.'),
        'link' => 'repasses.php?status_repasse=0'
    ];
}

// Aniversariantes hoje
$aniversariantesHoje = $db->select("
    SELECT COUNT(*) as total
    FROM clientes 
    WHERE ativo = 1 
    AND MONTH(data_nascimento) = MONTH(CURDATE())
    AND DAY(data_nascimento) = DAY(CURDATE())
")[0]['total'];

if ($aniversariantesHoje > 0) {
    $alertas[] = [
        'tipo' => 'success',
        'icone' => 'cake',
        'titulo' => 'Aniversariantes Hoje',
        'mensagem' => "$aniversariantesHoje cliente(s) fazem aniversário hoje",
        'link' => 'aniversario_cliente.php'
    ];
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Visão geral do sistema';

include 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100 dark:bg-gray-900">
    
    <?php include 'templates/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <?php include 'templates/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-900 p-6">
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Bem-vindo de volta, <?php echo htmlspecialchars(explode(' ', $_SESSION['nome_completo'])[0]); ?>!
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <!-- Filtro de Período -->
                    <form method="GET" class="flex items-center gap-2">
                        <select name="periodo" onchange="this.form.submit()" class="input-destacado">
                            <option value="hoje" <?php echo $periodo === 'hoje' ? 'selected' : ''; ?>>Hoje</option>
                            <option value="semana_atual" <?php echo $periodo === 'semana_atual' ? 'selected' : ''; ?>>Semana Atual</option>
                            <option value="mes_atual" <?php echo $periodo === 'mes_atual' ? 'selected' : ''; ?>>Mês Atual</option>
                            <option value="mes_passado" <?php echo $periodo === 'mes_passado' ? 'selected' : ''; ?>>Mês Passado</option>
                            <option value="ano_atual" <?php echo $periodo === 'ano_atual' ? 'selected' : ''; ?>>Ano Atual</option>
                        </select>
                    </form>
                    
                    <button onclick="atualizarDashboard()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        Atualizar
                    </button>
                </div>
            </div>
            
            <!-- Alertas -->
            <?php if (!empty($alertas)): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                    <?php foreach ($alertas as $alerta): ?>
                        <div class="bg-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-50 dark:bg-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-900/20 border border-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-200 dark:border-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-800 rounded-lg p-4">
                            <div class="flex items-start gap-3">
                                <div class="w-8 h-8 bg-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-100 dark:bg-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-900 rounded-full flex items-center justify-center">
                                    <i data-lucide="<?php echo $alerta['icone']; ?>" class="w-4 h-4 text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-600 dark:text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-400"></i>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-800 dark:text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-200">
                                        <?php echo $alerta['titulo']; ?>
                                    </h4>
                                    <p class="text-sm text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-700 dark:text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-300 mt-1">
                                        <?php echo $alerta['mensagem']; ?>
                                    </p>
                                    <a href="<?php echo $alerta['link']; ?>" class="text-sm text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-600 dark:text-<?php echo $alerta['tipo'] === 'warning' ? 'yellow' : ($alerta['tipo'] === 'success' ? 'green' : 'blue'); ?>-400 hover:underline mt-2 inline-block">
                                        Ver detalhes →
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Estatísticas Principais -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Guias</p>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo $stats['total_guias']; ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <?php 
                                $taxaRealizacao = $stats['total_guias'] > 0 ? (($stats['guias_realizadas'] / $stats['total_guias']) * 100) : 0;
                                echo number_format($taxaRealizacao, 1); ?>% realizadas
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="file-text" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Faturamento</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">R$ <?php echo number_format($stats['faturamento_total'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                R$ <?php echo number_format($stats['valor_recebido'], 2, ',', '.'); ?> recebido
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
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
                                $margem = $stats['faturamento_total'] > 0 ? (($stats['lucro_total'] / $stats['faturamento_total']) * 100) : 0;
                                echo number_format($margem, 1); ?>% margem
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="trending-up" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pendente</p>
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400">R$ <?php echo number_format($stats['valor_pendente'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                <?php 
                                $taxaPendente = $stats['faturamento_total'] > 0 ? (($stats['valor_pendente'] / $stats['faturamento_total']) * 100) : 0;
                                echo number_format($taxaPendente, 1); ?>% do total
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                            <i data-lucide="clock" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Estatísticas Gerais do Sistema -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clientes</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $statsGerais['total_clientes']; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="users" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clínicas</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $statsGerais['total_clinicas']; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="building" class="w-5 h-5 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Exames</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $statsGerais['total_exames']; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="clipboard-list" class="w-5 h-5 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Usuários</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $statsGerais['total_usuarios']; ?></p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                            <i data-lucide="user-check" class="w-5 h-5 text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Gráficos e Análises -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                
                <!-- Distribuição por Status -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Distribuição por Status</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <?php 
                            $statusData = [
                                'Agendado' => ['count' => $stats['guias_agendadas'], 'color' => 'blue'],
                                'Confirmado' => ['count' => $stats['guias_confirmadas'], 'color' => 'green'],
                                'Realizado' => ['count' => $stats['guias_realizadas'], 'color' => 'purple'],
                                'Cancelado' => ['count' => $stats['guias_canceladas'], 'color' => 'red'],
                                'Faltou' => ['count' => $stats['guias_faltou'], 'color' => 'yellow']
                            ];
                            
                            foreach ($statusData as $status => $data):
                                $percentual = $stats['total_guias'] > 0 ? (($data['count'] / $stats['total_guias']) * 100) : 0;
                            ?>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-4 h-4 bg-<?php echo $data['color']; ?>-500 rounded-full"></div>
                                        <span class="text-gray-900 dark:text-white"><?php echo $status; ?></span>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo $data['count']; ?></p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo number_format($percentual, 1); ?>%</p>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-<?php echo $data['color']; ?>-500 h-2 rounded-full" style="width: <?php echo $percentual; ?>%"></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tipos de Serviço -->
                <?php if (!empty($tiposServico)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Tipos de Serviço</h3>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($tiposServico as $tipo): 
                                    $percentual = $stats['total_guias'] > 0 ? (($tipo['quantidade'] / $stats['total_guias']) * 100) : 0;
                                ?>
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <i data-lucide="<?php echo $tipo['tipo_servico'] === 'Exame' ? 'clipboard-list' : 'user-check'; ?>" class="w-4 h-4 text-gray-400"></i>
                                            <span class="text-gray-900 dark:text-white"><?php echo $tipo['tipo_servico']; ?></span>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-gray-900 dark:text-white"><?php echo $tipo['quantidade']; ?></p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">R$ <?php echo number_format($tipo['valor_total'], 2, ',', '.'); ?></p>
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
            
            <!-- Listas e Informações -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Guias de Hoje -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Guias de Hoje</h3>
                            <a href="guias.php?data_inicio=<?php echo date('Y-m-d'); ?>&data_fim=<?php echo date('Y-m-d'); ?>" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                Ver todas
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($guiasHoje)): ?>
                            <div class="text-center py-8">
                                <i data-lucide="calendar" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400">Nenhuma guia para hoje</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($guiasHoje as $guia): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white text-sm">
                                                <?php echo htmlspecialchars($guia['cliente_nome']); ?>
                                            </p>
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                <?php echo htmlspecialchars($guia['clinica_nome']); ?>
                                            </p>
                                            <?php if ($guia['hora_agendamento']): ?>
                                                <p class="text-xs text-blue-600 dark:text-blue-400">
                                                    <?php echo date('H:i', strtotime($guia['hora_agendamento'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <span class="inline-flex items-center px-2 py-1 bg-<?php 
                                                echo $guia['status'] === 'Agendado' ? 'blue' : 
                                                    ($guia['status'] === 'Confirmado' ? 'green' : 
                                                    ($guia['status'] === 'Realizado' ? 'purple' : 
                                                    ($guia['status'] === 'Cancelado' ? 'red' : 'yellow')));
                                            ?>-100 dark:bg-<?php 
                                                echo $guia['status'] === 'Agendado' ? 'blue' : 
                                                    ($guia['status'] === 'Confirmado' ? 'green' : 
                                                    ($guia['status'] === 'Realizado' ? 'purple' : 
                                                    ($guia['status'] === 'Cancelado' ? 'red' : 'yellow')));
                                            ?>-900 text-<?php 
                                                echo $guia['status'] === 'Agendado' ? 'blue' : 
                                                    ($guia['status'] === 'Confirmado' ? 'green' : 
                                                    ($guia['status'] === 'Realizado' ? 'purple' : 
                                                    ($guia['status'] === 'Cancelado' ? 'red' : 'yellow')));
                                            ?>-800 dark:text-<?php 
                                                echo $guia['status'] === 'Agendado' ? 'blue' : 
                                                    ($guia['status'] === 'Confirmado' ? 'green' : 
                                                    ($guia['status'] === 'Realizado' ? 'purple' : 
                                                    ($guia['status'] === 'Cancelado' ? 'red' : 'yellow')));
                                            ?>-200 text-xs rounded-full">
                                                <?php echo $guia['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Aniversariantes -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Aniversariantes</h3>
                            <a href="aniversario_cliente.php" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                Ver todos
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($aniversariantes)): ?>
                            <div class="text-center py-8">
                                <i data-lucide="cake" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400">Nenhum aniversariante este mês</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($aniversariantes as $aniversariante): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-pink-100 dark:bg-pink-900 rounded-full flex items-center justify-center">
                                                <i data-lucide="cake" class="w-4 h-4 text-pink-600 dark:text-pink-400"></i>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white text-sm">
                                                    <?php echo htmlspecialchars($aniversariante['nome_completo']); ?>
                                                </p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                                    <?php echo date('d/m', strtotime($aniversariante['data_nascimento'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <?php 
                                            $diasAte = (strtotime(date('Y') . '-' . date('m-d', strtotime($aniversariante['data_nascimento']))) - strtotime(date('Y-m-d'))) / 86400;
                                            if ($diasAte < 0) $diasAte += 365;
                                            ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo $diasAte == 0 ? 'Hoje!' : ($diasAte == 1 ? 'Amanhã' : intval($diasAte) . ' dias'); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Top Clínicas -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Clínicas</h3>
                            <a href="clinicas.php" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
                                Ver todas
                            </a>
                        </div>
                    </div>
                    <div class="p-6">
                        <?php if (empty($topClinicas)): ?>
                            <div class="text-center py-8">
                                <i data-lucide="building" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                <p class="text-gray-500 dark:text-gray-400">Nenhuma clínica no período</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($topClinicas as $index => $clinica): ?>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                                <span class="text-blue-600 dark:text-blue-400 font-bold text-sm"><?php echo $index + 1; ?></span>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white text-sm">
                                                    <?php echo htmlspecialchars($clinica['nome_fantasia']); ?>
                                                </p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                                    <?php echo $clinica['total_guias']; ?> guia<?php echo $clinica['total_guias'] > 1 ? 's' : ''; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold text-green-600 dark:text-green-400 text-sm">
                                                R$ <?php echo number_format($clinica['faturamento'], 2, ',', '.'); ?>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Lucro: R$ <?php echo number_format($clinica['lucro'], 2, ',', '.'); ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
            
            <!-- Ações Rápidas -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ações Rápidas</h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        
                        <a href="guia_formulario.php" class="flex flex-col items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mb-3">
                                <i data-lucide="plus" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <span class="text-sm font-medium text-blue-800 dark:text-blue-200">Nova Guia</span>
                        </a>
                        
                        <a href="cliente_formulario.php" class="flex flex-col items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                            <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mb-3">
                                <i data-lucide="user-plus" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                            </div>
                            <span class="text-sm font-medium text-green-800 dark:text-green-200">Novo Cliente</span>
                        </a>
                        
                        <a href="clinica_formulario.php" class="flex flex-col items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors">
                            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center mb-3">
                                <i data-lucide="building" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                            </div>
                            <span class="text-sm font-medium text-purple-800 dark:text-purple-200">Nova Clínica</span>
                        </a>
                        
                        <a href="recibos.php" class="flex flex-col items-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg hover:bg-yellow-100 dark:hover:bg-yellow-900/30 transition-colors">
                            <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center mb-3">
                                <i data-lucide="receipt" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                            </div>
                            <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Recibos</span>
                        </a>
                        
                        <a href="repasses.php" class="flex flex-col items-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                            <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center mb-3">
                                <i data-lucide="arrow-right-left" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
                            </div>
                            <span class="text-sm font-medium text-red-800 dark:text-red-200">Repasses</span>
                        </a>
                        
                        <a href="financeiro.php" class="flex flex-col items-center p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors">
                            <div class="w-12 h-12 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mb-3">
                                <i data-lucide="bar-chart-3" class="w-6 h-6 text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200">Financeiro</span>
                        </a>
                        
                    </div>
                </div>
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
// Função para atualizar dashboard
function atualizarDashboard() {
    showToast('Atualizando dashboard...', 'info');
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Auto-atualização a cada 5 minutos
setInterval(function() {
    if (document.visibilityState === 'visible') {
        // Atualiza apenas os dados dinâmicos via AJAX
        atualizarDadosDinamicos();
    }
}, 300000); // 5 minutos

// Função para atualizar dados dinâmicos
function atualizarDadosDinamicos() {
    // Implementar atualização via AJAX dos dados que mudam frequentemente
    // como guias de hoje, alertas, etc.
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // F5 = Atualizar
    if (e.key === 'F5') {
        e.preventDefault();
        atualizarDashboard();
    }
    
    // Ctrl + N = Nova guia
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'guia_formulario.php';
    }
    
    // Ctrl + U = Novo cliente
    if (e.ctrlKey && e.key === 'u') {
        e.preventDefault();
        window.location.href = 'cliente_formulario.php';
    }
});

// Animações de entrada
document.addEventListener('DOMContentLoaded', function() {
    // Anima os cards de estatísticas
    const cards = document.querySelectorAll('.dashboard-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php include 'templates/footer.php'; ?>
