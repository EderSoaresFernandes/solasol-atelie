<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

$mensagem = "";

$vista = (($_GET['vista'] ?? '') === 'respondidas') ? 'respondidas' : 'pendentes';
const SQL_DUVIDA_RESPONDIDA = "(d.resposta IS NOT NULL AND TRIM(d.resposta) <> '')";

/**
 * Mensagem do WhatsApp para a resposta de uma dúvida, em duas partes: o texto ANTES e DEPOIS da resposta.
 * (O botão usa as mesmas partes no navegador, então o texto é sempre igual nos dois lugares.)
 */
if (!function_exists('partesMensagemDuvida')) {
    function partesMensagemDuvida(string $nome_cliente, string $nome_produto): array
    {
        $primeiro = trim(strtok(trim($nome_cliente), ' ') ?: '');
        $primeiro = $primeiro !== '' ? mb_convert_case(mb_strtolower($primeiro), MB_CASE_TITLE) : 'cliente';
        $produto  = trim($nome_produto) !== '' ? trim($nome_produto) : 'nossa peça';
        $inicio = "Olá {$primeiro}!😊\n"
                . "Ficamos muito felizes pelo seu interesse no produto {$produto}!!!\n\n"
                . "Quanto à sua dúvida: ";
        $fim    = "\n\nAgradecemos seu contato e aguardamos você em nosso site para explorar o universo do nosso Ateliê!💛\n\n"
                . "Atenciosamente, Equipe SolaSol Ateliê!!!";
        return [$inicio, $fim];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['responder_duvida'])) {
    $duvida_id = intval($_POST['duvida_id'] ?? 0);
    $resposta  = trim((string)($_POST['resposta'] ?? ''));

    $stmt_resp = $conn->prepare("UPDATE produto_duvidas SET resposta = ? WHERE id_duvida = ?");
    $stmt_resp->bind_param("si", $resposta, $duvida_id);
    $stmt_resp->execute();
    $stmt_resp->close();

    $stmt_w = $conn->prepare("SELECT d.whatsapp_cliente, d.nome_cliente, p.nome AS nome_produto
                                FROM produto_duvidas d LEFT JOIN produto p ON d.produto_id = p.id_produto
                               WHERE d.id_duvida = ?");
    $stmt_w->bind_param("i", $duvida_id);
    $stmt_w->execute();
    $linha_w = $stmt_w->get_result()->fetch_assoc();
    $stmt_w->close();
    $whatsapp = preg_replace('/\D/', '', (string)($linha_w['whatsapp_cliente'] ?? ''));

    if (strlen($whatsapp) >= 12 && strpos($whatsapp, '55') === 0) $whatsapp = substr($whatsapp, 2);
    $mensagem = "<div class='alert alert-success'>Dúvida respondida com sucesso!"
              . ($vista === 'pendentes' ? " Ela foi movida para <a href='admin.php?aba=duvidas&vista=respondidas' class='alert-link'>Mensagens Respondidas</a>." : '');
    if (in_array(strlen($whatsapp), [10, 11], true)) {
        [$ini_msg, $fim_msg] = partesMensagemDuvida((string)($linha_w['nome_cliente'] ?? ''), (string)($linha_w['nome_produto'] ?? ''));
        $url_zap = 'https://api.whatsapp.com/send?phone=55' . $whatsapp . '&text=' . rawurlencode($ini_msg . $resposta . $fim_msg);
        $mensagem .= " <a href='" . htmlspecialchars($url_zap, ENT_QUOTES) . "' target='_blank' rel='noopener' class='btn btn-success btn-sm ms-2'>Abrir WhatsApp do cliente novamente</a>";
    }
    $mensagem .= "</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_duvida'])) {
    $duvida_id = intval($_POST['excluir_duvida']);
    $stmt_exc = $conn->prepare("DELETE FROM produto_duvidas WHERE id_duvida = ?");
    $stmt_exc->bind_param("i", $duvida_id);
    $stmt_exc->execute();
    $stmt_exc->close();
    echo "<script>window.location.href='admin.php?aba=duvidas&vista={$vista}';</script>";
    exit;
}
?>

<?= $mensagem; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">💬 Gerenciamento de Dúvidas dos Clientes</h5>
    <a href="admin.php?aba=produtos" class="btn btn-outline-custom btn-sm d-none d-lg-inline-block">+ Cadastrar Produto</a>
</div>
<hr class="mb-3">

<?php
$cont_duv = $conn->query("SELECT SUM(" . SQL_DUVIDA_RESPONDIDA . ") AS resp, SUM(NOT " . SQL_DUVIDA_RESPONDIDA . ") AS pend FROM produto_duvidas d")->fetch_assoc();
$total_respondidas = (int)($cont_duv['resp'] ?? 0);
$total_pend_duv    = (int)($cont_duv['pend'] ?? 0);

$filtro_duv = ($vista === 'respondidas') ? SQL_DUVIDA_RESPONDIDA : "NOT " . SQL_DUVIDA_RESPONDIDA;
$ordem_duv = ($vista === 'respondidas') ? 'd.id_duvida DESC' : 'd.id_duvida ASC';
$sql_duv_admin = "SELECT d.*, p.nome AS nome_produto, p.imagem_url FROM produto_duvidas d LEFT JOIN produto p ON d.produto_id = p.id_produto
                  WHERE {$filtro_duv} ORDER BY {$ordem_duv}";
$res_duv_admin = $conn->query($sql_duv_admin);
?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <?php if ($vista === 'pendentes'): ?>
        <h6 class="fw-bold mb-0 me-auto">⏳ Dúvidas Pendentes (<?= $total_pend_duv; ?>)</h6>
        <a href="admin.php?aba=duvidas&vista=respondidas" class="btn btn-success btn-sm fw-semibold">✓ Mensagens Respondidas (<?= $total_respondidas; ?>)</a>
    <?php else: ?>
        <h6 class="fw-bold mb-0 me-auto text-success">✓ Mensagens Respondidas (<?= $total_respondidas; ?>)</h6>
        <a href="admin.php?aba=duvidas" class="btn btn-dark btn-sm fw-semibold">⏳ Voltar às Dúvidas Pendentes (<?= $total_pend_duv; ?>)</a>
    <?php endif; ?>
</div>

<?php if ($res_duv_admin && $res_duv_admin->num_rows > 0): ?>
    <div class="table-responsive">
        <table class="tabela-cartoes-admin table align-middle">
            <thead class="table-light small text-uppercase">
                <tr>
                    <th>Produto</th>
                    <th>Cliente / WhatsApp</th>
                    <th>Pergunta</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php while($d = $res_duv_admin->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?php if(!empty($d['imagem_url'])): ?>
                                    <img src="<?= htmlspecialchars($d['imagem_url']); ?>" style="width: 40px; height: 50px; object-fit: cover;" class="rounded border">
                                <?php endif; ?>
                                <span class="fw-semibold small text-dark"><?= htmlspecialchars($d['nome_produto'] ?? 'Produto Removido'); ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="fw-bold small"><?= htmlspecialchars($d['nome_cliente'] ?? 'Cliente'); ?></span><br>
                            <small class="text-muted"><?= htmlspecialchars($d['whatsapp_cliente'] ?? 'Não informado'); ?></small>
                        </td>
                        <td>
                            <p class="mb-1 small"><strong>P:</strong> <?= htmlspecialchars($d['pergunta']); ?></p>
                            <?php if(!empty($d['resposta'])): ?>
                                <p class="mb-0 small text-success"><strong>R:</strong> <?= htmlspecialchars($d['resposta']); ?></p>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark small">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-dark btn-sm mb-1" data-bs-toggle="modal" data-bs-target="#modalResponder<?= (int)$d['id_duvida']; ?>"><?= $vista === 'respondidas' ? 'Editar resposta' : 'Responder'; ?></button>
                            <form method="POST" action="admin.php?aba=duvidas&vista=<?= $vista; ?>" class="d-inline" onsubmit="return confirm('Deseja realmente excluir esta dúvida?');">
                                <?= campo_csrf(); ?>
                                <input type="hidden" name="excluir_duvida" value="<?= (int)$d['id_duvida']; ?>">
                                <button class="btn btn-outline-danger btn-sm mb-1">Excluir</button>
                            </form>

                            <div class="modal fade text-start" id="modalResponder<?= (int)$d['id_duvida']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <?php
                                            $zap_duv = preg_replace('/\D/', '', (string)($d['whatsapp_cliente'] ?? ''));
                                            if (strlen($zap_duv) >= 12 && strpos($zap_duv, '55') === 0) $zap_duv = substr($zap_duv, 2);
                                            if (!in_array(strlen($zap_duv), [10, 11], true)) $zap_duv = '';
                                        ?>
                                        <?php [$ini_msg, $fim_msg] = partesMensagemDuvida((string)($d['nome_cliente'] ?? ''), (string)($d['nome_produto'] ?? '')); ?>
                                        <form action="admin.php?aba=duvidas&vista=<?= $vista; ?>" method="POST" class="form-responder-duvida" data-fone="<?= htmlspecialchars($zap_duv); ?>"
                                              data-inicio="<?= htmlspecialchars($ini_msg); ?>" data-fim="<?= htmlspecialchars($fim_msg); ?>">
                                            <?= campo_csrf(); ?>
                                            <input type="hidden" name="responder_duvida" value="1">
                                            <input type="hidden" name="duvida_id" value="<?= (int)$d['id_duvida']; ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title fs-6 fw-bold">Responder Dúvida</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p class="small text-muted mb-2"><strong>Pergunta de <?= htmlspecialchars($d['nome_cliente']); ?>:</strong> <?= htmlspecialchars($d['pergunta']); ?></p>
                                                <div class="mb-3">
                                                    <label class="form-label small fw-semibold">Sua Resposta:</label>
                                                    <textarea name="resposta" class="form-control" rows="3" required><?= htmlspecialchars($d['resposta'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Fechar</button>
                                                <button type="submit" class="btn btn-dark btn-sm">Salvar e Enviar no WhatsApp</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted text-center py-4">
        <?= $vista === 'respondidas' ? 'Nenhuma dúvida respondida até o momento.' : 'Nenhuma dúvida pendente. Tudo respondido! 🎉'; ?>
    </p>
<?php endif; ?>

<script>
document.querySelectorAll('form.form-responder-duvida').forEach(function (form) {
    form.addEventListener('submit', function () {
        const fone = form.dataset.fone;
        if (!fone) return;
        const texto = form.dataset.inicio + form.resposta.value.trim() + form.dataset.fim;
        window.open('https://api.whatsapp.com/send?phone=55' + fone + '&text=' + encodeURIComponent(texto), '_blank', 'noopener');
    });
});
</script>
