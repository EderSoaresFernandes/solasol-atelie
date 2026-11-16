<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

function redirecionarCupons() {
    echo "<script>window.location.href='admin.php?aba=cupons';</script>";
    exit;
}

/** Lê e valida o formulário de cupom (criar ou editar). Devolve [dados, mensagem de erro]. */
function lerFormularioCupom(array $post): array
{
    $d = [
        'codigo' => strtolower(preg_replace('/[^A-Za-z0-9_-]/', '', trim((string)($post['codigo'] ?? '')))),
        'tipo'   => ($post['tipo'] ?? '') === 'valor' ? 'valor' : 'percentual',
        'regra'  => ($post['regra'] ?? '') === 'primeira_compra' ? 'primeira_compra' : 'livre',
        'valor'  => floatval(str_replace(',', '.', (string)($post['valor'] ?? 0))),
        'minimo' => max(0, floatval(str_replace(',', '.', (string)($post['valor_minimo'] ?? 0)))),
        'limite' => ($post['limite_uso'] ?? '') !== '' ? max(1, intval($post['limite_uso'])) : null,
        'validade' => null,
    ];
    if ($d['tipo'] === 'percentual' && $d['valor'] > 100) $d['valor'] = 100;

    $v = $post['validade'] ?? '';
    if (is_string($v) && $v !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $v);
        if ($dt && $dt->format('Y-m-d') === $v) $d['validade'] = $v;
    }

    $erro = ($d['codigo'] === '' || $d['valor'] <= 0)
        ? 'Informe um código (letras, números, - ou _) e um valor maior que zero.'
        : '';
    return [$d, $erro];
}

$id_em_edicao = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cupom'])) {
    $acao = $_POST['acao_cupom'];

    if ($acao === 'criar' || $acao === 'editar') {
        [$d, $mensagem] = lerFormularioCupom($_POST);
        $idc = intval($_POST['id_cupom'] ?? 0);
        if ($acao === 'editar') $id_em_edicao = $idc;

        if ($mensagem === '') {
            try {
                if ($acao === 'criar') {
                    $stmt = $conn->prepare("INSERT INTO cupons (codigo, tipo, regra, valor, valor_minimo, validade, limite_uso) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssddsi", $d['codigo'], $d['tipo'], $d['regra'], $d['valor'], $d['minimo'], $d['validade'], $d['limite']);
                } else {
                    $stmt = $conn->prepare("UPDATE cupons SET codigo = ?, tipo = ?, regra = ?, valor = ?, valor_minimo = ?, validade = ?, limite_uso = ? WHERE id_cupom = ?");
                    $stmt->bind_param("sssddsii", $d['codigo'], $d['tipo'], $d['regra'], $d['valor'], $d['minimo'], $d['validade'], $d['limite'], $idc);
                }
                $stmt->execute();
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                error_log("Cupom ($acao): " . $e->getMessage());
                $mensagem = $acao === 'criar'
                    ? "Não foi possível criar o cupom (o código já existe?)."
                    : "Não foi possível salvar o cupom (o código já é usado por outro cupom?).";
            }
        }
        if ($mensagem === '') redirecionarCupons();
    } elseif ($acao === 'alternar') {
        $idc = intval($_POST['id_cupom'] ?? 0);
        $conn->query("UPDATE cupons SET ativo = 1 - ativo WHERE id_cupom = $idc");
        redirecionarCupons();
    } elseif ($acao === 'excluir') {
        $idc = intval($_POST['id_cupom'] ?? 0);
        $conn->query("DELETE FROM cupons WHERE id_cupom = $idc");
        redirecionarCupons();
    }
}

$cupons = $conn->query("SELECT * FROM cupons ORDER BY id_cupom DESC");

/** Campos do formulário de cupom, preenchidos com $c (vazio = novo cupom). */
$camposCupom = function (array $c = []): string {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES);
    $sel = fn($campo, $valor) => (($c[$campo] ?? '') === $valor) ? ' selected' : '';
    $num = fn($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',');
    ob_start(); ?>
    <div class="col-md-2"><label class="form-label small fw-semibold">Código</label>
        <input type="text" name="codigo" class="form-control form-control-sm text-uppercase" required maxlength="40" value="<?= $h($c['codigo'] ?? ''); ?>"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold">Regra de uso</label>
        <select name="regra" class="form-select form-select-sm">
            <option value="livre"<?= $sel('regra', 'livre'); ?>>Livre (só período de validade)</option>
            <option value="primeira_compra"<?= $sel('regra', 'primeira_compra'); ?>>Somente 1ª compra do cliente</option>
        </select></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Tipo</label>
        <select name="tipo" class="form-select form-select-sm">
            <option value="percentual"<?= $sel('tipo', 'percentual'); ?>>Percentual (%)</option>
            <option value="valor"<?= $sel('tipo', 'valor'); ?>>Valor fixo (R$)</option>
        </select></div>
    <div class="col-md-1"><label class="form-label small fw-semibold">Valor</label>
        <input type="text" name="valor" class="form-control form-control-sm" required value="<?= $h($num($c['valor'] ?? '')); ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Pedido mínimo (R$)</label>
        <input type="text" name="valor_minimo" class="form-control form-control-sm" value="<?= $h(isset($c['valor_minimo']) ? number_format((float)$c['valor_minimo'], 2, ',', '') : '0'); ?>"></div>
    <div class="col-md-2"><label class="form-label small fw-semibold">Validade</label>
        <input type="date" name="validade" class="form-control form-control-sm" value="<?= $h($c['validade'] ?? ''); ?>"></div>
    <div class="col-md-1"><label class="form-label small fw-semibold">Limite</label>
        <input type="number" name="limite_uso" min="1" class="form-control form-control-sm" placeholder="∞" value="<?= $h($c['limite_uso'] ?? ''); ?>"></div>
    <?php return ob_get_clean();
};
?>

<h5 class="fw-bold mb-3">Cupons de Desconto</h5>

<?php if (!empty($mensagem)): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($mensagem); ?></div>
<?php endif; ?>

<form method="POST" action="admin.php?aba=cupons" class="row g-2 align-items-end mb-4 p-3 bg-light rounded border">
    <?= campo_csrf(); ?>
    <input type="hidden" name="acao_cupom" value="criar">
    <?= $camposCupom(); ?>
    <div class="col-md-2"><button class="btn btn-dark btn-sm w-100 text-uppercase fw-semibold">Criar Cupom</button></div>
</form>

<div class="table-responsive">
    <table class="tabela-cartoes-admin table table-sm align-middle small">
        <thead class="table-light text-uppercase">
            <tr><th>Código</th><th>Desconto</th><th>Regra</th><th>Mínimo</th><th>Validade</th><th>Usos</th><th>Status</th><th class="text-end">Ações</th></tr>
        </thead>
        <tbody>
        <?php if ($cupons && $cupons->num_rows > 0): ?>
            <?php while ($c = $cupons->fetch_assoc()): ?>
                <?php $idc = (int)$c['id_cupom']; ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($c['codigo']); ?></td>
                    <td><?= $c['tipo'] === 'percentual'
                            ? rtrim(rtrim(number_format($c['valor'], 2, ',', ''), '0'), ',') . '%'
                            : 'R$ ' . number_format($c['valor'], 2, ',', '.'); ?></td>
                    <td><?= $c['regra'] === 'primeira_compra' ? '1ª compra' : 'Livre'; ?></td>
                    <td>R$ <?= number_format($c['valor_minimo'], 2, ',', '.'); ?></td>
                    <td><?= $c['validade'] ? date('d/m/Y', strtotime($c['validade'])) : 'Sem validade'; ?></td>
                    <td><?= (int)$c['usos']; ?><?= $c['limite_uso'] !== null ? ' / ' . (int)$c['limite_uso'] : ''; ?></td>
                    <td><span class="badge <?= $c['ativo'] ? 'bg-success' : 'bg-secondary'; ?>"><?= $c['ativo'] ? 'Ativo' : 'Inativo'; ?></span></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="collapse" data-bs-target="#editarCupom<?= $idc; ?>">Editar</button>
                        <form method="POST" action="admin.php?aba=cupons" class="d-inline">
                            <?= campo_csrf(); ?>
                            <input type="hidden" name="acao_cupom" value="alternar">
                            <input type="hidden" name="id_cupom" value="<?= $idc; ?>">
                            <button class="btn btn-outline-secondary btn-sm">Ativar/Desativar</button>
                        </form>
                        <form method="POST" action="admin.php?aba=cupons" class="d-inline" onsubmit="return confirm('Excluir este cupom?');">
                            <?= campo_csrf(); ?>
                            <input type="hidden" name="acao_cupom" value="excluir">
                            <input type="hidden" name="id_cupom" value="<?= $idc; ?>">
                            <button class="btn btn-outline-danger btn-sm">Excluir</button>
                        </form>
                    </td>
                </tr>
                <tr class="collapse <?= $id_em_edicao === $idc ? 'show' : ''; ?>" id="editarCupom<?= $idc; ?>">
                    <td colspan="8" class="bg-light p-3">
                        <form method="POST" action="admin.php?aba=cupons" class="row g-2 align-items-end">
                            <?= campo_csrf(); ?>
                            <input type="hidden" name="acao_cupom" value="editar">
                            <input type="hidden" name="id_cupom" value="<?= $idc; ?>">
                            <?= $camposCupom($id_em_edicao === $idc ? array_merge($c, $_POST) : $c); ?>
                            <div class="col-md-2"><button class="btn btn-dark btn-sm w-100 text-uppercase fw-semibold">Salvar alterações</button></div>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum cupom cadastrado ainda.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
