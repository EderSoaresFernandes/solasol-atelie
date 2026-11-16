<?php
if (!defined('ADMIN_OK')) { http_response_code(403); exit('Acesso negado.'); }

$mensagem = "";

/**
 * Apaga um arquivo de vídeo da pasta uploads/videos SOMENTE se nenhum quadro
 * (linha da tabela destaques_videos) estiver usando esse caminho.
 * Retorna true se o arquivo foi apagado.
 */
if (!function_exists('apagar_video_se_orfao')) {
    function apagar_video_se_orfao(mysqli $conn, string $url_rel): bool {
        // Só aceita o padrão gerado pelo próprio sistema (para não apagar outros arquivos)
        if (!preg_match('#^uploads/videos/video_\d+_\d+\.mp4$#', $url_rel)) {
            return false;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM destaques_videos WHERE video_url = ?");
        $stmt->bind_param("s", $url_rel);
        $stmt->execute();
        $em_uso = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        if ($em_uso > 0) {
            return false;
        }

        $caminho = __DIR__ . '/../' . $url_rel;
        return is_file($caminho) && unlink($caminho);
    }
}

$check_cat = $conn->query("SELECT COUNT(*) as total FROM destaques_videos");
if ($check_cat) {
    $row_count = $check_cat->fetch_assoc()['total'];
    if ($row_count == 0) {
        $conn->query("INSERT INTO destaques_videos (categoria, video_url, titulo_legenda) VALUES ('Moda Feminina', '', 'Moda Feminina')");
        $conn->query("INSERT INTO destaques_videos (categoria, video_url, titulo_legenda) VALUES ('Moda Masculina', '', 'Moda Masculina')");
        $conn->query("INSERT INTO destaques_videos (categoria, video_url, titulo_legenda) VALUES ('Calçados', '', 'Calçados')");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_video'])) {
    $id_video = intval($_POST['id_video'] ?? 0);
    $titulo_legenda = mb_substr(trim((string)($_POST['titulo_legenda'] ?? '')), 0, 150);
    $erro_video = "";
    $novo_upload = false;

    $video_antigo = '';
    $stmt_old = $conn->prepare("SELECT video_url FROM destaques_videos WHERE id = ?");
    $stmt_old->bind_param("i", $id_video);
    $stmt_old->execute();
    $res_old = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();
    if ($res_old) {
        $video_antigo = $res_old['video_url'] ?? '';
    } else {
        $erro_video = "Quadro de vídeo inválido.";
    }
    $caminho_video_final = $video_antigo;

    if ($erro_video === '' && isset($_FILES['arquivo_video']) && $_FILES['arquivo_video']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['arquivo_video']['tmp_name'];
        $fileName = (string)$_FILES['arquivo_video']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($fileExtension === 'mp4' && eh_mp4_valido($fileTmpPath)) {
            $novoNomeFile = 'video_' . time() . '_' . random_int(1000, 9999) . '.mp4';

            $uploadFileDir = __DIR__ . '/../uploads/videos/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }

            $dest_path = $uploadFileDir . $novoNomeFile;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                @chmod($dest_path, 0644);
                $caminho_video_final = 'uploads/videos/' . $novoNomeFile;
                $novo_upload = true;
            } else {
                $erro_video = "Erro ao mover o arquivo de vídeo. Verifique as permissões da pasta uploads/videos.";
            }
        } else {
            $erro_video = "Formato inválido. Envie apenas arquivos MP4 válidos.";
        }
    } elseif ($erro_video === '' && isset($_FILES['arquivo_video']) && $_FILES['arquivo_video']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['arquivo_video']['error'] !== UPLOAD_ERR_OK) {
        if (in_array($_FILES['arquivo_video']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE])) {
            $erro_video = "O vídeo excede o tamanho máximo permitido pelo servidor (upload_max_filesize).";
        } else {
            $erro_video = "Falha no envio do vídeo (código " . intval($_FILES['arquivo_video']['error']) . ").";
        }
    }

    if (empty($erro_video)) {
        $stmt_up = $conn->prepare("UPDATE destaques_videos SET video_url = ?, titulo_legenda = ? WHERE id = ?");
        $stmt_up->bind_param("ssi", $caminho_video_final, $titulo_legenda, $id_video);

        if ($stmt_up->execute()) {
            $msg_extra = "";

            if ($novo_upload && !empty($video_antigo) && $video_antigo !== $caminho_video_final) {
                if (apagar_video_se_orfao($conn, $video_antigo)) {
                    $msg_extra = " O vídeo anterior foi removido automaticamente.";
                }
            }

            $mensagem = "<div class='alert alert-success'>Vídeo e legenda atualizados com sucesso!{$msg_extra}</div>";
        } else {
            if ($novo_upload && is_file(__DIR__ . '/../' . $caminho_video_final)) {
                unlink(__DIR__ . '/../' . $caminho_video_final);
            }
            $mensagem = "<div class='alert alert-danger'>Erro no banco de dados ao atualizar.</div>";
        }
        $stmt_up->close();
    } else {
        $mensagem = "<div class='alert alert-danger'>" . htmlspecialchars($erro_video) . "</div>";
    }
}

$slots = $conn->query("SELECT * FROM destaques_videos ORDER BY id")->fetch_all(MYSQLI_ASSOC);
?>

<?= $mensagem; ?>
<div class="text-center mb-4">
    <h4 class="fw-bold mb-2">Gerenciamento de Vídeos em Destaque (Home)</h4>
    <p class="text-muted small">Atualize os vídeos e legendas exibidos nas 3 colunas da página inicial do SolaSol Ateliê.</p>
</div>
<hr class="mb-4">

<div class="row g-4">
    <?php foreach ($slots as $vid):
        $caminho_fisico_admin = __DIR__ . '/../' . $vid['video_url'];
        $tem_video_atual = !empty($vid['video_url']) && file_exists($caminho_fisico_admin);
    ?>
        <div class="col-md-4">
            <div class="card border bg-light p-3 h-100">
                <form action="admin.php?aba=videos" method="POST" enctype="multipart/form-data"
                      class="form-video-destaque" data-tem-video="<?= $tem_video_atual ? '1' : '0'; ?>">
                    <?= campo_csrf(); ?>
                    <input type="hidden" name="salvar_video" value="1">
                    <input type="hidden" name="id_video" value="<?= (int)$vid['id']; ?>">

                    <h5 class="fw-bold text-dark text-uppercase text-center mb-3"><?= htmlspecialchars($vid['categoria']); ?></h5>

                    <div class="mb-3 bg-dark rounded overflow-hidden position-relative" style="height: 180px;">
                        <?php if ($tem_video_atual): ?>
                            <video muted preload="metadata" class="w-100 h-100 object-fit-cover">
                                <source src="/solasolAtelie/<?= htmlspecialchars($vid['video_url']); ?>#t=0.5" type="video/mp4">
                            </video>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-white small">Sem vídeo cadastrado</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Legenda Principal (Título)</label>
                        <input type="text" name="titulo_legenda" maxlength="150" class="form-control form-control-sm" required value="<?= htmlspecialchars($vid['titulo_legenda']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Substituir Vídeo (.MP4)</label>
                        <input type="file" name="arquivo_video" class="form-control form-control-sm" accept="video/mp4">
                        <div class="form-text" style="font-size: 0.75rem;">Deixe em branco para manter o atual. Ao substituir, o vídeo anterior é apagado automaticamente.</div>
                    </div>

                    <button type="submit" class="btn btn-dark btn-sm w-100 fw-semibold text-uppercase py-2">Salvar Alterações</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
document.querySelectorAll('form.form-video-destaque').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        var arquivo = form.querySelector('input[type="file"][name="arquivo_video"]');
        var temArquivoNovo = arquivo && arquivo.files && arquivo.files.length > 0;
        var temVideoAtual = form.dataset.temVideo === '1';

        if (temArquivoNovo && temVideoAtual) {
            if (!confirm('Tem certeza que deseja alterar o vídeo em exibição?')) {
                e.preventDefault();
            }
        }
    });
});
</script>
