<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    echo "<script>window.location.href='index.php?pagina=login';</script>";
    exit;
}

require_once 'conexao.php';

$id_cliente = (int)$_SESSION['usuario_id'];
$mensagem_retorno = "";

$stmt_email_cli = $conn->prepare("SELECT email FROM clientes WHERE id_cliente = ?");
$stmt_email_cli->bind_param("i", $id_cliente);
$stmt_email_cli->execute();
$res_cli = $stmt_email_cli->get_result()->fetch_assoc();
$email_usuario = $res_cli['email'] ?? '';
$stmt_email_cli->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_entrega'], $_POST['pedido_id'])) {
    $pedido_id = intval($_POST['pedido_id']);
    $acao = (string)$_POST['acao_entrega'];

    if ($acao === 'entregue') {
        $stmt_status = $conn->prepare("UPDATE pedidos SET status_entrega = 'Recebido pelo cliente',
                                              status_pedido = IF(status_pedido IN ('Cancelado', 'Abandonado'), status_pedido, 'Entregue')
                                        WHERE id_pedido = ? AND (id_cliente = ? OR email_cliente = ?)");
        $stmt_status->bind_param("iis", $pedido_id, $id_cliente, $email_usuario);
        $mensagem_retorno = "Que bom que sua compra chegou! Obrigado por confirmar o recebimento!";
    } else {
        $relato = mb_substr(trim((string)($_POST['relato_entrega'] ?? '')), 0, 1000);
        if ($relato === '') $relato = 'O cliente informou que não recebeu o pedido.';
        $stmt_status = $conn->prepare("UPDATE pedidos SET status_entrega = 'Não recebido', relato_entrega = ?, data_relato_entrega = NOW()
                                        WHERE id_pedido = ? AND (id_cliente = ? OR email_cliente = ?)");
        $stmt_status->bind_param("siis", $relato, $pedido_id, $id_cliente, $email_usuario);
        $mensagem_retorno = "Recebemos o seu aviso! A loja vai verificar a entrega e responder aqui e pelo WhatsApp!";
    }
    $stmt_status->execute();
    if ($stmt_status->affected_rows < 0) $mensagem_retorno = '';
    $stmt_status->close();
}

$stmt_pedidos = $conn->prepare("SELECT * FROM pedidos WHERE (id_cliente = ? OR email_cliente = ?) AND status_pedido <> 'Abandonado' ORDER BY data_criacao DESC");
$stmt_pedidos->bind_param("is", $id_cliente, $email_usuario);
$stmt_pedidos->execute();
$resultado_pedidos = $stmt_pedidos->get_result();
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom">
                <h3 class="fw-bold text-uppercase m-0">Minhas Compras</h3>
                <a href="index.php" class="btn btn-outline-dark btn-sm text-uppercase fw-semibold">Voltar à Loja</a>
            </div>

            <?php if (!empty($mensagem_retorno)): ?>
                <div class="alert alert-success text-center"><?= htmlspecialchars($mensagem_retorno); ?></div>
            <?php endif; ?>

            <?php if ($resultado_pedidos->num_rows === 0): ?>
                <div class="text-center py-5 bg-light rounded shadow-sm">
                    <p class="text-muted mb-3">Você ainda não realizou nenhuma compra conosco. Confira já nossa Coleção!</p>
                    <a href="index.php?pagina=home" class="btn btn-dark btn-sm text-uppercase fw-semibold px-4">Explorar
                        Produtos</a>
                </div>
            <?php else: ?>
                <div class="accordion" id="accordionPedidos">
                    <?php while ($pedido = $resultado_pedidos->fetch_assoc()):
                        $pedido_id = $pedido['id_pedido'];
                        $data_formatada = date('d/m/Y H:i', strtotime($pedido['data_criacao']));
                        $valor_total = number_format($pedido['total_geral'] ?? 0, 2, ',', '.');
                        $rastreio = trim((string)($pedido['codigo_rastreio'] ?? ''));
                        $data_prevista = !empty($pedido['data_prevista']) ? date('d/m/Y', strtotime($pedido['data_prevista'])) : '';
                        $nota_fiscal = trim((string)($pedido['nota_fiscal'] ?? ''));
                        $nf_chave = trim((string)($pedido['nota_fiscal_chave'] ?? ''));
                        $status_entrega = $pedido['status_entrega'] ?: 'Pendente';
                        $status_pedido = (string)($pedido['status_pedido'] ?? 'Pendente');
                        $itens_json = $pedido['itens_json'] ?? '';
                        $cor_status = [
                            'Pendente' => 'secondary', 'Pago / Aprovado' => 'primary', 'Enviado' => 'info',
                            'Entregue' => 'success', 'Cancelado' => 'danger',
                        ][$status_pedido] ?? 'secondary';
                        ?>
                        <div class="card border-0 shadow-sm mb-3 bg-light">
                            <div
                                class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <span class="d-block small text-muted text-uppercase fw-semibold">Data da Compra</span>
                                    <span class="fw-bold text-dark"><?= $data_formatada; ?></span>
                                </div>
                                <div>
                                    <span class="d-block small text-muted text-uppercase fw-semibold">N° Pedido</span>
                                    <span class="fw-bold text-dark">#<?= str_pad($pedido_id, 5, '0', STR_PAD_LEFT); ?></span>
                                </div>
                                <div>
                                    <span class="d-block small text-muted text-uppercase fw-semibold">Valor da Compra</span>
                                    <span class="fw-bold text-dark">R$ <?= $valor_total; ?></span>
                                </div>
                                <div>
                                    <span class="d-block small text-muted text-uppercase fw-semibold">Situação</span>
                                    <?php if ($status_pedido === 'Cancelado'): ?>
                                        <span class="badge bg-danger">✕ Cancelado</span>
                                    <?php elseif ($status_entrega === 'Recebido pelo cliente'): ?>
                                        <span class="badge bg-success">✓ Recebido</span>
                                    <?php elseif ($status_pedido === 'Entregue'): ?>
                                        <span class="badge bg-success">✓ Entregue</span>
                                    <?php else: ?>
                                        <span class="badge bg-<?= $cor_status; ?>"><?= htmlspecialchars($status_pedido); ?></span>
                                        <?php if ($status_entrega === 'Não recebido'): ?>
                                            <span class="badge bg-danger">Entrega em verificação</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button class="btn btn-outline-dark btn-sm fw-semibold text-uppercase" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#pedido<?= $pedido_id; ?>">
                                        Ver Detalhes
                                    </button>
                                </div>
                            </div>

                            <div id="pedido<?= $pedido_id; ?>" class="collapse" data-bs-parent="#accordionPedidos">
                                <div class="card-body p-4 bg-white border-top">

                                    <div class="row g-3 mb-4 bg-light p-3 rounded border">
                                        <div class="col-md-4">
                                            <span class="d-block small text-muted fw-semibold">Código de Rastreio:</span>
                                            <?php if ($rastreio !== ''): ?>
                                                <span class="fw-bold text-dark font-monospace"><?= htmlspecialchars($rastreio); ?></span>
                                                <div class="mt-1">
                                                    <button type="button" class="btn btn-warning btn-sm fw-semibold"
                                                            data-rastreio="<?= htmlspecialchars($rastreio); ?>" onclick="rastrearCorreios(this)">
                                                        📦 Rastrear nos Correios
                                                    </button>
                                                    <small class="d-block text-success mt-1 aviso-rastreio"></small>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">Aguardando postagem!</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="d-block small text-muted fw-semibold">Prazo de Entrega:</span>
                                            <span class="fw-bold text-dark"><?= $data_prevista !== '' ? 'Até ' . $data_prevista : 'A definir!'; ?></span>
                                        </div>
                                        <div class="col-md-4">
                                            <span class="d-block small text-muted fw-semibold">Nota Fiscal:</span>
                                            <?php if ($nota_fiscal !== '' || $nf_chave !== ''): ?>
                                                <?php if ($nota_fiscal !== ''): ?><span class="fw-bold text-dark">Nº <?= htmlspecialchars($nota_fiscal); ?></span><?php endif; ?>
                                                <?php if ($nf_chave !== ''): ?>
                                                    <small class="d-block text-muted mt-1">Chave de acesso:</small>
                                                    <small class="d-block font-monospace text-break"><?= htmlspecialchars(trim(chunk_split($nf_chave, 4, ' '))); ?></small>
                                                    <button type="button" class="btn btn-outline-dark btn-sm mt-1"
                                                            data-chave="<?= htmlspecialchars($nf_chave); ?>" onclick="consultarNotaFiscal(this)">
                                                        🧾 Consultar Nota Fiscal
                                                    </button>
                                                    <small class="d-block text-success mt-1 aviso-nf"></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Será emitida no envio!</span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($status_pedido === 'Cancelado'): ?>
                                            <div class="col-12 border-top pt-3">
                                                <div class="alert alert-danger small mb-0">
                                                    <strong>✕ Este pedido foi cancelado!</strong> Em caso de dúvidas, fale com a loja pelo WhatsApp!
                                                </div>
                                            </div>
                                        <?php elseif ($status_entrega === 'Não recebido'): ?>
                                            <div class="col-12 border-top pt-3">
                                                <div class="alert alert-danger small mb-0">
                                                    <strong>⚠️ Você informou que não recebeu este pedido<?= !empty($pedido['data_relato_entrega']) ? ' em ' . date('d/m/Y H:i', strtotime($pedido['data_relato_entrega'])) : ''; ?>!</strong><br>
                                                    <span class="text-dark">Seu relato: “<?= htmlspecialchars((string)$pedido['relato_entrega']); ?>”</span><br>
                                                    A loja já foi avisada e vai verificar a entrega!
                                                </div>
                                            </div>
                                        <?php elseif ($status_entrega === 'Problema resolvido'): ?>
                                            <div class="col-12 border-top pt-3">
                                                <div class="alert alert-success small mb-0">
                                                    <strong>✓ A loja resolveu a sua solicitação de entrega!</strong>
                                                    <?php if (!empty($pedido['resposta_entrega'])): ?>
                                                        <div class="text-dark mt-2 p-2 bg-white rounded border" style="white-space: pre-line;"><?= htmlspecialchars((string)$pedido['resposta_entrega']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php elseif ($status_entrega === 'Recebido pelo cliente'): ?>
                                            <div class="col-12 border-top pt-3">
                                                <span class="badge bg-success">✓ Você confirmou o recebimento deste pedido!</span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($status_entrega !== 'Recebido pelo cliente' && $status_entrega !== 'Não recebido' && $status_pedido !== 'Cancelado'): ?>
                                            <div class="col-12 border-top pt-3">
                                                <span class="d-block small fw-bold text-dark mb-2">A sua encomenda foi entregue?</span>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <form action="index.php?pagina=minhas-compras" method="POST">
                                                        <input type="hidden" name="pedido_id" value="<?= (int)$pedido_id; ?>">
                                                        <button type="submit" name="acao_entrega" value="entregue" class="btn btn-success btn-sm fw-semibold">
                                                            ✓ Sim, Recebi
                                                        </button>
                                                    </form>
                                                    <button type="button" class="btn btn-danger btn-sm fw-semibold"
                                                            data-bs-toggle="collapse" data-bs-target="#naoRecebi<?= (int)$pedido_id; ?>">
                                                        ✕ Não Recebi / Problema
                                                    </button>
                                                </div>
                                                <div class="collapse mt-2" id="naoRecebi<?= (int)$pedido_id; ?>">
                                                    <form action="index.php?pagina=minhas-compras" method="POST" class="border border-danger rounded p-3 bg-white">
                                                        <input type="hidden" name="pedido_id" value="<?= (int)$pedido_id; ?>">
                                                        <input type="hidden" name="acao_entrega" value="nao_entregue">
                                                        <label class="form-label small fw-semibold" for="relato<?= (int)$pedido_id; ?>">Conte o que aconteceu (opcional):</label>
                                                        <textarea name="relato_entrega" id="relato<?= (int)$pedido_id; ?>" class="form-control form-control-sm mb-2" rows="2" maxlength="1000"
                                                                  placeholder="Ex.: o rastreio diz entregue, mas não recebi; o pacote chegou violado..."></textarea>
                                                        <button type="submit" class="btn btn-danger btn-sm fw-semibold">Enviar aviso para a loja</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <h6 class="fw-bold text-uppercase text-dark mb-3">Itens Comprados</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Produto</th>
                                                    <th class="text-center">Qtd</th>
                                                    <th class="text-end">Preço Unit.</th>
                                                    <th class="text-end">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $itens = json_decode($itens_json, true);
                                                if (!empty($itens) && is_array($itens)) {
                                                    foreach ($itens as $item) {
                                                        if (!is_array($item))
                                                            continue;

                                                        $nome_prod = (string) ($item['nome'] ?? $item['nome_produto'] ?? 'Produto');
                                                        $qtd = max(1, (int) ($item['quantidade'] ?? 1));
                                                        $preco = (float) ($item['preco'] ?? $item['preco_unitario'] ?? 0);
                                                        $cod_prod = (int) ($item['id'] ?? $item['id_produto'] ?? 0);
                                                        $subtotal_item = $qtd * $preco;

                                                        $detalhe_cod = $cod_prod > 0 ? "<br><small class='text-muted'>Cód. {$cod_prod}</small>" : "";
                                                        $var_txt = trim(trim((string)($item['cor'] ?? '')) . ' / ' . trim((string)($item['tamanho'] ?? '')), ' /');
                                                        if ($var_txt !== '') $detalhe_cod .= "<br><small class='text-muted'>" . htmlspecialchars($var_txt) . "</small>";

                                                        echo "<tr>
                                                            <td>" . htmlspecialchars($nome_prod) . $detalhe_cod . "</td>
                                                            <td class='text-center'>{$qtd}</td>
                                                            <td class='text-end'>R$ " . number_format($preco, 2, ',', '.') . "</td>
                                                            <td class='text-end fw-semibold'>R$ " . number_format($subtotal_item, 2, ',', '.') . "</td>
                                                        </tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='4' class='text-center text-muted'>Nenhum detalhe de item encontrado para este pedido!</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php
$stmt_pedidos->close();
?>

<script>
function copiarTexto(texto) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(texto).catch(() => copiarTextoAntigo(texto));
    }
    copiarTextoAntigo(texto);
    return Promise.resolve();
}
function copiarTextoAntigo(texto) {
    const t = document.createElement('textarea');
    t.value = texto; t.style.position = 'fixed'; t.style.opacity = '0';
    document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove();
}

function rastrearCorreios(botao) {
    const codigo = botao.dataset.rastreio;
    const aviso = botao.parentElement.querySelector('.aviso-rastreio');
    copiarTexto(codigo).then(() => {
        aviso.textContent = 'Código ' + codigo + ' copiado! Cole na busca dos Correios!';
    });
    window.open('https://rastreamento.correios.com.br/app/index.php', '_blank', 'noopener');
}

function consultarNotaFiscal(botao) {
    const chave = botao.dataset.chave;
    const aviso = botao.parentElement.querySelector('.aviso-nf');
    copiarTexto(chave).then(() => {
        aviso.textContent = 'Chave copiada! No portal, escolha "Consultar NF-e" e cole a chave!';
    });
    window.open('https://www.nfe.fazenda.gov.br/portal/principal.aspx', '_blank', 'noopener');
}
</script>