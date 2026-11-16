<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

garantirTabelaPromocoes($conn);
$erros_p = [];

function voltarPromocoes(string $ok): void
{
    echo "<script>window.location.href='admin.php?aba=promocoes&ok=" . rawurlencode($ok) . "';</script>";
    exit;
}

/** Lê e valida o formulário de campanha. Devolve [dados, erros]. */
function lerFormularioPromocao(): array
{
    $erros = [];
    $nome  = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 100);
    $tipo  = (($_POST['tipo'] ?? '') === 'valor') ? 'valor' : 'percentual';
    $valor = (float)str_replace(',', '.', (string)($_POST['valor'] ?? '0'));
    $minimo = max(0, (float)str_replace(',', '.', (string)($_POST['valor_minimo'] ?? '0')));

    $data = function ($campo) {
        $v = (string)($_POST[$campo] ?? '');
        $d = DateTime::createFromFormat('Y-m-d\TH:i', $v);
        return ($d && $d->format('Y-m-d\TH:i') === $v) ? $d->format('Y-m-d H:i:00') : null;
    };
    $inicio = $data('inicio');
    $fim    = $data('fim');

    $cats = is_array($_POST['categorias'] ?? null) ? array_values(array_intersect(array_keys(CATEGORIAS_PROMOCAO), $_POST['categorias'])) : [];
    $prods = is_array($_POST['produtos'] ?? null) ? array_values(array_unique(array_filter(array_map('intval', $_POST['produtos'])))) : [];

    if ($nome === '') $erros[] = 'Informe o nome da campanha.';
    if ($tipo === 'percentual' && ($valor < 1 || $valor > 90)) $erros[] = 'O desconto em % deve ser de 1 a 90.';
    if ($tipo === 'valor' && $valor <= 0) $erros[] = 'Informe o valor do desconto em R$.';
    if (!$inicio) $erros[] = 'Informe a data e a hora de início.';
    if (!$fim) $erros[] = 'Informe a data e a hora de fim.';
    if ($inicio && $fim && $fim <= $inicio) $erros[] = 'O fim da campanha precisa ser depois do início.';
    if (!$cats && !$prods) $erros[] = 'Escolha ao menos uma categoria ou um produto.';

    return [[
        'nome' => $nome, 'tipo' => $tipo, 'valor' => round($valor, 2), 'valor_minimo' => round($minimo, 2),
        'categorias' => implode(',', $cats), 'produtos' => implode(',', $prods),
        'inicio' => $inicio, 'fim' => $fim,
    ], $erros];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_promocao'])) {
    $acao = (string)$_POST['acao_promocao'];
    $idp  = (int)($_POST['id_promocao'] ?? 0);

    if ($acao === 'criar' || $acao === 'editar') {
        [$d, $erros_p] = lerFormularioPromocao();
        if (!$erros_p) {
            if ($acao === 'criar') {
                $st = $conn->prepare("INSERT INTO promocoes (nome, tipo, valor, valor_minimo, categorias, produtos, inicio, fim) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $st->bind_param("ssddssss", $d['nome'], $d['tipo'], $d['valor'], $d['valor_minimo'], $d['categorias'], $d['produtos'], $d['inicio'], $d['fim']);
            } else {
                $st = $conn->prepare("UPDATE promocoes SET nome = ?, tipo = ?, valor = ?, valor_minimo = ?, categorias = ?, produtos = ?, inicio = ?, fim = ? WHERE id = ?");
                $st->bind_param("ssddssssi", $d['nome'], $d['tipo'], $d['valor'], $d['valor_minimo'], $d['categorias'], $d['produtos'], $d['inicio'], $d['fim'], $idp);
            }
            $st->execute();
            $st->close();
            voltarPromocoes($acao === 'criar' ? 'criada' : 'editada');
        }
    } elseif ($acao === 'alternar') {
        $st = $conn->prepare("UPDATE promocoes SET ativo = 1 - ativo WHERE id = ?");
        $st->bind_param("i", $idp);
        $st->execute();
        $st->close();
        voltarPromocoes('alternada');
    } elseif ($acao === 'excluir') {
        $st = $conn->prepare("DELETE FROM promocoes WHERE id = ?");
        $st->bind_param("i", $idp);
        $st->execute();
        $st->close();
        voltarPromocoes('excluida');
    }
}

$msgs_ok = [
    'criada'    => 'Campanha criada! Ela entra no ar automaticamente na data de início.',
    'editada'   => 'Campanha atualizada!',
    'alternada' => 'Situação da campanha alterada!',
    'excluida'  => 'Campanha excluída.',
];
$ok = (string)($_GET['ok'] ?? '');

$campanhas = $conn->query("SELECT *, (inicio <= NOW()) AS comecou, (fim >= NOW()) AS vigente FROM promocoes ORDER BY ativo DESC, fim DESC")->fetch_all(MYSQLI_ASSOC);
$produtos  = $conn->query("SELECT id_produto, nome, categoria, imagem_url, preco_original, preco_pix FROM produto ORDER BY categoria, nome")->fetch_all(MYSQLI_ASSOC);
$h = fn($v) => htmlspecialchars((string)$v);

function situacaoCampanha(array $c): array
{
    if (empty($c['ativo']))   return ['Pausada', 'secondary'];
    if (empty($c['comecou'])) return ['Agendada', 'info'];
    if (empty($c['vigente'])) return ['Encerrada', 'dark'];
    return ['Ativa agora', 'success'];
}

/** Quantos produtos a campanha alcança. */
function produtosAlcancados(array $c, array $produtos): int
{
    $n = 0;
    foreach ($produtos as $p) if (promocaoValeParaProduto($c, $p)) $n++;
    return $n;
}

/** Formulário de campanha (criar ou editar). */
$formulario = function (array $c, string $acao) use ($produtos, $h): string {
    $catsSel  = array_filter(explode(',', (string)($c['categorias'] ?? '')));
    $prodsSel = array_map('intval', array_filter(explode(',', (string)($c['produtos'] ?? ''))));
    $dt = fn($v) => $v ? date('Y-m-d\TH:i', strtotime($v)) : '';
    $uid = $acao . (int)($c['id'] ?? 0);
    ob_start(); ?>
    <form method="POST" action="admin.php?aba=promocoes" class="row g-2 form-promocao" autocomplete="off">
        <?= campo_csrf(); ?>
        <input type="hidden" name="acao_promocao" value="<?= $h($acao); ?>">
        <input type="hidden" name="id_promocao" value="<?= (int)($c['id'] ?? 0); ?>">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Nome da campanha</label>
            <input type="text" name="nome" class="form-control form-control-sm" required maxlength="100" placeholder="Ex.: Liquida Verão" value="<?= $h($c['nome'] ?? ''); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Tipo de desconto</label>
            <select name="tipo" class="form-select form-select-sm">
                <option value="percentual" <?= ($c['tipo'] ?? '') !== 'valor' ? 'selected' : ''; ?>>% (percentual)</option>
                <option value="valor" <?= ($c['tipo'] ?? '') === 'valor' ? 'selected' : ''; ?>>R$ (valor fixo)</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Desconto</label>
            <input type="number" name="valor" class="form-control form-control-sm" required min="0.01" step="0.01" placeholder="Ex.: 20" value="<?= $h($c['valor'] ?? ''); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Início</label>
            <input type="datetime-local" name="inicio" class="form-control form-control-sm" required value="<?= $h($dt($c['inicio'] ?? '')); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small fw-semibold">Fim</label>
            <input type="datetime-local" name="fim" class="form-control form-control-sm" required value="<?= $h($dt($c['fim'] ?? '')); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Valor mínimo do produto (R$) <span class="text-muted fw-normal">— opcional</span></label>
            <input type="number" name="valor_minimo" class="form-control form-control-sm" min="0" step="0.01" placeholder="Ex.: 100,00 (vazio = todos os produtos)"
                   value="<?= (float)($c['valor_minimo'] ?? 0) > 0 ? $h($c['valor_minimo']) : ''; ?>">
            <div class="form-text">Só entram na promoção os produtos com preço a partir deste valor.</div>
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold mb-1">Aplicar em categorias inteiras:</label>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach (CATEGORIAS_PROMOCAO as $valorCat => $rotulo): $idc = 'cat_' . $uid . '_' . md5($valorCat); ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="categorias[]" value="<?= $h($valorCat); ?>" id="<?= $idc; ?>" <?= in_array($valorCat, $catsSel, true) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="<?= $idc; ?>"><?= $h($rotulo); ?> (todos os produtos)</label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label small fw-semibold mb-1">e/ou em produtos escolhidos:</label>
            <input type="text" class="form-control form-control-sm mb-2 filtro-produtos" placeholder="🔎 Filtrar por nome ou código...">
            <div class="lista-produtos-promo border rounded p-2 bg-white">
                <?php foreach ($produtos as $p): $idpr = 'prod_' . $uid . '_' . (int)$p['id_produto']; ?>
                    <label class="d-flex align-items-center gap-2 small py-1 item-produto-promo" for="<?= $idpr; ?>"
                           data-busca="<?= $h(mb_strtolower($p['nome'] . ' ' . $p['id_produto'])); ?>">
                        <input class="form-check-input m-0" type="checkbox" name="produtos[]" value="<?= (int)$p['id_produto']; ?>" id="<?= $idpr; ?>" <?= in_array((int)$p['id_produto'], $prodsSel, true) ? 'checked' : ''; ?>>
                        <img src="<?= $h($p['imagem_url'] ?: 'imagens/logoSolasolPreto.jpeg'); ?>" alt="" style="width: 28px; height: 34px; object-fit: cover;" class="rounded border">
                        <span class="fw-semibold">Cód. <?= (int)$p['id_produto']; ?></span>
                        <span><?= $h($p['nome']); ?></span>
                        <span class="badge bg-light text-dark border"><?= $h(CATEGORIAS_PROMOCAO[$p['categoria']] ?? $p['categoria']); ?></span>
                        <span class="ms-auto text-muted"><?= formatarPreco($p['preco_original']); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="col-12">
            <button class="btn btn-<?= $acao === 'criar' ? 'success' : 'dark'; ?> btn-sm fw-semibold"><?= $acao === 'criar' ? 'Criar campanha' : 'Salvar alterações'; ?></button>
            <small class="text-muted ms-2">Se um produto estiver em mais de uma campanha ao mesmo tempo, vale a de maior desconto para o cliente.</small>
        </div>
    </form>
    <?php return ob_get_clean();
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">🔥 Promoções</h5>
</div>
<hr class="mb-4">

<?php if (isset($msgs_ok[$ok])): ?><div class="alert alert-success"><?= $h($msgs_ok[$ok]); ?></div><?php endif; ?>
<?php if ($erros_p): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros_p as $e): ?><li><?= $h($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card border p-3 mb-4">
    <h6 class="fw-bold mb-3">➕ Nova campanha</h6>
    <?php
    $valoresNovo = [];
    if ($erros_p && ($_POST['acao_promocao'] ?? '') === 'criar') {
        $valoresNovo = [
            'nome' => $_POST['nome'] ?? '', 'tipo' => $_POST['tipo'] ?? '', 'valor' => $_POST['valor'] ?? '', 'valor_minimo' => $_POST['valor_minimo'] ?? '',
            'inicio' => str_replace('T', ' ', (string)($_POST['inicio'] ?? '')), 'fim' => str_replace('T', ' ', (string)($_POST['fim'] ?? '')),
            'categorias' => implode(',', (array)($_POST['categorias'] ?? [])), 'produtos' => implode(',', (array)($_POST['produtos'] ?? [])),
        ];
    }
    echo $formulario($valoresNovo, 'criar');
    ?>
</div>

<h6 class="fw-bold mb-2">Campanhas (<?= count($campanhas); ?>)</h6>
<div class="table-responsive">
    <table class="tabela-cartoes-admin table table-sm align-middle small">
        <thead class="table-light text-uppercase">
            <tr><th>Campanha</th><th>Desconto</th><th>Aplica em</th><th>Período</th><th>Situação</th><th class="text-end">Ações</th></tr>
        </thead>
        <tbody>
        <?php if (!$campanhas): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Nenhuma campanha criada ainda.</td></tr>
        <?php endif; ?>
        <?php foreach ($campanhas as $c):
            [$sit, $cor] = situacaoCampanha($c);
            $idc = (int)$c['id'];
            $cats = array_filter(explode(',', (string)$c['categorias']));
            $qtdProds = count(array_filter(explode(',', (string)$c['produtos'])));
        ?>
            <tr>
                <td class="fw-semibold"><?= $h($c['nome']); ?></td>
                <td class="fw-bold text-danger"><?= $c['tipo'] === 'valor' ? '- ' . formatarPreco($c['valor']) : '-' . rtrim(rtrim(number_format((float)$c['valor'], 2, ',', ''), '0'), ',') . '%'; ?></td>
                <td>
                    <?php foreach ($cats as $cat): ?><span class="badge bg-light text-dark border"><?= $h(CATEGORIAS_PROMOCAO[$cat] ?? $cat); ?> (todos)</span> <?php endforeach; ?>
                    <?php if ($qtdProds): ?><span class="badge bg-light text-dark border"><?= $qtdProds; ?> produto(s) escolhido(s)</span><?php endif; ?>
                    <?php if ((float)($c['valor_minimo'] ?? 0) > 0): ?><span class="badge bg-warning text-dark">Produtos a partir de <?= formatarPreco($c['valor_minimo']); ?></span><?php endif; ?>
                    <small class="d-block text-muted mt-1"><?= produtosAlcancados($c, $produtos); ?> produto(s) com desconto</small>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($c['inicio'])); ?><br><span class="text-muted">até</span> <?= date('d/m/Y H:i', strtotime($c['fim'])); ?></td>
                <td><span class="badge bg-<?= $cor; ?>"><?= $sit; ?></span></td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="collapse" data-bs-target="#editarPromo<?= $idc; ?>">Editar</button>
                    <form method="POST" action="admin.php?aba=promocoes" class="d-inline">
                        <?= campo_csrf(); ?>
                        <input type="hidden" name="acao_promocao" value="alternar">
                        <input type="hidden" name="id_promocao" value="<?= $idc; ?>">
                        <button class="btn btn-outline-secondary btn-sm"><?= !empty($c['ativo']) ? 'Pausar' : 'Reativar'; ?></button>
                    </form>
                    <form method="POST" action="admin.php?aba=promocoes" class="d-inline" data-msg="<?= $h('Excluir a campanha ' . $c['nome'] . '?'); ?>" onsubmit="return confirm(this.dataset.msg);">
                        <?= campo_csrf(); ?>
                        <input type="hidden" name="acao_promocao" value="excluir">
                        <input type="hidden" name="id_promocao" value="<?= $idc; ?>">
                        <button class="btn btn-outline-danger btn-sm">Excluir</button>
                    </form>
                </td>
            </tr>
            <tr class="collapse <?= ($erros_p && ($_POST['acao_promocao'] ?? '') === 'editar' && (int)($_POST['id_promocao'] ?? 0) === $idc) ? 'show' : ''; ?>" id="editarPromo<?= $idc; ?>">
                <td colspan="6" class="bg-light p-3"><?= $formulario($c, 'editar'); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.form-promocao').forEach(function (form) {
    const filtro = form.querySelector('.filtro-produtos');
    if (!filtro) return;
    filtro.addEventListener('input', function () {
        const termo = filtro.value.trim().toLowerCase();
        form.querySelectorAll('.item-produto-promo').forEach(function (item) {
            item.classList.toggle('d-none', termo !== '' && !item.dataset.busca.includes(termo));
        });
    });
});
</script>
