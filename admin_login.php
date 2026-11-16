<?php
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/funcoes_admin.php';

iniciar_sessao_segura();
enviar_headers_seguranca();
garantirTabelaUsuariosAdmin($conn);

if (!empty($_SESSION['admin_logado'])) {
    header('Location: admin.php');
    exit;
}

const LOGIN_MAX_FALHAS    = 5;
const LOGIN_JANELA_MINUTOS = 15;

$conn->query("CREATE TABLE IF NOT EXISTS admin_login_tentativas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_data (ip, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("DELETE FROM admin_login_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)");

$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; // não confie em X-Forwarded-For sem proxy conhecido
$erro = '';
if (!empty($_GET['expirou'])) {
    $erro = 'Sua sessão expirou por inatividade. Entre novamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $janela = LOGIN_JANELA_MINUTOS;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM admin_login_tentativas WHERE ip = ? AND criado_em > (NOW() - INTERVAL ? MINUTE)");
    $stmt->bind_param("si", $ip, $janela);
    $stmt->execute();
    $stmt->bind_result($falhas);
    $stmt->fetch();
    $stmt->close();

    $usuario = trim((string)($_POST['usuario'] ?? ''));
    $senha   = (string)($_POST['senha'] ?? '');

    if (!csrf_confere()) {
        $erro = 'Sessão inválida. Recarregue a página e tente novamente.';
    } elseif ($falhas >= LOGIN_MAX_FALHAS) {
        $erro = 'Muitas tentativas incorretas. Aguarde ' . LOGIN_JANELA_MINUTOS . ' minutos e tente de novo.';
    } else {
        $login = normalizarUsuarioAdmin($usuario);
        $conta = null;
        if ($login !== '') {
            $stmt = $conn->prepare("SELECT id, senha_hash, ativo, sessao_versao FROM admin_usuarios WHERE usuario = ? LIMIT 1");
            $stmt->bind_param("s", $login);
            $stmt->execute();
            $conta = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
        $hash_teste = $conta['senha_hash'] ?? '$2y$10$d4SlNB06sTfQ995lyoVibe8ag/WGJzswwwnGLpGzsdxNQvPSmAL8S';
        $senha_ok = password_verify($senha, $hash_teste);

        if ($conta && $senha_ok && !empty($conta['ativo'])) {
            $stmt = $conn->prepare("DELETE FROM admin_login_tentativas WHERE ip = ?");
            $stmt->bind_param("s", $ip);
            $stmt->execute();
            $stmt->close();

            session_regenerate_id(true);
            unset($_SESSION['csrf']);
            $_SESSION['admin_logado']        = true;
            $_SESSION['admin_id']            = (int)$conta['id'];
            $_SESSION['admin_sessao_versao'] = (int)$conta['sessao_versao'];
            $_SESSION['ultima_atividade']    = time();

            $stmt = $conn->prepare("UPDATE admin_usuarios SET ultimo_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $conta['id']);
            $stmt->execute();
            $stmt->close();

            header('Location: admin.php');
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO admin_login_tentativas (ip) VALUES (?)");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->close();

        sleep(1);
        $erro = ($conta && $senha_ok && empty($conta['ativo']))
            ? 'Este usuário está desativado. Fale com o administrador.'
            : 'Usuário ou senha incorretos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Login Administrativo - SolaSol Ateliê</title>
    <link rel="icon" type="image/jpeg" href="/solasolAtelie/imagens/iconeSolasol.jpeg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5" style="max-width: 400px;">
        <div class="card shadow border-0 p-4">
            <div class="text-center mb-4">
                <img src="/solasolAtelie/imagens/logoSolasolPreto.jpeg" alt="SolaSol Ateliê" style="max-height: 50px; width: auto;">
                <h6 class="fw-bold mt-3 mb-0 text-uppercase">Área Administrativa</h6>
            </div>
            <?php if ($erro): ?>
                <div class="alert alert-danger py-2 small"><?= htmlspecialchars($erro); ?></div>
            <?php endif; ?>
            <form method="POST" autocomplete="on">
                <?= campo_csrf(); ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Usuário</label>
                    <input type="text" name="usuario" class="form-control" autocomplete="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Senha</label>
                    <input type="password" name="senha" class="form-control" autocomplete="current-password" required>
                </div>
                <button class="btn btn-dark w-100 text-uppercase fw-bold">Entrar</button>
            </form>
            <a href="index.php" class="btn btn-link btn-sm text-muted mt-2 text-decoration-none">← Voltar à loja</a>
        </div>
    </div>
</body>
</html>
