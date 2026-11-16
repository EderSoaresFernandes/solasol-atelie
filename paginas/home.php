<div id="bannerPrincipal" class="carousel slide mb-5" data-bs-ride="carousel" data-bs-interval="5000">

    <div class="carousel-indicators">
        <button type="button" data-bs-target="#bannerPrincipal" data-bs-slide-to="0" class="active" aria-current="true"
            aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#bannerPrincipal" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#bannerPrincipal" data-bs-slide-to="2" aria-label="Slide 3"></button>
        <button type="button" data-bs-target="#bannerPrincipal" data-bs-slide-to="3" aria-label="Slide 4"></button>
    </div>

    <div class="carousel-inner">
        <div class="carousel-item active">
            <a href="imagens/bannerVerao27Collection.jpeg" data-fslightbox="galeria-banners">
                <img src="imagens/bannerVerao27Collection.jpeg" class="d-block w-100" alt="Coleção Verão/27">
            </a>
        </div>
        <div class="carousel-item">
            <a href="imagens/bannerFemininoVerao27.jpeg" data-fslightbox="galeria-banners">
                <img src="imagens/bannerFemininoVerao27.jpeg" class="d-block w-100" alt="Coleção Feminina Verão/27">
            </a>
        </div>
        <div class="carousel-item">
            <a href="imagens/bannerCasualmen.jpeg" data-fslightbox="galeria-banners">
                <img src="imagens/bannerCasualmen.jpeg" class="d-block w-100" alt="Coleção CasualMen">
            </a>
        </div>
        <div class="carousel-item">
            <a href="imagens/bannerCalcadosFemininos.jpeg" data-fslightbox="galeria-banners">
                <img src="imagens/bannerCalcadosFemininos.jpeg" class="d-block w-100" alt="Destaque Calçados Femininos">
            </a>
        </div>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#bannerPrincipal" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Anterior</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#bannerPrincipal" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Próximo</span>
    </button>
</div>

<?php
if (file_exists('conexao.php')) {
    require_once 'conexao.php';
} else {
    require_once '../conexao.php';
}

$result_videos = $conn->query("SELECT * FROM destaques_videos ORDER BY id");
?>

<section class="destaques-videos-quadro my-5">
    <div class="text-center mb-4">
        <h2 class="text-uppercase fw-bold tracking-wide">Destaques da Coleção</h2>
        <p class="text-muted small mb-0">SolaSol Ateliê | Seu estilo, sua essência!</p>
    </div>

    <div class="destaques-videos-grid">
        <?php if ($result_videos && $result_videos->num_rows > 0): ?>
            <?php while ($vid = $result_videos->fetch_assoc()):
                $caminho_bruto = !empty($vid['video_url']) ? trim($vid['video_url']) : '';
                $legenda = $vid['titulo_legenda'] ?? 'Coleção';

                $link_categoria = "index.php?pagina=home";
                if (stripos($vid['categoria'], 'Feminina') !== false) {
                    $link_categoria = "index.php?pagina=feminino";
                } elseif (stripos($vid['categoria'], 'Masculina') !== false) {
                    $link_categoria = "index.php?pagina=masculino";
                } elseif (stripos($vid['categoria'], 'Calçados') !== false) {
                    $link_categoria = "index.php?pagina=calcados";
                }

                $nome_arquivo = basename($caminho_bruto);
                $tem_video_valido = !empty($caminho_bruto) && ($nome_arquivo !== '') && (stripos($caminho_bruto, '.mp4') !== false);

                $url_video_final = "uploads/videos/" . $nome_arquivo;
                ?>
                <div class="destaque-video-item">
                    <div class="card-video-wrapper video-destaque-card">

                        <?php if ($tem_video_valido): ?>
                            <video src="<?= htmlspecialchars($url_video_final); ?>" autoplay muted loop playsinline preload="auto"
                                class="video-destaque"></video>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-white small bg-dark position-absolute top-0 start-0 w-100 px-3 text-center"
                                style="z-index: 1;">
                                Vídeo pendente no Painel Admin
                            </div>
                        <?php endif; ?>

                        <div class="position-absolute top-0 start-0 w-100 h-100"
                            style="background: rgba(0,0,0,0.35); pointer-events: none; z-index: 2;"></div>

                        <a href="<?= $link_categoria; ?>"
                            class="text-decoration-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-end"
                            style="z-index: 3;">
                            <div class="video-overlay-content p-4">
                                <h3 class="video-title">
                                    <?= htmlspecialchars($legenda); ?>
                                </h3>
                                <p class="video-subtitle">
                                    Confira as Novidades!
                                </p>
                            </div>
                        </a>

                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="text-center text-muted" style="grid-column: 1 / -1;">Nenhum vídeo em destaque cadastrado no momento!
            </div>
        <?php endif; ?>
    </div>
</section>
<section class="nossa-historia">
    <h2 class="nossa-historia-titulo">Seja Bem-Vindo(a) ao nosso Ateliê!</h2>

    <p>
        Estamos muito felizes em ter você por aqui! Com mais de duas décadas de paixão pela moda,
        o SolaSol Ateliê nasceu para celebrar a beleza do essencial: o estilo básico com qualidade,
        feito para acompanhar o seu ritmo com conforto, elegância e versatilidade.
    </p>

    <p>
        Acreditamos que vestir-se bem não precisa ser complicado. Por isso, nossa curadoria é
        selecionada com amor 💛, escolhendo peça por peça junto aos melhores fabricantes para
        entregar a você aquele caimento perfeito e a durabilidade que o seu dia a dia merece.
    </p>

    <p>
        Sinta-se em casa no nosso espaço — seja de portas abertas para te receber ou a um clique
        de distância. Escolha suas peças favoritas e compartilhe o seu estilo com a
        <span class="nossa-historia-hashtag">#meulookSolaSol</span>!
    </p>
</section>

<script>
    window.addEventListener("load", function () {
        const videos = document.querySelectorAll("video.video-destaque");
        videos.forEach(function (video) {
            video.muted = true;
            video.play().catch(function (error) {
                console.log("Reprodução automática iniciada:", error);
            });
        });
    });
</script>