<?php
// Arquivo Central de Funções - Salvar como src/core/functions.php

/**
 * Gera um novo código de cliente sequencial (ex: P000004).
 *
 * @return string O novo código do cliente.
 */
function generateClientCode() {
    $ultimoCod = 0;
    if (!empty($_SESSION['clientes'])) {
        $todosCodigos = array_map(function($c) { return (int)substr($c['COD_CLIENTE'], 1); }, $_SESSION['clientes']);
        $ultimoCod = count($todosCodigos) > 0 ? max($todosCodigos) : 0;
    }
    $novoCodNumerico = $ultimoCod + 1;
    return 'P' . str_pad($novoCodNumerico, 6, '0', STR_PAD_LEFT);
}

/**
 * Calcula a idade a partir de uma data de nascimento.
 *
 * @param string|null $dataNasc A data de nascimento no formato YYYY-MM-DD.
 * @return string A idade em anos ou '?' se a data for inválida.
 */
function calcularIdade($dataNasc) { 
    if(!$dataNasc || $dataNasc == '0000-00-00') return '?'; 
    try {
        return date_diff(date_create($dataNasc), date_create('now'))->y; 
    } catch (Exception $e) {
        return '?';
    }
}

// (No futuro, adicionaremos outras funções aqui, como formatarMoeda, etc.)

?>