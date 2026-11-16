<?php
$nome_pre = htmlspecialchars((string)($_SESSION['usuario_nome'] ?? ''));
$email_pre = htmlspecialchars((string)($_SESSION['usuario_email'] ?? ''));
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h3 class="text-uppercase fw-light text-center mb-4">Entre em Contato</h3>
            <form id="formContato">
                <div class="mb-3">
                    <label class="form-label" for="contato-nome">Nome</label>
                    <input type="text" id="contato-nome" class="form-control" required maxlength="100" value="<?= $nome_pre; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="contato-email">E-mail</label>
                    <input type="email" id="contato-email" class="form-control" required maxlength="150" value="<?= $email_pre; ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="contato-msg">Mensagem</label>
                    <textarea id="contato-msg" class="form-control" rows="4" required maxlength="1500"></textarea>
                </div>
                <button type="submit" class="btn btn-dark w-100 text-uppercase">Enviar pelo WhatsApp</button>
                <p class="text-muted small text-center mt-2 mb-0">A mensagem será aberta no WhatsApp da loja para você enviar!</p>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('formContato').addEventListener('submit', function (e) {
    e.preventDefault();
    const nome = document.getElementById('contato-nome').value.trim();
    const email = document.getElementById('contato-email').value.trim();
    const msg = document.getElementById('contato-msg').value.trim();
    const texto = `Olá! Mensagem enviada pelo site:\n\n*Nome:* ${nome}\n*E-mail:* ${email}\n\n${msg}`;
    window.open('https://api.whatsapp.com/send?phone=5544997020518&text=' + encodeURIComponent(texto), '_blank', 'noopener');
});
</script>
