<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

$mensagem = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_frete'])) {
    $min_gratis = max(0, floatval($_POST['valor_minimo_frete_gratis'] ?? 0));
    $est    = max(0, floatval($_POST['dentro_estado'] ?? 0));
    $sul    = max(0, floatval($_POST['regiao_sul'] ?? 0));
    $sudest = max(0, floatval($_POST['regiao_sudeste'] ?? 0));
    $co     = max(0, floatval($_POST['regiao_centro_oeste'] ?? 0));
    $ne     = max(0, floatval($_POST['regiao_nordeste'] ?? 0));
    $norte  = max(0, floatval($_POST['regiao_norte'] ?? 0));

    $p_est    = max(0, intval($_POST['prazo_estado'] ?? 3));
    $p_sul    = max(0, intval($_POST['prazo_sul'] ?? 5));
    $p_sudest = max(0, intval($_POST['prazo_sudeste'] ?? 6));
    $p_co     = max(0, intval($_POST['prazo_centro_oeste'] ?? 7));
    $p_ne     = max(0, intval($_POST['prazo_nordeste'] ?? 10));
    $p_norte  = max(0, intval($_POST['prazo_norte'] ?? 12));

    $stmt_f = $conn->prepare("UPDATE configuracoes_frete SET valor_minimo_frete_gratis=?, dentro_estado=?, regiao_sul=?, regiao_sudeste=?, regiao_centro_oeste=?, regiao_nordeste=?, regiao_norte=?, prazo_estado=?, prazo_sul=?, prazo_sudeste=?, prazo_centro_oeste=?, prazo_nordeste=?, prazo_norte=? WHERE id=1");
    $stmt_f->bind_param("dddddddiiiiii", $min_gratis, $est, $sul, $sudest, $co, $ne, $norte, $p_est, $p_sul, $p_sudest, $p_co, $p_ne, $p_norte);
    $stmt_f->execute();
    $stmt_f->close();

    $mensagem = "<div class='alert alert-success'>Regras de frete e prazos atualizados com sucesso!</div>";
}

$res_cfg = $conn->query("SELECT * FROM configuracoes_frete WHERE id=1");
$cfg_frete = $res_cfg ? $res_cfg->fetch_assoc() : null;
if (!$cfg_frete) {
    $cfg_frete = [
        'valor_minimo_frete_gratis' => 199.90,
        'dentro_estado' => 15.00, 'prazo_estado' => 3,
        'regiao_sul' => 20.00, 'prazo_sul' => 5,
        'regiao_sudeste' => 25.00, 'prazo_sudeste' => 6,
        'regiao_centro_oeste' => 35.00, 'prazo_centro_oeste' => 7,
        'regiao_nordeste' => 45.00, 'prazo_nordeste' => 10,
        'regiao_norte' => 50.00, 'prazo_norte' => 12
    ];
}
?>

<?= $mensagem; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">⚙️ Configurações de Frete, Prazos e Regiões</h5>
    <a href="admin.php?aba=produtos" class="btn btn-outline-custom btn-sm d-none d-lg-inline-block">+ Cadastrar Produto</a>
</div>
<hr class="mb-4">

<form action="admin.php?aba=frete" method="POST">
    <?= campo_csrf(); ?>
    <input type="hidden" name="salvar_frete" value="1">
    <div class="mb-3">
        <label class="form-label small fw-semibold">Valor Mínimo para Frete Grátis (R$)</label>
        <input type="number" step="0.01" min="0" name="valor_minimo_frete_gratis" class="form-control" value="<?= htmlspecialchars($cfg_frete['valor_minimo_frete_gratis']); ?>" required>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Dentro do Estado (PR) - Valor (R$)</label><input type="number" step="0.01" min="0" name="dentro_estado" class="form-control" value="<?= htmlspecialchars($cfg_frete['dentro_estado']); ?>" required></div>
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Dentro do Estado (PR) - Prazo (Dias úteis)</label><input type="number" min="0" name="prazo_estado" class="form-control" value="<?= htmlspecialchars($cfg_frete['prazo_estado'] ?? 3); ?>" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Sul - Valor (R$)</label><input type="number" step="0.01" min="0" name="regiao_sul" class="form-control" value="<?= htmlspecialchars($cfg_frete['regiao_sul']); ?>" required></div>
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Sul - Prazo (Dias úteis)</label><input type="number" min="0" name="prazo_sul" class="form-control" value="<?= htmlspecialchars($cfg_frete['prazo_sul'] ?? 5); ?>" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Sudeste - Valor (R$)</label><input type="number" step="0.01" min="0" name="regiao_sudeste" class="form-control" value="<?= htmlspecialchars($cfg_frete['regiao_sudeste']); ?>" required></div>
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Sudeste - Prazo (Dias úteis)</label><input type="number" min="0" name="prazo_sudeste" class="form-control" value="<?= htmlspecialchars($cfg_frete['prazo_sudeste'] ?? 6); ?>" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Centro-Oeste - Valor (R$)</label><input type="number" step="0.01" min="0" name="regiao_centro_oeste" class="form-control" value="<?= htmlspecialchars($cfg_frete['regiao_centro_oeste']); ?>" required></div>
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Centro-Oeste - Prazo (Dias úteis)</label><input type="number" min="0" name="prazo_centro_oeste" class="form-control" value="<?= htmlspecialchars($cfg_frete['prazo_centro_oeste'] ?? 7); ?>" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Nordeste - Valor (R$)</label><input type="number" step="0.01" min="0" name="regiao_nordeste" class="form-control" value="<?= htmlspecialchars($cfg_frete['regiao_nordeste']); ?>" required></div>
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Nordeste - Prazo (Dias úteis)</label><input type="number" min="0" name="prazo_nordeste" class="form-control" value="<?= htmlspecialchars($cfg_frete['prazo_nordeste'] ?? 10); ?>" required></div>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Norte - Valor (R$)</label><input type="number" step="0.01" min="0" name="regiao_norte" class="form-control" value="<?= htmlspecialchars($cfg_frete['regiao_norte']); ?>" required></div>
        <div class="col-md-6 mb-3"><label class="form-label small fw-semibold">Região Norte - Prazo (Dias úteis)</label><input type="number" min="0" name="prazo_norte" class="form-control" value="<?= htmlspecialchars($cfg_frete['prazo_norte'] ?? 12); ?>" required></div>
    </div>
    <button type="submit" class="btn btn-dark w-100 py-2 text-uppercase fw-bold mt-3">Salvar Regras de Frete e Prazos</button>
</form>
