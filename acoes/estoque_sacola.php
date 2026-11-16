<?php
ini_set('display_errors', '0');
require_once __DIR__ . '/../conexao.php';
require_once __DIR__ . '/../funcoes_estoque.php';
require_once __DIR__ . '/../funcoes.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $in = json_decode(file_get_contents('php://input'), true);
    $itens = is_array($in['itens'] ?? null) ? array_slice($in['itens'], 0, 100) : [];

    $saida = [];
    foreach ($itens as $it) {
        if (!is_array($it)) continue;
        $pid = (int)($it['id_produto'] ?? $it['id'] ?? 0);
        $cor = trim((string)($it['cor'] ?? ''));
        $tam = trim((string)($it['tamanho'] ?? ''));
        if ($pid <= 0) continue;

        $disp = consultarDisponivel($conn, $pid, $cor, $tam);

        $preco = null; $preco_cheio = null; $promocao = null;
        if ($disp !== null) {
            $st = $conn->prepare("SELECT id_produto, categoria, preco_original, preco_pix FROM produto WHERE id_produto = ?");
            $st->bind_param("i", $pid);
            $st->execute();
            $prod = $st->get_result()->fetch_assoc();
            $st->close();
            if ($prod) {
                $pr = precosProduto($conn, $prod);
                $preco = $pr['original'];
                $preco_cheio = $pr['original_cheio'];
                $promocao = $pr['promocao'] ? $pr['promocao']['nome'] : null;
            }
        }

        $saida[] = [
            'id'          => $pid,
            'cor'         => $cor,
            'tamanho'     => $tam,
            'existe'      => $disp !== null,
            'disponivel'  => (int)($disp ?? 0),
            'preco'       => $preco,
            'preco_cheio' => $preco_cheio,
            'promocao'    => $promocao,
        ];
    }
    echo json_encode(['ok' => true, 'itens' => $saida], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('estoque_sacola: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'itens' => []]);
}
