<?php
// admin_logout.php — só encerra a sessão via POST com token CSRF (botão "Sair" do painel)
require_once __DIR__ . '/seguranca.php';
iniciar_sessao_segura();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_confere()) {
    encerrar_sessao();
    header('Location: admin_login.php');
    exit;
}
header('Location: admin.php');
exit;
