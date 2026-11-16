<?php
// Copie este arquivo para conexao.php.
// Os valores abaixo já funcionam no XAMPP padrão (usuário root, sem senha, banco local).
// O conexao.php real não é versionado (está no .gitignore) para não expor a senha do banco.
$host    = 'localhost';         // no projeto original: VM Ubuntu em 192.168.56.10
$usuario = 'root';              // EM PRODUÇÃO: use um usuário próprio, com permissões só neste banco
$senha   = '';                  // EM PRODUÇÃO: senha forte
$banco   = 'solasol_atelie';

try {
    $conn = mysqli_init();
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $conn->real_connect($host, $usuario, $senha, $banco);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    // Detalhes técnicos vão para o log do PHP, nunca para a tela
    error_log("Falha na conexão MySQL: " . $e->getMessage());
    http_response_code(500);
    die("Serviço temporariamente indisponível.");
}
