<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexao.php';
require_once __DIR__ . '/../funcoes_cliente.php';

$mensagem_sucesso = "";
$mensagem_erro = "";

$old = [
    'tipo_pessoa'     => 'PF',
    'nome'            => '',
    'sobrenome'       => '',
    'documento'       => '',
    'data_nascimento' => '',
    'email'           => '',
    'whatsapp'        => '',
    'newsletter'      => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_pessoa     = (($_POST['tipo_pessoa'] ?? 'PF') === 'PJ') ? 'PJ' : 'PF';
    $nome            = trim($_POST['nome'] ?? '');
    $sobrenome       = trim($_POST['sobrenome'] ?? '');
    $doc_digitos     = somenteDigitos($_POST['cpf'] ?? '');
    $data_nascimento = trim($_POST['data_nascimento'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $whatsapp        = normalizarWhatsapp($_POST['whatsapp'] ?? '');
    $senha           = $_POST['senha'] ?? '';
    $senha_conf      = $_POST['senha_confirmacao'] ?? '';
    $newsletter      = isset($_POST['newsletter']) ? 1 : 0;

    $old = [
        'tipo_pessoa'     => $tipo_pessoa,
        'nome'            => $nome,
        'sobrenome'       => $sobrenome,
        'documento'       => formatarDocumento($doc_digitos),
        'data_nascimento' => $data_nascimento,
        'email'           => $email,
        'whatsapp'        => $whatsapp,
        'newsletter'      => $newsletter,
    ];

    $erros = [];

    if ($nome === '' || $sobrenome === '') {
        $erros[] = 'Informe nome e sobrenome!';
    }

    $rotulo_doc = ($tipo_pessoa === 'PJ') ? 'CNPJ' : 'CPF';
    $tamanho_ok = ($tipo_pessoa === 'PJ') ? strlen($doc_digitos) === 14 : strlen($doc_digitos) === 11;
    if (!$tamanho_ok || !validarDocumento($doc_digitos)) {
        $erros[] = "$rotulo_doc inválido!";
    }

    $data_valida = null;
    if ($data_nascimento !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $data_nascimento);
        if ($d && $d->format('Y-m-d') === $data_nascimento && $data_nascimento <= date('Y-m-d')) {
            $data_valida = $data_nascimento;
        } else {
            $erros[] = 'Data de nascimento inválida!';
        }
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido!';
    }
    if (!whatsappValido($whatsapp)) {
        $erros[] = 'Informe o WhatsApp com DDD (10 ou 11 dígitos)!';
    }
    if (strlen($senha) < 6) {
        $erros[] = 'A senha precisa ter no mínimo 6 caracteres!';
    }
    if ($senha !== $senha_conf) {
        $erros[] = 'As senhas digitadas não coincidem!';
    }

    $doc_formatado = formatarDocumento($doc_digitos);
    if (!$erros) {
        $stmt = $conn->prepare("SELECT email, whatsapp, cpf_cnpj FROM clientes WHERE email = ? OR whatsapp = ? OR cpf_cnpj = ? LIMIT 1");
        $stmt->bind_param("sss", $email, $whatsapp, $doc_formatado);
        $stmt->execute();
        $existente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existente) {
            if (strcasecmp((string)$existente['email'], $email) === 0) {
                $erros[] = 'Este e-mail já está cadastrado. Faça login ou recupere a sua senha!';
            } elseif ((string)$existente['whatsapp'] === $whatsapp) {
                $erros[] = 'Este WhatsApp já está cadastrado em outra conta!';
            } else {
                $erros[] = "Este $rotulo_doc já está cadastrado. Faça login ou recupere a sua senha!";
            }
        }
    }

    if (!$erros) {
        try {
            $nome_completo = $nome . ' ' . $sobrenome;
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO clientes (tipo_pessoa, nome, email, whatsapp, cpf_cnpj, data_nascimento, senha, newsletter) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", $tipo_pessoa, $nome_completo, $email, $whatsapp, $doc_formatado, $data_valida, $senha_hash, $newsletter);
            $stmt->execute();
            $id_novo_cliente = $stmt->insert_id;
            $stmt->close();

            iniciarSessaoCliente($id_novo_cliente, $nome_completo, $email, $whatsapp);

            $mensagem_sucesso = "
            <div class='alert alert-success border-0 shadow-sm p-4 mb-4 text-center' style='background-color: #d1e7dd; color: #0f5132;'>
                <h4 class='fw-bold mb-1'>Obrigado por se cadastrar!</h4>
                <p class='mb-3 fs-5'>Seja bem vindo(a) ao nosso Ateliê!!!</p>
                <a href='index.php' class='btn btn-dark btn-sm text-uppercase fw-semibold me-1'>Ir para a loja</a>
                <a href='index.php?pagina=minha-conta' class='btn btn-outline-dark btn-sm text-uppercase fw-semibold'>Completar meu endereço</a>
            </div>";
        } catch (mysqli_sql_exception $e) {
            error_log('cadastro.php: ' . $e->getMessage());
            if ($e->getCode() == 1062) {
                $erros[] = 'Este e-mail, WhatsApp ou documento já está cadastrado no sistema. Tente recuperar a sua senha!';
            } else {
                $erros[] = 'Ocorreu um erro ao registrar. Por favor, tente novamente!';
            }
        }
    }

    if ($erros) {
        $itens = '';
        foreach ($erros as $e) $itens .= '<li>' . htmlspecialchars($e) . '</li>';
        $mensagem_erro = "<div class='alert alert-danger shadow-sm'><ul class='mb-0 small'>{$itens}</ul></div>";
    }
}

$e = fn($k) => htmlspecialchars((string)$old[$k]);
?>

<div class="container my-5" style="max-width: 720px;">

    <?= $mensagem_sucesso; ?>
    <?= $mensagem_erro; ?>

    <?php if (empty($mensagem_sucesso)): ?>
    <div class="card border-0 shadow-sm p-4">
        <h2 class="fw-light text-uppercase mb-4">Criar Nova Conta</h2>

        <form action="index.php?pagina=cadastro" method="POST" id="formCadastro" onsubmit="return validarFormulario();">

            <h5 class="fw-bold mb-3 border-bottom pb-2">Informações Pessoais</h5>

            <div class="mb-3">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_pessoa" id="pf" value="PF" <?= $old['tipo_pessoa'] === 'PF' ? 'checked' : ''; ?> onchange="trocarTipo()">
                    <label class="form-check-label small fw-semibold" for="pf">Pessoa Física</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="tipo_pessoa" id="pj" value="PJ" <?= $old['tipo_pessoa'] === 'PJ' ? 'checked' : ''; ?> onchange="trocarTipo()">
                    <label class="form-check-label small fw-semibold" for="pj">Pessoa Jurídica</label>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold">Nome *</label>
                    <input type="text" name="nome" class="form-control" required placeholder="Ex: Eder" value="<?= $e('nome'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold">Sobrenome *</label>
                    <input type="text" name="sobrenome" class="form-control" required placeholder="Ex: Fernandes" value="<?= $e('sobrenome'); ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold" id="labelDoc">CPF *</label>
                    <input type="text" id="cpf" name="cpf" class="form-control" maxlength="14" required placeholder="000.000.000-00" oninput="mascaraDocumento(this)" value="<?= $e('documento'); ?>">
                    <div id="cpfErro" class="text-danger small mt-1" style="display:none;">Documento inválido!</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold">Data de Nascimento</label>
                    <input type="date" name="data_nascimento" class="form-control" value="<?= $e('data_nascimento'); ?>">
                </div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" name="newsletter" id="newsCheck" <?= $old['newsletter'] ? 'checked' : ''; ?>>
                <label class="form-check-label small text-muted" for="newsCheck">
                    Assinar Newsletter para receber novidades do ateliê
                </label>
            </div>

            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4">Informações de Acesso</h5>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold">E-mail *</label>
                    <input type="email" name="email" class="form-control" required placeholder="seu@email.com" value="<?= $e('email'); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold">WhatsApp (com DDD) *</label>
                    <input type="text" id="whatsapp" name="whatsapp" class="form-control" required maxlength="15" placeholder="(44) 99999-9999" oninput="mascaraWhatsapp(this)" value="<?= $e('whatsapp'); ?>">
                    <div id="whatsErro" class="text-danger small mt-1" style="display:none;">Informe o DDD e o número!</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold">Senha *</label>
                    <input type="password" id="senha" name="senha" class="form-control" required minlength="6" placeholder="Mínimo 6 caracteres" onkeyup="checarForcaSenha(this.value)">

                    <div id="boxForcaSenha" class="mt-2 p-2 rounded text-center small fw-semibold" style="display: none;">
                        <span id="textoForcaSenha">Força da senha: Sem senha</span>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold">Confirmar Senha *</label>
                    <input type="password" id="senhaConf" name="senha_confirmacao" class="form-control" required placeholder="Repita a senha">
                </div>
            </div>

            <div class="text-muted small mb-3">* Campos Obrigatórios</div>

            <button type="submit" class="btn btn-dark w-100 py-2 text-uppercase fw-bold" style="background-color: #2f4f4f; border-color: #2f4f4f;">
                Criar Conta
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function tipoAtual() {
    return document.getElementById('pj').checked ? 'PJ' : 'PF';
}

function mascaraDocumento(input) {
    let v = input.value.replace(/\D/g, "");
    if (tipoAtual() === 'PF') {
        v = v.slice(0, 11);
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d)/, "$1.$2");
        v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
    } else {
        v = v.slice(0, 14);
        v = v.replace(/^(\d{2})(\d)/, "$1.$2");
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3");
        v = v.replace(/\.(\d{3})(\d)/, ".$1/$2");
        v = v.replace(/(\d{4})(\d)/, "$1-$2");
    }
    input.value = v;
}

function mascaraWhatsapp(input) {
    let v = input.value.replace(/\D/g, "").slice(0, 11);
    if (v.length > 6) {
        v = `(${v.slice(0, 2)}) ${v.slice(2, 7)}-${v.slice(7)}`;
    } else if (v.length > 2) {
        v = `(${v.slice(0, 2)}) ${v.slice(2)}`;
    } else if (v.length > 0) {
        v = `(${v}`;
    }
    input.value = v;
}

function trocarTipo() {
    const pj = tipoAtual() === 'PJ';
    const campo = document.getElementById('cpf');
    document.getElementById('labelDoc').innerText = pj ? 'CNPJ *' : 'CPF *';
    campo.placeholder = pj ? '00.000.000/0000-00' : '000.000.000-00';
    campo.maxLength = pj ? 18 : 14;
    document.getElementById('cpfErro').style.display = 'none';
    mascaraDocumento(campo);
}

function validarCPF(c) {
    c = c.replace(/\D/g, '');
    if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;
    for (let t = 9; t < 11; t++) {
        let s = 0;
        for (let i = 0; i < t; i++) s += parseInt(c[i]) * (t + 1 - i);
        const d = ((s * 10) % 11) % 10;
        if (d !== parseInt(c[t])) return false;
    }
    return true;
}

function validarCNPJ(c) {
    c = c.replace(/\D/g, '');
    if (c.length !== 14 || /^(\d)\1+$/.test(c)) return false;
    const calc = (base) => {
        const pesos = base === 12 ? [5,4,3,2,9,8,7,6,5,4,3,2] : [6,5,4,3,2,9,8,7,6,5,4,3,2];
        let s = 0;
        for (let i = 0; i < base; i++) s += parseInt(c[i]) * pesos[i];
        const r = s % 11;
        return r < 2 ? 0 : 11 - r;
    };
    return calc(12) === parseInt(c[12]) && calc(13) === parseInt(c[13]);
}

function checarForcaSenha(senha) {
    let box = document.getElementById('boxForcaSenha');
    let txt = document.getElementById('textoForcaSenha');
    if (senha.length === 0) {
        box.style.display = 'none';
        return;
    }
    box.style.display = 'block';

    let forca = 0;
    if (senha.length >= 6) forca++;
    if (senha.length >= 8) forca++;
    if (/[A-Z]/.test(senha)) forca++;
    if (/[0-9]/.test(senha)) forca++;
    if (/[^A-Za-z0-9]/.test(senha)) forca++;

    if (forca <= 2) {
        box.style.backgroundColor = '#f8d7da';
        box.style.color = '#842029';
        txt.innerText = 'Força da senha: Fraca';
    } else if (forca === 3 || forca === 4) {
        box.style.backgroundColor = '#fff3cd';
        box.style.color = '#664d03';
        txt.innerText = 'Força da senha: Média';
    } else {
        box.style.backgroundColor = '#d1e7dd';
        box.style.color = '#0f5132';
        txt.innerText = 'Força da senha: Forte';
    }
}

function validarFormulario() {
    const pj = tipoAtual() === 'PJ';
    const doc = document.getElementById('cpf').value;
    const docErro = document.getElementById('cpfErro');
    const docOk = pj ? validarCNPJ(doc) : validarCPF(doc);
    if (!docOk) {
        docErro.innerText = pj ? 'CNPJ inválido!' : 'CPF inválido segundo a Receita Federal!';
        docErro.style.display = 'block';
        return false;
    }
    docErro.style.display = 'none';

    const zap = document.getElementById('whatsapp').value.replace(/\D/g, '');
    const zapErro = document.getElementById('whatsErro');
    if (zap.length < 10 || zap.length > 11) {
        zapErro.style.display = 'block';
        return false;
    }
    zapErro.style.display = 'none';

    const s1 = document.getElementById('senha').value;
    const s2 = document.getElementById('senhaConf').value;
    if (s1 !== s2) {
        alert('As senhas digitadas não coincidem!');
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('cpf')) {
        trocarTipo();
        mascaraWhatsapp(document.getElementById('whatsapp'));
    }
});
</script>