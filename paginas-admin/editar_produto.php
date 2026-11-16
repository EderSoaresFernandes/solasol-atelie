<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

$id_produto = intval($_GET['id'] ?? 0);

$stmt_p = $conn->prepare("SELECT * FROM produto WHERE id_produto = ?");
$stmt_p->bind_param("i", $id_produto);
$stmt_p->execute();
$produto = $stmt_p->get_result()->fetch_assoc();
$stmt_p->close();

if (!$produto) {
    echo "<div class='alert alert-danger'>Produto não encontrado.</div>";
    return;
}

$mensagem_edit = "";
$categorias_validas = ['Moda Feminina', 'Moda Masculina', 'Calçados'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_produto'])) {
    try {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $categoria = trim((string)($_POST['categoria'] ?? ''));
        $descricao = trim((string)($_POST['descricao'] ?? ''));
        $preco_original = max(0, floatval($_POST['preco_original'] ?? 0));
        $preco_pix = round($preco_original * 0.95, 2);
        $parcelas_max = min(12, max(1, intval($_POST['parcelas_max'] ?? 6)));
        $tabela_medidas = trim((string)($_POST['tabela_medidas'] ?? ''));

        if ($nome === '') throw new Exception("Informe o nome do produto.");
        if (!in_array($categoria, $categorias_validas, true)) throw new Exception("Categoria inválida.");

        $stmt_up = $conn->prepare("UPDATE produto SET nome = ?, categoria = ?, descricao = ?, preco_original = ?, preco_pix = ?, parcelas_max = ?, tabela_medidas = ? WHERE id_produto = ?");
        $stmt_up->bind_param("sssddisi", $nome, $categoria, $descricao, $preco_original, $preco_pix, $parcelas_max, $tabela_medidas, $id_produto);
        $stmt_up->execute();
        $stmt_up->close();

        $pasta_uploads = "imagens/produtos/";
        $capa_definida = !empty($produto['imagem_url']) && $produto['imagem_url'] !== '0';
        $recusadas = [];

        if (isset($_FILES['fotos_produto']) && is_array($_FILES['fotos_produto']['name'])) {
            $total_arquivos = count($_FILES['fotos_produto']['name']);
            for ($i = 0; $i < $total_arquivos; $i++) {
                $erro_up = $_FILES['fotos_produto']['error'][$i];
                if ($erro_up === UPLOAD_ERR_NO_FILE) continue;

                $nome_orig = basename((string)$_FILES['fotos_produto']['name'][$i]);
                if ($erro_up !== UPLOAD_ERR_OK) { $recusadas[] = $nome_orig; continue; }

                $tmp = $_FILES['fotos_produto']['tmp_name'][$i];
                $ext = detectar_extensao_imagem($tmp);
                $caminho_destino = $ext ? salvar_imagem_upload($tmp, $pasta_uploads, $ext) : null;
                if ($caminho_destino === null) { $recusadas[] = $nome_orig; continue; }

                $stmt_foto = $conn->prepare("INSERT INTO produto_fotos (produto_id, imagem_url) VALUES (?, ?)");
                $stmt_foto->bind_param("is", $id_produto, $caminho_destino);
                $stmt_foto->execute();
                $stmt_foto->close();

                if (!$capa_definida) {
                    $stmt_img = $conn->prepare("UPDATE produto SET imagem_url = ? WHERE id_produto = ?");
                    $stmt_img->bind_param("si", $caminho_destino, $id_produto);
                    $stmt_img->execute();
                    $stmt_img->close();
                    $capa_definida = true;
                }
            }
        }

        $mensagem_edit = "<div class='alert alert-success'>Produto atualizado com sucesso!</div>";
        if ($recusadas) {
            $mensagem_edit .= "<div class='alert alert-warning'>Arquivos recusados (use JPG, PNG, WEBP ou GIF de até 5 MB): "
                . htmlspecialchars(implode(', ', $recusadas)) . "</div>";
        }

        $stmt_p = $conn->prepare("SELECT * FROM produto WHERE id_produto = ?");
        $stmt_p->bind_param("i", $id_produto);
        $stmt_p->execute();
        $produto = $stmt_p->get_result()->fetch_assoc();
        $stmt_p->close();

    } catch (Exception $e) {
        $mensagem_edit = "<div class='alert alert-danger'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$stmt_ft = $conn->prepare("SELECT * FROM produto_fotos WHERE produto_id = ?");
$stmt_ft->bind_param("i", $id_produto);
$stmt_ft->execute();
$fotos_cadastradas = $stmt_ft->get_result();
$stmt_ft->close();
?>

<?= $mensagem_edit; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">✏️ Alterar Produto: <?= (int)$produto['id_produto']; ?> - <?= htmlspecialchars($produto['nome']); ?></h5>
    <a href="admin.php?aba=lista_produtos" class="btn btn-outline-custom btn-sm">← Voltar para Lista</a>
</div>
<hr class="mb-4">

<form action="admin.php?aba=editar_produto&id=<?= (int)$id_produto; ?>" method="POST" enctype="multipart/form-data">
    <?= campo_csrf(); ?>
    <input type="hidden" name="atualizar_produto" value="1">

    <div class="mb-3">
        <label class="form-label fw-semibold">Código do Produto (ID)</label>
        <input type="text" class="form-control bg-light fw-bold" style="max-width: 180px;" value="<?= (int)$produto['id_produto']; ?>" readonly>
        <div class="form-text">O código é o ID do produto na loja e nos pedidos, por isso não pode ser alterado. Para usar outro código, cadastre um novo produto.</div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Nome do Produto</label>
        <input type="text" name="nome" class="form-control" required value="<?= htmlspecialchars($produto['nome']); ?>">
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold">Categoria</label>
            <select name="categoria" class="form-select" required>
                <option value="Moda Feminina" <?= $produto['categoria'] === 'Moda Feminina' ? 'selected' : ''; ?>>Moda Feminina</option>
                <option value="Moda Masculina" <?= $produto['categoria'] === 'Moda Masculina' ? 'selected' : ''; ?>>Moda Masculina</option>
                <option value="Calçados" <?= $produto['categoria'] === 'Calçados' ? 'selected' : ''; ?>>Calçados</option>
            </select>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label fw-semibold">Preço Original (R$)</label>
            <input type="number" step="0.01" min="0" name="preco_original" id="preco_original" class="form-control" required value="<?= htmlspecialchars($produto['preco_original']); ?>">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label small fw-semibold">Preço no Pix (R$)</label>
            <input type="number" step="0.01" min="0" name="preco_pix" id="preco_pix" class="form-control bg-light" readonly tabindex="-1" value="<?= number_format(round((float)$produto['preco_original'] * 0.95, 2), 2, '.', ''); ?>">
            <small class="text-muted" style="font-size: 11px;">Automático: preço original -5% (com promoção, o Pix desconta 5% do preço promocional)</small>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label small fw-semibold">Máx. Parcelas</label>
            <input type="number" min="1" max="12" name="parcelas_max" class="form-control" required value="<?= htmlspecialchars($produto['parcelas_max']); ?>">
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Adicionar Novas Fotos (Galeria)</label>
        <input type="file" name="fotos_produto[]" class="form-control" multiple accept="image/jpeg,image/png,image/webp,image/gif">
    </div>

    <?php if ($fotos_cadastradas && $fotos_cadastradas->num_rows > 0): ?>
        <div class="mb-3 p-3 bg-light border rounded">
            <label class="form-label fw-semibold small text-muted d-block mb-2">Fotos Atuais no Sistema:</label>
            <div class="d-flex flex-wrap gap-2">
                <?php while ($f = $fotos_cadastradas->fetch_assoc()): ?>
                    <div class="position-relative border rounded bg-white p-1" style="width: 70px; height: 70px;">
                        <img src="<?= htmlspecialchars($f['imagem_url']); ?>" class="w-100 h-100 object-fit-cover rounded">
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label fw-semibold">Descrição Detalhada</label>
        <textarea name="descricao" class="form-control" rows="3"><?= htmlspecialchars((string)$produto['descricao']); ?></textarea>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Tabela de Medidas (Detalhes)</label>
        <textarea name="tabela_medidas" class="form-control" rows="2"><?= htmlspecialchars((string)$produto['tabela_medidas']); ?></textarea>
    </div>

    <button type="submit" class="btn btn-dark w-100 py-2 text-uppercase fw-bold">Salvar Alterações do Produto</button>
</form>

<script>
    document.getElementById('preco_original').addEventListener('input', function() {
        let val = parseFloat(this.value);
        if (!isNaN(val) && val > 0) {
            document.getElementById('preco_pix').value = (val * 0.95).toFixed(2);
        }
    });
</script>
