<?php
require_once __DIR__ . '/seguranca.php';
iniciar_sessao_segura();
enviar_headers_seguranca();

$tempo_max_ocioso = 30 * 60;
$logado = !empty($_SESSION['admin_logado'])
    && (time() - (int)($_SESSION['ultima_atividade'] ?? 0)) <= $tempo_max_ocioso;

if (!$logado) {
    $expirou = !empty($_SESSION['admin_logado']);
    encerrar_sessao();
    header('Location: admin_login.php' . ($expirou ? '?expirou=1' : ''));
    exit;
}
$_SESSION['ultima_atividade'] = time();

define('ADMIN_OK', true);

set_time_limit(300);
$acesso_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
ini_set('display_errors', $acesso_local ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Todo POST do painel precisa trazer o token CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
}

require_once 'conexao.php';
require_once 'funcoes.php';
require_once __DIR__ . '/funcoes_admin.php';

garantirTabelaUsuariosAdmin($conn);
$ADMIN_ATUAL = buscarAdminPorId($conn, (int)($_SESSION['admin_id'] ?? 0));
if (!$ADMIN_ATUAL || empty($ADMIN_ATUAL['ativo'])
    || (int)$ADMIN_ATUAL['sessao_versao'] !== (int)($_SESSION['admin_sessao_versao'] ?? -1)) {
    encerrar_sessao();
    header('Location: admin_login.php?expirou=1');
    exit;
}

$mensagem = "";

$abas_validas = ['produtos', 'frete', 'duvidas', 'pedidos', 'videos', 'lista_produtos', 'editar_produto', 'cupons', 'promocoes', 'usuarios'];
$aba = $_GET['aba'] ?? '';
if (!is_string($aba) || !in_array($aba, $abas_validas, true)) {
    $aba = '';
}

$sem_permissao = ($aba !== '' && !adminPodeAba($ADMIN_ATUAL, $aba));
if ($aba === '' || $sem_permissao) {
    $liberadas = permissoesDoAdmin($ADMIN_ATUAL);
    $aba = $liberadas[0] ?? 'usuarios';
}
$pode = fn(string $a) => adminPodeAba($ADMIN_ATUAL, $a);

$res_pendentes = $conn->query("SELECT COUNT(*) AS total FROM produto_duvidas WHERE resposta IS NULL OR resposta = ''");
$total_pendentes = $res_pendentes ? intval($res_pendentes->fetch_assoc()['total']) : 0;

$res_p_pend = $conn->query("SELECT COUNT(*) AS total FROM pedidos WHERE status_pedido = 'Pendente'");
$total_pedidos_pendentes = $res_p_pend ? intval($res_p_pend->fetch_assoc()['total']) : 0;

$res_nr = $conn->query("SELECT COUNT(*) AS total FROM pedidos WHERE status_entrega = 'Não recebido'");
$total_nao_recebidos = $res_nr ? intval($res_nr->fetch_assoc()['total']) : 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Painel Administrativo - SolaSol Ateliê</title>
    <link rel="icon" type="image/jpeg" href="/solasolAtelie/imagens/iconeSolasol.jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/solasolAtelie/css/style.css?v=<?= @filemtime(__DIR__ . '/css/style.css'); ?>">
</head>
<body class="bg-light admin-body">
    <div class="container mt-2 mb-3 mb-lg-5 admin-container">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="card shadow border-0 p-3 p-lg-4 mb-4 mb-lg-5">

                    <?php $avisos_menu = $total_pendentes + $total_pedidos_pendentes + $total_nao_recebidos; ?>
                    <div class="admin-sticky-header admin-header mb-4 pb-3 border-bottom py-3">
                        <h5 class="admin-header-titulo fw-bold text-dark">Painel</h5>

                        <div class="admin-header-logo">
                            <a href="index.php">
                                <img src="/solasolAtelie/imagens/logoSolasolPreto.jpeg" alt="SolaSol Ateliê" class="admin-logo-img">
                            </a>
                        </div>

                        <button type="button" class="btn btn-link text-dark p-1 admin-menu-btn d-lg-none position-relative"
                                data-bs-toggle="offcanvas" data-bs-target="#adminMenu" aria-controls="adminMenu" aria-label="Abrir menu do painel">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                            </svg>
                            <?php if ($avisos_menu > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $avisos_menu; ?></span>
                            <?php endif; ?>
                        </button>

                        <div class="offcanvas-lg offcanvas-end admin-menu" tabindex="-1" id="adminMenu" aria-labelledby="adminMenuTitulo">
                            <div class="offcanvas-header border-bottom d-lg-none">
                                <h5 class="offcanvas-title fw-bold" id="adminMenuTitulo">Menu do Painel</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adminMenu" aria-label="Fechar menu"></button>
                            </div>
                            <div class="offcanvas-body">
                        <div class="admin-header-botoes">
                            <?php if ($pode('produtos')): ?>
                            <a href="admin.php?aba=produtos" class="btn btn-outline-custom btn-sm <?= ($aba === 'produtos') ? 'active' : ''; ?>">+ Cadastrar</a>
                            <?php endif; ?>
                            <?php if ($pode('frete')): ?>
                            <a href="admin.php?aba=frete" class="btn btn-outline-custom btn-sm <?= ($aba === 'frete') ? 'active' : ''; ?>">Frete</a>
                            <?php endif; ?>
                            <?php if ($pode('cupons')): ?>
                            <a href="admin.php?aba=cupons" class="btn btn-outline-custom btn-sm <?= ($aba === 'cupons') ? 'active' : ''; ?>">🏷️ Cupons</a>
                            <?php endif; ?>
                            <?php if ($pode('promocoes')): ?>
                            <a href="admin.php?aba=promocoes" class="btn btn-outline-custom btn-sm <?= ($aba === 'promocoes') ? 'active' : ''; ?>">🔥 Promoções</a>
                            <?php endif; ?>

                            <?php if ($pode('duvidas')): ?>
                            <a href="admin.php?aba=duvidas" class="btn btn-outline-custom btn-sm position-relative <?= ($aba === 'duvidas') ? 'active' : ''; ?>">
                                💬 Dúvidas
                                <?php if ($total_pendentes > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $total_pendentes; ?></span>
                                <?php endif; ?>
                            </a>
                            <?php endif; ?>

                            <?php if ($pode('pedidos')): ?>
                            <a href="admin.php?aba=pedidos" class="btn btn-outline-custom btn-sm position-relative <?= ($aba === 'pedidos') ? 'active' : ''; ?>">
                                🛍️ Pedidos
                                <?php if ($total_pedidos_pendentes > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $total_pedidos_pendentes; ?></span>
                                <?php endif; ?>
                                <?php if ($total_nao_recebidos > 0): ?>
                                    <span class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-warning text-dark" title="Clientes que informaram NÃO RECEBIDO">⚠️<?= $total_nao_recebidos; ?></span>
                                <?php endif; ?>
                            </a>
                            <?php endif; ?>

                            <?php if ($pode('videos')): ?>
                            <a href="admin.php?aba=videos" class="btn btn-outline-custom btn-sm <?= ($aba === 'videos') ? 'active' : ''; ?>">🎬 Vídeos Home</a>
                            <?php endif; ?>
                            <?php if ($pode('lista_produtos')): ?>
                            <a href="admin.php?aba=lista_produtos" class="btn btn-outline-custom btn-sm <?= ($aba === 'lista_produtos') ? 'active' : ''; ?>">Gerenciar</a>
                            <?php endif; ?>
                            <a href="admin.php?aba=usuarios" class="btn btn-outline-custom btn-sm <?= ($aba === 'usuarios') ? 'active' : ''; ?>">👤 Usuários</a>
                            <a href="index.php" class="btn btn-outline-custom btn-sm">Ver Loja</a>
                            <form method="POST" action="admin_logout.php">
                                <?= campo_csrf(); ?>
                                <button class="btn btn-outline-danger btn-sm">Sair</button>
                            </form>
                        </div>
                            </div>
                        </div>
                    </div>

                    <div class="small text-muted text-end mb-3">
                        Conectado como <strong><?= htmlspecialchars($ADMIN_ATUAL['nome']); ?></strong> (<?= htmlspecialchars($ADMIN_ATUAL['usuario']); ?>)<?= !empty($ADMIN_ATUAL['principal']) ? ' · Administrador principal' : ''; ?>
                    </div>
                    <?php if ($sem_permissao): ?>
                        <div class="alert alert-warning">Você não tem permissão para acessar aquela área do painel. Fale com o administrador.</div>
                    <?php endif; ?>

                    <?php
                    $pagina_arquivo = __DIR__ . "/paginas-admin/{$aba}.php";
                    if (is_file($pagina_arquivo)) {
                        include_once $pagina_arquivo;
                    } else {
                        echo "<div class='alert alert-danger'>Página administrativa não encontrada.</div>";
                    }
                    ?>

                </div>
            </div>
        </div>
    </div>

    <footer class="admin-footer border-top bg-white text-center text-muted small py-3">
        &copy; 2026 SolaSol Ateliê. Todos os direitos reservados.<br>Desenvolvido por Eder Fernandes
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('table.tabela-cartoes-admin').forEach(function (tabela) {
        const titulos = Array.from(tabela.querySelectorAll('thead th')).map(th => th.textContent.trim());
        tabela.querySelectorAll('tbody tr').forEach(function (tr) {
            let col = 0;
            Array.from(tr.children).forEach(function (td) {
                const span = parseInt(td.getAttribute('colspan')) || 1;
                if (span === 1 && titulos[col]) {
                    td.setAttribute('data-label', titulos[col]);
                    if (td.querySelector('form, ul, textarea, select, input') || td.textContent.replace(/\s+/g, ' ').trim().length > 45) {
                        td.classList.add('celula-empilhada');
                    }
                }
                else td.classList.add('celula-larga');
                col += span;
            });
        });
    });
    </script>
</body>
</html>