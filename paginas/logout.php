<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

foreach (['usuario_id', 'usuario_nome', 'usuario_email', 'usuario_whatsapp',
          'cliente_id', 'cliente_nome', 'cliente_email'] as $chave) {
    unset($_SESSION[$chave]);
}

$destino = (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'logout.php') ? '../index.php' : 'index.php';

if (!headers_sent()) {
    header('Location: ' . $destino);
    exit;
}
echo '<script>window.location.href=' . json_encode($destino) . ';</script>';
exit;