<?php

if (!function_exists('iniciar_sessao_segura')) {

    /** Inicia a sessão do admin com cookie endurecido (HttpOnly, SameSite, Secure se HTTPS). */
    function iniciar_sessao_segura(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('SOLASOL_ADMIN');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    /** Cabeçalhos para páginas administrativas (anti-clickjacking, sem cache, etc.). */
    function enviar_headers_seguranca(): void {
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
    }

    /** Encerra a sessão por completo (dados + cookie + arquivo no servidor). */
    function encerrar_sessao(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Strict',
            ]);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    // ---------------- CSRF ----------------
    function csrf_token(): string {
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf'];
    }

    function campo_csrf(): string {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    function csrf_confere(): bool {
        $esperado = $_SESSION['csrf'] ?? '';
        $enviado  = $_POST['csrf'] ?? '';
        return is_string($esperado) && $esperado !== '' && is_string($enviado) && hash_equals($esperado, $enviado);
    }

    /** Interrompe a requisição POST se o token CSRF não conferir. */
    function csrf_validar(): void {
        if (csrf_confere()) return;
        http_response_code(403);
        $excedeu = empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
        exit($excedeu
            ? 'O envio excedeu o limite do servidor (post_max_size no php.ini). Envie um arquivo menor.'
            : 'Requisição inválida: token de segurança ausente ou expirado. Volte, recarregue a página e tente de novo.');
    }

    /** Confere se o arquivo é mesmo uma imagem (JPG/PNG/GIF/WEBP) e devolve a extensão segura. */
    function detectar_extensao_imagem(string $tmp, int $maxBytes = 5242880): ?string {
        if (!is_uploaded_file($tmp) || filesize($tmp) > $maxBytes) return null;
        $info = @getimagesize($tmp);
        if ($info === false) return null;
        $mapa = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
        return $mapa[$info[2]] ?? null;
    }

    /** Salva a imagem com nome aleatório e extensão controlada pelo servidor. Retorna o caminho relativo. */
    function salvar_imagem_upload(string $tmp, string $pasta, string $ext): ?string {
        if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) return null;
        $destino = rtrim($pasta, '/') . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
        if (!move_uploaded_file($tmp, $destino)) return null;
        @chmod($destino, 0644);
        return $destino;
    }

    /** Confere a assinatura de um MP4 real (caixa "ftyp" nos bytes 4-8). */
    function eh_mp4_valido(string $tmp): bool {
        if (!is_uploaded_file($tmp)) return false;
        $f = @fopen($tmp, 'rb');
        if (!$f) return false;
        $head = fread($f, 12);
        fclose($f);
        return is_string($head) && strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp';
    }
}
