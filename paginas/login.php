<?php
date_default_timezone_set('America/Sao_Paulo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'conexao.php';
require_once __DIR__ . '/../funcoes_cliente.php';

$erro_login = "";
$sucesso_whatsapp_link = "";

$acao_tela = $_GET['recuperar'] ?? 'login';
if (!in_array($acao_tela, ['login', 'esqueci', 'enviar_whats'], true)) {
    $acao_tela = 'login';
}

$voltar_permitidos = ['checkout', 'sacola', 'minha-conta', 'minhas-compras'];
$voltar = (string)($_GET['voltar'] ?? '');
if (!in_array($voltar, $voltar_permitidos, true)) $voltar = '';
$destino_pos_login = $voltar !== '' ? 'index.php?pagina=' . $voltar : 'index.php';
$qs_voltar = $voltar !== '' ? '&voltar=' . $voltar : '';

const CLIENTE_LOGIN_MAX_FALHAS = 8;
const CLIENTE_LOGIN_JANELA_MIN = 15;
const CODIGO_MAX_TENTATIVAS    = 5;
$ip_cliente = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

function falhasLoginCliente(mysqli $conn, string $ip): int
{
    $janela = CLIENTE_LOGIN_JANELA_MIN;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM cliente_login_tentativas WHERE ip = ? AND criado_em > (NOW() - INTERVAL ? MINUTE)");
    $stmt->bind_param("si", $ip, $janela);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    return (int)$total;
}

function registrarFalhaLoginCliente(mysqli $conn, string $ip): void
{
    $stmt = $conn->prepare("INSERT INTO cliente_login_tentativas (ip) VALUES (?)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_login'])) {
    $identificador = trim((string)($_POST['identificador'] ?? ($_POST['email'] ?? '')));
    $senha = (string)($_POST['senha'] ?? '');

    $conn->query("DELETE FROM cliente_login_tentativas WHERE criado_em < (NOW() - INTERVAL 1 DAY)");
    $bloqueado = falhasLoginCliente($conn, $ip_cliente) >= CLIENTE_LOGIN_MAX_FALHAS;

    $candidatos = [];
    $colunas = "id_cliente, nome, email, whatsapp, senha";

    if (!$bloqueado && $identificador !== '') {
        if (strpos($identificador, '@') !== false) {
            $stmt = $conn->prepare("SELECT $colunas FROM clientes WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $identificador);
        } else {
            $digitos = somenteDigitos($identificador);
            if ($digitos !== '') {
                $doc = formatarDocumento($digitos);
                $zap = normalizarWhatsapp($digitos);
                $stmt = $conn->prepare("SELECT $colunas FROM clientes WHERE cpf_cnpj = ? OR whatsapp = ? LIMIT 5");
                $stmt->bind_param("ss", $doc, $zap);
            } else {
                $stmt = null;
            }
        }

        if ($stmt) {
            $stmt->execute();
            $res = $stmt->get_result();
            while ($linha = $res->fetch_assoc()) $candidatos[] = $linha;
            $stmt->close();
        }
    }

    if ($bloqueado) {
        $erro_login = "Muitas tentativas incorretas. Aguarde " . CLIENTE_LOGIN_JANELA_MIN . " minutos e tente de novo!";
    } else {
        $cliente_ok = null;
        foreach ($candidatos as $cand) {
            if (password_verify($senha, $cand['senha'])) { $cliente_ok = $cand; break; }
        }

        if ($cliente_ok) {
            iniciarSessaoCliente($cliente_ok['id_cliente'], $cliente_ok['nome'], $cliente_ok['email'], $cliente_ok['whatsapp']);
            redirecionarJs($destino_pos_login);
        } else {
            registrarFalhaLoginCliente($conn, $ip_cliente);
            $erro_login = "Dados de acesso incorretos. Confira o e-mail/CPF/WhatsApp e a senha!";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cadastro'])) {
    $nome     = trim($_POST['nome'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $whatsapp = normalizarWhatsapp($_POST['whatsapp'] ?? '');
    $senha    = $_POST['senha'] ?? '';

    if ($nome === '') {
        $erro_login = "Informe o seu nome!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro_login = "Informe um e-mail válido!";
    } elseif (!whatsappValido($whatsapp)) {
        $erro_login = "Informe o WhatsApp com DDD (10 ou 11 dígitos)!";
    } elseif (strlen($senha) < 6) {
        $erro_login = "A senha precisa ter no mínimo 6 caracteres!";
    } else {
        $stmt_check = $conn->prepare("SELECT email, whatsapp FROM clientes WHERE email = ? OR whatsapp = ? LIMIT 1");
        $stmt_check->bind_param("ss", $email, $whatsapp);
        $stmt_check->execute();
        $existente = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($existente) {
            $erro_login = (strcasecmp((string)$existente['email'], $email) === 0)
                ? "Este e-mail já está cadastrado. Faça login ao lado!"
                : "Este WhatsApp já está cadastrado em outra conta!";
        } else {
            try {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt_ins = $conn->prepare("INSERT INTO clientes (nome, email, whatsapp, senha) VALUES (?, ?, ?, ?)");
                $stmt_ins->bind_param("ssss", $nome, $email, $whatsapp, $senha_hash);
                $stmt_ins->execute();
                $novo_id = $stmt_ins->insert_id;
                $stmt_ins->close();

                iniciarSessaoCliente($novo_id, $nome, $email, $whatsapp);
                redirecionarJs($destino_pos_login, 'Cadastro realizado com sucesso! Bem-vindo(a), ' . $nome . '!');
            } catch (mysqli_sql_exception $e) {
                error_log('login.php cadastro: ' . $e->getMessage());
                $erro_login = ($e->getCode() == 1062)
                    ? "Este e-mail, WhatsApp ou documento já está cadastrado!"
                    : "Não foi possível concluir o cadastro. Tente novamente!";
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_recuperacao'])) {
    $whatsapp_digitado = normalizarWhatsapp($_POST['whatsapp_recuperacao'] ?? '');

    $stmt = $conn->prepare("SELECT id_cliente, nome, email FROM clientes WHERE whatsapp = ? LIMIT 1");
    $stmt->bind_param("s", $whatsapp_digitado);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($whatsapp_digitado !== '' && ($cliente = $res->fetch_assoc())) {
        $codigo = (string)random_int(100000, 999999);
        $expira = date('Y-m-d H:i:s', strtotime('+60 minutes'));

        $stmt_up = $conn->prepare("UPDATE clientes SET codigo_recuperacao = ?, expira_codigo = ?, tentativas_codigo = 0 WHERE id_cliente = ?");
        $stmt_up->bind_param("ssi", $codigo, $expira, $cliente['id_cliente']);
        $stmt_up->execute();
        $stmt_up->close();

        $mensagem_whats = "Olá {$cliente['nome']}!\n\n"
                        . "Seu código para redefinição de senha é: *{$codigo}*\n\n"
                        . "O código é válido por 60 minutos.\n\n"
                        . "Se você não solicitou essa alteração, ignore esta mensagem ou entre em contato conosco pelo WhatsApp (44) 99702-0518.";

        $sucesso_whatsapp_link = "https://api.whatsapp.com/send?phone=55{$whatsapp_digitado}&text=" . urlencode($mensagem_whats);
        $acao_tela = 'enviar_whats';

        $_SESSION['recuperacao'] = [
            'id_cliente' => (int)$cliente['id_cliente'],
            'link'       => $sucesso_whatsapp_link,
            'zap_final'  => substr($whatsapp_digitado, -4),
            'conta'      => (function ($e) {
                $e = (string)$e;
                $arroba = strpos($e, '@');
                if ($arroba === false) return '';
                return mb_substr($e, 0, min(3, $arroba)) . '***' . substr($e, $arroba);
            })($cliente['email']),
        ];
    } else {
        $erro_login = "Número incorreto. Digite o número cadastrado em seu cadastro!";
        $acao_tela = 'esqueci';
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_senha'])) {
    $codigo_digitado = preg_replace('/\D/', '', trim((string)($_POST['codigo'] ?? '')));
    $nova_senha      = (string)($_POST['nova_senha'] ?? '');
    $nova_senha_conf = (string)($_POST['nova_senha_confirmacao'] ?? '');
    $id_recuperacao  = (int)($_SESSION['recuperacao']['id_cliente'] ?? 0);

    $acao_tela = 'enviar_whats';
    $sucesso_whatsapp_link = (string)($_SESSION['recuperacao']['link'] ?? '');

    $stmt = $conn->prepare("SELECT id_cliente, codigo_recuperacao, expira_codigo, tentativas_codigo FROM clientes WHERE id_cliente = ? LIMIT 1");
    $stmt->bind_param("i", $id_recuperacao);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($id_recuperacao > 0 && ($cliente = $res->fetch_assoc())) {
        $codigo_ok = $codigo_digitado !== ''
            && !empty($cliente['codigo_recuperacao'])
            && (int)$cliente['tentativas_codigo'] < CODIGO_MAX_TENTATIVAS
            && hash_equals((string)$cliente['codigo_recuperacao'], $codigo_digitado)
            && !empty($cliente['expira_codigo'])
            && strtotime($cliente['expira_codigo']) >= time();

        if (!$codigo_ok) {
            if (!empty($cliente['codigo_recuperacao'])) {
                $stmt_t = $conn->prepare("UPDATE clientes SET
                        codigo_recuperacao = IF(tentativas_codigo + 1 >= ?, NULL, codigo_recuperacao),
                        tentativas_codigo = tentativas_codigo + 1
                    WHERE id_cliente = ?");
                $max_t = CODIGO_MAX_TENTATIVAS;
                $stmt_t->bind_param("ii", $max_t, $cliente['id_cliente']);
                $stmt_t->execute();
                $stmt_t->close();
            }
            $restantes = CODIGO_MAX_TENTATIVAS - ((int)$cliente['tentativas_codigo'] + 1);
            $expirado  = empty($cliente['expira_codigo']) || strtotime($cliente['expira_codigo']) < time();
            if (empty($cliente['codigo_recuperacao']) || $expirado || $restantes <= 0) {
                unset($_SESSION['recuperacao']);
                $erro_login = "O código expirou ou foi bloqueado por excesso de tentativas. Solicite um novo código!";
                $acao_tela = 'esqueci';
            } else {
                $erro_login = "Código incorreto. Confira o código recebido no WhatsApp ({$restantes} tentativa(s) restante(s))!";
            }
        } elseif (strlen($nova_senha) < 6) {
            $erro_login = "A nova senha precisa ter no mínimo 6 caracteres!";
        } elseif ($nova_senha !== $nova_senha_conf) {
            $erro_login = "As senhas digitadas não coincidem!";
        } else {
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $stmt_nova = $conn->prepare("UPDATE clientes SET senha = ?, codigo_recuperacao = NULL, expira_codigo = NULL, tentativas_codigo = 0 WHERE id_cliente = ?");
            $stmt_nova->bind_param("si", $senha_hash, $cliente['id_cliente']);
            $stmt_nova->execute();
            $stmt_nova->close();
            $stmt->close();
            unset($_SESSION['recuperacao']);

            redirecionarJs('index.php?pagina=login' . $qs_voltar, 'Senha alterada com sucesso! Faça login com sua nova senha!');
        }
    } else {
        $erro_login = "Sua solicitação expirou. Digite o seu WhatsApp para receber um novo código!";
        $acao_tela = 'esqueci';
    }
    if (isset($stmt) && $stmt) $stmt->close();
}
?>

<div class="container my-5 py-3">
    <?php if (!empty($erro_login)): ?>
        <div class="alert alert-danger text-center mb-4"><?= htmlspecialchars($erro_login); ?></div>
    <?php endif; ?>

    <?php if ($voltar === 'checkout' && $acao_tela === 'login' && empty($_SESSION['usuario_id'])): ?>
        <div class="alert alert-light border text-center small mb-4">
            Entre na sua conta para finalizar a compra com seus dados já preenchidos e usar cupons de desconto!
        </div>
    <?php endif; ?>

    <div class="row g-5 justify-content-center">
        <div class="col-md-5">
            <div class="card border-0 shadow-sm p-4 h-100">

                <?php if ($acao_tela === 'login'): ?>
                    <h5 class="fw-bold text-uppercase mb-3 pb-2 border-bottom">Já tenho cadastro</h5>
                    <form action="index.php?pagina=login<?= $qs_voltar; ?>" method="POST">
                        <input type="hidden" name="acao_login" value="1">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">E-mail, CPF/CNPJ ou WhatsApp</label>
                            <input type="text" name="identificador" class="form-control" required autocomplete="username" placeholder="seu@email.com, CPF ou WhatsApp"
                                   value="<?= htmlspecialchars((string)($_POST['identificador'] ?? '')); ?>">
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Senha</label>
                            <div class="input-group">
                                <input type="password" name="senha" id="senhaLogin" class="form-control" required autocomplete="current-password" placeholder="••••••••">
                                <button class="btn btn-outline-dark border-secondary-subtle px-3 bg-white" type="button" onclick="toggleSenha('senhaLogin', this)">
                                    <?= iconeOlhoSvg(); ?>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4 text-end">
                            <a href="index.php?pagina=login&recuperar=esqueci<?= $qs_voltar; ?>" class="small text-muted text-decoration-underline">Esqueci minha senha!</a>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2 fw-bold text-uppercase">Entrar na Conta</button>
                    </form>

                <?php elseif ($acao_tela === 'esqueci'): ?>
                    <h5 class="fw-bold text-uppercase mb-3 pb-2 border-bottom">Recuperar Senha</h5>
                    <p class="small text-muted">Digite o número de <strong>WhatsApp cadastrado</strong> em sua conta para receber o código de segurança!</p>

                    <form action="index.php?pagina=login&recuperar=esqueci<?= $qs_voltar; ?>" method="POST">
                        <input type="hidden" name="solicitar_recuperacao" value="1">

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">WhatsApp (com DDD)</label>
                            <input type="text" name="whatsapp_recuperacao" class="form-control" required placeholder="(44) 99999-9999"
                                   value="<?= htmlspecialchars((string)($_POST['whatsapp_recuperacao'] ?? '')); ?>">
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2 fw-bold text-uppercase mb-2">Avançar</button>
                        <a href="index.php?pagina=login<?= $qs_voltar; ?>" class="btn btn-outline-secondary w-100 btn-sm text-uppercase fw-semibold">Voltar ao Login</a>
                    </form>

                <?php elseif ($acao_tela === 'enviar_whats'): ?>
                    <h5 class="fw-bold text-uppercase mb-3 pb-2 border-bottom">Redefinição de Senha</h5>

                    <div class="alert alert-success text-center py-3 mb-3 small fw-semibold">
                        Código gerado com sucesso! Clique no botão abaixo para enviar o código em seu WhatsApp!
                    </div>

                    <div class="d-grid mb-4">
                        <a href="<?= htmlspecialchars($sucesso_whatsapp_link); ?>" target="_blank" rel="noopener" class="btn btn-success py-3 fw-bold text-uppercase shadow-sm d-flex align-items-center justify-content-center gap-2">
                            <span style="font-size: 1.2rem;">📲</span> Abrir WhatsApp e Enviar Código
                        </a>
                    </div>

                    <hr class="text-muted my-3">

                    <?php if (!empty($_SESSION['recuperacao']['conta'])): ?>
                        <div class="alert alert-light border small py-2 mb-3">
                            Conta que terá a senha alterada: <strong><?= htmlspecialchars((string)$_SESSION['recuperacao']['conta']); ?></strong><br>
                            <span class="text-muted">Não é a sua conta? <a href="index.php?pagina=login&recuperar=esqueci<?= $qs_voltar; ?>">Digite outro WhatsApp</a>.</span>
                        </div>
                    <?php endif; ?>

                    <p class="small text-muted mb-3">
                        Já enviou a mensagem? Digite o código de 6 dígitos enviado para o WhatsApp
                        <?php if (!empty($_SESSION['recuperacao']['zap_final'])): ?>
                            terminado em <strong><?= htmlspecialchars((string)$_SESSION['recuperacao']['zap_final']); ?></strong>
                        <?php endif; ?>
                        e defina a sua nova senha:
                    </p>

                    <form action="index.php?pagina=login<?= $qs_voltar; ?>" method="POST">
                        <input type="hidden" name="atualizar_senha" value="1">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Código de Recuperação (6 dígitos)</label>
                            <input type="text" name="codigo" class="form-control text-center fw-bold fs-5" required maxlength="6" placeholder="000000" inputmode="numeric">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Nova Senha</label>
                            <div class="input-group">
                                <input type="password" name="nova_senha" id="novaSenhaRedef" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                                <button class="btn btn-outline-dark border-secondary-subtle px-3 bg-white" type="button" onclick="toggleSenha('novaSenhaRedef', this)">
                                    <?= iconeOlhoSvg(); ?>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-semibold">Confirmar Nova Senha</label>
                            <div class="input-group">
                                <input type="password" name="nova_senha_confirmacao" id="novaSenhaConfRedef" class="form-control" required minlength="6" placeholder="Repita a nova senha" autocomplete="new-password">
                                <button class="btn btn-outline-dark border-secondary-subtle px-3 bg-white" type="button" onclick="toggleSenha('novaSenhaConfRedef', this)">
                                    <?= iconeOlhoSvg(); ?>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 py-2 fw-bold text-uppercase mb-2">Atualizar Senha</button>
                        <a href="index.php?pagina=login<?= $qs_voltar; ?>" class="btn btn-outline-secondary w-100 btn-sm text-uppercase fw-semibold">Cancelar</a>
                    </form>
                <?php endif; ?>

            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm p-4 bg-light h-100">
                <h5 class="fw-bold text-uppercase mb-3 pb-2 border-bottom">Quero me cadastrar</h5>
                <form action="index.php?pagina=login<?= $qs_voltar; ?>" method="POST">
                    <input type="hidden" name="acao_cadastro" value="1">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nome Completo</label>
                        <input type="text" name="nome" class="form-control" required placeholder="Como gostaria de ser chamado(a)"
                               value="<?= htmlspecialchars((string)($_POST['nome'] ?? '')); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold">E-mail</label>
                            <input type="email" name="email" class="form-control" required placeholder="seu@email.com"
                                   value="<?= htmlspecialchars((string)($_POST['email'] ?? '')); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold">WhatsApp (com DDD)</label>
                            <input type="text" name="whatsapp" class="form-control" required placeholder="(44) 99999-9999"
                                   value="<?= htmlspecialchars((string)($_POST['whatsapp'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Crie uma Senha</label>
                        <div class="input-group">
                            <input type="password" name="senha" id="senhaCadastro" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres">
                            <button class="btn btn-outline-dark border-secondary-subtle px-3 bg-white" type="button" onclick="toggleSenha('senhaCadastro', this)">
                                <?= iconeOlhoSvg(); ?>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-outline-dark w-100 py-2 fw-bold text-uppercase">Criar Minha Conta</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function aplicarMascaraWhatsapp(input) {
        if (!input) return;

        input.addEventListener("input", function () {
            let digits = this.value.replace(/\D/g, "").slice(0, 11);
            let formatted = "";

            if (digits.length > 0) {
                formatted = "(" + digits.substring(0, 2);
            }
            if (digits.length > 2) {
                formatted += ") " + digits.substring(2, 7);
            }
            if (digits.length > 7) {
                formatted += "-" + digits.substring(7);
            }

            this.value = formatted;
        });

        if (input.value) {
            input.dispatchEvent(new Event("input"));
        }
    }

    aplicarMascaraWhatsapp(document.querySelector("input[name='whatsapp']"));
    aplicarMascaraWhatsapp(document.querySelector("input[name='whatsapp_recuperacao']"));
});

function toggleSenha(idInput, botaoEl) {
    const input = document.getElementById(idInput);
    const svgIcon = botaoEl.querySelector("svg");

    if (input.type === "password") {
        input.type = "text";
        svgIcon.innerHTML = `
            <path d="M13.359 11.238C15.06 9.72 16 8 16 8s-3-5.5-8-5.5a7.028 7.028 0 0 0-2.79.588l.77.771A5.944 5.944 0 0 1 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.134 13.134 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755-.247.248-.517.5-.802.748l.98 1.08z"/>
            <path d="M11.297 9.176a3.5 3.5 0 0 0-4.474-4.474l.823.823a2.5 2.5 0 0 1 2.828 2.828l.823.823z"/>
            <path d="M3.35 5.47q-.27.24-.518.477A13.53 13.53 0 0 0 1.172 8s3 5.5 8 5.5a7.029 7.029 0 0 0 2.79-.588l-.77-.771A5.944 5.944 0 0 1 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8c.058-.087.122-.183.195-.288.335-.48.83-1.12 1.465-1.755q.27-.27.56-.54l-.042-.042z"/>
            <path d="m10.854 10.146-8-8 .708-.708 8 8z"/>
        `;
    } else {
        input.type = "password";
        svgIcon.innerHTML = `
            <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
            <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
        `;
    }
}
</script>
