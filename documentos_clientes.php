<?php
// Página de Documentos de Clientes - Salvar como documentos_clientes.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Processa filtros
$search = trim($_GET['search'] ?? '');
$tipoDocumento = $_GET['tipo_documento'] ?? '';
$clienteId = intval($_GET['cliente_id'] ?? 0);

// Monta a query base
$whereConditions = ["d.documentavel_type = 'Cliente'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(c.nome_completo LIKE ? OR c.cpf LIKE ? OR d.nome_original LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if (!empty($tipoDocumento)) {
    $whereConditions[] = "d.tipo_documento = ?";
    $params[] = $tipoDocumento;
}

if ($clienteId > 0) {
    $whereConditions[] = "d.documentavel_id = ?";
    $params[] = $clienteId;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Busca os documentos
$documentos = $db->select("
    SELECT d.*, 
           c.nome_completo as cliente_nome,
           c.cpf as cliente_cpf,
           c.codigo_cliente,
           u.nome_completo as uploaded_by_nome
    FROM documentos d
    LEFT JOIN clientes c ON d.documentavel_id = c.id
    LEFT JOIN usuarios u ON d.uploaded_by_id = u.id
    $whereClause
    ORDER BY d.data_upload DESC
", $params);

// Estatísticas
$totalDocumentos = count($documentos);
$tiposDocumentos = $db->select("
    SELECT tipo_documento, COUNT(*) as total 
    FROM documentos 
    WHERE documentavel_type = 'Cliente' 
    GROUP BY tipo_documento 
    ORDER BY total DESC
");

// Busca clientes para o filtro
$clientes = $db->select("
    SELECT id, codigo_cliente, nome_completo 
    FROM clientes 
    ORDER BY nome_completo 
    LIMIT 100
");

$pageTitle = 'Documentos de Clientes';
$pageSubtitle = 'Gerenciamento de documentos anexados aos clientes';

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
                    <a href="clientes.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                        Lista de Clientes
                    </a>
                    <a href="aniversario_cliente.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                        Aniversariantes
                    </a>
                    <a href="documentos_clientes.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium">
                        Documentos
                    </a>
                </nav>
            </div>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Documentos</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($totalDocumentos); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="file-text" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tipos Diferentes</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo count($tiposDocumentos); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="folder" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Hoje</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                <?php 
                                $hoje = array_filter($documentos, function($doc) {
                                    return date('Y-m-d', strtotime($doc['data_upload'])) === date('Y-m-d');
                                });
                                echo count($hoje);
                                ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                            <i data-lucide="calendar" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Esta Semana</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                <?php 
                                $semana = array_filter($documentos, function($doc) {
                                    $dataUpload = strtotime($doc['data_upload']);
                                    $inicioSemana = strtotime('monday this week');
                                    return $dataUpload >= $inicioSemana;
                                });
                                echo count($semana);
                                ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="trending-up" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Tipos de Documentos -->
            <?php if (!empty($tiposDocumentos)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Distribuição por Tipo de Documento</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach ($tiposDocumentos as $tipo): ?>
                                <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-2xl font-bold text-gray-900 dark:text-white"><?php echo $tipo['total']; ?></p>
                                    <p class="text-xs text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($tipo['tipo_documento']); ?></p>
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
                               placeholder="Nome do cliente, CPF ou nome do arquivo..."
                               class="input-destacado w-full">
                    </div>
                    
                    <!-- Tipo de Documento -->
                    <div>
                        <label for="tipo_documento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Tipo de Documento
                        </label>
                        <select id="tipo_documento" name="tipo_documento" class="input-destacado w-full">
                            <option value="">Todos os tipos</option>
                            <?php foreach ($tiposDocumentos as $tipo): ?>
                                <option value="<?php echo htmlspecialchars($tipo['tipo_documento']); ?>" 
                                        <?php echo $tipoDocumento === $tipo['tipo_documento'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['tipo_documento']); ?> (<?php echo $tipo['total']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Cliente -->
                    <div>
                        <label for="cliente_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Cliente
                        </label>
                        <select id="cliente_id" name="cliente_id" class="input-destacado w-full">
                            <option value="">Todos os clientes</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" 
                                        <?php echo $clienteId === intval($cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['codigo_cliente'] . ' - ' . $cliente['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Botões -->
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            Filtrar
                        </button>
                        <a href="documentos_clientes.php" class="flex-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 py-2 px-4 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 text-center">
                            Limpar
                        </a>
                    </div>
                    
                </form>
            </div>
            
            <!-- Lista de Documentos -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Documentos de Clientes
                        </h3>
                        
                        <div class="flex items-center gap-3">
                            <button onclick="exportDocuments()" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Exportar Lista
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($documentos)): ?>
                    <div class="p-12 text-center">
                        <i data-lucide="file-text" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Nenhum documento encontrado
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Não há documentos que correspondam aos filtros aplicados.
                        </p>
                        <a href="cliente_formulario.php" class="text-blue-600 dark:text-blue-400 hover:underline">
                            Cadastrar novo cliente com documentos
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Documento</th>
                                    <th>Tipo</th>
                                    <th>Tamanho</th>
                                    <th>Upload</th>
                                    <th>Enviado por</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documentos as $doc): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($doc['cliente_nome']); ?>
                                                </p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    <?php echo htmlspecialchars($doc['codigo_cliente']); ?> - 
                                                    <?php echo formatCPF($doc['cliente_cpf']); ?>
                                                </p>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <?php 
                                                $extension = strtolower(pathinfo($doc['nome_original'], PATHINFO_EXTENSION));
                                                $iconClass = match($extension) {
                                                    'pdf' => 'text-red-500',
                                                    'doc', 'docx' => 'text-blue-500',
                                                    'jpg', 'jpeg', 'png' => 'text-green-500',
                                                    default => 'text-gray-500'
                                                };
                                                ?>
                                                <i data-lucide="file" class="w-4 h-4 <?php echo $iconClass; ?>"></i>
                                                <span class="font-medium text-gray-900 dark:text-white truncate max-w-xs" title="<?php echo htmlspecialchars($doc['nome_original']); ?>">
                                                    <?php echo htmlspecialchars($doc['nome_original']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                                                <?php echo htmlspecialchars($doc['tipo_documento']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo formatFileSize($doc['tamanho_arquivo']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo formatDateBR($doc['data_upload'], true); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo htmlspecialchars($doc['uploaded_by_nome'] ?? 'Sistema'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <a href="<?php echo htmlspecialchars($doc['path_arquivo']); ?>" 
                                                   target="_blank"
                                                   class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                   title="Visualizar documento">
                                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                                </a>
                                                
                                                <a href="<?php echo htmlspecialchars($doc['path_arquivo']); ?>" 
                                                   download
                                                   class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 dark:hover:bg-green-900 rounded-lg"
                                                   title="Baixar documento">
                                                    <i data-lucide="download" class="w-4 h-4"></i>
                                                </a>
                                                
                                                <a href="cliente_editar.php?id=<?php echo $doc['documentavel_id']; ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-gray-600 hover:text-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                                                   title="Editar cliente">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </a>
                                                
                                                <button onclick="deleteDocument(<?php echo $doc['id']; ?>)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:text-red-800 hover:bg-red-100 dark:hover:bg-red-900 rounded-lg"
                                                        title="Excluir documento">
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
                                Mostrando <?php echo count($documentos); ?> documentos
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
// Função para excluir documento
function deleteDocument(documentId) {
    if (confirm('Tem certeza que deseja excluir este documento? Esta ação não pode ser desfeita.')) {
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

// Função para exportar lista de documentos
function exportDocuments() {
    showToast('Exportando lista de documentos...', 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Lista exportada com sucesso!', 'success');
    }, 2000);
}

// Auto-submit do formulário quando mudar filtros
document.getElementById('tipo_documento').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('cliente_id').addEventListener('change', function() {
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
