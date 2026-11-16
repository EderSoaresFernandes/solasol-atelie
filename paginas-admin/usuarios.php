<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

$gerencia = adminGerenciaUsuarios($ADMIN_ATUAL);
$meu_id   = (int)$ADMIN_ATUAL['id'];
$erros_u  = [];

/** Recarrega a página depois de salvar (evita reenviar o formulário com F5 e atualiza o nome no topo). */
function voltarUsuarios(string $ok): void
{
    echo "<script>window.location.href='admin.php?aba=usuarios&ok=" . rawurlencode($ok) . "';</script>";
    exit;
}

/** Permissões marcadas no formulário (só as que existem). */
function permissoesDoFormulario(): string
{
    $marcadas = is_array($_POST['permissoes'] ?? null) ? $_POST['permissoes'] : [];
    return implode(',', array_values(array_intersect(array_keys(PERMISSOES_ADMIN), $marcadas)));
}

function loginEmUso(mysqli $conn, string $usuario, int $exceto_id): bool
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_usuarios WHERE usuario = ? AND id <> ?");
    $stmt->bind_param("si", $usuario, $exceto_id);
    $stmt->execute();
    $stmt->bind_result($n);
    $stmt->fetch();
    $stmt->close();
    return $n > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_usuario'])) {
    $acao = (string)$_POST['acao_usuario'];

    if ($acao === 'meu_perfil') {
        $nome    = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 80);
        $usuario = normalizarUsuarioAdmin((string)($_POST['usuario'] ?? ''));
        $atual   = (string)($_POST['senha_atual'] ?? '');
        $nova    = (string)($_POST['nova_senha'] ?? '');
        $conf    = (string)($_POST['nova_senha_conf'] ?? '');

        if (!password_verify($atual, $ADMIN_ATUAL['senha_hash'])) $erros_u[] = 'Senha atual incorreta.';
        if ($nome === '') $erros_u[] = 'Informe o nome.';
        if ($usuario === '') $erros_u[] = 'Usuário de acesso inválido: use de 3 a 30 letras minúsculas, números, ponto, hífen ou sublinhado.';
        elseif (loginEmUso($conn, $usuario, $meu_id)) $erros_u[] = 'Este usuário de acesso já está em uso.';
        if ($nova !== '' || $conf !== '') {
            if ($e = erroSenhaAdmin($nova)) $erros_u[] = $e;
            elseif ($nova !== $conf) $erros_u[] = 'A confirmação da nova senha não confere.';
        }

        if (!$erros_u) {
            if ($nova !== '') {
                $hash = password_hash($nova, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admin_usuarios SET nome = ?, usuario = ?, senha_hash = ?, sessao_versao = sessao_versao + 1 WHERE id = ?");
                $stmt->bind_param("sssi", $nome, $usuario, $hash, $meu_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['admin_sessao_versao'] = (int)$ADMIN_ATUAL['sessao_versao'] + 1;
            } else {
                $stmt = $conn->prepare("UPDATE admin_usuarios SET nome = ?, usuario = ? WHERE id = ?");
                $stmt->bind_param("ssi", $nome, $usuario, $meu_id);
                $stmt->execute();
                $stmt->close();
            }
            voltarUsuarios($nova !== '' ? 'perfil_senha' : 'perfil');
        }
    }

    elseif (!$gerencia) {
        $erros_u[] = 'Você não tem permissão para gerenciar usuários.';
    }

    elseif ($acao === 'criar') {
        $nome    = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 80);
        $usuario = normalizarUsuarioAdmin((string)($_POST['usuario'] ?? ''));
        $senha   = (string)($_POST['senha'] ?? '');
        $conf    = (string)($_POST['senha_conf'] ?? '');
        $perms   = permissoesDoFormulario();

        if ($nome === '') $erros_u[] = 'Informe o nome do novo usuário.';
        if ($usuario === '') $erros_u[] = 'Usuário de acesso inválido: use de 3 a 30 letras minúsculas, números, ponto, hífen ou sublinhado.';
        elseif (loginEmUso($conn, $usuario, 0)) $erros_u[] = 'Este usuário de acesso já está em uso.';
        if ($e = erroSenhaAdmin($senha)) $erros_u[] = $e;
        elseif ($senha !== $conf) $erros_u[] = 'A confirmação da senha não confere.';
        if ($perms === '') $erros_u[] = 'Marque ao menos uma área que o usuário poderá acessar.';

        if (!$erros_u) {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin_usuarios (usuario, nome, senha_hash, permissoes) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $usuario, $nome, $hash, $perms);
            $stmt->execute();
            $stmt->close();
            voltarUsuarios('criado');
        }
    }

    elseif ($acao === 'editar' || $acao === 'excluir') {
        $alvo = buscarAdminPorId($conn, (int)($_POST['id_usuario'] ?? 0));
        if (!$alvo) {
            $erros_u[] = 'Usuário não encontrado.';
        } elseif ((int)$alvo['id'] === $meu_id) {
            $erros_u[] = 'Para alterar os seus próprios dados, use "Meu usuário" no topo da página.';
        } elseif (!empty($alvo['principal'])) {
            $erros_u[] = 'O Administrador principal só pode ser alterado por ele mesmo.';
        } elseif ($acao === 'excluir') {
            $stmt = $conn->prepare("DELETE FROM admin_usuarios WHERE id = ? AND principal = 0");
            $stmt->bind_param("i", $alvo['id']);
            $stmt->execute();
            $stmt->close();
            voltarUsuarios('excluido');
        } else {
            $nome    = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 80);
            $usuario = normalizarUsuarioAdmin((string)($_POST['usuario'] ?? ''));
            $perms   = permissoesDoFormulario();
            $ativo   = !empty($_POST['ativo']) ? 1 : 0;
            $nova    = (string)($_POST['nova_senha'] ?? '');

            if ($nome === '') $erros_u[] = 'Informe o nome.';
            if ($usuario === '') $erros_u[] = 'Usuário de acesso inválido: use de 3 a 30 letras minúsculas, números, ponto, hífen ou sublinhado.';
            elseif (loginEmUso($conn, $usuario, (int)$alvo['id'])) $erros_u[] = 'Este usuário de acesso já está em uso.';
            if ($perms === '') $erros_u[] = 'Marque ao menos uma área que o usuário poderá acessar.';
            if ($nova !== '' && ($e = erroSenhaAdmin($nova))) $erros_u[] = $e;

            if (!$erros_u) {
                $desconectar = ($nova !== '' || ($ativo === 0 && !empty($alvo['ativo']))) ? 1 : 0;
                $stmt = $conn->prepare("UPDATE admin_usuarios SET nome = ?, usuario = ?, permissoes = ?, ativo = ?, sessao_versao = sessao_versao + ? WHERE id = ?");
                $stmt->bind_param("sssiii", $nome, $usuario, $perms, $ativo, $desconectar, $alvo['id']);
                $stmt->execute();
                $stmt->close();
                if ($nova !== '') {
                    $hash = password_hash($nova, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admin_usuarios SET senha_hash = ? WHERE id = ?");
                    $stmt->bind_param("si", $hash, $alvo['id']);
                    $stmt->execute();
                    $stmt->close();
                }
                voltarUsuarios('editado');
            }
        }
    }
}

$msgs_ok = [
    'perfil'       => 'Seus dados foram atualizados.',
    'perfil_senha' => 'Seus dados e sua senha foram atualizados. Use a nova senha no próximo login.',
    'criado'       => 'Novo usuário criado. Passe o usuário e a senha para a pessoa que vai usar.',
    'editado'      => 'Usuário atualizado.',
    'excluido'     => 'Usuário excluído.',
];
$ok = (string)($_GET['ok'] ?? '');

$usuarios = $gerencia ? $conn->query("SELECT * FROM admin_usuarios ORDER BY principal DESC, nome")->fetch_all(MYSQLI_ASSOC) : [];
$h = fn($v) => htmlspecialchars((string)$v);

/** Caixas de seleção das permissões (marcadas conforme $marcadas). */
$caixasPermissao = function (array $marcadas, string $prefixo) use ($h): string {
    $html = '<div class="row g-1">';
    foreach (PERMISSOES_ADMIN as $chave => $rotulo) {
        $id = $prefixo . '_' . $chave;
        $html .= '<div class="col-md-6"><div class="form-check">'
               . '<input class="form-check-input" type="checkbox" name="permissoes[]" value="' . $h($chave) . '" id="' . $h($id) . '"'
               . (in_array($chave, $marcadas, true) ? ' checked' : '') . '>'
               . '<label class="form-check-label small" for="' . $h($id) . '">' . $h($rotulo) . '</label></div></div>';
    }
    return $html . '</div>';
};
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="fw-bold text-dark mb-0">👤 Usuários do Painel</h5>
</div>
<hr class="mb-4">

<?php if (isset($msgs_ok[$ok])): ?>
    <div class="alert alert-success"><?= $h($msgs_ok[$ok]); ?></div>
<?php endif; ?>
<?php if ($erros_u): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($erros_u as $e): ?><li><?= $h($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card border-0 bg-light p-3 mb-4">
    <h6 class="fw-bold mb-1">Meu usuário</h6>
    <p class="small text-muted mb-3">
        <?= !empty($ADMIN_ATUAL['principal']) ? 'Você é o <strong>Administrador principal</strong>: tem acesso a todas as áreas do painel.' : 'Altere aqui o seu nome, o seu usuário de acesso e a sua senha.'; ?>
    </p>
    <form method="POST" action="admin.php?aba=usuarios" class="row g-2" autocomplete="off">
        <?= campo_csrf(); ?>
        <input type="hidden" name="acao_usuario" value="meu_perfil">
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Nome</label>
            <input type="text" name="nome" class="form-control form-control-sm" required maxlength="80" value="<?= $h($ADMIN_ATUAL['nome']); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Usuário de acesso (login)</label>
            <input type="text" name="usuario" class="form-control form-control-sm" required maxlength="30" pattern="[a-zA-Z0-9._\-]{3,30}" value="<?= $h($ADMIN_ATUAL['usuario']); ?>">
        </div>
        <div class="col-md-4"></div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Nova senha <span class="text-muted fw-normal">(deixe em branco para manter)</span></label>
            <input type="password" name="nova_senha" class="form-control form-control-sm" autocomplete="new-password" minlength="8">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Confirmar nova senha</label>
            <input type="password" name="nova_senha_conf" class="form-control form-control-sm" autocomplete="new-password" minlength="8">
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold">Senha atual <span class="text-danger">*</span></label>
            <input type="password" name="senha_atual" class="form-control form-control-sm" required autocomplete="current-password">
        </div>
        <div class="col-12 d-flex flex-wrap align-items-center gap-2">
            <button class="btn btn-dark btn-sm fw-semibold">Salvar meus dados</button>
            <small class="text-muted">Senha: mínimo de 8 caracteres, com letras e números.</small>
        </div>
    </form>
</div>

<?php if ($gerencia): ?>
    <div class="card border p-3 mb-4">
        <h6 class="fw-bold mb-3">➕ Criar novo usuário</h6>
        <form method="POST" action="admin.php?aba=usuarios" class="row g-2" autocomplete="off">
            <?= campo_csrf(); ?>
            <input type="hidden" name="acao_usuario" value="criar">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Nome</label>
                <input type="text" name="nome" class="form-control form-control-sm" required maxlength="80" placeholder="Ex.: Ana Paula"
                       value="<?= ($_POST['acao_usuario'] ?? '') === 'criar' ? $h($_POST['nome'] ?? '') : ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Usuário de acesso (login)</label>
                <input type="text" name="usuario" class="form-control form-control-sm" required maxlength="30" pattern="[a-zA-Z0-9._\-]{3,30}" placeholder="Ex.: ana.paula"
                       value="<?= ($_POST['acao_usuario'] ?? '') === 'criar' ? $h($_POST['usuario'] ?? '') : ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Senha</label>
                <input type="password" name="senha" class="form-control form-control-sm" required minlength="8" autocomplete="new-password">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Confirmar senha</label>
                <input type="password" name="senha_conf" class="form-control form-control-sm" required minlength="8" autocomplete="new-password">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold mb-1">Áreas que este usuário poderá acessar:</label>
                <?= $caixasPermissao(($_POST['acao_usuario'] ?? '') === 'criar' && is_array($_POST['permissoes'] ?? null) ? $_POST['permissoes'] : [], 'novo'); ?>
            </div>
            <div class="col-12">
                <button class="btn btn-success btn-sm fw-semibold">Criar usuário</button>
                <small class="text-muted ms-2">Login: 3 a 30 caracteres (letras, números, ponto, hífen ou sublinhado). Senha: mínimo de 8, com letras e números.</small>
            </div>
        </form>
    </div>

    <h6 class="fw-bold mb-2">Usuários cadastrados (<?= count($usuarios); ?>)</h6>
    <div class="table-responsive">
        <table class="tabela-cartoes-admin table table-sm align-middle small">
            <thead class="table-light text-uppercase">
                <tr><th>Nome</th><th>Login</th><th>Acesso liberado</th><th>Situação</th><th>Último acesso</th><th class="text-end">Ações</th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u):
                $uid = (int)$u['id'];
                $perms_u = permissoesDoAdmin($u);
                $eu = ($uid === $meu_id);
            ?>
                <tr class="<?= empty($u['ativo']) ? 'table-secondary' : ''; ?>">
                    <td class="fw-semibold"><?= $h($u['nome']); ?><?= $eu ? ' <span class="badge bg-info text-dark">você</span>' : ''; ?></td>
                    <td class="font-monospace"><?= $h($u['usuario']); ?></td>
                    <td>
                        <?php if (!empty($u['principal'])): ?>
                            <span class="badge bg-dark">Administrador principal (acesso total)</span>
                        <?php else: foreach ($perms_u as $p): ?>
                            <span class="badge bg-light text-dark border"><?= $h(PERMISSOES_ADMIN[$p]); ?></span>
                        <?php endforeach; endif; ?>
                    </td>
                    <td><?= !empty($u['ativo']) ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Desativado</span>'; ?></td>
                    <td><?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : '<span class="text-muted">nunca</span>'; ?></td>
                    <td class="text-end">
                        <?php if ($eu): ?>
                            <span class="text-muted">use "Meu usuário"</span>
                        <?php elseif (!empty($u['principal'])): ?>
                            <span class="text-muted">só ele mesmo altera</span>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="collapse" data-bs-target="#editarUsuario<?= $uid; ?>">Editar</button>
                            <form method="POST" action="admin.php?aba=usuarios" class="d-inline"
                                  data-msg="<?= $h('Excluir o usuário ' . $u['nome'] . ' (' . $u['usuario'] . ')? Ele perderá o acesso ao painel.'); ?>"
                                  onsubmit="return confirm(this.dataset.msg);">
                                <?= campo_csrf(); ?>
                                <input type="hidden" name="acao_usuario" value="excluir">
                                <input type="hidden" name="id_usuario" value="<?= $uid; ?>">
                                <button class="btn btn-outline-danger btn-sm">Excluir</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if (!$eu && empty($u['principal'])): ?>
                <tr class="collapse" id="editarUsuario<?= $uid; ?>">
                    <td colspan="6" class="bg-light">
                        <form method="POST" action="admin.php?aba=usuarios" class="row g-2 p-2" autocomplete="off">
                            <?= campo_csrf(); ?>
                            <input type="hidden" name="acao_usuario" value="editar">
                            <input type="hidden" name="id_usuario" value="<?= $uid; ?>">
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Nome</label>
                                <input type="text" name="nome" class="form-control form-control-sm" required maxlength="80" value="<?= $h($u['nome']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Usuário de acesso (login)</label>
                                <input type="text" name="usuario" class="form-control form-control-sm" required maxlength="30" pattern="[a-zA-Z0-9._\-]{3,30}" value="<?= $h($u['usuario']); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Nova senha <span class="text-muted fw-normal">(em branco = manter)</span></label>
                                <input type="password" name="nova_senha" class="form-control form-control-sm" minlength="8" autocomplete="new-password">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="ativo" value="1" id="ativo<?= $uid; ?>" <?= !empty($u['ativo']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="ativo<?= $uid; ?>">Acesso ativo</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1">Áreas liberadas:</label>
                                <?= $caixasPermissao($perms_u, 'u' . $uid); ?>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-dark btn-sm fw-semibold">Salvar alterações</button>
                                <small class="text-muted ms-2">Trocar a senha ou desativar desconecta o usuário na hora.</small>
                            </div>
                        </form>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
