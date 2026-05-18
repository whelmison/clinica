CREATE DATABASE IF NOT EXISTS clinica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE clinica;

CREATE TABLE IF NOT EXISTS pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    telefone VARCHAR(30) NULL,
    convenio VARCHAR(120) NULL,
    valor_sessao DECIMAL(10,2) DEFAULT 0,
    prontuario VARCHAR(120) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS planos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    valor_sessao DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS profissionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    endereco VARCHAR(255) NULL,
    telefone VARCHAR(30) NULL,
    profissao VARCHAR(120) NULL,
    foto VARCHAR(255) NULL,
    permite_editar_guias TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(120) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(30) NOT NULL,
    profissional_id INT NULL,
    nome_exibicao VARCHAR(255) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuarios_perfil (perfil),
    INDEX idx_usuarios_profissional (profissional_id)
);

CREATE TABLE IF NOT EXISTS servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    tempo_minutos INT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS profissional_servico (
    profissional_id INT NOT NULL,
    servico_id INT NOT NULL,
    tempo_minutos INT NULL,
    PRIMARY KEY (profissional_id, servico_id)
);

CREATE TABLE IF NOT EXISTS lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_lote VARCHAR(50) NULL,
    convenio VARCHAR(180) NOT NULL,
    data DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'aberto',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_lotes_numero (numero_lote)
);

CREATE TABLE IF NOT EXISTS guias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(60) NULL,
    paciente_id INT NOT NULL,
    plano_id INT NULL,
    total_sessoes INT NOT NULL DEFAULT 1,
    data DATE NOT NULL,
    valor_guia DECIMAL(10,2) NOT NULL DEFAULT 0,
    recebido DECIMAL(10,2) NOT NULL DEFAULT 0,
    sessoes_usadas INT NOT NULL DEFAULT 0,
    profissional_id INT NULL,
    tipo_guia VARCHAR(30) NULL,
    lote_id INT NULL,
    convenio VARCHAR(120) NULL,
    conta_receber_gerada TINYINT(1) NOT NULL DEFAULT 0,
    conta_receber_id INT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_guias_paciente (paciente_id),
    INDEX idx_guias_profissional (profissional_id),
    INDEX idx_guias_lote (lote_id)
);

CREATE TABLE IF NOT EXISTS atendimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    data DATE NOT NULL,
    tipo VARCHAR(80) NULL,
    status VARCHAR(50) NULL,
    valor DECIMAL(10,2) DEFAULT 0,
    pago VARCHAR(30) NULL,
    guia_id INT NULL,
    status_atendimento VARCHAR(50) NOT NULL DEFAULT 'Realizado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_atendimentos_guia (guia_id)
);

CREATE TABLE IF NOT EXISTS agenda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_agendamento DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    profissional_id INT NOT NULL,
    servico_id INT NOT NULL,
    cliente_id INT NULL,
    cliente_nome VARCHAR(255) NOT NULL,
    cliente_telefone VARCHAR(30) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'agendado',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agenda_profissional_data (profissional_id, data_agendamento),
    INDEX idx_agenda_cliente (cliente_id)
);

CREATE TABLE IF NOT EXISTS agenda_disponibilidade (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NOT NULL,
    data_disponivel DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agenda_disponibilidade_profissional_data (profissional_id, data_disponivel)
);

CREATE TABLE IF NOT EXISTS plano_contas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    categoria_pai_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contas_pagar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    vencimento DATE NOT NULL,
    pagamento DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'aberto',
    plano_conta_id INT NULL,
    origem_tipo VARCHAR(30) NULL,
    origem_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contas_receber (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    vencimento DATE NOT NULL,
    recebimento DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'aberto',
    plano_conta_id INT NULL,
    profissional_id INT NULL,
    origem_tipo VARCHAR(30) NULL,
    origem_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contas_receber_origem (origem_tipo, origem_id)
);
