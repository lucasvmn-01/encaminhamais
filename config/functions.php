<?php
// Funções Auxiliares do Sistema - Salvar como config/functions.php

// Função para verificar se o usuário está logado
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Função para redirecionar se não estiver logado
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

// Função para verificar permissão
function hasPermission($permissao) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Por enquanto, admin tem todas as permissões
    if ($_SESSION['perfil_id'] == 1) {
        return true;
    }
    
    // Implementar lógica de permissões específicas aqui
    return false;
}

// Função para formatar CPF
function formatCPF($cpf) {
    if (empty($cpf)) return '';
    $cpf = preg_replace('/\D/', '', $cpf);
    return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
}

// Função para formatar CNPJ
function formatCNPJ($cnpj) {
    if (empty($cnpj)) return '';
    $cnpj = preg_replace('/\D/', '', $cnpj);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
}

// Função para formatar telefone
function formatPhone($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/\D/', '', $phone);
    
    // Remove código do país se presente
    if (substr($phone, 0, 2) == '55') {
        $phone = substr($phone, 2);
    }
    
    // Formata o telefone
    if (strlen($phone) == 11) {
        return '+55 (' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 1) . ' ' . substr($phone, 3, 4) . '-' . substr($phone, 7);
    } elseif (strlen($phone) == 10) {
        return '+55 (' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
    }
    
    return $phone;
}

// Função para gerar WhatsApp link
function getWhatsAppLink($phone) {
    if (empty($phone)) return '#';
    $phone = preg_replace('/\D/', '', $phone);
    
    // Adiciona código do país se não tiver
    if (substr($phone, 0, 2) != '55') {
        $phone = '55' . $phone;
    }
    
    return 'https://wa.me/' . $phone;
}

// Função para calcular idade
function calculateAge($birthDate) {
    if (empty($birthDate)) return 0;
    $birth = new DateTime($birthDate);
    $today = new DateTime();
    return $birth->diff($today)->y;
}

// Função para determinar faixa etária
function getAgeGroup($age) {
    if ($age <= 2) return 'Berçário';
    if ($age <= 4) return 'Maternal';
    if ($age <= 6) return 'Jardim de Infância';
    if ($age <= 8) return 'Primários';
    if ($age <= 10) return 'Juniores';
    if ($age <= 12) return 'Pré-adolescentes';
    if ($age <= 14) return 'Adolescentes';
    if ($age <= 17) return 'Juvenis';
    if ($age <= 20) return 'Jovens';
    if ($age <= 40) return 'Adulto (Fase Inicial)';
    if ($age <= 50) return 'Adulto (Fase Intermediária)';
    if ($age <= 60) return 'Adulto (Fase Maduro)';
    return 'Melhor Idade';
}

// Função para gerar próximo código de cliente
function getNextClientCode() {
    $db = getDB();
    $result = $db->select("SELECT codigo_cliente FROM clientes ORDER BY id DESC LIMIT 1");
    
    if (empty($result)) {
        return 'P000001';
    }
    
    $lastCode = $result[0]['codigo_cliente'];
    $number = intval(substr($lastCode, 1)) + 1;
    return 'P' . str_pad($number, 6, '0', STR_PAD_LEFT);
}

// Função para gerar próximo código de clínica
function getNextClinicCode() {
    $db = getDB();
    $result = $db->select("SELECT codigo_clinica FROM clinicas ORDER BY id DESC LIMIT 1");
    
    if (empty($result)) {
        return 'CL000001';
    }
    
    $lastCode = $result[0]['codigo_clinica'];
    $number = intval(substr($lastCode, 2)) + 1;
    return 'CL' . str_pad($number, 6, '0', STR_PAD_LEFT);
}

// Função para upload de arquivos
function uploadFile($file, $destination, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Nenhum arquivo foi enviado.'];
    }
    
    $fileName = $file['name'];
    $fileTmp = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileError = $file['error'];
    
    if ($fileError !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erro no upload do arquivo.'];
    }
    
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de arquivo não permitido.'];
    }
    
    if ($fileSize > 10 * 1024 * 1024) { // 10MB
        return ['success' => false, 'message' => 'Arquivo muito grande. Máximo 10MB.'];
    }
    
    $newFileName = uniqid() . '_' . $fileName;
    $uploadPath = $destination . '/' . $newFileName;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (move_uploaded_file($fileTmp, $uploadPath)) {
        return [
            'success' => true, 
            'filename' => $newFileName,
            'original_name' => $fileName,
            'path' => $uploadPath,
            'size' => $fileSize,
            'type' => $fileExt
        ];
    }
    
    return ['success' => false, 'message' => 'Erro ao salvar o arquivo.'];
}

// Função para formatar data brasileira
function formatDateBR($date, $includeTime = false) {
    if (empty($date)) return '';
    
    $dateObj = new DateTime($date);
    $format = $includeTime ? 'd/m/Y H:i' : 'd/m/Y';
    return $dateObj->format($format);
}

// Função para sanitizar entrada
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Função para validar CPF
function validateCPF($cpf) {
    $cpf = preg_replace('/\D/', '', $cpf);
    
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

// Função para validar CNPJ
function validateCNPJ($cnpj) {
    $cnpj = preg_replace('/\D/', '', $cnpj);
    
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Validação dos dígitos verificadores
    for ($i = 0, $j = 5, $soma = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    
    $resto = $soma % 11;
    
    if ($cnpj[12] != ($resto < 2 ? 0 : 11 - $resto)) {
        return false;
    }
    
    for ($i = 0, $j = 6, $soma = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $j;
        $j = ($j == 2) ? 9 : $j - 1;
    }
    
    $resto = $soma % 11;
    
    return $cnpj[13] == ($resto < 2 ? 0 : 11 - $resto);
}

// Função para obter saudação por horário
function getGreeting() {
    $hour = date('H');
    
    if ($hour >= 5 && $hour < 12) {
        return 'Bom dia';
    } elseif ($hour >= 12 && $hour < 18) {
        return 'Boa tarde';
    } else {
        return 'Boa noite';
    }
}

// Função para obter configuração do sistema
function getSystemConfig($key, $default = null) {
    $db = getDB();
    $result = $db->select("SELECT valor FROM configuracoes_sistema WHERE chave = ?", [$key]);
    
    if (!empty($result)) {
        return $result[0]['valor'];
    }
    
    return $default;
}

// Função para definir configuração do sistema
function setSystemConfig($key, $value) {
    $db = getDB();
    return $db->execute(
        "INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?) 
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
        [$key, $value]
    );
}
?>
