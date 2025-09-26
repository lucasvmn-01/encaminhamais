<?php
// Template da Barra Lateral - Salvar como templates/sidebar.php

// Verifica se o usuário está logado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Obtém informações do usuário
$db = getDB();
$user = $db->select("SELECT * FROM usuarios WHERE id = ?", [$_SESSION['user_id']]);
$user = $user ? $user[0] : null;

// Define se a sidebar está colapsada
$sidebarCollapsed = isset($_SESSION['sidebar_collapsed']) && $_SESSION['sidebar_collapsed'];
?>

<nav id="main-sidebar" class="h-screen bg-white dark:bg-gray-800 shadow-lg flex flex-col transition-all duration-300 ease-in-out z-30 <?php echo $sidebarCollapsed ? 'sidebar-mini' : 'w-64'; ?>">
    
    <!-- Logo e título -->
    <div class="p-4 border-b dark:border-gray-700 flex items-center gap-4">
        <div class="flex-shrink-0">
            <i data-lucide="activity" class="text-blue-600 dark:text-blue-400 h-8 w-8"></i>
        </div>
        <h1 class="text-2xl font-bold text-blue-600 dark:text-blue-400 sidebar-logo-text">
            Encaminha+
        </h1>
    </div>
    
    <!-- Menu de navegação -->
    <div class="flex-grow overflow-y-auto overflow-x-hidden">
        <ul class="py-4 space-y-1">
            
            <!-- Início -->
            <li>
                <div class="sidebar-link-container relative px-4">
                    <a href="bem_vindo.php" class="flex items-center gap-3 w-full py-2 px-2 text-sm font-medium rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'bem_vindo.php' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : ''; ?>">
                        <i data-lucide="home" class="w-5 h-5 flex-shrink-0"></i>
                        <span class="sidebar-text">Início</span>
                    </a>
                    <span class="sidebar-tooltip">Início</span>
                </div>
            </li>
            
            <!-- Dashboard -->
            <li>
                <div class="sidebar-link-container relative px-4">
                    <a href="dashboard.php" class="flex items-center gap-3 w-full py-2 px-2 text-sm font-medium rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : ''; ?>">
                        <i data-lucide="bar-chart-3" class="w-5 h-5 flex-shrink-0"></i>
                        <span class="sidebar-text">Dashboard</span>
                    </a>
                    <span class="sidebar-tooltip">Dashboard</span>
                </div>
            </li>
            
            <!-- Clientes -->
            <li x-data="{ open: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['clientes.php', 'cliente_formulario.php', 'cliente_editar.php', 'aniversario_cliente.php', 'documentos_clientes.php']) ? 'true' : 'false'; ?> }">
                <div class="sidebar-link-container relative px-4">
                    <div class="flex items-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        <a href="clientes.php" class="flex-grow flex items-center gap-3 py-2 px-2 text-sm font-medium text-gray-600 dark:text-gray-300 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['clientes.php', 'cliente_formulario.php', 'cliente_editar.php', 'aniversario_cliente.php', 'documentos_clientes.php']) ? 'text-blue-700 dark:text-blue-300' : ''; ?>">
                            <i data-lucide="users" class="w-5 h-5 flex-shrink-0"></i>
                            <span class="sidebar-text">Clientes</span>
                        </a>
                        <button @click="open = !open" class="p-2 sidebar-text">
                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                    </div>
                    <span class="sidebar-tooltip">Clientes</span>
                </div>
                <div x-show="open" x-transition class="pt-1 pl-12 space-y-1 sidebar-text">
                    <a href="clientes.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'clientes.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Lista de Clientes
                    </a>
                    <a href="aniversario_cliente.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'aniversario_cliente.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Aniversariantes
                    </a>
                    <a href="documentos_clientes.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'documentos_clientes.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Documentos
                    </a>
                </div>
            </li>
            
            <!-- Clínicas -->
            <li x-data="{ open: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['clinicas.php', 'clinica_formulario.php', 'clinica_editar.php', 'documentos_clinicas.php']) ? 'true' : 'false'; ?> }">
                <div class="sidebar-link-container relative px-4">
                    <div class="flex items-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        <a href="clinicas.php" class="flex-grow flex items-center gap-3 py-2 px-2 text-sm font-medium text-gray-600 dark:text-gray-300 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['clinicas.php', 'clinica_formulario.php', 'clinica_editar.php', 'documentos_clinicas.php']) ? 'text-blue-700 dark:text-blue-300' : ''; ?>">
                            <i data-lucide="building-2" class="w-5 h-5 flex-shrink-0"></i>
                            <span class="sidebar-text">Clínicas</span>
                        </a>
                        <button @click="open = !open" class="p-2 sidebar-text">
                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                    </div>
                    <span class="sidebar-tooltip">Clínicas</span>
                </div>
                <div x-show="open" x-transition class="pt-1 pl-12 space-y-1 sidebar-text">
                    <a href="clinicas.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'clinicas.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Lista de Clínicas
                    </a>
                    <a href="documentos_clinicas.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'documentos_clinicas.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Documentos
                    </a>
                </div>
            </li>
            
            <!-- Exames/Consultas -->
            <li x-data="{ open: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['exames_consultas.php', 'cadastrar_exames_consultas.php', 'editar_exames_consultas.php', 'lista_medicos.php', 'cadastrar_medico.php', 'editar_medico.php']) ? 'true' : 'false'; ?> }">
                <div class="sidebar-link-container relative px-4">
                    <div class="flex items-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        <a href="exames_consultas.php" class="flex-grow flex items-center gap-3 py-2 px-2 text-sm font-medium text-gray-600 dark:text-gray-300 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['exames_consultas.php', 'cadastrar_exames_consultas.php', 'editar_exames_consultas.php', 'lista_medicos.php', 'cadastrar_medico.php', 'editar_medico.php']) ? 'text-blue-700 dark:text-blue-300' : ''; ?>">
                            <i data-lucide="stethoscope" class="w-5 h-5 flex-shrink-0"></i>
                            <span class="sidebar-text">Exames</span>
                        </a>
                        <button @click="open = !open" class="p-2 sidebar-text">
                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                    </div>
                    <span class="sidebar-tooltip">Exames/Consultas</span>
                </div>
                <div x-show="open" x-transition class="pt-1 pl-12 space-y-1 sidebar-text">
                    <a href="exames_consultas.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'exames_consultas.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Lista de Exames
                    </a>
                    <a href="lista_medicos.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'lista_medicos.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Médicos
                    </a>
                </div>
            </li>
            
            <!-- Guias -->
            <li x-data="{ open: <?php echo in_array(basename($_SERVER['PHP_SELF']), ['guias_encaminhamentos.php', 'nova_guia_encaminhamento.php', 'editar_guia.php']) ? 'true' : 'false'; ?> }">
                <div class="sidebar-link-container relative px-4">
                    <div class="flex items-center rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                        <a href="guias_encaminhamentos.php" class="flex-grow flex items-center gap-3 py-2 px-2 text-sm font-medium text-gray-600 dark:text-gray-300 <?php echo in_array(basename($_SERVER['PHP_SELF']), ['guias_encaminhamentos.php', 'nova_guia_encaminhamento.php', 'editar_guia.php']) ? 'text-blue-700 dark:text-blue-300' : ''; ?>">
                            <i data-lucide="file-text" class="w-5 h-5 flex-shrink-0"></i>
                            <span class="sidebar-text">Guias</span>
                        </a>
                        <button @click="open = !open" class="p-2 sidebar-text">
                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                    </div>
                    <span class="sidebar-tooltip">Guias</span>
                </div>
                <div x-show="open" x-transition class="pt-1 pl-12 space-y-1 sidebar-text">
                    <a href="guias_encaminhamentos.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'guias_encaminhamentos.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Histórico de Guias
                    </a>
                    <a href="nova_guia_encaminhamento.php" class="block px-4 py-2 text-sm rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'nova_guia_encaminhamento.php' ? 'bg-blue-50 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : 'text-gray-600 dark:text-gray-400'; ?>">
                        Nova Guia
                    </a>
                </div>
            </li>
            
            <!-- Financeiro -->
            <li>
                <div class="sidebar-link-container relative px-4">
                    <a href="financeiro.php" class="flex items-center gap-3 w-full py-2 px-2 text-sm font-medium rounded-md text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 <?php echo basename($_SERVER['PHP_SELF']) == 'financeiro.php' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : ''; ?>">
                        <i data-lucide="dollar-sign" class="w-5 h-5 flex-shrink-0"></i>
                        <span class="sidebar-text">Financeiro</span>
                    </a>
                    <span class="sidebar-tooltip">Financeiro</span>
                </div>
            </li>
            
        </ul>
    </div>
    
    <!-- Área do usuário -->
    <div class="p-4 border-t dark:border-gray-700" x-data="{ userMenuOpen: false }">
        <div class="relative">
            <button @click="userMenuOpen = !userMenuOpen" class="w-full flex items-center gap-3 p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
    <?php if ($user && !empty($user['foto_path']) && file_exists($user['foto_path'])): // Linha corrigida ?>
        <img src="<?php echo htmlspecialchars($user['foto_path']); ?>" class="w-10 h-10 rounded-full object-cover flex-shrink-0" alt="Foto do usuário">
    <?php else: ?>
        <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center flex-shrink-0">
            <span class="text-white font-semibold text-sm">
                <?php echo $user ? strtoupper(substr($user['nome_completo'], 0, 1)) : 'U'; ?>
            </span>
        </div>
    <?php endif; ?>
    
    <div class="sidebar-text text-left">
        <p class="font-semibold text-sm text-gray-800 dark:text-gray-200 truncate">
            <?php echo $user ? htmlspecialchars($user['nome_completo']) : 'Usuário'; ?>
        </p>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            <?php 
            if ($user) {
                $perfil = $db->select("SELECT nome FROM perfis WHERE id = ?", [$user['perfil_id']]);
                echo $perfil ? htmlspecialchars($perfil[0]['nome']) : 'Usuário';
            } else {
                echo 'Usuário';
            }
            ?>
        </p>
    </div>
</button>
            
            <!-- Menu dropdown do usuário -->
            <div x-show="userMenuOpen" @click.away="userMenuOpen = false" x-transition class="absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-gray-700 rounded-md shadow-lg border dark:border-gray-600 sidebar-text">
                <a href="configuracoes.php" class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-t-md">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    Configurações
                </a>
                <hr class="border-gray-200 dark:border-gray-600">
                <a href="logout.php" class="flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-b-md">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                    Sair
                </a>
            </div>
        </div>
        
        <!-- Botão para colapsar sidebar -->
        <button id="sidebar-toggle" class="w-full mt-3 flex items-center justify-center p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md">
            <i data-lucide="chevrons-left" class="w-5 h-5 sidebar-text"></i>
            <i data-lucide="chevrons-right" class="w-5 h-5 hidden"></i>
        </button>
    </div>
    
</nav>

<!-- Alpine.js para interatividade -->
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
