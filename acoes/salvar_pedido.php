<?php
// acoes/salvar_pedido.php - Processa e grava o pedido no banco de dados.
// O servidor RECALCULA tudo (preços, cupom, frete, desconto Pix): valores vindos do navegador não são confiáveis.
// Também CONFERE e BAIXA o estoque (por cor/tamanho) na mesma transação que grava o pedido.
ini_set('display_errors', '0');
require_once __DIR__ . '/validar_cupom.php';
require_once __DIR__ . '/../funcoes_cliente.php';
require_once __DIR__ . '/../funcoes_estoque.php';
require_once __DIR__ . '/../funcoes.php';

header('Content-Type: application/json; charset=utf-8');

const WHATSAPP_LOJISTA  = '5544997020518';
const COD_ERRO_CUPOM    = 4001;            // código das exceções causadas pelo cupom (o checkout remove o cupom)
const PARCELAS_MAX      = 10;              // mesma regra de funcoes.php (calcularParcelaDinamica) e do checkout.php
const PARCELA_MINIMA    = 50.00;

// ---- Pix (SIMULADO) ----
// A chave abaixo é FICTÍCIA de propósito: o QR Code tem o formato oficial do Banco Central (BR Code),
// mas nenhum banco encontra esta chave, então ninguém paga de verdade por engano.
// Para receber pagamentos reais no futuro, troque pela chave Pix da loja (e-mail, telefone +55..., CPF/CNPJ ou aleatória).
const PIX_CHAVE  = 'pix-simulado@solasolatelie.com.br';
const PIX_NOME   = 'SOLASOL ATELIE';
const PIX_CIDADE = 'RONCADOR';
const PIX_PRAZO_MINUTOS = 30;

/** Campo EMV: ID (2 dígitos) + tamanho (2 dígitos) + valor. */
function pixCampo(string $id, string $valor): string
{
    return $id . str_pad((string)strlen($valor), 2, '0', STR_PAD_LEFT) . $valor;
}

/** CRC16-CCITT (polinômio 0x1021, início 0xFFFF), exigido no final do código Pix. */
function pixCrc16(string $dados): string
{
    $crc = 0xFFFF;
    for ($i = 0, $n = strlen($dados); $i < $n; $i++) {
        $crc ^= ord($dados[$i]) << 8;
        for ($b = 0; $b < 8; $b++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}

/** Monta o "Pix Copia e Cola" (BR Code estático com valor) — o mesmo texto vira o QR Code. */
function gerarPixCopiaCola(float $valor, string $txid): string
{
    $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid), 0, 25) ?: '***';
    $payload = pixCampo('00', '01')
        . pixCampo('26', pixCampo('00', 'br.gov.bcb.pix') . pixCampo('01', PIX_CHAVE))
        . pixCampo('52', '0000')
        . pixCampo('53', '986')
        . pixCampo('54', number_format($valor, 2, '.', ''))
        . pixCampo('58', 'BR')
        . pixCampo('59', substr(PIX_NOME, 0, 25))
        . pixCampo('60', substr(PIX_CIDADE, 0, 15))
        . pixCampo('62', pixCampo('05', $txid))
        . '6304';
    return $payload . pixCrc16($payload);
}

function responder(array $dados)
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// Mesma lógica de regiões do checkout.php
function calcularFreteServidor($cep, $cfg)
{
    $cep = preg_replace('/\D/', '', $cep);
    if (strlen($cep) !== 8) return null;
    $p = intval(substr($cep, 0, 5));

    if ($p >= 80000 && $p <= 87999)                                   return (float)($cfg['dentro_estado'] ?? 15.00);
    if ($p >= 80000 && $p <= 99999)                                   return (float)($cfg['regiao_sul'] ?? 20.00);
    if (($p >= 1000 && $p <= 39999) || ($p >= 10000 && $p <= 19999))  return (float)($cfg['regiao_sudeste'] ?? 25.00);
    if ($p >= 76800 && $p <= 77999)                                   return (float)($cfg['regiao_norte'] ?? 50.00);
    if ($p >= 70000 && $p <= 79999)                                  return (float)($cfg['regiao_centro_oeste'] ?? 35.00);
    if ($p >= 40000 && $p <= 65999)                                   return (float)($cfg['regiao_nordeste'] ?? 45.00);
    return (float)($cfg['regiao_norte'] ?? 50.00);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['sucesso' => false, 'erro' => 'Método inválido!']);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    responder(['sucesso' => false, 'erro' => 'Dados inválidos!']);
}

$idCliente = !empty($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;

$nome        = trim($data['nome'] ?? '');
$email       = trim($data['email'] ?? '');
$telefone    = trim($data['telefone'] ?? '');
$cpf_digitos = somenteDigitos($data['cpf_cnpj'] ?? '');
$cep         = trim($data['cep'] ?? '');
$endereco    = trim($data['endereco'] ?? '');
$numero      = trim($data['numero'] ?? '');
$bairro      = trim($data['bairro'] ?? '');
$cidade      = trim($data['cidade'] ?? '');
$estado      = strtoupper(trim($data['estado'] ?? ''));
$complemento = trim($data['complemento'] ?? '');

if ($complemento !== '') {
    $endereco = mb_substr($endereco . ' - ' . $complemento, 0, 255);
}

$forma_pagamento = in_array(($data['forma_pagamento'] ?? 'pix'), ['pix', 'cartao'], true) ? $data['forma_pagamento'] : 'pix';

$status_pedido = (($data['status_pedido'] ?? 'Pendente') === 'Abandonado') ? 'Abandonado' : 'Pendente';
$estrito = ($status_pedido === 'Pendente');

if ($estrito) {
    if ($nome === '' || $telefone === '') {
        responder(['sucesso' => false, 'erro' => 'Preencha nome e telefone!']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responder(['sucesso' => false, 'erro' => 'Informe um e-mail válido!']);
    }
    if ($endereco === '' || $numero === '' || $bairro === '' || $cidade === '' || !preg_match('/^[A-Z]{2}$/', $estado)) {
        responder(['sucesso' => false, 'erro' => 'Preencha o endereço completo (rua, número, bairro, cidade e UF)!']);
    }
    if (!validarDocumento($cpf_digitos)) {
        responder(['sucesso' => false, 'erro' => 'Informe um CPF ou CNPJ válido (necessário para a emissão da nota fiscal)!']);
    }
}
$cpf_cnpj = formatarDocumento($cpf_digitos);

try {
    $itens_servidor = [];
    $subtotal = 0.0;
    $subtotal_elegivel_cupom = 0.0;
    $tem_promo = false;
    foreach ((array)($data['itens'] ?? []) as $it) {
        $pid = intval($it['id_produto'] ?? $it['id'] ?? 0);
        $qtd = max(1, min(99, intval($it['quantidade'] ?? 1)));
        if ($pid <= 0) continue;

        $stmt = $conn->prepare("SELECT id_produto, nome, categoria, imagem_url, preco_pix, preco_original FROM produto WHERE id_produto = ?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $p = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$p) continue;

        $precos = precosProduto($conn, $p);
        $preco = $precos['original'];
        $subtotal += $preco * $qtd;
        if ($precos['promocao']) $tem_promo = true;
        else $subtotal_elegivel_cupom += $preco * $qtd;

        $item = [
            'id'         => $pid,
            'nome'       => $p['nome'],
            'preco'      => $preco,
            'imagem'     => $p['imagem_url'],
            'quantidade' => $qtd,
            'tamanho'    => trim((string)($it['tamanho'] ?? '')),
            'cor'        => trim((string)($it['cor'] ?? '')),
        ];
        if ($precos['promocao']) {
            $item['preco_cheio'] = $precos['original_cheio'];
            $item['promocao']    = $precos['promocao']['nome'];
        }
        $itens_servidor[] = $item;
    }
    $subtotal = round($subtotal, 2);

    if (empty($itens_servidor)) {
        responder(['sucesso' => false, 'erro' => 'Sacola vazia ou produtos indisponíveis!']);
    }

    $cupom_codigo = null;
    $desconto_cupom = 0.0;
    $regra_cupom = null;
    $codigo_enviado = trim($data['cupom_codigo'] ?? '');
    if ($codigo_enviado !== '') {
        $r = validarCupom($conn, $codigo_enviado, $subtotal, round($subtotal_elegivel_cupom, 2), $tem_promo);
        if ($r['ok']) {
            $cupom_codigo = $r['codigo'];
            $desconto_cupom = $r['desconto'];
            $regra_cupom = $r['regra'];
        } elseif ($estrito) {
            responder(['sucesso' => false, 'erro' => $r['mensagem'], 'cupom_invalido' => true]);
        }
    }
    $base = round($subtotal - $desconto_cupom, 2);

    $cfg = $conn->query("SELECT * FROM configuracoes_frete WHERE id=1")->fetch_assoc() ?: [];
    $frete_tabela = calcularFreteServidor($cep, $cfg);
    if ($frete_tabela === null) {
        if ($estrito) responder(['sucesso' => false, 'erro' => 'CEP inválido!']);
        $frete_tabela = 0.0;
    }
    $minimo_gratis = (float)($cfg['valor_minimo_frete_gratis'] ?? 199.90);
    $frete = ($base >= $minimo_gratis) ? 0.0 : $frete_tabela;

    $desconto_pix = ($forma_pagamento === 'pix') ? round($base * PERC_DESCONTO_PIX, 2) : 0.0;
    $total_geral  = round($base + $frete - $desconto_pix, 2);
    $itens_json   = json_encode($itens_servidor);

    // ---- Parcelas (cartão): até 10x, sem parcela menor que R$ 50,00. O valor pedido é ajustado à regra. ----
    // Pagamento com cartão é SIMULADO: o servidor não recebe nenhum dado do cartão, só o número de parcelas.
    $parcelas = 1;
    if ($forma_pagamento === 'cartao') {
        $max_parcelas = min(PARCELAS_MAX, max(1, (int)floor($total_geral / PARCELA_MINIMA)));
        $parcelas = max(1, min($max_parcelas, (int)($data['parcelas'] ?? 1)));
    }
    $valor_parcela = round($total_geral / $parcelas, 2);

    $r_col = $conn->query("SHOW COLUMNS FROM pedidos LIKE 'parcelas'");
    if ($r_col && $r_col->num_rows === 0) {
        $conn->query("ALTER TABLE pedidos ADD COLUMN parcelas TINYINT NOT NULL DEFAULT 1");
    }

    // Coluna de controle de estoque (criada automaticamente na 1ª vez; ALTER TABLE precisa ficar fora da transação)
    garantirColunaEstoqueBaixado($conn);

    $flag_estoque = $estrito ? 1 : 2;

    $conn->begin_transaction();
    try {
        if ($estrito) {
            baixarEstoque($conn, $itens_servidor);
        }

        if ($cupom_codigo !== null && $estrito) {
            if ($regra_cupom === 'primeira_compra' && $idCliente) {
                $lk = $conn->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = ? FOR UPDATE");
                $lk->bind_param("i", $idCliente);
                $lk->execute();
                $lk->close();
                if (clienteJaComprou($conn, $idCliente, (string)($_SESSION['usuario_email'] ?? ''))) {
                    throw new RuntimeException('Este cupom é válido apenas na primeira compra!', COD_ERRO_CUPOM);
                }
            }

            $up = $conn->prepare("UPDATE cupons SET usos = usos + 1 WHERE codigo = ? AND (limite_uso IS NULL OR usos < limite_uso)");
            $up->bind_param("s", $cupom_codigo);
            $up->execute();
            $consumiu = $up->affected_rows > 0;
            $up->close();
            if (!$consumiu) throw new RuntimeException('Este cupom atingiu o limite de usos!', COD_ERRO_CUPOM);
        }

        $stmt = $conn->prepare("INSERT INTO pedidos (nome_cliente, email_cliente, telefone_cliente, cep, endereco, numero, bairro, cidade, estado, forma_pagamento, subtotal, frete, desconto_pix, total_geral, itens_json, status_pedido, cupom_codigo, desconto_cupom, estoque_baixado, id_cliente, cpf_cnpj, parcelas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "ssssssssssddddsssdiisi",
            $nome, $email, $telefone, $cep, $endereco, $numero, $bairro, $cidade, $estado, $forma_pagamento,
            $subtotal, $frete, $desconto_pix, $total_geral,
            $itens_json, $status_pedido, $cupom_codigo,
            $desconto_cupom, $flag_estoque, $idCliente, $cpf_cnpj, $parcelas
        );
        $stmt->execute();
        $pedido_id = $stmt->insert_id;
        $stmt->close();

        // Relacionamento N:N pedidos <-> produto: um registro por item, na mesma transação do pedido
        $st_it = $conn->prepare("INSERT INTO pedido_itens (id_pedido, id_produto, nome_produto, cor, tamanho, quantidade, preco_unitario) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($itens_servidor as $it) {
            $st_it->bind_param("iisssid", $pedido_id, $it['id'], $it['nome'], $it['cor'], $it['tamanho'], $it['quantidade'], $it['preco']);
            $st_it->execute();
        }
        $st_it->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    // ---- Guarda o CPF/CNPJ no cadastro se o cliente ainda não tinha (nunca sobrescreve) ----
    if ($estrito && $idCliente && $cpf_cnpj !== '') {
        try {
            $tipo_p = (strlen($cpf_digitos) === 14) ? 'PJ' : 'PF';
            $q = $conn->prepare("UPDATE clientes SET cpf_cnpj = ?, tipo_pessoa = ? WHERE id_cliente = ? AND (cpf_cnpj IS NULL OR cpf_cnpj = '')");
            $q->bind_param("ssi", $cpf_cnpj, $tipo_p, $idCliente);
            $q->execute();
            $q->close();
        } catch (Throwable $e) {
            error_log('salvar_pedido (cpf no cadastro): ' . $e->getMessage());
        }
    }

    $linha_cupom = $cupom_codigo ? "\n*Cupom:* {$cupom_codigo} (- R$ " . number_format($desconto_cupom, 2, ',', '.') . ")" : '';
    if ($status_pedido === 'Abandonado') {
        $msg_zap = rawurlencode("⚠️ *Carrinho Abandonado Detectado!*\n\n*Cliente:* {$nome}\n*Telefone:* {$telefone}{$linha_cupom}\n*Total:* R$ " . number_format($total_geral, 2, ',', '.'));
    } else {
        $txt_pagto = ($forma_pagamento === 'cartao')
            ? "CARTÃO (simulado) em {$parcelas}x de R$ " . number_format($valor_parcela, 2, ',', '.')
            : 'PIX (simulado, QR Code exibido ao cliente)';
        $msg_zap = rawurlencode("🛍️ *Novo Pedido Realizado! (#{$pedido_id})*\n\n*Cliente:* {$nome}\n*Telefone:* {$telefone}\n*Pagamento:* {$txt_pagto}{$linha_cupom}\n*Total:* R$ " . number_format($total_geral, 2, ',', '.'));
    }

    $pix = null;
    if ($forma_pagamento === 'pix' && $estrito) {
        $pix = [
            'copia_cola'    => gerarPixCopiaCola($total_geral, 'SOLASOL' . $pedido_id),
            'chave'         => PIX_CHAVE,
            'recebedor'     => PIX_NOME,
            'prazo_minutos' => PIX_PRAZO_MINUTOS,
        ];
    }

    responder([
        'sucesso'       => true,
        'id_pedido'     => $pedido_id,
        'total_geral'   => $total_geral,
        'parcelas'      => $parcelas,
        'valor_parcela' => $valor_parcela,
        'pix'           => $pix,
        'link_whatsapp' => 'https://api.whatsapp.com/send?phone=' . WHATSAPP_LOJISTA . '&text=' . $msg_zap
    ]);

} catch (mysqli_sql_exception $e) {
    // mysqli_sql_exception também é um RuntimeException: este catch vem ANTES para o erro técnico do banco
    // nunca chegar ao cliente
    error_log('salvar_pedido (banco): ' . $e->getMessage());
    responder(['sucesso' => false, 'erro' => 'Não foi possível registrar o pedido. Tente novamente!']);
} catch (RuntimeException $e) {
    responder([
        'sucesso'        => false,
        'erro'           => $e->getMessage(),
        'cupom_invalido' => ($e->getCode() === COD_ERRO_CUPOM),
        'estoque_insuficiente' => ($e instanceof EstoqueException),
    ]);
} catch (Throwable $e) {
    error_log('salvar_pedido: ' . $e->getMessage());   // detalhe técnico fica no log, não vai para o cliente
    responder(['sucesso' => false, 'erro' => 'Não foi possível registrar o pedido. Tente novamente!']);
}