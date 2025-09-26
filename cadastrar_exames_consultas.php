<?php
// Formulário de Cadastro de Exame - Salvar como exame_formulario.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();
$errors = [];
$success = false;

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
    
    // Verifica se nome já existe
    if (!empty($nome)) {
        $existingName = $db->select("SELECT id FROM exames WHERE nome = ?", [$nome]);
        if ($existingName) {
            $errors[] = 'Já existe um exame com este nome';
        }
    }
    
    // Se não há erros, processa o cadastro
    if (empty($errors)) {
        try {
            // Gera código único do exame
            $codigo_exame = generateUniqueCode('exames', 'codigo_exame', 'EX');
            
            // Dados do exame
            $exameData = [
                'codigo_exame' => $codigo_exame,
                'nome' => $nome,
                'categoria' => $categoria,
                'descricao' => trim($_POST['descricao'] ?? ''),
                'valor_padrao' => $valor_padrao,
                'tempo_estimado' => $tempo_estimado,
                'preparacao' => trim($_POST['preparacao'] ?? ''),
                'observacoes' => trim($_POST['observacoes'] ?? ''),
                'ativo' => 1,
                'cadastrado_por_id' => $_SESSION['user_id'],
                'data_cadastro' => date('Y-m-d H:i:s')
            ];
            
            // Monta a query de inserção
            $columns = implode(', ', array_keys($exameData));
            $placeholders = implode(', ', array_fill(0, count($exameData), '?'));
            $sql = "INSERT INTO exames ($columns) VALUES ($placeholders)";
            
            $inserted = $db->execute($sql, array_values($exameData));
            
            if ($inserted) {
                $exameId = $db->lastInsertId();
                $success = true;
                
                // Redireciona para a página de edição
                header("Location: exame_editar.php?id=$exameId&success=1");
                exit;
                
            } else {
                $errors[] = 'Erro ao cadastrar exame. Tente novamente.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro interno: ' . $e->getMessage();
            error_log("Erro ao cadastrar exame: " . $e->getMessage());
        }
    }
}

// Busca categorias existentes para sugestões
$categorias = $db->select("SELECT DISTINCT categoria FROM exames WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria");

$pageTitle = 'Novo Exame';
$pageSubtitle = 'Cadastro de novo exame ou procedimento';

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
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Novo Exame</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Mensagens -->
            <?php if ($success): ?>
                <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700 dark:text-green-300">Exame cadastrado com sucesso!</span>
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
            
            <!-- Cabeçalho -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Novo Exame</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-2">
                    Cadastre um novo exame ou procedimento no catálogo
                </p>
            </div>
            
            <!-- Formulário -->
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
                                       value="<?php echo htmlspecialchars($_POST['nome'] ?? ''); ?>"
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
                                           value="<?php echo htmlspecialchars($_POST['categoria'] ?? ''); ?>"
                                           class="input-destacado w-full"
                                           placeholder="Ex: Exames Laboratoriais"
                                           list="categorias-list">
                                    <datalist id="categorias-list">
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['categoria']); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Digite uma nova categoria ou selecione uma existente
                                </p>
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
                                      placeholder="Descrição detalhada do exame..."><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
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
                                           value="<?php echo $_POST['valor_padrao'] ?? ''; ?>"
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
                                           value="<?php echo $_POST['tempo_estimado'] ?? ''; ?>"
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
                                      placeholder="Ex: Jejum de 12 horas, não ingerir bebidas alcoólicas 24h antes..."><?php echo htmlspecialchars($_POST['preparacao'] ?? ''); ?></textarea>
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
                                      placeholder="Observações importantes sobre o exame..."><?php echo htmlspecialchars($_POST['observacoes'] ?? ''); ?></textarea>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Exemplos de Categorias -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
                    <h4 class="text-lg font-semibold text-blue-900 dark:text-blue-200 mb-3 flex items-center gap-2">
                        <i data-lucide="lightbulb" class="w-5 h-5"></i>
                        Sugestões de Categorias
                    </h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div class="space-y-1">
                            <p class="font-medium text-blue-800 dark:text-blue-300">Laboratoriais</p>
                            <ul class="text-blue-700 dark:text-blue-400 space-y-0.5">
                                <li>• Exames de Sangue</li>
                                <li>• Exames de Urina</li>
                                <li>• Bioquímica</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <p class="font-medium text-blue-800 dark:text-blue-300">Imagem</p>
                            <ul class="text-blue-700 dark:text-blue-400 space-y-0.5">
                                <li>• Raio-X</li>
                                <li>• Ultrassom</li>
                                <li>• Tomografia</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <p class="font-medium text-blue-800 dark:text-blue-300">Cardiologia</p>
                            <ul class="text-blue-700 dark:text-blue-400 space-y-0.5">
                                <li>• Eletrocardiograma</li>
                                <li>• Ecocardiograma</li>
                                <li>• Holter</li>
                            </ul>
                        </div>
                        <div class="space-y-1">
                            <p class="font-medium text-blue-800 dark:text-blue-300">Outros</p>
                            <ul class="text-blue-700 dark:text-blue-400 space-y-0.5">
                                <li>• Endoscopia</li>
                                <li>• Biópsias</li>
                                <li>• Consultas</li>
                            </ul>
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
                        <button type="reset" class="px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                            Limpar Formulário
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Cadastrar Exame
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
        // Verifica se nome já existe
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=validate_exame_name&nome=' + encodeURIComponent(nome)
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

// Sugestões de tempo baseadas na categoria
document.getElementById('categoria').addEventListener('change', function() {
    const categoria = this.value.toLowerCase();
    const tempoInput = document.getElementById('tempo_estimado');
    
    // Sugestões de tempo baseadas na categoria
    const temposSugeridos = {
        'exames laboratoriais': 15,
        'exames de sangue': 10,
        'exames de urina': 5,
        'raio-x': 15,
        'ultrassom': 30,
        'tomografia': 45,
        'ressonância': 60,
        'eletrocardiograma': 10,
        'ecocardiograma': 30,
        'endoscopia': 60,
        'consulta': 30
    };
    
    for (const [cat, tempo] of Object.entries(temposSugeridos)) {
        if (categoria.includes(cat)) {
            if (!tempoInput.value || tempoInput.value == 0) {
                tempoInput.value = tempo;
            }
            break;
        }
    }
});

// Animação de loading no botão ao submeter
document.querySelector('form').addEventListener('submit', function() {
    const button = this.querySelector('button[type="submit"]');
    const originalContent = button.innerHTML;
    
    button.innerHTML = `
        <div class="flex items-center gap-2">
            <div class="spinner"></div>
            Cadastrando...
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
