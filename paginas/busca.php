<?php
require_once 'funcoes.php';

$termo = trim((string)($_GET['q'] ?? ''));
$termo = mb_substr($termo, 0, 80);
$resultados = [];

if ($termo !== '') {
    $like = '%' . addcslashes($termo, '%_\\') . '%';
    $codigo = ctype_digit($termo) ? (int)$termo : 0;

    $stmt = $conn->prepare("SELECT id_produto, nome, categoria, imagem_url, preco_original, preco_pix, parcelas_max
                              FROM produto
                             WHERE nome LIKE ? OR descricao LIKE ? OR id_produto = ?
                             ORDER BY nome
                             LIMIT 60");
    $stmt->bind_param("ssi", $like, $like, $codigo);
    $stmt->execute();
    $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<div class="container-fluid px-4 my-4">
    <div class="text-center mb-5">
        <h2 class="text-uppercase fw-light tracking-wide">Busca</h2>
        <?php if ($termo !== ''): ?>
            <p class="text-muted small">
                <?= count($resultados); ?> resultado(s) para "<strong><?= htmlspecialchars($termo); ?></strong>"
            </p>
        <?php endif; ?>
    </div>

    <form action="index.php" method="GET" class="d-flex mx-auto mb-5" style="max-width: 480px;">
        <input type="hidden" name="pagina" value="busca">
        <input type="text" name="q" class="form-control me-2" placeholder="Digite o que procura..." required
               maxlength="80" value="<?= htmlspecialchars($termo); ?>">
        <button type="submit" class="btn btn-dark px-4">Buscar</button>
    </form>

    <div class="row g-2 g-md-4 grade-produtos">
        <?php if ($termo === ''): ?>
            <div class="col-12"><div class="alert alert-light text-center border py-5">Digite o nome ou o código de um produto!</div></div>
        <?php elseif (!$resultados): ?>
            <div class="col-12"><div class="alert alert-light text-center border py-5">Nenhum produto encontrado!</div></div>
        <?php else: ?>
            <?php foreach ($resultados as $item):
                $idProduto = (int)$item['id_produto'];
                $foto = !empty($item['imagem_url']) ? $item['imagem_url'] : 'imagens/logoSolasolPreto.jpeg';
            ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="product-card h-100 position-relative">
                        <?php $precos = precosProduto($conn, $item);?>
                        <?php $foto2 = segundaFotoProduto($conn, $idProduto, $item['imagem_url'] ?? ''); ?>
                        <div class="product-image-wrapper<?= $foto2 !== '' ? ' tem-foto-2' : ''; ?>">
                            <?= seloPromocaoHtml($precos); ?>
                            <img src="<?= htmlspecialchars($foto); ?>" alt="<?= htmlspecialchars($item['nome']); ?>" class="product-img">
                            <?php if ($foto2 !== ''): ?>
                                <img src="<?= htmlspecialchars($foto2); ?>" alt="" class="product-img product-img-2" loading="lazy" aria-hidden="true">
                            <?php endif; ?>
                        </div>
                        <div class="product-info text-start p-3">
                            <a href="index.php?pagina=detalhe&id=<?= $idProduto; ?>" class="text-decoration-none text-dark stretched-link">
                                <h6 class="product-title text-uppercase fw-bold mb-2"><?= htmlspecialchars($item['nome']); ?></h6>
                            </a>
                            <?= precosCartaoHtml($precos, $item['parcelas_max']); ?>
                            <span class="d-block text-muted codigo-produto">Cód. <?= $idProduto; ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
