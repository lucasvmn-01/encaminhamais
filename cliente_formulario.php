<?php
// Formulário de Cadastro de Cliente - Salvar como cliente_formulario.php

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
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $data_nascimento = $_POST['data_nascimento'] ?? '';
    $sexo = $_POST['sexo'] ?? '';
    
    // Validações
    if (empty($nome_completo)) {
        $errors[] = 'Nome completo é obrigatório';
    }
    
    if (empty($cpf)) {
        $errors[] = 'CPF é obrigatório';
    } elseif (!validateCPF($cpf)) {
        $errors[] = 'CPF inválido';
    } else {
        // Verifica se CPF já existe
        $existingCPF = $db->select("SELECT id FROM clientes WHERE cpf = ?", [$cpf]);
        if ($existingCPF) {
            $errors[] = 'CPF já cadastrado no sistema';
        }
    }
    
    if (empty($data_nascimento)) {
        $errors[] = 'Data de nascimento é obrigatória';
    }
    
    if (empty($sexo)) {
        $errors[] = 'Sexo é obrigatório';
    }
    
    // Se não há erros, processa o cadastro
    if (empty($errors)) {
        try {
            // Gera código do cliente
            $codigo_cliente = getNextClientCode(); // Usando a função de functions.php
            
            // Dados do cliente
            $clientData = [
                'codigo_cliente' => $codigo_cliente,
                'nome_completo' => $nome_completo,
                'cpf' => $cpf,
                'data_nascimento' => $data_nascimento,
                'sexo' => $sexo,
                'telefone1' => preg_replace('/\D/', '', $_POST['telefone1'] ?? ''),
                'telefone2' => preg_replace('/\D/', '', $_POST['telefone2'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'cep' => preg_replace('/\D/', '', $_POST['cep'] ?? ''),
                'rua' => trim($_POST['rua'] ?? ''),
                'numero' => trim($_POST['numero'] ?? ''),
                'complemento' => trim($_POST['complemento'] ?? ''),
                'bairro' => trim($_POST['bairro'] ?? ''),
                'cidade' => trim($_POST['cidade'] ?? ''),
                'estado' => $_POST['estado'] ?? '',
                'nome_mae' => trim($_POST['nome_mae'] ?? ''),
                'nome_pai' => trim($_POST['nome_pai'] ?? ''),
                'estado_civil' => $_POST['estado_civil'] ?? '',
                'profissao' => trim($_POST['profissao'] ?? ''),
                'observacoes' => trim($_POST['observacoes'] ?? ''),
                'aceita_parabens' => isset($_POST['aceita_parabens']) ? 1 : 0,
                'ativo' => 1, // Cliente ativo por padrão
                'cadastrado_por_id' => $_SESSION['user_id'],
                'data_cadastro' => date('Y-m-d H:i:s'),
                'data_modificacao' => date('Y-m-d H:i:s')
            ];
            
            // Insere no banco
            $sql = "INSERT INTO clientes (" . implode(', ', array_keys($clientData)) . ") VALUES (" . rtrim(str_repeat('?,', count($clientData)), ',') . ")";
            $inserted = $db->execute($sql, array_values($clientData));
            
            if ($inserted) {
                $clientId = $db->lastInsertId();
                
                // Redireciona para a página de edição para adicionar foto e documentos
                header("Location: cliente_editar.php?id=$clientId&success=1");
                exit;
            } else {
                $errors[] = 'Erro ao cadastrar cliente. Tente novamente.';
            }
            
        } catch (Exception $e) {
            $errors[] = 'Erro interno: ' . $e->getMessage();
            error_log("Erro ao cadastrar cliente: " . $e->getMessage());
        }
    }
}

$pageTitle = 'Cadastrar Cliente';
$pageSubtitle = 'Adicionar novo cliente ao sistema';

include 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100 dark:bg-gray-900">
    
    <?php include 'templates/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <?php include 'templates/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-900 p-6">
            
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
                            <a href="clientes.php" class="ml-1 text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Clientes</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Cadastrar</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
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
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="user" class="w-5 h-5"></i>
                            Dados Pessoais
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label for="nome_completo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nome Completo <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="nome_completo" 
                                       name="nome_completo" 
                                       required
                                       value="<?php echo htmlspecialchars($_POST['nome_completo'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Digite o nome completo">
                            </div>
                            
                            <div>
                                <label for="cpf" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    CPF <span class="text-red-500">*</span>
                                </label>
                                <input type="text" 
                                       id="cpf" 
                                       name="cpf" 
                                       required
                                       data-mask="cpf"
                                       maxlength="14"
                                       value="<?php echo htmlspecialchars($_POST['cpf'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="000.000.000-00">
                            </div>
                            
                            <div>
                                <label for="data_nascimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Data de Nascimento <span class="text-red-500">*</span>
                                </label>
                                <input type="date" 
                                       id="data_nascimento" 
                                       name="data_nascimento" 
                                       required
                                       value="<?php echo htmlspecialchars($_POST['data_nascimento'] ?? ''); ?>"
                                       class="input-destacado w-full">
                            </div>
                            
                            <div>
                                <label for="sexo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Sexo <span class="text-red-500">*</span>
                                </label>
                                <select id="sexo" name="sexo" required class="input-destacado w-full">
                                    <option value="">Selecione...</option>
                                    <option value="Masculino" <?php echo ($_POST['sexo'] ?? '') === 'Masculino' ? 'selected' : ''; ?>>Masculino</option>
                                    <option value="Feminino" <?php echo ($_POST['sexo'] ?? '') === 'Feminino' ? 'selected' : ''; ?>>Feminino</option>
                                    <option value="Não Informado" <?php echo ($_POST['sexo'] ?? '') === 'Não Informado' ? 'selected' : ''; ?>>Não Informado</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="estado_civil" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Estado Civil
                                </label>
                                <select id="estado_civil" name="estado_civil" class="input-destacado w-full">
                                    <option value="">Selecione...</option>
                                    <option value="Solteiro(a)" <?php echo ($_POST['estado_civil'] ?? '') === 'Solteiro(a)' ? 'selected' : ''; ?>>Solteiro(a)</option>
                                    <option value="Casado(a)" <?php echo ($_POST['estado_civil'] ?? '') === 'Casado(a)' ? 'selected' : ''; ?>>Casado(a)</option>
                                    <option value="Divorciado(a)" <?php echo ($_POST['estado_civil'] ?? '') === 'Divorciado(a)' ? 'selected' : ''; ?>>Divorciado(a)</option>
                                    <option value="Viúvo(a)" <?php echo ($_POST['estado_civil'] ?? '') === 'Viúvo(a)' ? 'selected' : ''; ?>>Viúvo(a)</option>
                                    <option value="União Estável" <?php echo ($_POST['estado_civil'] ?? '') === 'União Estável' ? 'selected' : ''; ?>>União Estável</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                     <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="phone" class="w-5 h-5"></i>
                            Informações de Contato
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="telefone1" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Telefone Principal
                                </label>
                                <input type="text" 
                                       id="telefone1" 
                                       name="telefone1" 
                                       data-mask="phone"
                                       value="<?php echo htmlspecialchars($_POST['telefone1'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="+55 (11) 99999-9999">
                            </div>
                            
                            <div>
                                <label for="telefone2" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Telefone Secundário
                                </label>
                                <input type="text" 
                                       id="telefone2" 
                                       name="telefone2" 
                                       data-mask="phone"
                                       value="<?php echo htmlspecialchars($_POST['telefone2'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="+55 (11) 99999-9999">
                            </div>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                E-mail
                            </label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   class="input-destacado w-full"
                                   placeholder="cliente@email.com">
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="map-pin" class="w-5 h-5"></i>
                            Endereço
                        </h3>
                    </div>
                    <div class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="cep" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    CEP
                                </label>
                                <div class="flex gap-2">
                                    <input type="text" 
                                           id="cep" 
                                           name="cep" 
                                           data-mask="cep"
                                           maxlength="9"
                                           value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>"
                                           class="input-destacado flex-1"
                                           placeholder="00000-000">
                                    <button type="button" 
                                            data-action="buscar-cep"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2">
                                        <i data-lucide="search" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="md:col-span-2">
                                <label for="rua" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Rua/Logradouro
                                </label>
                                <input type="text" 
                                       id="rua" 
                                       name="rua" 
                                       value="<?php echo htmlspecialchars($_POST['rua'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome da rua">
                            </div>
                            
                            <div>
                                <label for="numero" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Número
                                </label>
                                <input type="text" 
                                       id="numero" 
                                       name="numero" 
                                       value="<?php echo htmlspecialchars($_POST['numero'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="123">
                            </div>
                            
                            <div>
                                <label for="complemento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Complemento
                                </label>
                                <input type="text" 
                                       id="complemento" 
                                       name="complemento" 
                                       value="<?php echo htmlspecialchars($_POST['complemento'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Apto, Bloco, etc.">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="bairro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Bairro
                                </label>
                                <input type="text" 
                                       id="bairro" 
                                       name="bairro" 
                                       value="<?php echo htmlspecialchars($_POST['bairro'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome do bairro">
                            </div>
                            
                            <div>
                                <label for="cidade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Cidade
                                </label>
                                <input type="text" 
                                       id="cidade" 
                                       name="cidade" 
                                       value="<?php echo htmlspecialchars($_POST['cidade'] ?? ''); ?>"
                                       class="input-destacado w-full"
                                       placeholder="Nome da cidade">
                            </div>
                            
                            <div>
                                <label for="estado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Estado
                                </label>
                                <select id="estado" name="estado" class="input-destacado w-full">
                                    <option value="">Selecione...</option>
                                    <?php 
                                    $estados = [
                                        'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                        'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
                                        'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
                                        'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
                                        'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                        'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
                                        'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
                                    ];
                                    foreach ($estados as $uf => $nome):
                                    ?>
                                        <option value="<?php echo $uf; ?>" <?php echo ($_POST['estado'] ?? '') === $uf ? 'selected' : ''; ?>>
                                            <?php echo $nome; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <a href="clientes.php" class="inline-flex items-center gap-2 px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Voltar
                    </a>
                    
                    <div class="flex gap-3">
                        <button type="reset" class="px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500">
                            Limpar Formulário
                        </button>
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            Cadastrar Cliente
                        </button>
                    </div>
                </div>
                
            </form>
            
        </main>
        
    </div>
    
</div>

<script>
// Validação em tempo real do CPF
document.getElementById('cpf').addEventListener('blur', function() {
    const cpf = this.value.replace(/\D/g, '');
    
    if (cpf.length === 11) {
        // Verifica se CPF já existe via AJAX
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=validate_cpf&cpf=' + cpf
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
document.getElementById('cpf').addEventListener('input', function() {
    this.setCustomValidity('');
    this.classList.remove('border-red-500');
});

</script>

<?php include 'templates/footer.php'; ?>