<?php
require_once __DIR__ . '/conexao.php';

function formatarPreco($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function calcularParcelaDinamica($precoOriginal, $parcelasMaxDesejadas = 10, $valorMinimoParcela = 50.00) {
    $preco = floatval($precoOriginal);
    
    if ($preco <= 0) {
        return "Parcelamento indisponível";
    }

    $qtdParcelas = floor($preco / $valorMinimoParcela);

    if ($qtdParcelas < 1) {
        $qtdParcelas = 1;
    }

    if ($qtdParcelas > $parcelasMaxDesejadas) {
        $qtdParcelas = $parcelasMaxDesejadas;
    }

    $valorParcela = $preco / $qtdParcelas;

    if ($qtdParcelas > 1) {
        return "em até {$qtdParcelas}x de R$ " . number_format($valorParcela, 2, ',', '.') . " sem juros";
    } else {
        return "à vista ou em 1x de R$ " . number_format($valorParcela, 2, ',', '.');
    }
}

/**
 * Segunda foto do produto (a primeira da galeria que seja diferente da capa), usada na vitrine
 * para trocar a imagem quando o mouse passa por cima do cartão. Devolve '' se não houver.
 */
function segundaFotoProduto($conn, $idProduto, $capa)
{
    $idProduto = (int)$idProduto;
    $capa = (string)$capa;
    $stmt = $conn->prepare("SELECT imagem_url FROM produto_fotos WHERE produto_id = ? AND imagem_url <> ? ORDER BY id LIMIT 1");
    $stmt->bind_param("is", $idProduto, $capa);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $linha ? (string)$linha['imagem_url'] : '';
}

/* =====================================================================
   PROMOÇÕES (campanhas do Painel > Promoções)
   ===================================================================== */

/** Categorias que podem receber campanha (valor gravado em produto.categoria => rótulo). */
const CATEGORIAS_PROMOCAO = ['Moda Feminina' => 'Feminino', 'Moda Masculina' => 'Masculino', 'Calçados' => 'Calçados'];

/** Cria a tabela de campanhas na primeira vez. */
function garantirTabelaPromocoes($conn)
{
    $conn->query("CREATE TABLE IF NOT EXISTS promocoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        tipo ENUM('percentual','valor') NOT NULL DEFAULT 'percentual',
        valor DECIMAL(10,2) NOT NULL,
        categorias VARCHAR(255) NOT NULL DEFAULT '',
        produtos TEXT NULL,
        inicio DATETIME NOT NULL,
        fim DATETIME NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_periodo (ativo, inicio, fim)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // MySQL não aceita "ADD COLUMN IF NOT EXISTS" (sintaxe exclusiva do MariaDB): conferir antes de alterar
    $col = $conn->query("SHOW COLUMNS FROM promocoes LIKE 'valor_minimo'");
    if ($col && $col->num_rows === 0) {
        $conn->query("ALTER TABLE promocoes ADD COLUMN valor_minimo DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER valor");
    }
}

/**
 * A campanha vale para este produto? Além de estar na categoria/lista escolhida, o PREÇO CHEIO
 * do produto precisa ser igual ou maior que o "valor mínimo" da campanha (quando houver).
 */
function promocaoValeParaProduto(array $promo, array $p)
{
    if (!promocaoAlcancaProduto($promo, $p['id_produto'] ?? 0, $p['categoria'] ?? '')) return false;
    $minimo = (float)($promo['valor_minimo'] ?? 0);
    return $minimo <= 0 || (float)($p['preco_original'] ?? 0) >= $minimo;
}

/** Campanhas valendo AGORA (ativas e dentro do período). Lidas uma vez por página. */
function promocoesVigentes($conn)
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        $res = $conn->query("SELECT * FROM promocoes WHERE ativo = 1 AND inicio <= NOW() AND fim >= NOW()");
        if ($res) $cache = $res->fetch_all(MYSQLI_ASSOC);
    } catch (Throwable $e) {
        $cache = [];
    }
    return $cache;
}

/** A campanha vale para este produto? (produto escolhido individualmente ou categoria inteira) */
function promocaoAlcancaProduto(array $promo, $idProduto, $categoria)
{
    $ids = array_filter(array_map('intval', explode(',', (string)($promo['produtos'] ?? ''))));
    if (in_array((int)$idProduto, $ids, true)) return true;
    $cats = array_filter(array_map('trim', explode(',', (string)($promo['categorias'] ?? ''))));
    return in_array((string)$categoria, $cats, true);
}

/** Aplica o desconto da campanha a um preço (nunca abaixo de R$ 0,01). */
function aplicarDescontoPromocao($preco, array $promo)
{
    $preco = (float)$preco;
    if ($preco <= 0) return $preco;
    $novo = ($promo['tipo'] === 'valor') ? $preco - (float)$promo['valor'] : $preco * (1 - (float)$promo['valor'] / 100);
    return round(max(0.01, $novo), 2);
}

/**
 * Preços EFETIVOS do produto (com a melhor promoção vigente, se houver).
 * Precisa de: id_produto, categoria, preco_original, preco_pix.
 * Retorna: original (preço a cobrar: cheio ou com promoção), pix (original - 5%, só para exibir),
 *          original_cheio, pix_cheio (sem promoção), promocao (null ou ['id','nome','percentual','fim']).
 *
 * REGRA DE PREÇO (sacola, cupom e pedido usam SEMPRE o "original"):
 *   Cartão = preço cheio (ou de promoção) - cupom.
 *   Pix    = o mesmo valor do cartão - 5% (PERC_DESCONTO_PIX), aplicado no checkout DEPOIS do cupom.
 * A coluna produto.preco_pix não define mais o preço: só é usada se o produto não tiver preco_original.
 */
const PERC_DESCONTO_PIX = 0.05;

function precoComDescontoPix($valor)
{
    return round((float)$valor * (1 - PERC_DESCONTO_PIX), 2);
}

function precosProduto($conn, array $p)
{
    $orig = (float)($p['preco_original'] ?? 0);
    if ($orig <= 0) $orig = (float)($p['preco_pix'] ?? 0);
    $res = ['original' => $orig, 'pix' => precoComDescontoPix($orig),
            'original_cheio' => $orig, 'pix_cheio' => precoComDescontoPix($orig), 'promocao' => null];

    $melhor = null;
    foreach (promocoesVigentes($conn) as $promo) {
        if (!promocaoValeParaProduto($promo, $p)) continue;
        $novo = aplicarDescontoPromocao($orig, $promo);
        if ($novo < $orig && ($melhor === null || $novo < $melhor['original'])) {
            $melhor = ['promo' => $promo, 'original' => $novo];
        }
    }
    if ($melhor) {
        $res['original'] = $melhor['original'];
        $res['pix'] = precoComDescontoPix($melhor['original']);
        $res['promocao'] = [
            'id'         => (int)$melhor['promo']['id'],
            'nome'       => (string)$melhor['promo']['nome'],
            'percentual' => $orig > 0 ? (int)round((1 - $res['original'] / $orig) * 100) : 0,
            'fim'        => (string)$melhor['promo']['fim'],
        ];
    }
    return $res;
}

/** Selo "-20%" para a foto do cartão (vazio se o produto não está em promoção). */
function seloPromocaoHtml(array $precos)
{
    if (!$precos['promocao'] || $precos['promocao']['percentual'] <= 0) return '';
    return '<span class="selo-promocao" title="' . htmlspecialchars($precos['promocao']['nome']) . '">-'
         . (int)$precos['promocao']['percentual'] . '%</span>';
}

/** Bloco de preços dos cartões das vitrines: preço cheio riscado + preço promocional em destaque. */
function precosCartaoHtml(array $precos, $parcelasMax)
{
    $promo = $precos['promocao'] !== null;
    $h = '<div class="product-pricing mb-2' . ($promo ? ' em-promocao' : '') . '">';
    if ($promo) {
        $h .= '<span class="preco-cheio-riscado d-block">' . formatarPreco($precos['original_cheio']) . '</span>';
        $h .= '<span class="preco-promocional fs-5 d-block">' . formatarPreco($precos['original']) . '</span>';
    } else {
        $h .= '<span class="text-dark fs-5 d-block">' . formatarPreco($precos['original']) . '</span>';
    }
    $h .= '<span class="text-muted small">' . calcularParcela($precos['original'], $parcelasMax) . '</span></div>';
    $h .= '<div class="product-pix mb-3">'
        . '<span class="text-muted small d-block" style="font-size: 0.75rem; line-height: 1;">ou</span>'
        . '<span class="fw-bold text-success fs-6">' . formatarPreco($precos['pix']) . '</span> '
        . '<span class="text-muted small" style="font-size: 0.75rem;">(no Pix)</span></div>';
    return $h;
}

function calcularParcela($precoOriginal, $parcelasMax = 10, $valorMinimo = 50.00) {
    return calcularParcelaDinamica($precoOriginal, $parcelasMax, $valorMinimo);
}