<?php
// Template da Barra Superior - Salvar como templates/topbar.php

// Verifica se o usuário está logado
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Obtém informações do usuário
$db = getDB();
$user = $db->select("SELECT * FROM usuarios WHERE id = ?", [$_SESSION['user_id']]);
$user = $user ? $user[0] : null;

// Verifica se é aniversário do usuário
$isUserBirthday = false;
if ($user && isset($user['data_nascimento']) && $user['data_nascimento']) { // Adicionada a verificação "isset"
    $today = date('m-d');
    $birthday = date('m-d', strtotime($user['data_nascimento']));
    $isUserBirthday = ($today === $birthday && !empty($user['receber_felicitacoes_aniversario']));
}
?>

<header class="bg-white dark:bg-gray-800 shadow-sm border-b dark:border-gray-700">
    <div class="flex items-center justify-between px-6 py-4">
        
        <!-- Logo e título da página -->
        <div class="flex items-center gap-4">
            <!-- Logo da empresa -->
            <?php 
            $logoPath = getSystemConfig('empresa_logo_path');
            if ($logoPath && file_exists($logoPath)): 
            ?>
                <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="Logo" class="h-8 w-auto">
            <?php else: ?>
                <div class="h-8 w-8 bg-blue-500 rounded flex items-center justify-center">
                    <i data-lucide="activity" class="h-5 w-5 text-white"></i>
                </div>
            <?php endif; ?>
            
            <!-- Título da página atual -->
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Encaminha Mais+'; ?>
                </h1>
                <?php if (isset($pageSubtitle)): ?>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <?php echo htmlspecialchars($pageSubtitle); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ações da direita -->
        <div class="flex items-center gap-4">
            
            <!-- Atalhos rápidos -->
            <div class="hidden md:flex items-center gap-2">
                <!-- Cadastrar Cliente -->
                <a href="cliente_formulario.php" 
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md transition-colors"
                   title="Cadastrar Cliente">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span class="hidden lg:inline">Cliente</span>
                </a>
                
                <!-- Nova Guia -->
                <a href="nova_guia_encaminhamento.php" 
                   class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors"
                   title="Nova Guia">
                    <i data-lucide="file-plus" class="w-4 h-4"></i>
                    <span class="hidden lg:inline">Nova Guia</span>
                </a>
            </div>
            
            <!-- Separador -->
            <div class="hidden md:block w-px h-6 bg-gray-300 dark:bg-gray-600"></div>
            
            <!-- Alternador de tema -->
            <button id="theme-toggle" 
                    class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                    title="Alternar tema">
                <i data-lucide="sun" class="w-5 h-5 dark:hidden"></i>
                <i data-lucide="moon" class="w-5 h-5 hidden dark:block"></i>
            </button>
            
            <!-- Notificações -->
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" 
                        class="relative p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
                        title="Notificações">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <!-- Badge de notificação -->
                    <span class="absolute -top-1 -right-1 h-4 w-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center">
                        3
                    </span>
                </button>
                
                <!-- Dropdown de notificações -->
                <div x-show="open" @click.away="open = false" x-transition
                     class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-md shadow-lg border dark:border-gray-700 z-50">
                    <div class="p-4 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Notificações</h3>
                    </div>
                    <div class="max-h-64 overflow-y-auto">
                        <!-- Exemplo de notificação -->
                        <div class="p-4 border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0">
                                    <i data-lucide="calendar" class="w-5 h-5 text-blue-500"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        Aniversariantes hoje
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        3 clientes fazem aniversário hoje
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                        Há 2 horas
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mais notificações... -->
                        <div class="p-4 text-center">
                            <a href="#" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                Ver todas as notificações
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ícone de aniversário (se for aniversário do usuário) -->
            <?php if ($isUserBirthday): ?>
                <button id="birthday-celebration" 
                        class="p-2 text-yellow-500 hover:text-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 rounded-md transition-colors animate-bounce"
                        title="Feliz Aniversário! 🎉">
                    <i data-lucide="party-popper" class="w-5 h-5"></i>
                </button>
            <?php endif; ?>
            
            <!-- Configurações -->
            <a href="configuracoes.php" 
               class="p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-md transition-colors"
               title="Configurações">
                <i data-lucide="settings" class="w-5 h-5"></i>
            </a>
            
        </div>
    </div>
</header>

<!-- Modal de celebração de aniversário -->
<?php if ($isUserBirthday): ?>
<div id="birthday-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 max-w-md mx-4 text-center">
        <div class="text-6xl mb-4">🎉</div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
            Feliz Aniversário!
        </h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">
            Desejamos um dia repleto de alegrias e realizações, <?php echo htmlspecialchars(explode(' ', $user['nome_completo'])[0]); ?>!
        </p>
        <button onclick="closeBirthdayModal()" 
                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
            Obrigado! 😊
        </button>
    </div>
</div>

<!-- Confetes animados -->
<div id="confetti-container" style="display: none;"></div>

<style>
.confetti {
    position: fixed;
    width: 10px;
    height: 10px;
    background: #f0f;
    animation: confetti-fall 3s linear infinite;
    z-index: 1000;
}

@keyframes confetti-fall {
    0% {
        transform: translateY(-100vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(100vh) rotate(720deg);
        opacity: 0;
    }
}
</style>

<script>
// Mostrar modal de aniversário automaticamente
document.addEventListener('DOMContentLoaded', function() {
    // Verifica se já foi mostrado hoje
    const today = new Date().toDateString();
    const lastShown = localStorage.getItem('birthday_modal_shown');
    
    if (lastShown !== today) {
        setTimeout(() => {
            showBirthdayModal();
            localStorage.setItem('birthday_modal_shown', today);
        }, 1000);
    }
});

// Mostrar modal ao clicar no ícone
document.getElementById('birthday-celebration')?.addEventListener('click', showBirthdayModal);

function showBirthdayModal() {
    document.getElementById('birthday-modal').style.display = 'flex';
    createConfetti();
}

function closeBirthdayModal() {
    document.getElementById('birthday-modal').style.display = 'none';
    document.getElementById('confetti-container').style.display = 'none';
}

function createConfetti() {
    const container = document.getElementById('confetti-container');
    container.style.display = 'block';
    container.innerHTML = '';
    
    const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7', '#dda0dd'];
    
    for (let i = 0; i < 50; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.animationDelay = Math.random() * 3 + 's';
        confetti.style.animationDuration = (Math.random() * 3 + 2) + 's';
        container.appendChild(confetti);
    }
    
    // Remove confetes após a animação
    setTimeout(() => {
        container.style.display = 'none';
    }, 6000);
}
</script>
<?php endif; ?>

<script>
// Alternador de tema
document.getElementById('theme-toggle').addEventListener('click', function() {
    const html = document.documentElement;
    const isDark = html.classList.contains('dark');
    
    if (isDark) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    }
    
    // Salva a preferência no servidor
    fetch('ajax_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=toggle_theme&theme=' + (isDark ? 'light' : 'dark')
    });
});

// Carrega tema salvo
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
    }
});
</script>
