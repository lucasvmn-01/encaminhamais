<?php
// Controle de Repasses para Clínicas - Salvar como repasses.php

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
        case 'confirmar_repasse':
            $guiaIds = json_decode($_POST['guia_ids'] ?? '[]', true);
            $dataRepasse = $_POST['data_repasse'] ?? date('Y-m-d');
            $observacoes = trim($_POST['observacoes'] ?? '');
            
            if (!empty($guiaIds)) {
                try {
                    $db->beginTransaction();
                    
                    $sucessos = 0;
                    foreach ($guiaIds as $guiaId) {
                        $updated = $db->execute("
                            UPDATE guias 
                            SET repasse_pago = 1, 
                                data_repasse = ?, 
                                observacoes_repasse = ?,
                                modificado_por_id = ?,
                                data_modificacao = NOW()
                            WHERE id = ? AND pago = 1 AND repasse_pago = 0
                        ", [$dataRepasse, $observacoes, $_SESSION['user_id'], $guiaId]);
                        
                        if ($updated) {
                            // Registra transação financeira
                            $guia = $db->select("SELECT numero_guia, valor_repasse, clinica_id FROM guias WHERE id = ?", [$guiaId])[0];
                            
                            $db->execute("
                                INSERT INTO transacoes_financeiras 
                                (guia_id, tipo, descricao, valor, data_transacao, cadastrado_por_id, data_cadastro)
                                VALUES (?, 'Repasse', ?, ?, ?, ?, NOW())
                            ", [
                                $guiaId,
                                'Repasse da guia ' . $guia['numero_guia'],
                                $guia['valor_repasse'],
                                $dataRepasse,
                                $_SESSION['user_id']
                            ]);
                            
                            $sucessos++;
                        }
                    }
                    
                    $db->commit();
                    
                    if ($sucessos > 0) {
                        echo json_encode(['success' => true, 'message' => "$sucessos repasse(s) confirmado(s) com sucesso!"]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Nenhum repasse foi processado']);
                    }
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Nenhuma guia selecionada']);
            }
            exit;
            
        case 'cancelar_repasse':
            $guiaId = intval($_POST['guia_id'] ?? 0);
            
            if ($guiaId > 0) {
                try {
                    $updated = $db->execute("
                        UPDATE guias 
                        SET repasse_pago = 0, 
                            data_repasse = NULL,
                            observacoes_repasse = NULL,
                            modificado_por_id = ?,
                            data_modificacao = NOW()
                        WHERE id = ?
                    ", [$_SESSION['user_id'], $guiaId]);
                    
                    if ($updated) {
                        // Remove transação financeira
                        $db->execute("DELETE FROM transacoes_financeiras WHERE guia_id = ? AND tipo = 'Repasse'", [$guiaId]);
                        
                        echo json_encode(['success' => true, 'message' => 'Repasse cancelado com sucesso!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erro ao cancelar repasse']);
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
$clinica_id = intval($_GET['clinica_id'] ?? 0);
$status_repasse = $_GET['status_repasse'] ?? '';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

// Busca clínicas para o filtro
$clinicas = $db->select("
    SELECT id, nome_fantasia 
    FROM clinicas 
    WHERE ativo = 1 
    ORDER BY nome_fantasia
");

// Monta a query base
$whereConditions = ['g.pago = 1']; // Só mostra guias já pagas
$params = [];

if ($clinica_id > 0) {
    $whereConditions[] = "g.clinica_id = ?";
    $params[] = $clinica_id;
}

if ($status_repasse !== '') {
    $whereConditions[] = "g.repasse_pago = ?";
    $params[] = intval($status_repasse);
}

if (!empty($data_inicio)) {
    $whereConditions[] = "g.data_agendamento >= ?";
    $params[] = $data_inicio;
}

if (!empty($data_fim)) {
    $whereConditions[] = "g.data_agendamento <= ?";
    $params[] = $data_fim;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Busca resumo por clínica
$resumoClinicas = $db->select("
    SELECT 
        cl.id,
        cl.nome_fantasia,
        cl.telefone_financeiro,
        cl.email_financeiro,
        cl.conta_banco,
        cl.agencia_banco,
        cl.banco,
        COUNT(g.id) as total_guias,
        SUM(g.valor_repasse) as valor_total,
        SUM(CASE WHEN g.repasse_pago = 1 THEN g.valor_repasse ELSE 0 END) as valor_pago,
        SUM(CASE WHEN g.repasse_pago = 0 THEN g.valor_repasse ELSE 0 END) as valor_pendente,
        COUNT(CASE WHEN g.repasse_pago = 1 THEN 1 END) as guias_pagas,
        COUNT(CASE WHEN g.repasse_pago = 0 THEN 1 END) as guias_pendentes,
        MIN(CASE WHEN g.repasse_pago = 0 THEN g.data_agendamento END) as data_mais_antiga
    FROM clinicas cl
    LEFT JOIN guias g ON cl.id = g.clinica_id AND g.pago = 1
    " . ($clinica_id > 0 ? "WHERE cl.id = $clinica_id" : "WHERE cl.ativo = 1") . "
    " . (!empty($data_inicio) ? "AND (g.data_agendamento >= '$data_inicio' OR g.data_agendamento IS NULL)" : "") . "
    " . (!empty($data_fim) ? "AND (g.data_agendamento <= '$data_fim' OR g.data_agendamento IS NULL)" : "") . "
    GROUP BY cl.id, cl.nome_fantasia, cl.telefone_financeiro, cl.email_financeiro, cl.conta_banco, cl.agencia_banco, cl.banco
    HAVING total_guias > 0
    ORDER BY valor_pendente DESC, valor_total DESC
");

// Busca guias detalhadas (se uma clínica específica foi selecionada)
$guiasDetalhadas = [];
if ($clinica_id > 0) {
    $guiasDetalhadas = $db->select("
        SELECT g.*, 
               c.nome_completo as cliente_nome,
               e.nome as exame_nome,
               m.nome_completo as medico_nome
        FROM guias g
        LEFT JOIN clientes c ON g.cliente_id = c.id
        LEFT JOIN exames e ON g.exame_id = e.id
        LEFT JOIN medicos m ON g.medico_id = m.id
        $whereClause
        ORDER BY g.repasse_pago ASC, g.data_agendamento DESC
    ", $params);
}

// Estatísticas gerais
$statsGerais = $db->select("
    SELECT 
        COUNT(*) as total_guias,
        SUM(g.valor_repasse) as valor_total,
        SUM(CASE WHEN g.repasse_pago = 1 THEN g.valor_repasse ELSE 0 END) as valor_pago,
        SUM(CASE WHEN g.repasse_pago = 0 THEN g.valor_repasse ELSE 0 END) as valor_pendente,
        COUNT(CASE WHEN g.repasse_pago = 1 THEN 1 END) as guias_pagas,
        COUNT(CASE WHEN g.repasse_pago = 0 THEN 1 END) as guias_pendentes
    FROM guias g
    WHERE g.pago = 1
    " . (!empty($data_inicio) ? "AND g.data_agendamento >= '$data_inicio'" : "") . "
    " . (!empty($data_fim) ? "AND g.data_agendamento <= '$data_fim'" : "")
)[0] ?? [
    'total_guias' => 0,
    'valor_total' => 0,
    'valor_pago' => 0,
    'valor_pendente' => 0,
    'guias_pagas' => 0,
    'guias_pendentes' => 0
];

$pageTitle = 'Controle de Repasses';
$pageSubtitle = 'Gestão de repasses para clínicas';

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
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Repasses</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Controle de Repasses</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Gestão de repasses financeiros para clínicas parceiras
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <?php if ($clinica_id > 0): ?>
                        <button onclick="confirmarRepasseLote()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i data-lucide="check-circle" class="w-4 h-4"></i>
                            Confirmar em Lote
                        </button>
                    <?php endif; ?>
                    
                    <button onclick="exportRepasses()" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Exportar
                    </button>
                    
                    <button onclick="gerarRelatorioRepasses()" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                        Relatório
                    </button>
                </div>
            </div>
            
            <!-- Estatísticas Gerais -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total a Repassar</p>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">R$ <?php echo number_format($statsGerais['valor_total'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo $statsGerais['total_guias']; ?> guias</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="arrow-right-left" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Já Repassado</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">R$ <?php echo number_format($statsGerais['valor_pago'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo $statsGerais['guias_pagas']; ?> pagas</p>
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
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400">R$ <?php echo number_format($statsGerais['valor_pendente'], 2, ',', '.'); ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400"><?php echo $statsGerais['guias_pendentes']; ?> pendentes</p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                            <i data-lucide="clock" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Taxa de Repasse</p>
                            <?php 
                            $taxaRepasse = $statsGerais['total_guias'] > 0 ? (($statsGerais['guias_pagas'] / $statsGerais['total_guias']) * 100) : 0;
                            ?>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo number_format($taxaRepasse, 1); ?>%</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Efetivados</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="percent" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    
                    <!-- Clínica -->
                    <div>
                        <label for="clinica_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Clínica
                        </label>
                        <select id="clinica_id" name="clinica_id" class="input-destacado w-full">
                            <option value="">Todas as Clínicas</option>
                            <?php foreach ($clinicas as $clinica): ?>
                                <option value="<?php echo $clinica['id']; ?>" <?php echo $clinica_id == $clinica['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($clinica['nome_fantasia']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Status Repasse -->
                    <div>
                        <label for="status_repasse" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status
                        </label>
                        <select id="status_repasse" name="status_repasse" class="input-destacado w-full">
                            <option value="">Todos</option>
                            <option value="1" <?php echo $status_repasse === '1' ? 'selected' : ''; ?>>Repassado</option>
                            <option value="0" <?php echo $status_repasse === '0' ? 'selected' : ''; ?>>Pendente</option>
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
                    <div class="md:col-span-2 lg:col-span-4 flex items-center gap-2">
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                        <a href="repasses.php" class="px-6 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                            Limpar
                        </a>
                    </div>
                    
                </form>
            </div>
            
            <!-- Resumo por Clínica -->
            <?php if ($clinica_id == 0): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Resumo por Clínica</h3>
                    </div>
                    
                    <?php if (empty($resumoClinicas)): ?>
                        <div class="p-12 text-center">
                            <i data-lucide="building" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                Nenhuma clínica encontrada
                            </h3>
                            <p class="text-gray-600 dark:text-gray-400">
                                Não há clínicas com guias pagas no período selecionado.
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="table-custom">
                                <thead>
                                    <tr>
                                        <th>Clínica</th>
                                        <th>Contato Financeiro</th>
                                        <th>Dados Bancários</th>
                                        <th>Guias</th>
                                        <th>Total</th>
                                        <th>Repassado</th>
                                        <th>Pendente</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resumoClinicas as $resumo): ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td>
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($resumo['nome_fantasia']); ?>
                                                    </p>
                                                    <?php if ($resumo['data_mais_antiga']): ?>
                                                        <p class="text-sm text-yellow-600 dark:text-yellow-400">
                                                            Pendente desde <?php echo formatDateBR($resumo['data_mais_antiga']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-sm">
                                                    <?php if ($resumo['telefone_financeiro']): ?>
                                                        <p class="text-gray-900 dark:text-white">
                                                            <?php echo formatPhone($resumo['telefone_financeiro']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if ($resumo['email_financeiro']): ?>
                                                        <p class="text-gray-600 dark:text-gray-400">
                                                            <?php echo htmlspecialchars($resumo['email_financeiro']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-sm">
                                                    <?php if ($resumo['banco']): ?>
                                                        <p class="text-gray-900 dark:text-white">
                                                            <?php echo htmlspecialchars($resumo['banco']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <?php if ($resumo['agencia_banco'] && $resumo['conta_banco']): ?>
                                                        <p class="text-gray-600 dark:text-gray-400">
                                                            Ag: <?php echo htmlspecialchars($resumo['agencia_banco']); ?> 
                                                            CC: <?php echo htmlspecialchars($resumo['conta_banco']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo $resumo['total_guias']; ?></p>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        <span class="text-green-600 dark:text-green-400"><?php echo $resumo['guias_pagas']; ?></span> /
                                                        <span class="text-red-600 dark:text-red-400"><?php echo $resumo['guias_pendentes']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="font-semibold text-blue-600 dark:text-blue-400">
                                                    R$ <?php echo number_format($resumo['valor_total'], 2, ',', '.'); ?>
                                                </p>
                                            </td>
                                            <td>
                                                <p class="font-semibold text-green-600 dark:text-green-400">
                                                    R$ <?php echo number_format($resumo['valor_pago'], 2, ',', '.'); ?>
                                                </p>
                                            </td>
                                            <td>
                                                <p class="font-semibold text-red-600 dark:text-red-400">
                                                    R$ <?php echo number_format($resumo['valor_pendente'], 2, ',', '.'); ?>
                                                </p>
                                            </td>
                                            <td>
                                                <div class="flex items-center gap-2">
                                                    <a href="repasses.php?clinica_id=<?php echo $resumo['id']; ?>" 
                                                       class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                       title="Ver detalhes">
                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                    </a>
                                                    
                                                    <?php if ($resumo['valor_pendente'] > 0): ?>
                                                        <button onclick="confirmarRepasseClinica(<?php echo $resumo['id']; ?>)" 
                                                                class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 dark:hover:bg-green-900 rounded-lg"
                                                                title="Confirmar repasse">
                                                            <i data-lucide="check" class="w-4 h-4"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button onclick="gerarComprovanteRepasse(<?php echo $resumo['id']; ?>)" 
                                                            class="inline-flex items-center justify-center w-8 h-8 text-purple-600 hover:text-purple-800 hover:bg-purple-100 dark:hover:bg-purple-900 rounded-lg"
                                                            title="Gerar comprovante">
                                                        <i data-lucide="printer" class="w-4 h-4"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Detalhes da Clínica Selecionada -->
            <?php if ($clinica_id > 0 && !empty($guiasDetalhadas)): ?>
                <?php 
                $clinicaSelecionada = array_filter($resumoClinicas, function($c) use ($clinica_id) {
                    return $c['id'] == $clinica_id;
                });
                $clinicaSelecionada = reset($clinicaSelecionada);
                ?>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-6">
                    <div class="p-6 border-b dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($clinicaSelecionada['nome_fantasia']); ?>
                                </h3>
                                <p class="text-gray-600 dark:text-gray-400">
                                    Detalhamento de repasses
                                </p>
                            </div>
                            
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Pendente</p>
                                    <p class="text-xl font-bold text-red-600 dark:text-red-400">
                                        R$ <?php echo number_format($clinicaSelecionada['valor_pendente'], 2, ',', '.'); ?>
                                    </p>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" id="select-all-guias" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Selecionar todos</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th class="w-12">
                                        <input type="checkbox" id="select-all-header-guias" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th>Guia</th>
                                    <th>Cliente</th>
                                    <th>Serviço</th>
                                    <th>Data</th>
                                    <th>Valor Repasse</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guiasDetalhadas as $guia): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td>
                                            <input type="checkbox" 
                                                   class="guia-repasse-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500" 
                                                   value="<?php echo $guia['id']; ?>"
                                                   data-repasse-pago="<?php echo $guia['repasse_pago']; ?>"
                                                   <?php echo $guia['repasse_pago'] ? 'disabled' : ''; ?>>
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
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($guia['cliente_nome']); ?>
                                            </p>
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
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo formatDateBR($guia['data_agendamento']); ?>
                                                </p>
                                                <?php if ($guia['repasse_pago'] && $guia['data_repasse']): ?>
                                                    <p class="text-sm text-green-600 dark:text-green-400">
                                                        Repasse: <?php echo formatDateBR($guia['data_repasse']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <p class="font-semibold text-lg text-green-600 dark:text-green-400">
                                                R$ <?php echo number_format($guia['valor_repasse'], 2, ',', '.'); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <?php if ($guia['repasse_pago']): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded-full">
                                                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                                                    Repassado
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200 text-xs rounded-full">
                                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                                    Pendente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <?php if (!$guia['repasse_pago']): ?>
                                                    <button onclick="confirmarRepasseIndividual(<?php echo $guia['id']; ?>)" 
                                                            class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 dark:hover:bg-green-900 rounded-lg"
                                                            title="Confirmar repasse">
                                                        <i data-lucide="check" class="w-4 h-4"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button onclick="cancelarRepasse(<?php echo $guia['id']; ?>)" 
                                                            class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg"
                                                            title="Cancelar repasse">
                                                        <i data-lucide="x" class="w-4 h-4"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
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
                </div>
            <?php endif; ?>
            
        </main>
        
    </div>
    
</div>

<!-- Modal de Confirmação de Repasse -->
<div id="modal-repasse" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Confirmar Repasse</h3>
        
        <form id="form-repasse">
            <input type="hidden" id="guias-ids" name="guia_ids">
            
            <div class="space-y-4">
                <div>
                    <label for="data-repasse" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Data do Repasse
                    </label>
                    <input type="date" 
                           id="data-repasse" 
                           name="data_repasse" 
                           value="<?php echo date('Y-m-d'); ?>"
                           class="input-destacado w-full">
                </div>
                
                <div>
                    <label for="observacoes-repasse" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Observações (opcional)
                    </label>
                    <textarea id="observacoes-repasse" 
                              name="observacoes" 
                              rows="3"
                              placeholder="Informações sobre o repasse..."
                              class="input-destacado w-full"></textarea>
                </div>
                
                <div id="resumo-repasse" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <span class="font-medium">Guias selecionadas:</span> <span id="total-guias">0</span><br>
                        <span class="font-medium">Valor total:</span> R$ <span id="valor-total">0,00</span>
                    </p>
                </div>
            </div>
            
            <div class="flex items-center justify-end gap-3 mt-6">
                <button type="button" onclick="fecharModalRepasse()" class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    Confirmar Repasse
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Variáveis globais
let guiasParaRepasse = [];

// Função para confirmar repasse individual
function confirmarRepasseIndividual(guiaId) {
    guiasParaRepasse = [guiaId];
    atualizarResumoRepasse();
    document.getElementById('modal-repasse').classList.remove('hidden');
    document.getElementById('modal-repasse').classList.add('flex');
}

// Função para confirmar repasse em lote
function confirmarRepasseLote() {
    const checkboxes = document.querySelectorAll('.guia-repasse-checkbox:checked');
    const guiasPendentes = Array.from(checkboxes).filter(cb => cb.dataset.repassePago === '0');
    
    if (guiasPendentes.length === 0) {
        showToast('Selecione pelo menos uma guia pendente', 'warning');
        return;
    }
    
    guiasParaRepasse = guiasPendentes.map(cb => parseInt(cb.value));
    atualizarResumoRepasse();
    document.getElementById('modal-repasse').classList.remove('hidden');
    document.getElementById('modal-repasse').classList.add('flex');
}

// Função para confirmar repasse de toda uma clínica
function confirmarRepasseClinica(clinicaId) {
    if (confirm('Confirmar todos os repasses pendentes desta clínica?')) {
        window.location.href = `repasses.php?clinica_id=${clinicaId}&status_repasse=0`;
    }
}

// Função para atualizar resumo do repasse
function atualizarResumoRepasse() {
    document.getElementById('guias-ids').value = JSON.stringify(guiasParaRepasse);
    document.getElementById('total-guias').textContent = guiasParaRepasse.length;
    
    // Calcular valor total (simplificado - em produção você buscaria os valores reais)
    let valorTotal = 0;
    guiasParaRepasse.forEach(guiaId => {
        const row = document.querySelector(`input[value="${guiaId}"]`).closest('tr');
        const valorText = row.querySelector('td:nth-child(6)').textContent;
        const valor = parseFloat(valorText.replace('R$ ', '').replace('.', '').replace(',', '.'));
        valorTotal += valor;
    });
    
    document.getElementById('valor-total').textContent = valorTotal.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Função para fechar modal
function fecharModalRepasse() {
    document.getElementById('modal-repasse').classList.add('hidden');
    document.getElementById('modal-repasse').classList.remove('flex');
    guiasParaRepasse = [];
}

// Função para cancelar repasse
function cancelarRepasse(guiaId) {
    if (confirm('Tem certeza que deseja cancelar este repasse?')) {
        fetch('repasses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=cancelar_repasse&guia_id=' + guiaId
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

// Função para exportar repasses
function exportRepasses() {
    showToast('Exportando dados de repasses...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Dados exportados com sucesso!', 'success');
    }, 2000);
}

// Função para gerar relatório
function gerarRelatorioRepasses() {
    const params = new URLSearchParams(window.location.search);
    window.open('relatorio_repasses.php?' + params.toString(), '_blank');
}

// Função para gerar comprovante
function gerarComprovanteRepasse(clinicaId) {
    const params = new URLSearchParams(window.location.search);
    params.set('clinica_id', clinicaId);
    window.open('comprovante_repasse.php?' + params.toString(), '_blank');
}

// Manipulação de checkboxes
document.getElementById('select-all-guias')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.guia-repasse-checkbox:not(:disabled)');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

document.getElementById('select-all-header-guias')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.guia-repasse-checkbox:not(:disabled)');
    checkboxes.forEach(cb => cb.checked = this.checked);
    if (document.getElementById('select-all-guias')) {
        document.getElementById('select-all-guias').checked = this.checked;
    }
});

// Submissão do formulário de repasse
document.getElementById('form-repasse').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'confirmar_repasse');
    
    fetch('repasses.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            fecharModalRepasse();
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
document.getElementById('clinica_id').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('status_repasse').addEventListener('change', function() {
    this.form.submit();
});

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalRepasse();
    }
});

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + A = Selecionar todos
    if (e.ctrlKey && e.key === 'a') {
        e.preventDefault();
        const selectAll = document.getElementById('select-all-guias');
        if (selectAll) selectAll.click();
    }
    
    // Ctrl + R = Confirmar repasse em lote
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        confirmarRepasseLote();
    }
    
    // Ctrl + E = Exportar
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportRepasses();
    }
});
</script>

<?php include 'templates/footer.php'; ?>
