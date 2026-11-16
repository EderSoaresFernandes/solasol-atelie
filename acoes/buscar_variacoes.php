<?php
require_once __DIR__ . '/../conexao.php';
header('Content-Type: application/json; charset=utf-8');

$id = intval($_GET['id_produto'] ?? 0);
$cores = [];
$tamanhos_por_cor = [];
$estoque = [];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT cor, tamanho, quantidade FROM produto_cores_tamanhos WHERE produto_id = ? AND quantidade > 0");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    while ($row = $res->fetch_assoc()) {
        $cor = trim($row['cor']);
        if ($cor === '') continue;
        if (!in_array($cor, $cores, true)) $cores[] = $cor;

        foreach (array_map('trim', explode(',', $row['tamanho'])) as $tam) {
            if ($tam === '') continue;
            if (!isset($tamanhos_por_cor[$cor]) || !in_array($tam, $tamanhos_por_cor[$cor], true)) {
                $tamanhos_por_cor[$cor][] = $tam;
            }
            $estoque[$cor][$tam] = ($estoque[$cor][$tam] ?? 0) + (int)$row['quantidade'];
        }
    }
}

echo json_encode([
    'cores' => $cores,
    'tamanhos_por_cor' => $tamanhos_por_cor ?: new stdClass(),
    'estoque' => $estoque ?: new stdClass()
], JSON_UNESCAPED_UNICODE);