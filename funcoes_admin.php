<?php

if (!function_exists('garantirTabelaUsuariosAdmin')) {

    /** Áreas do painel que podem ser liberadas para cada usuário (chave = aba do admin.php). */
    define('PERMISSOES_ADMIN', [
        'produtos'       => 'Cadastrar produtos',
        'lista_produtos' => 'Gerenciar produtos (lista e edição)',
        'pedidos'        => 'Pedidos',
        'cupons'         => 'Cupons',
        'promocoes'      => 'Promoções',
        'frete'          => 'Frete',
        'duvidas'        => 'Dúvidas',
        'videos'         => 'Vídeos da Home',
        'usuarios'       => 'Usuários (criar e editar acessos)',
    ]);

    /**
     * Cria a tabela de usuários na primeira vez e migra o login antigo do config_admin.php
     * como "Administrador principal" (mesmo usuário e mesma senha).
     */
    function garantirTabelaUsuariosAdmin(mysqli $conn): void
    {
        $conn->query("CREATE TABLE IF NOT EXISTS admin_usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario VARCHAR(30) NOT NULL UNIQUE,
            nome VARCHAR(80) NOT NULL,
            senha_hash VARCHAR(255) NOT NULL,
            permissoes VARCHAR(255) NOT NULL DEFAULT '',
            principal TINYINT(1) NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            sessao_versao INT NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ultimo_login DATETIME NULL DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $r = $conn->query("SELECT COUNT(*) AS total FROM admin_usuarios");
        if ($r && (int)$r->fetch_assoc()['total'] === 0) {
            $ADMIN_USUARIO = 'admin';
            $ADMIN_SENHA_HASH = '';
            if (is_file(__DIR__ . '/config_admin.php')) include __DIR__ . '/config_admin.php';
            if (strpos((string)$ADMIN_SENHA_HASH, '$') === 0) {
                $usuario = normalizarUsuarioAdmin((string)$ADMIN_USUARIO) ?: 'admin';
                $todas = implode(',', array_keys(PERMISSOES_ADMIN));
                $nome = 'Administrador';
                $stmt = $conn->prepare("INSERT INTO admin_usuarios (usuario, nome, senha_hash, permissoes, principal) VALUES (?, ?, ?, ?, 1)");
                $stmt->bind_param("ssss", $usuario, $nome, $ADMIN_SENHA_HASH, $todas);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    /** Login: letras minúsculas, números, ponto, hífen e sublinhado; de 3 a 30 caracteres. '' se inválido. */
    function normalizarUsuarioAdmin(string $usuario): string
    {
        $u = strtolower(trim($usuario));
        return preg_match('/^[a-z0-9._-]{3,30}$/', $u) ? $u : '';
    }

    /** Regra de senha do painel. Devolve a mensagem de erro ('' = senha aceita). */
    function erroSenhaAdmin(string $senha): string
    {
        if (strlen($senha) < 8) return 'A senha precisa ter no mínimo 8 caracteres.';
        if (!preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) return 'A senha precisa ter letras e números.';
        return '';
    }

    function buscarAdminPorId(mysqli $conn, int $id): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM admin_usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $u ?: null;
    }

    /** Lista de abas liberadas. O administrador principal sempre tem todas. */
    function permissoesDoAdmin(array $u): array
    {
        if (!empty($u['principal'])) return array_keys(PERMISSOES_ADMIN);
        $lista = array_filter(array_map('trim', explode(',', (string)$u['permissoes'])));
        return array_values(array_intersect(array_keys(PERMISSOES_ADMIN), $lista));
    }

    /** O usuário pode abrir esta aba? ("editar_produto" faz parte de "Gerenciar produtos"; "usuarios" sempre abre para o próprio perfil.) */
    function adminPodeAba(array $u, string $aba): bool
    {
        if ($aba === 'usuarios') return true;
        if ($aba === 'editar_produto') $aba = 'lista_produtos';
        return in_array($aba, permissoesDoAdmin($u), true);
    }

    function adminGerenciaUsuarios(array $u): bool
    {
        return in_array('usuarios', permissoesDoAdmin($u), true);
    }
}
