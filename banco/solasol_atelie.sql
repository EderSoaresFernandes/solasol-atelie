-- SolaSol Ateliê - script de criação do banco de dados
-- Compatível com MySQL 8 e MariaDB 10.4+ (XAMPP)
-- Cria o banco, as tabelas (PKs, FKs e relacionamento N:N pedidos <-> produto via pedido_itens)
-- e carrega o catálogo de produtos. Não contém dados de clientes, pedidos ou senhas.

CREATE DATABASE IF NOT EXISTS solasol_atelie DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE solasol_atelie;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `admin_login_tentativas`;
CREATE TABLE `admin_login_tentativas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_data` (`ip`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `admin_usuarios`;
CREATE TABLE `admin_usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `nome` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `senha_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `permissoes` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `principal` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `sessao_versao` int NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `cliente_login_tentativas`;
CREATE TABLE `cliente_login_tentativas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) COLLATE utf8mb4_general_ci NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_data` (`ip`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id_cliente` int NOT NULL AUTO_INCREMENT,
  `tipo_pessoa` varchar(2) COLLATE utf8mb4_general_ci DEFAULT 'PF',
  `nome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `sobrenome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `cpf` varchar(14) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `whatsapp` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `senha` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `newsletter` tinyint(1) DEFAULT '1',
  `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `codigo_recuperacao` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `expira_codigo` datetime DEFAULT NULL,
  `cpf_cnpj` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rg_ie` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rua` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bairro` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cep` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cidade` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `estado` varchar(2) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `complemento` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tentativas_codigo` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `cpf` (`cpf`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `configuracoes_frete`;
CREATE TABLE `configuracoes_frete` (
  `id` int NOT NULL AUTO_INCREMENT,
  `valor_minimo_frete_gratis` decimal(10,2) DEFAULT '199.90',
  `dentro_estado` decimal(10,2) DEFAULT '15.00',
  `regiao_sul` decimal(10,2) DEFAULT '20.00',
  `regiao_sudeste` decimal(10,2) DEFAULT '25.00',
  `regiao_centro_oeste` decimal(10,2) DEFAULT '35.00',
  `regiao_nordeste` decimal(10,2) DEFAULT '45.00',
  `regiao_norte` decimal(10,2) DEFAULT '50.00',
  `prazo_estado` int DEFAULT '3',
  `prazo_sul` int DEFAULT '5',
  `prazo_sudeste` int DEFAULT '6',
  `prazo_centro_oeste` int DEFAULT '7',
  `prazo_nordeste` int DEFAULT '10',
  `prazo_norte` int DEFAULT '12',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `configuracoes_frete` (`id`, `valor_minimo_frete_gratis`, `dentro_estado`, `regiao_sul`, `regiao_sudeste`, `regiao_centro_oeste`, `regiao_nordeste`, `regiao_norte`, `prazo_estado`, `prazo_sul`, `prazo_sudeste`, `prazo_centro_oeste`, `prazo_nordeste`, `prazo_norte`) VALUES
(1, 499.00, 29.90, 39.90, 49.90, 59.90, 69.90, 79.90, 7, 10, 12, 14, 16, 20);

DROP TABLE IF EXISTS `cupons`;
CREATE TABLE `cupons` (
  `id_cupom` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` enum('percentual','valor') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'percentual',
  `regra` enum('livre','primeira_compra') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'livre',
  `valor` decimal(10,2) NOT NULL,
  `valor_minimo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `inicio` date DEFAULT NULL,
  `validade` date DEFAULT NULL,
  `limite_uso` int DEFAULT NULL,
  `usos` int NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cupom`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `cupons` (`id_cupom`, `codigo`, `tipo`, `regra`, `valor`, `valor_minimo`, `inicio`, `validade`, `limite_uso`, `usos`, `ativo`, `criado_em`) VALUES
(1, 'atelie', 'percentual', 'primeira_compra', 10.00, 0.00, NULL, NULL, NULL, 0, 1, '2026-11-14 12:54:59');

DROP TABLE IF EXISTS `destaques_videos`;
CREATE TABLE `destaques_videos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `video_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `titulo_legenda` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `destaques_videos` (`id`, `categoria`, `video_url`, `titulo_legenda`) VALUES
(1, 'Moda Feminina', 'uploads/videos/video_1794262824_8281.mp4', 'Moda Feminina'),
(2, 'Moda Masculina', 'uploads/videos/video_1794262859_6254.mp4', 'Moda Masculina'),
(3, 'Calçados', 'uploads/videos/video_1794263626_7621.mp4', 'Nossos Calçados');

DROP TABLE IF EXISTS `pedido_itens`;
CREATE TABLE `pedido_itens` (
  `id_item` int NOT NULL AUTO_INCREMENT,
  `id_pedido` int NOT NULL,
  `id_produto` int DEFAULT NULL,
  `nome_produto` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `cor` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `tamanho` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `quantidade` int NOT NULL DEFAULT '1',
  `preco_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_item`),
  KEY `fk_itens_pedido` (`id_pedido`),
  KEY `fk_itens_produto` (`id_produto`),
  CONSTRAINT `fk_itens_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedidos` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `fk_itens_produto` FOREIGN KEY (`id_produto`) REFERENCES `produto` (`id_produto`) ON DELETE SET NULL,
  CONSTRAINT `chk_itens_preco` CHECK ((`preco_unitario` >= 0)),
  CONSTRAINT `chk_itens_qtd` CHECK ((`quantidade` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `pedidos`;
CREATE TABLE `pedidos` (
  `id_pedido` int NOT NULL AUTO_INCREMENT,
  `id_cliente` int DEFAULT NULL,
  `nome_cliente` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `email_cliente` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `telefone_cliente` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `cpf_cnpj` varchar(18) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cep` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `endereco` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `numero` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `bairro` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `cidade` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `estado` varchar(2) COLLATE utf8mb4_general_ci NOT NULL,
  `forma_pagamento` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `frete` decimal(10,2) NOT NULL,
  `desconto_pix` decimal(10,2) DEFAULT '0.00',
  `total_geral` decimal(10,2) NOT NULL,
  `itens_json` text COLLATE utf8mb4_general_ci NOT NULL,
  `status_pedido` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'Pendente',
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `codigo_rastreio` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_prevista` date DEFAULT NULL,
  `status_entrega` varchar(60) COLLATE utf8mb4_general_ci DEFAULT 'Pendente',
  `cupom_codigo` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `desconto_cupom` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estoque_baixado` tinyint(1) NOT NULL DEFAULT '0',
  `parcelas` tinyint NOT NULL DEFAULT '1',
  `nota_fiscal` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nota_fiscal_chave` char(44) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `relato_entrega` text COLLATE utf8mb4_general_ci,
  `data_relato_entrega` datetime DEFAULT NULL,
  `resposta_entrega` text COLLATE utf8mb4_general_ci,
  `data_resolucao_entrega` datetime DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  CONSTRAINT `fk_pedidos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `produto`;
CREATE TABLE `produto` (
  `id_produto` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_general_ci,
  `preco_original` decimal(10,2) NOT NULL,
  `preco_pix` decimal(10,2) NOT NULL,
  `parcelas_max` int DEFAULT '10',
  `imagem_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `estoque` int NOT NULL DEFAULT '1',
  `cores` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tamanhos` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tabela_medidas` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_produto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `produto` (`id_produto`, `nome`, `categoria`, `descricao`, `preco_original`, `preco_pix`, `parcelas_max`, `imagem_url`, `estoque`, `cores`, `tamanhos`, `tabela_medidas`) VALUES
(15, 'Shorts Feminino Linho', 'Moda Feminina', 'Shorts feminino confeccionado em linho de alta qualidade. Conta com comprimento curto e modelagem mais justa. Possui cintura alta, fechamento por botão e zíper, gancho no lugar e barra simples. Conta com bolsos frontais estilo faca e posteriores funcionais. A peça apresenta tonalidade areia com acabamento no mesmo tom, tornando-a uma base neutra para diversas combinações e estilos. As pregas frontais dão ao modelo um toque de alfaiataria, realçando a modelagem e o caimento. Além disso, o shorts curto é uma excelente opção para valorizar as pernas.\r\n\r\nDetalhes:\r\nModelagem: Shorts Curto\r\nCintura: Alta\r\nGancho: No lugar\r\nFechamento: Botão e zíper\r\nBolsos: Frontais e posteriores funcionais\r\nFerragens: Metalizadas\r\nBarra: Simples\r\nTecido: Linho\r\nLavagem: Color\r\nCor: Areia', 149.90, 139.00, 6, 'imagens/produtos/1792703414_0_Shorts Sawary Ref.281522.6.webp', 1, 'Azul, Preto', 'P, M, G', NULL),
(29, 'Camisa Alfaiataria Clássica', 'Moda Feminina', 'Camisa Feminina modelagem Over, tendência da estação!', 199.90, 189.91, 6, 'imagens/produtos/1792872672_0_WhatsAppImage2026-10-24at12.04.431.jpeg', 6, NULL, NULL, 'Tam.P - Veste manequim: 36/38\r\nTam.M - Veste manequim: 40/42\r\nTam.G - Veste manequim: 44/46\r\nTam.GG - Veste manequim: 48/50'),
(30, 'Calça Wide Leg 100% Algodão', 'Moda Feminina', 'Calça Wide Leg 100% Algodão, modelagem Petit, ideal para mulheres de 1,60m de altura!', 199.90, 189.91, 6, 'imagens/produtos/1792872998_0_WhatsAppImage2026-10-24at12.04.393.jpeg', 6, NULL, NULL, 'Tabela de Medidas de Referência (Cintura e Quadril)\r\n\r\nTamanho	Cintura (cm)		Quadril (cm)		Comprimento aprox. (cm)\r\n    34		 64 – 68 cm		 90 – 94 cm			     113 cm\r\n    36		 69 – 72 cm		 95 – 98 cm			     113,5 cm\r\n    38		 73 – 76 cm		99 – 102 cm			     114 cm\r\n    40		 77 – 80 cm		103 – 106 cm	             114,5 cm\r\n    42		 81 – 84 cm		107 – 110 cm		     115 cm\r\n    44		 85 – 88 cm		111 – 114 cm		     115,5 cm\r\n    46		 89 – 92 cm		115 – 118 cm		     116 cm\r\n    48		 93 – 96 cm		119 – 122 cm	             116,5 cm'),
(42, 'Saia Midi Feminina Estampa Poa', 'Moda Feminina', 'Saia Longa estampa poá, tendência da estação!', 199.90, 189.91, 6, 'imagens/produtos/1792887490_0_WhatsAppImage2026-10-24at12.04.411.jpeg', 5, NULL, NULL, 'Tam.P - Veste manequim: 36/38\r\nTam.M - Veste manequim: 40/42\r\nTam.G - Veste manequim: 44/46\r\nTam.GG - Veste manequim: 48/50'),
(43, 'Saia Midi Alfaiataria', 'Moda Feminina', 'Saia Midi Alfaiataria, elegante e moderna!\r\nIdeal para ocasiões especiais de Fim de Ano!!', 199.90, 189.00, 6, 'imagens/produtos/1792961450_0_WhatsAppImage2026-10-24at12.04.381.jpeg', 5, NULL, NULL, 'Tabela de Medidas\r\nTam: P - Cintura: 80 cm\r\nTam.M - Cintura: 85 cm\r\nTam.G - Cintura: 90 cm\r\nTam.GG - Cintura: 95 cm\r\n'),
(44, 'Colete Alfaiataria Botões Funcionais', 'Moda Feminina', 'Colete Alta Costura com Botões Funcionais!', 169.90, 159.00, 6, 'imagens/produtos/1792961843_0_WhatsAppImage2026-10-24at12.04.421.jpeg', 6, NULL, NULL, 'Tabela de Medidas\r\nTam.P - Manequim: 36/38\r\nTam.M - Manequim: 40/42\r\nTam.G - Manequim: 44/46\r\nTam.GG - Manequim: 46/48'),
(101, 'Bermuda Masculina Sarja', 'Moda Masculina', 'Bermuda sarja com elastano. Estilo contemporâneo!', 149.90, 142.41, 6, 'imagens/produtos/d2a8bf53b9d1809c9e5214d4.jpg', 4, NULL, NULL, 'tam.36 - Veste P\r\ntam.38 - Veste P\r\ntam.40 - Veste M\r\ntam.42 - Veste M\r\ntam.44 - Veste G\r\ntam.46 - Veste G'),
(102, 'Camiseta Algodão Bordada', 'Moda Masculina', 'Camiseta Algodão Penteado Premium!', 159.90, 151.91, 6, 'imagens/produtos/616d0234e33b3aa2ea665bd0.jpg', 4, NULL, NULL, 'tam.36 - Veste P\r\ntam.38 - Veste P\r\ntam.40 - Veste M\r\ntam.42 - Veste M\r\ntam.44 - Veste G\r\ntam.46 - Veste G\r\ntam.48 - Veste GG'),
(103, 'Calça Sarja Elastano', 'Moda Masculina', 'Calça Sarja com Elastano. Estilo Casual contemporâneo!', 169.90, 161.41, 6, 'imagens/produtos/1cdba8bd07d53ded353344bf.jpg', 4, NULL, NULL, 'tam.36 - Veste P\r\ntam.38 - Veste P\r\ntam.40 - Veste M\r\ntam.42 - Veste M\r\ntam.44 - Veste G\r\ntam.46 - Veste G\r\ntam.48 - Veste GG'),
(104, 'Camiseta Algodão Estampa', 'Moda Masculina', 'Camiseta Algodão Premium, estampa minimalista!', 159.90, 151.91, 6, 'imagens/produtos/8fbc083f223cae78af23ed28.jpg', 4, NULL, NULL, 'tam.36 - Veste P\r\ntam.38 - Veste P\r\ntam.40 - Veste M\r\ntam.42 - Veste M\r\ntam.44 - Veste G\r\ntam.46 - Veste G\r\ntam.48 - Veste GG'),
(105, 'Sandália Dakota Rasteira', 'Calçados', 'Sandália Rasteira Pedraria, confortável e moderna!', 169.90, 161.41, 6, 'imagens/produtos/55fcae7c1b1463443a10236e.jpg', 4, NULL, NULL, 'N. 34 - 22 Cm\r\nN.35 - 23 Cm\r\nN.36 - 24 Cm\r\nN.37 - 25 Cm\r\nN.38 - 26 Cm\r\nN.39 - 27 Cm'),
(106, 'Sandália Dakota Salto', 'Calçados', 'Sandália Salto Bloco, detalhes em brilho!', 199.90, 189.91, 6, 'imagens/produtos/d7fa83190edc2a0281de4e68.jpg', 4, NULL, NULL, 'N. 34 - 22 Cm\r\nN.35 - 23 Cm\r\nN.36 - 24 Cm\r\nN.37 - 25 Cm\r\nN.38 - 26 Cm\r\nN.39 - 27 Cm'),
(107, 'Tênis Comply Logo', 'Calçados', 'Tênis urbano camurça com detalhe logo!', 229.90, 218.41, 6, 'imagens/produtos/64e4ab96d4b59e31bfa99099.jpg', 4, NULL, NULL, 'N.37 - 25 Cm\r\nN.38 - 26 Cm\r\nN.39 - 27 Cm\r\nN.40 - 28 Cm\r\nN.41 - 29 Cm\r\nN.42 - 30 Cm'),
(108, 'Tênis Comply Urbano', 'Calçados', 'Tênis Camurça, sola vulcanizada, urbano!', 249.90, 237.41, 6, 'imagens/produtos/e7935ba1237e3a99fd2a0405.jpg', 4, NULL, NULL, 'N.37 - 25 Cm\r\nN.38 - 26 Cm\r\nN.39 - 27 Cm\r\nN.40 - 28 Cm\r\nN.41 - 29 Cm\r\nN.42 - 30 Cm');

DROP TABLE IF EXISTS `produto_avaliacoes`;
CREATE TABLE `produto_avaliacoes` (
  `id_avaliacao` int NOT NULL AUTO_INCREMENT,
  `produto_id` int NOT NULL,
  `nota` decimal(2,1) NOT NULL,
  `comentario` text COLLATE utf8mb4_general_ci,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_cliente` int DEFAULT NULL,
  PRIMARY KEY (`id_avaliacao`),
  KEY `fk_avaliacoes_produto` (`produto_id`),
  KEY `fk_avaliacoes_cliente` (`id_cliente`),
  CONSTRAINT `fk_avaliacoes_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE SET NULL,
  CONSTRAINT `fk_avaliacoes_produto` FOREIGN KEY (`produto_id`) REFERENCES `produto` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `produto_cores_tamanhos`;
CREATE TABLE `produto_cores_tamanhos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produto_id` int NOT NULL,
  `cor` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tamanho` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `quantidade` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `produto_id` (`produto_id`),
  CONSTRAINT `produto_cores_tamanhos_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produto` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `produto_cores_tamanhos` (`id`, `produto_id`, `cor`, `tamanho`, `quantidade`) VALUES
(11, 29, 'Verde Musgo', 'P', 1),
(12, 29, 'Verde Musgo', 'M', 2),
(13, 29, 'Amarelo', 'G', 1),
(14, 29, 'Amarelo', 'GG', 2),
(15, 30, 'Azul Ascuro', '36', 1),
(16, 30, 'Azul Ascuro', '38', 2),
(17, 30, 'Azul Ascuro', '40', 0),
(18, 30, 'Preto', '40', 1),
(19, 30, 'Preto', '42', 2),
(47, 42, 'Branco', 'P', 1),
(48, 42, 'Branco', 'M', 1),
(49, 42, 'Preto', 'G', 2),
(50, 42, 'Preto', 'GG', 1),
(51, 43, 'Manteiga', 'P', 1),
(52, 43, 'Manteiga', 'M', 2),
(53, 43, 'Verde', 'G', 2),
(54, 43, 'Verde', 'GG', 0),
(55, 44, 'Preto', 'P', 1),
(56, 44, 'Preto', 'M', 2),
(57, 44, 'Vermelho', 'G', 2),
(58, 44, 'Vermelho', 'GG', 1),
(59, 101, 'Marrom', '36', 1),
(60, 101, 'Marrom', '38', 1),
(61, 101, 'Preto', '40', 1),
(62, 101, 'Preto', '42', 1),
(63, 102, 'Verde', 'P', 1),
(64, 102, 'Verde', 'M', 1),
(65, 102, 'Preto', 'G', 1),
(66, 102, 'Preto', 'GG', 1),
(67, 103, 'Calça Sarja Elastano', '40', 1),
(68, 103, 'Calça Sarja Elastano', '42', 1),
(69, 103, 'Azul', '44', 1),
(70, 103, 'Azul', '46', 1),
(71, 104, 'Preto', 'P', 1),
(72, 104, 'Preto', 'M', 1),
(73, 104, 'Verde', 'G', 1),
(74, 104, 'Verde', 'GG', 1),
(75, 105, 'Marrom', '35', 1),
(76, 105, 'Marrom', '36', 1),
(77, 105, 'Preto', '37', 1),
(78, 105, 'Preto', '38', 1),
(79, 106, 'Marrom', '36', 1),
(80, 106, 'Marrom', '37', 1),
(81, 106, 'Preto', '38', 1),
(82, 106, 'Preto', '39', 1),
(83, 107, 'Preto', '37', 1),
(84, 107, 'Preto', '38', 1),
(85, 107, 'Marrom', '39', 1),
(86, 107, 'Marrom', '40', 1),
(87, 108, 'Preto', '39', 1),
(88, 108, 'Preto', '40', 1),
(89, 108, 'Branco', '41', 1),
(90, 108, 'Branco', '42', 1);

DROP TABLE IF EXISTS `produto_duvidas`;
CREATE TABLE `produto_duvidas` (
  `id_duvida` int NOT NULL AUTO_INCREMENT,
  `produto_id` int NOT NULL,
  `pergunta` text COLLATE utf8mb4_general_ci NOT NULL,
  `resposta` text COLLATE utf8mb4_general_ci,
  `data_criacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `nome_cliente` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `whatsapp_cliente` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_duvida`),
  KEY `fk_duvidas_produto` (`produto_id`),
  CONSTRAINT `fk_duvidas_produto` FOREIGN KEY (`produto_id`) REFERENCES `produto` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `produto_fotos`;
CREATE TABLE `produto_fotos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produto_id` int NOT NULL,
  `imagem_url` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_fotos_produto` (`produto_id`),
  CONSTRAINT `fk_fotos_produto` FOREIGN KEY (`produto_id`) REFERENCES `produto` (`id_produto`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `produto_fotos` (`id`, `produto_id`, `imagem_url`) VALUES
(31, 15, 'imagens/produtos/1792703414_0_Shorts Sawary Ref.281522.6.webp'),
(32, 15, 'imagens/produtos/1792703414_1_Shorts Sawary Ref.281522.7.webp'),
(33, 15, 'imagens/produtos/1792703414_2_Shorts Sawary Ref.281522_2.webp'),
(34, 15, 'imagens/produtos/1792703414_3_Shorts Sawary Ref.281522_3.webp'),
(35, 15, 'imagens/produtos/1792703414_4_Shorts Sawary Ref.281522_4.webp'),
(36, 15, 'imagens/produtos/1792703414_5_Shorts Sawary Ref.281522_5.webp'),
(70, 29, 'imagens/produtos/1792872672_0_WhatsAppImage2026-10-24at12.04.431.jpeg'),
(71, 29, 'imagens/produtos/1792872672_1_WhatsAppImage2026-10-24at12.04.432.jpeg'),
(72, 29, 'imagens/produtos/1792872672_2_WhatsAppImage2026-10-24at12.04.433.jpeg'),
(73, 30, 'imagens/produtos/1792872998_0_WhatsAppImage2026-10-24at12.04.393.jpeg'),
(74, 30, 'imagens/produtos/1792872998_1_WhatsAppImage2026-10-24at12.04.394.jpeg'),
(75, 30, 'imagens/produtos/1792872998_2_WhatsAppImage2026-10-24at12.04.395.jpeg'),
(109, 42, 'imagens/produtos/1792887490_0_WhatsAppImage2026-10-24at12.04.411.jpeg'),
(110, 42, 'imagens/produtos/1792887490_1_WhatsAppImage2026-10-24at12.04.412.jpeg'),
(111, 42, 'imagens/produtos/1792887490_2_WhatsAppImage2026-10-24at12.04.41.jpeg'),
(112, 43, 'imagens/produtos/1792961450_0_WhatsAppImage2026-10-24at12.04.381.jpeg'),
(113, 43, 'imagens/produtos/1792961450_1_WhatsAppImage2026-10-24at12.04.391.jpeg'),
(114, 43, 'imagens/produtos/1792961450_2_WhatsAppImage2026-10-24at12.04.39.jpeg'),
(115, 44, 'imagens/produtos/1792961843_0_WhatsAppImage2026-10-24at12.04.421.jpeg'),
(116, 44, 'imagens/produtos/1792961843_1_WhatsAppImage2026-10-24at12.04.422.jpeg'),
(117, 44, 'imagens/produtos/1792961843_2_WhatsAppImage2026-10-24at12.04.423.jpeg'),
(118, 101, 'imagens/produtos/d2a8bf53b9d1809c9e5214d4.jpg'),
(119, 101, 'imagens/produtos/7c6655cd7951717bd6fe6b1b.jpg'),
(120, 101, 'imagens/produtos/eacf3e8cc919e04fd6b3250a.jpg'),
(121, 102, 'imagens/produtos/616d0234e33b3aa2ea665bd0.jpg'),
(122, 102, 'imagens/produtos/ee44261649e9c8bff50ebfef.jpg'),
(123, 102, 'imagens/produtos/91d151b4abda22d2d94b2d16.jpg'),
(124, 103, 'imagens/produtos/1cdba8bd07d53ded353344bf.jpg'),
(125, 103, 'imagens/produtos/9d32cd5d214352b3889624e6.jpg'),
(126, 103, 'imagens/produtos/3715072763fd9c2180b6a63a.jpg'),
(127, 104, 'imagens/produtos/8fbc083f223cae78af23ed28.jpg'),
(128, 104, 'imagens/produtos/80080a1178b0149400bfe0a7.jpg'),
(129, 104, 'imagens/produtos/3800f1fa3f29b08cecbc5a4f.jpg'),
(130, 105, 'imagens/produtos/55fcae7c1b1463443a10236e.jpg'),
(131, 105, 'imagens/produtos/903be4086e7b46f730fd91b9.jpg'),
(132, 105, 'imagens/produtos/5b8ef7bc58771009361eae5e.jpg'),
(133, 106, 'imagens/produtos/d7fa83190edc2a0281de4e68.jpg'),
(134, 106, 'imagens/produtos/009d9f2b0cd51184ccb7a619.jpg'),
(135, 106, 'imagens/produtos/12a100736da936bc78a51fd3.jpg'),
(136, 107, 'imagens/produtos/64e4ab96d4b59e31bfa99099.jpg'),
(137, 107, 'imagens/produtos/c55d5313df504ce9dbe30ff8.jpg'),
(138, 107, 'imagens/produtos/5b8a2e6d97bc45f9231383a4.jpg'),
(139, 108, 'imagens/produtos/e7935ba1237e3a99fd2a0405.jpg'),
(140, 108, 'imagens/produtos/ac3f2b9a0c376db0bdbdb8b7.jpg'),
(141, 108, 'imagens/produtos/a23bf2998c1e114681168bbc.jpg');

DROP TABLE IF EXISTS `promocoes`;
CREATE TABLE `promocoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` enum('percentual','valor') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'percentual',
  `valor` decimal(10,2) NOT NULL,
  `valor_minimo` decimal(10,2) NOT NULL DEFAULT '0.00',
  `categorias` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `produtos` text COLLATE utf8mb4_general_ci,
  `inicio` datetime NOT NULL,
  `fim` datetime NOT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_periodo` (`ativo`,`inicio`,`fim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `promocoes` (`id`, `nome`, `tipo`, `valor`, `valor_minimo`, `categorias`, `produtos`, `inicio`, `fim`, `ativo`, `criado_em`) VALUES
(5, 'Black Friday', 'percentual', 20.00, 150.00, 'Moda Feminina,Moda Masculina,Calçados', '', '2026-11-14 21:00:00', '2026-11-30 23:59:00', 1, '2026-11-14 21:46:26');

SET FOREIGN_KEY_CHECKS = 1;
