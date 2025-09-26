<?php
// Página de Login - Salvar como login.php

session_start();

// Se já estiver logado, redireciona para a página inicial
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: bem_vindo.php');
    exit;
}

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

$error_message = '';

// Processa o login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['username'] ?? ''); // Variável corrigida para $email
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Por favor, preencha todos os campos.';
    } else {
        $db = getDB();
        
        // Busca o usuário no banco de dados pelo EMAIL
        $user = $db->select(
            "SELECT u.*, p.nome as perfil_nome 
     FROM usuarios u 
     LEFT JOIN perfis p ON u.perfil_id = p.id 
     WHERE u.email = ? AND u.ativo = 1", // <-- CORREÇÃO AQUI
    [$email] // Variável corrigida para $email
        );
        
        if ($user && password_verify($password, $user[0]['senha'])) {
            // Login bem-sucedido
            $userData = $user[0];
            
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['nome_completo'] = $userData['nome_completo'];
            $_SESSION['email'] = $userData['email'];
            $_SESSION['perfil_id'] = $userData['perfil_id'];
            $_SESSION['perfil_nome'] = $userData['perfil_nome'];
            $_SESSION['foto_path'] = $userData['foto_path'];
            
            // Atualiza último logout (será usado para mostrar quando foi o último acesso)
            $db->execute(
                "UPDATE usuarios SET ultimo_logout = NOW() WHERE id = ?", 
                [$userData['id']]
            );
            
            // Redireciona para a página inicial
            header('Location: bem_vindo.php');
            exit;
        } else {
            $error_message = 'Usuário ou senha inválidos.';
        }
    }
}

// Verifica se há mensagem de erro na URL (vinda de outras páginas)
if (isset($_GET['error'])) {
    $error_message = htmlspecialchars($_GET['error']);
}

$pageTitle = 'Login';
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
        
        .login-card {
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
        
        .pulse-animation {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
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
    
    <!-- Card de Login -->
    <div class="login-card w-full max-w-md rounded-2xl shadow-2xl p-8 relative z-10">
        
        <!-- Logo e Título -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-blue-600 rounded-full mb-4 pulse-animation">
                <i data-lucide="activity" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Encaminha Mais+</h1>
            <p class="text-gray-600">Sistema de Gestão de Encaminhamentos</p>
        </div>
        
        <!-- Mensagem de Erro -->
        <?php if (!empty($error_message)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-center">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
                    <span class="text-red-700 text-sm"><?php echo $error_message; ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Formulário de Login -->
        <form method="POST" action="" class="space-y-6">
            
            <!-- Campo Usuário -->
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                    Usuário
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="user" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           required
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           class="input-destacado w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                           placeholder="Digite seu usuário"
                           autocomplete="username">
                </div>
            </div>
            
            <!-- Campo Senha -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    Senha
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="lock" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required
                           class="input-destacado w-full pl-10 pr-12 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200"
                           placeholder="Digite sua senha"
                           autocomplete="current-password">
                    <button type="button" 
                            id="toggle-password"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                        <i data-lucide="eye" class="w-5 h-5 text-gray-400 hover:text-gray-600 cursor-pointer"></i>
                    </button>
                </div>
            </div>
            
            <!-- Lembrar-me e Esqueci a senha -->
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input type="checkbox" 
                           id="remember" 
                           name="remember"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="remember" class="ml-2 block text-sm text-gray-700">
                        Lembrar-me
                    </label>
                </div>
                <a href="recuperar_senha.php" class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
                    Esqueci minha senha
                </a>
            </div>
            
            <!-- Botão de Login -->
            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                <div class="flex items-center justify-center">
                    <i data-lucide="log-in" class="w-5 h-5 mr-2"></i>
                    Entrar
                </div>
            </button>
            
        </form>
        
        <!-- Informações de Teste -->
        <div class="mt-8 p-4 bg-gray-50 rounded-lg">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Usuários de Teste:</h3>
            <div class="text-xs text-gray-600 space-y-1">
                <div><strong>Admin:</strong> admin / admin</div>
                <div><strong>Usuário 1:</strong> user1 / user1</div>
                <div><strong>Usuário 2:</strong> user2 / user2</div>
            </div>
        </div>
        
        <!-- Rodapé -->
        <div class="mt-8 text-center text-xs text-gray-500">
            <p>&copy; <?php echo date('Y'); ?> Encaminha Mais+. Todos os direitos reservados.</p>
        </div>
        
    </div>
    
    <!-- Scripts -->
    <script>
        // Inicializa os ícones
        lucide.createIcons();
        
        // Toggle para mostrar/ocultar senha
        document.getElementById('toggle-password').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.setAttribute('data-lucide', 'eye-off');
            } else {
                passwordInput.type = 'password';
                eyeIcon.setAttribute('data-lucide', 'eye');
            }
            
            // Recria os ícones
            lucide.createIcons();
        });
        
        // Foco automático no primeiro campo
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Animação de loading no botão ao submeter
        document.querySelector('form').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            const originalContent = button.innerHTML;
            
            button.innerHTML = `
                <div class="flex items-center justify-center">
                    <div class="spinner mr-2"></div>
                    Entrando...
                </div>
            `;
            button.disabled = true;
            
            // Se houver erro, restaura o botão (isso será feito pelo reload da página)
            setTimeout(() => {
                button.innerHTML = originalContent;
                button.disabled = false;
                lucide.createIcons();
            }, 5000);
        });
        
        // Efeito de digitação no placeholder
        const usernameInput = document.getElementById('username');
        const originalPlaceholder = usernameInput.placeholder;
        
        usernameInput.addEventListener('focus', function() {
            this.placeholder = '';
        });
        
        usernameInput.addEventListener('blur', function() {
            if (this.value === '') {
                this.placeholder = originalPlaceholder;
            }
        });
    </script>
    
</body>
</html>
