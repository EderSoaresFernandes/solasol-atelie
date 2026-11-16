<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_produto'])) {
    $id_excluir = intval($_POST['excluir_produto']);

    $base_fotos = realpath(__DIR__ . '/../imagens/produtos');
    $stmt_f = $conn->prepare("SELECT imagem_url FROM produto_fotos WHERE produto_id = ?");
    $stmt_f->bind_param("i", $id_excluir);
    $stmt_f->execute();
    $res_f = $stmt_f->get_result();
    while ($foto = $res_f->fetch_assoc()) {
        if (empty($foto['imagem_url']) || !$base_fotos) continue;
        $real = realpath(__DIR__ . '/../' . $foto['imagem_url']);
        if ($real && strpos($real, $base_fotos . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
            unlink($real);
        }
    }
    $stmt_f->close();

    foreach ([
        "DELETE FROM produto_fotos WHERE produto_id = ?",
        "DELETE FROM produto_cores_tamanhos WHERE produto_id = ?",
        "DELETE FROM produto_avaliacoes WHERE produto_id = ?",
        "DELETE FROM produto_duvidas WHERE produto_id = ?",
        "DELETE FROM produto WHERE id_produto = ?",
    ] as $sql_del) {
        $stmt_d = $conn->prepare($sql_del);
        $stmt_d->bind_param("i", $id_excluir);
        $stmt_d->execute();
        $stmt_d->close();
    }

    echo "<script>window.location.href='admin.php?aba=lista_produtos&sucesso=1';</script>";
    exit;
}

$cats_filtro = CATEGORIAS_PROMOCAO;
$cat_sel = is_string($_GET['cat'] ?? null) && isset($cats_filtro[$_GET['cat']]) ? $_GET['cat'] : '';

$contagem = ['' => 0];
$rc = $conn->query("SELECT categoria, COUNT(*) AS n FROM produto GROUP BY categoria");
while ($rc && ($l = $rc->fetch_assoc())) { $contagem[$l['categoria']] = (int)$l['n']; $contagem[''] += (int)$l['n']; }

if ($cat_sel !== '') {
    $st_l = $conn->prepare("SELECT * FROM produto WHERE categoria = ? ORDER BY id_produto DESC");
    $st_l->bind_param("s", $cat_sel);
    $st_l->execute();
    $resultado = $st_l->get_result();
} else {
    $resultado = $conn->query("SELECT * FROM produto ORDER BY id_produto DESC");
}
?>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">Produto excluído com sucesso!</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">📋 Lista de Produtos Cadastrados</h5>
    <a href="admin.php?aba=produtos" class="btn btn-dark btn-sm text-uppercase fw-bold d-none d-lg-inline-block">+ Novo Produto</a>
</div>
<hr class="mb-3">

<div class="d-flex flex-wrap gap-2 mb-4 filtro-categorias-admin">
    <a href="admin.php?aba=lista_produtos" class="btn btn-sm btn-outline-custom <?= $cat_sel === '' ? 'active' : ''; ?>">Todos (<?= $contagem['']; ?>)</a>
    <?php foreach ($cats_filtro as $valor => $rotulo): ?>
        <a href="admin.php?aba=lista_produtos&cat=<?= urlencode($valor); ?>"
           class="btn btn-sm btn-outline-custom <?= $cat_sel === $valor ? 'active' : ''; ?>"><?= htmlspecialchars($rotulo); ?> (<?= $contagem[$valor] ?? 0; ?>)</a>
    <?php endforeach; ?>
</div>

<div class="table-responsive">
    <table class="tabela-cartoes-admin table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Código</th>
                <th>Foto</th>
                <th>Nome do Produto</th>
                <th>Categoria</th>
                <th>Preço Original</th>
                <th>Preço Pix</th>
                <th>Estoque Total</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado && $resultado->num_rows > 0): ?>
                <?php while ($prod = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold"><?= (int)$prod['id_produto']; ?></td>
                        <td>
                            <img src="<?= htmlspecialchars($prod['imagem_url'] ?: '/solasolAtelie/imagens/logoSolasolPreto.jpeg'); ?>" alt="" class="admin-produto-img border rounded bg-white">
                        </td>
                        <td class="fw-semibold"><?= htmlspecialchars($prod['nome']); ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($prod['categoria']); ?></span></td>
                        <td><?= formatarPreco($prod['preco_original']); ?></td>
                        <td class="text-success fw-bold"><?= formatarPreco(round((float)$prod['preco_original'] * 0.95, 2)); ?></td>
                        <td><span class="badge bg-info text-dark"><?= intval($prod['estoque']); ?> un.</span></td>
                        <td class="text-end">
                            <a href="admin.php?aba=editar_produto&id=<?= (int)$prod['id_produto']; ?>" class="btn btn-outline-dark btn-sm">Alterar</a>
                            <a href="index.php?pagina=detalhe&id=<?= (int)$prod['id_produto']; ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">Ver</a>
                            <form method="POST" action="admin.php?aba=lista_produtos<?= $cat_sel !== '' ? '&cat=' . urlencode($cat_sel) : ''; ?>" class="d-inline"
                                  data-msg="<?= htmlspecialchars('Tem certeza que deseja excluir o produto ' . (int)$prod['id_produto'] . ' - ' . $prod['nome'] . '?', ENT_QUOTES); ?>"
                                  onsubmit="return confirm(this.dataset.msg);">
                                <?= campo_csrf(); ?>
                                <input type="hidden" name="excluir_produto" value="<?= (int)$prod['id_produto']; ?>">
                                <button class="btn btn-danger btn-sm">Excluir</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4"><?= $cat_sel !== '' ? 'Nenhum produto nesta categoria.' : 'Nenhum produto cadastrado até o momento.'; ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>