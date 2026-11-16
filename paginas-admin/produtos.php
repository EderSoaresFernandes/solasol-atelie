<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

$mensagem = "";
$categorias_validas = ['Moda Feminina', 'Moda Masculina', 'Calçados'];
$cadastro_ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_produto'])) {
    try {
        $nome = trim((string)($_POST['nome'] ?? ''));
        $categoria = trim((string)($_POST['categoria'] ?? ''));
        $descricao = (string)($_POST['descricao'] ?? '');
        $preco_original = max(0, floatval($_POST['preco_original'] ?? 0));
        $preco_pix = round($preco_original * 0.95, 2);
        $parcelas_max = min(12, max(1, intval($_POST['parcelas_max'] ?? 6)));
        $tabela_medidas = (string)($_POST['tabela_medidas'] ?? '');
        $imagem_vazia = '';

        if ($nome === '') throw new Exception("Informe o nome do produto.");
        if (!in_array($categoria, $categorias_validas, true)) throw new Exception("Categoria inválida.");

        $codigo_txt = trim((string)($_POST['codigo'] ?? ''));
        if (!ctype_digit($codigo_txt) || (int)$codigo_txt < 1 || (int)$codigo_txt > 999999) {
            throw new Exception("Informe o código do produto usando apenas números (de 1 a 999999).");
        }
        $codigo = (int)$codigo_txt;

        $stmt_cod = $conn->prepare("SELECT nome FROM produto WHERE id_produto = ?");
        $stmt_cod->bind_param("i", $codigo);
        $stmt_cod->execute();
        $ja_existe = $stmt_cod->get_result()->fetch_assoc();
        $stmt_cod->close();
        if ($ja_existe) {
            throw new Exception("O código {$codigo} já está em uso pelo produto \"" . $ja_existe['nome'] . "\". Escolha outro código.");
        }

        $fotos_validas = [];
        if (isset($_FILES['fotos_produto']) && is_array($_FILES['fotos_produto']['name'])) {
            $total_arquivos = count($_FILES['fotos_produto']['name']);
            for ($i = 0; $i < $total_arquivos; $i++) {
                if ($_FILES['fotos_produto']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $tmp = $_FILES['fotos_produto']['tmp_name'][$i];
                $ext = detectar_extensao_imagem($tmp);
                if ($ext !== null) $fotos_validas[] = ['tmp' => $tmp, 'ext' => $ext];
            }
        }
        if (empty($fotos_validas)) {
            throw new Exception("Envie ao menos uma imagem válida (JPG, PNG, WEBP ou GIF de até 5 MB).");
        }

        $blocos_cores = is_array($_POST['bloco_cor'] ?? null) ? $_POST['bloco_cor'] : [];
        $estoque_total = 0;
        $dados_para_salvar = [];

        foreach ($blocos_cores as $bloco) {
            if (!is_array($bloco)) continue;
            $cor_nome = trim((string)($bloco['cor'] ?? ''));
            if (!empty($cor_nome) && isset($bloco['tamanho']) && is_array($bloco['tamanho'])) {
                for ($i = 0; $i < count($bloco['tamanho']); $i++) {
                    $tam = trim((string)$bloco['tamanho'][$i]);
                    $qtd = max(0, intval($bloco['quantidade'][$i] ?? 0));

                    if (!empty($tam)) {
                        $estoque_total += $qtd;
                        $dados_para_salvar[] = ['cor' => $cor_nome, 'tamanho' => $tam, 'quantidade' => $qtd];
                    }
                }
            }
        }

        $stmt = $conn->prepare("INSERT INTO produto (id_produto, nome, categoria, descricao, preco_original, preco_pix, parcelas_max, imagem_url, estoque, tabela_medidas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssddisis", $codigo, $nome, $categoria, $descricao, $preco_original, $preco_pix, $parcelas_max, $imagem_vazia, $estoque_total, $tabela_medidas);
        $stmt->execute();
        $produto_id = $codigo;
        $stmt->close();

        if (!empty($dados_para_salvar)) {
            $stmt_var = $conn->prepare("INSERT INTO produto_cores_tamanhos (produto_id, cor, tamanho, quantidade) VALUES (?, ?, ?, ?)");
            foreach ($dados_para_salvar as $var) {
                $stmt_var->bind_param("issi", $produto_id, $var['cor'], $var['tamanho'], $var['quantidade']);
                $stmt_var->execute();
            }
            $stmt_var->close();
        }

        $primeira_foto = "";
        $pasta_uploads = "imagens/produtos/";

        foreach ($fotos_validas as $foto) {
            $caminho_destino = salvar_imagem_upload($foto['tmp'], $pasta_uploads, $foto['ext']);
            if ($caminho_destino === null) continue;
            if ($primeira_foto === "") $primeira_foto = $caminho_destino;
            $stmt_foto = $conn->prepare("INSERT INTO produto_fotos (produto_id, imagem_url) VALUES (?, ?)");
            $stmt_foto->bind_param("is", $produto_id, $caminho_destino);
            $stmt_foto->execute();
            $stmt_foto->close();
        }

        if ($primeira_foto !== "") {
            $stmt_update = $conn->prepare("UPDATE produto SET imagem_url = ? WHERE id_produto = ?");
            $stmt_update->bind_param("si", $primeira_foto, $produto_id);
            $stmt_update->execute();
            $stmt_update->close();
        }

        $cadastro_ok = true;
        $mensagem = "<div class='alert alert-success'>Produto e grade cadastrados com sucesso! Código do produto: <strong>{$codigo}</strong></div>";
    } catch (mysqli_sql_exception $e) {
        $msg_db = ((int)$e->getCode() === 1062) ? "Este código já está em uso. Escolha outro código." : $e->getMessage();
        $mensagem = "<div class='alert alert-danger'><strong>Erro:</strong> " . htmlspecialchars($msg_db) . "</div>";
    } catch (Exception $e) {
        $mensagem = "<div class='alert alert-danger'><strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

$res_prox = $conn->query("SELECT COALESCE(MAX(id_produto), 0) + 1 AS prox FROM produto");
$proximo_codigo = $res_prox ? (int)$res_prox->fetch_assoc()['prox'] : 1;
$valor_codigo = (!$cadastro_ok && isset($_POST['cadastrar_produto']) && is_string($_POST['codigo'] ?? null)) ? $_POST['codigo'] : '';
?>
<script>
    let blocoIndex = 0;
    function adicionarBlocoCor() {
        blocoIndex++;
        const container = document.getElementById('container-cores-tamanhos');
        const blocoHtml = `
            <div class="card mb-3 p-3 bg-white border bloco-cor-item shadow-sm" id="bloco_${blocoIndex}">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <div class="w-75">
                        <label class="form-label fw-bold text-dark small mb-1">Cor do Produto</label>
                        <input type="text" name="bloco_cor[${blocoIndex}][cor]" class="form-control" placeholder="Ex: Azul, Preto, Manteiga" required>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removerBlocoCor('bloco_${blocoIndex}')">Remover Cor</button>
                </div>
                <label class="form-label text-muted small fw-semibold mt-1">Tamanhos e Quantidades Disponíveis:</label>
                <div class="tamanhos-container_${blocoIndex}">
                    <div class="row g-2 mb-2 tamanho-qtd-linha">
                        <div class="col-6"><input type="text" name="bloco_cor[${blocoIndex}][tamanho][]" class="form-control form-control-sm" placeholder="Tamanho (Ex: 36, P)" required></div>
                        <div class="col-5"><input type="number" name="bloco_cor[${blocoIndex}][quantidade][]" class="form-control form-control-sm" placeholder="Qtd Estoque" value="1" min="0" required></div>
                        <div class="col-1"></div>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="adicionarLinhaTamanho(${blocoIndex})">+ Adicionar outro tamanho</button>
            </div>`;
        container.insertAdjacentHTML('beforeend', blocoHtml);
    }
    function adicionarLinhaTamanho(bIndex) {
        const containerTid = document.querySelector(`.tamanhos-container_${bIndex}`);
        const linhaHtml = `
            <div class="row g-2 mb-2 tamanho-qtd-linha">
                <div class="col-6"><input type="text" name="bloco_cor[${bIndex}][tamanho][]" class="form-control form-control-sm" placeholder="Tamanho (Ex: 38, M)" required></div>
                <div class="col-5"><input type="number" name="bloco_cor[${bIndex}][quantidade][]" class="form-control form-control-sm" placeholder="Qtd Estoque" value="1" min="0" required></div>
                <div class="col-1 d-grid"><button type="button" class="btn btn-outline-danger btn-sm px-1 py-0" onclick="removerLinhaTamanho(this)">X</button></div>
            </div>`;
        containerTid.insertAdjacentHTML('beforeend', linhaHtml);
    }
    function removerLinhaTamanho(btn) { btn.closest('.tamanho-qtd-linha').remove(); }
    function removerBlocoCor(blocoId) {
        if (document.querySelectorAll('.bloco-cor-item').length > 1) {
            document.getElementById(blocoId).remove();
        } else {
            alert("O produto precisa ter pelo menos uma cor cadastrada!");
        }
    }
    function calcularPixAutomatico() {
        const inputOriginal = document.getElementById('preco_original');
        const inputPix = document.getElementById('preco_pix');
        if (inputOriginal && inputPix) {
            let precoOriginal = parseFloat(inputOriginal.value);
            if (!isNaN(precoOriginal) && precoOriginal > 0) {
                inputPix.value = (precoOriginal * 0.95).toFixed(2);
            }
        }
    }
    window.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('container-cores-tamanhos')) adicionarBlocoCor();
        const inputOriginal = document.getElementById('preco_original');
        if (inputOriginal) inputOriginal.addEventListener('input', calcularPixAutomatico);
    });
</script>

<?= $mensagem; ?>
<h5 class="fw-bold text-dark mb-0">📦 Cadastrar Novo Produto</h5>
<hr class="mb-4">
<form action="admin.php?aba=produtos" method="POST" enctype="multipart/form-data">
    <?= campo_csrf(); ?>
    <input type="hidden" name="cadastrar_produto" value="1">

    <div class="mb-4 p-3 bg-light border rounded shadow-sm">
        <label for="codigo_produto" class="form-label fw-bold text-dark mb-2">Código do Produto (ID) *</label>
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <input type="text" id="codigo_produto" name="codigo" class="form-control" required
                       inputmode="numeric" pattern="[0-9]{1,6}" maxlength="6" autocomplete="off"
                       placeholder="Ex: <?= (int)$proximo_codigo; ?>"
                       value="<?= htmlspecialchars($valor_codigo); ?>"
                       oninput="this.value = this.value.replace(/\D/g, '');">
            </div>
            <div class="col-md-9">
                <small class="text-muted">
                    Somente números (1 a 999999). Este código é o <strong>ID do produto</strong> na loja, na sacola e nos pedidos, e não pode se repetir.
                    Próximo número livre: <strong><?= (int)$proximo_codigo; ?></strong>.
                </small>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label fw-semibold">Nome do Produto</label>
        <input type="text" name="nome" class="form-control" required placeholder="Ex: CAMISA ALFAIATARIA CLÁSSICA">
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label fw-semibold">Categoria</label>
            <select name="categoria" class="form-select" required>
                <option value="Moda Feminina">Moda Feminina</option>
                <option value="Moda Masculina">Moda Masculina</option>
                <option value="Calçados">Calçados</option>
            </select>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label class="form-label fw-semibold">Preço Original (R$)</label>
            <input type="number" step="0.01" min="0" name="preco_original" id="preco_original" class="form-control" required placeholder="199.90">
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label small fw-semibold">Preço no Pix (R$)</label>
            <input type="number" step="0.01" min="0" name="preco_pix" id="preco_pix" class="form-control bg-light" readonly tabindex="-1" placeholder="189.91">
            <small class="text-muted" style="font-size: 11px;">Automático: preço original -5% (com promoção, o Pix desconta 5% do preço promocional)</small>
        </div>
        <div class="col-md-4 mb-3">
            <label class="form-label small fw-semibold">Máx. Parcelas</label>
            <input type="number" min="1" max="12" name="parcelas_max" class="form-control" value="6" required>
        </div>
    </div>
    <div class="mb-4 p-3 bg-light border rounded shadow-sm">
        <label class="form-label fw-bold text-dark mb-2">Grade de Cores, Tamanhos e Estoque</label>
        <div id="container-cores-tamanhos"></div>
        <button type="button" class="btn btn-dark btn-sm mt-3" onclick="adicionarBlocoCor()">+ Adicionar Outra Cor</button>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Fotos do Produto (Capa + Galeria)</label>
        <input type="file" name="fotos_produto[]" class="form-control" multiple required accept="image/jpeg,image/png,image/webp,image/gif">
        <div class="form-text">JPG, PNG, WEBP ou GIF, até 5 MB cada.</div>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Descrição Detalhada</label>
        <textarea name="descricao" class="form-control" rows="3" placeholder="Detalhes da peça..."></textarea>
    </div>
    <div class="mb-3">
        <label class="form-label fw-semibold">Tabela de Medidas (Detalhes)</label>
        <textarea name="tabela_medidas" class="form-control" rows="2" placeholder="Ex: 36 veste 38..."></textarea>
    </div>
    <button type="submit" class="btn btn-dark w-100 py-2 text-uppercase fw-bold">Cadastrar Produto com Grade Completa</button>
</form>