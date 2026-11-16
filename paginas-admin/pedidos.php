<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }
require_once __DIR__ . '/../funcoes_estoque.php';

$status_validos = ['Pendente', 'Pago / Aprovado', 'Enviado', 'Entregue', 'Abandonado', 'Cancelado'];

$mensagem = "";
$estoque_msg_erro = "Não foi possível atualizar o pedido. Tente novamente.";

$vista = (($_GET['vista'] ?? '') === 'finalizados') ? 'finalizados' : 'pendentes';
const SQL_FINALIZADO = "((COALESCE(status_pedido, '') IN ('Entregue', 'Cancelado') OR COALESCE(status_entrega, '') = 'Recebido pelo cliente')
                         AND COALESCE(status_entrega, '') <> 'Não recebido')";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status_pedido'])) {
    $id_pedido   = intval($_POST['id_pedido'] ?? 0);
    $novo_status = trim((string)($_POST['status_pedido'] ?? ''));

    if (in_array($novo_status, $status_validos, true)) {
        try {
            garantirColunaEstoqueBaixado($conn);
            $conn->begin_transaction();

            $stmt_p = $conn->prepare("SELECT estoque_baixado, itens_json FROM pedidos WHERE id_pedido = ? FOR UPDATE");
            $stmt_p->bind_param("i", $id_pedido);
            $stmt_p->execute();
            $ped_atual = $stmt_p->get_result()->fetch_assoc();
            $stmt_p->close();
            if (!$ped_atual) throw new EstoqueException("Pedido não encontrado.");

            $flag  = (int)$ped_atual['estoque_baixado'];
            $itens = json_decode((string)$ped_atual['itens_json'], true);
            if (!is_array($itens)) $itens = [];

            $libera = in_array($novo_status, ['Cancelado', 'Abandonado'], true);
            $nota = "";
            if ($libera && $flag === 1) {
                devolverEstoque($conn, $itens);
                $flag = 2;
                $nota = " Estoque devolvido.";
            } elseif (!$libera && $flag === 2) {
                baixarEstoque($conn, $itens);
                $flag = 1;
                $nota = " Estoque baixado novamente.";
            }

            $stmt_st = $conn->prepare("UPDATE pedidos SET status_pedido = ?, estoque_baixado = ?,
                    data_resolucao_entrega = IF(status_entrega = 'Não recebido', NOW(), data_resolucao_entrega),
                    status_entrega = IF(status_entrega = 'Não recebido', 'Problema resolvido', status_entrega)
                WHERE id_pedido = ?");
            $stmt_st->bind_param("sii", $novo_status, $flag, $id_pedido);
            $stmt_st->execute();
            $stmt_st->close();

            $conn->commit();
            $mudou_lista = '';
            if ($vista === 'pendentes' && in_array($novo_status, ['Entregue', 'Cancelado'], true)) {
                $mudou_lista = " O pedido foi movido para <a href='admin.php?aba=pedidos&vista=finalizados' class='alert-link'>Pedidos Finalizados</a>.";
            } elseif ($vista === 'finalizados' && !in_array($novo_status, ['Entregue', 'Cancelado'], true)) {
                $mudou_lista = " O pedido voltou para <a href='admin.php?aba=pedidos' class='alert-link'>Pedidos Pendentes</a>.";
            }
            $mensagem = "<div class='alert alert-success'>Status do pedido #{$id_pedido} atualizado com sucesso!{$nota}{$mudou_lista}</div>";
        } catch (EstoqueException $e) {
            $conn->rollback();
            $mensagem = "<div class='alert alert-danger'>" . htmlspecialchars($e->getMessage()) . " O status do pedido #{$id_pedido} não foi alterado.</div>";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('pedidos.php status: ' . $e->getMessage());
            $mensagem = "<div class='alert alert-danger'>" . htmlspecialchars($estoque_msg_erro) . "</div>";
        }
    } else {
        $mensagem = "<div class='alert alert-danger'>Status inválido.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_entrega'])) {
    $id_pedido = intval($_POST['id_pedido'] ?? 0);
    $rastreio  = strtoupper(preg_replace('/\s+/', '', (string)($_POST['codigo_rastreio'] ?? '')));
    $rastreio  = mb_substr($rastreio, 0, 40);
    $nf        = mb_substr(trim((string)($_POST['nota_fiscal'] ?? '')), 0, 60);
    $nf_chave  = preg_replace('/\D/', '', (string)($_POST['nota_fiscal_chave'] ?? ''));
    $prazo_txt = trim((string)($_POST['data_prevista'] ?? ''));
    $erros_ent = [];

    $prazo = null;
    if ($prazo_txt !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $prazo_txt);
        if ($d && $d->format('Y-m-d') === $prazo_txt) $prazo = $prazo_txt;
        else $erros_ent[] = 'Prazo de entrega inválido.';
    }
    if ($nf_chave !== '' && strlen($nf_chave) !== 44) $erros_ent[] = 'A chave de acesso da nota fiscal deve ter 44 números.';
    if ($rastreio !== '' && !preg_match('/^[A-Z0-9]{8,40}$/', $rastreio)) $erros_ent[] = 'Código de rastreio inválido (use só letras e números).';

    if ($erros_ent) {
        $mensagem = "<div class='alert alert-danger'>Pedido #{$id_pedido}: " . htmlspecialchars(implode(' ', $erros_ent)) . "</div>";
    } else {
        $rastreio_db = $rastreio !== '' ? $rastreio : null;
        $nf_db       = $nf !== '' ? $nf : null;
        $chave_db    = $nf_chave !== '' ? $nf_chave : null;
        $stmt_e = $conn->prepare("UPDATE pedidos SET codigo_rastreio = ?, data_prevista = ?, nota_fiscal = ?, nota_fiscal_chave = ? WHERE id_pedido = ?");
        $stmt_e->bind_param("ssssi", $rastreio_db, $prazo, $nf_db, $chave_db, $id_pedido);
        $stmt_e->execute();
        $stmt_e->close();

        $aviso_formato = ($rastreio !== '' && !preg_match('/^[A-Z]{2}\d{9}[A-Z]{2}$/', $rastreio))
            ? " <small>(Atenção: o código não está no formato dos Correios, ex.: AA123456789BR.)</small>" : '';
        $mensagem = "<div class='alert alert-success'>Dados de entrega do pedido #{$id_pedido} salvos! O cliente já pode ver em \"Minhas Compras\".{$aviso_formato}</div>";
    }
}

/** Link do WhatsApp do cliente com a resposta da loja (telefone lido do BANCO e reduzido a dígitos). '' se inválido. */
if (!function_exists('linkWhatsappEntrega')) {
    function linkWhatsappEntrega(mysqli $conn, int $id_pedido, string $resposta): string
    {
        $stmt = $conn->prepare("SELECT telefone_cliente, nome_cliente FROM pedidos WHERE id_pedido = ?");
        $stmt->bind_param("i", $id_pedido);
        $stmt->execute();
        $cli = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $fone = whatsappDoCliente((string)($cli['telefone_cliente'] ?? ''));
        if ($fone === '') return '';
        return 'https://api.whatsapp.com/send?phone=55' . $fone . '&text=' . rawurlencode($resposta);
    }

    /** Só dígitos, sem o 55 do país; precisa ter DDD + número (10 ou 11 dígitos). */
    function whatsappDoCliente(string $telefone): string
    {
        $fone = preg_replace('/\D/', '', $telefone);
        if (strlen($fone) >= 12 && strpos($fone, '55') === 0) $fone = substr($fone, 2);
        return in_array(strlen($fone), [10, 11], true) ? $fone : '';
    }

    /** Mensagem automática de resolução da entrega (o nome entra no lugar de "(nome do cliente)"). */
    function mensagemResolucaoPadrao(string $nome_completo): string
    {
        $primeiro_nome = trim(strtok(trim($nome_completo), ' ') ?: '');
        $primeiro_nome = $primeiro_nome !== '' ? mb_convert_case(mb_strtolower($primeiro_nome), MB_CASE_TITLE) : 'cliente';
        return "Olá {$primeiro_nome}! Tudo bem? 😊\n\n"
             . "Estamos entrando em contato para lhe informar que conseguimos resolver a questão referente à entrega do seu produto. "
             . "Pedimos desculpas pelo transtorno e agradecemos muito pela sua compreensão e paciência.\n\n"
             . "Acompanhe a reprogramação da sua entrega no seus Painel Minhas Compras em nosso site!\n\n"
             . "Ficamos felizes em poder solucionar tudo e seguimos à disposição! 💛\n\n"
             . "Atenciosamente, Equipe SolaSol Ateliê!";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolver_entrega'])) {
    $id_pedido = intval($_POST['id_pedido'] ?? 0);
    $resposta  = mb_substr(trim((string)($_POST['resposta_entrega'] ?? '')), 0, 1500);
    if ($resposta === '') {
        $stmt_n = $conn->prepare("SELECT nome_cliente FROM pedidos WHERE id_pedido = ?");
        $stmt_n->bind_param("i", $id_pedido);
        $stmt_n->execute();
        $resposta = mensagemResolucaoPadrao((string)($stmt_n->get_result()->fetch_assoc()['nome_cliente'] ?? ''));
        $stmt_n->close();
    }

    $stmt_r = $conn->prepare("UPDATE pedidos SET status_entrega = 'Problema resolvido', resposta_entrega = ?, data_resolucao_entrega = NOW() WHERE id_pedido = ?");
    $stmt_r->bind_param("si", $resposta, $id_pedido);
    $stmt_r->execute();
    $stmt_r->close();
    $mensagem = "<div class='alert alert-success'>Aviso de entrega do pedido #{$id_pedido} marcado como resolvido!";

    $url_zap = linkWhatsappEntrega($conn, $id_pedido, $resposta);
    if ($url_zap !== '') {
        $mensagem .= " <a href='" . htmlspecialchars($url_zap, ENT_QUOTES) . "' target='_blank' rel='noopener' class='btn btn-success btn-sm ms-2'>"
                   . "Abrir WhatsApp do cliente novamente</a>";
    } else {
        $mensagem .= " <strong>O telefone do cliente é inválido: avise-o por outro meio.</strong>";
    }
    $mensagem .= "</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_pedido'])) {
    $id_pedido = intval($_POST['excluir_pedido']);
    try {
        garantirColunaEstoqueBaixado($conn);
        $conn->begin_transaction();

        $stmt_p = $conn->prepare("SELECT estoque_baixado, itens_json FROM pedidos WHERE id_pedido = ? FOR UPDATE");
        $stmt_p->bind_param("i", $id_pedido);
        $stmt_p->execute();
        $ped_atual = $stmt_p->get_result()->fetch_assoc();
        $stmt_p->close();

        if ($ped_atual && (int)$ped_atual['estoque_baixado'] === 1) {
            $itens = json_decode((string)$ped_atual['itens_json'], true);
            if (is_array($itens)) devolverEstoque($conn, $itens);
        }

        $stmt_del = $conn->prepare("DELETE FROM pedidos WHERE id_pedido = ?");
        $stmt_del->bind_param("i", $id_pedido);
        $stmt_del->execute();
        $stmt_del->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('pedidos.php excluir: ' . $e->getMessage());
    }
    echo "<script>window.location.href='admin.php?aba=pedidos&vista={$vista}';</script>";
    exit;
}
?>

<?= $mensagem; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">🛍️ Gerenciamento de Vendas e Pedidos</h5>
    <a href="admin.php?aba=produtos" class="btn btn-outline-custom btn-sm d-none d-lg-inline-block">+ Cadastrar Produto</a>
</div>
<hr class="mb-3">

<?php
$filtro = ($vista === 'finalizados') ? SQL_FINALIZADO : "NOT " . SQL_FINALIZADO;
$cont = $conn->query("SELECT SUM(" . SQL_FINALIZADO . ") AS fin, SUM(NOT " . SQL_FINALIZADO . ") AS pend FROM pedidos")->fetch_assoc();
$total_finalizados = (int)($cont['fin'] ?? 0);
$total_pend_lista  = (int)($cont['pend'] ?? 0);

$res_pedidos = $conn->query("SELECT * FROM pedidos WHERE {$filtro} ORDER BY (status_entrega = 'Não recebido') DESC, id_pedido DESC");
$res_prob = $conn->query("SELECT id_pedido FROM pedidos WHERE status_entrega = 'Não recebido' ORDER BY id_pedido DESC");
$pedidos_com_problema = $res_prob ? array_column($res_prob->fetch_all(MYSQLI_ASSOC), 'id_pedido') : [];
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <?php if ($vista === 'pendentes'): ?>
        <h6 class="fw-bold mb-0 me-auto">⏳ Pedidos Pendentes (<?= $total_pend_lista; ?>)</h6>
        <a href="admin.php?aba=pedidos&vista=finalizados" class="btn btn-success btn-sm fw-semibold">✓ Pedidos Finalizados (<?= $total_finalizados; ?>)</a>
    <?php else: ?>
        <h6 class="fw-bold mb-0 me-auto text-success">✓ Pedidos Finalizados (<?= $total_finalizados; ?>)</h6>
        <a href="admin.php?aba=pedidos" class="btn btn-dark btn-sm fw-semibold">⏳ Voltar aos Pedidos Pendentes (<?= $total_pend_lista; ?>)</a>
    <?php endif; ?>
</div>

<?php if ($pedidos_com_problema): ?>
    <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2">
        <strong>⚠️ ATENÇÃO: <?= count($pedidos_com_problema); ?> pedido(s) com aviso de NÃO RECEBIDO pelo cliente!</strong>
        <span>Resolva:</span>
        <?php foreach ($pedidos_com_problema as $idp): ?>
            <a href="#pedido-<?= (int)$idp; ?>" class="badge bg-danger text-decoration-none">#<?= (int)$idp; ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($res_pedidos && $res_pedidos->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="tabela-cartoes-admin table align-middle small">
            <thead class="table-light text-uppercase">
                <tr>
                    <th>Pedido</th>
                    <th>Cliente / Contato</th>
                    <th>Endereço</th>
                    <th>Pagamento & Valores</th>
                    <th>Itens</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while($ped = $res_pedidos->fetch_assoc()): ?>
                    <?php
                        $itens_lista = json_decode((string)$ped['itens_json'], true); if (!is_array($itens_lista)) $itens_lista = [];
                        $idp = (int)$ped['id_pedido'];
                        $nao_recebido = ($ped['status_entrega'] ?? '') === 'Não recebido';
                        $classe_linha = $nao_recebido ? 'table-danger' : ($ped['status_pedido'] == 'Abandonado' ? 'table-warning' : '');
                    ?>

                    <?php if ($nao_recebido): ?>
                        <tr class="table-danger" id="pedido-<?= $idp; ?>">
                            <td colspan="7" class="border-danger border-2">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                    <div class="text-danger">
                                        <strong class="fs-6">⚠️ ATENÇÃO — Pedido #<?= $idp; ?>: o cliente informou que NÃO RECEBEU a compra!</strong><br>
                                        <small>Aviso enviado em <?= !empty($ped['data_relato_entrega']) ? date('d/m/Y H:i', strtotime($ped['data_relato_entrega'])) : 'data não registrada'; ?></small>
                                        <?php if (!empty($ped['data_resolucao_entrega']) && !empty($ped['data_relato_entrega'])
                                                  && strtotime($ped['data_resolucao_entrega']) < strtotime($ped['data_relato_entrega'])): ?>
                                            <br><span class="badge bg-warning text-dark">🔁 NOVO AVISO</span>
                                            <small class="text-dark">O aviso anterior deste pedido já tinha sido resolvido em <?= date('d/m/Y H:i', strtotime($ped['data_resolucao_entrega'])); ?>; o cliente avisou de novo depois disso.</small>
                                        <?php endif; ?>
                                        <div class="text-dark mt-1">Relato do cliente: “<?= htmlspecialchars((string)($ped['relato_entrega'] ?: 'Sem detalhes.')); ?>”</div>
                                    </div>
                                    <?php $fone_zap = whatsappDoCliente((string)$ped['telefone_cliente']); ?>
                                    <?php $msg_padrao = mensagemResolucaoPadrao((string)$ped['nome_cliente']); ?>
                                    <form method="POST" action="admin.php?aba=pedidos&vista=<?= $vista; ?>" class="d-flex flex-column gap-1 form-resolver-entrega" style="min-width: 340px; max-width: 460px;"
                                          data-fone="<?= htmlspecialchars($fone_zap); ?>"
                                          data-padrao="<?= htmlspecialchars($msg_padrao); ?>">
                                        <?= campo_csrf(); ?>
                                        <input type="hidden" name="resolver_entrega" value="1">
                                        <input type="hidden" name="id_pedido" value="<?= $idp; ?>">
                                        <label class="small fw-semibold text-dark mb-0">Mensagem ao cliente (já preenchida, pode editar):</label>
                                        <textarea name="resposta_entrega" class="form-control form-control-sm" rows="9" maxlength="1500"><?= htmlspecialchars($msg_padrao); ?></textarea>
                                        <button class="btn btn-success btn-sm fw-semibold">✓ Marcar como resolvido e avisar no WhatsApp</button>
                                        <?php if ($fone_zap === ''): ?>
                                            <small class="text-danger">Telefone do cliente inválido: a resposta fica só em "Minhas Compras".</small>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr class="<?= $classe_linha; ?>" <?= $nao_recebido ? '' : 'id="pedido-' . $idp . '"'; ?>>
                        <td>
                            <strong class="fs-6 text-dark">#<?= (int)$ped['id_pedido']; ?></strong>
                            <?php if (($ped['status_entrega'] ?? '') === 'Recebido pelo cliente'): ?>
                                <span class="badge bg-success">✓ Recebido</span>
                            <?php elseif ($ped['status_pedido'] === 'Cancelado'): ?>
                                <span class="badge bg-danger">Cancelado</span>
                            <?php elseif ($ped['status_pedido'] === 'Entregue'): ?>
                                <span class="badge bg-success">Entregue</span>
                            <?php endif; ?><br>
                            <span class="text-muted" style="font-size: 11px;"><?= date('d/m/Y H:i', strtotime($ped['data_criacao'])); ?></span>
                        </td>
                        <td>
                            <strong class="text-dark"><?= htmlspecialchars($ped['nome_cliente']); ?></strong><br>
                            <span><?= htmlspecialchars($ped['telefone_cliente']); ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($ped['email_cliente']); ?></small>
                            <?php if (!empty($ped['cpf_cnpj'])): ?><br><small class="text-muted">CPF/CNPJ: <?= htmlspecialchars($ped['cpf_cnpj']); ?></small><?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($ped['endereco']); ?>, <?= htmlspecialchars($ped['numero']); ?><br>
                            <?= htmlspecialchars($ped['bairro']); ?> - <?= htmlspecialchars($ped['cidade']); ?>/<?= htmlspecialchars($ped['estado']); ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($ped['forma_pagamento']); ?></span>
                            <?php if ($ped['forma_pagamento'] === 'cartao'): $np = max(1, (int)($ped['parcelas'] ?? 1)); ?>
                                <small class="text-muted"><?= $np; ?>x de R$ <?= number_format((float)$ped['total_geral'] / $np, 2, ',', '.'); ?> (simulado)</small>
                            <?php endif; ?><br>
                            <div class="mt-1">Total: <strong class="text-success fs-6">R$ <?= number_format($ped['total_geral'], 2, ',', '.'); ?></strong></div>
                            <small class="text-muted d-block">Frete: R$ <?= number_format((float)$ped['frete'], 2, ',', '.'); ?></small>
                            <?php if (!empty($ped['cupom_codigo'])): ?>
                                <small class="text-muted d-block">Cupom <?= htmlspecialchars($ped['cupom_codigo']); ?>: - R$ <?= number_format((float)$ped['desconto_cupom'], 2, ',', '.'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <ul class="list-unstyled mb-0" style="max-height: 100px; overflow-y: auto;">
                                <?php foreach($itens_lista as $it): if (!is_array($it)) continue; ?>
                                    <?php $var_txt = trim(trim((string)($it['cor'] ?? '')) . ' / ' . trim((string)($it['tamanho'] ?? '')), ' /'); ?>
                                    <li><strong>Cód. <?= (int)($it['id'] ?? $it['id_produto'] ?? 0); ?></strong> — <?= htmlspecialchars((string)($it['nome'] ?? 'Produto')); ?> (<?= (int)($it['quantidade'] ?? 1); ?>x)
                                        <?php if ($var_txt !== ''): ?><br><small class="text-muted">Cor/Tam: <?= htmlspecialchars($var_txt); ?></small><?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td>
                            <form action="admin.php?aba=pedidos&vista=<?= $vista; ?>" method="POST" class="d-inline">
                                <?= campo_csrf(); ?>
                                <input type="hidden" name="atualizar_status_pedido" value="1">
                                <input type="hidden" name="id_pedido" value="<?= (int)$ped['id_pedido']; ?>">
                                <select name="status_pedido" class="form-select form-select-sm mb-1" onchange="this.form.submit()">
                                    <option value="Pendente" <?= $ped['status_pedido'] == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="Pago / Aprovado" <?= $ped['status_pedido'] == 'Pago / Aprovado' ? 'selected' : ''; ?>>Pago / Aprovado</option>
                                    <option value="Enviado" <?= $ped['status_pedido'] == 'Enviado' ? 'selected' : ''; ?>>Enviado</option>
                                    <option value="Entregue" <?= $ped['status_pedido'] == 'Entregue' ? 'selected' : ''; ?>>Entregue</option>
                                    <option value="Abandonado" <?= $ped['status_pedido'] == 'Abandonado' ? 'selected' : ''; ?>>⚠️ Abandonado</option>
                                    <option value="Cancelado" <?= $ped['status_pedido'] == 'Cancelado' ? 'selected' : ''; ?>>Cancelado</option>
                                </select>
                            </form>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="admin.php?aba=pedidos&vista=<?= $vista; ?>" class="d-inline" onsubmit="return confirm('Deseja excluir este pedido?');">
                                <?= campo_csrf(); ?>
                                <input type="hidden" name="excluir_pedido" value="<?= (int)$ped['id_pedido']; ?>">
                                <button class="btn btn-outline-danger btn-sm">Excluir</button>
                            </form>
                        </td>
                    </tr>

                    <tr class="<?= $classe_linha; ?>">
                        <td colspan="7" class="pt-0 pb-3 border-bottom border-2">
                            <form method="POST" action="admin.php?aba=pedidos&vista=<?= $vista; ?>#pedido-<?= $idp; ?>" class="row g-2 align-items-end bg-light border rounded p-2 mx-0">
                                <?= campo_csrf(); ?>
                                <input type="hidden" name="salvar_entrega" value="1">
                                <input type="hidden" name="id_pedido" value="<?= $idp; ?>">
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-0">📦 Código de Rastreio</label>
                                    <input type="text" name="codigo_rastreio" class="form-control form-control-sm text-uppercase font-monospace" maxlength="40"
                                           placeholder="AA123456789BR" value="<?= htmlspecialchars((string)($ped['codigo_rastreio'] ?? '')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold mb-0">📅 Prazo Entrega</label>
                                    <input type="date" name="data_prevista" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($ped['data_prevista'] ?? '')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold mb-0">🧾 Nota Fiscal (nº)</label>
                                    <input type="text" name="nota_fiscal" class="form-control form-control-sm" maxlength="60"
                                           placeholder="Ex.: 000123" value="<?= htmlspecialchars((string)($ped['nota_fiscal'] ?? '')); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-0">Chave de acesso da NF-e (44 números, opcional)</label>
                                    <input type="text" name="nota_fiscal_chave" class="form-control form-control-sm font-monospace" maxlength="60" inputmode="numeric"
                                           value="<?= htmlspecialchars((string)($ped['nota_fiscal_chave'] ?? '')); ?>">
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button class="btn btn-dark btn-sm fw-semibold">Salvar entrega</button>
                                </div>
                                <?php $st_ent = (string)($ped['status_entrega'] ?: 'Pendente'); ?>
                                <div class="col-12 small text-muted">
                                    Confirmação do cliente:
                                    <?php if ($st_ent === 'Recebido pelo cliente'): ?><span class="badge bg-success">✓ Recebeu</span>
                                    <?php elseif ($st_ent === 'Não recebido'): ?><span class="badge bg-danger">✕ Não recebeu</span>
                                    <?php elseif ($st_ent === 'Problema resolvido'): ?><span class="badge bg-success">Problema resolvido</span>
                                        <?php if (!empty($ped['resposta_entrega'])): ?> — resposta: “<?= htmlspecialchars((string)$ped['resposta_entrega']); ?>”<?php endif; ?>
                                    <?php else: ?><span class="badge bg-secondary">Aguardando</span><?php endif; ?>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted text-center py-4">
        <?= $vista === 'finalizados' ? 'Nenhum pedido finalizado até o momento.' : 'Nenhum pedido pendente no momento. 🎉'; ?>
    </p>
<?php endif; ?>

<script>
document.querySelectorAll('form.form-resolver-entrega').forEach(function (form) {
    form.addEventListener('submit', function () {
        const fone = form.dataset.fone;
        if (!fone) return;
        const resposta = form.resposta_entrega.value.trim() || form.dataset.padrao;
        const url = 'https://api.whatsapp.com/send?phone=55' + fone + '&text=' + encodeURIComponent(resposta);
        window.open(url, '_blank', 'noopener');
    });
});
</script>