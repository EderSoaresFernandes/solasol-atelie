<?php
/* =====================================================================
   funcoes_catalogo.php — Catálogo das vitrines em UM ÚNICO ARRAY + funções de processamento
   (requisitos da disciplina Tech Forge)

   - Armazenamento estruturado: todos os produtos da vitrine ficam no array $catalogo
     (nada de $produto1, $produto2...). Cada item é um array associativo com as mesmas chaves.
   - Funções de processamento: recebem os dados por PARÂMETRO e devolvem o resultado com RETURN
     (nenhuma usa "global").
   - Pesquisa/filtro: filtrarPorFaixaDePreco() e buscarProdutoPorCodigo() procuram DENTRO do array.
   - Validação com if/else: nenhuma função processa array vazio, preço negativo ou faixa invertida.
   ===================================================================== */
require_once __DIR__ . '/funcoes.php';

/** Faixas de preço oferecidas na vitrine: chave da URL => [rótulo, mínimo, máximo (null = sem limite)] */
const FAIXAS_PRECO = [
    'ate150'    => ['Até R$ 150',         0.00,   150.00],
    '150a200'   => ['R$ 150 a R$ 200',    150.01, 200.00],
    'acima200'  => ['Acima de R$ 200',    200.01, null],
];

/** Ordenações oferecidas na vitrine: chave da URL => rótulo */
const ORDENACOES_CATALOGO = [
    'recentes'   => 'Mais recentes',
    'menorpreco' => 'Menor preço',
    'maiorpreco' => 'Maior preço',
];

/**
 * Lê os produtos de UMA categoria e devolve o catálogo num único array.
 * Cada item: id, nome, preco (valor cobrado: cheio ou com promoção), precos (todos os preços),
 * parcelas_max, foto, foto2 e tamanhos (só os que têm estoque).
 */
function carregarCatalogo($conn, string $categoria): array
{
    $catalogo = [];
    if (trim($categoria) === '') {
        return $catalogo;
    }

    $stmt = $conn->prepare("SELECT * FROM produto WHERE categoria = ? ORDER BY id_produto DESC");
    $stmt->bind_param("s", $categoria);
    $stmt->execute();
    $res = $stmt->get_result();

    $stmtTam = $conn->prepare("SELECT DISTINCT tamanho FROM produto_cores_tamanhos WHERE produto_id = ? AND quantidade > 0");

    while ($linha = $res->fetch_assoc()) {
        $id = (int)$linha['id_produto'];

        $tamanhos = [];
        $stmtTam->bind_param("i", $id);
        $stmtTam->execute();
        $resTam = $stmtTam->get_result();
        while ($t = $resTam->fetch_assoc()) {
            $tamanhos[] = $t['tamanho'];
        }

        $precos = precosProduto($conn, $linha);
        $catalogo[] = [
            'id'           => $id,
            'nome'         => (string)$linha['nome'],
            'preco'        => (float)$precos['original'],
            'precos'       => $precos,
            'parcelas_max' => (int)($linha['parcelas_max'] ?? 10),
            'foto'         => !empty($linha['imagem_url']) ? $linha['imagem_url'] : 'imagens/logoSolasolPreto.jpeg',
            'foto2'        => segundaFotoProduto($conn, $id, $linha['imagem_url'] ?? ''),
            'tamanhos'     => $tamanhos,
        ];
    }
    $stmt->close();
    $stmtTam->close();
    return $catalogo;
}

/**
 * Confere se os dados do catálogo são consistentes antes de processá-los.
 * Devolve a lista de problemas encontrados (vazia = tudo certo).
 */
function validarCatalogo(array $catalogo): array
{
    $erros = [];
    if (empty($catalogo)) {
        $erros[] = 'O catálogo está vazio.';
        return $erros;
    }
    foreach ($catalogo as $item) {
        if (!isset($item['id'], $item['preco'])) {
            $erros[] = 'Item sem código ou sem preço.';
        } elseif ($item['preco'] < 0) {
            $erros[] = 'O produto ' . $item['id'] . ' está com preço negativo.';
        }
    }
    return $erros;
}

/**
 * FILTRO: devolve só os produtos com preço entre $minimo e $maximo ($maximo null = sem limite).
 * Não processa se o catálogo estiver vazio, se houver valor negativo ou se a faixa estiver invertida.
 */
function filtrarPorFaixaDePreco(array $catalogo, float $minimo, ?float $maximo = null): array
{
    if (empty($catalogo)) {
        return [];
    }
    if ($minimo < 0 || ($maximo !== null && $maximo < 0)) {
        return [];
    }
    if ($maximo !== null && $minimo > $maximo) {
        return [];
    }

    $filtrados = [];
    foreach ($catalogo as $item) {
        if ($item['preco'] < 0) {
            continue;   // item inconsistente nunca entra no resultado
        }
        if ($item['preco'] >= $minimo && ($maximo === null || $item['preco'] <= $maximo)) {
            $filtrados[] = $item;
        }
    }
    return $filtrados;
}

/** PESQUISA: devolve o produto com o código informado, ou null se não existir no catálogo. */
function buscarProdutoPorCodigo(array $catalogo, int $codigo): ?array
{
    if (empty($catalogo) || $codigo <= 0) {
        return null;
    }
    foreach ($catalogo as $item) {
        if ($item['id'] === $codigo) {
            return $item;
        }
    }
    return null;
}

/** Ordena o catálogo pelo critério escolhido (chave de ORDENACOES_CATALOGO). */
function ordenarCatalogo(array $catalogo, string $ordem): array
{
    if (count($catalogo) < 2) {
        return $catalogo;
    }
    if ($ordem === 'menorpreco') {
        usort($catalogo, fn($a, $b) => $a['preco'] <=> $b['preco']);
    } elseif ($ordem === 'maiorpreco') {
        usort($catalogo, fn($a, $b) => $b['preco'] <=> $a['preco']);
    } else {
        usort($catalogo, fn($a, $b) => $b['id'] <=> $a['id']);
    }
    return $catalogo;
}

/** Quantos produtos do catálogo cabem em cada faixa de preço (para mostrar nos botões). */
function contarPorFaixa(array $catalogo, array $faixas): array
{
    $contagem = [];
    foreach ($faixas as $chave => $faixa) {
        $contagem[$chave] = count(filtrarPorFaixaDePreco($catalogo, $faixa[1], $faixa[2]));
    }
    return $contagem;
}

/** Resumo do catálogo: quantidade, menor e maior preço. Catálogo vazio devolve zeros. */
function resumoCatalogo(array $catalogo): array
{
    if (empty($catalogo)) {
        return ['quantidade' => 0, 'menor' => 0.0, 'maior' => 0.0];
    }
    $precos = array_column($catalogo, 'preco');
    return ['quantidade' => count($catalogo), 'menor' => min($precos), 'maior' => max($precos)];
}
