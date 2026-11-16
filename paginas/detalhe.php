<?php
require_once 'funcoes.php';
require_once __DIR__ . '/../funcoes_estoque.php';

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM produto WHERE id_produto = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$produto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produto) {
    echo "<div class='container my-5 text-center py-5'><h3>Produto não encontrado!</h3><a href='index.php' class='btn btn-dark mt-3'>Voltar à Loja</a></div>";
    return;
}

$precos = precosProduto($conn, $produto);
$produto['preco_original'] = $precos['original'];
$produto['preco_pix']      = $precos['pix'];

$cliente_logado_id = (int)($_SESSION['usuario_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_duvida'])) {
    $usuario_logado = $_SESSION['usuario_nome'] ?? $_SESSION['cliente_nome'] ?? null;
    $whatsapp_logado = $_SESSION['usuario_whatsapp'] ?? $_SESSION['cliente_whatsapp'] ?? '';

    if (empty($usuario_logado)) {
        echo "<script>alert('Faça seu login para enviar sua dúvida!'); window.location.href='index.php?pagina=login';</script>";
        exit;
    }

    $pergunta = mb_substr(trim((string)($_POST['pergunta'] ?? '')), 0, 1000);
    if (!empty($pergunta)) {
        $stmt_d = $conn->prepare("INSERT INTO produto_duvidas (produto_id, pergunta, nome_cliente, whatsapp_cliente) VALUES (?, ?, ?, ?)");
        $stmt_d->bind_param("isss", $id, $pergunta, $usuario_logado, $whatsapp_logado);
        $stmt_d->execute();
        $stmt_d->close();
    }

    $urlDestino = "index.php?pagina=detalhe&id=" . $id;
    if (!headers_sent()) {
        header("Location: " . $urlDestino);
    } else {
        echo "<script>window.location.href='" . $urlDestino . "';</script>";
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_avaliacao'])) {
    if ($cliente_logado_id <= 0) {
        echo "<script>alert('Faça seu login para avaliar o produto!'); window.location.href='index.php?pagina=login';</script>";
        exit;
    }
    $nota = max(1, min(5, (int)($_POST['nota'] ?? 5)));
    $comentario = mb_substr(trim((string)($_POST['comentario'] ?? '')), 0, 1000);
    $stmt_a = $conn->prepare("INSERT INTO produto_avaliacoes (produto_id, nota, comentario, id_cliente) VALUES (?, ?, ?, ?)");
    $stmt_a->bind_param("iisi", $id, $nota, $comentario, $cliente_logado_id);
    $stmt_a->execute();
    $stmt_a->close();

    $urlDestino = "index.php?pagina=detalhe&id=" . $id;
    if (!headers_sent()) {
        header("Location: " . $urlDestino);
    } else {
        echo "<script>window.location.href='" . $urlDestino . "';</script>";
    }
    exit;
}

$paginasPorCategoria = [
    'Moda Feminina' => 'feminino',
    'Moda Masculina' => 'masculino',
    'Calçados' => 'calcados',
];
$paginaVoltar = $paginasPorCategoria[$produto['categoria'] ?? ''] ?? 'home';

$stmt_fotos = $conn->prepare("SELECT * FROM produto_fotos WHERE produto_id = ?");
$stmt_fotos->bind_param("i", $id);
$stmt_fotos->execute();
$resultado_fotos = $stmt_fotos->get_result();
$fotos_extras = [];
while ($f = $resultado_fotos->fetch_assoc()) {
    $fotos_extras[] = $f['imagem_url'];
}
$stmt_fotos->close();

$stmt_av = $conn->prepare("SELECT * FROM produto_avaliacoes WHERE produto_id = ? ORDER BY id_avaliacao DESC");
$stmt_av->bind_param("i", $id);
$stmt_av->execute();
$res_av = $stmt_av->get_result();
$total_av = $res_av->num_rows;
$soma_notas = 0;
$avaliacoes = [];
while ($av = $res_av->fetch_assoc()) {
    $soma_notas += $av['nota'];
    $avaliacoes[] = $av;
}
$stmt_av->close();
$media_notas = $total_av > 0 ? round($soma_notas / $total_av, 1) : 5.0;

$stmt_duv = $conn->prepare("SELECT * FROM produto_duvidas WHERE produto_id = ? ORDER BY id_duvida DESC");
$stmt_duv->bind_param("i", $id);
$stmt_duv->execute();
$res_duv = $stmt_duv->get_result();
$stmt_duv->close();

$cfg_frete = $conn->query("SELECT * FROM configuracoes_frete WHERE id=1")->fetch_assoc();
if (!$cfg_frete) {
    $cfg_frete = [
        'valor_minimo_frete_gratis' => 199.90,
        'dentro_estado' => 15.00,
        'prazo_estado' => 3,
        'regiao_sul' => 20.00,
        'prazo_sul' => 5,
        'regiao_sudeste' => 25.00,
        'prazo_sudeste' => 6,
        'regiao_centro_oeste' => 35.00,
        'prazo_centro_oeste' => 7,
        'regiao_nordeste' => 45.00,
        'prazo_nordeste' => 10,
        'regiao_norte' => 50.00,
        'prazo_norte' => 12
    ];
}

$stmt_estoque = $conn->prepare("SELECT cor, tamanho, quantidade FROM produto_cores_tamanhos WHERE produto_id = ? AND quantidade > 0");
$stmt_estoque->bind_param("i", $id);
$stmt_estoque->execute();
$res_estoque = $stmt_estoque->get_result();
$stmt_estoque->close();

$estoque_detalhado = [];
$cores_disponiveis = [];
while ($row = $res_estoque->fetch_assoc()) {
    $cor = trim($row['cor']);
    $tamanho_raw = trim($row['tamanho']);
    $qtd = intval($row['quantidade']);

    $tamanhos_separados = array_map('trim', explode(',', $tamanho_raw));

    foreach ($tamanhos_separados as $tam) {
        if (!empty($tam)) {
            $estoque_detalhado[$cor][] = [
                'tamanho' => $tam,
                'quantidade' => $qtd
            ];
        }
    }

    if (!in_array($cor, $cores_disponiveis)) {
        $cores_disponiveis[] = $cor;
    }
}

// Mesma regra do servidor ao gravar o pedido: com grade controla por cor+tamanho, sem grade usa produto.estoque
$tem_grade = produtoTemGrade($conn, $id);
$estoque_sem_grade = $tem_grade ? 0 : max(0, (int)$produto['estoque']);
$produto_esgotado = $tem_grade ? empty($cores_disponiveis) : $estoque_sem_grade <= 0;

function obterCorHex($nomeCor)
{
    $c = strtolower(trim($nomeCor));
    $mapa = [
        'bege' => '#d8ccc0',
        'azul' => '#0056b3',
        'azul claro' => '#add8e6',
        'azul escuro' => '#1b263b',
        'preto' => '#111111',
        'branco' => '#ffffff',
        'vermelho' => '#dc3545',
        'rosa' => '#ffc0cb',
        'verde' => '#28a745',
        'verde musgo' => '#556b2f',
        'cinza' => '#6c757d',
        'marrom' => '#8b4513',
        'amarelo' => '#ffc107',
        'nude' => '#e3bc9a',
        'jeans' => '#3b5998',
        'manteiga' => '#fdf4dc',
        'off-white' => '#f8f9fa',
        'terracota' => '#e2725b'
    ];
    return $mapa[$c] ?? '#cccccc';
}
?>

<div class="container my-5">
    <div class="row g-5">
        <div class="col-md-6 coluna-fotos-detalhe">
            <div id="carrosselDetalhe" class="carousel slide mb-4" data-bs-ride="carousel">
                <div class="carousel-inner rounded shadow-sm bg-white">
                    <div class="carousel-item active text-center">
                        <img src="<?= htmlspecialchars($produto['imagem_url']); ?>"
                            class="d-block w-100 produto-img-carousel" alt="Capa" onclick="abrirLightbox(0)">
                    </div>
                    <?php foreach ($fotos_extras as $indexExtra => $foto): ?>
                        <div class="carousel-item text-center">
                            <img src="<?= htmlspecialchars($foto); ?>" class="d-block w-100 produto-img-carousel"
                                alt="Extra" onclick="abrirLightbox(<?= $indexExtra + 1; ?>)">
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($fotos_extras) > 0): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#carrosselDetalhe"
                        data-bs-slide="prev"><span
                            class="carousel-control-prev-icon bg-dark rounded-circle p-2"></span></button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carrosselDetalhe"
                        data-bs-slide="next"><span
                            class="carousel-control-next-icon bg-dark rounded-circle p-2"></span></button>
                <?php endif; ?>
            </div>

        </div>

        <div class="col-md-6">
            <h2 class="text-uppercase fw-bold mb-1 titulo-produto-detalhe"><?= htmlspecialchars($produto['nome']); ?></h2>
            <p class="text-muted small mb-3">Cód. <?= (int) $produto['id_produto']; ?></p>

            <div class="mb-3<?= $precos['promocao'] ? ' em-promocao' : ''; ?>">
                <?php if ($precos['promocao']): ?>
                    <span class="selo-promocao-detalhe">🔥 <?= htmlspecialchars($precos['promocao']['nome']); ?> · -<?= (int)$precos['promocao']['percentual']; ?>%</span>
                    <span class="preco-cheio-riscado d-block mt-2"><?= formatarPreco($precos['original_cheio']); ?></span>
                    <span class="preco-promocional fs-3 d-block"><?= formatarPreco($produto['preco_original']); ?></span>
                    <small class="text-muted d-block mb-1">Promoção válida até <?= date('d/m/Y \à\s H:i', strtotime($precos['promocao']['fim'])); ?>!</small>
                <?php else: ?>
                    <span class="text-dark fs-4 d-block"><?= formatarPreco($produto['preco_original']); ?></span>
                <?php endif; ?>
                <span
                    class="text-muted small"><?= calcularParcelaDinamica($produto['preco_original'], 10, 50.00); ?></span>
            </div>

            <div class="mb-4">
                <h3 class="text-success fw-bold"><?= formatarPreco($produto['preco_pix']); ?> <small
                        class="text-muted fs-6 fw-normal">(5% OFF no Pix)</small></h3>
                <a href="javascript:void(0);" onclick="toggleParcelamentoBox()"
                    class="small text-decoration-none text-muted">Ver mais opções de parcelamento</a>
            </div>

            <div id="blocoParcelamento" class="bloco-parcelamento-box" style="display: none;">
                <div class="parcelamento-titulo">Opções de Parcelamento</div>
                <div id="conteudoParcelas"></div>
                <p class="parcelamento-aviso">Os valores apresentados são apenas para consulta, o valor real da parcela
                    será exibido no fechamento do pedido!</p>
            </div>

            <?php if (!empty($cores_disponiveis)): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Cor: <span id="corSelecionadaLabel"
                            class="fw-normal text-capitalize"><?= htmlspecialchars($cores_disponiveis[0]); ?></span></label>
                    <div class="d-flex gap-3 align-items-center">
                        <?php foreach ($cores_disponiveis as $index => $cor): ?>
                            <div class="text-center cursor-pointer"
                                onclick="selecionarCor(<?= htmlspecialchars(json_encode((string)$cor, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>, this)">
                                <div class="cor-item rounded-circle border shadow-sm mx-auto mb-1 <?= $index === 0 ? 'border-dark border-3' : ''; ?>"
                                    style="width: 36px; height: 36px; background-color: <?= obterCorHex($cor); ?>;"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Tamanho:</label>
                <div id="containerTamanhos" class="d-flex flex-wrap gap-2"></div>
            </div>

            <?php if ($produto_esgotado): ?>
                <div class="alert alert-secondary text-center fw-semibold mb-3">Produto esgotado no momento!</div>
            <?php endif; ?>
            <div class="row g-2 mb-1 align-items-center">
                <div class="col-4">
                    <div class="input-group input-group-sm">
                        <button class="btn btn-outline-secondary" type="button" id="btn-qtd-menos" onclick="alterarQtdDetalhe(-1)">-</button>
                        <input type="text" id="qtd" class="form-control text-center bg-white" value="1" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="btn-qtd-mais" onclick="alterarQtdDetalhe(1)">+</button>
                    </div>
                </div>
                <div class="col-8">
                    <button type="button" id="btn-comprar-agora" class="btn btn-dark w-100 text-uppercase fw-bold py-2 btn-comprar-custom"
                        onclick="comprarAgoraDireto()">Comprar Agora</button>
                </div>
            </div>
            <div id="estoqueInfo" class="small text-muted mb-4"></div>

            <div class="card bg-light border-0 p-3 mb-4">
                <p class="text-dark mb-0 descricao-produto-txt">
                    <?= nl2br(htmlspecialchars($produto['descricao'] ?? '')); ?>
                </p>
            </div>

            <hr class="text-muted">

            <div class="d-flex justify-content-around align-items-center mb-4 py-2 small border-bottom border-top">
                <a href="#" class="text-decoration-none text-dark fw-semibold" data-bs-toggle="modal"
                    data-bs-target="#modalMedidas">📐 Tabela de Medidas</a>
                <span class="text-muted">|</span>
                <a href="#" id="btn-favorito"
                    class="text-decoration-none text-dark fw-semibold text-danger d-inline-flex align-items-center gap-1"
                    data-id="<?= $produto['id_produto']; ?>" data-nome="<?= htmlspecialchars($produto['nome']); ?>"
                    data-preco="<?= $produto['preco_original']; ?>"
                    data-imagem="<?= htmlspecialchars($produto['imagem_url']); ?>">
                    <span class="fs-5" id="coracao-icone">♡</span> Lista de desejos
                </a>
                <span class="text-muted">|</span>
                <a href="javascript:void(0);" id="btn-sacola-prod"
                    class="text-decoration-none text-dark fw-semibold d-inline-flex align-items-center gap-1"
                    data-id="<?= $produto['id_produto']; ?>" data-nome="<?= htmlspecialchars($produto['nome']); ?>"
                    data-preco="<?= $produto['preco_original']; ?>"
                    data-imagem="<?= htmlspecialchars($produto['imagem_url']); ?>">
                    <span class="fs-5" id="sacola-icone">🛍️</span> Adicionar à Sacola
                </a>
            </div>

            <div id="blocoFrete" class="card bg-light border-0 p-3 mb-4">
                <h6 class="fw-semibold small mb-2">CALCULAR FRETE E PRAZO</h6>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" id="cep" class="form-control" placeholder="Digite seu CEP (Ex: 87320-000)"
                        maxlength="9">
                    <button class="btn btn-outline-dark ms-2" type="button" onclick="calcularFrete()">Calcular</button>
                </div>
                <div id="resultadoFrete" class="small text-muted"></div>
            </div>

            <div class="card border-0 shadow-sm p-4 mb-4">
                <div class="text-center mb-3">
                    <h1 class="fw-bold mb-0 display-5 text-dark"><?= $media_notas; ?></h1>
                    <div class="text-warning my-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star-item"><?= $i <= round($media_notas) ? '★' : '☆'; ?></span>
                        <?php endfor; ?>
                    </div>
                    <button type="button"
                        class="btn btn-link text-muted small text-decoration-underline p-0 border-0 bg-transparent"
                        onclick="toggleAvaliacoes()">
                        Média com base em <?= $total_av; ?> avaliações (Ver avaliações)
                    </button>
                </div>

                <form action="index.php?pagina=detalhe&id=<?= $id; ?>" method="POST"
                    class="bg-light p-3 rounded mb-3 border"
                    onsubmit="if (!CLIENTE_LOGADO) { alert('Faça seu login para avaliar o produto!'); window.location.href='index.php?pagina=login'; return false; } return true;">
                    <input type="hidden" name="nova_avaliacao" value="1">
                    <label class="form-label small fw-semibold">Clique nas estrelas para avaliar:</label>
                    <div class="star-rating mb-2" id="inputEstrelas">
                        <span onclick="setNota(1)">★</span><span onclick="setNota(2)">★</span><span
                            onclick="setNota(3)">★</span><span onclick="setNota(4)">★</span><span
                            onclick="setNota(5)">★</span>
                    </div>
                    <input type="hidden" name="nota" id="valorNota" value="5">
                    <textarea name="comentario" class="form-control form-control-sm mb-2"
                        placeholder="Escreva seu comentário sobre o produto..."></textarea>
                    <button type="submit" class="btn btn-dark btn-sm w-100">Enviar Avaliação</button>
                </form>

                <div id="listaAvaliacoesBox" class="avaliacoes-lista scroll-lista" style="display: none;">
                    <?php if ($total_av > 0): ?>
                        <?php foreach ($avaliacoes as $av): ?>
                            <div class="border-bottom py-2 small">
                                <span class="text-warning">⭐ <?= $av['nota']; ?></span>
                                <p class="mb-1 text-muted"><?= htmlspecialchars($av['comentario']); ?></p>
                                <small
                                    class="text-muted data-avaliacao-small"><?= date('d/m/Y', strtotime($av['data_criacao'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small text-center mb-0">Nenhuma avaliação ainda. Seja o primeiro a avaliar!</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-4">
                <h6 class="fw-bold mb-3">Dúvidas sobre o produto</h6>
                <form action="index.php?pagina=detalhe&id=<?= $id; ?>" method="POST" class="mb-3"
                    onsubmit="return validarEnvioDuvida(event)">
                    <input type="hidden" name="nova_duvida" value="1">

                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" name="pergunta" id="inputPerguntaDuvida"
                            class="form-control form-control-sm py-2" placeholder="Faça sua pergunta para a loja..."
                            required>
                        <button class="btn btn-dark btn-sm px-4 text-uppercase fw-semibold py-2"
                            type="submit">Enviar</button>
                    </div>

                    <small class="text-muted d-block mt-1" style="font-size: 11px;">* Identificado automaticamente com
                        os dados da sua conta!</small>
                </form>
                <div class="duvidas-lista scroll-lista-duvidas">
                    <?php if ($res_duv->num_rows > 0): ?>
                        <?php while ($duv = $res_duv->fetch_assoc()): ?>
                            <div class="p-2 mb-2 bg-light rounded border-start border-3 border-dark small">
                                <strong class="text-dark"><?= htmlspecialchars($duv['nome_cliente'] ?? 'Cliente'); ?>:</strong>
                                <?= htmlspecialchars($duv['pergunta']); ?><br>
                                <small class="text-muted"><?= date('d/m/Y', strtotime($duv['data_criacao'])); ?></small>
                                <?php if (!empty($duv['resposta'])): ?>
                                    <div class="mt-2 ps-2 border-start border-success text-success">
                                        <strong>SolaSol Ateliê:</strong> <?= htmlspecialchars($duv['resposta']); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-1 text-muted fst-italic aguardando-resposta-txt">Aguardando resposta da
                                        administração...</div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted small text-center mb-0">Nenhuma dúvida cadastrada ainda!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="index.php?pagina=<?= $paginaVoltar; ?>" class="btn-continuar-compras">
            Continuar minhas compras!
        </a>
    </div>
</div>

<div class="modal fade" id="modalMedidas" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">Tabela de Medidas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <pre
                    class="p-3 bg-white rounded border small text-dark mb-0 modal-pre-medidas"><?= htmlspecialchars($produto['tabela_medidas'] ?? 'Medidas padrão do fabricante.'); ?></pre>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalLightbox" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content lightbox-modal-content border-0 p-3">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body d-flex align-items-center justify-content-center p-0 position-relative text-center">
                <div class="position-relative d-inline-block">
                    <button class="lightbox-btn-proximo btn-prev" onclick="mudarFotoLightbox(-1)">&#10094;</button>
                    <div class="lightbox-modal-container" onclick="alternarZoomProgressivo()">
                        <img id="imagemLightboxSrc" src="" alt="Imagem Ampliada">
                    </div>
                    <button class="lightbox-btn-proximo btn-next" onclick="mudarFotoLightbox(1)">&#10095;</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Dados do PHP para o JS sempre via json_encode (com HEX_*): aspas, barras ou tags HTML no texto não quebram o script.
    // ATENÇÃO: nunca escreva a tag de fechamento de script dentro deste bloco, nem em comentário: o navegador encerra o script ali.
    <?php $JSF = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE; ?>
    const estoqueProduto = <?= json_encode($estoque_detalhado ?: new stdClass(), $JSF); ?>;
    const PRODUTO_JS = <?= json_encode([
        'id'     => (string)$produto['id_produto'],
        'nome'   => (string)$produto['nome'],
        'preco'  => (float)$produto['preco_original'],
        'imagem' => (string)$produto['imagem_url'],
    ], $JSF); ?>;
    const CLIENTE_LOGADO = <?= $cliente_logado_id > 0 ? 'true' : 'false'; ?>;
    const TEM_GRADE = <?= $tem_grade ? 'true' : 'false'; ?>;
    const ESTOQUE_SEM_GRADE = <?= (int)$estoque_sem_grade; ?>;
    const SACOLA_KEY_DET = 'solasol_sacola';

    // Cor/tamanho escolhidos ficam em variáveis (antes eram lidos do texto da tela, que o CSS pode alterar)
    let corAtual = null;
    let tamanhoAtual = null;

    /* ---------- Estoque x quantidade ---------- */
    function corDoItem()     { return TEM_GRADE ? (corAtual || '') : 'Padrão'; }
    function tamanhoDoItem() { return TEM_GRADE ? (tamanhoAtual || '') : 'Único'; }

    function lerSacolaDet() {
        try { const s = JSON.parse(localStorage.getItem(SACOLA_KEY_DET)); return Array.isArray(s) ? s : []; }
        catch (e) { return []; }
    }
    function mesmoItem(it) {
        return String(it.id_produto || it.id) === String(PRODUTO_JS.id)
            && String(it.cor || '').toLowerCase() === corDoItem().toLowerCase()
            && String(it.tamanho || '').toLowerCase() === tamanhoDoItem().toLowerCase();
    }
    function qtdNaSacola() {
        return lerSacolaDet().filter(mesmoItem).reduce((s, it) => s + (parseInt(it.quantidade) || 0), 0);
    }
    function disponivelSelecionado() {
        if (!TEM_GRADE) return ESTOQUE_SEM_GRADE;
        if (!corAtual || !tamanhoAtual) return 0;
        const item = (estoqueProduto[corAtual] || []).find(i => i.tamanho === tamanhoAtual);
        return item ? parseInt(item.quantidade) || 0 : 0;
    }
    function maxPermitido() { return Math.max(0, disponivelSelecionado() - qtdNaSacola()); }

    function atualizarControlesQtd() {
        const disp = disponivelSelecionado();
        const naSacola = qtdNaSacola();
        const max = maxPermitido();
        const q = document.getElementById('qtd');
        let v = parseInt(q.value) || 1;
        v = max > 0 ? Math.min(Math.max(1, v), max) : 0;
        q.value = v;

        document.getElementById('btn-qtd-menos').disabled = v <= 1;
        document.getElementById('btn-qtd-mais').disabled = v >= max;

        const btnComprar = document.getElementById('btn-comprar-agora');
        const btnSacola = document.getElementById('btn-sacola-prod');
        // "Comprar Agora" continua liberado se a peça já está na sacola (só leva ao checkout)
        btnComprar.disabled = max <= 0 && naSacola <= 0;
        if (btnSacola) btnSacola.classList.toggle('opacity-50', max <= 0);

        const info = document.getElementById('estoqueInfo');
        if (disp <= 0) {
            info.innerHTML = '<span class="text-danger fw-semibold">Indisponível nesta cor/tamanho!</span>';
        } else if (max <= 0) {
            info.innerHTML = '<span class="text-warning-emphasis fw-semibold">Você já tem na sacola todas as unidades disponíveis (' + disp + ')!</span>';
        } else {
            info.textContent = (disp === 1 ? 'Última unidade disponível!' : disp + ' unidades disponíveis!')
                + (naSacola > 0 ? ' (' + naSacola + ' já na sua sacola)' : '');
        }
    }

    function alterarQtdDetalhe(delta) {
        const q = document.getElementById('qtd');
        q.value = (parseInt(q.value) || 1) + delta;
        atualizarControlesQtd();
    }

    /** Coloca a quantidade escolhida na sacola, sem passar do estoque. Retorna false se não couber. */
    function adicionarNaSacolaDet(quantidade) {
        if (quantidade < 1 || quantidade > maxPermitido()) return false;
        const sacola = lerSacolaDet();
        const existente = sacola.find(mesmoItem);
        if (existente) {
            existente.quantidade = (parseInt(existente.quantidade) || 0) + quantidade;
        } else {
            sacola.push({
                id: PRODUTO_JS.id,
                nome: PRODUTO_JS.nome,
                preco: PRODUTO_JS.preco,
                imagem: PRODUTO_JS.imagem,
                quantidade: quantidade,
                cor: corDoItem(),
                tamanho: tamanhoDoItem(),
                uniqueKey: PRODUTO_JS.id + '-' + corDoItem() + '-' + tamanhoDoItem()
            });
        }
        localStorage.setItem(SACOLA_KEY_DET, JSON.stringify(sacola));
        return true;
    }

    function selecionarCor(corNome, elementoEl) {
        corAtual = corNome;
        tamanhoAtual = null;
        document.getElementById('corSelecionadaLabel').innerText = corNome;
        document.querySelectorAll('.cor-item').forEach(el => el.classList.remove('border-dark', 'border-3'));
        if (elementoEl) elementoEl.querySelector('.cor-item').classList.add('border-dark', 'border-3');

        const containerTamanhos = document.getElementById('containerTamanhos');
        containerTamanhos.innerHTML = '';

        if (estoqueProduto[corNome]) {
            estoqueProduto[corNome].forEach((item, index) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-outline-dark btn-sm rounded-pill px-3 py-2 d-inline-flex align-items-center justify-content-center tamanho-btn tamanho-btn-custom' + (index === 0 ? ' active' : '');
                btn.textContent = item.tamanho;   // texto puro (não interpreta HTML)
                btn.dataset.tamanho = item.tamanho;
                btn.onclick = function () { ativarTamanho(btn); };
                containerTamanhos.appendChild(btn);
                if (index === 0) tamanhoAtual = item.tamanho;
            });
        }
        document.getElementById('qtd').value = 1;
        atualizarControlesQtd();
    }

    function ativarTamanho(botaoEl) {
        document.querySelectorAll('.tamanho-btn').forEach(b => b.classList.remove('active'));
        botaoEl.classList.add('active');
        tamanhoAtual = botaoEl.dataset.tamanho;
        document.getElementById('qtd').value = 1;
        atualizarControlesQtd();
    }

    let galeriaFotos = <?= json_encode(array_values(array_merge([(string)$produto['imagem_url']], $fotos_extras)), $JSF); ?>;
    let indiceAtualFoto = 0;
    let nivelZoom = 0;

    function abrirLightbox(indice) {
        indiceAtualFoto = indice;
        nivelZoom = 0;
        atualizarImagemLightbox();
        var meuModal = new bootstrap.Modal(document.getElementById('modalLightbox'));
        meuModal.show();
    }

    function mudarFotoLightbox(direcao) {
        nivelZoom = 0;
        document.getElementById('imagemLightboxSrc').style.transform = 'scale(1)';
        indiceAtualFoto += direcao;
        if (indiceAtualFoto < 0) {
            indiceAtualFoto = galeriaFotos.length - 1;
        } else if (indiceAtualFoto >= galeriaFotos.length) {
            indiceAtualFoto = 0;
        }
        atualizarImagemLightbox();
    }

    function atualizarImagemLightbox() {
        let img = document.getElementById('imagemLightboxSrc');
        img.src = galeriaFotos[indiceAtualFoto];
        img.style.transform = 'scale(1)';
    }

    function alternarZoomProgressivo() {
        let img = document.getElementById('imagemLightboxSrc');
        nivelZoom++;

        if (nivelZoom === 1) {
            img.style.transform = 'scale(1.8)';
            img.style.cursor = 'zoom-out';
        } else if (nivelZoom === 2) {
            img.style.transform = 'scale(2.6)';
        } else {
            img.style.transform = 'scale(1)';
            img.style.cursor = 'zoom-in';
            nivelZoom = 0;
        }
    }

    // Validação de login estrita no envio da dúvida
    function validarEnvioDuvida(event) {
        let usuarioLogado = CLIENTE_LOGADO;

        if (!usuarioLogado) {
            event.preventDefault();
            alert('Faça seu login para enviar sua dúvida!');
            window.location.href = 'index.php?pagina=login';
            return false;
        }
        return true;
    }

    // Coloca a peça na sacola (sem apagar os outros itens, como acontecia antes) e vai para o checkout
    function comprarAgoraDireto() {
        const quantidade = parseInt(document.getElementById('qtd').value) || 0;
        if (quantidade > 0 && !adicionarNaSacolaDet(quantidade)) {
            alert('Quantidade indisponível em estoque!');
            atualizarControlesQtd();
            return;
        }
        if (quantidade <= 0 && qtdNaSacola() <= 0) {
            alert('Este item está indisponível no momento!');
            return;
        }
        window.location.href = 'index.php?pagina=checkout';
    }

    function toggleAvaliacoes() {
        const box = document.getElementById('listaAvaliacoesBox');
        if (box.style.display === 'none' || box.style.display === '') {
            box.style.display = 'block';
        } else {
            box.style.display = 'none';
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        const primeirasCores = document.querySelectorAll('.cursor-pointer');
        if (primeirasCores.length > 0) primeirasCores[0].click();

        const btnFavorito = document.getElementById('btn-favorito');
        const iconeCoracao = document.getElementById('coracao-icone');

        if (btnFavorito) {
            const produtoObj = {
                id: btnFavorito.getAttribute('data-id'),
                nome: btnFavorito.getAttribute('data-nome'),
                preco: btnFavorito.getAttribute('data-preco'),
                imagem: btnFavorito.getAttribute('data-imagem')
            };
            const STORAGE_KEY = 'solasol_favoritos';

            function checarFavorito() {
                let favoritos = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
                if (favoritos.some(item => item.id === produtoObj.id)) {
                    iconeCoracao.innerText = '♥';
                    iconeCoracao.style.color = '#dc3545';
                } else {
                    iconeCoracao.innerText = '♡';
                    iconeCoracao.style.color = 'inherit';
                }
            }

            btnFavorito.addEventListener('click', (e) => {
                e.preventDefault();
                let favoritos = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
                let index = favoritos.findIndex(item => item.id === produtoObj.id);
                if (index > -1) favoritos.splice(index, 1);
                else favoritos.push(produtoObj);
                localStorage.setItem(STORAGE_KEY, JSON.stringify(favoritos));
                checarFavorito();
            });
            checarFavorito();
        }

        const btnSacola = document.getElementById('btn-sacola-prod');
        if (btnSacola) {
            btnSacola.addEventListener('click', (e) => {
                e.preventDefault();
                const quantidade = parseInt(document.getElementById('qtd').value) || 0;
                if (!adicionarNaSacolaDet(quantidade)) {
                    alert(disponivelSelecionado() <= 0
                        ? 'Este item está indisponível nesta cor/tamanho!'
                        : 'Você já tem na sacola todas as unidades disponíveis deste item!');
                    atualizarControlesQtd();
                    return;
                }
                document.getElementById('qtd').value = 1;
                atualizarControlesQtd();
                alert('Produto adicionado à sacola com sucesso!');
            });
        }

        // Produto sem grade (sem cores) ou sem nenhuma cor disponível: atualiza os controles mesmo assim
        if (primeirasCores.length === 0) atualizarControlesQtd();

        // Se a sacola mudar em outra aba, recalcula o limite
        window.addEventListener('storage', atualizarControlesQtd);
    });

    function setNota(nota) {
        document.getElementById('valorNota').value = nota;
        document.querySelectorAll('#inputEstrelas span').forEach((el, index) => {
            el.style.color = index < nota ? '#ffc107' : '#ccc';
        });
    }

    function calcularFrete() {
        let cepInput = document.getElementById('cep').value.replace(/\D/g, '');
        let res = document.getElementById('resultadoFrete');

        if (cepInput.length !== 8) {
            res.innerHTML = "<span class='text-danger'>Digite um CEP válido com 8 dígitos!</span>";
            return;
        }

        let prefixo = parseInt(cepInput.substring(0, 5));
        let regiaoNome = "";
        let valorFrete = 0.0;
        let prazoDias = 0;

        if (prefixo >= 80000 && prefixo <= 87999) {
            regiaoNome = "Entrega Local / Dentro do Estado (PR)";
            valorFrete = <?= $cfg_frete['dentro_estado'] ?? 15.00; ?>;
            prazoDias = <?= $cfg_frete['prazo_estado'] ?? 3; ?>;
        }
        else if (prefixo >= 80000 && prefixo <= 99999) {
            regiaoNome = "Região Sul";
            valorFrete = <?= $cfg_frete['regiao_sul'] ?? 20.00; ?>;
            prazoDias = <?= $cfg_frete['prazo_sul'] ?? 5; ?>;
        }
        else if ((prefixo >= 1000 && prefixo <= 39999) || (prefixo >= 10000 && prefixo <= 19999)) {
            regiaoNome = "Região Sudeste";
            valorFrete = <?= $cfg_frete['regiao_sudeste'] ?? 25.00; ?>;
            prazoDias = <?= $cfg_frete['prazo_sudeste'] ?? 6; ?>;
        }
        else if (prefixo >= 76800 && prefixo <= 77999) {
            // Rondônia (76800-76999) e Tocantins (77000-77999) são Região Norte, apesar do CEP 7xxxx
            regiaoNome = "Região Norte";
            valorFrete = <?= (float)($cfg_frete['regiao_norte'] ?? 50.00); ?>;
            prazoDias = <?= (int)($cfg_frete['prazo_norte'] ?? 12); ?>;
        }
        else if (prefixo >= 70000 && prefixo <= 79999) {
            regiaoNome = "Região Centro-Oeste";
            valorFrete = <?= $cfg_frete['regiao_centro_oeste'] ?? 35.00; ?>;
            prazoDias = <?= $cfg_frete['prazo_centro_oeste'] ?? 7; ?>;
        }
        else if (prefixo >= 40000 && prefixo <= 65999) {
            regiaoNome = "Região Nordeste";
            valorFrete = <?= $cfg_frete['regiao_nordeste'] ?? 45.00; ?>;
            prazoDias = <?= $cfg_frete['prazo_nordeste'] ?? 10; ?>;
        }
        else {
            regiaoNome = "Região Norte / Demais";
            valorFrete = <?= $cfg_frete['regiao_norte'] ?? 50.00; ?>;
            prazoDias = <?= $cfg_frete['prazo_norte'] ?? 12; ?>;
        }

        let precoProduto = parseFloat(<?= (float)($produto['preco_original'] ?? 0); ?>);   // frete grátis conta o valor antes do 5% do Pix (igual ao checkout)
        let valorMinimoGratis = parseFloat(<?= $cfg_frete['valor_minimo_frete_gratis'] ?? 199.90; ?>);

        if (precoProduto >= valorMinimoGratis) {
            res.innerHTML = `
            <div class='mt-2 border-top pt-2'>
                <div class='small text-muted'>Região: <strong>${regiaoNome}</strong></div>
                <div class='text-success fw-bold mt-1'>✨ FRETE GRÁTIS (Aplicado para compras acima de R$ ${valorMinimoGratis.toFixed(2)})</div>
                <div class='small text-muted mt-1'>Prazo estimado de entrega: <strong>${prazoDias} dias úteis</strong></div>
            </div>`;
        } else {
            res.innerHTML = `
            <div class='mt-2 border-top pt-2'>
                <div class='small text-muted'>Região identificada: <strong>${regiaoNome}</strong></div>
                <div class='d-flex justify-content-between mt-1'>
                    <span>Frete Padrão (${prazoDias} dias úteis)</span> 
                    <strong>R$ ${valorFrete.toFixed(2)}</strong>
                </div>
            </div>`;
        }
    }

    function toggleParcelamentoBox() {
        const bloco = document.getElementById('blocoParcelamento');

        if (bloco.style.display === 'none' || bloco.style.display === '') {
            let precoTotal = parseFloat(<?= $produto['preco_original'] ?? 0; ?>);
            let maxParcelasDesejadas = 10;
            let valorMinimoParcela = 50.00;

            let qtdParcelasPermitidas = Math.floor(precoTotal / valorMinimoParcela);
            if (qtdParcelasPermitidas < 1) qtdParcelasPermitidas = 1;
            if (qtdParcelasPermitidas > maxParcelasDesejadas) qtdParcelasPermitidas = maxParcelasDesejadas;

            let htmlParcelas = '';
            for (let i = 1; i <= qtdParcelasPermitidas; i++) {
                let valorParcela = precoTotal / i;
                htmlParcelas += `
                <div class="d-flex justify-content-between parcelamento-linha">
                    <span>Em <strong>${i}x</strong> de <strong>R$ ${valorParcela.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong></span>
                    <span class="text-muted">sem juros</span>
                </div>`;
            }

            document.getElementById('conteudoParcelas').innerHTML = htmlParcelas;
            bloco.style.display = 'block';
        } else {
            bloco.style.display = 'none';
        }
    }
</script>