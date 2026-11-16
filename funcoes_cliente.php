<?php

function somenteDigitos($v)
{
    return preg_replace('/\D/', '', (string)$v);
}

/** WhatsApp só com dígitos, sem o 55 do país (DDD + número = 10 ou 11 dígitos). */
function normalizarWhatsapp($v)
{
    $d = somenteDigitos($v);
    if (strlen($d) >= 12 && strpos($d, '55') === 0) $d = substr($d, 2);
    return $d;
}

function whatsappValido($digitos)
{
    return in_array(strlen($digitos), [10, 11], true);
}

function validarCpf($cpf)
{
    $cpf = somenteDigitos($cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) $soma += (int)$cpf[$i] * (($t + 1) - $i);
        $dig = (($soma * 10) % 11) % 10;
        if ((int)$cpf[$t] !== $dig) return false;
    }
    return true;
}

function validarCnpj($cnpj)
{
    $cnpj = somenteDigitos($cnpj);
    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
    foreach ([12, 13] as $t) {
        $pesos = ($t === 12) ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < $t; $i++) $soma += (int)$cnpj[$i] * $pesos[$i];
        $resto = $soma % 11;
        $dig = ($resto < 2) ? 0 : 11 - $resto;
        if ((int)$cnpj[$t] !== $dig) return false;
    }
    return true;
}

/** CPF (11 dígitos) ou CNPJ (14 dígitos) válido. */
function validarDocumento($doc)
{
    $d = somenteDigitos($doc);
    if (strlen($d) === 11) return validarCpf($d);
    if (strlen($d) === 14) return validarCnpj($d);
    return false;
}

/** Devolve o documento sempre no mesmo formato (é assim que fica gravado no banco). */
function formatarDocumento($doc)
{
    $d = somenteDigitos($doc);
    if (strlen($d) === 11) {
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
    if (strlen($d) === 14) {
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    }
    return $d;
}

/** Abre a sessão do cliente (usada no login e no cadastro). */
function iniciarSessaoCliente($id, $nome, $email, $whatsapp)
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!headers_sent()) session_regenerate_id(true);

    unset($_SESSION['cliente_id'], $_SESSION['cliente_nome'], $_SESSION['cliente_email']);

    $_SESSION['usuario_id']       = (int)$id;
    $_SESSION['usuario_nome']     = (string)$nome;
    $_SESSION['usuario_email']    = (string)$email;
    $_SESSION['usuario_whatsapp'] = normalizarWhatsapp($whatsapp);
}

/**
 * Redireciona por JavaScript (as páginas rodam depois do HTML do index.php já ter começado,
 * então header("Location") não funciona). O texto é escapado com json_encode: nada vira script.
 */
function redirecionarJs($url, $alerta = null)
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    echo '<script>';
    if ($alerta !== null) echo 'alert(' . json_encode($alerta, $flags) . ');';
    echo 'window.location.href=' . json_encode($url, $flags) . ';</script>';
    exit;
}

/** Ícone do "olhinho" (botão de mostrar/ocultar senha). */
function iconeOlhoSvg()
{
    return '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye text-dark" viewBox="0 0 16 16">'
         . '<path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>'
         . '<path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>'
         . '</svg>';
}