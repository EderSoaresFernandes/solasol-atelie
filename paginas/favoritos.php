<?php
?>

<div class="container my-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h2 class="fw-bold mb-0">❤️ Sua Lista de Desejos</h2>
        <button type="button" id="btn-todos-sacola" class="btn btn-dark btn-sm text-uppercase fw-bold px-3" style="display: none;">
            Adicionar todos à Sacola
        </button>
    </div>

    <div id="aviso-sacola"></div>

    <div id="lista-favoritos-container" class="row g-4">
    </div>

    <div id="vazio-msg" class="text-center py-5" style="display: none;">
        <p class="text-muted">A sua lista de desejos está vazia!</p>
        <a href="index.php" class="btn btn-dark btn-sm">Explorar Produtos</a>
    </div>
</div>

<script>
const FAVORITOS_KEY = 'solasol_favoritos';
const SACOLA_KEY = 'solasol_sacola';

function lerLista(chave) {
    try {
        return JSON.parse(localStorage.getItem(chave)) || [];
    } catch (e) {
        return [];
    }
}

function esc(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function formatarPreco(valor) {
    return (parseFloat(valor) || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function atualizarBadgeSacola() {
    const total = lerLista(SACOLA_KEY).reduce((soma, item) => soma + (parseInt(item.quantidade) || 1), 0);
    const badge = document.querySelector('a[href="index.php?pagina=sacola"] .badge');
    if (badge) badge.textContent = total;
}

function atualizarBadgeFavoritos() {
    const total = lerLista(FAVORITOS_KEY).length;
    const badge = document.getElementById('badge-favoritos-header');
    if (badge) {
        badge.textContent = total;
        badge.style.display = total > 0 ? 'inline-block' : 'none';
    }
}

function adicionarNaSacola(prod, quantidade = 1) {
    const sacola = lerLista(SACOLA_KEY);
    const cor = prod.cor || '';
    const tamanho = prod.tamanho || '';

    const existente = sacola.find(item => {
        const mesmoProduto = (item.id !== undefined && item.id !== null)
            ? String(item.id) === String(prod.id)
            : item.nome === prod.nome;
        return mesmoProduto && (item.cor || '') === cor && (item.tamanho || '') === tamanho;
    });

    if (existente) {
        existente.quantidade = (parseInt(existente.quantidade) || 1) + quantidade;
    } else {
        sacola.push({
            id: prod.id,
            nome: prod.nome,
            preco: parseFloat(prod.preco) || 0,
            imagem: prod.imagem,
            cor: cor,
            tamanho: tamanho,
            quantidade: quantidade
        });
    }

    localStorage.setItem(SACOLA_KEY, JSON.stringify(sacola));
}

function mostrarAviso(mensagem) {
    document.getElementById('aviso-sacola').innerHTML = `
        <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert">
            <span>${mensagem}</span>
            <a href="index.php?pagina=sacola" class="btn btn-dark btn-sm text-uppercase fw-semibold">Ver Sacola</a>
        </div>`;
}

function renderizarFavoritos() {
    const container = document.getElementById('lista-favoritos-container');
    const vazioMsg = document.getElementById('vazio-msg');
    const btnTodos = document.getElementById('btn-todos-sacola');
    const favoritos = lerLista(FAVORITOS_KEY);

    container.innerHTML = '';

    if (favoritos.length === 0) {
        vazioMsg.style.display = 'block';
        btnTodos.style.display = 'none';
        return;
    }

    vazioMsg.style.display = 'none';
    btnTodos.style.display = 'inline-block';

    favoritos.forEach(prod => {
        const codigoHtml = (prod.id !== undefined && prod.id !== null && prod.id !== '')
            ? `<p class="text-muted mb-1" style="font-size: 0.72rem;">Cód. ${esc(prod.id)}</p>`
            : '';

        const cardHtml = `
            <div class="col-6 col-md-3">
                <div class="card h-100 shadow-sm border-0">
                    <img src="${esc(prod.imagem)}" class="card-img-top" alt="${esc(prod.nome)}" style="height: 250px; object-fit: cover;">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fs-6 fw-semibold">${esc(prod.nome)}</h5>
                        ${codigoHtml}
                        <p class="text-dark fw-bold mb-3">R$ ${formatarPreco(prod.preco)}</p>
                        <div class="mt-auto">
                            <button type="button" class="btn btn-dark btn-sm w-100 text-uppercase fw-semibold mb-2"
                                    data-acao="sacola" data-id="${esc(prod.id)}">
                                Adicionar à Sacola
                            </button>
                            <div class="d-flex gap-2">
                                <a href="index.php?pagina=detalhe&id=${encodeURIComponent(prod.id)}" class="btn btn-outline-secondary btn-sm w-100">Ver Peça</a>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        data-acao="remover" data-id="${esc(prod.id)}">Remover</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', cardHtml);
    });
}

function removerFavorito(id) {
    const favoritos = lerLista(FAVORITOS_KEY).filter(item => String(item.id) !== String(id));
    localStorage.setItem(FAVORITOS_KEY, JSON.stringify(favoritos));
    document.getElementById('aviso-sacola').innerHTML = '';
    renderizarFavoritos();
    atualizarBadgeFavoritos();
}

document.addEventListener('DOMContentLoaded', () => {
    renderizarFavoritos();
    atualizarBadgeSacola();

    document.getElementById('lista-favoritos-container').addEventListener('click', (e) => {
        const botao = e.target.closest('button[data-acao]');
        if (!botao) return;

        const id = botao.dataset.id;

        if (botao.dataset.acao === 'remover') {
            removerFavorito(id);
            return;
        }

        if (botao.dataset.acao === 'sacola') {
            const prod = lerLista(FAVORITOS_KEY).find(item => String(item.id) === String(id));
            if (!prod) return;
            adicionarNaSacola(prod);
            atualizarBadgeSacola();
            mostrarAviso(`<strong>${esc(prod.nome)}</strong> foi adicionado à sua sacola!`);
        }
    });

    document.getElementById('btn-todos-sacola').addEventListener('click', () => {
        const favoritos = lerLista(FAVORITOS_KEY);
        if (favoritos.length === 0) return;

        favoritos.forEach(prod => adicionarNaSacola(prod));
        atualizarBadgeSacola();

        const qtd = favoritos.length;
        mostrarAviso(qtd === 1
            ? '1 peça da sua lista foi adicionada à sacola!'
            : `${qtd} peças da sua lista foram adicionadas à sacola!`);
    });
});
</script>