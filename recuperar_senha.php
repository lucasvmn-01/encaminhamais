<?php
// Página de Recuperação de Senha - Salvar como recuperar_senha.php

session_start();

// Se já estiver logado, redireciona para a página inicial
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: bem_vindo.php');
    exit;
}

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

$message = '';
$message_type = '';

// Processa a recuperação de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($cpf) || empty($email)) {
        $message = 'Por favor, preencha todos os campos.';
        $message_type = 'error';
    } elseif (!validateCPF($cpf)) {
        $message = 'CPF inválido.';
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'E-mail inválido.';
        $message_type = 'error';
    } else {
        $db = getDB();
        
        // Busca o usuário no banco de dados
        $user = $db->select(
            "SELECT * FROM usuarios WHERE cpf = ? AND email = ? AND status = 'ativo'", 
            [$cpf, $email]
        );
        
        if ($user) {
            $userData = $user[0];
            
            // Gera nova senha temporária
            $newPassword = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Atualiza a senha no banco
            $updated = $db->execute(
                "UPDATE usuarios SET senha = ? WHERE id = ?", 
                [$hashedPassword, $userData['id']]
            );
            
            if ($updated) {
                // Aqui você implementaria o envio do e-mail
                // Por enquanto, vamos simular mostrando as credenciais na tela
                
                $message = "Nova senha gerada com sucesso!<br><br>
                          <strong>Usuário:</strong> " . htmlspecialchars($userData['username']) . "<br>
                          <strong>Nova Senha:</strong> " . htmlspecialchars($newPassword) . "<br><br>
                          <small>Em um ambiente real, essas informações seriam enviadas para o e-mail cadastrado.</small>";
                $message_type = 'success';
                
                // Log da ação
                error_log("Senha recuperada para usuário: " . $userData['username'] . " - Nova senha: " . $newPassword);
            } else {
                $message = 'Erro interno. Tente novamente mais tarde.';
                $message_type = 'error';
            }
        } else {
            $message = 'CPF e e-mail não encontrados ou não correspondem a um usuário ativo.';
            $message_type = 'error';
        }
    }
}

$pageTitle = 'Recuperar Senha';
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
        
        .recovery-card {
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
    
    <!-- Card de Recuperação -->
    <div class="recovery-card w-full max-w-md rounded-2xl shadow-2xl p-8 relative z-10">
        
        <!-- Logo e Título -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-orange-600 rounded-full mb-4 pulse-animation">
                <i data-lucide="key" class="w-8 h-8 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Recuperar Senha</h1>
            <p class="text-gray-600">Digite seus dados para recuperar o acesso</p>
        </div>
        
        <!-- Mensagem de Resultado -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
                <div class="flex items-start">
                    <i data-lucide="<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>" 
                       class="w-5 h-5 <?php echo $message_type === 'success' ? 'text-green-500' : 'text-red-500'; ?> mr-2 mt-0.5 flex-shrink-0"></i>
                    <div class="<?php echo $message_type === 'success' ? 'text-green-700' : 'text-red-700'; ?> text-sm">
                        <?php echo $message; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Formulário de Recuperação -->
        <form method="POST" action="" class="space-y-6">
            
            <!-- Campo CPF -->
            <div>
                <label for="cpf" class="block text-sm font-medium text-gray-700 mb-2">
                    CPF
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="credit-card" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="text" 
                           id="cpf" 
                           name="cpf" 
                           required
                           data-mask="cpf"
                           maxlength="14"
                           value="<?php echo isset($_POST['cpf']) ? htmlspecialchars($_POST['cpf']) : ''; ?>"
                           class="input-destacado w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200"
                           placeholder="000.000.000-00">
                </div>
            </div>
            
            <!-- Campo E-mail -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                    E-mail
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="mail" class="w-5 h-5 text-gray-400"></i>
                    </div>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           class="input-destacado w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all duration-200"
                           placeholder="seu@email.com">
                </div>
            </div>
            
            <!-- Informação sobre o processo -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <i data-lucide="info" class="w-5 h-5 text-blue-500 mr-2 mt-0.5 flex-shrink-0"></i>
                    <div class="text-blue-700 text-sm">
                        <p class="font-medium mb-1">Como funciona:</p>
                        <ul class="text-xs space-y-1">
                            <li>• Digite o CPF e e-mail cadastrados no sistema</li>
                            <li>• Uma nova senha será gerada automaticamente</li>
                            <li>• As credenciais serão enviadas para o e-mail informado</li>
                            <li>• Faça login com a nova senha e altere-a nas configurações</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Botão de Recuperar -->
            <button type="submit" 
                    class="w-full bg-orange-600 hover:bg-orange-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                <div class="flex items-center justify-center">
                    <i data-lucide="send" class="w-5 h-5 mr-2"></i>
                    Recuperar Senha
                </div>
            </button>
            
        </form>
        
        <!-- Link para voltar ao login -->
        <div class="mt-6 text-center">
            <a href="login.php" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800 hover:underline">
                <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i>
                Voltar ao Login
            </a>
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
        
        // Máscara de CPF
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
        
        // Foco automático no primeiro campo
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('cpf').focus();
        });
        
        // Animação de loading no botão ao submeter
        document.querySelector('form').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            const originalContent = button.innerHTML;
            
            button.innerHTML = `
                <div class="flex items-center justify-center">
                    <div class="spinner mr-2"></div>
                    Processando...
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
        
        // Validação em tempo real do CPF
        document.getElementById('cpf').addEventListener('blur', function() {
            const cpf = this.value.replace(/\D/g, '');
            
            if (cpf.length === 11) {
                // Validação básica de CPF
                if (!/^(\d)\1{10}$/.test(cpf)) {
                    let sum = 0;
                    for (let i = 0; i < 9; i++) {
                        sum += parseInt(cpf.charAt(i)) * (10 - i);
                    }
                    let remainder = 11 - (sum % 11);
                    if (remainder === 10 || remainder === 11) remainder = 0;
                    
                    if (remainder !== parseInt(cpf.charAt(9))) {
                        this.setCustomValidity('CPF inválido');
                        this.classList.add('border-red-500');
                        return;
                    }
                    
                    sum = 0;
                    for (let i = 0; i < 10; i++) {
                        sum += parseInt(cpf.charAt(i)) * (11 - i);
                    }
                    remainder = 11 - (sum % 11);
                    if (remainder === 10 || remainder === 11) remainder = 0;
                    
                    if (remainder !== parseInt(cpf.charAt(10))) {
                        this.setCustomValidity('CPF inválido');
                        this.classList.add('border-red-500');
                        return;
                    }
                }
            }
            
            this.setCustomValidity('');
            this.classList.remove('border-red-500');
        });
        
        // Limpa validação ao digitar
        document.getElementById('cpf').addEventListener('input', function() {
            this.setCustomValidity('');
            this.classList.remove('border-red-500');
        });
    </script>
    
</body>
</html>
