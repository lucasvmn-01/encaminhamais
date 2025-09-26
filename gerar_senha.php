<?php
// Escolha sua nova senha aqui
$nova_senha = 'admin123';

// Gera o hash da senha
$hash = password_hash($nova_senha, PASSWORD_DEFAULT);

// Exibe o hash gerado
echo "Sua nova senha criptografada é: <br><br>";
echo $hash;
?>