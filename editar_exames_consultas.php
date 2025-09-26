<?php
// Página de Edição de Exame - Salvar como exame_editar.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();
$errors = [];
$success = false;

// Obtém o ID do exame
$exameId = intval($_GET['id'] ?? 0);

if ($exameId <= 0) {
    header('Location: exames.php');
    exit;
}

// Busca os dados do exame
$exame = $db->select("SELECT * FROM exames WHERE id = ?", [$exameId]);

if (!$exame) {
    header('Location: exames.php?error=Exame não encontrado');
    exit;
}

$exame = $exame[0];

// Verifica mensagem de sucesso
if (isset($_GET['success'])) {
    $success = true;
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação dos campos obrigatórios
    $nome = trim($_POST['nome'] ?? '');
    $categoria = trim($_POST['categoria'] ?? '');
    $valor_padrao = floatval($_POST['valor_padrao'] ?? 0);
    $tempo_estimado = intval($_POST['tempo_estimado'] ?? 0);
    
    // Validações
    if (empty($nome)) {
        $errors[] = 'Nome do exame é obrigatório';
    }
    
    if (empty($categoria)) {
        $errors[] = 'Categoria é obrigatória';
    }
    
    if ($valor_padrao <= 0) {
        $errors[] = 'Valor padrão deve ser maior que zero';
    }
    
    if ($tempo_estimado <= 0) {
        $errors[] = 'Tempo estimado deve ser maior que zero';
    }
    
    // Verifica se nome já existe (exceto o próprio exame)
    if (!empty($nome)) {
        $existingName = $db->select("SELECT id FROM exames WHERE nome = ? AND id != ?", [$nome, $exameId]);
        if ($existingName) {
            $errors[] = 'Já existe um exame com este nome';
        }
    }
    
    // Se não há erros, processa a atualização
    if (empty($errors)) {
        try {
            // Dados do exame
            $exameData = [
                'nome' => $nome,
                'categoria' => $categoria,
                'descricao' => trim($_POST['descricao'] ?? ''),
                'valor_padrao' => $valor_padrao,
                'tempo_estimado' => $tempo_estimado,
                'preparacao' => trim($_POST['preparacao'] ?? ''),
                'observacoes' => trim($_POST['observacoes'] ?? ''),
                'modificado_por_id' => $_SESSION['user_id'],
                'data_modificacao' => date('Y-m-d H:i:s')
            ];
            
            // Monta a query de atualização
            $setClause = implode(' = ?, ', array_keys($exameData)) . ' = ?';
            $sql = "UPDATE exames SET $setClause WHERE id = ?";
            $params = array_merge(array_values($exameData), [$exameId]);
            
            $updated = $db->execute($sql, $params);
            
            if ($updated) {
                $success = true;
                
                // Atualiza os dados do exame para exibição
                $exame = $db->select("SELECT * FROM exames WHERE id = ?", [$exameId]);
                $exame = $exame[0];
                
            } else {
                $errors[] = 'Erro ao atualizar exame. Tente novamente.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro interno: ' . $e->getMessage();
            error_log("Erro ao atualizar exame: " . $e->getMessage());
        }
    }
}

// Busca estatísticas do exame
$guias = $db->select(
    "SELECT g.*, c.nome_completo as cliente_nome, cl.nome_fantasia as clinica_nome 
     FROM guias g 
     LEFT JOIN clientes c ON g.cliente_id = c.id 
     LEFT JOIN clinicas cl ON g.clinica_id = cl.id 
     WHERE g.exame_id = ? 
     ORDER BY g.data_agendamento DESC 
     LIMIT 10", 
    [$exameId]
);

$totalGuias = $db->select("SELECT COUNT(*) as total FROM guias WHERE exame_id = ?", [$exameId]);
$totalGuias = $totalGuias[0]['total'];

$valorTotal = $db->select("SELECT SUM(valor_exame) as total FROM guias WHERE exame_id = ?", [$exameId]);
$valorTotal = $valorTotal[0]['total'] ?? 0;

// Busca categorias existentes para sugestões
$categorias = $db->select("SELECT DISTINCT categoria FROM exames WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");

$pageTitle = 'Editar Exame';
$pageSubtitle = 'Código: ' . $exame['codigo_exame'] . ' - ' . $exame['nome'];

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
                            <a href="exames.php" class="ml-1 text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Exames</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Editar</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Mensagens -->
            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700 dark:text-green-300">Exame atualizado com sucesso!</span>
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
            
            <!-- Informações do Exame -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center border-4 border-gray-200 dark:border-gray-600">
                                <i data-lucide="clipboard-list" class="w-8 h-8 text-blue-600 dark:text-blue-400"></i>
                            </div>
                            
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($exame['nome']); ?>
                                </h2>
                                <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                    <span>Código: <?php echo htmlspecialchars($exame['codigo_exame']); ?></span>
                                    <span>Categoria: <?php echo htmlspecialchars($exame['categoria']); ?></span>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 <?php echo $exame['ativo'] ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200'; ?> text-xs rounded-full">
                                        <i data-lucide="<?php echo $exame['ativo'] ? 'check-circle' : 'x-circle'; ?>" class="w-3 h-3"></i>
                                        <?php echo $exame['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-3">
                            <button onclick="toggleStatus(<?php echo $exameId; ?>, <?php echo $exame['ativo'] ? 'true' : 'false'; ?>)" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                                <i data-lucide="<?php echo $exame['ativo'] ? 'pause' : 'play'; ?>" class="w-4 h-4"></i>
                                <?php echo $exame['ativo'] ? 'Desativar' : 'Ativar'; ?>
                            </button>
                            
                            <button onclick="deleteExame(<?php echo $exameId; ?>)" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                                    data-action="delete"
                                    data-item="o exame <?php echo htmlspecialchars($exame['nome']); ?>">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                Excluir
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estatísticas Rápidas -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Guias Geradas</p>
                            <p class="text-3xl font-bold text-blue-600 dark:text-blue-400"><?php echo $totalGuias; ?></p>
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
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="dollar-sign" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Valor Padrão</p>
                            <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">R$ <?php echo number_format($exame['valor_padrao'], 2, ',', '.'); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="tag" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Últimas Guias -->
            <?php if (!empty($guias)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Últimas Guias Geradas</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Clínica</th>
                                    <th>Data</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($guias as $guia): ?>
                                    <tr>
                                        <td>
                                            <p class="font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($guia['cliente_nome']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <p class="text-gray-600 dark:text-gray-400">
                                                <?php echo htmlspecialchars($guia['clinica_nome']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                <?php echo formatDateBR($guia['data_agendamento']); ?>
                                            </p>
                                        </td>
                                        <td>
                                            <span class="font-semibold text-green-600 dark:text-green-400">
                                                R$ <?php echo number_format($guia['valor_exame'], 2, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 text-xs rounded-full">
                                                <?php echo htmlspecialchars($guia['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Formulário de Edição -->
            <form method="POST" class="space-y-6">
                
                <!-- Informações Básicas -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                            Informações Básicas
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Nome do Exame -->
                            <div>
                                <label for="nome" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome do Exame <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="nome" 
                                       name="nome" 
                                       required
                                       value="<?php echo htmlspecialchars($exame['nome']); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Ex: Hemograma Completo">
                            </div>
                            
                            <!-- Categoria -->
                            <div>
                                <label for="categoria" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Categoria <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                           id="categoria" 
                                           name="categoria" 
                                           required
                                           value="<?php echo htmlspecialchars($exame['categoria']); ?>"
                                           class="input-destacado w-full"
                                           placeholder="Ex: Exames Laboratoriais"
                                           list="categorias-list">
                                    <datalist id="categorias-list">
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['categoria']); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descrição -->
                        <div>
                            <label for="descricao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Descrição
                            </label>
                            <textarea id="descricao" 
                                      name="descricao" 
                                      rows="3"
                                      class="input-destacado w-full"
                                      placeholder="Descrição detalhada do exame..."><?php echo htmlspecialchars($exame['descricao']); ?></textarea>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Valores e Tempo -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="dollar-sign" class="w-5 h-5"></i>
                            Valores e Tempo
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Valor Padrão -->
                            <div>
                                <label for="valor_padrao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Valor Padrão (R$) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400">R$</span>
                                    <input type="number" 
                                           id="valor_padrao" 
                                           name="valor_padrao" 
                                           required
                                           min="0.01"
                                           step="0.01"
                                           value="<?php echo $exame['valor_padrao']; ?>"
                                           class="input-destacado w-full pl-10"
                                           placeholder="0,00">
                                </div>
                            </div>
                            
                            <!-- Tempo Estimado -->
                            <div>
                                <label for="tempo_estimado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Tempo Estimado (minutos) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number" 
                                           id="tempo_estimado" 
                                           name="tempo_estimado" 
                                           required
                                           min="1"
                                           value="<?php echo $exame['tempo_estimado']; ?>"
                                           class="input-destacado w-full pr-16"
                                           placeholder="30">
                                    <span class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 dark:text-gray-400">min</span>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Preparação e Observações -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="info" class="w-5 h-5"></i>
                            Preparação e Observações
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <!-- Preparação -->
                        <div>
                            <label for="preparacao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Preparação Necessária
                            </label>
                            <textarea id="preparacao" 
                                      name="preparacao" 
                                      rows="4"
                                      class="input-destacado w-full"
                                      placeholder="Ex: Jejum de 12 horas, não ingerir bebidas alcoólicas 24h antes..."><?php echo htmlspecialchars($exame['preparacao']); ?></textarea>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Instruções que o paciente deve seguir antes do exame
                            </p>
                        </div>
                        
                        <!-- Observações -->
                        <div>
                            <label for="observacoes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Observações Gerais
                            </label>
                            <textarea id="observacoes" 
                                      name="observacoes" 
                                      rows="3"
                                      class="input-destacado w-full"
                                      placeholder="Observações importantes sobre o exame..."><?php echo htmlspecialchars($exame['observacoes']); ?></textarea>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Informações de Auditoria -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-xl p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informações de Auditoria</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div>
                            <p class="text-gray-600 dark:text-gray-400">
                                <strong>Cadastrado em:</strong> <?php echo formatDateBR($exame['data_cadastro'], true); ?>
                            </p>
                            <?php if ($exame['cadastrado_por_id']): ?>
                                <?php 
                                $cadastradoPor = $db->select("SELECT nome_completo FROM usuarios WHERE id = ?", [$exame['cadastrado_por_id']]);
                                if ($cadastradoPor):
                                ?>
                                    <p class="text-gray-600 dark:text-gray-400">
                                        <strong>Cadastrado por:</strong> <?php echo htmlspecialchars($cadastradoPor[0]['nome_completo']); ?>
                                    </p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if ($exame['data_modificacao']): ?>
                                <p class="text-gray-600 dark:text-gray-400">
                                    <strong>Última modificação:</strong> <?php echo formatDateBR($exame['data_modificacao'], true); ?>
                                </p>
                                <?php if ($exame['modificado_por_id']): ?>
                                    <?php 
                                    $modificadoPor = $db->select("SELECT nome_completo FROM usuarios WHERE id = ?", [$exame['modificado_por_id']]);
                                    if ($modificadoPor):
                                    ?>
                                        <p class="text-gray-600 dark:text-gray-400">
                                            <strong>Modificado por:</strong> <?php echo htmlspecialchars($modificadoPor[0]['nome_completo']); ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Botões de Ação -->
                <div class="flex items-center justify-between">
                    <a href="exames.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Voltar
                    </a>
                    
                    <div class="flex gap-3">
                        <button type="button" onclick="window.location.reload()" class="px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                            Cancelar Alterações
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Salvar Alterações
                        </button>
                    </div>
                </div>
                
            </form>
            
        </main>
        
    </div>
    
</div>

<script>
// Validação em tempo real do nome
document.getElementById('nome').addEventListener('blur', function() {
    const nome = this.value.trim();
    
    if (nome.length > 0) {
        // Verifica se nome já existe (exceto o próprio exame)
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=validate_exame_name&nome=' + encodeURIComponent(nome) + '&exclude_id=<?php echo $exameId; ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                this.setCustomValidity(data.message);
                this.classList.add('border-red-500');
            } else {
                this.setCustomValidity('');
                this.classList.remove('border-red-500');
            }
        });
    }
});

// Limpa validação ao digitar
document.getElementById('nome').addEventListener('input', function() {
    this.setCustomValidity('');
    this.classList.remove('border-red-500');
});

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
                    window.location.href = 'exames.php';
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

// Formatação automática do valor
document.getElementById('valor_padrao').addEventListener('input', function() {
    let value = this.value.replace(/[^\d.,]/g, '');
    
    // Substitui vírgula por ponto para cálculos
    value = value.replace(',', '.');
    
    // Limita a 2 casas decimais
    if (value.includes('.')) {
        const parts = value.split('.');
        if (parts[1] && parts[1].length > 2) {
            value = parts[0] + '.' + parts[1].substring(0, 2);
        }
    }
    
    this.value = value;
});

// Animação de loading no botão ao submeter
document.querySelector('form').addEventListener('submit', function() {
    const button = this.querySelector('button[type="submit"]');
    const originalContent = button.innerHTML;
    
    button.innerHTML = `
        <div class="flex items-center gap-2">
            <div class="spinner"></div>
            Salvando...
        </div>
    `;
    button.disabled = true;
    
    // Se houver erro, restaura o botão (isso será feito pelo reload da página)
    setTimeout(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
        lucide.createIcons();
    }, 10000);
});
</script>

<?php include 'templates/footer.php'; ?>
