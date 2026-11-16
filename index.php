<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
require_once 'conexao.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <base href="<?= rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SolaSol Ateliê</title>
    <link rel="icon" type="image/jpeg" href="imagens/iconeSolasol.jpeg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=<?= (int)@filemtime(__DIR__ . '/css/style.css'); ?>">
</head>

<body>

    <?php
    $cliente_logado = isset($_SESSION['usuario_id']);
    $nome_cliente = $_SESSION['usuario_nome'] ?? 'Cliente';
    $pagina_menu = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 'home';
    $menu_ativo = fn(string $p) => $pagina_menu === $p ? ' ativo' : '';
    ?>

    <div class="header-fixed-wrapper">
        <header class="bg-white">
            <div class="top-bar bg-dark text-white py-2 text-center small fw-semibold overflow-hidden">
                <div id="topbarCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <span>Frete GRÁTIS nas compras acima de R$ 499!</span>
                        </div>
                        <div class="carousel-item">
                            <span>Parcelamento em até 10X sem juros!</span>
                        </div>
                        <div class="carousel-item">
                            <span>Cupom de 10% de desconto na primeira compra com o cupom:
                                <strong>#atelie</strong></span>
                      </div>
                    </div>
                </div>
            </div>

            <div class="container-fluid px-2 px-lg-4 py-2 py-lg-3 header-linha">
                <div class="row align-items-center g-0">

                    <div class="col-3 col-lg-4 text-start ps-lg-4">
                           <button type="button" class="btn btn-link text-dark p-2 header-icon-btn so-mobile" data-bs-toggle="offcanvas"
                                data-bs-target="#menuMobile" aria-controls="menuMobile" aria-label="Abrir menu" title="Menu">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5"/>
                            </svg>
                        </button>
                        <a href="index.php?pagina=loja"
                            class="text-decoration-none text-dark small fw-semibold d-none d-lg-inline-flex align-items-center gap-2 px-2 py-1 atelie-btn-link">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                                class="bi bi-geo-alt" viewBox="0 0 16 16">
                                <path
                                    d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z" />
                                <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                            </svg>
                            <span>Nosso Ateliê</span>
                        </a>
                    </div>

                     <div class="col-6 col-lg-4 text-center">
                        <a href="index.php?pagina=home" class="text-decoration-none logo-topo-link" title="Ir para a página inicial">
                            <img src="imagens/logoSolasolTopo.jpeg" alt="SolaSol Ateliê" class="logo-topo">
                        </a>
                    </div>
                    <div class="col-3 col-lg-4 text-end d-flex justify-content-end align-items-center gap-1 gap-lg-4 pe-1 pe-lg-4">

                        <button class="btn btn-link text-dark p-1 text-decoration-none header-icon-btn" type="button"
                            data-bs-toggle="collapse" data-bs-target="#boxBusca" title="Pesquisar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor"
                                class="bi bi-search" viewBox="0 0 16 16">
                                <path
                                    d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z" />
                            </svg>
                        </button>

                        <a href="index.php?pagina=favoritos"
                            class="text-dark position-relative text-decoration-none header-icon-btn so-desktop"
                            title="Lista de Desejos">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor"
                                class="bi bi-heart" viewBox="0 0 16 16">
                                <path
                                    d="m8 2.748-.717-.737C5.6.281 2.514.878 1.4 3.053c-.523 1.023-.641 2.5.314 4.385.92 1.815 2.834 3.989 6.286 6.357 3.452-2.368 5.365-4.542 6.286-6.357.955-1.886.838-3.362.314-4.385C13.486.878 10.4.28 8.717 2.01L8 2.748zM8 15C-7.333 4.868 3.27-3.04 7.8 1.043c.225.2.392.398.57.57.178-.172.345-.37.57-.57C12.73-3.04 23.333 4.868 8 15z" />
                            </svg>
                            <span id="badge-favoritos-header"
                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                style="font-size: 0.65rem; display: none;">0</span>
                        </a>

                        <div class="dropdown so-desktop">
                            <a href="#"
                                class="text-dark p-1 text-decoration-none dropdown-toggle no-caret header-icon-btn"
                                id="dropdownUser" data-bs-toggle="dropdown" title="Minha Conta">
                                <svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" fill="currentColor"
                                    class="bi bi-person" viewBox="0 0 16 16">
                                    <path
                                        d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z" />
                                </svg>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-3" style="min-width: 220px;"
                                aria-labelledby="dropdownUser">
                                <?php if ($cliente_logado): ?>
                                    <li class="pb-2 mb-2 border-bottom">
                                        <span class="d-block fw-bold text-dark" style="font-size: 0.95rem;">Bem-vindo(a),
                                            <?= htmlspecialchars($nome_cliente); ?>!</span>
                                    </li>
                                    <li><a class="dropdown-item small text-uppercase fw-semibold py-2"
                                            href="index.php?pagina=minha-conta">Minha Conta</a></li>
                                    <li><a class="dropdown-item small text-uppercase fw-semibold py-2"
                                            href="index.php?pagina=minhas-compras">Minhas Compras</a></li>
                                    <li><a class="dropdown-item small text-uppercase fw-semibold py-2"
                                            href="index.php?pagina=favoritos">Lista de Desejos</a></li>
                                    <li><a class="dropdown-item small text-uppercase fw-semibold py-2"
                                            href="index.php?pagina=checkout">Finalizar Compra</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item small text-uppercase fw-semibold py-2 text-danger"
                                            href="paginas/logout.php">Sair</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item small fw-semibold py-2" href="index.php?pagina=login">Entrar
                                            na Conta</a></li>
                                    <li><a class="dropdown-item small py-2" href="index.php?pagina=login">Criar Nova
                                            Conta</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <a href="index.php?pagina=sacola"
                            class="text-dark p-1 text-decoration-none position-relative header-icon-btn"
                            title="Sacola de Compras">
                            <svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" fill="currentColor"
                                class="bi bi-bag" viewBox="0 0 16 16">
                                <path
                                    d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1zm35 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5zM2 5h12v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V5z" />
                            </svg>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-dark"
                                style="font-size: 0.65rem;">0</span>
                        </a>

                    </div>
                </div>
            </div>

            <div class="collapse my-2" id="boxBusca">
                <form action="index.php" method="GET" class="d-flex max-w-md mx-auto px-4 pb-2">
                    <input type="hidden" name="pagina" value="busca">
                    <input type="text" name="q" class="form-control form-control-sm me-2"
                        placeholder="Digite o que procura..." required maxlength="80">
                    <button type="submit" class="btn btn-dark btn-sm px-3">Buscar</button>
                </form>
            </div>
        </header>
    </div>

        <nav class="navbar navbar-expand-lg navbar-light bg-white main-nav-shadow d-none d-lg-flex">
            <div class="container justify-content-center">
                <div class="collapse navbar-collapse justify-content-center" id="menuPrincipal">
                    <ul class="navbar-nav gap-3">
                        <li class="nav-item"><a class="nav-link text-uppercase fw-bold text-dark"
                                href="index.php?pagina=home">Home</a></li>
                        <li class="nav-item"><a class="nav-link text-uppercase fw-bold text-dark"
                                href="index.php?pagina=feminino">Feminino</a></li>
                        <li class="nav-item"><a class="nav-link text-uppercase fw-bold text-dark"
                                href="index.php?pagina=masculino">Masculino</a></li>
                        <li class="nav-item"><a class="nav-link text-uppercase fw-bold text-dark"
                                href="index.php?pagina=calcados">Calçados</a></li>
                        <li class="nav-item"><a class="nav-link text-uppercase fw-bold text-dark"
                                href="index.php?pagina=contato">Contato</a></li>
                    </ul>
                </div>
            </div>
        </nav>

    <div class="offcanvas offcanvas-start menu-mobile" tabindex="-1" id="menuMobile" aria-labelledby="menuMobileTitulo">
        <div class="offcanvas-header border-bottom">
            <img src="imagens/logoSolasolTopo.jpeg" alt="SolaSol Ateliê" class="menu-mobile-logo" id="menuMobileTitulo">
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar menu"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <?php if ($cliente_logado): ?>
                <div class="menu-mobile-ola px-4 py-3">Olá, <strong><?= htmlspecialchars(strtok($nome_cliente, ' ') ?: $nome_cliente); ?></strong>!</div>
            <?php endif; ?>

            <nav class="menu-mobile-links">
                <a href="index.php?pagina=home" class="<?= $menu_ativo('home'); ?>">Home</a>
                <a href="index.php?pagina=feminino" class="<?= $menu_ativo('feminino'); ?>">Feminino</a>
                <a href="index.php?pagina=masculino" class="<?= $menu_ativo('masculino'); ?>">Masculino</a>
                <a href="index.php?pagina=calcados" class="<?= $menu_ativo('calcados'); ?>">Calçados</a>
                <a href="index.php?pagina=contato" class="<?= $menu_ativo('contato'); ?>">Contato</a>
            </nav>

            <div class="menu-mobile-secao">
                <a href="index.php?pagina=favoritos">♡ Lista de Desejos <span class="badge rounded-pill bg-danger ms-1" id="badge-favoritos-menu" style="display:none;">0</span></a>
                <a href="index.php?pagina=loja">📍 Nosso Ateliê</a>
                <?php if ($cliente_logado): ?>
                    <a href="index.php?pagina=minha-conta">👤 Minha Conta</a>
                    <a href="index.php?pagina=minhas-compras">📦 Minhas Compras</a>
                <?php endif; ?>
            </div>

            <div class="mt-auto p-4 border-top">
                <?php if ($cliente_logado): ?>
                    <a href="paginas/logout.php" class="btn btn-outline-danger w-100 text-uppercase fw-semibold">Sair da conta</a>
                <?php else: ?>
                    <a href="index.php?pagina=login" class="btn btn-dark w-100 text-uppercase fw-semibold mb-2">Entrar na conta</a>
                    <a href="index.php?pagina=login" class="btn btn-outline-dark w-100 text-uppercase fw-semibold">Criar conta</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <main>
        <?php
        $pagina = $_GET['pagina'] ?? 'home';
        if (!is_string($pagina) || !preg_match('/^[a-z0-9_-]+$/i', $pagina)) {
            $pagina = 'home';
        }
        $arquivo = __DIR__ . "/paginas/{$pagina}.php";

        if (is_file($arquivo)) {
            include $arquivo;
        } else {
            echo "<div class='container my-5'><div class='alert alert-warning text-center'>Página não encontrada!</div></div>";
        }
        ?>
    </main>

    <footer class="site-footer bg-light border-top mt-5">
        <div class="container-fluid px-4 py-4">
            <div class="footer-grid">

                <div class="footer-esquerda">

                    <div class="footer-redes">
                        <p class="footer-redes-label text-muted fw-semibold text-uppercase">Siga nossas redes:</p>
                        <div class="footer-redes-icones">
                            <a href="https://www.instagram.com/loja_solasol/" target="_blank"
                                class="social-icon-btn instagram" title="Instagram">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                                    class="bi bi-instagram" viewBox="0 0 16 16">
                                    <path
                                        d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.917 3.917 0 0 0-1.417.923A3.927 3.927 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.916 3.916 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.198-.509.333-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.926 3.926 0 0 0-.923-1.417A3.911 3.911 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0h.003zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599.28.28.453.546.598.92.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.47 2.47 0 0 1-.599.919c-.28.28-.546.453-.92.598-.282.11-.705.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.478 2.478 0 0 1-.92-.598 2.48 2.48 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233 0-2.136.008-2.388.046-3.231.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92.28-.28.546-.453.92-.598.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045v.002zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92zm-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217zm0 1.441a2.668 2.668 0 1 1 0 5.335 2.668 2.668 0 0 1 0-5.335z" />
                                </svg>
                            </a>

                            <a href="https://www.facebook.com/solasolroncador/" target="_blank"
                                class="social-icon-btn facebook" title="Facebook">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                                    class="bi bi-facebook" viewBox="0 0 16 16">
                                    <path
                                        d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951z" />
                                </svg>
                            </a>

                            <a href="https://www.tiktok.com/@lojasolasol" target="_blank" class="social-icon-btn tiktok"
                                title="TikTok">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                    viewBox="0 0 24 24">
                                    <path
                                        d="M19.589 6.686a4.793 4.793 0 0 1-3.77-4.242V2h-3.445v13.672a2.896 2.896 0 0 1-5.201 1.743l-.002-.001.002-.001a2.895 2.895 0 0 1 3.144-4.53v-3.455a6.345 6.345 0 0 0-5.462 6.275 6.353 6.353 0 0 0 6.353 6.353 6.354 6.354 0 0 0 6.353-6.353V8.892a8.196 8.196 0 0 0 4.877 1.554V6.995a4.803 4.803 0 0 1-1.168-.309z" />
                                </svg>
                            </a>
                        </div>
                    </div>

                    <div class="footer-ajuda-area">
                        <div class="footer-ajuda">
                            <h6 class="footer-titulo">Ajuda</h6>
                            <ul class="footer-lista">
                                <li>
                                    <a class="footer-link"
                                        href="<?= $cliente_logado ? 'index.php?pagina=minha-conta' : 'index.php?pagina=login'; ?>">Minha
                                        Conta</a>
                                </li>
                                <li>
                                    <a class="footer-link"
                                        href="<?= $cliente_logado ? 'index.php?pagina=minhas-compras' : 'index.php?pagina=login&redirect=minhas-compras'; ?>">Minhas
                                        Compras</a>
                                </li>
                                <li>
                                    <a class="footer-link" href="index.php?pagina=trocas-devolucoes">Troca e
                                        Devoluções</a>
                                </li>
                                <li>
                                    <a class="footer-link" href="index.php?pagina=formas-de-pagamento">Formas de
                                        Pagamento</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                </div>

                <div class="footer-copy">
                    <p class="mb-0 text-muted">&copy; 2026 SolaSol Ateliê. Todos os direitos reservados.<br>Desenvolvido
                        por Eder Fernandes</p>
                </div>

                <?php $site_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); ?>
                <div class="footer-selos">
                    <div class="selo-seguranca" title="Senhas criptografadas, dados do cartão não são armazenados e proteção contra acessos indevidos">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path fill="#1e7a46" d="M12 1 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                            <path d="M8 12.3l2.7 2.7L16.2 9.5" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div class="selo-textos">
                            <span class="selo-titulo">Site Protegido</span>
                            <span class="selo-texto">Seus dados estão seguros!</span>
                        </div>
                    </div>
                    <?php if ($site_https): ?>
                        <div class="selo-seguranca selo-ssl" title="A comunicação entre o seu navegador e a loja é criptografada (HTTPS)">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path fill="#1e7a46" d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3.1-9H8.9V6a3.1 3.1 0 0 1 6.2 0v2z"/>
                            </svg>
                            <div class="selo-textos">
                                <span class="selo-titulo">Conexão Segura</span>
                                <span class="selo-texto">Criptografia SSL!</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fslightbox/3.3.1/index.min.js"></script>
    <script>
    (function () {
        function ler(chave) {
            try { const v = JSON.parse(localStorage.getItem(chave)); return Array.isArray(v) ? v : []; }
            catch (e) { return []; }
        }
        function atualizarContadoresHeader() {
            const totalSacola = ler('solasol_sacola').reduce((s, it) => s + (parseInt(it.quantidade) || 1), 0);
            const bSacola = document.querySelector('a[href="index.php?pagina=sacola"] .badge');
            if (bSacola) bSacola.textContent = totalSacola;

            const totalFav = ler('solasol_favoritos').length;
            ['badge-favoritos-header', 'badge-favoritos-menu'].forEach(function (id) {
                const bFav = document.getElementById(id);
                if (bFav) {
                    bFav.textContent = totalFav;
                    bFav.style.display = totalFav > 0 ? 'inline-block' : 'none';
                }
            });
        }

        let compacto = false;
        function verificarRolagem() {
            const y = window.scrollY;
            if (!compacto && y > 120) { compacto = true; document.body.classList.add('header-compacto'); }
            else if (compacto && y < 10) { compacto = false; document.body.classList.remove('header-compacto'); }
        }
        window.addEventListener('scroll', verificarRolagem, { passive: true });
        document.addEventListener('DOMContentLoaded', verificarRolagem);

        const cabecalho = document.querySelector('.header-fixed-wrapper');
        function ajustarEspacoCabecalho() {
            if (!cabecalho) return;
            if (window.innerWidth >= 992) { document.body.style.removeProperty('--altura-cabecalho'); return; }
            if (document.body.classList.contains('header-compacto')) return;
            document.body.style.setProperty('--altura-cabecalho', cabecalho.offsetHeight + 'px');
        }
        ajustarEspacoCabecalho();
        window.addEventListener('resize', ajustarEspacoCabecalho);
        if (window.ResizeObserver && cabecalho) new ResizeObserver(ajustarEspacoCabecalho).observe(cabecalho);
        document.addEventListener('DOMContentLoaded', atualizarContadoresHeader);
        window.addEventListener('storage', atualizarContadoresHeader);
        document.addEventListener('click', () => setTimeout(atualizarContadoresHeader, 0));
    })();
    </script>

    <a href="https://wa.me/5544997020518?text=Olá!%20Vim%20pelo%20site%20e%20gostaria%20de%20mais%20informações."
        target="_blank" rel="noopener" class="whatsapp-float-btn" title="Fale conosco no WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16">
            <path
                d="M13.601 2.326A7.854 7.854 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.933 7.933 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.898 7.898 0 0 0 13.601 2.326zM7.994 14.521a6.573 6.573 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.558 6.558 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592zm3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.59-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.09-.088.197-.232.296-.347.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.323-.335-.445-.342-.116-.003-.249-.003-.382-.003a.747.747 0 0 0-.543.253c-.187.198-.716.7-.716 1.707 0 1.008.734 1.981.837 2.114.103.133 1.444 2.205 3.502 3.094.49.212.872.339 1.17.433.492.158.94.135 1.295-.082.395-.24 1.17-.779 1.334-1.532.163-.753.163-1.398.114-1.532-.049-.134-.182-.203-.379-.302z" />
        </svg>
    </a>
</body>

</html>