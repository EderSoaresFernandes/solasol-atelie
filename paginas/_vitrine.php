<?php
require_once __DIR__ . '/../funcoes_catalogo.php';

if (!isset($categoria, $titulo, $subtitulo)) {
    echo "<div class='container my-5'><div class='alert alert-warning text-center'>Página não encontrada!</div></div>";
    return;
}

$pagina_atual = is_string($_GET['pagina'] ?? null) ? $_GET['pagina'] : 'home';
$faixa_sel = is_string($_GET['faixa'] ?? null) && isset(FAIXAS_PRECO[$_GET['faixa']]) ? $_GET['faixa'] : '';
$ordem_sel = is_string($_GET['ordem'] ?? null) && isset(ORDENACOES_CATALOGO[$_GET['ordem']]) ? $_GET['ordem'] : 'recentes';

$catalogo  = carregarCatalogo($conn, $categoria);
$problemas = validarCatalogo($catalogo);
$contagem  = contarPorFaixa($catalogo, FAIXAS_PRECO);

if (!empty($catalogo) && empty($problemas)) {
    $exibidos = $catalogo;
    if ($faixa_sel !== '') {
        [, $min, $max] = FAIXAS_PRECO[$faixa_sel];
        $exibidos = filtrarPorFaixaDePreco($catalogo, $min, $max);
    }
    $exibidos = ordenarCatalogo($exibidos, $ordem_sel);
} else {
    $exibidos = [];
}

$linkVitrine = fn(string $faixa, string $ordem) => 'index.php?' . http_build_query(array_filter([
    'pagina' => $pagina_atual,
    'faixa'  => $faixa,
    'ordem'  => $ordem !== 'recentes' ? $ordem : '',
]));
?>

<div class="container-fluid px-4 my-4">
    <div class="text-center mb-4">
        <h2 class="text-uppercase fw-light tracking-wide"><?= htmlspecialchars($titulo); ?></h2>
        <p class="text-muted small"><?= htmlspecialchars($subtitulo); ?></p>
    </div>

    <?php if (!empty($catalogo)): ?>
        <?php
        $qtd_ativos = ($faixa_sel !== '' ? 1 : 0) + ($ordem_sel !== 'recentes' ? 1 : 0);
        $qtd_exibidos = count($exibidos);
        ?>
        <div class="vitrine-barra d-flex align-items-center justify-content-between gap-2 mb-3 mb-md-4">
            <span class="vitrine-total"><?= $qtd_exibidos; ?> <?= $qtd_exibidos === 1 ? 'produto' : 'produtos'; ?></span>

            <div class="d-flex align-items-center gap-2">
                <div class="vitrine-chips d-none d-md-flex align-items-center gap-2">
                    <?php if ($faixa_sel !== ''): ?>
                        <a href="<?= htmlspecialchars($linkVitrine('', $ordem_sel)); ?>" class="vitrine-chip" title="Remover filtro de preço">
                            <?= htmlspecialchars(FAIXAS_PRECO[$faixa_sel][0]); ?> <span aria-hidden="true">&times;</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($ordem_sel !== 'recentes'): ?>
                        <a href="<?= htmlspecialchars($linkVitrine($faixa_sel, 'recentes')); ?>" class="vitrine-chip" title="Remover ordenação">
                            <?= htmlspecialchars(ORDENACOES_CATALOGO[$ordem_sel]); ?> <span aria-hidden="true">&times;</span>
                        </a>
                    <?php endif; ?>
                </div>

                <button type="button" class="btn btn-filtros" data-bs-toggle="offcanvas" data-bs-target="#painelFiltros" aria-controls="painelFiltros">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path fill-rule="evenodd" d="M11.5 2a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3M9.05 3a2.5 2.5 0 0 1 4.9 0H16v1h-2.05a2.5 2.5 0 0 1-4.9 0H0V3zM4.5 7a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3M2.05 8a2.5 2.5 0 0 1 4.9 0H16v1H6.95a2.5 2.5 0 0 1-4.9 0H0V8zm9.45 4a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3m-2.45 1a2.5 2.5 0 0 1 4.9 0H16v1h-2.05a2.5 2.5 0 0 1-4.9 0H0v-1z"/>
                    </svg>
                    Filtros
                    <?php if ($qtd_ativos > 0): ?><span class="filtros-contador"><?= $qtd_ativos; ?></span><?php endif; ?>
                </button>
            </div>
        </div>

        <div class="offcanvas offcanvas-end painel-filtros" tabindex="-1" id="painelFiltros" aria-labelledby="painelFiltrosTitulo">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title text-uppercase" id="painelFiltrosTitulo">Filtros</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
            </div>

            <form method="GET" action="index.php" class="d-flex flex-column flex-grow-1 overflow-hidden">
                <input type="hidden" name="pagina" value="<?= htmlspecialchars($pagina_atual); ?>">

                <div class="offcanvas-body">
                    <fieldset class="mb-4">
                        <legend class="painel-filtros-titulo">Ordenar por</legend>
                        <?php foreach (ORDENACOES_CATALOGO as $chave => $rotulo): ?>
                            <label class="opcao-filtro">
                                <input type="radio" name="ordem" value="<?= $chave; ?>" <?= $ordem_sel === $chave ? 'checked' : ''; ?>>
                                <span><?= htmlspecialchars($rotulo); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>

                    <fieldset>
                        <legend class="painel-filtros-titulo">Faixa de preço</legend>
                        <label class="opcao-filtro">
                            <input type="radio" name="faixa" value="" <?= $faixa_sel === '' ? 'checked' : ''; ?>>
                            <span>Todos os preços</span>
                            <small><?= count($catalogo); ?></small>
                        </label>
                        <?php foreach (FAIXAS_PRECO as $chave => $faixa): ?>
                            <label class="opcao-filtro <?= $contagem[$chave] < 1 ? 'indisponivel' : ''; ?>">
                                <input type="radio" name="faixa" value="<?= $chave; ?>"
                                    <?= $faixa_sel === $chave ? 'checked' : ''; ?> <?= $contagem[$chave] < 1 ? 'disabled' : ''; ?>>
                                <span><?= htmlspecialchars($faixa[0]); ?></span>
                                <small><?= $contagem[$chave]; ?></small>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>

                <div class="painel-filtros-rodape d-flex gap-2">
                    <a href="<?= htmlspecialchars($linkVitrine('', 'recentes')); ?>" class="btn btn-outline-dark flex-fill">Limpar</a>
                    <button type="submit" class="btn btn-dark flex-fill">Aplicar</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="row g-2 g-md-4 grade-produtos vitrine-categoria">
        <?php if (!empty($exibidos)): ?>
            <?php foreach ($exibidos as $item): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="product-card h-100 position-relative">
                        <div class="product-image-wrapper<?= $item['foto2'] !== '' ? ' tem-foto-2' : ''; ?>">
                            <?= seloPromocaoHtml($item['precos']); ?>
                            <img src="<?= htmlspecialchars($item['foto']); ?>" alt="<?= htmlspecialchars($item['nome']); ?>" class="product-img">
                            <?php if ($item['foto2'] !== ''): ?>
                                <img src="<?= htmlspecialchars($item['foto2']); ?>" alt="" class="product-img product-img-2" loading="lazy" aria-hidden="true">
                            <?php endif; ?>
                        </div>

                        <div class="product-info text-start p-3">
                            <a href="index.php?pagina=detalhe&id=<?= $item['id']; ?>" class="text-decoration-none text-dark stretched-link">
                                <h6 class="product-title text-uppercase fw-bold mb-2"><?= htmlspecialchars($item['nome']); ?></h6>
                            </a>

                            <?= precosCartaoHtml($item['precos'], $item['parcelas_max']); ?>

                            <div class="product-sizes d-flex gap-2 mb-3">
                                <?php if (!empty($item['tamanhos'])): ?>
                                    <?php foreach ($item['tamanhos'] as $tam): ?>
                                        <span class="size-badge border px-2 py-1 rounded small"><?= htmlspecialchars($tam); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small">Esgotado</span>
                                <?php endif; ?>
                            </div>

                            <span class="d-block text-muted codigo-produto">Cód. <?= (int)$item['id']; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php elseif (empty($catalogo)): ?>
            <div class="col-12"><div class="alert alert-light text-center border py-5">Nenhum produto cadastrado nesta categoria!</div></div>
        <?php elseif (!empty($problemas)): ?>
            <div class="col-12"><div class="alert alert-warning text-center border py-5">Não foi possível exibir os produtos agora. Tente novamente mais tarde!</div></div>
        <?php else: ?>
            <div class="col-12"><div class="alert alert-light text-center border py-5">Nenhum produto nesta faixa de preço! <a href="<?= htmlspecialchars($linkVitrine('', $ordem_sel)); ?>" class="fw-semibold text-dark">Ver todos</a></div></div>
        <?php endif; ?>
    </div>
</div>
