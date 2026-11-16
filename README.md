# SolaSol Ateliê — Loja Virtual

Loja virtual de moda (feminino, masculino e calçados) desenvolvida em **PHP 8**, **MySQL** e **Bootstrap 5**, com painel administrativo para gestão de produtos, estoque, pedidos, cupons e promoções.

## Funcionalidades

**Loja**
- Vitrines por categoria com filtro por faixa de preço e ordenação (painel "Filtros")
- Página de produto com galeria, seleção de cor/tamanho, avaliações e dúvidas
- Sacola, favoritos, cadastro/login de clientes e histórico de compras
- Checkout com cálculo de frete, cupom de desconto, Pix (BR Code) e cartão (simulado)

**Painel administrativo**
- Produtos com grade de cores/tamanhos e controle de estoque
- Pedidos (status, rastreio, devolução automática de estoque no cancelamento)
- Cupons, promoções por período, frete, vídeos em destaque, dúvidas
- Usuários do painel com permissões por área

**Segurança**
- Senhas com `password_hash`, proteção CSRF, limite de tentativas de login
- Consultas preparadas (prepared statements) e validação no servidor de preços, cupons e estoque
- `.htaccess` bloqueando listagem de diretórios e acesso direto a arquivos internos

## Estrutura

```
├── index.php              # Template da loja (cabeçalho, menu, rodapé) + roteamento ?pagina=
├── admin.php              # Template e roteamento do painel administrativo
├── paginas/               # Páginas da loja (incluídas pelo index.php)
├── paginas-admin/         # Páginas do painel (incluídas pelo admin.php)
├── acoes/                 # Endpoints (salvar pedido, validar cupom, estoque)
├── funcoes*.php           # Funções de catálogo, estoque, clientes e administração
├── css/                   # style.scss (fonte) e style.css (compilado)
├── imagens/, uploads/     # Imagens dos produtos e vídeos
├── banco/solasol_atelie.sql   # Script de criação do banco de dados
└── documentacao/          # Diagrama Entidade-Relacionamento (DER)
```

## Banco de dados

O script `banco/solasol_atelie.sql` cria o banco `solasol_atelie` com **15 tabelas**, chaves primárias, **8 chaves estrangeiras** e o relacionamento **N:N** entre `pedidos` e `produto` (tabela associativa `pedido_itens`). Também carrega o catálogo de produtos. Não contém dados de clientes, pedidos ou senhas.

O DER está em `documentacao/DER_solasol.html`.

## Instalação (XAMPP)

Requisitos: XAMPP com PHP 8.1 ou superior, Apache e MySQL ligados no XAMPP Control Panel.

1. **Baixe e copie a pasta.** Em **Code > Download ZIP**, extraia o arquivo e **renomeie a pasta para `solasolAtelie`**
   (exatamente assim). Copie para `C:\xampp\htdocs\`, ficando `C:\xampp\htdocs\solasolAtelie\index.php`.

   > ⚠️ O nome da pasta precisa ser `solasolAtelie`: o site usa caminhos como `/solasolAtelie/imagens/...`.
   > Com outro nome (por exemplo `solasol-atelie-main`), imagens e estilos não carregam.

2. **Crie o banco.** Abra `http://localhost/phpmyadmin`, aba **Importar**, escolha o arquivo
   `C:\xampp\htdocs\solasolAtelie\banco\solasol_atelie.sql` e clique em **Importar**.
   O script cria o banco `solasol_atelie` com as tabelas e os produtos.

3. **Crie os dois arquivos de configuração.** Na pasta `solasolAtelie`, faça uma cópia de cada arquivo abaixo, com o novo nome:

   | Copiar | Para |
   |---|---|
   | `conexao.exemplo.php` | `conexao.php` |
   | `config_admin.exemplo.php` | `config_admin.php` |

   O `conexao.exemplo.php` já vem pronto para o XAMPP padrão (usuário `root`, sem senha). Não é preciso editar.

4. **Acesse.**
   - Loja: `http://localhost/solasolAtelie/`
   - Painel: `http://localhost/solasolAtelie/admin_login.php`, com usuário **admin** e senha **SolaSol@2026**
     (login de demonstração; troque a senha em Painel > Usuários).

## Infraestrutura do projeto

Ambiente montado em duas máquinas:

| Máquina | Função | Endereço |
|---|---|---|
| Windows + XAMPP (Apache/PHP) | Servidor web, porta **8080**, virtual host `solasol.local` | 192.168.56.1 |
| VM Ubuntu Server 26.04 LTS (VirtualBox) | Servidor MySQL 8.4 com **IP fixo** | 192.168.56.10 |

- Rede host-only entre as máquinas + NAT na VM para atualizações
- DNS local: `127.0.0.1 solasol.local` no arquivo `hosts` do Windows
- Firewall UFW na VM liberando apenas SSH (22) e MySQL (3306) a partir do servidor web
- Acesso SSH à VM por chave ed25519

## Tecnologias

PHP 8 · MySQL 8 / MariaDB · Bootstrap 5.3 · JavaScript · Sass · Apache · Ubuntu Server · VirtualBox
