<?php
// funcoes_estoque.php — baixa e devolução de estoque dos pedidos.
//  • Produto COM grade (linhas em produto_cores_tamanhos): controla por cor + tamanho
//    e mantém produto.estoque igual à soma da grade.
//  • Produto SEM grade (nenhuma linha nessa tabela): controla direto em produto.estoque.
// baixarEstoque() e devolverEstoque() devem rodar DENTRO de uma transação ($conn->begin_transaction()).
// Valores de pedidos.estoque_baixado: 0 = pedido antigo (não controlado) | 1 = estoque baixado | 2 = estoque liberado.

if (!class_exists('EstoqueException')) {
    /** Erro "de negócio" (ex.: sem estoque): a mensagem pode ser mostrada ao cliente. */
    class EstoqueException extends RuntimeException {}
}

if (!function_exists('juntarDemandaEstoque')) {

    /** Junta itens iguais (mesmo produto, cor e tamanho) e ordena, para travar as linhas sempre na mesma ordem. */
    function juntarDemandaEstoque(array $itens): array
    {
        $demanda = [];
        foreach ($itens as $it) {
            if (!is_array($it)) continue;
            $pid = (int)($it['id_produto'] ?? $it['id'] ?? 0);
            $qtd = (int)($it['quantidade'] ?? 0);
            if ($pid <= 0 || $qtd <= 0) continue;

            $cor = trim((string)($it['cor'] ?? ''));
            $tam = trim((string)($it['tamanho'] ?? ''));
            $chave = $pid . '|' . mb_strtolower($cor) . '|' . mb_strtolower($tam);

            if (!isset($demanda[$chave])) {
                $demanda[$chave] = ['pid' => $pid, 'cor' => $cor, 'tam' => $tam, 'qtd' => 0, 'nome' => (string)($it['nome'] ?? 'Produto')];
            }
            $demanda[$chave]['qtd'] += $qtd;
        }
        ksort($demanda);
        return array_values($demanda);
    }

    function produtoTemGrade(mysqli $conn, int $pid): bool
    {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM produto_cores_tamanhos WHERE produto_id = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();
        return (int)$total > 0;
    }

    /**
     * Quanto existe em estoque para um produto/cor/tamanho, com a MESMA regra do baixarEstoque():
     * produto com grade → linha da cor+tamanho; produto sem grade → produto.estoque.
     * Retorna null se o produto não existe.
     */
    function consultarDisponivel(mysqli $conn, int $pid, string $cor, string $tam): ?int
    {
        $stmt = $conn->prepare("SELECT estoque FROM produto WHERE id_produto = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$prod) return null;

        if (!produtoTemGrade($conn, $pid)) return max(0, (int)$prod['estoque']);

        $stmt = $conn->prepare("SELECT quantidade FROM produto_cores_tamanhos WHERE produto_id = ? AND cor = ? AND tamanho = ? LIMIT 1");
        $stmt->bind_param("iss", $pid, $cor, $tam);
        $stmt->execute();
        $linha = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $linha ? max(0, (int)$linha['quantidade']) : 0;
    }

    /** produto.estoque = soma das quantidades da grade (a lista de produtos do admin lê esse campo). */
    function sincronizarEstoqueProduto(mysqli $conn, int $pid): void
    {
        $stmt = $conn->prepare("UPDATE produto SET estoque = (SELECT COALESCE(SUM(quantidade), 0) FROM produto_cores_tamanhos WHERE produto_id = ?) WHERE id_produto = ?");
        $stmt->bind_param("ii", $pid, $pid);
        $stmt->execute();
        $stmt->close();
    }

    /** Cria a coluna de controle na tabela pedidos, se ainda não existir (roda sozinha, uma vez). */
    function garantirColunaEstoqueBaixado(mysqli $conn): void
    {
        $r = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'estoque_baixado'");
        if ($r && $r->num_rows === 0) {
            $conn->query("ALTER TABLE pedidos ADD COLUMN estoque_baixado TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    /** Confere e desconta o estoque. Lança EstoqueException se faltar. */
    function baixarEstoque(mysqli $conn, array $itens): void
    {
        foreach (juntarDemandaEstoque($itens) as $d) {
            $pid = $d['pid'];
            $qtd = $d['qtd'];
            $variacao = trim($d['cor'] . ' / ' . $d['tam'], ' /');
            $rotulo = '"' . $d['nome'] . '"' . ($variacao !== '' ? " ({$variacao})" : '');

            if (produtoTemGrade($conn, $pid)) {
                $stmt = $conn->prepare("SELECT id, quantidade FROM produto_cores_tamanhos WHERE produto_id = ? AND cor = ? AND tamanho = ? LIMIT 1 FOR UPDATE");
                $stmt->bind_param("iss", $pid, $d['cor'], $d['tam']);
                $stmt->execute();
                $linha = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$linha) {
                    throw new EstoqueException("O item {$rotulo} não está mais disponível. Atualize a sua sacola!");
                }
                $disp = (int)$linha['quantidade'];
                if ($disp < $qtd) {
                    throw new EstoqueException($disp <= 0
                        ? "O item {$rotulo} esgotou. Atualize a sua sacola!"
                        : "Estoque insuficiente para {$rotulo}. Disponível: {$disp}!");
                }

                $up = $conn->prepare("UPDATE produto_cores_tamanhos SET quantidade = quantidade - ? WHERE id = ?");
                $up->bind_param("ii", $qtd, $linha['id']);
                $up->execute();
                $up->close();

                sincronizarEstoqueProduto($conn, $pid);
            } else {
                $stmt = $conn->prepare("SELECT estoque FROM produto WHERE id_produto = ? FOR UPDATE");
                $stmt->bind_param("i", $pid);
                $stmt->execute();
                $linha = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $disp = (int)($linha['estoque'] ?? 0);
                if ($disp < $qtd) {
                    throw new EstoqueException($disp <= 0
                        ? "O item {$rotulo} esgotou. Atualize a sua sacola!"
                        : "Estoque insuficiente para {$rotulo}. Disponível: {$disp}!");
                }

                $up = $conn->prepare("UPDATE produto SET estoque = estoque - ? WHERE id_produto = ?");
                $up->bind_param("ii", $qtd, $pid);
                $up->execute();
                $up->close();
            }
        }
    }

    /** Devolve ao estoque os itens de um pedido cancelado/excluído. */
    function devolverEstoque(mysqli $conn, array $itens): void
    {
        foreach (juntarDemandaEstoque($itens) as $d) {
            $pid = $d['pid'];
            $qtd = $d['qtd'];

            if (produtoTemGrade($conn, $pid)) {
                $up = $conn->prepare("UPDATE produto_cores_tamanhos SET quantidade = quantidade + ? WHERE produto_id = ? AND cor = ? AND tamanho = ? LIMIT 1");
                $up->bind_param("iiss", $qtd, $pid, $d['cor'], $d['tam']);
                $up->execute();
                $up->close();
                sincronizarEstoqueProduto($conn, $pid);
            } else {
                $up = $conn->prepare("UPDATE produto SET estoque = estoque + ? WHERE id_produto = ?");
                $up->bind_param("ii", $qtd, $pid);
                $up->execute();
                $up->close();
            }
        }
    }
}