<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/connection.php';

set_time_limit(0);
date_default_timezone_set('America/Sao_Paulo');

final class StressDataSeeder
{
    private const SEED_KEY = 'perf20260506';

    private PDO $pdo;
    private DateTimeImmutable $today;
    private DateTimeImmutable $startDate;
    private array $plans = [];
    private array $particularPlans = [];
    private array $convenioPlans = [];
    private array $revenueAccounts = [];
    private array $expenseAccounts = [];
    private array $costCenters = [];
    private array $financialAccounts = [];
    private array $professionals = [];
    private array $newProfessionalIds = [];
    private array $services = [];
    private array $newServiceIds = [];
    private array $serviceMapByProfessional = [];
    private array $lots = [];
    private array $patients = [];
    private array $patientsByProfessional = [];
    private array $patientGuideMap = [];
    private array $guideStates = [];
    private array $guideIdsByLot = [];
    private array $patientRotation = [];
    private array $inserted = [
        'servicos' => 0,
        'profissionais' => 0,
        'usuarios' => 0,
        'lotes' => 0,
        'pacientes' => 0,
        'guias' => 0,
        'contas_receber' => 0,
        'contas_pagar' => 0,
        'agenda_disponibilidade' => 0,
        'agenda' => 0,
        'atendimentos' => 0,
        'profissional_servico' => 0,
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->today = new DateTimeImmutable('today');
        $this->startDate = $this->today->modify('-5 months');
        mt_srand(20260506);
    }

    public function run(): void
    {
        $this->write('Iniciando carga grande para relatorios e performance...');
        $this->loadReferenceData();
        $this->pdo->beginTransaction();

        try {
            $this->createServices(20);
            $this->createProfessionals(50);
            $this->refreshProfessionalsAndServices();
            $this->ensureProfessionalServices();
            $this->createUsers(10);
            $this->createLots(50);
            $this->createPatientsAndGuides(1000);
            $this->createReceivables();
            $this->createPayables(500);
            $this->createScheduleHistory();
            $this->syncGuideUsage();
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        $this->printSummary();
    }

    private function loadReferenceData(): void
    {
        $this->plans = $this->fetchAll('SELECT id, nome FROM planos ORDER BY id');

        if ($this->plans === []) {
            throw new RuntimeException('Nenhum plano encontrado para montar a carga.');
        }

        foreach ($this->plans as $plan) {
            if (stripos((string) $plan['nome'], 'particular') !== false) {
                $this->particularPlans[] = $plan;
            } else {
                $this->convenioPlans[] = $plan;
            }
        }

        if ($this->particularPlans === []) {
            $this->particularPlans = [$this->plans[0]];
        }

        if ($this->convenioPlans === []) {
            $this->convenioPlans = $this->plans;
        }

        $this->revenueAccounts = $this->fetchAll("SELECT id, nome, tipo FROM plano_contas WHERE tipo = 'receita' AND ativo = 1 AND aceita_lancamento = 1 ORDER BY id");
        $this->expenseAccounts = $this->fetchAll("SELECT id, nome, tipo FROM plano_contas WHERE tipo = 'despesa' AND ativo = 1 AND aceita_lancamento = 1 ORDER BY id");
        $this->costCenters = $this->fetchAll('SELECT id, nome FROM centros_custo WHERE ativo = 1 ORDER BY id');
        $this->financialAccounts = $this->fetchAll('SELECT id, nome, tipo FROM contas_financeiras WHERE ativo = 1 ORDER BY id');

        if ($this->revenueAccounts === [] || $this->expenseAccounts === [] || $this->costCenters === [] || $this->financialAccounts === []) {
            throw new RuntimeException('Cadastros financeiros base insuficientes para a carga.');
        }
    }

    private function createServices(int $count): void
    {
        $this->write('Criando servicos...');

        $catalog = [
            ['Avaliacao biomecanica funcional', 50],
            ['Pilates clinico individual', 60],
            ['RPG segmentado', 55],
            ['Fisioterapia neurofuncional', 50],
            ['Fisioterapia respiratoria adulto', 40],
            ['Treino de equilibrio funcional', 45],
            ['Reabilitacao pos-operatoria', 50],
            ['Terapia manual integrada', 45],
            ['Drenagem linfatica terapeutica', 60],
            ['Liberacao miofascial guiada', 40],
            ['Consulta de dor cronica', 30],
            ['Atendimento home care', 70],
            ['Reeducacao postural global', 60],
            ['Treino cardiorrespiratorio', 45],
            ['Fortalecimento para idosos', 50],
            ['Pre-natal funcional', 50],
            ['Reabilitacao esportiva', 45],
            ['Atendimento vestibular', 40],
            ['Fonoterapia motora', 45],
            ['Estimulacao cognitivo-motora', 50],
        ];

        $stmt = $this->pdo->prepare('INSERT INTO servicos (nome, tempo_minutos, ativo) VALUES (?, ?, 1)');

        foreach (array_slice($catalog, 0, $count) as $item) {
            $stmt->execute([$item[0], $item[1]]);
            $this->newServiceIds[] = (int) $this->pdo->lastInsertId();
            $this->inserted['servicos']++;
        }
    }

    private function createProfessionals(int $count): void
    {
        $this->write('Criando profissionais...');

        $firstNames = ['Helena', 'Miguel', 'Laura', 'Arthur', 'Beatriz', 'Enzo', 'Valentina', 'Gael', 'Cecilia', 'Davi', 'Antonella', 'Theo', 'Clara', 'Ravi', 'Alice', 'Noah', 'Yasmin', 'Samuel', 'Marina', 'Benjamin', 'Livia', 'Pedro', 'Manuela', 'Lucas', 'Sara', 'Joao', 'Nina', 'Matheus', 'Isadora', 'Caio'];
        $lastNamesA = ['Almeida', 'Castro', 'Siqueira', 'Barros', 'Nogueira', 'Pereira', 'Lima', 'Freitas', 'Melo', 'Araujo', 'Carvalho', 'Borges', 'Teixeira', 'Machado', 'Moura'];
        $lastNamesB = ['Silva', 'Souza', 'Oliveira', 'Costa', 'Fernandes', 'Rodrigues', 'Martins', 'Ribeiro', 'Rocha', 'Gomes', 'Monteiro', 'Dias', 'Correia', 'Macedo', 'Queiroz'];
        $professions = [
            'Fisioterapeuta traumato-ortopedico',
            'Fisioterapeuta neurofuncional',
            'Fisioterapeuta respiratorio',
            'Fisioterapeuta esportivo',
            'Instrutor de pilates clinico',
            'Terapeuta ocupacional',
            'Psicologa clinica',
            'Fonoaudiologa',
            'Fisioterapeuta pelvico',
            'Reabilitador cardiorrespiratorio',
        ];
        $districts = ['Centro', 'Adrianopolis', 'Vieiralves', 'Dom Pedro', 'Parque Dez', 'Flores', 'Aleixo', 'Coroado', 'Taruma', 'Ponta Negra'];

        $stmt = $this->pdo->prepare(
            'INSERT INTO profissionais
                (nome, endereco, telefone, profissao, permite_editar_guias, salario_fixo, comissao_percentual, imposto_fixo, imposto_percentual, mensagem_padrao_whatsapp, permite_secretaria_liberar_agenda)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        for ($i = 1; $i <= $count; $i++) {
            $name = $this->composeName($i, $firstNames, $lastNamesA, $lastNamesB);
            $profession = $professions[($i - 1) % count($professions)];
            $address = 'Rua ' . $districts[($i - 1) % count($districts)] . ', ' . (100 + $i) . ' - Sala ' . (($i % 9) + 1);
            $phone = $this->formatPhone(920000000 + $i * 37);
            $canEditGuides = $i % 3 !== 0 ? 1 : 0;
            $fixedSalary = (float) mt_rand(2800, 6800);
            $commission = (float) mt_rand(4, 14);
            $fixedTax = (float) mt_rand(0, 260);
            $taxPercent = (float) mt_rand(0, 8);
            $message = 'Ola, aqui e ' . $name . ' da equipe FisioLife. Qualquer ajuste de agenda ou retorno, pode me chamar.';
            $allowSecretary = $i % 5 !== 0 ? 1 : 0;

            $stmt->execute([
                $name,
                $address,
                $phone,
                $profession,
                $canEditGuides,
                number_format($fixedSalary, 2, '.', ''),
                number_format($commission, 2, '.', ''),
                number_format($fixedTax, 2, '.', ''),
                number_format($taxPercent, 2, '.', ''),
                $message,
                $allowSecretary,
            ]);

            $this->newProfessionalIds[] = (int) $this->pdo->lastInsertId();
            $this->inserted['profissionais']++;
        }
    }

    private function refreshProfessionalsAndServices(): void
    {
        $this->professionals = $this->fetchAll('SELECT id, nome, profissao, telefone FROM profissionais ORDER BY id');
        $this->services = $this->fetchAll('SELECT id, nome, tempo_minutos FROM servicos WHERE ativo = 1 ORDER BY id');
    }

    private function ensureProfessionalServices(): void
    {
        $this->write('Vinculando servicos aos profissionais...');

        $existing = [];
        foreach ($this->fetchAll('SELECT profissional_id, servico_id FROM profissional_servico') as $row) {
            $professionalId = (int) $row['profissional_id'];
            $serviceId = (int) $row['servico_id'];
            $existing[$professionalId][$serviceId] = true;
        }

        $insert = $this->pdo->prepare('INSERT IGNORE INTO profissional_servico (profissional_id, servico_id, tempo_minutos) VALUES (?, ?, ?)');
        $serviceIds = array_map(static fn (array $service): int => (int) $service['id'], $this->services);

        foreach ($this->professionals as $professional) {
            $professionalId = (int) $professional['id'];
            $currentIds = array_keys($existing[$professionalId] ?? []);
            $target = max(4, min(8, count($currentIds) + mt_rand(2, 4)));
            $suggested = $this->suggestServiceIds((string) $professional['profissao']);
            $chosen = array_values(array_unique(array_merge($currentIds, $suggested)));

            while (count($chosen) < $target) {
                $candidate = $serviceIds[array_rand($serviceIds)];
                if (!in_array($candidate, $chosen, true)) {
                    $chosen[] = $candidate;
                }
            }

            foreach ($chosen as $serviceId) {
                if (isset($existing[$professionalId][$serviceId])) {
                    continue;
                }

                $baseDuration = $this->serviceDuration($serviceId);
                $customDuration = max(20, $baseDuration + (mt_rand(-1, 2) * 5));
                $insert->execute([$professionalId, $serviceId, $customDuration]);
                $existing[$professionalId][$serviceId] = true;
                $this->inserted['profissional_servico']++;
            }
        }

        $this->serviceMapByProfessional = [];
        foreach ($this->fetchAll('SELECT ps.profissional_id, s.id AS servico_id, s.nome, COALESCE(ps.tempo_minutos, s.tempo_minutos) AS tempo_minutos FROM profissional_servico ps INNER JOIN servicos s ON s.id = ps.servico_id ORDER BY ps.profissional_id, s.id') as $row) {
            $this->serviceMapByProfessional[(int) $row['profissional_id']][] = [
                'id' => (int) $row['servico_id'],
                'nome' => (string) $row['nome'],
                'tempo_minutos' => (int) $row['tempo_minutos'],
            ];
        }
    }

    private function createUsers(int $count): void
    {
        $this->write('Criando usuarios...');

        $passwordHash = password_hash('123456', PASSWORD_DEFAULT);
        $linkedProfessionals = array_slice($this->newProfessionalIds, 0, 6);
        $profiles = [
            ['perfil' => 'profissional', 'login' => 'stress.prof.01', 'nome' => 'Agenda Profissional 01', 'profissional_id' => $linkedProfessionals[0] ?? null],
            ['perfil' => 'profissional', 'login' => 'stress.prof.02', 'nome' => 'Agenda Profissional 02', 'profissional_id' => $linkedProfessionals[1] ?? null],
            ['perfil' => 'profissional', 'login' => 'stress.prof.03', 'nome' => 'Agenda Profissional 03', 'profissional_id' => $linkedProfessionals[2] ?? null],
            ['perfil' => 'profissional', 'login' => 'stress.prof.04', 'nome' => 'Agenda Profissional 04', 'profissional_id' => $linkedProfessionals[3] ?? null],
            ['perfil' => 'profissional', 'login' => 'stress.prof.05', 'nome' => 'Agenda Profissional 05', 'profissional_id' => $linkedProfessionals[4] ?? null],
            ['perfil' => 'profissional', 'login' => 'stress.prof.06', 'nome' => 'Agenda Profissional 06', 'profissional_id' => $linkedProfessionals[5] ?? null],
            ['perfil' => 'secretaria', 'login' => 'stress.secretaria.01', 'nome' => 'Secretaria Performance 01', 'profissional_id' => null],
            ['perfil' => 'secretaria', 'login' => 'stress.secretaria.02', 'nome' => 'Secretaria Performance 02', 'profissional_id' => null],
            ['perfil' => 'administrativo', 'login' => 'stress.adm.01', 'nome' => 'Financeiro Performance', 'profissional_id' => null],
            ['perfil' => 'desenvolvedor', 'login' => 'stress.dev.01', 'nome' => 'Desenvolvimento Performance', 'profissional_id' => null],
        ];

        $stmt = $this->pdo->prepare('INSERT INTO usuarios (login, senha_hash, perfil, profissional_id, nome_exibicao, ativo) VALUES (?, ?, ?, ?, ?, 1)');

        foreach (array_slice($profiles, 0, $count) as $user) {
            $stmt->bindValue(1, $user['login']);
            $stmt->bindValue(2, $passwordHash);
            $stmt->bindValue(3, $user['perfil']);
            if ($user['profissional_id'] === null) {
                $stmt->bindValue(4, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(4, (int) $user['profissional_id'], PDO::PARAM_INT);
            }
            $stmt->bindValue(5, $user['nome']);
            $stmt->execute();
            $this->inserted['usuarios']++;
        }
    }

    private function createLots(int $count): void
    {
        $this->write('Criando lotes...');

        $convenioNames = array_map(static fn (array $plan): string => (string) $plan['nome'], $this->convenioPlans);
        $stmt = $this->pdo->prepare('INSERT INTO lotes (convenio, data, status, numero_lote) VALUES (?, ?, ?, ?)');

        for ($i = 1; $i <= $count; $i++) {
            $date = $this->randomDate($this->startDate->modify('+35 days'), $this->today->modify('-8 days'));
            $number = 'PERF-' . $this->today->format('ym') . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $convenio = $convenioNames[($i - 1) % count($convenioNames)];
            $status = $i % 3 === 0 ? 'pago' : 'faturado';

            $stmt->execute([
                $convenio,
                $date->format('Y-m-d'),
                $status,
                $number,
            ]);

            $lotId = (int) $this->pdo->lastInsertId();
            $this->lots[] = [
                'id' => $lotId,
                'convenio' => $convenio,
                'data' => $date,
                'status' => $status,
                'numero_lote' => $number,
            ];
            $this->inserted['lotes']++;
        }
    }

    private function createPatientsAndGuides(int $count): void
    {
        $this->write('Criando pacientes e guias...');

        $typeSequence = array_merge(
            array_fill(0, 550, 'convenio_lote'),
            array_fill(0, 225, 'particular'),
            array_fill(0, 225, 'convenio_direto')
        );
        shuffle($typeSequence);

        $dayPreferences = ['Segunda-feira', 'Terca-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sabado'];
        $hourPreferences = ['07:30', '08:00', '09:00', '10:30', '13:30', '14:00', '15:30', '16:00', '17:00'];
        $firstNames = ['Amanda', 'Bianca', 'Caio', 'Daniela', 'Eduardo', 'Fernanda', 'Gabriel', 'Heloisa', 'Igor', 'Juliana', 'Karina', 'Leonardo', 'Melissa', 'Natanael', 'Olivia', 'Paulo', 'Quiteria', 'Rafael', 'Sabrina', 'Thiago', 'Ursula', 'Vicente', 'Wesley', 'Ximena', 'Yuri', 'Zelia', 'Aline', 'Bruno', 'Cristiane', 'Diego', 'Elisa', 'Fabio', 'Giovana', 'Henrique', 'Isabela', 'Jorge', 'Larissa', 'Marcelo', 'Natalia', 'Otavio'];
        $lastNamesA = ['Mendes', 'Pinheiro', 'Farias', 'Torres', 'Peixoto', 'Santos', 'Aguiar', 'Pinho', 'Mota', 'Assis', 'Leite', 'Campos', 'Batista', 'Miranda', 'Prado', 'Guerra', 'Ramos', 'Porto', 'Menezes', 'Valente'];
        $lastNamesB = ['Silva', 'Souza', 'Oliveira', 'Costa', 'Pereira', 'Rodrigues', 'Alves', 'Ferreira', 'Teixeira', 'Barbosa', 'Dias', 'Azevedo', 'Rocha', 'Moura', 'Lopes', 'Cavalcante', 'Nunes', 'Rezende', 'Figueiredo', 'Moreira'];
        $professionals = $this->professionals;
        shuffle($professionals);
        $patientStmt = $this->pdo->prepare('INSERT INTO pacientes (nome, telefone, convenio, valor_sessao, prontuario, dia_preferencia, horario_preferencia) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $guideStmt = $this->pdo->prepare(
            'INSERT INTO guias
                (codigo, paciente_id, total_sessoes, data_envio, previsao_pagamento, valor_guia, recebido, data, plano_id, sessoes_usadas, profissional_id, servico_id, tipo_guia, lote_id, convenio, conta_receber_gerada, conta_receber_id, observacoes)
             VALUES
                (?, ?, ?, ?, ?, ?, 0, ?, ?, 0, ?, ?, ?, ?, ?, 0, NULL, ?)'
        );

        $batchCounter = 0;

        for ($i = 1; $i <= $count; $i++) {
            $type = $typeSequence[$i - 1];
            $professional = $professionals[($i - 1) % count($professionals)];
            $servicePool = $this->serviceMapByProfessional[(int) $professional['id']] ?? [];
            $guideService = $servicePool !== [] ? $servicePool[array_rand($servicePool)] : ['id' => null, 'nome' => ''];
            $plan = $this->pickPlanForType($type);
            $name = $this->composeName($i + 100, $firstNames, $lastNamesA, $lastNamesB);
            $phone = $this->formatPhone(930000000 + $i * 29);
            $sessions = $this->sessionsForGuideType($type);
            $perSessionValue = $this->sessionValueForService($guideService, $type);
            $totalValue = round($perSessionValue * $sessions, 2);
            $guideDate = $this->randomDate($this->startDate, $this->today->modify('-5 days'));
            $sendDate = $guideDate->modify('+' . mt_rand(2, 24) . ' days');
            $paymentForecast = $sendDate->modify('+' . mt_rand(8, 28) . ' days');
            $convenio = $type === 'particular' ? 'Particular' : (string) $plan['nome'];
            $loteId = null;

            if ($type === 'convenio_lote') {
                $lot = $this->lots[$batchCounter % count($this->lots)];
                $loteId = (int) $lot['id'];
                $batchCounter++;
            }

            $patientStmt->execute([
                $name,
                $phone,
                $convenio,
                number_format($perSessionValue, 2, '.', ''),
                'seed://' . self::SEED_KEY . '/paciente-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                $dayPreferences[array_rand($dayPreferences)],
                $hourPreferences[array_rand($hourPreferences)],
            ]);

            $patientId = (int) $this->pdo->lastInsertId();
            $guideCode = 'PF-' . $this->today->format('ymd') . '-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT);
            $guideNote = 'Carga de performance ' . self::SEED_KEY . ' com paciente recorrente e historico para relatorios.';

            $guideStmt->bindValue(1, $guideCode);
            $guideStmt->bindValue(2, $patientId, PDO::PARAM_INT);
            $guideStmt->bindValue(3, $sessions, PDO::PARAM_INT);
            $guideStmt->bindValue(4, $sendDate->format('Y-m-d'));
            $guideStmt->bindValue(5, $paymentForecast->format('Y-m-d'));
            $guideStmt->bindValue(6, number_format($totalValue, 2, '.', ''));
            $guideStmt->bindValue(7, $guideDate->format('Y-m-d'));
            $guideStmt->bindValue(8, (int) $plan['id'], PDO::PARAM_INT);
            $guideStmt->bindValue(9, (int) $professional['id'], PDO::PARAM_INT);
            if (empty($guideService['id'])) {
                $guideStmt->bindValue(10, null, PDO::PARAM_NULL);
            } else {
                $guideStmt->bindValue(10, (int) $guideService['id'], PDO::PARAM_INT);
            }
            $guideStmt->bindValue(11, $type);
            if ($loteId === null) {
                $guideStmt->bindValue(12, null, PDO::PARAM_NULL);
            } else {
                $guideStmt->bindValue(12, $loteId, PDO::PARAM_INT);
            }
            $guideStmt->bindValue(13, $convenio);
            $guideStmt->bindValue(14, $guideNote);
            $guideStmt->execute();

            $guideId = (int) $this->pdo->lastInsertId();

            $this->patients[] = [
                'id' => $patientId,
                'nome' => $name,
                'telefone' => $phone,
                'profissional_id' => (int) $professional['id'],
            ];
            $this->patientsByProfessional[(int) $professional['id']][] = $patientId;
            $this->patientGuideMap[$patientId] = $guideId;
            $this->guideStates[$guideId] = [
                'id' => $guideId,
                'patient_id' => $patientId,
                'patient_name' => $name,
                'patient_phone' => $phone,
                'professional_id' => (int) $professional['id'],
                'service_id' => (int) ($guideService['id'] ?? 0),
                'type' => $type,
                'plan_id' => (int) $plan['id'],
                'plan_name' => (string) $plan['nome'],
                'total_sessions' => $sessions,
                'used_sessions' => 0,
                'value_total' => $totalValue,
                'value_per_session' => round($totalValue / $sessions, 2),
                'guide_date' => $guideDate,
                'lote_id' => $loteId,
                'payment_status' => 'aberto',
                'payment_ratio' => 0.0,
            ];

            if ($loteId !== null) {
                $this->guideIdsByLot[$loteId][] = $guideId;
            }

            $this->inserted['pacientes']++;
            $this->inserted['guias']++;
        }
    }

    private function createReceivables(): void
    {
        $this->write('Criando contas a receber vinculadas a guias e lotes...');

        $stmt = $this->pdo->prepare(
            'INSERT INTO contas_receber
                (descricao, valor, vencimento, recebimento, status, plano_conta_id, profissional_id, origem_tipo, origem_id, competencia, valor_recebido, juros, multa, desconto, forma_pagamento, conta_financeira_id, centro_custo_id, numero_documento, fonte_pagadora, paciente_id, observacoes)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $guideUpdate = $this->pdo->prepare('UPDATE guias SET conta_receber_gerada = 1, conta_receber_id = ?, recebido = ? WHERE id = ?');
        $guideReceivedUpdate = $this->pdo->prepare('UPDATE guias SET recebido = ? WHERE id = ?');
        $lotStatusUpdate = $this->pdo->prepare('UPDATE lotes SET status = ? WHERE id = ?');

        foreach ($this->guideStates as $guideId => &$guide) {
            if ($guide['type'] === 'convenio_lote') {
                continue;
            }

            $status = $this->settlementStatusForDate($guide['guide_date']);
            $interest = (float) mt_rand(0, 35);
            $fine = (float) mt_rand(0, 18);
            $discount = $status === 'pago' ? (float) mt_rand(0, 22) : (float) mt_rand(0, 10);
            $receivedAmount = $this->settledAmount($guide['value_total'], $interest, $fine, $discount, $status);
            $dueDate = $guide['guide_date']->modify('+' . mt_rand(7, 28) . ' days');
            $receiveDate = in_array($status, ['pago', 'parcial'], true)
                ? $dueDate->modify('+' . mt_rand(0, 18) . ' days')->format('Y-m-d')
                : null;
            $accountId = $this->revenueAccountIdForGuideType($guide['type']);
            $centerId = $this->costCenterIdForGuideType($guide['type']);
            $financialAccountId = in_array($status, ['pago', 'parcial'], true) ? $this->randomFinancialAccountId() : null;
            $paymentMethod = in_array($status, ['pago', 'parcial'], true) ? $this->randomPaymentMethod() : null;
            $description = 'Guia ' . $this->guideCode($guideId) . ' - ' . $guide['patient_name'];

            $stmt->execute([
                $description,
                number_format($guide['value_total'], 2, '.', ''),
                $dueDate->format('Y-m-d'),
                $receiveDate,
                $status,
                $accountId,
                $guide['professional_id'],
                'guia',
                $guideId,
                $dueDate->format('Y-m-01'),
                $receivedAmount > 0 ? number_format($receivedAmount, 2, '.', '') : null,
                number_format($interest, 2, '.', ''),
                number_format($fine, 2, '.', ''),
                number_format($discount, 2, '.', ''),
                $paymentMethod,
                $financialAccountId,
                $centerId,
                'CR-G-' . str_pad((string) $guideId, 6, '0', STR_PAD_LEFT),
                $guide['type'] === 'particular' ? $guide['patient_name'] : $guide['plan_name'],
                $guide['patient_id'],
                'Receita automatizada da carga de performance ' . self::SEED_KEY . '.',
            ]);

            $receivableId = (int) $this->pdo->lastInsertId();
            $receivedForGuide = min($guide['value_total'], $receivedAmount);
            $ratio = $guide['value_total'] > 0 ? min(1.0, $receivedForGuide / $guide['value_total']) : 0.0;

            $guideUpdate->execute([
                $receivableId,
                number_format($receivedForGuide, 2, '.', ''),
                $guideId,
            ]);

            $guide['payment_status'] = $status;
            $guide['payment_ratio'] = $ratio;
            $this->inserted['contas_receber']++;
        }
        unset($guide);

        foreach ($this->lots as $lot) {
            $lotId = (int) $lot['id'];
            $guideIds = $this->guideIdsByLot[$lotId] ?? [];
            if ($guideIds === []) {
                continue;
            }

            $lotTotal = 0.0;
            foreach ($guideIds as $guideId) {
                $lotTotal += (float) $this->guideStates[$guideId]['value_total'];
            }

            $status = $lot['status'] === 'pago' ? 'pago' : ($lotId % 4 === 0 ? 'parcial' : 'aberto');
            $interest = (float) mt_rand(5, 65);
            $fine = (float) mt_rand(0, 25);
            $discount = $status === 'pago' ? (float) mt_rand(0, 35) : (float) mt_rand(0, 12);
            $receivedAmount = $this->settledAmount($lotTotal, $interest, $fine, $discount, $status);
            $dueDate = $lot['data']->modify('+' . mt_rand(20, 45) . ' days');
            $receiveDate = in_array($status, ['pago', 'parcial'], true)
                ? $dueDate->modify('+' . mt_rand(2, 24) . ' days')->format('Y-m-d')
                : null;

            $stmt->execute([
                'Lote ' . $lot['numero_lote'] . ' - ' . $lot['convenio'],
                number_format($lotTotal, 2, '.', ''),
                $dueDate->format('Y-m-d'),
                $receiveDate,
                $status,
                $this->revenueAccountIdForGuideType('convenio_lote'),
                null,
                'lote',
                $lotId,
                $dueDate->format('Y-m-01'),
                $receivedAmount > 0 ? number_format($receivedAmount, 2, '.', '') : null,
                number_format($interest, 2, '.', ''),
                number_format($fine, 2, '.', ''),
                number_format($discount, 2, '.', ''),
                in_array($status, ['pago', 'parcial'], true) ? $this->randomPaymentMethod() : null,
                in_array($status, ['pago', 'parcial'], true) ? $this->randomFinancialAccountId() : null,
                $this->costCenterIdForGuideType('convenio_lote'),
                'CR-L-' . str_pad((string) $lotId, 5, '0', STR_PAD_LEFT),
                $lot['convenio'],
                null,
                'Faturamento de lote criado na carga de performance ' . self::SEED_KEY . '.',
            ]);

            $ratio = $lotTotal > 0 ? min(1.0, $receivedAmount / $lotTotal) : 0.0;
            $lotStatus = $status === 'pago' ? 'pago' : 'faturado';
            $lotStatusUpdate->execute([$lotStatus, $lotId]);

            foreach ($guideIds as $guideId) {
                $guideReceived = round((float) $this->guideStates[$guideId]['value_total'] * $ratio, 2);
                $guideReceivedUpdate->execute([
                    number_format($guideReceived, 2, '.', ''),
                    $guideId,
                ]);
                $this->guideStates[$guideId]['payment_status'] = $status;
                $this->guideStates[$guideId]['payment_ratio'] = $ratio;
            }

            $this->inserted['contas_receber']++;
        }
    }

    private function createPayables(int $count): void
    {
        $this->write('Criando contas a pagar...');

        $suppliers = ['ClinLab Suprimentos', 'Studio Movimento', 'Energia Norte', 'Agua Clara', 'Digital Wave Midia', 'Pleno Sistemas', 'Condominio Medical Tower', 'Ativa Limpeza', 'Max Equipamentos', 'Farmacia Reab'];
        $descriptors = ['Repasse profissional', 'Compra de material', 'Assinatura de sistema', 'Campanha de captacao', 'Conta operacional', 'Manutencao de equipamentos', 'Servico terceirizado', 'Deslocamento domiciliar', 'Treinamento de equipe', 'Compra de escritorio'];
        $stmt = $this->pdo->prepare(
            'INSERT INTO contas_pagar
                (descricao, valor, vencimento, pagamento, status, plano_conta_id, origem_tipo, origem_id, competencia, valor_pago, juros, multa, desconto, forma_pagamento, conta_financeira_id, centro_custo_id, numero_documento, favorecido, observacoes)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        for ($i = 1; $i <= $count; $i++) {
            $expense = $this->randomItem($this->expenseAccounts);
            $supplier = $suppliers[($i - 1) % count($suppliers)];
            $descriptor = $descriptors[($i - 1) % count($descriptors)];
            $competence = $this->randomDate($this->startDate->modify('-1 month'), $this->today);
            $dueDate = $competence->modify('+' . mt_rand(3, 25) . ' days');
            $status = $this->payableStatusForDate($dueDate);
            $baseValue = (float) mt_rand(120, 4200);
            $interest = $status === 'pago' ? (float) mt_rand(0, 20) : (float) mt_rand(0, 8);
            $fine = (float) mt_rand(0, 16);
            $discount = $status === 'pago' ? (float) mt_rand(0, 24) : (float) mt_rand(0, 10);
            $paidAmount = $this->settledAmount($baseValue, $interest, $fine, $discount, $status);
            $paymentDate = in_array($status, ['pago', 'parcial'], true)
                ? $dueDate->modify('+' . mt_rand(0, 15) . ' days')->format('Y-m-d')
                : null;
            $center = $this->randomItem($this->costCenters);
            $financialAccountId = in_array($status, ['pago', 'parcial'], true) ? $this->randomFinancialAccountId() : null;
            $paymentMethod = in_array($status, ['pago', 'parcial'], true) ? $this->randomPaymentMethod() : null;

            $stmt->execute([
                $descriptor . ' - ' . $supplier,
                number_format($baseValue, 2, '.', ''),
                $dueDate->format('Y-m-d'),
                $paymentDate,
                $status,
                (int) $expense['id'],
                'seed_despesa',
                null,
                $competence->format('Y-m-01'),
                $paidAmount > 0 ? number_format($paidAmount, 2, '.', '') : null,
                number_format($interest, 2, '.', ''),
                number_format($fine, 2, '.', ''),
                number_format($discount, 2, '.', ''),
                $paymentMethod,
                $financialAccountId,
                (int) $center['id'],
                'CP-' . $this->today->format('ymd') . '-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                $supplier,
                'Despesa criada para testar filtros, aging e consolidacoes financeiras.',
            ]);

            $this->inserted['contas_pagar']++;
        }
    }

    private function createScheduleHistory(): void
    {
        $this->write('Criando historico de agenda e atendimentos...');

        $existingAvailability = [];
        foreach ($this->fetchAll('SELECT profissional_id, data_disponivel FROM agenda_disponibilidade WHERE data_disponivel BETWEEN ? AND ?', [$this->startDate->format('Y-m-d'), $this->today->format('Y-m-d')]) as $row) {
            $existingAvailability[(int) $row['profissional_id'] . '|' . (string) $row['data_disponivel']] = true;
        }

        $existingAppointments = [];
        foreach ($this->fetchAll('SELECT profissional_id, data_agendamento, hora_inicio FROM agenda WHERE data_agendamento BETWEEN ? AND ?', [$this->startDate->format('Y-m-d'), $this->today->format('Y-m-d')]) as $row) {
            $existingAppointments[(int) $row['profissional_id'] . '|' . (string) $row['data_agendamento'] . '|' . substr((string) $row['hora_inicio'], 0, 5)] = true;
        }

        $availabilityStmt = $this->pdo->prepare('INSERT INTO agenda_disponibilidade (profissional_id, data_disponivel, hora_inicio, hora_fim, observacoes, ativo) VALUES (?, ?, ?, ?, ?, 1)');
        $appointmentStmt = $this->pdo->prepare(
            'INSERT INTO agenda
                (data_agendamento, hora_inicio, hora_fim, profissional_id, servico_id, cliente_id, cliente_nome, cliente_telefone, status, observacoes)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $attendanceStmt = $this->pdo->prepare(
            'INSERT INTO atendimentos
                (paciente_id, data, tipo, status, valor, pago, guia_id, status_atendimento)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($this->businessDays($this->startDate, $this->today) as $date) {
            $weekday = (int) $date->format('N');
            $slots = in_array($weekday, [3, 5], true)
                ? ['08:30', '10:00', '14:30']
                : ['08:00', '09:30', '14:00', '15:30'];

            foreach ($this->professionals as $professional) {
                $professionalId = (int) $professional['id'];
                $availabilityKey = $professionalId . '|' . $date->format('Y-m-d');

                if (!isset($existingAvailability[$availabilityKey])) {
                    $availabilityStmt->execute([$professionalId, $date->format('Y-m-d'), '08:00', '12:00', 'Carga historica de performance - turno manha']);
                    $availabilityStmt->execute([$professionalId, $date->format('Y-m-d'), '13:30', '18:00', 'Carga historica de performance - turno tarde']);
                    $existingAvailability[$availabilityKey] = true;
                    $this->inserted['agenda_disponibilidade'] += 2;
                }

                $patientPool = $this->patientsByProfessional[$professionalId] ?? [];
                $servicePool = $this->serviceMapByProfessional[$professionalId] ?? [];

                if ($patientPool === [] || $servicePool === []) {
                    continue;
                }

                foreach ($slots as $slotIndex => $startTime) {
                    $appointmentKey = $professionalId . '|' . $date->format('Y-m-d') . '|' . $startTime;
                    if (isset($existingAppointments[$appointmentKey])) {
                        continue;
                    }

                    $status = $this->appointmentStatusForDate($date);
                    $patientId = $this->nextPatientForProfessional($professionalId, $status === 'realizado');

                    if ($patientId === null) {
                        $status = 'cancelado';
                        $patientId = $this->nextPatientForProfessional($professionalId, false);
                    }

                    if ($patientId === null) {
                        continue;
                    }

                    $guideId = $this->patientGuideMap[$patientId];
                    $guide = &$this->guideStates[$guideId];
                    $service = $servicePool[$slotIndex % count($servicePool)];
                    foreach ($servicePool as $candidateService) {
                        if ((int) $candidateService['id'] === (int) ($guide['service_id'] ?? 0)) {
                            $service = $candidateService;
                            break;
                        }
                    }

                    if ($status === 'realizado' && $guide['used_sessions'] >= $guide['total_sessions']) {
                        $status = mt_rand(0, 1) === 0 ? 'cancelado' : 'confirmado';
                    }

                    $endTime = $this->addMinutes($startTime, (int) $service['tempo_minutos']);
                    $note = $this->appointmentNote($status, (string) $service['nome'], (string) $guide['plan_name']);

                    $appointmentStmt->execute([
                        $date->format('Y-m-d'),
                        $startTime . ':00',
                        $endTime . ':00',
                        $professionalId,
                        (int) $service['id'],
                        $patientId,
                        (string) $guide['patient_name'],
                        (string) $guide['patient_phone'],
                        $status,
                        $note,
                    ]);

                    $existingAppointments[$appointmentKey] = true;
                    $this->inserted['agenda']++;

                    if ($status !== 'realizado') {
                        unset($guide);
                        continue;
                    }

                    $guide['used_sessions']++;
                    $attendanceStatus = $guide['type'] === 'convenio_lote' && mt_rand(1, 100) <= 7 ? 'Glosado' : 'Realizado';
                    $attendanceStmt->execute([
                        $patientId,
                        $date->format('Y-m-d'),
                        (string) $service['nome'],
                        'concluido',
                        number_format((float) $guide['value_per_session'], 2, '.', ''),
                        $this->attendancePaymentLabel((string) $guide['payment_status']),
                        $guideId,
                        $attendanceStatus,
                    ]);

                    $this->inserted['atendimentos']++;
                    unset($guide);
                }
            }
        }
    }

    private function syncGuideUsage(): void
    {
        $this->write('Atualizando sessoes usadas das guias...');
        $stmt = $this->pdo->prepare('UPDATE guias SET sessoes_usadas = ? WHERE id = ?');

        foreach ($this->guideStates as $guide) {
            $used = min((int) $guide['used_sessions'], (int) $guide['total_sessions']);
            $stmt->execute([$used, (int) $guide['id']]);
        }
    }

    private function printSummary(): void
    {
        $this->write('');
        $this->write('Carga concluida com sucesso.');
        foreach ($this->inserted as $table => $count) {
            $this->write(str_pad($table, 24, ' ') . ': ' . $count);
        }

        $totals = $this->fetchAll(
            "SELECT 'profissionais' AS tabela, COUNT(*) AS total FROM profissionais
             UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
             UNION ALL SELECT 'servicos', COUNT(*) FROM servicos
             UNION ALL SELECT 'profissional_servico', COUNT(*) FROM profissional_servico
             UNION ALL SELECT 'pacientes', COUNT(*) FROM pacientes
             UNION ALL SELECT 'guias', COUNT(*) FROM guias
             UNION ALL SELECT 'lotes', COUNT(*) FROM lotes
             UNION ALL SELECT 'contas_receber', COUNT(*) FROM contas_receber
             UNION ALL SELECT 'contas_pagar', COUNT(*) FROM contas_pagar
             UNION ALL SELECT 'agenda_disponibilidade', COUNT(*) FROM agenda_disponibilidade
             UNION ALL SELECT 'agenda', COUNT(*) FROM agenda
             UNION ALL SELECT 'atendimentos', COUNT(*) FROM atendimentos"
        );

        $this->write('');
        $this->write('Totais atuais das tabelas principais:');
        foreach ($totals as $row) {
            $this->write(str_pad((string) $row['tabela'], 24, ' ') . ': ' . (int) $row['total']);
        }

        $this->write('');
        $this->write('Usuarios criados para teste:');
        $this->write('  stress.prof.01 .. stress.prof.06');
        $this->write('  stress.secretaria.01 / stress.secretaria.02');
        $this->write('  stress.adm.01 / stress.dev.01');
        $this->write('Senha padrao: 123456');
    }

    private function suggestServiceIds(string $profession): array
    {
        $professionLower = strtolower($profession);
        $byName = [];
        foreach ($this->services as $service) {
            $byName[strtolower((string) $service['nome'])] = (int) $service['id'];
        }

        $preferred = [];

        if (str_contains($professionLower, 'pilates') && isset($byName['pilates clinico individual'])) {
            $preferred[] = $byName['pilates clinico individual'];
        }
        if (str_contains($professionLower, 'respiratorio') && isset($byName['fisioterapia respiratoria adulto'])) {
            $preferred[] = $byName['fisioterapia respiratoria adulto'];
        }
        if (str_contains($professionLower, 'neuro') && isset($byName['fisioterapia neurofuncional'])) {
            $preferred[] = $byName['fisioterapia neurofuncional'];
        }
        if (str_contains($professionLower, 'fono') && isset($byName['fonoterapia motora'])) {
            $preferred[] = $byName['fonoterapia motora'];
        }
        if (str_contains($professionLower, 'esportivo') && isset($byName['reabilitacao esportiva'])) {
            $preferred[] = $byName['reabilitacao esportiva'];
        }
        if (str_contains($professionLower, 'psicolog') && isset($byName['sessao psicologia'])) {
            $preferred[] = $byName['sessao psicologia'];
        }
        if (str_contains($professionLower, 'cardio') && isset($byName['treino cardiorrespiratorio'])) {
            $preferred[] = $byName['treino cardiorrespiratorio'];
        }

        return $preferred;
    }

    private function pickPlanForType(string $type): array
    {
        if ($type === 'particular') {
            return $this->randomItem($this->particularPlans);
        }

        return $this->randomItem($this->convenioPlans);
    }

    private function sessionsForGuideType(string $type): int
    {
        return match ($type) {
            'particular' => mt_rand(12, 20),
            'convenio_direto' => mt_rand(16, 28),
            default => mt_rand(20, 36),
        };
    }

    private function sessionValueForService(array $service, string $type): float
    {
        $base = $type === 'particular' ? 150.0 : 60.0;
        $serviceName = (string) ($service['nome'] ?? '');

        if (stripos($serviceName, 'home') !== false) {
            $base += 55.0;
        } elseif (stripos($serviceName, 'rpg') !== false || stripos($serviceName, 'pilates') !== false) {
            $base += 25.0;
        } elseif (stripos($serviceName, 'avaliacao') !== false || stripos($serviceName, 'consulta') !== false) {
            $base += 35.0;
        }

        $factor = match ($type) {
            'particular' => mt_rand(95, 115) / 100,
            'convenio_direto' => mt_rand(98, 122) / 100,
            default => mt_rand(100, 135) / 100,
        };

        return round($base * $factor, 2);
    }

    private function settlementStatusForDate(DateTimeImmutable $reference): string
    {
        $days = (int) $reference->diff($this->today)->format('%a');
        $roll = mt_rand(1, 100);

        if ($days >= 90) {
            return $roll <= 58 ? 'pago' : ($roll <= 78 ? 'parcial' : ($roll <= 92 ? 'aberto' : 'cancelado'));
        }

        if ($days >= 45) {
            return $roll <= 42 ? 'pago' : ($roll <= 67 ? 'parcial' : ($roll <= 90 ? 'aberto' : 'cancelado'));
        }

        return $roll <= 22 ? 'pago' : ($roll <= 44 ? 'parcial' : ($roll <= 90 ? 'aberto' : 'cancelado'));
    }

    private function payableStatusForDate(DateTimeImmutable $reference): string
    {
        $days = (int) $reference->diff($this->today)->format('%a');
        $roll = mt_rand(1, 100);

        if ($days >= 75) {
            return $roll <= 56 ? 'pago' : ($roll <= 76 ? 'parcial' : ($roll <= 92 ? 'aberto' : 'cancelado'));
        }

        if ($days >= 30) {
            return $roll <= 38 ? 'pago' : ($roll <= 62 ? 'parcial' : ($roll <= 91 ? 'aberto' : 'cancelado'));
        }

        return $roll <= 20 ? 'pago' : ($roll <= 40 ? 'parcial' : ($roll <= 88 ? 'aberto' : 'cancelado'));
    }

    private function appointmentStatusForDate(DateTimeImmutable $reference): string
    {
        $days = (int) $reference->diff($this->today)->format('%a');
        $roll = mt_rand(1, 100);

        if ($days >= 60) {
            return $roll <= 66 ? 'realizado' : ($roll <= 82 ? 'cancelado' : ($roll <= 92 ? 'confirmado' : 'agendado'));
        }

        if ($days >= 15) {
            return $roll <= 54 ? 'realizado' : ($roll <= 74 ? 'cancelado' : ($roll <= 88 ? 'confirmado' : 'agendado'));
        }

        return $roll <= 32 ? 'realizado' : ($roll <= 56 ? 'cancelado' : ($roll <= 78 ? 'confirmado' : 'agendado'));
    }

    private function settledAmount(float $baseValue, float $interest, float $fine, float $discount, string $status): float
    {
        $expected = max(0.0, $baseValue + $interest + $fine - $discount);

        return match ($status) {
            'pago' => round($expected, 2),
            'parcial' => round($expected * (mt_rand(35, 82) / 100), 2),
            default => 0.0,
        };
    }

    private function revenueAccountIdForGuideType(string $type): int
    {
        $matches = array_filter($this->revenueAccounts, static function (array $account) use ($type): bool {
            $name = strtolower((string) $account['nome']);

            return match ($type) {
                'particular' => str_contains($name, 'particular') || str_contains($name, 'sessoes particulares') || str_contains($name, 'guias particulares'),
                'convenio_direto' => str_contains($name, 'convenio') || str_contains($name, 'faturamento') || str_contains($name, 'consultas'),
                default => str_contains($name, 'lote') || str_contains($name, 'convenio'),
            };
        });

        if ($matches === []) {
            return (int) $this->randomItem($this->revenueAccounts)['id'];
        }

        return (int) $this->randomItem(array_values($matches))['id'];
    }

    private function costCenterIdForGuideType(string $type): int
    {
        $matches = array_filter($this->costCenters, static function (array $center) use ($type): bool {
            $name = strtolower((string) $center['nome']);

            return match ($type) {
                'particular' => str_contains($name, 'assistencial') || str_contains($name, 'recepcao'),
                'convenio_direto', 'convenio_lote' => str_contains($name, 'convenios') || str_contains($name, 'assistencial'),
                default => true,
            };
        });

        return (int) $this->randomItem(array_values($matches !== [] ? $matches : $this->costCenters))['id'];
    }

    private function randomFinancialAccountId(): int
    {
        return (int) $this->randomItem($this->financialAccounts)['id'];
    }

    private function randomPaymentMethod(): string
    {
        $methods = ['pix', 'dinheiro', 'cartao_credito', 'cartao_debito', 'boleto', 'transferencia'];
        return $methods[array_rand($methods)];
    }

    private function nextPatientForProfessional(int $professionalId, bool $requiresCapacity): ?int
    {
        $pool = $this->patientsByProfessional[$professionalId] ?? [];
        $total = count($pool);

        if ($total === 0) {
            return null;
        }

        $start = $this->patientRotation[$professionalId] ?? 0;

        for ($offset = 0; $offset < $total; $offset++) {
            $index = ($start + $offset) % $total;
            $patientId = $pool[$index];
            $guideId = $this->patientGuideMap[$patientId];
            $guide = $this->guideStates[$guideId];

            if (!$requiresCapacity || $guide['used_sessions'] < $guide['total_sessions']) {
                $this->patientRotation[$professionalId] = ($index + 1) % $total;
                return $patientId;
            }
        }

        return $requiresCapacity ? null : $pool[$start % $total];
    }

    private function attendancePaymentLabel(string $status): string
    {
        return match ($status) {
            'pago' => 'sim',
            'parcial' => 'parcial',
            default => 'nao',
        };
    }

    private function appointmentNote(string $status, string $serviceName, string $planName): string
    {
        return match ($status) {
            'realizado' => 'Sessao realizada em ' . $serviceName . ' com foco em evolucao assistida pelo plano ' . $planName . '.',
            'cancelado' => 'Paciente solicitou remarcacao ou ajuste de rotina.',
            'confirmado' => 'Paciente confirmou a presenca e aguarda atendimento de ' . $serviceName . '.',
            default => 'Agendamento preventivo para monitorar continuidade do plano ' . $planName . '.',
        };
    }

    private function serviceDuration(int $serviceId): int
    {
        foreach ($this->services as $service) {
            if ((int) $service['id'] === $serviceId) {
                return (int) $service['tempo_minutos'];
            }
        }

        return 45;
    }

    private function guideCode(int $guideId): string
    {
        return 'PF-' . $this->today->format('ymd') . '-' . str_pad((string) ($guideId - min(array_keys($this->guideStates)) + 1), 5, '0', STR_PAD_LEFT);
    }

    private function composeName(int $index, array $firstNames, array $lastNamesA, array $lastNamesB): string
    {
        $first = $firstNames[$index % count($firstNames)];
        $lastA = $lastNamesA[($index * 3) % count($lastNamesA)];
        $lastB = $lastNamesB[($index * 5) % count($lastNamesB)];

        return $first . ' ' . $lastA . ' ' . $lastB;
    }

    private function formatPhone(int $number): string
    {
        $prefix = 90000 + ($number % 10000);
        $suffix = 1000 + (($number * 7) % 9000);
        return '(92) ' . $prefix . '-' . $suffix;
    }

    private function randomDate(DateTimeImmutable $start, DateTimeImmutable $end): DateTimeImmutable
    {
        $min = $start->getTimestamp();
        $max = $end->getTimestamp();
        $value = mt_rand($min, $max);
        return (new DateTimeImmutable('@' . $value))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    }

    private function businessDays(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $days = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $weekday = (int) $cursor->format('N');
            if ($weekday <= 5) {
                $days[] = $cursor;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $days;
    }

    private function addMinutes(string $time, int $minutes): string
    {
        $dateTime = DateTimeImmutable::createFromFormat('H:i', $time);
        if (!$dateTime) {
            return $time;
        }

        return $dateTime->modify('+' . $minutes . ' minutes')->format('H:i');
    }

    private function randomItem(array $items): array
    {
        return $items[array_rand($items)];
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function write(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }
}

$config = app_db_config();
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $config['host'],
    $config['port'],
    $config['database'],
    $config['charset']
);

$pdo = new PDO($dsn, $config['username'], $config['password']);
$seeder = new StressDataSeeder($pdo);
$seeder->run();
