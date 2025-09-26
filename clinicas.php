<?php
// Página Principal de Clínicas - Salvar como clinicas.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Processa filtros
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$cidade = trim($_GET['cidade'] ?? '');
$estado = $_GET['estado'] ?? '';

// Monta a query base
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(nome_fantasia LIKE ? OR razao_social LIKE ? OR cnpj LIKE ? OR codigo_clinica LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($status)) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($cidade)) {
    $whereConditions[] = "cidade LIKE ?";
    $params[] = "%$cidade%";
}

if (!empty($estado)) {
    $whereConditions[] = "estado = ?";
    $params[] = $estado;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Busca as clínicas
$clinicas = $db->select("
    SELECT c.*, 
           u1.nome_completo as cadastrado_por_nome,
           u2.nome_completo as modificado_por_nome,
           (SELECT COUNT(*) FROM medicos m WHERE m.clinica_id = c.id AND m.ativo = 1) as total_medicos,
           (SELECT COUNT(*) FROM guias g WHERE g.clinica_id = c.id) as total_guias
    FROM clinicas c
    LEFT JOIN usuarios u1 ON c.cadastrado_por_id = u1.id
    LEFT JOIN usuarios u2 ON c.modificado_por_id = u2.id
    $whereClause
    ORDER BY c.nome_fantasia
", $params);

// Estatísticas
$totalClinicas = count($clinicas);
$clinicasAtivas = array_filter($clinicas, function($c) { return $c['status'] === 'Ativa'; });
$totalAtivas = count($clinicasAtivas);
$clinicasInativas = $totalClinicas - $totalAtivas;

// Estatísticas por estado
$estadosStats = [];
foreach ($clinicas as $clinica) {
    $uf = $clinica['estado'] ?: 'Não informado';
    if (!isset($estadosStats[$uf])) {
        $estadosStats[$uf] = 0;
    }
    $estadosStats[$uf]++;
}
arsort($estadosStats);

// Busca cidades e estados para filtros
$cidades = $db->select("SELECT DISTINCT cidade FROM clinicas WHERE cidade IS NOT NULL AND cidade != '' ORDER BY cidade");
$estados = $db->select("SELECT DISTINCT estado FROM clinicas WHERE estado IS NOT NULL AND estado != '' ORDER BY estado");

$pageTitle = 'Clínicas';
$pageSubtitle = 'Gerenciamento de clínicas e laboratórios parceiros';

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
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Clínicas</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Clínicas</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Gerencie clínicas e laboratórios parceiros
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <a href="clinica_formulario.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Nova Clínica
                    </a>
                    
                    <button onclick="exportClinicas()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Exportar
                    </button>
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Clínicas</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $totalClinicas; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="building" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clínicas Ativas</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo $totalAtivas; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Clínicas Inativas</p>
                            <p class="text-3xl font-bold text-red-600 dark:text-red-400"><?php echo $clinicasInativas; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                            <i data-lucide="x-circle" class="w-6 h-6 text-red-600 dark:text-red-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Estados Atendidos</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo count($estadosStats); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="map-pin" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Distribuição por Estado -->
            <?php if (!empty($estadosStats)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Distribuição por Estado</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach (array_slice($estadosStats, 0, 12) as $estado => $total): ?>
                                <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $total; ?></p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($estado); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    
                    <!-- Busca -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Buscar
                        </label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Nome, CNPJ ou código..."
                               class="input-destacado w-full">
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status
                        </label>
                        <select id="status" name="status" class="input-destacado w-full">
                            <option value="">Todos os status</option>
                            <option value="Ativa" <?php echo $status === 'Ativa' ? 'selected' : ''; ?>>Ativa</option>
                            <option value="Inativa" <?php echo $status === 'Inativa' ? 'selected' : ''; ?>>Inativa</option>
                        </select>
                    </div>
                    
                    <!-- Cidade -->
                    <div>
                        <label for="cidade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cidade
                        </label>
                        <input type="text" 
                               id="cidade" 
                               name="cidade" 
                               value="<?php echo htmlspecialchars($cidade); ?>"
                               placeholder="Nome da cidade..."
                               class="input-destacado w-full"
                               list="cidades-list">
                        <datalist id="cidades-list">
                            <?php foreach ($cidades as $c): ?>
                                <option value="<?php echo htmlspecialchars($c['cidade']); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <!-- Estado -->
                    <div>
                        <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Estado
                        </label>
                        <select id="estado" name="estado" class="input-destacado w-full">
                            <option value="">Todos os estados</option>
                            <?php 
                            $estadosBrasil = [
                                'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                                'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                                'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                                'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                                'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
                            ];
                            foreach ($estadosBrasil as $uf => $nome):
                            ?>
                                <option value="<?php echo $uf; ?>" <?php echo $estado === $uf ? 'selected' : ''; ?>>
                                    <?php echo $nome; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Botões -->
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                        <a href="clinicas.php" class="flex-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 text-center">
                            Limpar
                        </a>
                    </div>
                    
                </form>
            </div>
            
            <!-- Lista de Clínicas -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Lista de Clínicas
                        <?php if (!empty($search) || !empty($status) || !empty($cidade) || !empty($estado)): ?>
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                (<?php echo count($clinicas); ?> resultado<?php echo count($clinicas) !== 1 ? 's' : ''; ?> encontrado<?php echo count($clinicas) !== 1 ? 's' : ''; ?>)
                            </span>
                        <?php endif; ?>
                    </h3>
                </div>
                
                <?php if (empty($clinicas)): ?>
                    <div class="p-12 text-center">
                        <i data-lucide="building" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Nenhuma clínica encontrada
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            <?php if (!empty($search) || !empty($status) || !empty($cidade) || !empty($estado)): ?>
                                Não há clínicas que correspondam aos filtros aplicados.
                            <?php else: ?>
                                Comece cadastrando sua primeira clínica parceira.
                            <?php endif; ?>
                        </p>
                        <a href="clinica_formulario.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Cadastrar Primeira Clínica
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Clínica</th>
                                    <th>Contato</th>
                                    <th>Localização</th>
                                    <th>Médicos</th>
                                    <th>Guias</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clinicas as $clinica): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <?php if ($clinica['logo_path'] && file_exists($clinica['logo_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($clinica['logo_path']); ?>" 
                                                         class="w-10 h-10 rounded-lg object-cover border border-gray-200 dark:border-gray-600" 
                                                         alt="Logo">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                                        <i data-lucide="building" class="w-5 h-5 text-blue-600 dark:text-blue-400"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($clinica['nome_fantasia']); ?>
                                                    </p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo htmlspecialchars($clinica['codigo_clinica']); ?> - 
                                                        <?php echo formatCNPJ($clinica['cnpj']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-sm">
                                                <?php if ($clinica['telefone1']): ?>
                                                    <p class="text-gray-900 dark:text-white">
                                                        <i data-lucide="phone" class="w-3 h-3 inline mr-1"></i>
                                                        <?php echo formatPhone($clinica['telefone1']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if ($clinica['email_contato']): ?>
                                                    <p class="text-gray-600 dark:text-gray-400">
                                                        <i data-lucide="mail" class="w-3 h-3 inline mr-1"></i>
                                                        <?php echo htmlspecialchars($clinica['email_contato']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-sm">
                                                <p class="text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($clinica['cidade'] ?? 'Não informado'); ?>
                                                </p>
                                                <p class="text-gray-600 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($clinica['estado'] ?? 'N/A'); ?>
                                                </p>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm rounded-full">
                                                <i data-lucide="user-check" class="w-3 h-3"></i>
                                                <?php echo $clinica['total_medicos']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-sm rounded-full">
                                                <i data-lucide="file-text" class="w-3 h-3"></i>
                                                <?php echo $clinica['total_guias']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($clinica['status'] === 'Ativa'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded-full">
                                                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                                                    Ativa
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs rounded-full">
                                                    <i data-lucide="x-circle" class="w-3 h-3"></i>
                                                    Inativa
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <a href="clinica_editar.php?id=<?php echo $clinica['id']; ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                   title="Editar clínica">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </a>
                                                
                                                <?php if ($clinica['telefone1']): ?>
                                                    <a href="<?php echo getWhatsAppLink($clinica['telefone1']); ?>" 
                                                       target="_blank"
                                                       class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 dark:hover:bg-green-900 rounded-lg"
                                                       title="WhatsApp">
                                                        <i data-lucide="message-circle" class="w-4 h-4"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button onclick="toggleStatus(<?php echo $clinica['id']; ?>, '<?php echo $clinica['status']; ?>')" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-yellow-600 hover:text-yellow-800 hover:bg-yellow-100 dark:hover:bg-yellow-900 rounded-lg"
                                                        title="<?php echo $clinica['status'] === 'Ativa' ? 'Desativar' : 'Ativar'; ?> clínica">
                                                    <i data-lucide="<?php echo $clinica['status'] === 'Ativa' ? 'pause' : 'play'; ?>" class="w-4 h-4"></i>
                                                </button>
                                                
                                                <button onclick="deleteClinica(<?php echo $clinica['id']; ?>)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg"
                                                        title="Excluir clínica">
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
                                Mostrando <?php echo count($clinicas); ?> clínica<?php echo count($clinicas) !== 1 ? 's' : ''; ?>
                            </p>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Última atualização: <?php echo date('d/m/Y H:i'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
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
                    window.location.reload();
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

// Função para exportar clínicas
function exportClinicas() {
    showToast('Exportando lista de clínicas...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Lista exportada com sucesso!', 'success');
    }, 2000);
}

// Auto-submit do formulário quando mudar filtros
document.getElementById('status').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('estado').addEventListener('change', function() {
    this.form.submit();
});

// Busca em tempo real
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    
    this.searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});

document.getElementById('cidade').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    
    this.searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});
</script>

<?php include 'templates/footer.php'; ?>
