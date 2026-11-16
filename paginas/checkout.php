<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'funcoes.php';

$cfg_frete = $conn->query("SELECT * FROM configuracoes_frete WHERE id=1")->fetch_assoc();
if (!$cfg_frete) {
    $cfg_frete = [
        'valor_minimo_frete_gratis' => 199.90,
        'dentro_estado' => 15.00,
        'regiao_sul' => 20.00,
        'regiao_sudeste' => 25.00,
        'regiao_centro_oeste' => 35.00,
        'regiao_nordeste' => 45.00,
        'regiao_norte' => 50.00
    ];
}

$frete_js = [
    'dentro_estado'       => (float)($cfg_frete['dentro_estado'] ?? 15.00),
    'regiao_sul'          => (float)($cfg_frete['regiao_sul'] ?? 20.00),
    'regiao_sudeste'      => (float)($cfg_frete['regiao_sudeste'] ?? 25.00),
    'regiao_centro_oeste' => (float)($cfg_frete['regiao_centro_oeste'] ?? 35.00),
    'regiao_nordeste'     => (float)($cfg_frete['regiao_nordeste'] ?? 45.00),
    'regiao_norte'        => (float)($cfg_frete['regiao_norte'] ?? 50.00),
    'minimo_gratis'       => (float)($cfg_frete['valor_minimo_frete_gratis'] ?? 199.90),
];

$logado = !empty($_SESSION['usuario_id']);
$cli = [];
if ($logado) {
    $id_cli = (int)$_SESSION['usuario_id'];
    $st = $conn->prepare("SELECT nome, email, whatsapp, cpf_cnpj, cep, rua, numero, bairro, cidade, estado, complemento FROM clientes WHERE id_cliente = ?");
    $st->bind_param("i", $id_cli);
    $st->execute();
    $cli = $st->get_result()->fetch_assoc() ?: [];
    $st->close();
}
$pre = fn($k) => htmlspecialchars((string)($cli[$k] ?? ''));
?>

<div class="container my-5">
    <h2 class="text-uppercase fw-bold mb-4">Finalizar Compra</h2>

    <div class="row g-5">
        <div class="col-lg-7">

            <?php if ($logado): ?>
                <div class="alert alert-light border small py-2 mb-3">
                    ✅ Olá, <strong><?= htmlspecialchars((string)($_SESSION['usuario_nome'] ?? '')); ?></strong>! Preenchemos seus dados com o seu cadastro.
                    Se precisar, é só alterar os campos abaixo!
                </div>
            <?php else: ?>
                <div class="alert alert-light border small py-2 mb-3">
                    Já tem cadastro? <a href="index.php?pagina=login&voltar=checkout" class="fw-semibold text-dark">Entre na sua conta</a>
                    para preencher os dados automaticamente e usar cupons de desconto!
                </div>
            <?php endif; ?>

            <form id="formCheckout" onsubmit="processarPagamento(event)">

                <div class="card border-0 shadow-sm p-4 mb-4">
                    <h5 class="fw-bold mb-3 text-dark">1. Dados Pessoais</h5>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nome Completo</label>
                        <input type="text" id="checkout-nome" class="form-control" required placeholder="Seu nome completo" value="<?= $pre('nome'); ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold">E-mail</label>
                            <input type="email" id="checkout-email" class="form-control" required placeholder="seu@email.com" value="<?= $pre('email'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-semibold">Telefone / WhatsApp</label>
                            <input type="text" id="checkout-telefone" class="form-control" required placeholder="(44) 99999-9999" value="<?= $pre('whatsapp'); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-1">
                            <label class="form-label small fw-semibold">CPF / CNPJ <span class="text-danger">*</span></label>
                            <input type="text" id="checkout-cpf" class="form-control" required maxlength="18" placeholder="000.000.000-00" inputmode="numeric" value="<?= $pre('cpf_cnpj'); ?>">
                            <div id="checkout-cpf-erro" class="text-danger small mt-1 d-none">CPF/CNPJ inválido!</div>
                            <div class="form-text">Obrigatório para a emissão da nota fiscal!</div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm p-4 mb-4">
                    <h5 class="fw-bold mb-3 text-dark">2. Endereço de Entrega</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold">CEP</label>
                            <input type="text" id="checkout-cep" class="form-control" required placeholder="00000-000" maxlength="9" onblur="calcularFreteCheckout()" value="<?= $pre('cep'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Endereço (Rua, Avenida...)</label>
                        <input type="text" id="checkout-endereco" class="form-control" required placeholder="Nome da rua" value="<?= $pre('rua'); ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold">Número</label>
                            <input type="text" id="checkout-numero" class="form-control" required placeholder="123" value="<?= $pre('numero'); ?>">
                        </div>
                        <div class="col-md-8 mb-3">
                            <label class="form-label small fw-semibold">Bairro</label>
                            <input type="text" id="checkout-bairro" class="form-control" required placeholder="Nome do bairro" value="<?= $pre('bairro'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Complemento <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" id="checkout-complemento" class="form-control" placeholder="Apto, bloco, referência" value="<?= $pre('complemento'); ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label small fw-semibold">Cidade</label>
                            <input type="text" id="checkout-cidade" class="form-control" required placeholder="Sua cidade" value="<?= $pre('cidade'); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label small fw-semibold">Estado (UF)</label>
                            <input type="text" id="checkout-estado" class="form-control text-uppercase" required placeholder="PR" maxlength="2" value="<?= $pre('estado'); ?>">
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm p-4 mb-4">
                    <h5 class="fw-bold mb-3 text-dark">3. Forma de Pagamento</h5>

                    <div class="form-check mb-3 border p-3 rounded">
                        <input class="form-check-input" type="radio" name="formaPagamento" id="pagPix" value="pix" checked onclick="alternarPagamento('pix')">
                        <label class="form-check-label fw-semibold text-success ms-2" for="pagPix">
                            Pix (5% de desconto extra)
                        </label>
                        <div class="small text-muted mt-1 ps-4">Os 5% são descontados do valor já com promoção e cupom!</div>
                        <div class="small text-muted mt-1 ps-4">O QR Code e o código Pix Copia e Cola aparecem logo após a confirmação do pedido!</div>
                    </div>

                    <div class="form-check mb-3 border p-3 rounded">
                        <input class="form-check-input" type="radio" name="formaPagamento" id="pagCartao" value="cartao" onclick="alternarPagamento('cartao')">
                        <label class="form-check-label fw-semibold text-dark ms-2" for="pagCartao">
                            Cartão de Crédito (Até 10x respeitando o mínimo de R$ 50,00)
                        </label>

                        <!-- Campos do Cartão (PAGAMENTO SIMULADO: os dados do cartão são conferidos só no navegador
                             e NUNCA são enviados ao servidor nem gravados; o pedido leva apenas a forma de pagamento e as parcelas) -->
                        <div id="camposCartao" class="mt-3 ps-4" style="display: none;">
                            <div class="alert alert-info py-2 small mb-3">
                                🔒 Ambiente de demonstração: o pagamento com cartão é simulado e nenhum valor será cobrado!
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="cartao-numero">Número do Cartão</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="cartao-numero" class="form-control form-control-sm" placeholder="0000 0000 0000 0000"
                                           inputmode="numeric" autocomplete="cc-number" maxlength="23">
                                    <span class="input-group-text small" id="cartao-bandeira" style="min-width: 90px;">—</span>
                                </div>
                                <div class="invalid-feedback d-block small" id="erro-cartao-numero"></div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-2">
                                    <label class="form-label small" for="cartao-validade">Validade</label>
                                    <input type="text" id="cartao-validade" class="form-control form-control-sm" placeholder="MM/AA"
                                           inputmode="numeric" autocomplete="cc-exp" maxlength="5">
                                    <div class="invalid-feedback d-block small" id="erro-cartao-validade"></div>
                                </div>
                                <div class="col-6 mb-2">
                                    <label class="form-label small" for="cartao-cvv">CVV</label>
                                    <input type="password" id="cartao-cvv" class="form-control form-control-sm" placeholder="123"
                                           inputmode="numeric" autocomplete="cc-csc" maxlength="4">
                                    <div class="invalid-feedback d-block small" id="erro-cartao-cvv"></div>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="cartao-nome">Nome no Cartão</label>
                                <input type="text" id="cartao-nome" class="form-control form-control-sm text-uppercase" placeholder="Como impresso no cartão"
                                       autocomplete="cc-name" maxlength="60">
                                <div class="invalid-feedback d-block small" id="erro-cartao-nome"></div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" for="cartao-parcelas">Parcelamento</label>
                                <select id="cartao-parcelas" class="form-select form-select-sm"></select>
                                <div class="form-text">Até 10x sem juros, com parcela mínima de R$ 50,00!</div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" id="btn-finalizar" class="btn btn-dark w-100 py-3 fw-bold text-uppercase">Finalizar Pedido e Pagar</button>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card bg-light border-0 p-4 shadow-sm sticky-top" style="top: 100px;">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Resumo da Sacola</h5>

                <div id="checkout-itens-lista" class="mb-3 scroll-lista" style="max-height: 250px; overflow-y: auto;">
                </div>

                <div class="mb-3">
                    <?php if ($logado): ?>
                        <div id="chk-cupom-form">
                            <label class="form-label small fw-semibold mb-1" for="chk-cupom-input">Cupom de desconto</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="chk-cupom-input" class="form-control" placeholder="Digite o cupom" autocomplete="off" maxlength="41">
                                <button type="button" id="chk-cupom-btn" class="btn btn-dark text-uppercase fw-semibold" onclick="aplicarCupomCheckout()">Aplicar</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="small text-muted">
                            🏷️ Tem um cupom? <a href="index.php?pagina=login&voltar=checkout" class="fw-semibold text-dark">Entre na sua conta</a> para usá-lo!
                        </div>
                    <?php endif; ?>
                </div>

                <div id="chk-cupom-aviso" class="alert alert-warning py-2 small d-none"></div>

                <div class="d-flex justify-content-between mb-2 small">
                    <span>Subtotal</span>
                    <strong id="chk-subtotal">R$ 0,00</strong>
                </div>

                <div class="justify-content-between align-items-center mb-2 small text-success d-none" id="chk-cupom-box">
                    <span>🏷️ Cupom <strong id="chk-cupom-codigo"></strong>
                        <button type="button" class="btn btn-link btn-sm text-danger p-0 ms-1 text-decoration-none" style="font-size: 0.75rem;" onclick="removerCupomCheckout()">remover</button>
                    </span>
                    <strong id="chk-cupom-valor">- R$ 0,00</strong>
                </div>

                <div class="d-flex justify-content-between mb-2 small">
                    <span>Frete</span>
                    <strong id="chk-frete">Informe o CEP</strong>
                </div>

                <div class="justify-content-between mb-3 border-bottom pb-3 small text-success d-none" id="chk-desconto-box">
                    <span>Desconto Pix (5%)</span>
                    <strong id="chk-desconto">R$ 0,00</strong>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <span class="fw-bold fs-5">Total a Pagar</span>
                    <strong class="fw-bold fs-5 text-success" id="chk-total">R$ 0,00</strong>
                </div>

                <a href="index.php?pagina=sacola" class="btn btn-outline-secondary w-100 btn-sm text-uppercase fw-semibold">Editar Sacola</a>
            </div>
        </div>
    </div>
</div>

<!-- Pagamento Pix (SIMULADO): QR Code + Copia e Cola gerados pelo servidor com o valor do pedido -->
<div class="modal fade" id="modalPix" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="modalPixTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalPixTitulo">Pague com Pix!</h5>
            </div>
            <div class="modal-body text-center pt-2">
                <div class="alert alert-info py-2 small mb-3">
                    🔒 Ambiente de demonstração: o Pix é simulado e nenhum valor será cobrado!
                </div>

                <p class="mb-1 small text-muted">Pedido <strong id="pix-pedido"></strong> realizado com sucesso!</p>
                <div class="fs-3 fw-bold text-success mb-2" id="pix-valor"></div>

                <div id="pix-area-pagamento">
                    <div class="d-inline-block p-2 bg-white border rounded mb-2" id="pix-qrcode" style="min-width: 220px; min-height: 220px;"></div>
                    <p class="small text-muted mb-3">Abra o app do seu banco, escolha <strong>Pix &gt; Ler QR Code</strong> e aponte a câmera!</p>

                    <label class="form-label small fw-semibold d-block text-start mb-1" for="pix-copia-cola">Ou use o Pix Copia e Cola:</label>
                    <div class="input-group input-group-sm mb-1">
                        <input type="text" id="pix-copia-cola" class="form-control font-monospace" readonly>
                        <button type="button" class="btn btn-dark fw-semibold" id="btn-copiar-pix" onclick="copiarPix()">Copiar</button>
                    </div>
                    <div class="small text-success text-start mb-3" id="pix-copiado" style="min-height: 1.2em;"></div>

                    <div class="small mb-3">
                        Recebedor: <strong id="pix-recebedor"></strong><br>
                        Este código expira em <strong id="pix-timer" class="text-danger">30:00</strong>!
                    </div>

                    <button type="button" class="btn btn-success w-100 fw-bold text-uppercase py-2 mb-2" id="btn-simular-pix" onclick="simularPagamentoPix()">
                        Já fiz o pagamento (simular)
                    </button>
                    <button type="button" class="btn btn-outline-secondary w-100 btn-sm" onclick="pagarPixDepois()">Pagar depois</button>
                </div>

                <div id="pix-resultado" class="d-none py-3">
                    <div class="display-5 mb-2" id="pix-resultado-icone"></div>
                    <h5 class="fw-bold" id="pix-resultado-titulo"></h5>
                    <p class="small text-muted mb-3" id="pix-resultado-texto"></p>
                    <button type="button" class="btn btn-dark w-100 fw-bold text-uppercase" onclick="sairDoCheckout()">Continuar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script>
const SACOLA_KEY = 'solasol_sacola';
const CUPOM_KEY  = 'solasol_cupom';

const URL_SALVAR_PEDIDO = 'acoes/salvar_pedido.php';
const URL_VALIDAR_CUPOM = 'acoes/validar_cupom.php';

const FRETE = <?= json_encode($frete_js); ?>;
const LOGADO = <?= $logado ? 'true' : 'false'; ?>;
const PERC_DESCONTO_PIX = <?= json_encode(PERC_DESCONTO_PIX); ?>;   // definido em funcoes.php (igual ao servidor)

let subtotalGlobal = 0.0;
let valorFreteTabela = 0.0;      // valor da tabela para a região do CEP
let freteCalculado = false;      // false até o cliente informar um CEP válido
let cupomCodigo = null;
let cupomDesconto = 0.0;

function moeda(v) { return v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function lerSacola() {
    try { const s = JSON.parse(localStorage.getItem(SACOLA_KEY)); return Array.isArray(s) ? s : []; }
    catch (e) { return []; }
}
function itensParaCupom() {
    return lerSacola().map(i => ({ id: i.id_produto || i.id, quantidade: parseInt(i.quantidade) || 1 }));
}

/* ---------- CPF / CNPJ ---------- */
function validarCPF(c) {
    c = c.replace(/\D/g, '');
    if (c.length !== 11 || /^(\d)\1+$/.test(c)) return false;
    for (let t = 9; t < 11; t++) {
        let s = 0;
        for (let i = 0; i < t; i++) s += parseInt(c[i]) * (t + 1 - i);
        if (((s * 10) % 11) % 10 !== parseInt(c[t])) return false;
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
function validarDocumento(v) {
    const d = v.replace(/\D/g, '');
    return d.length === 11 ? validarCPF(d) : (d.length === 14 ? validarCNPJ(d) : false);
}

/* ---------- Máscaras (também aplicadas aos dados que já vêm preenchidos) ---------- */
function aplicarMascara(el, tipo) {
    if (!el) return;
    function formatar() {
        let v = el.value.replace(/\D/g, '');
        if (tipo === 'telefone') {
            if (v.length > 11) v = v.slice(0, 11);
            if (v.length > 6)      v = `(${v.slice(0, 2)}) ${v.slice(2, 7)}-${v.slice(7)}`;
            else if (v.length > 2) v = `(${v.slice(0, 2)}) ${v.slice(2)}`;
            else if (v.length > 0) v = `(${v}`;
        } else if (tipo === 'documento') {
            if (v.length > 14) v = v.slice(0, 14);
            if (v.length <= 11) {
                if (v.length > 9)      v = `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6, 9)}-${v.slice(9)}`;
                else if (v.length > 6) v = `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6)}`;
                else if (v.length > 3) v = `${v.slice(0, 3)}.${v.slice(3)}`;
            } else {
                v = `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}/${v.slice(8, 12)}-${v.slice(12)}`;
            }
        } else if (tipo === 'cep') {
            if (v.length > 8) v = v.slice(0, 8);
            if (v.length > 5) v = `${v.slice(0, 5)}-${v.slice(5)}`;
        }
        el.value = v;
        if (tipo === 'cep' && v.replace(/\D/g, '').length === 8) calcularFreteCheckout();
    }
    el.addEventListener('input', formatar);
    if (el.value) formatar();
}

document.addEventListener("DOMContentLoaded", function() {
    aplicarMascara(document.getElementById('checkout-telefone'), 'telefone');
    aplicarMascara(document.getElementById('checkout-cpf'), 'documento');
    aplicarMascara(document.getElementById('checkout-cep'), 'cep');

    const campoCupom = document.getElementById('chk-cupom-input');
    if (campoCupom) {
        campoCupom.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); aplicarCupomCheckout(); }
        });
    }

    carregarResumoCheckout();
    conferirEstoqueCheckout();
    carregarCupomCheckout();
    calcularFreteCheckout();   // CEP vindo do cadastro: já calcula o frete
});

function carregarResumoCheckout() {
    const sacola = lerSacola();
    const container = document.getElementById('checkout-itens-lista');

    if (sacola.length === 0) {
        window.location.href = 'index.php?pagina=sacola';
        return;
    }

    // Itens sem cor/tamanho (ex.: vindos dos favoritos) precisam ser definidos antes de pagar
    if (sacola.some(it => !it.cor || !it.tamanho)) {
        alert('Escolha a cor e o tamanho de todos os itens antes de finalizar a compra!');
        window.location.href = 'index.php?pagina=sacola';
        return;
    }

    let html = '';
    subtotalGlobal = 0;

    sacola.forEach(item => {
        const precoNum = parseFloat(item.preco) || 0;
        const qtdNum = parseInt(item.quantidade) || 1;
        const totalItem = precoNum * qtdNum;
        subtotalGlobal += totalItem;
        const codigoItem = item.id_produto || item.id;

        html += `
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom small">
                <div class="d-flex align-items-center gap-2">
                    <img src="${esc(item.imagem)}" style="width: 40px; height: 50px; object-fit: cover;" class="rounded border">
                    <div>
                        <span class="fw-semibold d-block text-dark">${esc(item.nome)}</span>
                        ${codigoItem ? `<span class="text-muted d-block" style="font-size: 0.72rem;">Cód. ${esc(codigoItem)}</span>` : ''}
                        <span class="text-muted">Qtd: ${qtdNum} | ${esc(item.tamanho)} / ${esc(item.cor)}</span>
                        ${item.promocao ? `<span class="badge bg-dark d-inline-block mt-1">🔥 ${esc(item.promocao)}</span>` : ''}
                    </div>
                </div>
                <div class="text-end">
                    ${item.preco_cheio ? `<span class="preco-cheio-riscado d-block small">R$ ${moeda(parseFloat(item.preco_cheio) * qtdNum)}</span>` : ''}
                    <strong class="${item.preco_cheio ? 'preco-promocional' : ''}">R$ ${moeda(totalItem)}</strong>
                </div>
            </div>`;
    });

    container.innerHTML = html;
    atualizarTotaisTela();
}

/* ---------- Estoque: confere antes de deixar finalizar ---------- */
// Se algum item passou do estoque (ou esgotou), volta para a sacola, que ajusta as quantidades e mostra o aviso.
function conferirEstoqueCheckout() {
    const sacola = lerSacola();
    const itens = sacola.filter(i => (i.id_produto || i.id) && i.cor && i.tamanho)
        .map(i => ({ id: i.id_produto || i.id, cor: i.cor, tamanho: i.tamanho }));
    if (!itens.length) return Promise.resolve(true);

    return fetch('acoes/estoque_sacola.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itens: itens })
    })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) return true;   // sem resposta: o servidor confere de novo ao gravar o pedido
        const chave = i => String(i.id_produto || i.id) + '|' + String(i.cor).trim().toLowerCase() + '|' + String(i.tamanho).trim().toLowerCase();
        const disp = {};
        const precoAtual = {};
        res.itens.forEach(i => {
            disp[chave(i)] = i.existe ? i.disponivel : 0;
            if (i.preco !== null && i.preco !== undefined) precoAtual[chave(i)] = i;
        });

        // Preços atualizados pelo servidor (ex.: promoção que começou/terminou): corrige a sacola e o resumo
        let mudouPreco = false;
        sacola.forEach(i => {
            const p = i.cor && i.tamanho ? precoAtual[chave(i)] : null;
            if (!p) return;
            if (Math.abs((parseFloat(i.preco) || 0) - parseFloat(p.preco)) >= 0.01) mudouPreco = true;
            i.preco = parseFloat(p.preco);
            i.preco_cheio = (p.preco_cheio && parseFloat(p.preco_cheio) > i.preco) ? parseFloat(p.preco_cheio) : null;
            i.promocao = p.promocao || null;
        });
        localStorage.setItem(SACOLA_KEY, JSON.stringify(sacola));
        carregarResumoCheckout();
        if (mudouPreco) {
            // Com o subtotal novo, o cupom (mínimo/valor) também precisa ser recalculado
            carregarCupomCheckout();
            alert('Os preços da sua sacola foram atualizados conforme as promoções vigentes. Confira o total antes de finalizar!');
        }

        // Soma linhas repetidas do mesmo produto/cor/tamanho antes de comparar
        const pedido = {};
        sacola.forEach(i => { if (i.cor && i.tamanho) pedido[chave(i)] = (pedido[chave(i)] || 0) + (parseInt(i.quantidade) || 1); });

        const problema = Object.keys(pedido).some(k => k in disp && pedido[k] > disp[k]);
        if (problema) {
            alert('Alguns itens da sua sacola não têm estoque suficiente. Vamos ajustar a sua sacola antes de finalizar!');
            window.location.href = 'index.php?pagina=sacola';
            return false;
        }
        // Preço mudou: não finaliza agora — o cliente confere o novo total e clica de novo
        return !mudouPreco;
    })
    .catch(() => true);
}

/* ---------- Cupom (digitado aqui ou vindo da sacola; o servidor sempre revalida) ---------- */
function mostrarAvisoCupom(texto) {
    const el = document.getElementById('chk-cupom-aviso');
    el.textContent = texto || '';
    el.classList.toggle('d-none', !texto);
}

function chamarValidacaoCupom(codigo) {
    return fetch(URL_VALIDAR_CUPOM, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: codigo, itens: itensParaCupom() })
    }).then(r => r.json());
}

// Cupom já guardado (por exemplo, aplicado na Sacola)
function carregarCupomCheckout() {
    let salvo = null;
    try { salvo = JSON.parse(localStorage.getItem(CUPOM_KEY)); } catch (e) {}
    if (!salvo || !salvo.codigo) return;

    chamarValidacaoCupom(salvo.codigo)
    .then(res => {
        if (res.ok) {
            cupomCodigo = res.codigo;
            cupomDesconto = res.desconto;
            // Há itens em promoção: o cupom só desconta os demais
            mostrarAvisoCupom(res.parcial ? res.mensagem : '');
        } else if (res.precisa_login) {
            // Mantém o cupom guardado: vale depois que o cliente entrar na conta
            cupomCodigo = null;
            cupomDesconto = 0;
            mostrarAvisoCupom('Entre na sua conta para usar o cupom "' + salvo.codigo + '"!');
        } else {
            localStorage.removeItem(CUPOM_KEY);
            cupomCodigo = null;
            cupomDesconto = 0;
            mostrarAvisoCupom('O cupom foi removido: ' + (res.mensagem || 'não é mais válido!'));
        }
        atualizarTotaisTela();
    })
    .catch(() => { /* sem conexão: segue sem desconto; o servidor revalida de qualquer forma */ });
}

// Cliente digita o cupom aqui no checkout
async function aplicarCupomCheckout() {
    const campo = document.getElementById('chk-cupom-input');
    const btn = document.getElementById('chk-cupom-btn');
    if (!campo) return;

    const codigo = campo.value.trim().replace(/^#/, '').toLowerCase();
    if (!codigo) { mostrarAvisoCupom('Digite o código do cupom!'); return; }

    btn.disabled = true;
    try {
        const res = await chamarValidacaoCupom(codigo);
        if (res.ok) {
            cupomCodigo = res.codigo;
            cupomDesconto = res.desconto;
            localStorage.setItem(CUPOM_KEY, JSON.stringify({ codigo: res.codigo }));
            campo.value = '';
            mostrarAvisoCupom(res.parcial ? res.mensagem : '');
        } else {
            mostrarAvisoCupom(res.mensagem || 'Cupom inválido!');
        }
    } catch (e) {
        mostrarAvisoCupom('Não foi possível validar o cupom agora. Tente novamente!');
    }
    btn.disabled = false;
    atualizarTotaisTela();
}

function removerCupomCheckout() {
    localStorage.removeItem(CUPOM_KEY);
    cupomCodigo = null;
    cupomDesconto = 0;
    mostrarAvisoCupom('');
    atualizarTotaisTela();
}

/* ---------- Pagamento, frete e totais ---------- */
function alternarPagamento(tipo) {
    document.getElementById('camposCartao').style.display = (tipo === 'cartao') ? 'block' : 'none';
    atualizarTotaisTela();
}

function calcularFreteCheckout() {
    const cep = document.getElementById('checkout-cep').value.replace(/\D/g, '');
    if (cep.length !== 8) { freteCalculado = false; atualizarTotaisTela(); return; }

    const prefixo = parseInt(cep.substring(0, 5));

    if (prefixo >= 80000 && prefixo <= 87999) {
        valorFreteTabela = FRETE.dentro_estado;
    } else if (prefixo >= 80000 && prefixo <= 99999) {
        valorFreteTabela = FRETE.regiao_sul;
    } else if ((prefixo >= 1000 && prefixo <= 39999) || (prefixo >= 10000 && prefixo <= 19999)) {
        valorFreteTabela = FRETE.regiao_sudeste;
    } else if (prefixo >= 76800 && prefixo <= 77999) {
        valorFreteTabela = FRETE.regiao_norte;          // RO e TO são Região Norte (mesma regra do servidor)
    } else if (prefixo >= 70000 && prefixo <= 79999) {
        valorFreteTabela = FRETE.regiao_centro_oeste;
    } else if (prefixo >= 40000 && prefixo <= 65999) {
        valorFreteTabela = FRETE.regiao_nordeste;
    } else {
        valorFreteTabela = FRETE.regiao_norte;
    }

    freteCalculado = true;
    atualizarTotaisTela();
}

// Regra de preço: subtotal = preço cheio (ou de promoção) → cupom → (frete grátis conforme valor já com cupom).
// Cartão paga esse valor; o Pix ganha mais 5% de desconto sobre ele (o frete não entra no desconto).
function calcularTotais() {
    const isPix = document.getElementById('pagPix').checked;
    const desconto = Math.min(cupomDesconto, subtotalGlobal);
    const base = Math.round((subtotalGlobal - desconto) * 100) / 100;

    let frete = 0;
    if (freteCalculado) frete = (base >= FRETE.minimo_gratis) ? 0 : valorFreteTabela;

    const descontoPix = isPix ? Math.round(base * PERC_DESCONTO_PIX * 100) / 100 : 0;
    const total = base + frete - descontoPix;

    return { isPix, subtotal: subtotalGlobal, cupom: desconto, base, frete, descontoPix, total };
}

function atualizarTotaisTela() {
    const t = calcularTotais();

    document.getElementById('chk-subtotal').innerText = 'R$ ' + moeda(t.subtotal);

    const cupomBox = document.getElementById('chk-cupom-box');
    const temCupom = !!cupomCodigo && t.cupom > 0;
    cupomBox.classList.toggle('d-none', !temCupom);
    cupomBox.classList.toggle('d-flex', temCupom);
    if (temCupom) {
        document.getElementById('chk-cupom-codigo').innerText = cupomCodigo;
        document.getElementById('chk-cupom-valor').innerText = '- R$ ' + moeda(t.cupom);
    }

    // Com cupom aplicado, esconde a caixa de digitar (para trocar de cupom, use "remover")
    const formCupom = document.getElementById('chk-cupom-form');
    if (formCupom) formCupom.classList.toggle('d-none', temCupom);

    document.getElementById('chk-frete').innerText = !freteCalculado ? 'Informe o CEP' : (t.frete === 0 ? 'Grátis' : 'R$ ' + moeda(t.frete));

    const descontoBox = document.getElementById('chk-desconto-box');
    descontoBox.classList.toggle('d-none', !t.isPix);
    descontoBox.classList.toggle('d-flex', t.isPix);
    if (t.isPix) document.getElementById('chk-desconto').innerText = '- R$ ' + moeda(t.descontoPix);

    document.getElementById('chk-total').innerText = 'R$ ' + moeda(t.total);
    atualizarParcelas(t.total);
}

/* ---------- Cartão de crédito (SIMULADO: nada do cartão sai do navegador) ---------- */
const PARCELAS_MAX = 10;          // mesma regra de funcoes.php e do servidor (salvar_pedido.php)
const PARCELA_MINIMA = 50.00;

function maxParcelas(total) {
    return Math.min(PARCELAS_MAX, Math.max(1, Math.floor(total / PARCELA_MINIMA)));
}

// Monta as opções 1x..Nx conforme o total; mantém a escolha do cliente quando ainda for válida
function atualizarParcelas(total) {
    const sel = document.getElementById('cartao-parcelas');
    if (!sel) return;
    const anterior = parseInt(sel.value) || 1;
    const max = maxParcelas(total);
    let html = '';
    for (let n = 1; n <= max; n++) {
        const valor = total / n;
        html += `<option value="${n}">${n}x de R$ ${moeda(valor)} sem juros${n === 1 ? ' (à vista)' : ''}</option>`;
    }
    sel.innerHTML = html;
    sel.value = String(Math.min(anterior, max));
}

function bandeiraCartao(num) {
    // Elo vem primeiro: alguns prefixos Elo começam com 4 ou 5 (que seriam Visa/Master)
    if (/^(4011|4312|4389|4514|4576|5041|5066|5067|509|6277|6362|6363|650|6516|6550)/.test(num)) return 'Elo';
    if (/^4/.test(num)) return 'Visa';
    if (/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/.test(num)) return 'Mastercard';
    if (/^3[47]/.test(num)) return 'Amex';
    if (/^(606282|3841)/.test(num)) return 'Hipercard';
    return '';
}

// Algoritmo de Luhn: confere o dígito verificador do número do cartão
function luhnValido(num) {
    let soma = 0, dobra = false;
    for (let i = num.length - 1; i >= 0; i--) {
        let d = parseInt(num[i]);
        if (dobra) { d *= 2; if (d > 9) d -= 9; }
        soma += d; dobra = !dobra;
    }
    return soma % 10 === 0;
}

function mascararCartao() {
    const campo = document.getElementById('cartao-numero');
    const num = campo.value.replace(/\D/g, '').slice(0, 19);
    const bandFinal = bandeiraCartao(num);
    // Amex: 4-6-5 | demais: grupos de 4
    campo.value = (bandFinal === 'Amex')
        ? num.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*/, (m, a, b, c) => [a, b, c].filter(Boolean).join(' '))
        : num.replace(/(\d{4})(?=\d)/g, '$1 ');
    document.getElementById('cartao-bandeira').textContent = bandFinal || '—';
    document.getElementById('cartao-cvv').maxLength = bandFinal === 'Amex' ? 4 : 3;
}

// Validade sempre no formato MM/AA (a barra entra sozinha; mês "2" vira "02")
function mascararValidade(e) {
    const campo = document.getElementById('cartao-validade');
    let v = campo.value.replace(/\D/g, '').slice(0, 4);
    if (v.length === 1 && parseInt(v) > 1) v = '0' + v;          // "5" → "05"
    if (v.length >= 3) v = v.slice(0, 2) + '/' + v.slice(2);
    else if (v.length === 2 && !(e && e.inputType === 'deleteContentBackward')) v = v + '/';
    campo.value = v;
}

function mostrarErroCartao(campo, msg) {
    const el = document.getElementById('erro-' + campo);
    const input = document.getElementById(campo);
    el.textContent = msg || '';
    input.classList.toggle('is-invalid', !!msg);
    input.classList.toggle('is-valid', !msg && input.value.trim() !== '');
}

/** Confere UM campo do cartão e devolve a mensagem de erro ('' = correto). */
function erroCampoCartao(id) {
    const num = document.getElementById('cartao-numero').value.replace(/\D/g, '');
    if (id === 'cartao-numero') {
        return (num.length < 13 || num.length > 19 || !luhnValido(num)) ? 'Número do cartão inválido. Confira os dígitos!' : '';
    }
    if (id === 'cartao-validade') {
        const m = document.getElementById('cartao-validade').value.match(/^(\d{2})\/(\d{2})$/);
        if (!m) return 'Informe a validade no formato MM/AA!';
        const mes = parseInt(m[1]), ano = 2000 + parseInt(m[2]);
        const hoje = new Date();
        if (mes < 1 || mes > 12) return 'Mês da validade inválido (use de 01 a 12)!';
        if (new Date(ano, mes, 0, 23, 59, 59) < hoje) return 'Este cartão está vencido!';   // vale até o último dia do mês
        if (ano > hoje.getFullYear() + 20) return 'Ano da validade inválido!';
        return '';
    }
    if (id === 'cartao-cvv') {
        const tam = bandeiraCartao(num) === 'Amex' ? 4 : 3;
        return document.getElementById('cartao-cvv').value.replace(/\D/g, '').length === tam ? '' : `O CVV deve ter ${tam} dígitos!`;
    }
    if (id === 'cartao-nome') {
        const nome = document.getElementById('cartao-nome').value.trim().replace(/\s+/g, ' ');
        return /^[A-Za-zÀ-ÿ]{2,}( [A-Za-zÀ-ÿ.]+)+$/.test(nome) ? '' : 'Informe o nome e o sobrenome como impressos no cartão!';
    }
    return '';
}

const CAMPOS_CARTAO = ['cartao-numero', 'cartao-validade', 'cartao-cvv', 'cartao-nome'];

/** Confere todos os dados do cartão. Retorna true se estiver tudo certo. */
function validarCartao() {
    let primeiroErro = null;
    CAMPOS_CARTAO.forEach(id => {
        const msg = erroCampoCartao(id);
        mostrarErroCartao(id, msg);
        if (msg && !primeiroErro) primeiroErro = id;
    });
    if (primeiroErro) document.getElementById(primeiroErro).focus();
    return !primeiroErro;
}

function limparCartao() {
    CAMPOS_CARTAO.forEach(id => {
        const el = document.getElementById(id);
        el.value = '';
        el.classList.remove('is-valid', 'is-invalid');
    });
    document.getElementById('cartao-bandeira').textContent = '—';
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('cartao-numero').addEventListener('input', mascararCartao);
    document.getElementById('cartao-validade').addEventListener('input', mascararValidade);
    document.getElementById('cartao-cvv').addEventListener('input', e => { e.target.value = e.target.value.replace(/\D/g, ''); });
    document.getElementById('cartao-nome').addEventListener('input', e => { e.target.value = e.target.value.replace(/[^A-Za-zÀ-ÿ. ]/g, ''); });
    // Confere cada campo ao sair dele (mostra o erro na hora, sem esperar o "Finalizar")
    CAMPOS_CARTAO.forEach(id =>
        document.getElementById(id).addEventListener('blur', () => {
            if (document.getElementById(id).value) mostrarErroCartao(id, erroCampoCartao(id));
        }));
});

/* ---------- Pix (SIMULADO): QR Code, Copia e Cola, prazo e confirmação ---------- */
let pixTimer = null;

function sairDoCheckout() {
    if (pixTimer) clearInterval(pixTimer);
    window.location.href = LOGADO ? 'index.php?pagina=minhas-compras' : 'index.php?pagina=home';
}

function abrirPagamentoPix(resultado) {
    const pix = resultado.pix;
    document.getElementById('pix-pedido').textContent = '#' + resultado.id_pedido;
    document.getElementById('pix-valor').textContent = 'R$ ' + moeda(resultado.total_geral);
    document.getElementById('pix-copia-cola').value = pix.copia_cola;
    document.getElementById('pix-recebedor').textContent = pix.recebedor;

    // Desenha o QR Code com o mesmo texto do Copia e Cola
    const box = document.getElementById('pix-qrcode');
    box.innerHTML = '';
    if (typeof QRCode !== 'undefined') {
        new QRCode(box, { text: pix.copia_cola, width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
    } else {
        box.innerHTML = '<p class="small text-muted m-3">Não foi possível desenhar o QR Code. Use o Pix Copia e Cola abaixo!</p>';
    }

    // Contagem regressiva do prazo de pagamento
    let restante = (parseInt(pix.prazo_minutos) || 30) * 60;
    const timerEl = document.getElementById('pix-timer');
    const mostrar = () => { timerEl.textContent = String(Math.floor(restante / 60)).padStart(2, '0') + ':' + String(restante % 60).padStart(2, '0'); };
    mostrar();
    pixTimer = setInterval(() => {
        restante--;
        mostrar();
        if (restante <= 0) {
            clearInterval(pixTimer);
            mostrarResultadoPix('⏰', 'O prazo do Pix expirou!',
                'Seu pedido continua registrado. Fale com a loja pelo WhatsApp para receber um novo código!');
        }
    }, 1000);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPix')).show();
}

function copiarPix() {
    const campo = document.getElementById('pix-copia-cola');
    const aviso = document.getElementById('pix-copiado');
    const ok = () => { aviso.textContent = '✓ Código copiado! Cole no app do seu banco!'; };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(campo.value).then(ok).catch(() => { campo.select(); document.execCommand('copy'); ok(); });
    } else {
        campo.select(); document.execCommand('copy'); ok();   // navegadores antigos / sem HTTPS
    }
}

function mostrarResultadoPix(icone, titulo, texto) {
    if (pixTimer) clearInterval(pixTimer);
    document.getElementById('pix-area-pagamento').classList.add('d-none');
    document.getElementById('pix-resultado').classList.remove('d-none');
    document.getElementById('pix-resultado-icone').textContent = icone;
    document.getElementById('pix-resultado-titulo').textContent = titulo;
    document.getElementById('pix-resultado-texto').textContent = texto;
}

function simularPagamentoPix() {
    const btn = document.getElementById('btn-simular-pix');
    btn.disabled = true;
    btn.textContent = 'Confirmando pagamento...';
    // Simula o tempo de resposta do banco
    setTimeout(() => mostrarResultadoPix('✅', 'Pagamento Pix aprovado (simulação)!',
        'Recebemos a confirmação do seu Pix de ' + document.getElementById('pix-valor').textContent
        + '! Em breve a loja entrará em contato para combinar a entrega!'), 1500);
}

function pagarPixDepois() {
    mostrarResultadoPix('🕒', 'Pedido aguardando pagamento!',
        'Seu pedido está registrado. Para pagar depois, fale com a loja pelo WhatsApp e peça o código Pix!');
}

async function processarPagamento(event) {
    event.preventDefault();

    // CPF/CNPJ obrigatório e válido (necessário para a nota fiscal)
    const campoCpf = document.getElementById('checkout-cpf');
    const erroCpf = document.getElementById('checkout-cpf-erro');
    if (!validarDocumento(campoCpf.value)) {
        erroCpf.classList.remove('d-none');
        campoCpf.focus();
        return;
    }
    erroCpf.classList.add('d-none');

    // Garante o frete mesmo se o campo CEP nunca perdeu o foco (ex.: preenchimento automático)
    calcularFreteCheckout();
    if (!freteCalculado) { alert('Informe um CEP válido para calcular o frete!'); return; }

    // Cartão: só segue se todos os dados estiverem corretos
    const pagandoCartao = document.getElementById('pagCartao').checked;
    if (pagandoCartao && !validarCartao()) {
        alert('Confira os dados do cartão destacados em vermelho!');
        return;
    }

    // Confere o estoque mais uma vez logo antes de enviar o pedido
    const btnFin = document.getElementById('btn-finalizar');
    btnFin.disabled = true;
    const estoqueOk = await conferirEstoqueCheckout();
    btnFin.disabled = false;
    if (!estoqueOk) return;

    const sacola = lerSacola();
    const formaPagamento = document.querySelector('input[name="formaPagamento"]:checked').value;
    const nomeCliente = document.getElementById('checkout-nome').value;
    const t = calcularTotais();

    const dadosPedido = {
        nome: nomeCliente,
        email: document.getElementById('checkout-email').value,
        telefone: document.getElementById('checkout-telefone').value,
        cpf_cnpj: campoCpf.value,
        cep: document.getElementById('checkout-cep').value,
        endereco: document.getElementById('checkout-endereco').value,
        numero: document.getElementById('checkout-numero').value,
        bairro: document.getElementById('checkout-bairro').value,
        complemento: document.getElementById('checkout-complemento').value,
        cidade: document.getElementById('checkout-cidade').value,
        estado: document.getElementById('checkout-estado').value,
        forma_pagamento: formaPagamento,
        // Do cartão vai SÓ o número de parcelas (o servidor confere a regra de 10x / mínimo R$ 50,00)
        parcelas: pagandoCartao ? (parseInt(document.getElementById('cartao-parcelas').value) || 1) : 1,
        subtotal: t.subtotal,
        cupom_codigo: cupomCodigo,          // o servidor revalida e recalcula o desconto
        desconto_cupom: t.cupom,
        frete: t.frete,
        desconto_pix: t.descontoPix,
        total_geral: t.total,
        itens: sacola
    };

    const btn = document.getElementById('btn-finalizar');
    btn.disabled = true;   // evita pedido duplicado por clique duplo

    try {
        const response = await fetch(URL_SALVAR_PEDIDO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dadosPedido)
        });
        const resultado = await response.json();

        if (resultado.sucesso) {
            // Dispara a notificação automática para o WhatsApp do lojista
            if (resultado.link_whatsapp) {
                window.open(resultado.link_whatsapp, '_blank');
            }

            let msg = 'Pedido #' + resultado.id_pedido + ' realizado com sucesso, ' + nomeCliente + '!';
            if (resultado.total_geral !== undefined) msg += '\nTotal: R$ ' + moeda(resultado.total_geral) + '!';
            if (pagandoCartao) {
                const p = resultado.parcelas || 1;
                const bandeira = document.getElementById('cartao-bandeira').textContent;
                const final4 = document.getElementById('cartao-numero').value.replace(/\D/g, '').slice(-4);
                msg += '\n\n💳 Pagamento aprovado (simulação)!'
                    + '\nCartão ' + (bandeira !== '—' ? bandeira + ' ' : '') + 'final ' + final4 + '!'
                    + '\n' + p + 'x de R$ ' + moeda(resultado.valor_parcela || resultado.total_geral) + ' sem juros!';
                limparCartao();   // os dados do cartão não ficam na tela
            }
            // O pedido já foi gravado: a sacola e o cupom podem ser limpos
            localStorage.removeItem(SACOLA_KEY);
            localStorage.removeItem(CUPOM_KEY);

            if (!pagandoCartao && resultado.pix && resultado.pix.copia_cola) {
                abrirPagamentoPix(resultado);   // mostra QR Code + Copia e Cola (sai do checkout depois)
                return;
            }
            alert(msg);
            sairDoCheckout();
        } else {
            if (resultado.cupom_invalido) {
                // O servidor recusou o cupom: tira do pedido e mostra o motivo
                removerCupomCheckout();
                mostrarAvisoCupom(resultado.erro || 'Cupom inválido!');
                alert('O cupom não pôde ser aplicado: ' + (resultado.erro || 'Cupom inválido!') + '\nConfira o novo total e finalize novamente!');
            } else if (resultado.estoque_insuficiente) {
                // Alguém comprou a peça entre a conferência e o envio: ajusta a sacola
                alert(resultado.erro + '\nVamos atualizar a sua sacola conforme o estoque!');
                window.location.href = 'index.php?pagina=sacola';
                return;
            } else {
                alert('Não foi possível registrar o pedido: ' + resultado.erro);
            }
            btn.disabled = false;
        }
    } catch (error) {
        console.error('Erro:', error);
        alert('Ocorreu um erro ao processar o seu pedido. Tente novamente!');
        btn.disabled = false;
    }
}
</script>