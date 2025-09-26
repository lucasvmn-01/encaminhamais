<?php
// --- CONFIGURAÇÃO ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'encaminhamais_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- SCRIPT DE RESET ---
echo "<!DOCTYPE html><html lang='pt-BR'><head><meta charset='UTF-8'><title>Reset de Senha Admin</title>";
echo "<style>body { font-family: sans-serif; background-color: #f0f2f5; color: #333; padding: 2em; } .container { max-width: 600px; margin: auto; background: white; padding: 2em; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); } .success { color: #28a745; font-weight: bold; } .error { color: #dc3545; font-weight: bold; }</style>";
echo "</head><body><div class='container'>";
echo "<h1>Assistente de Recuperação de Senha</h1>";

try {
    // 1. Conectar ao banco de dados
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Conexão com o banco de dados bem-sucedida!</p>";

    // 2. Definir o e-mail do usuário e a nova senha
    $email_para_resetar = 'admin@encaminhamais.com';
    $nova_senha = 'admin123';
    echo "<p>🔧 Tentando resetar a senha para o usuário com o e-mail '<b>" . htmlspecialchars($email_para_resetar) . "</b>' e ativá-lo.</p>";

    // 3. Gerar o hash da nova senha
    $hash_da_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
    echo "<p>🔑 Hash da nova senha gerado com sucesso.</p>";

    // 4. Preparar e executar a atualização no banco (usando as colunas corretas 'senha' e 'ativo')
    $sql = "UPDATE usuarios SET senha = :senha, ativo = 1 WHERE email = :email"; // <-- CORREÇÃO AQUI
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':senha', $hash_da_senha);
    $stmt->bindParam(':email', $email_para_resetar);
    
    $stmt->execute();

    // 5. Verificar se a alteração foi feita
    $linhas_afetadas = $stmt->rowCount();
    if ($linhas_afetadas > 0) {
        echo "<p class='success'>🚀 SUCESSO! A senha do usuário foi atualizada e o status foi definido como 'ativo'.</p>";
        echo "<p>Agora você já pode acessar o sistema com:</p>";
        echo "<ul>";
        echo "<li><b>Usuário (E-mail):</b> " . htmlspecialchars($email_para_resetar) . "</li>";
        echo "<li><b>Senha:</b> " . htmlspecialchars($nova_senha) . "</li>";
        echo "</ul>";
        echo "<a href='login.php' style='display:inline-block; margin-top:1em; padding: 0.8em 1.5em; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;'>Ir para a tela de Login</a>";
    } else {
        echo "<p class='error'>❌ FALHA! Nenhum usuário com o e-mail '" . htmlspecialchars($email_para_resetar) . "' foi encontrado. Verifique no phpMyAdmin se o e-mail está correto.</p>";
    }

} catch (PDOException $e) {
    echo "<p class='error'>❌ ERRO CRÍTICO: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>