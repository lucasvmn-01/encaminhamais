<?php
// Página de Visualização de Guia - Salvar como guia_visualizar.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Verifica se foi passado um ID
$guiaId = intval($_GET['id'] ?? 0);
if ($guiaId <= 0) {
    header('Location: guias.php');
    exit;
}

// Verifica se é para impressão
$isPrint = isset($_GET['print']);

// Busca a guia com todos os dados relacionados
$guia = $db->select("
    SELECT g.*, 
           c.codigo_cliente, c.nome_completo as cliente_nome, c.cpf as cliente_cpf, 
           c.data_nascimento as cliente_nascimento, c.sexo as cliente_sexo,
           c.cartao_sus as cliente_sus, c.telefone1 as cliente_telefone,
           c.email as cliente_email, c.cep as cliente_cep, c.rua as cliente_rua,
           c.numero as cliente_numero, c.bairro as cliente_bairro, 
           c.cidade as cliente_cidade, c.estado as cliente_estado,
           cl.codigo_clinica, cl.nome_fantasia as clinica_nome, cl.razao_social as clinica_razao,
           cl.cnpj as clinica_cnpj, cl.telefone1 as clinica_telefone, cl.email_contato as clinica_email,
           cl.cep as clinica_cep, cl.rua as clinica_rua, cl.numero as clinica_numero,
           cl.bairro as clinica_bairro, cl.cidade as clinica_cidade, cl.estado as clinica_estado,
           e.codigo_exame, e.nome as exame_nome, e.categoria as exame_categoria,
           e.preparacao as exame_preparacao, e.observacoes as exame_observacoes,
           m.codigo_medico, m.nome_completo as medico_nome, m.crm as medico_crm,
           m.uf_crm as medico_uf_crm, m.especialidade as medico_especialidade,
           u1.nome_completo as cadastrado_por_nome,
           u2.nome_completo as modificado_por_nome
    FROM guias g
    LEFT JOIN clientes c ON g.cliente_id = c.id
    LEFT JOIN clinicas cl ON g.clinica_id = cl.id
    LEFT JOIN exames e ON g.exame_id = e.id
    LEFT JOIN medicos m ON g.medico_id = m.id
    LEFT JOIN usuarios u1 ON g.cadastrado_por_id = u1.id
    LEFT JOIN usuarios u2 ON g.modificado_por_id = u2.id
    WHERE g.id = ?
", [$guiaId]);

if (empty($guia)) {
    header('Location: guias.php');
    exit;
}

$guia = $guia[0];

// Busca configurações da empresa
$configs = $db->select("SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('empresa_nome', 'empresa_slogan', 'empresa_cnpj', 'empresa_logo_path')");
$empresaConfig = [];
foreach ($configs as $config) {
    $empresaConfig[$config['chave']] = $config['valor'];
}

$pageTitle = 'Visualizar Guia';
$pageSubtitle = 'Guia ' . $guia['numero_guia'];

// Se for impressão, usa layout específico
if ($isPrint) {
    include 'templates/print_header.php';
} else {
    include 'templates/header.php';
}
?>

<?php if (!$isPrint): ?>
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
                            <a href="guias.php" class="ml-1 text-gray-700 dark:text-gray-300 hover:text-blue-600 dark:hover:text-blue-400">Guias</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                            <span class="ml-1 text-gray-500 dark:text-gray-400">Visualizar</span>
                        </div>
                    </li>
                </ol>
            </nav>
            
            <!-- Cabeçalho -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                        Guia <?php echo htmlspecialchars($guia['numero_guia']); ?>
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">
                        <?php echo $guia['tipo_servico']; ?> - <?php echo formatDateBR($guia['data_agendamento']); ?>
                        <?php if ($guia['hora_agendamento']): ?>
                            às <?php echo date('H:i', strtotime($guia['hora_agendamento'])); ?>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="mt-4 lg:mt-0 flex items-center gap-3">
                    <a href="guia_editar.php?id=<?php echo $guiaId; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                        Editar
                    </a>
                    
                    <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        Imprimir
                    </button>
                    
                    <button onclick="window.open('guia_visualizar.php?id=<?php echo $guiaId; ?>&print=1', '_blank')" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i data-lucide="external-link" class="w-4 h-4"></i>
                        Abrir para Impressão
                    </button>
                </div>
            </div>
            
            <div class="print-container">
<?php endif; ?>

<!-- Conteúdo da Guia (para impressão e visualização) -->
<div class="<?php echo $isPrint ? 'print-page' : 'bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700'; ?>">
    
    <!-- Cabeçalho da Empresa -->
    <div class="<?php echo $isPrint ? 'print-header' : 'p-8 border-b dark:border-gray-700'; ?>">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <?php if (!empty($empresaConfig['empresa_logo_path']) && file_exists($empresaConfig['empresa_logo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($empresaConfig['empresa_logo_path']); ?>" alt="Logo" class="h-16 w-auto">
                <?php endif; ?>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        <?php echo htmlspecialchars($empresaConfig['empresa_nome'] ?? 'Encaminha Mais+'); ?>
                    </h1>
                    <?php if (!empty($empresaConfig['empresa_slogan'])): ?>
                        <p class="text-gray-600 dark:text-gray-400">
                            <?php echo htmlspecialchars($empresaConfig['empresa_slogan']); ?>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($empresaConfig['empresa_cnpj'])): ?>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            CNPJ: <?php echo formatCNPJ($empresaConfig['empresa_cnpj']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="text-right">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">GUIA DE ENCAMINHAMENTO</h2>
                <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">Nº <?php echo htmlspecialchars($guia['numero_guia']); ?></p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Emitida em: <?php echo formatDateTimeBR($guia['data_cadastro']); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Informações do Cliente -->
    <div class="<?php echo $isPrint ? 'print-section' : 'p-8 border-b dark:border-gray-700'; ?>">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <i data-lucide="user" class="w-5 h-5"></i>
            Dados do Paciente
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Nome Completo:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['cliente_nome']); ?></p>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">CPF:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo formatCPF($guia['cliente_cpf']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Código:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['codigo_cliente']); ?></p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Data de Nascimento:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo formatDateBR($guia['cliente_nascimento']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Sexo:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['cliente_sexo']); ?></p>
                    </div>
                </div>
                
                <?php if ($guia['cliente_sus']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Cartão SUS:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['cliente_sus']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Telefone:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo formatPhone($guia['cliente_telefone']); ?></p>
                </div>
                
                <?php if ($guia['cliente_email']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">E-mail:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['cliente_email']); ?></p>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Endereço:</label>
                    <p class="font-semibold text-gray-900 dark:text-white">
                        <?php 
                        $endereco = [];
                        if ($guia['cliente_rua']) $endereco[] = $guia['cliente_rua'];
                        if ($guia['cliente_numero']) $endereco[] = $guia['cliente_numero'];
                        if ($guia['cliente_bairro']) $endereco[] = $guia['cliente_bairro'];
                        if ($guia['cliente_cidade']) $endereco[] = $guia['cliente_cidade'];
                        if ($guia['cliente_estado']) $endereco[] = $guia['cliente_estado'];
                        if ($guia['cliente_cep']) $endereco[] = 'CEP: ' . formatCEP($guia['cliente_cep']);
                        echo htmlspecialchars(implode(', ', $endereco) ?: 'Não informado');
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Informações da Clínica -->
    <div class="<?php echo $isPrint ? 'print-section' : 'p-8 border-b dark:border-gray-700'; ?>">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <i data-lucide="building" class="w-5 h-5"></i>
            Clínica de Destino
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Nome Fantasia:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['clinica_nome']); ?></p>
                </div>
                
                <?php if ($guia['clinica_razao']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Razão Social:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['clinica_razao']); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">CNPJ:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo formatCNPJ($guia['clinica_cnpj']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Código:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['codigo_clinica']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Telefone:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo formatPhone($guia['clinica_telefone']); ?></p>
                </div>
                
                <?php if ($guia['clinica_email']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">E-mail:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['clinica_email']); ?></p>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Endereço:</label>
                    <p class="font-semibold text-gray-900 dark:text-white">
                        <?php 
                        $endereco = [];
                        if ($guia['clinica_rua']) $endereco[] = $guia['clinica_rua'];
                        if ($guia['clinica_numero']) $endereco[] = $guia['clinica_numero'];
                        if ($guia['clinica_bairro']) $endereco[] = $guia['clinica_bairro'];
                        if ($guia['clinica_cidade']) $endereco[] = $guia['clinica_cidade'];
                        if ($guia['clinica_estado']) $endereco[] = $guia['clinica_estado'];
                        if ($guia['clinica_cep']) $endereco[] = 'CEP: ' . formatCEP($guia['clinica_cep']);
                        echo htmlspecialchars(implode(', ', $endereco) ?: 'Não informado');
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Informações do Serviço -->
    <div class="<?php echo $isPrint ? 'print-section' : 'p-8 border-b dark:border-gray-700'; ?>">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <?php if ($guia['tipo_servico'] === 'Exame'): ?>
                <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                Exame Solicitado
            <?php else: ?>
                <i data-lucide="user-check" class="w-5 h-5"></i>
                Consulta Solicitada
            <?php endif; ?>
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Tipo de Serviço:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo $guia['tipo_servico']; ?></p>
                </div>
                
                <?php if ($guia['tipo_servico'] === 'Exame' && $guia['exame_nome']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Exame:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['exame_nome']); ?></p>
                    </div>
                    
                    <?php if ($guia['exame_categoria']): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Categoria:</label>
                            <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['exame_categoria']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Código do Exame:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['codigo_exame']); ?></p>
                    </div>
                    
                <?php elseif ($guia['tipo_servico'] === 'Consulta' && $guia['medico_nome']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Médico:</label>
                        <p class="font-semibold text-gray-900 dark:text-white">Dr. <?php echo htmlspecialchars($guia['medico_nome']); ?></p>
                    </div>
                    
                    <?php if ($guia['medico_especialidade']): ?>
                        <div>
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Especialidade:</label>
                            <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['medico_especialidade']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">CRM:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['medico_crm']); ?>/<?php echo htmlspecialchars($guia['medico_uf_crm']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Data do Agendamento:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo formatDateBR($guia['data_agendamento']); ?></p>
                </div>
                
                <?php if ($guia['hora_agendamento']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Hora do Agendamento:</label>
                        <p class="font-semibold text-gray-900 dark:text-white"><?php echo date('H:i', strtotime($guia['hora_agendamento'])); ?></p>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Status:</label>
                    <p class="font-semibold text-gray-900 dark:text-white">
                        <?php 
                        $statusColors = [
                            'Agendado' => 'text-blue-600 dark:text-blue-400',
                            'Confirmado' => 'text-green-600 dark:text-green-400',
                            'Realizado' => 'text-purple-600 dark:text-purple-400',
                            'Cancelado' => 'text-red-600 dark:text-red-400',
                            'Faltou' => 'text-yellow-600 dark:text-yellow-400'
                        ];
                        $colorClass = $statusColors[$guia['status']] ?? 'text-gray-600 dark:text-gray-400';
                        ?>
                        <span class="<?php echo $colorClass; ?>"><?php echo $guia['status']; ?></span>
                    </p>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Valor do Serviço:</label>
                    <p class="font-semibold text-green-600 dark:text-green-400 text-lg">R$ <?php echo number_format($guia['valor_exame'], 2, ',', '.'); ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Preparação do Exame (se houver) -->
    <?php if ($guia['tipo_servico'] === 'Exame' && $guia['exame_preparacao']): ?>
        <div class="<?php echo $isPrint ? 'print-section' : 'p-8 border-b dark:border-gray-700'; ?>">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                Preparação Necessária
            </h3>
            
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <p class="text-gray-900 dark:text-white whitespace-pre-line"><?php echo htmlspecialchars($guia['exame_preparacao']); ?></p>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Observações -->
    <?php if ($guia['observacoes'] || ($guia['tipo_servico'] === 'Exame' && $guia['exame_observacoes'])): ?>
        <div class="<?php echo $isPrint ? 'print-section' : 'p-8 border-b dark:border-gray-700'; ?>">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                <i data-lucide="message-square" class="w-5 h-5"></i>
                Observações
            </h3>
            
            <div class="space-y-4">
                <?php if ($guia['observacoes']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Observações da Guia:</label>
                        <p class="text-gray-900 dark:text-white whitespace-pre-line"><?php echo htmlspecialchars($guia['observacoes']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($guia['tipo_servico'] === 'Exame' && $guia['exame_observacoes']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Observações do Exame:</label>
                        <p class="text-gray-900 dark:text-white whitespace-pre-line"><?php echo htmlspecialchars($guia['exame_observacoes']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Informações de Controle -->
    <div class="<?php echo $isPrint ? 'print-section' : 'p-8'; ?>">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
            <i data-lucide="info" class="w-5 h-5"></i>
            Informações de Controle
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Forma de Pagamento:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['forma_pagamento']); ?></p>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Situação do Pagamento:</label>
                    <p class="font-semibold <?php echo $guia['pago'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'; ?>">
                        <?php echo $guia['pago'] ? 'Pago' : 'Pendente'; ?>
                        <?php if ($guia['pago'] && $guia['data_pagamento']): ?>
                            (<?php echo formatDateBR($guia['data_pagamento']); ?>)
                        <?php endif; ?>
                    </p>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Cadastrado por:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo htmlspecialchars($guia['cadastrado_por_nome'] ?? 'Sistema'); ?></p>
                </div>
            </div>
            
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Data de Cadastro:</label>
                    <p class="font-semibold text-gray-900 dark:text-white"><?php echo formatDateTimeBR($guia['data_cadastro']); ?></p>
                </div>
                
                <?php if ($guia['modificado_por_nome']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Última Modificação:</label>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            <?php echo htmlspecialchars($guia['modificado_por_nome']); ?> em <?php echo formatDateTimeBR($guia['data_modificacao']); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div>
                    <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Situação do Repasse:</label>
                    <p class="font-semibold <?php echo $guia['repasse_pago'] ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400'; ?>">
                        <?php echo $guia['repasse_pago'] ? 'Efetuado' : 'Pendente'; ?>
                        <?php if ($guia['repasse_pago'] && $guia['data_repasse']): ?>
                            (<?php echo formatDateBR($guia['data_repasse']); ?>)
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rodapé para impressão -->
    <?php if ($isPrint): ?>
        <div class="print-footer">
            <div class="flex justify-between items-center text-sm text-gray-600">
                <div>
                    <p>Esta guia é válida apenas para o serviço especificado.</p>
                    <p>Em caso de dúvidas, entre em contato conosco.</p>
                </div>
                <div class="text-right">
                    <p>Guia: <?php echo htmlspecialchars($guia['numero_guia']); ?></p>
                    <p>Impresso em: <?php echo date('d/m/Y H:i'); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
</div>

<?php if (!$isPrint): ?>
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
// Função para imprimir
function printGuia() {
    window.print();
}

// Atalhos de teclado
document.addEventListener('keydown', function(e) {
    // Ctrl + P = Imprimir
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        printGuia();
    }
    
    // Esc = Voltar
    if (e.key === 'Escape') {
        window.location.href = 'guias.php';
    }
    
    // E = Editar
    if (e.key === 'e' || e.key === 'E') {
        window.location.href = 'guia_editar.php?id=<?php echo $guiaId; ?>';
    }
});

// Estilos específicos para impressão
const printStyles = `
    @media print {
        body * {
            visibility: hidden;
        }
        .print-container, .print-container * {
            visibility: visible;
        }
        .print-container {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .print-page {
            background: white !important;
            color: black !important;
            box-shadow: none !important;
            border: none !important;
        }
        .print-header {
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }
        .print-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ccc;
        }
        .print-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #000;
        }
    }
`;

// Adiciona estilos de impressão
const styleSheet = document.createElement('style');
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);
</script>

<?php include 'templates/footer.php'; ?>

<?php else: ?>

<script>
// Auto-impressão quando abrir em modo de impressão
window.onload = function() {
    window.print();
};
</script>

</body>
</html>

<?php endif; ?>
