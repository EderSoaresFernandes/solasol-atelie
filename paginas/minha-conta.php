<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../funcoes_cliente.php';

if (empty($_SESSION['usuario_id'])) {
    redirecionarJs('index.php?pagina=login');
}

require_once 'conexao.php';

$id_cliente = (int)$_SESSION['usuario_id'];
$mensagem_sucesso = "";
$mensagem_erro = "";
$tentou_enviar = false;
$post = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_conta'])) {
    $tentou_enviar = true;

    foreach (['nome', 'email', 'whatsapp', 'cpf_cnpj', 'rg_ie', 'data_nascimento', 'rua', 'numero',
              'bairro', 'cep', 'cidade', 'estado', 'complemento'] as $campo) {
        $post[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $senha_atual = (string)($_POST['senha_atual'] ?? '');

    $stmt_senha = $conn->prepare("SELECT senha FROM clientes WHERE id_cliente = ?");
    $stmt_senha->bind_param("i", $id_cliente);
    $stmt_senha->execute();
    $res_senha = $stmt_senha->get_result()->fetch_assoc();
    $stmt_senha->close();

    if ($senha_atual === '' || !$res_senha || !password_verify($senha_atual, $res_senha['senha'])) {
        $mensagem_erro = "Senha atual incorreta. Digite sua senha correta para salvar as alterações!";
    } else {
        $nome        = $post['nome'];
        $email       = $post['email'];
        $whatsapp    = normalizarWhatsapp($post['whatsapp']);
        $doc_digitos = somenteDigitos($post['cpf_cnpj']);
        $doc_fmt     = formatarDocumento($doc_digitos);
        $tipo_pessoa = (strlen($doc_digitos) === 14) ? 'PJ' : 'PF';
        $cep_digitos = somenteDigitos($post['cep']);
        $uf          = strtoupper($post['estado']);
        $data_nasc   = null;

        $erros = [];
        if ($nome === '')                                  $erros[] = 'Informe o nome completo!';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))    $erros[] = 'Informe um e-mail válido!';
        if (!whatsappValido($whatsapp))                    $erros[] = 'Informe o WhatsApp com DDD (10 ou 11 dígitos)!';
        if (!validarDocumento($doc_digitos))               $erros[] = 'CPF/CNPJ inválido!';
        if ($post['rua'] === '' || $post['numero'] === '' || $post['bairro'] === '' || $post['cidade'] === '') {
            $erros[] = 'Preencha rua, número, bairro e cidade!';
        }
        if (strlen($cep_digitos) !== 8)                    $erros[] = 'CEP inválido!';
        if (!preg_match('/^[A-Z]{2}$/', $uf))              $erros[] = 'Informe a UF com 2 letras (ex.: PR)!';

        if ($post['data_nascimento'] !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $post['data_nascimento']);
            if ($d && $d->format('Y-m-d') === $post['data_nascimento'] && $post['data_nascimento'] <= date('Y-m-d')) {
                $data_nasc = $post['data_nascimento'];
            } else {
                $erros[] = 'Data de nascimento inválida!';
            }
        }

        if (!$erros) {
            $stmt_check = $conn->prepare("SELECT email, whatsapp, cpf_cnpj FROM clientes WHERE id_cliente <> ? AND (email = ? OR whatsapp = ? OR cpf_cnpj = ?) LIMIT 1");
            $stmt_check->bind_param("isss", $id_cliente, $email, $whatsapp, $doc_fmt);
            $stmt_check->execute();
            $outro = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($outro) {
                if (strcasecmp((string)$outro['email'], $email) === 0)      $erros[] = 'Este e-mail já está sendo utilizado por outro cadastro!';
                elseif ((string)$outro['whatsapp'] === $whatsapp)           $erros[] = 'Este WhatsApp já está sendo utilizado por outro cadastro!';
                else                                                        $erros[] = 'Este CPF/CNPJ já está sendo utilizado por outro cadastro!';
            }
        }

        if ($erros) {
            $mensagem_erro = implode("\n", $erros);
        } else {
            try {
                $cep_fmt = substr($cep_digitos, 0, 5) . '-' . substr($cep_digitos, 5);
                $rg_ie   = $post['rg_ie'];

                $stmt_up = $conn->prepare("UPDATE clientes SET tipo_pessoa = ?, nome = ?, email = ?, whatsapp = ?, cpf_cnpj = ?, rg_ie = ?, data_nascimento = ?, rua = ?, numero = ?, bairro = ?, cep = ?, cidade = ?, estado = ?, complemento = ? WHERE id_cliente = ?");
                $stmt_up->bind_param(
                    "ssssssssssssssi",
                    $tipo_pessoa, $nome, $email, $whatsapp, $doc_fmt, $rg_ie, $data_nasc,
                    $post['rua'], $post['numero'], $post['bairro'], $cep_fmt, $post['cidade'], $uf, $post['complemento'],
                    $id_cliente
                );
                $stmt_up->execute();
                $stmt_up->close();

                $_SESSION['usuario_nome']     = $nome;
                $_SESSION['usuario_email']    = $email;
                $_SESSION['usuario_whatsapp'] = $whatsapp;

                $mensagem_sucesso = "Dados atualizados com sucesso!";
                $tentou_enviar = false;
                $post = [];
            } catch (mysqli_sql_exception $e) {
                error_log('minha-conta.php: ' . $e->getMessage());
                $mensagem_erro = ($e->getCode() == 1062)
                    ? "E-mail, WhatsApp ou CPF/CNPJ já está sendo utilizado por outro cadastro!"
                    : "Não foi possível atualizar os dados. Tente novamente!";
            }
        }
    }
}

$stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
$stmt->bind_param("i", $id_cliente);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

if ($tentou_enviar && $post) {
    $cliente = array_merge($cliente, $post);
}
$v = fn($k) => htmlspecialchars((string)($cliente[$k] ?? ''));
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                <h3 class="fw-bold text-uppercase m-0">Minha Conta</h3>
                <a href="index.php" class="btn btn-outline-dark btn-sm text-uppercase fw-semibold">Voltar à Loja</a>
            </div>

            <?php if ($tentou_enviar && !empty($mensagem_erro)): ?>
                <p class="text-danger small fw-semibold mb-3">* Verifique os campos obrigatórios e tente novamente!</p>
            <?php endif; ?>

            <?php if (!empty($mensagem_sucesso)): ?>
                <div class="alert alert-success text-center"><?= htmlspecialchars($mensagem_sucesso); ?></div>
            <?php endif; ?>

            <?php if (!empty($mensagem_erro)): ?>
                <div class="alert alert-danger text-center"><?= nl2br(htmlspecialchars($mensagem_erro)); ?></div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm p-4 bg-light">
                <form action="index.php?pagina=minha-conta" method="POST">
                    <input type="hidden" name="atualizar_conta" value="1">

                    <h5 class="fw-bold text-uppercase text-dark mb-3">Dados Pessoais</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Nome Completo *</label>
                            <input type="text" name="nome" class="form-control" required value="<?= $v('nome'); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">E-mail *</label>
                            <input type="email" name="email" class="form-control" required value="<?= $v('email'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">WhatsApp (com DDD) *</label>
                            <input type="text" name="whatsapp" class="form-control" required value="<?= $v('whatsapp'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">CPF / CNPJ *</label>
                            <input type="text" name="cpf_cnpj" class="form-control" required placeholder="000.000.000-00" value="<?= $v('cpf_cnpj'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">RG / Inscrição Estadual</label>
                            <input type="text" name="rg_ie" class="form-control" value="<?= $v('rg_ie'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Data de Nascimento</label>
                            <input type="date" name="data_nascimento" class="form-control" value="<?= $v('data_nascimento'); ?>">
                        </div>
                    </div>

                    <hr class="text-muted my-4">

                    <h5 class="fw-bold text-uppercase text-dark mb-3">Endereço de Entrega</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Rua / Lote / Chácara / Sítio / Fazenda *</label>
                            <input type="text" name="rua" class="form-control" required placeholder="Ex: Rua das Flores, Sítio Bela Vista" value="<?= $v('rua'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Número *</label>
                            <input type="text" name="numero" class="form-control" required placeholder="Ex: 123 ou S/N" value="<?= $v('numero'); ?>">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">Bairro *</label>
                            <input type="text" name="bairro" class="form-control" required placeholder="Nome do bairro ou região" value="<?= $v('bairro'); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">CEP *</label>
                            <input type="text" name="cep" class="form-control" required placeholder="00000-000" value="<?= $v('cep'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Complemento</label>
                            <input type="text" name="complemento" class="form-control" placeholder="Apto, Bloco, Referência" value="<?= $v('complemento'); ?>">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Cidade *</label>
                            <input type="text" name="cidade" class="form-control" required placeholder="Sua cidade" value="<?= $v('cidade'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Estado (UF) *</label>
                            <input type="text" name="estado" class="form-control text-uppercase" required maxlength="2" placeholder="PR" value="<?= $v('estado'); ?>">
                        </div>
                    </div>

                    <hr class="text-muted my-4">

                    <div class="bg-white p-3 rounded border border-warning-subtle mb-4">
                        <label class="form-label small fw-bold text-dark">Confirme sua Senha Atual para Salvar as Alterações *</label>
                        <div class="input-group">
                            <input type="password" name="senha_atual" id="senhaAtualConta" class="form-control" required placeholder="Digite sua senha para confirmar">
                            <button class="btn btn-outline-dark border-secondary-subtle px-3 bg-white" type="button" onclick="toggleSenha('senhaAtualConta', this)">
                                <?= iconeOlhoSvg(); ?>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 py-3 fw-bold text-uppercase shadow-sm">
                        Salvar Alterações
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function aplicarMascara(inputEl, tipo) {
        if (!inputEl) return;

        function formatar() {
            let valor = inputEl.value.replace(/\D/g, "");

            if (tipo === "whatsapp") {
                if (valor.length > 11) valor = valor.slice(0, 11);
                if (valor.length > 6) {
                    valor = `(${valor.slice(0, 2)}) ${valor.slice(2, 7)}-${valor.slice(7)}`;
                } else if (valor.length > 2) {
                    valor = `(${valor.slice(0, 2)}) ${valor.slice(2)}`;
                } else if (valor.length > 0) {
                    valor = `(${valor}`;
                }
            }
            else if (tipo === "cpf_cnpj") {
                if (valor.length > 14) valor = valor.slice(0, 14);
                if (valor.length <= 11) {
                    if (valor.length > 9) {
                        valor = `${valor.slice(0, 3)}.${valor.slice(3, 6)}.${valor.slice(6, 9)}-${valor.slice(9)}`;
                    } else if (valor.length > 6) {
                        valor = `${valor.slice(0, 3)}.${valor.slice(3, 6)}.${valor.slice(6)}`;
                    } else if (valor.length > 3) {
                        valor = `${valor.slice(0, 3)}.${valor.slice(3)}`;
                    }
                } else {
                    valor = `${valor.slice(0, 2)}.${valor.slice(2, 5)}.${valor.slice(5, 8)}/${valor.slice(8, 12)}-${valor.slice(12)}`;
                }
            }
            else if (tipo === "cep") {
                if (valor.length > 8) valor = valor.slice(0, 8);
                if (valor.length > 5) {
                    valor = `${valor.slice(0, 5)}-${valor.slice(5)}`;
                }
            }

            inputEl.value = valor;
        }

        inputEl.addEventListener("input", formatar);
        if (inputEl.value) formatar();
    }

    aplicarMascara(document.querySelector("input[name='whatsapp']"), "whatsapp");
    aplicarMascara(document.querySelector("input[name='cpf_cnpj']"), "cpf_cnpj");
    aplicarMascara(document.querySelector("input[name='cep']"), "cep");
});

function toggleSenha(idInput, botaoEl) {
    const input = document.getElementById(idInput);
    const svgIcon = botaoEl.querySelector('svg');

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