<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../funcoes.php';

/**
 * Soma o valor dos itens usando os PREÇOS DO BANCO (nunca confie no valor vindo do navegador).
 * Retorna: total (todos os itens), elegivel (só itens FORA de promoção, os únicos que aceitam cupom)
 * e tem_promo (se algum item está em promoção).
 */
function calcularSubtotalItens($conn, $itens)
{
    $total = 0.0; $elegivel = 0.0; $temPromo = false;
    foreach ((array)$itens as $it) {
        $pid = intval($it['id_produto'] ?? $it['id'] ?? 0);
        $qtd = max(1, intval($it['quantidade'] ?? 1));
        if ($pid <= 0) continue;
        $stmt = $conn->prepare("SELECT id_produto, categoria, preco_pix, preco_original FROM produto WHERE id_produto = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($p) {
            $precos = precosProduto($conn, $p);
            $total += $precos['original'] * $qtd;
            if ($precos['promocao']) $temPromo = true;
            else $elegivel += $precos['original'] * $qtd;
        }
    }
    return ['total' => round($total, 2), 'elegivel' => round($elegivel, 2), 'tem_promo' => $temPromo];
}

/** O cliente já tem algum pedido real? (abandonados e cancelados não contam) */
function clienteJaComprou($conn, $idCliente, $email)
{
    $idCliente = (int)$idCliente;
    $email = (string)$email;
    $qtd = 0;

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM pedidos
         WHERE (id_cliente = ? OR (? <> '' AND email_cliente = ?))
           AND status_pedido NOT IN ('Abandonado', 'Cancelado')"
    );
    $stmt->bind_param("iss", $idCliente, $email, $email);
    $stmt->execute();
    $stmt->bind_result($qtd);
    $stmt->fetch();
    $stmt->close();

    return (int)$qtd > 0;
}

/**
 * Valida o cupom e calcula o desconto.
 * Cupom vale SÓ para itens fora de promoção: $elegivel é a soma desses itens e $temPromo diz se há itens em promoção.
 * (Sem os dois últimos parâmetros, todo o subtotal é considerado elegível.)
 */
function validarCupom($conn, $codigo, $subtotal, $elegivel = null, $temPromo = false)
{
    $elegivel = ($elegivel === null) ? (float)$subtotal : (float)$elegivel;
    $codigo = strtolower(ltrim(trim((string)$codigo), '#'));
    if ($codigo === '') return ['ok' => false, 'mensagem' => 'Digite o código do cupom!'];

    $idCliente = (int)($_SESSION['usuario_id'] ?? 0);
    if ($idCliente <= 0) {
        return ['ok' => false, 'precisa_login' => true, 'mensagem' => 'Faça login para usar cupons de desconto!'];
    }
    $emailCliente = (string)($_SESSION['usuario_email'] ?? '');

    $stmt = $conn->prepare("SELECT * FROM cupons WHERE codigo = ? LIMIT 1");
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $c = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $hoje = date('Y-m-d');

    if (!$c || !$c['ativo'])
        return ['ok' => false, 'mensagem' => 'Cupom inválido!'];
    if (!empty($c['inicio']) && $c['inicio'] > $hoje)
        return ['ok' => false, 'mensagem' => 'Este cupom começa a valer em ' . date('d/m/Y', strtotime($c['inicio'])) . '!'];
    if (!empty($c['validade']) && $c['validade'] < $hoje)
        return ['ok' => false, 'mensagem' => 'Este cupom expirou!'];
    if ($c['limite_uso'] !== null && (int)$c['usos'] >= (int)$c['limite_uso'])
        return ['ok' => false, 'mensagem' => 'Este cupom atingiu o limite de usos!'];

    if ($temPromo && $elegivel <= 0)
        return ['ok' => false, 'so_promocao' => true, 'mensagem' => 'Cupom válido apenas para itens fora da Promoção!'];

    if ($elegivel < (float)$c['valor_minimo'])
        return ['ok' => false, 'mensagem' => 'Pedido mínimo para este cupom: R$ ' . number_format((float)$c['valor_minimo'], 2, ',', '.')
            . ($temPromo ? ' em itens fora da Promoção!' : '!')];

    if ($c['regra'] === 'primeira_compra' && clienteJaComprou($conn, $idCliente, $emailCliente))
        return ['ok' => false, 'mensagem' => 'Este cupom é válido apenas na primeira compra!'];

    $desconto = ($c['tipo'] === 'percentual')
        ? $elegivel * ((float)$c['valor'] / 100)
        : (float)$c['valor'];
    $desconto = round(min($desconto, $elegivel), 2);

    $parcial = $temPromo;
    return [
        'ok'       => true,
        'codigo'   => $c['codigo'],
        'regra'    => $c['regra'],
        'tipo'     => $c['tipo'],
        'valor'    => (float)$c['valor'],
        'desconto' => $desconto,
        'subtotal' => round($subtotal, 2),
        'elegivel' => round($elegivel, 2),
        'parcial'  => $parcial,
        'total'    => round($subtotal - $desconto, 2),
        'mensagem' => $parcial ? 'Cupom de desconto aplicado apenas nos itens não participantes da PROMOÇÃO!' : 'Cupom aplicado!'
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');

    try {
        $in = json_decode(file_get_contents('php://input'), true);
        if (!is_array($in)) $in = [];

        $soma = calcularSubtotalItens($conn, $in['itens'] ?? []);
        echo json_encode(validarCupom($conn, $in['codigo'] ?? '', $soma['total'], $soma['elegivel'], $soma['tem_promo']), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        // Ex.: coluna inexistente no banco. O detalhe vai para o log do PHP, não para o cliente.
        error_log('validar_cupom: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'mensagem' => 'Não foi possível validar o cupom agora. Tente novamente!'], JSON_UNESCAPED_UNICODE);
    }
}