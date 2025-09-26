<?php
// Página de Boas-Vindas - Salvar como bem_vindo.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Obtém dados do usuário
$user = $db->select("SELECT * FROM usuarios WHERE id = ?", [$_SESSION['user_id']]);
$user = $user ? $user[0] : null;

// Obtém estatísticas básicas
$totalClientes = $db->count("SELECT COUNT(*) FROM clientes");
$totalClinicas = $db->count("SELECT COUNT(*) FROM clinicas");

// Estatísticas do usuário atual
$clientesCadastradosUsuario = $db->count(
    "SELECT COUNT(*) FROM clientes WHERE cadastrado_por_id = ?", 
    [$_SESSION['user_id']]
);

// Verifica se é aniversário do usuário
$isUserBirthday = false;
if ($user && isset($user['data_nascimento']) && $user['data_nascimento']) { // Adicionada a verificação "isset"
    $today = date('m-d');
    $birthday = date('m-d', strtotime($user['data_nascimento']));
    $isUserBirthday = ($today === $birthday && !empty($user['receber_felicitacoes_aniversario']));
}

// Obtém mensagem personalizada por horário
$greeting = getGreeting();
$firstName = $user ? explode(' ', $user['nome_completo'])[0] : 'Usuário';

// Obtém frase motivacional aleatória (simulada por enquanto)
$frases = [
    "O sucesso é a soma de pequenos esforços repetidos dia após dia.",
    "A persistência é o caminho do êxito.",
    "Grandes realizações requerem grandes ambições.",
    "O futuro pertence àqueles que acreditam na beleza de seus sonhos.",
    "Seja a mudança que você quer ver no mundo.",
    "A única forma de fazer um excelente trabalho é amar o que você faz.",
    "Acredite em si mesmo e tudo será possível."
];
$fraseAleatoria = $frases[array_rand($frases)];

// Obtém aniversariantes de hoje
$aniversariantesHoje = $db->select(
    "SELECT nome_completo, telefone1 FROM clientes 
     WHERE DAY(data_nascimento) = DAY(CURDATE()) 
     AND MONTH(data_nascimento) = MONTH(CURDATE())
     AND aceita_parabens = 1
     ORDER BY nome_completo"
);

// Adicione esta verificação para evitar o erro fatal
if (!is_array($aniversariantesHoje)) {
    $aniversariantesHoje = []; // Garante que é um array
}

$pageTitle = 'Início';
$pageSubtitle = $greeting . ', ' . $firstName . '!';

include 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100 dark:bg-gray-900">
    
    <?php include 'templates/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <?php include 'templates/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-900 p-6">
            
            <!-- Saudação Principal -->
            <div class="mb-8">
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-2xl p-8 text-white shadow-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex-1">
                            <h1 class="text-3xl font-bold mb-2">
                                <?php echo $greeting; ?>, <?php echo htmlspecialchars($firstName); ?>! 👋
                            </h1>
                            <p class="text-blue-100 text-lg mb-4">
                                Bem-vindo ao sistema Encaminha Mais+
                            </p>
                            
                            <!-- Informações do usuário -->
                            <div class="flex items-center gap-6 text-sm text-blue-100">
                                <?php if ($user && $user['ultimo_logout']): ?>
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="clock" class="w-4 h-4"></i>
                                        <span>Último acesso: <?php echo formatDateBR($user['ultimo_logout'], true); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-2">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    <span>Hoje: <?php echo date('d/m/Y'); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Foto do usuário -->
                        <div class="flex-shrink-0 ml-6">
                            <?php if ($user && $user['foto_path'] && file_exists($user['foto_path'])): ?>
                                <img src="<?php echo htmlspecialchars($user['foto_path']); ?>" 
                                     class="w-20 h-20 rounded-full object-cover border-4 border-white/20" 
                                     alt="Foto do usuário">
                            <?php else: ?>
                                <div class="w-20 h-20 rounded-full bg-white/20 flex items-center justify-center border-4 border-white/20">
                                    <span class="text-2xl font-bold">
                                        <?php echo strtoupper(substr($firstName, 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Frase Motivacional -->
            <div class="mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                                <i data-lucide="lightbulb" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-1">Frase do Dia</h3>
                            <p class="text-gray-600 dark:text-gray-400 italic">
                                "<?php echo htmlspecialchars($fraseAleatoria); ?>"
                            </p>
                        </div>
                        <button onclick="location.reload()" 
                                class="flex-shrink-0 p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                                title="Nova frase">
                            <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Cards de Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Total de Clientes -->
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Clientes</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($totalClientes); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="users" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="clientes.php" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                            Ver todos os clientes →
                        </a>
                    </div>
                </div>
                
                <!-- Total de Clínicas -->
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total de Clínicas</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($totalClinicas); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="building-2" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="clinicas.php" class="text-sm text-green-600 dark:text-green-400 hover:underline">
                            Ver todas as clínicas →
                        </a>
                    </div>
                </div>
                
                <!-- Clientes Cadastrados por Mim -->
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Meus Cadastros</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo number_format($clientesCadastradosUsuario); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="user-plus" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="cliente_formulario.php" class="text-sm text-purple-600 dark:text-purple-400 hover:underline">
                            Cadastrar novo cliente →
                        </a>
                    </div>
                </div>
                
                <!-- Aniversariantes Hoje -->
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Aniversariantes Hoje</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo count($aniversariantesHoje); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                            <i data-lucide="cake" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="aniversario_cliente.php" class="text-sm text-yellow-600 dark:text-yellow-400 hover:underline">
                            Ver aniversariantes →
                        </a>
                    </div>
                </div>
                
            </div>
            
            <!-- Aniversariantes de Hoje -->
            <?php if (!empty($aniversariantesHoje)): ?>
                <div class="mb-8">
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                        <div class="p-6 border-b dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                                    <i data-lucide="cake" class="w-5 h-5 text-yellow-600 dark:text-yellow-400"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        🎉 Aniversariantes de Hoje
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Que tal enviar uma mensagem de parabéns?
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid gap-4">
                                <?php foreach ($aniversariantesHoje as $aniversariante): ?>
                                    <div class="flex items-center justify-between p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 bg-yellow-200 dark:bg-yellow-800 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">
                                                    <?php echo strtoupper(substr($aniversariante['nome_completo'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($aniversariante['nome_completo']); ?>
                                                </p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    Fazendo aniversário hoje! 🎂
                                                </p>
                                            </div>
                                        </div>
                                        <?php if ($aniversariante['telefone1']): ?>
                                            <a href="<?php echo getWhatsAppLink($aniversariante['telefone1']); ?>" 
                                               target="_blank"
                                               class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                                                Parabenizar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Ações Rápidas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Cadastrar Cliente -->
                <a href="cliente_formulario.php" class="group">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border dark:border-gray-700 hover:shadow-md transition-all duration-200 group-hover:scale-105">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center group-hover:bg-blue-200 dark:group-hover:bg-blue-800 transition-colors">
                                <i data-lucide="user-plus" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Cadastrar Cliente</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Adicionar novo cliente ao sistema</p>
                            </div>
                        </div>
                    </div>
                </a>
                
                <!-- Nova Guia -->
                <a href="nova_guia_encaminhamento.php" class="group">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border dark:border-gray-700 hover:shadow-md transition-all duration-200 group-hover:scale-105">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center group-hover:bg-green-200 dark:group-hover:bg-green-800 transition-colors">
                                <i data-lucide="file-plus" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Nova Guia</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Criar nova guia de encaminhamento</p>
                            </div>
                        </div>
                    </div>
                </a>
                
                <!-- Dashboard -->
                <a href="dashboard.php" class="group">
                    <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-sm border dark:border-gray-700 hover:shadow-md transition-all duration-200 group-hover:scale-105">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center group-hover:bg-purple-200 dark:group-hover:bg-purple-800 transition-colors">
                                <i data-lucide="bar-chart-3" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Dashboard</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Ver relatórios e estatísticas</p>
                            </div>
                        </div>
                    </div>
                </a>
                
            </div>
            
            <!-- Carrossel de Imagens (Placeholder) -->
            <div class="mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 overflow-hidden">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Destaques</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Informações importantes e atualizações</p>
                    </div>
                    <div class="p-6">
                        <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 rounded-lg p-8 text-center">
                            <i data-lucide="image" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                Carrossel de Imagens
                            </h4>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                Aqui aparecerão as imagens e destaques configurados pelo administrador
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-500">
                                Configure imagens em: Configurações → Gerenciar Conteúdo
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
        
    </div>
    
</div>

<?php include 'templates/footer.php'; ?>
