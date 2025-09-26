<?php
// Página de Logout - Salvar como logout.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
if (isLoggedIn()) {
    $db = getDB();
    
    // Atualiza o último logout no banco de dados
    $db->execute(
        "UPDATE usuarios SET ultimo_logout = NOW() WHERE id = ?", 
        [$_SESSION['user_id']]
    );
    
    // Armazena algumas informações antes de destruir a sessão
    $username = $_SESSION['username'] ?? '';
    $nome_completo = $_SESSION['nome_completo'] ?? '';
}

// Destrói todas as variáveis de sessão
$_SESSION = array();

// Se existir um cookie de sessão, remove ele
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

$pageTitle = 'Logout';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Encaminha Mais+</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- CSS customizado -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .logout-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .floating-animation {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-animation {
            animation: successPulse 2s ease-in-out infinite;
        }
        
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">
    
    <!-- Elementos decorativos de fundo -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-white opacity-10 rounded-full floating-animation"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-white opacity-5 rounded-full floating-animation" style="animation-delay: -1s;"></div>
        <div class="absolute top-1/2 left-1/4 w-32 h-32 bg-white opacity-10 rounded-full floating-animation" style="animation-delay: -2s;"></div>
    </div>
    
    <!-- Card de Logout -->
    <div class="logout-card w-full max-w-md rounded-2xl shadow-2xl p-8 relative z-10 fade-in">
        
        <!-- Ícone de Sucesso -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-500 rounded-full mb-4 success-animation">
                <i data-lucide="check" class="w-10 h-10 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Logout Realizado</h1>
            <p class="text-gray-600">Você foi desconectado com sucesso</p>
        </div>
        
        <!-- Mensagem de Despedida -->
        <div class="text-center mb-8">
            <?php if (isset($nome_completo) && !empty($nome_completo)): ?>
                <p class="text-lg text-gray-700 mb-2">
                    Até logo, <strong><?php echo htmlspecialchars(explode(' ', $nome_completo)[0]); ?></strong>!
                </p>
            <?php endif; ?>
            <p class="text-sm text-gray-500">
                Obrigado por usar o sistema Encaminha Mais+
            </p>
        </div>
        
        <!-- Informações de Segurança -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <i data-lucide="shield-check" class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0"></i>
                <div class="text-blue-700 text-sm">
                    <p class="font-medium mb-1">Sua sessão foi encerrada com segurança</p>
                    <ul class="text-xs space-y-1">
                        <li>• Todos os dados da sessão foram removidos</li>
                        <li>• Cookies de autenticação foram limpos</li>
                        <li>• Horário do logout foi registrado no sistema</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Botões de Ação -->
        <div class="space-y-3">
            <a href="login.php" 
               class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center justify-center">
                <i data-lucide="log-in" class="w-5 h-5 mr-2"></i>
                Fazer Login Novamente
            </a>
            
            <button onclick="window.close()" 
                    class="w-full bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 flex items-center justify-center">
                <i data-lucide="x" class="w-5 h-5 mr-2"></i>
                Fechar Janela
            </button>
        </div>
        
        <!-- Informações de Contato -->
        <div class="mt-8 text-center">
            <p class="text-xs text-gray-500 mb-2">
                Precisa de ajuda? Entre em contato:
            </p>
            <div class="flex justify-center space-x-4 text-xs">
                <a href="mailto:suporte@encaminhamais.com" class="text-blue-600 hover:underline flex items-center">
                    <i data-lucide="mail" class="w-3 h-3 mr-1"></i>
                    E-mail
                </a>
                <a href="tel:+5511999999999" class="text-blue-600 hover:underline flex items-center">
                    <i data-lucide="phone" class="w-3 h-3 mr-1"></i>
                    Telefone
                </a>
            </div>
        </div>
        
        <!-- Rodapé -->
        <div class="mt-8 text-center text-xs text-gray-500">
            <p>&copy; <?php echo date('Y'); ?> Encaminha Mais+. Todos os direitos reservados.</p>
            <p class="mt-1">Logout realizado em <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
        
    </div>
    
    <!-- Scripts -->
    <script>
        // Inicializa os ícones
        lucide.createIcons();
        
        // Redireciona automaticamente após 10 segundos
        let countdown = 10;
        const countdownElement = document.createElement('div');
        countdownElement.className = 'text-center mt-4 text-sm text-gray-500';
        countdownElement.innerHTML = `Redirecionando para login em <span id="countdown">${countdown}</span> segundos...`;
        document.querySelector('.logout-card').appendChild(countdownElement);
        
        const countdownTimer = setInterval(() => {
            countdown--;
            document.getElementById('countdown').textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                window.location.href = 'login.php';
            }
        }, 1000);
        
        // Para o countdown se o usuário interagir com a página
        document.addEventListener('click', () => {
            clearInterval(countdownTimer);
            countdownElement.style.display = 'none';
        });
        
        document.addEventListener('keydown', () => {
            clearInterval(countdownTimer);
            countdownElement.style.display = 'none';
        });
        
        // Limpa o localStorage (se houver dados do sistema)
        try {
            localStorage.removeItem('theme');
            localStorage.removeItem('sidebar_collapsed');
            localStorage.removeItem('dashboard_preferences');
            // Adicione outras chaves conforme necessário
        } catch (e) {
            console.log('Erro ao limpar localStorage:', e);
        }
        
        // Previne o botão "Voltar" do navegador
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
        
        // Efeito de confetes para celebrar o logout seguro
        function createConfetti() {
            const colors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7'];
            
            for (let i = 0; i < 20; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '6px';
                confetti.style.height = '6px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.top = '-10px';
                confetti.style.borderRadius = '50%';
                confetti.style.pointerEvents = 'none';
                confetti.style.zIndex = '1000';
                confetti.style.animation = `fall ${Math.random() * 3 + 2}s linear forwards`;
                
                document.body.appendChild(confetti);
                
                setTimeout(() => {
                    if (confetti.parentNode) {
                        confetti.parentNode.removeChild(confetti);
                    }
                }, 5000);
            }
        }
        
        // Adiciona a animação de queda
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                }
            }
        `;
        document.head.appendChild(style);
        
        // Executa o confetti após 1 segundo
        setTimeout(createConfetti, 1000);
    </script>
    
</body>
</html>
