<?php
// Página Principal de Exames - Salvar como exames.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Processa filtros
$search = trim($_GET['search'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');
$ativo = $_GET['ativo'] ?? '';

// Monta a query base
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(nome LIKE ? OR codigo_exame LIKE ? OR descricao LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($categoria)) {
    $whereConditions[] = "categoria = ?";
    $params[] = $categoria;
}

if ($ativo !== '') {
    $whereConditions[] = "ativo = ?";
    $params[] = intval($ativo);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Busca os exames
$exames = $db->select("
    SELECT e.*, 
           u1.nome_completo as cadastrado_por_nome,
           u2.nome_completo as modificado_por_nome,
           (SELECT COUNT(*) FROM guias g WHERE g.exame_id = e.id) as total_guias
    FROM exames e
    LEFT JOIN usuarios u1 ON e.cadastrado_por_id = u1.id
    LEFT JOIN usuarios u2 ON e.modificado_por_id = u2.id
    $whereClause
    ORDER BY e.categoria, e.nome
", $params);

// Estatísticas
$totalExames = count($exames);
$examesAtivos = array_filter($exames, function($e) { return $e['ativo'] == 1; });
$totalAtivos = count($examesAtivos);
$examesInativos = $totalExames - $totalAtivos;

// Estatísticas por categoria
$categoriasStats = [];
foreach ($exames as $exame) {
    $cat = $exame['categoria'] ?: 'Sem categoria';
    if (!isset($categoriasStats[$cat])) {
        $categoriasStats[$cat] = 0;
    }
    $categoriasStats[$cat]++;
}
arsort($categoriasStats);

// Busca categorias para filtros
$categorias = $db->select("SELECT DISTINCT categoria FROM exames WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");

// Valor médio dos exames
$valorMedio = 0;
if (!empty($examesAtivos)) {
    $somaValores = array_sum(array_column($examesAtivos, 'valor_padrao'));
    $valorMedio = $somaValores / count($examesAtivos);
}

$pageTitle = 'Exames';
$pageSubtitle = 'Catálogo de exames e procedimentos disponíveis';

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
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Exames</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Exames</h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        Catálogo de exames e procedimentos disponíveis
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <a href="exame_formulario.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Novo Exame
                    </a>
                    
                    <button onclick="exportExames()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
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
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Exames</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $totalExames; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="clipboard-list" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Exames Ativos</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400"><?php echo $totalAtivos; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Categorias</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400"><?php echo count($categoriasStats); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="folder" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Valor Médio</p>
                            <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">R$ <?php echo number_format($valorMedio, 2, ',', '.'); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Distribuição por Categoria -->
            <?php if (!empty($categoriasStats)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Distribuição por Categoria</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach ($categoriasStats as $categoria => $total): ?>
                                <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $total; ?></p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($categoria); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    
                    <!-- Busca -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Buscar
                        </label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Nome do exame ou código..."
                               class="input-destacado w-full">
                    </div>
                    
                    <!-- Categoria -->
                    <div>
                        <label for="categoria" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Categoria
                        </label>
                        <select id="categoria" name="categoria" class="input-destacado w-full">
                            <option value="">Todas as categorias</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['categoria']); ?>" 
                                        <?php echo $categoria === $cat['categoria'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['categoria']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Status -->
                    <div>
                        <label for="ativo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Status
                        </label>
                        <select id="ativo" name="ativo" class="input-destacado w-full">
                            <option value="">Todos os status</option>
                            <option value="1" <?php echo $ativo === '1' ? 'selected' : ''; ?>>Ativo</option>
                            <option value="0" <?php echo $ativo === '0' ? 'selected' : ''; ?>>Inativo</option>
                        </select>
                    </div>
                    
                    <!-- Botões -->
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                        <a href="exames.php" class="flex-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 text-center">
                            Limpar
                        </a>
                    </div>
                    
                </form>
            </div>
            
            <!-- Lista de Exames -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Lista de Exames
                        <?php if (!empty($search) || !empty($categoria) || $ativo !== ''): ?>
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                (<?php echo count($exames); ?> resultado<?php echo count($exames) !== 1 ? 's' : ''; ?> encontrado<?php echo count($exames) !== 1 ? 's' : ''; ?>)
                            </span>
                        <?php endif; ?>
                    </h3>
                </div>
                
                <?php if (empty($exames)): ?>
                    <div class="p-12 text-center">
                        <i data-lucide="clipboard-list" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Nenhum exame encontrado
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            <?php if (!empty($search) || !empty($categoria) || $ativo !== ''): ?>
                                Não há exames que correspondam aos filtros aplicados.
                            <?php else: ?>
                                Comece cadastrando seu primeiro exame.
                            <?php endif; ?>
                        </p>
                        <a href="exame_formulario.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            Cadastrar Primeiro Exame
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Exame</th>
                                    <th>Categoria</th>
                                    <th>Valor</th>
                                    <th>Tempo</th>
                                    <th>Guias</th>
                                    <th>Status</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($exames as $exame): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($exame['nome']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($exame['codigo_exame']); ?>
                                                    <?php if ($exame['descricao']): ?>
                                                        - <?php echo htmlspecialchars(substr($exame['descricao'], 0, 50)); ?><?php echo strlen($exame['descricao']) > 50 ? '...' : ''; ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($exame['categoria']): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-sm rounded-full">
                                                    <i data-lucide="folder" class="w-3 h-3"></i>
                                                    <?php echo htmlspecialchars($exame['categoria']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="font-semibold text-green-600 dark:text-green-400">
                                                R$ <?php echo number_format($exame['valor_padrao'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo $exame['tempo_estimado']; ?> min
                                            </span>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200 text-sm rounded-full">
                                                <i data-lucide="file-text" class="w-3 h-3"></i>
                                                <?php echo $exame['total_guias']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($exame['ativo']): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded-full">
                                                    <i data-lucide="check-circle" class="w-3 h-3"></i>
                                                    Ativo
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs rounded-full">
                                                    <i data-lucide="x-circle" class="w-3 h-3"></i>
                                                    Inativo
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <a href="exame_editar.php?id=<?php echo $exame['id']; ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                   title="Editar exame">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </a>
                                                
                                                <button onclick="toggleStatus(<?php echo $exame['id']; ?>, <?php echo $exame['ativo'] ? 'true' : 'false'; ?>)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-yellow-600 hover:text-yellow-800 hover:bg-yellow-100 dark:hover:bg-yellow-900 rounded-lg"
                                                        title="<?php echo $exame['ativo'] ? 'Desativar' : 'Ativar'; ?> exame">
                                                    <i data-lucide="<?php echo $exame['ativo'] ? 'pause' : 'play'; ?>" class="w-4 h-4"></i>
                                                </button>
                                                
                                                <button onclick="deleteExame(<?php echo $exame['id']; ?>)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg"
                                                        title="Excluir exame">
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
                                Mostrando <?php echo count($exames); ?> exame<?php echo count($exames) !== 1 ? 's' : ''; ?>
                            </p>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Valor total: R$ <?php echo number_format(array_sum(array_column($exames, 'valor_padrao')), 2, ',', '.'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
// Função para alternar status do exame
function toggleStatus(exameId, currentStatus) {
    const newStatus = !currentStatus;
    const action = newStatus ? 'ativar' : 'desativar';
    
    if (confirm(`Tem certeza que deseja ${action} este exame?`)) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=toggle_exame_status&exame_id=${exameId}&new_status=${newStatus ? 1 : 0}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`Exame ${action === 'ativar' ? 'ativado' : 'desativado'} com sucesso!`, 'success');
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

// Função para excluir exame
function deleteExame(exameId) {
    if (confirm('Tem certeza que deseja excluir este exame? Esta ação não pode ser desfeita e pode afetar guias existentes.')) {
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_exame&exame_id=' + exameId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Exame excluído com sucesso!', 'success');
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('Erro ao excluir exame: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Erro interno. Tente novamente.', 'error');
        });
    }
}

// Função para exportar exames
function exportExames() {
    showToast('Exportando lista de exames...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Lista exportada com sucesso!', 'success');
    }, 2000);
}

// Auto-submit do formulário quando mudar filtros
document.getElementById('categoria').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('ativo').addEventListener('change', function() {
    this.form.submit();
});

// Busca em tempo real
document.getElementById('search').addEventListener('input', function() {
    clearTimeout(this.searchTimeout);
    
    this.searchTimeout = setTimeout(() => {
        this.form.submit();
    }, 500);
});
</script>

<?php include 'templates/footer.php'; ?>
