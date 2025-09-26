<?php
// Handler AJAX - Salvar como ajax_handler.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Obtém a ação solicitada
$action = $_POST['action'] ?? '';

$db = getDB();
$response = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        
        case 'toggle_theme':
            $theme = $_POST['theme'] ?? 'light';
            $_SESSION['theme'] = $theme;
            
            // Salva a preferência no banco de dados
            $db->execute(
                "INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                ["user_{$_SESSION['user_id']}_theme", $theme]
            );
            
            $response['success'] = true;
            $response['message'] = 'Tema alterado com sucesso';
            break;
            
        case 'toggle_sidebar':
            $collapsed = $_POST['collapsed'] ?? '0';
            $_SESSION['sidebar_collapsed'] = ($collapsed === '1');
            
            // Salva a preferência no banco de dados
            $db->execute(
                "INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                ["user_{$_SESSION['user_id']}_sidebar_collapsed", $collapsed]
            );
            
            $response['success'] = true;
            $response['message'] = 'Preferência da sidebar salva';
            break;
            
        case 'save_table_columns':
            $table = $_POST['table'] ?? '';
            $columns = $_POST['columns'] ?? '';
            
            if (!empty($table) && !empty($columns)) {
                $db->execute(
                    "INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                    ["user_{$_SESSION['user_id']}_table_{$table}_columns", $columns]
                );
                
                $response['success'] = true;
                $response['message'] = 'Colunas da tabela salvas';
            } else {
                $response['message'] = 'Parâmetros inválidos';
            }
            break;
            
        case 'hide_dashboard':
            $page = $_POST['page'] ?? '';
            $hidden = $_POST['hidden'] ?? '0';
            
            if (!empty($page)) {
                $db->execute(
                    "INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
                    ["user_{$_SESSION['user_id']}_dashboard_{$page}_hidden", $hidden]
                );
                
                $response['success'] = true;
                $response['message'] = 'Preferência do dashboard salva';
            } else {
                $response['message'] = 'Parâmetros inválidos';
            }
            break;
            
        case 'delete_client':
            $clientId = intval($_POST['client_id'] ?? 0);
            
            if ($clientId > 0) {
                // Verifica se o cliente existe
                $client = $db->select("SELECT * FROM clientes WHERE id = ?", [$clientId]);
                
                if ($client) {
                    // Remove documentos associados
                    $documents = $db->select(
                        "SELECT * FROM documentos WHERE documentavel_id = ? AND documentavel_type = 'Cliente'", 
                        [$clientId]
                    );
                    
                    foreach ($documents as $doc) {
                        if (file_exists($doc['path_arquivo'])) {
                            unlink($doc['path_arquivo']);
                        }
                    }
                    
                    // Remove registros de documentos
                    $db->execute(
                        "DELETE FROM documentos WHERE documentavel_id = ? AND documentavel_type = 'Cliente'", 
                        [$clientId]
                    );
                    
                    // Remove o cliente
                    $deleted = $db->execute("DELETE FROM clientes WHERE id = ?", [$clientId]);
                    
                    if ($deleted) {
                        $response['success'] = true;
                        $response['message'] = 'Cliente excluído com sucesso';
                    } else {
                        $response['message'] = 'Erro ao excluir cliente';
                    }
                } else {
                    $response['message'] = 'Cliente não encontrado';
                }
            } else {
                $response['message'] = 'ID do cliente inválido';
            }
            break;
            
        case 'delete_clinic':
            $clinicId = intval($_POST['clinic_id'] ?? 0);
            
            if ($clinicId > 0) {
                // Verifica se a clínica existe
                $clinic = $db->select("SELECT * FROM clinicas WHERE id = ?", [$clinicId]);
                
                if ($clinic) {
                    // Remove documentos associados
                    $documents = $db->select(
                        "SELECT * FROM documentos WHERE documentavel_id = ? AND documentavel_type = 'Clinica'", 
                        [$clinicId]
                    );
                    
                    foreach ($documents as $doc) {
                        if (file_exists($doc['path_arquivo'])) {
                            unlink($doc['path_arquivo']);
                        }
                    }
                    
                    // Remove registros de documentos
                    $db->execute(
                        "DELETE FROM documentos WHERE documentavel_id = ? AND documentavel_type = 'Clinica'", 
                        [$clinicId]
                    );
                    
                    // Remove a clínica
                    $deleted = $db->execute("DELETE FROM clinicas WHERE id = ?", [$clinicId]);
                    
                    if ($deleted) {
                        $response['success'] = true;
                        $response['message'] = 'Clínica excluída com sucesso';
                    } else {
                        $response['message'] = 'Erro ao excluir clínica';
                    }
                } else {
                    $response['message'] = 'Clínica não encontrada';
                }
            } else {
                $response['message'] = 'ID da clínica inválido';
            }
            break;
            
        case 'delete_document':
            $documentId = intval($_POST['document_id'] ?? 0);
            
            if ($documentId > 0) {
                // Busca o documento
                $document = $db->select("SELECT * FROM documentos WHERE id = ?", [$documentId]);
                
                if ($document) {
                    $doc = $document[0];
                    
                    // Remove o arquivo físico
                    if (file_exists($doc['path_arquivo'])) {
                        unlink($doc['path_arquivo']);
                    }
                    
                    // Remove o registro do banco
                    $deleted = $db->execute("DELETE FROM documentos WHERE id = ?", [$documentId]);
                    
                    if ($deleted) {
                        $response['success'] = true;
                        $response['message'] = 'Documento excluído com sucesso';
                    } else {
                        $response['message'] = 'Erro ao excluir documento';
                    }
                } else {
                    $response['message'] = 'Documento não encontrado';
                }
            } else {
                $response['message'] = 'ID do documento inválido';
            }
            break;
            
        case 'search_clients':
            $searchTerm = trim($_POST['search'] ?? '');
            $limit = intval($_POST['limit'] ?? 50);
            
            if (!empty($searchTerm)) {
                $clients = $db->select(
                    "SELECT id, codigo_cliente, nome_completo, cpf, telefone1 
                     FROM clientes 
                     WHERE nome_completo LIKE ? OR cpf LIKE ? OR telefone1 LIKE ?
                     ORDER BY nome_completo 
                     LIMIT ?",
                    ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", $limit]
                );
                
                $response['success'] = true;
                $response['data'] = $clients;
            } else {
                $response['message'] = 'Termo de busca vazio';
            }
            break;
            
        case 'search_clinics':
            $searchTerm = trim($_POST['search'] ?? '');
            $limit = intval($_POST['limit'] ?? 50);
            
            if (!empty($searchTerm)) {
                $clinics = $db->select(
                    "SELECT id, codigo_clinica, nome_fantasia, cnpj, telefone1 
                     FROM clinicas 
                     WHERE nome_fantasia LIKE ? OR razao_social LIKE ? OR cnpj LIKE ?
                     ORDER BY nome_fantasia 
                     LIMIT ?",
                    ["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", $limit]
                );
                
                $response['success'] = true;
                $response['data'] = $clinics;
            } else {
                $response['message'] = 'Termo de busca vazio';
            }
            break;
            
        case 'get_client_data':
            $clientId = intval($_POST['client_id'] ?? 0);
            
            if ($clientId > 0) {
                $client = $db->select("SELECT * FROM clientes WHERE id = ?", [$clientId]);
                
                if ($client) {
                    $response['success'] = true;
                    $response['data'] = $client[0];
                } else {
                    $response['message'] = 'Cliente não encontrado';
                }
            } else {
                $response['message'] = 'ID do cliente inválido';
            }
            break;
            
        case 'get_clinic_data':
            $clinicId = intval($_POST['clinic_id'] ?? 0);
            
            if ($clinicId > 0) {
                $clinic = $db->select("SELECT * FROM clinicas WHERE id = ?", [$clinicId]);
                
                if ($clinic) {
                    $response['success'] = true;
                    $response['data'] = $clinic[0];
                } else {
                    $response['message'] = 'Clínica não encontrada';
                }
            } else {
                $response['message'] = 'ID da clínica inválido';
            }
            break;
            
        case 'validate_cpf':
            $cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
            $excludeId = intval($_POST['exclude_id'] ?? 0);
            
            if (!empty($cpf)) {
                if (validateCPF($cpf)) {
                    // Verifica se já existe no banco
                    $sql = "SELECT id FROM clientes WHERE cpf = ?";
                    $params = [$cpf];
                    
                    if ($excludeId > 0) {
                        $sql .= " AND id != ?";
                        $params[] = $excludeId;
                    }
                    
                    $existing = $db->select($sql, $params);
                    
                    if ($existing) {
                        $response['message'] = 'CPF já cadastrado';
                    } else {
                        $response['success'] = true;
                        $response['message'] = 'CPF válido';
                    }
                } else {
                    $response['message'] = 'CPF inválido';
                }
            } else {
                $response['message'] = 'CPF não informado';
            }
            break;
            
        case 'validate_cnpj':
            $cnpj = preg_replace('/\D/', '', $_POST['cnpj'] ?? '');
            $excludeId = intval($_POST['exclude_id'] ?? 0);
            
            if (!empty($cnpj)) {
                if (validateCNPJ($cnpj)) {
                    // Verifica se já existe no banco
                    $sql = "SELECT id FROM clinicas WHERE cnpj = ?";
                    $params = [$cnpj];
                    
                    if ($excludeId > 0) {
                        $sql .= " AND id != ?";
                        $params[] = $excludeId;
                    }
                    
                    $existing = $db->select($sql, $params);
                    
                    if ($existing) {
                        $response['message'] = 'CNPJ já cadastrado';
                    } else {
                        $response['success'] = true;
                        $response['message'] = 'CNPJ válido';
                    }
                } else {
                    $response['message'] = 'CNPJ inválido';
                }
            } else {
                $response['message'] = 'CNPJ não informado';
            }
            break;
            
        default:
            $response['message'] = 'Ação não reconhecida';
            break;
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Erro interno: ' . $e->getMessage();
    error_log("Erro no AJAX Handler: " . $e->getMessage());
}

// Retorna a resposta em JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
