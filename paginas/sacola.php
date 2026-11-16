<?php
require_once 'funcoes.php';
?>

<div class="container my-5">
    <h2 class="text-uppercase fw-bold mb-4">Sua Sacola de Compras</h2>

    <div id="aviso-estoque"></div>

    <div class="row g-5">
        <div class="col-lg-8">
            <div id="sacola-conteudo" class="table-responsive">
                <div class="text-center py-5 text-muted" id="sacola-vazia-msg">
                    <p class="fs-5 mb-3">Sua sacola está vazia!</p>
                    <a href="index.php?pagina=feminino" class="btn btn-dark btn-sm text-uppercase fw-bold px-4 py-2">Escolher Produtos</a>
                </div>
            </div>
        </div>

        <div class="col-lg-4" id="resumo-pedido-box" style="display: none;">
            <div class="card bg-light border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3 border-bottom pb-2">Resumo do Pedido</h5>

                <div class="mb-3 pb-3 border-bottom">
                    <label for="input-cupom" class="form-label small fw-bold text-muted text-uppercase mb-1">Cupom de desconto</label>

                    <div id="cupom-form" class="input-group input-group-sm">
                        <input type="text" id="input-cupom" class="form-control text-uppercase" placeholder="Digite seu cupom" maxlength="40" autocomplete="off">
                        <button type="button" class="btn btn-dark fw-semibold text-uppercase" id="btn-aplicar-cupom" onclick="aplicarCupom()">Aplicar</button>
                    </div>

                    <div id="cupom-aplicado" class="d-none align-items-center justify-content-between bg-white border rounded px-2 py-1 small">
                        <span>🏷️ <strong id="cupom-aplicado-codigo"></strong> aplicado</span>
                        <button type="button" class="btn btn-link btn-sm text-danger p-0 text-decoration-none" onclick="removerCupom()">Remover</button>
                    </div>

                    <div id="cupom-msg" class="small mt-1"></div>
                </div>

                <div class="d-flex justify-content-between mb-2 small">
                    <span>Subtotal</span>
                    <strong id="subtotal-valor">R$ 0,00</strong>
                </div>

                <div class="d-flex justify-content-between mb-2 small d-none" id="linha-desconto">
                    <span class="text-success">Desconto</span>
                    <strong class="text-success" id="desconto-valor">- R$ 0,00</strong>
                </div>

                <div class="d-flex justify-content-between mb-3 small border-bottom pb-3">
                    <span>Frete</span>
                    <span class="text-muted">Calculado no checkout</span>
                </div>

                <div class="d-flex justify-content-between mb-1">
                    <span class="fw-bold fs-5">Total</span>
                    <strong class="fw-bold fs-5 text-dark" id="total-valor">R$ 0,00</strong>
                </div>
                <div class="d-flex justify-content-between mb-4 small text-success">
                    <span>ou no Pix (5% OFF)</span>
                    <strong id="total-pix-valor">R$ 0,00</strong>
                </div>

                <button type="button" class="btn btn-dark w-100 py-3 fw-bold text-uppercase d-flex align-items-center justify-content-center gap-2 mb-2" onclick="avancarParaCheckout()">
                    <span>Avançar para o Checkout</span>
                </button>
                <a href="index.php?pagina=feminino" class="btn btn-outline-secondary w-100 btn-sm text-uppercase fw-semibold">Continuar Comprando</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVariacoes" tabindex="-1" aria-labelledby="modalVariacoesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalVariacoesLabel">Selecionar Cor e Tamanho</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-item-index">
                <input type="hidden" id="modal-cor-selecionada">

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">1. Escolha a Cor:</label>
                    <div id="lista-cores-container" class="d-flex flex-wrap gap-2 mt-1"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">2. Escolha o Tamanho:</label>
                    <div id="lista-tamanhos-container" class="d-flex flex-wrap gap-2 mt-1">
                        <span class="text-muted small fst-italic">Primeiro selecione uma cor acima...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-dark btn-sm fw-bold px-4" id="btn-salvar-variacao" onclick="salvarVariacoes()" disabled>Confirmar Alteração</button>
            </div>
        </div>
    </div>
</div>

<script>
const SACOLA_KEY = 'solasol_sacola';
const PERC_DESCONTO_PIX_SC = <?= json_encode(PERC_DESCONTO_PIX); ?>;   // definido em funcoes.php
const CUPOM_KEY  = 'solasol_cupom';

// Mesmas cores do detalhe.php (a busca ignora maiúsculas/minúsculas)
const MAPA_CORES = {
    'bege': '#d8ccc0', 'azul': '#0056b3', 'azul claro': '#add8e6', 'azul escuro': '#1b263b',
    'preto': '#111111', 'branco': '#ffffff', 'vermelho': '#dc3545', 'rosa': '#ffc0cb',
    'verde': '#28a745', 'verde musgo': '#556b2f', 'cinza': '#6c757d', 'marrom': '#8b4513',
    'amarelo': '#ffc107', 'nude': '#e3bc9a', 'jeans': '#3b5998', 'manteiga': '#fdf4dc',
    'off-white': '#f8f9fa', 'terracota': '#e2725b'
};

let variacoesProdutoAtual = { cores: [], tamanhos_por_cor: {}, estoque: {} };
let cupomReq = 0; // evita que respostas antigas sobrescrevam as novas

// Estoque disponível de cada item da sacola, vindo do servidor: chave "id|cor|tamanho" → quantidade
let estoqueSacola = {};

document.addEventListener("DOMContentLoaded", () => {
    carregarSacola();
    sincronizarEstoque();
});

/* ---------- Estoque ---------- */
function chaveEstoque(it) {
    return String(idDoItem(it)) + '|' + String(it.cor || '').trim().toLowerCase() + '|' + String(it.tamanho || '').trim().toLowerCase();
}
function disponivelDe(it) {
    const k = chaveEstoque(it);
    return Object.prototype.hasOwnProperty.call(estoqueSacola, k) ? estoqueSacola[k] : null; // null = ainda não consultado
}

function mostrarAvisoEstoque(linhas) {
    const box = document.getElementById('aviso-estoque');
    if (!linhas.length) { box.innerHTML = ''; return; }
    box.innerHTML = `<div class="alert alert-warning small"><strong>Atualizamos a sua sacola conforme o estoque:</strong>
        <ul class="mb-0 mt-1">${linhas.map(l => `<li>${l}</li>`).join('')}</ul></div>`;
}

/**
 * Consulta o estoque de todos os itens no servidor, junta linhas repetidas (mesmo produto/cor/tamanho)
 * e reduz quantidades acima do disponível. Itens esgotados ficam marcados e bloqueiam o checkout.
 */
function sincronizarEstoque() {
    let sacola = lerSacola();

    // Junta itens repetidos (podem vir da página do produto e dos favoritos)
    const juntos = [];
    sacola.forEach(it => {
        const igual = (it.cor && it.tamanho) ? juntos.find(j => j.cor && j.tamanho && chaveEstoque(j) === chaveEstoque(it)) : null;
        if (igual) igual.quantidade = intval(igual.quantidade) + intval(it.quantidade);
        else juntos.push(it);
    });
    sacola = juntos;

    const consultar = sacola.filter(it => idDoItem(it) && it.cor && it.tamanho)
        .map(it => ({ id: idDoItem(it), cor: it.cor, tamanho: it.tamanho }));
    if (!consultar.length) { salvarSacola(sacola); carregarSacola(); return Promise.resolve(); }

    return fetch('acoes/estoque_sacola.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itens: consultar })
    })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) return;
        estoqueSacola = {};
        const precoAtual = {};
        res.itens.forEach(i => {
            estoqueSacola[chaveEstoque(i)] = i.existe ? i.disponivel : 0;
            if (i.preco !== null && i.preco !== undefined) precoAtual[chaveEstoque(i)] = i;
        });

        const avisos = [];
        // Preços sempre atualizados pelo servidor (promoção que começou/terminou, preço alterado no painel)
        sacola.forEach(it => {
            const p = precoAtual[chaveEstoque(it)];
            if (!p) return;
            const antes = parseFloat(it.preco) || 0;
            const novo = parseFloat(p.preco);
            if (Math.abs(antes - novo) >= 0.01) {
                avisos.push(`<strong>${esc(it.nome)}</strong>: preço atualizado de R$ ${formatarMoeda(antes)} para R$ ${formatarMoeda(novo)}${p.promocao ? ' (promoção ' + esc(p.promocao) + ')' : ''}!`);
            }
            it.preco = novo;
            it.preco_cheio = (p.preco_cheio && parseFloat(p.preco_cheio) > novo) ? parseFloat(p.preco_cheio) : null;
            it.promocao = p.promocao || null;
        });

        sacola.forEach(it => {
            const disp = disponivelDe(it);
            if (disp === null || !it.cor || !it.tamanho) return;
            const desc = `<strong>${esc(it.nome)}</strong> (${esc(it.cor)} / ${esc(it.tamanho)})`;
            if (disp <= 0) {
                avisos.push(`${desc}: esgotado. Remova o item ou escolha outra cor/tamanho!`);
            } else if (intval(it.quantidade) > disp) {
                it.quantidade = disp;
                avisos.push(`${desc}: quantidade ajustada para ${disp} (${disp === 1 ? 'última unidade' : 'disponíveis em estoque'})!`);
            }
        });
        salvarSacola(sacola);
        mostrarAvisoEstoque(avisos);
        carregarSacola();
    })
    .catch(err => console.error('Erro ao consultar estoque:', err));
}

/* ---------- Utilitários ---------- */
function corHex(nome) { return MAPA_CORES[String(nome).trim().toLowerCase()] || '#cccccc'; }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function intval(val) { return parseInt(val) || 1; }
function formatarMoeda(v) { return v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

// Favoritos e detalhe salvam "id"; aceitamos os dois nomes
function idDoItem(item) { return item.id_produto || item.id || null; }

function lerSacola() {
    try {
        const s = JSON.parse(localStorage.getItem(SACOLA_KEY));
        return Array.isArray(s) ? s : [];
    } catch (e) { return []; }
}
function salvarSacola(s) { localStorage.setItem(SACOLA_KEY, JSON.stringify(s)); }

function atualizarBadgeSacola() {
    const total = lerSacola().reduce((soma, item) => soma + intval(item.quantidade), 0);
    const badge = document.querySelector('a[href="index.php?pagina=sacola"] .badge');
    if (badge) badge.textContent = total;
}

/* ---------- Render da sacola ---------- */
function carregarSacola() {
    const sacola = lerSacola();
    const container = document.getElementById('sacola-conteudo');
    const resumoBox = document.getElementById('resumo-pedido-box');

    if (sacola.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <p class="fs-5 mb-3">Sua sacola está vazia!</p>
                <a href="index.php?pagina=feminino" class="btn btn-dark btn-sm text-uppercase fw-bold px-4 py-2">Escolher Produtos</a>
            </div>`;
        if (resumoBox) resumoBox.style.display = 'none';
        atualizarBadgeSacola();
        return;
    }

    if (resumoBox) resumoBox.style.display = 'block';

    let html = `
        <table class="table align-middle tabela-sacola">
            <thead class="table-light small text-uppercase">
                <tr><th>Produto</th><th>Preço</th><th>Qtd</th><th>Subtotal</th><th class="text-end">Ação</th></tr>
            </thead>
            <tbody>`;

    let subtotalGeral = 0;

    sacola.forEach((item, index) => {
        const precoNum = parseFloat(item.preco) || 0;
        const qtdNum = intval(item.quantidade);
        const totalItem = precoNum * qtdNum;
        const codigoItem = idDoItem(item);
        subtotalGeral += totalItem;

        const temCorTam = !!(item.cor && item.tamanho);
        const disp = temCorTam ? disponivelDe(item) : null;
        const esgotado = disp !== null && disp <= 0;
        const noLimite = disp !== null && qtdNum >= disp;

        let linhaEstoque = '';
        if (esgotado) linhaEstoque = `<small class="text-danger fw-semibold d-block mb-1">Esgotado nesta cor/tamanho</small>`;
        else if (disp !== null && disp <= 3) linhaEstoque = `<small class="text-warning-emphasis d-block mb-1">${disp === 1 ? 'Última unidade!' : 'Restam ' + disp + ' unidades'}</small>`;

        const linhaDetalhes = (temCorTam
            ? `<small class="text-muted d-block mb-1">Cor: <span class="text-capitalize fw-semibold text-dark">${esc(item.cor)}</span> | Tam: <strong class="text-dark">${esc(item.tamanho)}</strong></small>`
            : `<small class="text-danger d-block mb-1">Cor e Tamanho não definidos</small>`) + linhaEstoque
            + (item.promocao ? `<span class="badge bg-dark mb-1">🔥 ${esc(item.promocao)}</span>` : '');

        const textoBotao = temCorTam ? 'Alterar Cor e Tamanho' : 'Escolher Cor e Tamanho';
        const classeBotao = temCorTam ? 'btn-outline-secondary' : 'btn-primary';

        html += `
            <tr data-index="${index}" class="${esgotado ? 'table-danger' : ''}">
                <td class="sc-produto">
                    <div class="d-flex align-items-center gap-3">
                        <img src="${esc(item.imagem)}" alt="${esc(item.nome)}" style="width: 60px; height: 75px; object-fit: cover;" class="rounded border flex-shrink-0">
                        <div>
                            <h6 class="fw-bold mb-1 text-dark fs-6">${esc(item.nome)}</h6>
                            ${codigoItem ? `<small class="text-muted d-block mb-1">Cód. ${esc(codigoItem)}</small>` : ''}
                            ${linhaDetalhes}
                            <button type="button" class="btn ${classeBotao} btn-sm mt-1 fw-semibold text-uppercase" style="font-size: 0.75rem;" onclick="abrirModalVariacoes(${index})">${textoBotao}</button>
                        </div>
                    </div>
                </td>
                <td class="sc-preco">${item.preco_cheio ? `<span class="preco-cheio-riscado d-block small">R$ ${formatarMoeda(parseFloat(item.preco_cheio))}</span><span class="preco-promocional">` : ''}R$ ${formatarMoeda(precoNum)}${item.preco_cheio ? '</span>' : ''}</td>
                <td class="sc-qtd">
                    <div class="input-group input-group-sm" style="width: 100px;">
                        <button class="btn btn-outline-secondary" type="button" onclick="alterarQtd(${index}, -1)">-</button>
                        <input type="text" class="form-control text-center bg-white" value="${qtdNum}" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="alterarQtd(${index}, 1)" ${noLimite ? 'disabled title="Quantidade máxima em estoque"' : ''}>+</button>
                    </div>
                </td>
                <td class="fw-semibold sc-subtotal">R$ ${formatarMoeda(totalItem)}</td>
                <td class="text-end sc-acao">
                    <button class="btn btn-link text-danger p-0 text-decoration-none small" onclick="removerItem(${index})">Remover</button>
                </td>
            </tr>`;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;

    atualizarResumo(subtotalGeral);
    atualizarBadgeSacola();
}

function alterarQtd(index, delta) {
    const sacola = lerSacola();
    if (!sacola[index]) return;
    let nova = Math.max(1, intval(sacola[index].quantidade) + delta);
    const disp = disponivelDe(sacola[index]);
    if (disp !== null && disp > 0 && nova > disp) nova = disp;   // não passa do estoque
    sacola[index].quantidade = nova;
    salvarSacola(sacola);
    carregarSacola();
}

function removerItem(index) {
    const sacola = lerSacola();
    sacola.splice(index, 1);
    salvarSacola(sacola);
    carregarSacola();
}

/* ---------- Modal de cor e tamanho ---------- */
function abrirModalVariacoes(index) {
    const item = lerSacola()[index];
    if (!item) return;

    document.getElementById('modal-item-index').value = index;
    document.getElementById('modal-cor-selecionada').value = '';
    document.getElementById('btn-salvar-variacao').disabled = true;
    document.getElementById('lista-cores-container').innerHTML = '<span class="text-muted small fst-italic">Carregando cores...</span>';
    document.getElementById('lista-tamanhos-container').innerHTML = '<span class="text-muted small fst-italic">Primeiro selecione uma cor acima...</span>';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVariacoes')).show();

    const idProduto = idDoItem(item);
    if (!idProduto) {
        document.getElementById('lista-cores-container').innerHTML = '<span class="text-danger small">Não foi possível identificar este produto. Remova-o e adicione novamente!</span>';
        return;
    }

    // Busca SOMENTE o que existe em estoque para este produto
    fetch(`acoes/buscar_variacoes.php?id_produto=${encodeURIComponent(idProduto)}`)
        .then(r => r.json())
        .then(data => {
            variacoesProdutoAtual = {
                cores: data.cores || [],
                tamanhos_por_cor: data.tamanhos_por_cor || {},
                estoque: data.estoque || {}
            };

            // Só pré-seleciona se a cor/tamanho atuais ainda existirem no estoque
            const corAtual = variacoesProdutoAtual.cores.includes(item.cor) ? item.cor : '';
            renderizarCoresModal(corAtual);

            if (corAtual) {
                document.getElementById('modal-cor-selecionada').value = corAtual;
                renderizarTamanhosModal(corAtual, item.tamanho || '');
            }
        })
        .catch(err => {
            console.error('Erro ao buscar variações:', err);
            document.getElementById('lista-cores-container').innerHTML = '<span class="text-danger small">Erro ao carregar variações. Tente novamente!</span>';
        });
}

function renderizarCoresModal(corAtual) {
    const box = document.getElementById('lista-cores-container');
    box.innerHTML = '';

    if (variacoesProdutoAtual.cores.length === 0) {
        box.innerHTML = '<span class="text-muted small">Este produto está sem estoque no momento!</span>';
        return;
    }

    variacoesProdutoAtual.cores.forEach(cor => {
        const btn = document.createElement('div');
        btn.className = 'btn-cor-opcao' + (cor === corAtual ? ' active' : '');
        btn.innerHTML = `<span class="circulo-cor" style="background-color:${corHex(cor)};"></span><span class="small fw-semibold text-capitalize">${esc(cor)}</span>`;

        btn.onclick = function () {
            document.querySelectorAll('.btn-cor-opcao').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('modal-cor-selecionada').value = cor;
            document.getElementById('btn-salvar-variacao').disabled = true;
            renderizarTamanhosModal(cor, '');
        };
        box.appendChild(btn);
    });
}

function renderizarTamanhosModal(cor, tamanhoAtual) {
    const box = document.getElementById('lista-tamanhos-container');
    box.innerHTML = '';

    const tamanhos = variacoesProdutoAtual.tamanhos_por_cor[cor] || [];
    if (tamanhos.length === 0) {
        box.innerHTML = '<span class="text-muted small">Nenhum tamanho disponível para esta cor!</span>';
        return;
    }

    tamanhos.forEach(tam => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn-tamanho-opcao' + (tam === tamanhoAtual ? ' active' : '');
        btn.textContent = tam;
        btn.dataset.tamanho = tam;
        const qtdTam = (variacoesProdutoAtual.estoque[cor] || {})[tam];
        if (qtdTam !== undefined) btn.title = qtdTam === 1 ? 'Última unidade' : qtdTam + ' unidades disponíveis';
        btn.onclick = function () {
            document.querySelectorAll('.btn-tamanho-opcao').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('btn-salvar-variacao').disabled = false;
        };
        box.appendChild(btn);
    });

    // Se já havia um tamanho válido selecionado, libera o botão de confirmar
    if (tamanhoAtual && tamanhos.includes(tamanhoAtual)) {
        document.getElementById('btn-salvar-variacao').disabled = false;
    }
}

function salvarVariacoes() {
    const index = parseInt(document.getElementById('modal-item-index').value);
    const cor = document.getElementById('modal-cor-selecionada').value;
    const ativo = document.querySelector('.btn-tamanho-opcao.active');
    const tamanho = ativo ? (ativo.dataset.tamanho || ativo.textContent) : '';

    if (!cor || !tamanho) {
        alert('Por favor, selecione a cor e o tamanho!');
        return;
    }

    const sacola = lerSacola();
    const item = sacola[index];
    if (!item) return;

    item.id_produto = idDoItem(item);
    item.cor = cor;
    item.tamanho = tamanho;
    item.uniqueKey = item.id_produto + '-' + cor + '-' + tamanho;

    // Se já existe outro item igual (mesmo produto, cor e tamanho), junta as quantidades
    const outro = sacola.findIndex((it, i) => i !== index && it.uniqueKey === item.uniqueKey);
    let alvo = item;
    if (outro > -1) {
        sacola[outro].quantidade = intval(sacola[outro].quantidade) + intval(item.quantidade);
        sacola.splice(index, 1);
        alvo = sacola[outro > index ? outro - 1 : outro];
    }

    // Não deixa a quantidade passar do estoque da nova cor/tamanho
    const dispNovo = parseInt((variacoesProdutoAtual.estoque[cor] || {})[tamanho]);
    if (!isNaN(dispNovo) && intval(alvo.quantidade) > dispNovo) {
        alvo.quantidade = dispNovo;
        alert(`Restam apenas ${dispNovo} unidade(s) nesta cor/tamanho. A quantidade foi ajustada!`);
    }

    salvarSacola(sacola);
    bootstrap.Modal.getInstance(document.getElementById('modalVariacoes')).hide();
    sincronizarEstoque();   // atualiza o estoque mostrado de todos os itens
}

/* ---------- Cupom de desconto ---------- */
function itensParaServidor() {
    return lerSacola().map(i => ({ id: idDoItem(i), quantidade: intval(i.quantidade) })).filter(i => i.id);
}

function consultarCupom(codigo) {
    return fetch('acoes/validar_cupom.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: codigo, itens: itensParaServidor() })
    }).then(r => r.json());
}

function mostrarMsgCupom(texto, ok) {
    const el = document.getElementById('cupom-msg');
    el.className = 'small mt-1 ' + (ok ? 'text-success' : 'text-danger');
    el.textContent = texto || '';
}

function aplicarCupom() {
    const codigo = document.getElementById('input-cupom').value.trim().toUpperCase();
    if (!codigo) { mostrarMsgCupom('Digite o código do cupom!', false); return; }

    const btn = document.getElementById('btn-aplicar-cupom');
    btn.disabled = true;

    consultarCupom(codigo)
        .then(res => {
            if (res.ok) {
                localStorage.setItem(CUPOM_KEY, JSON.stringify({ codigo: res.codigo }));
                carregarSacola();
                mostrarMsgCupom(res.mensagem, true);
            } else {
                mostrarMsgCupom(res.mensagem || 'Cupom inválido!', false);
            }
        })
        .catch(() => mostrarMsgCupom('Não foi possível validar o cupom. Tente novamente!', false))
        .finally(() => { btn.disabled = false; });
}

function removerCupom() {
    localStorage.removeItem(CUPOM_KEY);
    document.getElementById('input-cupom').value = '';
    mostrarMsgCupom('', true);
    carregarSacola();
}

function lerCupomSalvo() {
    try { return JSON.parse(localStorage.getItem(CUPOM_KEY)); } catch (e) { return null; }
}

function mostrarCupomUI(aplicado, codigo) {
    document.getElementById('cupom-form').classList.toggle('d-none', aplicado);
    const box = document.getElementById('cupom-aplicado');
    box.classList.toggle('d-none', !aplicado);
    box.classList.toggle('d-flex', aplicado);
    if (aplicado) document.getElementById('cupom-aplicado-codigo').textContent = codigo;
}

function mostrarTotais(subtotal, desconto) {
    document.getElementById('subtotal-valor').innerText = 'R$ ' + formatarMoeda(subtotal);
    document.getElementById('linha-desconto').classList.toggle('d-none', !(desconto > 0));
    document.getElementById('desconto-valor').innerText = '- R$ ' + formatarMoeda(desconto);
    const total = Math.max(0, Math.round((subtotal - desconto) * 100) / 100);
    document.getElementById('total-valor').innerText = 'R$ ' + formatarMoeda(total);
    // Pix: mais 5% sobre o total já com promoção e cupom (frete à parte, calculado no checkout)
    document.getElementById('total-pix-valor').innerText = 'R$ ' + formatarMoeda(Math.round(total * (1 - PERC_DESCONTO_PIX_SC) * 100) / 100);
}

// Mostra o subtotal e, se houver cupom salvo, revalida no servidor (preços, mínimo, validade)
function atualizarResumo(subtotal) {
    mostrarTotais(subtotal, 0);
    const salvo = lerCupomSalvo();
    if (!salvo || !salvo.codigo) { mostrarCupomUI(false); return; }

    const req = ++cupomReq;
    consultarCupom(salvo.codigo)
        .then(res => {
            if (req !== cupomReq) return;
            if (res.ok) {
                mostrarCupomUI(true, res.codigo);
                mostrarTotais(subtotal, res.desconto);
                // Há itens em promoção: avisa que o cupom só vale para os demais
                mostrarMsgCupom(res.parcial ? res.mensagem : '', true);
            } else {
                // Cupom deixou de valer (expirou, pedido abaixo do mínimo, etc.)
                localStorage.removeItem(CUPOM_KEY);
                mostrarCupomUI(false);
                mostrarMsgCupom(res.mensagem || 'Cupom removido!', false);
            }
        })
        .catch(() => { /* sem conexão: mantém o subtotal sem desconto */ });
}

/* ---------- Checkout ---------- */
function avancarParaCheckout() {
    const sacola = lerSacola();
    if (sacola.length === 0) return;

    const pendentes = sacola.map((it, i) => (it.cor && it.tamanho) ? -1 : i).filter(i => i >= 0);
    if (pendentes.length > 0) {
        alert('Escolha a cor e o tamanho de todos os itens antes de continuar!');
        const linha = document.querySelector(`tr[data-index="${pendentes[0]}"]`);
        if (linha) linha.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // Confere o estoque de novo na hora de avançar (pode ter mudado enquanto a página estava aberta)
    sincronizarEstoque().then(() => {
        const atual = lerSacola();
        const esgotados = atual.map((it, i) => (disponivelDe(it) === 0) ? i : -1).filter(i => i >= 0);
        if (esgotados.length > 0) {
            alert('Há itens esgotados na sua sacola. Remova-os ou escolha outra cor/tamanho para continuar!');
            const linha = document.querySelector(`tr[data-index="${esgotados[0]}"]`);
            if (linha) linha.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        window.location.href = 'index.php?pagina=checkout';
    });
}
</script>