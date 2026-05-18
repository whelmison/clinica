<?php
include 'config/db.php';

$clinicId = app_active_clinic_id();
$currentUser = app_current_user() ?? [];
$canManageGroupAgenda = app_has_any_role(['secretaria', 'administrativo', 'desenvolvedor']);

if (!function_exists('app_group_time_to_minutes')) {
    function app_group_time_to_minutes(string $time): int
    {
        $parts = explode(':', $time);
        return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
    }
}

if (!function_exists('app_group_minutes_to_time')) {
    function app_group_minutes_to_time(int $minutes): string
    {
        return str_pad((string) floor($minutes / 60), 2, '0', STR_PAD_LEFT)
            . ':' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('app_group_service')) {
    function app_group_service(mysqli $conn, int $clinicId, int $professionalId, int $serviceId): ?array
    {
        return app_stmt_one(
            $conn,
            'SELECT s.id, s.nome, COALESCE(ps.tempo_minutos, s.tempo_minutos) AS tempo_minutos,
                    COALESCE(s.tipo_agendamento, ?) AS tipo_agendamento,
                    COALESCE(s.capacidade_agendamento, 1) AS capacidade_agendamento
             FROM profissional_servico ps
             INNER JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = ps.clinica_id
             WHERE ps.clinica_id = ? AND ps.profissional_id = ? AND ps.servico_id = ? AND s.ativo = 1
             LIMIT 1',
            'siii',
            ['individual', $clinicId, $professionalId, $serviceId]
        );
    }
}

if (!function_exists('app_group_has_availability')) {
    function app_group_has_availability(mysqli $conn, int $clinicId, int $professionalId, string $date, string $start, string $end): bool
    {
        $row = app_stmt_one(
            $conn,
            'SELECT COUNT(*) AS total
             FROM agenda_disponibilidade
             WHERE clinica_id = ? AND profissional_id = ? AND data_disponivel = ? AND ativo = 1
               AND hora_inicio <= ? AND hora_fim >= ?',
            'iisss',
            [$clinicId, $professionalId, $date, $start, $end]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('app_group_has_individual_conflict')) {
    function app_group_has_individual_conflict(mysqli $conn, int $clinicId, int $professionalId, string $date, string $start, string $end): bool
    {
        $row = app_stmt_one(
            $conn,
            'SELECT COUNT(*) AS total
             FROM agenda
             WHERE clinica_id = ? AND profissional_id = ? AND data_agendamento = ? AND status <> ?
               AND NOT (hora_fim <= ? OR hora_inicio >= ?)',
            'iissss',
            [$clinicId, $professionalId, $date, 'cancelado', $start, $end]
        );

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('app_group_has_other_group_conflict')) {
    function app_group_has_other_group_conflict(mysqli $conn, int $clinicId, int $professionalId, int $serviceId, string $date, string $start, string $end, ?int $ignoreGroupId = null): bool
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM agenda_grupos
                WHERE clinica_id = ? AND profissional_id = ? AND data_agendamento = ?
                  AND NOT (hora_fim <= ? OR hora_inicio >= ?)
                  AND NOT (servico_id = ? AND hora_inicio = ?)';
        $types = 'iisssis';
        $params = [$clinicId, $professionalId, $date, $start, $end, $serviceId, $start];

        if ($ignoreGroupId !== null && $ignoreGroupId > 0) {
            $sql .= ' AND id <> ?';
            $types .= 'i';
            $params[] = $ignoreGroupId;
        }

        $row = app_stmt_one($conn, $sql, $types, $params);

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('app_group_validate_schedule_date')) {
    function app_group_validate_schedule_date(string $date): ?string
    {
        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return 'Informe uma data valida para agendar.';
        }

        if (date('Y-m-d', $timestamp) < date('Y-m-d')) {
            return 'Nao e permitido agendar paciente em data anterior ao dia atual.';
        }

        return null;
    }
}

if (!function_exists('app_group_patient_schedule_conflict')) {
    function app_group_patient_schedule_conflict(mysqli $conn, int $clinicId, int $patientId, string $date, string $start, string $end, ?int $ignoreMemberId = null): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        $individual = app_stmt_one(
            $conn,
            'SELECT a.id,
                    a.data_agendamento,
                    a.hora_inicio,
                    a.hora_fim,
                    a.status,
                    COALESCE(p.nome, ?) AS profissional_nome,
                    COALESCE(s.nome, ?) AS servico_nome
             FROM agenda a
             LEFT JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
             LEFT JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
             WHERE a.clinica_id = ?
               AND a.cliente_id = ?
               AND a.data_agendamento = ?
               AND a.status <> ?
               AND NOT (a.hora_fim <= ? OR a.hora_inicio >= ?)
             ORDER BY a.hora_inicio
             LIMIT 1',
            'ssiissss',
            ['', '', $clinicId, $patientId, $date, 'cancelado', $start, $end]
        );

        if ($individual) {
            $individual['tipo_agenda'] = 'individual';

            return $individual;
        }

        $sql = 'SELECT gp.id,
                       g.data_agendamento,
                       g.hora_inicio,
                       g.hora_fim,
                       gp.status,
                       COALESCE(p.nome, ?) AS profissional_nome,
                       COALESCE(s.nome, ?) AS servico_nome
                FROM agenda_grupo_pacientes gp
                INNER JOIN agenda_grupos g ON g.id = gp.grupo_id AND g.clinica_id = gp.clinica_id
                LEFT JOIN profissionais p ON p.id = g.profissional_id AND p.clinica_id = g.clinica_id
                LEFT JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
                WHERE gp.clinica_id = ?
                  AND gp.paciente_id = ?
                  AND gp.status <> ?
                  AND g.data_agendamento = ?
                  AND NOT (g.hora_fim <= ? OR g.hora_inicio >= ?)';
        $types = 'ssiissss';
        $params = ['', '', $clinicId, $patientId, 'cancelado', $date, $start, $end];

        if ($ignoreMemberId !== null && $ignoreMemberId > 0) {
            $sql .= ' AND gp.id <> ?';
            $types .= 'i';
            $params[] = $ignoreMemberId;
        }

        $sql .= ' ORDER BY g.hora_inicio LIMIT 1';
        $group = app_stmt_one($conn, $sql, $types, $params);

        if ($group) {
            $group['tipo_agenda'] = 'grupo';

            return $group;
        }

        return null;
    }
}

if (!function_exists('app_group_patient_has_schedule_conflict')) {
    function app_group_patient_has_schedule_conflict(mysqli $conn, int $clinicId, int $patientId, string $date, string $start, string $end, ?int $ignoreMemberId = null): bool
    {
        return app_group_patient_schedule_conflict($conn, $clinicId, $patientId, $date, $start, $end, $ignoreMemberId) !== null;
    }
}

if (!function_exists('app_group_patient_conflict_message')) {
    function app_group_patient_conflict_message(array $conflict): string
    {
        $type = ($conflict['tipo_agenda'] ?? '') === 'grupo' ? 'sessao em grupo' : 'agenda individual';
        $professional = trim((string) ($conflict['profissional_nome'] ?? '')) ?: 'profissional nao informado';
        $service = trim((string) ($conflict['servico_nome'] ?? '')) ?: 'servico nao informado';
        $date = (string) ($conflict['data_agendamento'] ?? '');
        $dateLabel = $date !== '' ? app_date_br($date) : 'data nao informada';
        $start = substr((string) ($conflict['hora_inicio'] ?? ''), 0, 5);
        $end = substr((string) ($conflict['hora_fim'] ?? ''), 0, 5);
        $timeLabel = $start !== '' && $end !== '' ? $start . ' as ' . $end : 'horario nao informado';
        $status = trim((string) ($conflict['status'] ?? '')) ?: 'sem status';

        return 'Este paciente ja possui agendamento neste mesmo horario. Motivo: ja existe ' . $type
            . ' com ' . $professional
            . ', servico ' . $service
            . ', em ' . $dateLabel
            . ' das ' . $timeLabel
            . ' (status: ' . $status . ').';
    }
}

if (!function_exists('app_group_find_or_create')) {
    function app_group_find_or_create(mysqli $conn, int $clinicId, int $professionalId, array $service, string $date, string $start): array
    {
        $duration = max(1, (int) ($service['tempo_minutos'] ?? 0));
        $startSql = strlen($start) === 5 ? $start . ':00' : $start;
        $end = app_group_minutes_to_time(app_group_time_to_minutes($startSql) + $duration) . ':00';
        $capacity = max(1, (int) ($service['capacidade_agendamento'] ?? 1));

        $dateError = app_group_validate_schedule_date($date);

        if ($dateError !== null) {
            return ['ok' => false, 'message' => $dateError];
        }

        $existing = app_stmt_one(
            $conn,
            'SELECT * FROM agenda_grupos
             WHERE clinica_id = ? AND profissional_id = ? AND servico_id = ? AND data_agendamento = ? AND hora_inicio = ?
             LIMIT 1',
            'iiiss',
            [$clinicId, $professionalId, (int) $service['id'], $date, $startSql]
        );

        if ($existing) {
            return ['ok' => true, 'group' => $existing];
        }

        if (!app_group_has_availability($conn, $clinicId, $professionalId, $date, $startSql, $end)) {
            return ['ok' => false, 'message' => 'Este horario ainda nao foi liberado pelo profissional.'];
        }

        if (app_group_has_individual_conflict($conn, $clinicId, $professionalId, $date, $startSql, $end)) {
            return ['ok' => false, 'message' => 'Ja existe agendamento individual ocupando este horario.'];
        }

        if (app_group_has_other_group_conflict($conn, $clinicId, $professionalId, (int) $service['id'], $date, $startSql, $end)) {
            return ['ok' => false, 'message' => 'Ja existe outra agenda em grupo ocupando este horario.'];
        }

        $ok = app_stmt_execute(
            $conn,
            'INSERT INTO agenda_grupos (clinica_id, profissional_id, servico_id, data_agendamento, hora_inicio, hora_fim, capacidade)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iiisssi',
            [$clinicId, $professionalId, (int) $service['id'], $date, $startSql, $end, $capacity]
        );

        if (!$ok) {
            return ['ok' => false, 'message' => 'Nao foi possivel criar o grupo.'];
        }

        $groupId = (int) $conn->insert_id;
        $group = app_stmt_one($conn, 'SELECT * FROM agenda_grupos WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $groupId]);

        return ['ok' => true, 'group' => $group];
    }
}

if (!function_exists('app_group_active_guide')) {
    function app_group_active_guide(mysqli $conn, int $clinicId, int $guideId, int $patientId, int $professionalId, int $ignoreAttendanceId = 0, int $ignoreMemberId = 0, int $serviceId = 0, bool $requireAuthorized = true): ?array
    {
        $authorizationSql = $requireAuthorized ? "\n               AND g.autorizada = 1" : '';
        $guide = app_stmt_one(
            $conn,
            'SELECT g.id, g.codigo, g.paciente_id, g.profissional_id, g.total_sessoes, g.autorizada,
                    COALESCE(pl.nome, ?) AS plano_nome,
                    COALESCE(a.usadas, 0) AS usadas
             FROM guias g
             LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
             LEFT JOIN (
                SELECT guia_id, COUNT(*) AS usadas
                FROM atendimentos
                WHERE clinica_id = ? AND (? <= 0 OR id <> ?)
                GROUP BY guia_id
             ) a ON a.guia_id = g.id
             WHERE g.clinica_id = ?
               AND g.id = ?
               AND g.paciente_id = ?
               AND (g.profissional_id = ? OR g.profissional_id IS NULL)
               AND (? <= 0 OR g.servico_id = ? OR g.servico_id IS NULL)
               ' . $authorizationSql . '
               AND COALESCE(g.status_operacional, ?) NOT IN (?, ?)
             LIMIT 1',
            'siiiiiiiiisss',
            ['', $clinicId, $ignoreAttendanceId, $ignoreAttendanceId, $clinicId, $guideId, $patientId, $professionalId, $serviceId, $serviceId, 'aguardando_autorizacao', 'cancelada', 'finalizada']
        );

        if (!$guide) {
            return null;
        }

        $remaining = (int) ($guide['total_sessoes'] ?? 0) - (int) ($guide['usadas'] ?? 0);

        return $remaining > 0 ? $guide : null;
    }
}

if (!function_exists('app_group_patient_guides')) {
    function app_group_patient_guides(mysqli $conn, int $clinicId, int $patientId, int $professionalId, int $ignoreAttendanceId = 0, int $ignoreMemberId = 0, int $serviceId = 0, bool $requireAuthorized = true): array
    {
        $authorizationSql = $requireAuthorized ? "\n               AND g.autorizada = 1" : '';

        return app_stmt_all(
            $conn,
            'SELECT g.id,
                    g.codigo,
                    g.total_sessoes,
                    g.autorizada,
                    COALESCE(pl.nome, ?) AS plano_nome,
                    COALESCE(a.usadas, 0) + COALESCE(r.reservadas, 0) AS usadas,
                    (g.total_sessoes - COALESCE(a.usadas, 0) - COALESCE(r.reservadas, 0)) AS restantes
             FROM guias g
             LEFT JOIN planos pl ON pl.id = g.plano_id AND pl.clinica_id = g.clinica_id
             LEFT JOIN (
                SELECT guia_id, COUNT(*) AS usadas
                FROM atendimentos
                WHERE clinica_id = ? AND (? <= 0 OR id <> ?)
                GROUP BY guia_id
             ) a ON a.guia_id = g.id
             LEFT JOIN (
                SELECT guia_id, COUNT(*) AS reservadas
                FROM agenda_grupo_pacientes
                WHERE clinica_id = ? AND status <> ? AND guia_id IS NOT NULL AND atendimento_id IS NULL
                  AND (? <= 0 OR id <> ?)
                GROUP BY guia_id
             ) r ON r.guia_id = g.id
             WHERE g.clinica_id = ?
               AND g.paciente_id = ?
               AND (g.profissional_id = ? OR g.profissional_id IS NULL)
               AND (? <= 0 OR g.servico_id = ? OR g.servico_id IS NULL)
               ' . $authorizationSql . '
               AND COALESCE(g.status_operacional, ?) NOT IN (?, ?)
             HAVING restantes > 0
             ORDER BY g.data DESC, g.id DESC',
            'siiiisiiiiiiisss',
        ['', $clinicId, $ignoreAttendanceId, $ignoreAttendanceId, $clinicId, 'cancelado', $ignoreMemberId, $ignoreMemberId, $clinicId, $patientId, $professionalId, $serviceId, $serviceId, 'aguardando_autorizacao', 'cancelada', 'finalizada']
        );
    }
}

if (!function_exists('app_group_authorized_guides')) {
    function app_group_authorized_guides(mysqli $conn, int $clinicId, int $patientId, int $professionalId, int $ignoreAttendanceId = 0, int $ignoreMemberId = 0, int $serviceId = 0): array
    {
        return app_group_patient_guides($conn, $clinicId, $patientId, $professionalId, $ignoreAttendanceId, $ignoreMemberId, $serviceId, true);
    }
}

if (!function_exists('app_group_available_guides')) {
    function app_group_available_guides(mysqli $conn, int $clinicId, int $patientId, int $professionalId, int $ignoreAttendanceId = 0, int $ignoreMemberId = 0, int $serviceId = 0): array
    {
        return app_group_patient_guides($conn, $clinicId, $patientId, $professionalId, $ignoreAttendanceId, $ignoreMemberId, $serviceId, false);
    }
}

if (app_request_query('ajax', '') === 'group_guides') {
    $ajaxPatientId = app_query_int('paciente_id');
    $ajaxProfessionalId = app_query_int('professional_id');
    $ajaxServiceId = app_query_int('service_id');
    $ajaxRequireAuthorized = app_request_query('require_authorized', '') === '1';

    if (!$canManageGroupAgenda) {
        app_json(['guides' => []], 403);
    }

    if ($ajaxPatientId <= 0 || $ajaxProfessionalId <= 0) {
        app_json(['guides' => []]);
    }

    $ajaxGuides = app_group_patient_guides(
        $conn,
        $clinicId,
        $ajaxPatientId,
        $ajaxProfessionalId,
        0,
        0,
        $ajaxServiceId,
        $ajaxRequireAuthorized
    );

    app_json([
        'guides' => array_map(static function (array $guide): array {
            $code = trim((string) ($guide['codigo'] ?? ''));
            $guideId = (int) ($guide['id'] ?? 0);
            $remaining = (int) ($guide['restantes'] ?? 0);
            $labelCode = $code !== '' ? $code : 'GUIA #' . $guideId;
            $status = !empty($guide['autorizada']) ? 'Autorizada' : 'Nao autorizada';

            return [
                'id' => $guideId,
                'codigo' => $labelCode,
                'restantes' => $remaining,
                'autorizada' => !empty($guide['autorizada']),
                'label' => $labelCode . ' - restam ' . $remaining . ' - ' . $status,
            ];
        }, $ajaxGuides),
    ]);
}

if (!function_exists('app_group_create_attendance')) {
    function app_group_create_attendance(mysqli $conn, int $clinicId, array $group, array $member, array $guide): int
    {
        $ok = app_stmt_execute(
            $conn,
            'INSERT INTO atendimentos (clinica_id, paciente_id, data, tipo, pago, guia_id, status_atendimento)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            'iisssis',
            [
                $clinicId,
                (int) $member['paciente_id'],
                (string) $group['data_agendamento'],
                (string) ($guide['plano_nome'] ?? ''),
                'Pendente',
                (int) $guide['id'],
                'Realizado',
            ]
        );

        return $ok ? (int) $conn->insert_id : 0;
    }
}

if (!function_exists('app_group_update_attendance')) {
    function app_group_update_attendance(mysqli $conn, int $clinicId, int $attendanceId, array $group, array $member, array $guide): bool
    {
        return app_stmt_execute(
            $conn,
            'UPDATE atendimentos
             SET paciente_id = ?, data = ?, tipo = ?, pago = ?, guia_id = ?, status_atendimento = ?
             WHERE clinica_id = ? AND id = ?',
            'isssisii',
            [
                (int) $member['paciente_id'],
                (string) $group['data_agendamento'],
                (string) ($guide['plano_nome'] ?? ''),
                'Pendente',
                (int) $guide['id'],
                'Realizado',
                $clinicId,
                $attendanceId,
            ]
        );
    }
}

if (!function_exists('app_group_delete_attendance')) {
    function app_group_delete_attendance(mysqli $conn, int $clinicId, int $attendanceId): void
    {
        if ($attendanceId > 0) {
            app_stmt_execute($conn, 'DELETE FROM atendimentos WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $attendanceId]);
        }
    }
}

if (!function_exists('app_group_whatsapp_link')) {
    function app_group_whatsapp_link(?string $phone, string $message): ?string
    {
        $digits = app_normalize_phone($phone);

        if ($digits === '') {
            return null;
        }

        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }
}

if (!function_exists('app_group_patient_whatsapp_link')) {
    function app_group_patient_whatsapp_link(array $member, string $date, string $time, string $professional, string $service): ?string
    {
        $patient = trim((string) ($member['paciente_nome'] ?? 'Paciente')) ?: 'Paciente';
        $message = "Ola, {$patient}! Confirmando seu atendimento em grupo.\nData: " . app_date_br($date) . "\nHorario: {$time}\nProfissional: {$professional}\nServico: {$service}\nPor favor confirme sua presenca.";

        return app_group_whatsapp_link((string) ($member['paciente_telefone'] ?? ''), $message);
    }
}

if (!function_exists('app_group_reserved_guide_sessions')) {
    function app_group_reserved_guide_sessions(mysqli $conn, int $clinicId, int $guideId, int $ignoreMemberId = 0): int
    {
        $row = app_stmt_one(
            $conn,
            'SELECT COUNT(*) AS total
             FROM agenda_grupo_pacientes
             WHERE clinica_id = ?
               AND guia_id = ?
               AND guia_id IS NOT NULL
               AND atendimento_id IS NULL
               AND status <> ?
               AND (? <= 0 OR id <> ?)',
            'iisii',
            [$clinicId, $guideId, 'cancelado', $ignoreMemberId, $ignoreMemberId]
        );

        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('app_group_patient_schedule_rows')) {
    function app_group_patient_schedule_rows(mysqli $conn, int $clinicId, int $patientId, string $fromDate, int $limit = 20): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $timestamp = strtotime($fromDate);
        $fromDate = $timestamp ? date('Y-m-d', $timestamp) : date('Y-m-d');
        $limit = max(1, min(40, $limit));

        $individualRows = app_stmt_all(
            $conn,
            'SELECT a.id AS item_id,
                    0 AS grupo_id,
                    a.profissional_id,
                    a.servico_id,
                    ? AS tipo_agenda,
                    a.data_agendamento,
                    a.hora_inicio,
                    a.hora_fim,
                    a.status,
                    COALESCE(p.nome, ?) AS profissional_nome,
                    COALESCE(s.nome, ?) AS servico_nome,
                    a.observacoes
             FROM agenda a
             LEFT JOIN profissionais p ON p.id = a.profissional_id AND p.clinica_id = a.clinica_id
             LEFT JOIN servicos s ON s.id = a.servico_id AND s.clinica_id = a.clinica_id
             WHERE a.clinica_id = ?
               AND a.cliente_id = ?
               AND a.status <> ?
               AND a.data_agendamento >= ?
             ORDER BY a.data_agendamento, a.hora_inicio
             LIMIT ' . $limit,
            'sssiiss',
            ['individual', '', '', $clinicId, $patientId, 'cancelado', $fromDate]
        );

        $groupRows = app_stmt_all(
            $conn,
            'SELECT gp.id AS item_id,
                    g.id AS grupo_id,
                    g.profissional_id,
                    g.servico_id,
                    ? AS tipo_agenda,
                    g.data_agendamento,
                    g.hora_inicio,
                    g.hora_fim,
                    gp.status,
                    COALESCE(p.nome, ?) AS profissional_nome,
                    COALESCE(s.nome, ?) AS servico_nome,
                    gp.observacoes
             FROM agenda_grupo_pacientes gp
             INNER JOIN agenda_grupos g ON g.id = gp.grupo_id AND g.clinica_id = gp.clinica_id
             LEFT JOIN profissionais p ON p.id = g.profissional_id AND p.clinica_id = g.clinica_id
             LEFT JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
             WHERE gp.clinica_id = ?
               AND gp.paciente_id = ?
               AND gp.status <> ?
               AND g.data_agendamento >= ?
             ORDER BY g.data_agendamento, g.hora_inicio
             LIMIT ' . $limit,
            'sssiiss',
            ['grupo', '', '', $clinicId, $patientId, 'cancelado', $fromDate]
        );

        $rows = array_merge($individualRows, $groupRows);
        usort(
            $rows,
            static fn (array $left, array $right): int => strcmp(
                (string) ($left['data_agendamento'] ?? '') . ' ' . (string) ($left['hora_inicio'] ?? ''),
                (string) ($right['data_agendamento'] ?? '') . ' ' . (string) ($right['hora_inicio'] ?? '')
            )
        );

        return array_slice($rows, 0, $limit);
    }
}

if (!function_exists('app_group_quick_weekday_labels')) {
    function app_group_quick_weekday_labels(): array
    {
        return [
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sab',
            0 => 'Dom',
        ];
    }
}

if (!function_exists('app_group_quick_weekdays')) {
    function app_group_quick_weekdays(mixed $value): array
    {
        $items = is_array($value) ? $value : (($value === null || $value === '') ? [] : [$value]);
        $days = [];

        foreach ($items as $item) {
            $day = (int) $item;

            if ($day >= 0 && $day <= 6) {
                $days[$day] = $day;
            }
        }

        $order = [1, 2, 3, 4, 5, 6, 0];
        return array_values(array_filter($order, static fn (int $day): bool => array_key_exists($day, $days)));
    }
}

if (!function_exists('app_group_quick_date_value')) {
    function app_group_quick_date_value(?string $date, string $fallback): string
    {
        $timestamp = strtotime((string) $date);

        return $timestamp ? date('Y-m-d', $timestamp) : $fallback;
    }
}

if (!function_exists('app_group_quick_time_value')) {
    function app_group_quick_time_value(?string $time, string $fallback = '08:00'): string
    {
        $parsed = DateTime::createFromFormat('H:i', (string) $time) ?: DateTime::createFromFormat('H:i:s', (string) $time);

        return $parsed ? $parsed->format('H:i') : $fallback;
    }
}

if (!function_exists('app_group_quick_reason_action')) {
    function app_group_quick_reason_action(string $reason): string
    {
        return match ($reason) {
            'Horario nao liberado pelo profissional.' => 'Libere este horario em Liberar horarios ou escolha outro horario/dia ja liberado.',
            'Horario ocupado por agenda individual.' => 'Escolha outro horario/dia ou remaneje a agenda individual.',
            'Horario ocupado por outro grupo.' => 'Escolha outro horario/dia ou abra o grupo correto para esse servico.',
            'Paciente ja tem agenda neste horario.' => 'Escolha outro horario/dia para o paciente ou remaneje o agendamento existente.',
            'Paciente ja esta neste grupo.' => 'Este dia ja foi lancado para o paciente; escolha outra data ou reduza a quantidade.',
            'Grupo lotado.' => 'Escolha outro horario/dia ou aumente a capacidade do grupo, se fizer sentido.',
            default => 'Ajuste a data inicial, os dias da semana, o horario ou a quantidade de sessoes.',
        };
    }
}

if (!function_exists('app_group_quick_preview_error')) {
    function app_group_quick_preview_error(array $rows, array $skipped, int $sessions): string
    {
        $valid = count($rows);
        $missing = max(0, $sessions - $valid);
        $reasonCounts = [];

        foreach ($skipped as $item) {
            $reason = trim((string) ($item['reason'] ?? ''));

            if ($reason === '') {
                continue;
            }

            $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
        }

        arsort($reasonCounts);
        $mainReason = (string) array_key_first($reasonCounts);
        $parts = [
            'Foram encontradas ' . $valid . ' de ' . $sessions . ' sessao(oes).',
            'Faltam ' . $missing . '.',
        ];

        if ($mainReason !== '') {
            $reasonText = [];

            foreach (array_slice($reasonCounts, 0, 3, true) as $reason => $total) {
                $reasonText[] = $reason . ' (' . $total . ' data(s))';
            }

            $parts[] = 'Motivo: ' . implode('; ', $reasonText) . '.';
            $parts[] = 'O que fazer: ' . app_group_quick_reason_action($mainReason);
        } else {
            $parts[] = 'Motivo: a grade escolhida nao gerou datas suficientes dentro do periodo pesquisado.';
            $parts[] = 'O que fazer: marque mais dias da semana, escolha outro horario ou reduza a quantidade de sessoes.';
        }

        return implode(' ', $parts);
    }
}

if (!function_exists('app_group_quick_group_for_slot')) {
    function app_group_quick_group_for_slot(mysqli $conn, int $clinicId, int $professionalId, int $serviceId, string $date, string $start): ?array
    {
        $startSql = strlen($start) === 5 ? $start . ':00' : $start;

        return app_stmt_one(
            $conn,
            'SELECT * FROM agenda_grupos
             WHERE clinica_id = ? AND profissional_id = ? AND servico_id = ? AND data_agendamento = ? AND hora_inicio = ?
             LIMIT 1',
            'iiiss',
            [$clinicId, $professionalId, $serviceId, $date, $startSql]
        );
    }
}

if (!function_exists('app_group_quick_build_preview')) {
    function app_group_quick_build_preview(mysqli $conn, int $clinicId, int $professionalId, array $service, int $patientId, string $startDate, string $time, int $sessions, array $weekdays): array
    {
        $rows = [];
        $skipped = [];
        $errors = [];
        $sessions = max(1, min(60, $sessions));
        $weekdays = app_group_quick_weekdays($weekdays);
        $startDate = app_group_quick_date_value($startDate, date('Y-m-d'));
        $time = app_group_quick_time_value($time);
        $startTime = DateTime::createFromFormat('H:i', $time);

        if ($weekdays === []) {
            $errors[] = 'Marque pelo menos um dia da semana.';
        }

        if (!$startTime) {
            $errors[] = 'Informe um horario valido.';
        }

        if ($patientId <= 0) {
            $errors[] = 'Escolha um paciente.';
        }

        if ($errors !== []) {
            return ['rows' => [], 'skipped' => [], 'errors' => $errors, 'sessions' => $sessions];
        }

        $duration = max(1, (int) ($service['tempo_minutos'] ?? 0));
        $startSql = $startTime->format('H:i:s');
        $endSql = app_group_minutes_to_time(app_group_time_to_minutes($startSql) + $duration) . ':00';
        $capacityDefault = max(1, (int) ($service['capacidade_agendamento'] ?? 1));
        $cursor = new DateTime($startDate);
        $guard = 0;

        while (count($rows) < $sessions && $guard < 420) {
            $date = $cursor->format('Y-m-d');
            $weekday = (int) $cursor->format('w');

            if (in_array($weekday, $weekdays, true)) {
                $action = 'Criar grupo';
                $capacity = $capacityDefault;
                $used = 0;
                $status = 'ok';
                $reason = '';
                $existingGroup = app_group_quick_group_for_slot($conn, $clinicId, $professionalId, (int) $service['id'], $date, $startSql);

                if ($existingGroup) {
                    $action = 'Grupo existente';
                    $capacity = max(1, (int) ($existingGroup['capacidade'] ?? $capacityDefault));
                    $total = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM agenda_grupo_pacientes WHERE clinica_id = ? AND grupo_id = ? AND status <> ?', 'iis', [$clinicId, (int) $existingGroup['id'], 'cancelado']);
                    $exists = app_stmt_one($conn, 'SELECT id FROM agenda_grupo_pacientes WHERE clinica_id = ? AND grupo_id = ? AND paciente_id = ? LIMIT 1', 'iii', [$clinicId, (int) $existingGroup['id'], $patientId]);
                    $used = (int) ($total['total'] ?? 0);

                    if ($exists) {
                        $status = 'skip';
                        $reason = 'Paciente ja esta neste grupo.';
                    } elseif ($used >= $capacity) {
                        $status = 'skip';
                        $reason = 'Grupo lotado.';
                    }
                }

                if ($status === 'ok') {
                    $dateError = app_group_validate_schedule_date($date);

                    if ($dateError !== null) {
                        $status = 'skip';
                        $reason = $dateError;
                    } else {
                        if (!$existingGroup) {
                            if (!app_group_has_availability($conn, $clinicId, $professionalId, $date, $startSql, $endSql)) {
                                $status = 'skip';
                                $reason = 'Horario nao liberado pelo profissional.';
                            } elseif (app_group_has_individual_conflict($conn, $clinicId, $professionalId, $date, $startSql, $endSql)) {
                                $status = 'skip';
                                $reason = 'Horario ocupado por agenda individual.';
                            } elseif (app_group_has_other_group_conflict($conn, $clinicId, $professionalId, (int) $service['id'], $date, $startSql, $endSql)) {
                                $status = 'skip';
                                $reason = 'Horario ocupado por outro grupo.';
                            }
                        }

                        $patientConflict = app_group_patient_schedule_conflict($conn, $clinicId, $patientId, $date, $startSql, $endSql);

                        if ($status === 'ok' && $patientConflict !== null) {
                            $status = 'skip';
                            $reason = 'Paciente ja tem agenda neste horario.';
                        }
                    }
                }

                $row = [
                    'session' => count($rows) + 1,
                    'date' => $date,
                    'weekday' => $weekday,
                    'time' => substr($startSql, 0, 5),
                    'end_time' => substr($endSql, 0, 5),
                    'action' => $action,
                    'capacity' => $capacity,
                    'used' => $used,
                    'reason' => $reason,
                    'group_id' => $existingGroup ? (int) $existingGroup['id'] : 0,
                ];

                if ($status === 'ok') {
                    $rows[] = $row;
                } else {
                    $skipped[] = $row;
                }
            }

            $cursor->modify('+1 day');
            $guard++;
        }

        if (count($rows) < $sessions) {
            $errors[] = app_group_quick_preview_error($rows, $skipped, $sessions);
        }

        return ['rows' => $rows, 'skipped' => $skipped, 'errors' => $errors, 'sessions' => $sessions];
    }
}

if (!function_exists('app_group_quick_whatsapp_message')) {
    function app_group_quick_whatsapp_message(array $patient, array $rows, string $professional, string $service): string
    {
        $patientName = trim((string) ($patient['nome'] ?? 'Paciente')) ?: 'Paciente';
        $lines = [
            'Ola, ' . $patientName . '! Segue sua grade de atendimentos em grupo.',
            'Profissional: ' . $professional,
            'Servico: ' . $service,
            '',
        ];

        foreach ($rows as $row) {
            $lines[] = str_pad((string) (int) $row['session'], 2, '0', STR_PAD_LEFT)
                . ' - ' . app_date_br((string) $row['date'])
                . ' as ' . (string) $row['time'];
        }

        return implode("\n", $lines);
    }
}

$professionals = app_stmt_all(
    $conn,
    'SELECT DISTINCT p.id, p.nome, p.telefone
     FROM profissionais p
     INNER JOIN profissional_servico ps ON ps.profissional_id = p.id AND ps.clinica_id = p.clinica_id
     INNER JOIN servicos s ON s.id = ps.servico_id AND s.clinica_id = ps.clinica_id
     WHERE p.clinica_id = ? AND s.ativo = 1
     ORDER BY p.nome',
    'i',
    [$clinicId]
);

$selectedProfessionalId = app_query_int('professional_id') ?: (int) ($professionals[0]['id'] ?? 0);
$servicesForProfessional = $selectedProfessionalId > 0
    ? app_services_for_professional($conn, $selectedProfessionalId)
    : [];
$groupServices = array_values(array_filter(
    $servicesForProfessional,
    static fn (array $service): bool => ($service['tipo_agendamento'] ?? 'individual') === 'grupo'
));
$selectedServiceId = app_query_int('service_id');

if ($selectedServiceId <= 0 && count($servicesForProfessional) === 1) {
    $selectedServiceId = (int) ($servicesForProfessional[0]['id'] ?? 0);
}

if ($selectedServiceId <= 0) {
    $selectedServiceId = (int) ($groupServices[0]['id'] ?? 0);
}

$selectedService = null;
$showGroupServiceSelect = count($groupServices) > 1;
$groupStatuses = app_schedule_statuses();

foreach ($groupServices as $service) {
    if ((int) $service['id'] === $selectedServiceId) {
        $selectedService = $service;
        break;
    }
}

$weekStart = app_week_start(app_request_query('week_start', date('Y-m-d')) ?? date('Y-m-d'));
$weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$openGroupId = app_query_int('group_id');
$openSlotDate = app_request_query('slot_date', '') ?? '';
$openSlotTime = app_request_query('slot_time', '') ?? '';

if (app_request_method() === 'POST' && $canManageGroupAgenda) {
    $action = app_request_post('action', '') ?? '';
    $postProfessionalId = app_post_int('professional_id');
    $postServiceId = app_post_int('service_id');
    $postWeekStart = app_week_start(app_request_post('week_start', $weekStart) ?? $weekStart);
    $redirectBase = [
        'professional_id' => $postProfessionalId ?: $selectedProfessionalId,
        'service_id' => $postServiceId ?: $selectedServiceId,
        'week_start' => $postWeekStart,
    ];
    $quickRedirectExtra = [];
    $result = ['ok' => false, 'message' => 'Acao invalida.'];
    $postService = $postProfessionalId > 0 && $postServiceId > 0
        ? app_group_service($conn, $clinicId, $postProfessionalId, $postServiceId)
        : null;

    if (!$postService || ($postService['tipo_agendamento'] ?? 'individual') !== 'grupo') {
        $result = ['ok' => false, 'message' => 'Selecione um servico de grupo.'];
    } elseif ($action === 'add_patient') {
        $date = app_request_post('data_agendamento', '') ?? '';
        $time = app_request_post('hora_inicio', '') ?? '';
        $patientId = app_post_int('paciente_id');
        $guideId = app_post_int('guia_id');
        $startDate = DateTime::createFromFormat('H:i', $time) ?: DateTime::createFromFormat('H:i:s', $time);
        $dateError = app_group_validate_schedule_date($date);
        $guide = $patientId > 0 && $guideId > 0
            ? app_group_active_guide($conn, $clinicId, $guideId, $patientId, $postProfessionalId, 0, 0, (int) ($postService['id'] ?? 0), false)
            : null;

        if ($dateError !== null) {
            $result = ['ok' => false, 'message' => $dateError];
        } elseif (!$startDate) {
            $result = ['ok' => false, 'message' => 'Informe um horario valido para agendar.'];
        } elseif ($patientId <= 0) {
            $result = ['ok' => false, 'message' => 'Escolha um paciente da lista.'];
        } elseif (!$guide) {
            $result = ['ok' => false, 'message' => 'Escolha uma guia com sessoes disponiveis.'];
        } else {
            $reserved = app_group_reserved_guide_sessions($conn, $clinicId, $guideId);
            $remaining = max(0, (int) ($guide['total_sessoes'] ?? 0) - (int) ($guide['usadas'] ?? 0) - $reserved);
            $duration = max(1, (int) ($postService['tempo_minutos'] ?? 0));
            $endDate = clone $startDate;
            $endDate->modify('+' . $duration . ' minutes');
            $startSql = $startDate->format('H:i:s');
            $endSql = $endDate->format('H:i:s');

            $patientConflict = app_group_patient_schedule_conflict($conn, $clinicId, $patientId, date('Y-m-d', strtotime($date)), $startSql, $endSql);

            if ($remaining <= 0) {
                $result = ['ok' => false, 'message' => 'A guia selecionada nao possui sessoes disponiveis.'];
            } elseif ($patientConflict !== null) {
                $result = ['ok' => false, 'message' => app_group_patient_conflict_message($patientConflict)];
            } else {
                $openResult = app_group_find_or_create($conn, $clinicId, $postProfessionalId, $postService, $date, $startSql);

                if (!($openResult['ok'] ?? false)) {
                    $result = $openResult;
                } else {
                    $group = $openResult['group'];
                    $total = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM agenda_grupo_pacientes WHERE clinica_id = ? AND grupo_id = ? AND status <> ?', 'iis', [$clinicId, (int) $group['id'], 'cancelado']);
                    $exists = app_stmt_one($conn, 'SELECT id FROM agenda_grupo_pacientes WHERE clinica_id = ? AND grupo_id = ? AND paciente_id = ? LIMIT 1', 'iii', [$clinicId, (int) $group['id'], $patientId]);

                    if ($exists) {
                        $result = ['ok' => false, 'message' => 'Paciente ja esta neste grupo.'];
                    } elseif ((int) ($total['total'] ?? 0) >= (int) $group['capacidade']) {
                        $result = ['ok' => false, 'message' => 'Grupo lotado para este horario.'];
                    } else {
                        $ok = app_stmt_execute(
                            $conn,
                            'INSERT INTO agenda_grupo_pacientes (clinica_id, grupo_id, paciente_id, guia_id, status, observacoes)
                             VALUES (?, ?, ?, ?, ?, ?)',
                            'iiiiss',
                            [$clinicId, (int) $group['id'], $patientId, $guideId, 'agendado', trim((string) ($_POST['observacoes'] ?? ''))]
                        );
                        $result = $ok
                            ? ['ok' => true, 'message' => 'Paciente adicionado ao grupo.', 'group_id' => (int) $group['id']]
                            : ['ok' => false, 'message' => 'Nao foi possivel adicionar o paciente.'];
                    }
                }
            }
        }
    } elseif ($action === 'quick_schedule') {
        $patientId = app_post_int('paciente_id');
        $guideId = app_post_int('guia_id');
        $startDate = app_group_quick_date_value(app_request_post('data_inicio', date('Y-m-d')), date('Y-m-d'));
        $time = app_group_quick_time_value(app_request_post('hora_inicio', '08:00'));
        $sessions = max(1, min(60, app_post_int('quantidade_sessoes', 1)));
        $weekdays = app_group_quick_weekdays($_POST['dias_semana'] ?? []);
        $notes = trim((string) app_request_post('observacoes', ''));
        $quickRedirectExtra = [
            'modelo' => 'rapido',
            'paciente_id' => $patientId,
            'guia_id' => $guideId,
            'data_inicio' => $startDate,
            'hora_inicio' => $time,
            'quantidade_sessoes' => $sessions,
            'dias_semana' => $weekdays,
        ];
        $guide = $guideId > 0 ? app_group_active_guide($conn, $clinicId, $guideId, $patientId, $postProfessionalId, 0, 0, (int) ($postService['id'] ?? 0), false) : null;

        if ($patientId <= 0) {
            $result = ['ok' => false, 'message' => 'Escolha um paciente da lista.'];
        } elseif (!$guide) {
            $result = ['ok' => false, 'message' => 'Escolha uma guia com sessoes disponiveis.'];
        } else {
            $reserved = app_group_reserved_guide_sessions($conn, $clinicId, $guideId);
            $remaining = max(0, (int) ($guide['total_sessoes'] ?? 0) - (int) ($guide['usadas'] ?? 0) - $reserved);
            $sessions = max(1, min($sessions, max(1, $remaining)));
            $quickRedirectExtra['quantidade_sessoes'] = $sessions;
            $preview = app_group_quick_build_preview($conn, $clinicId, $postProfessionalId, $postService, $patientId, $startDate, $time, $sessions, $weekdays);

            if ($remaining <= 0) {
                $result = ['ok' => false, 'message' => 'A guia selecionada nao possui sessoes disponiveis.'];
            } elseif (!empty($preview['errors'])) {
                $result = ['ok' => false, 'message' => implode(' ', $preview['errors'])];
            } elseif (count($preview['rows'] ?? []) < $sessions) {
                $result = ['ok' => false, 'message' => 'A previa nao completou a quantidade de sessoes solicitada.'];
            } else {
                $patient = app_stmt_one($conn, 'SELECT id, nome, telefone FROM pacientes WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $patientId]);
                $created = 0;

                $conn->begin_transaction();

                try {
                    foreach ($preview['rows'] as $row) {
                        $openResult = app_group_find_or_create($conn, $clinicId, $postProfessionalId, $postService, (string) $row['date'], (string) $row['time']);

                        if (!($openResult['ok'] ?? false) || empty($openResult['group'])) {
                            throw new RuntimeException((string) ($openResult['message'] ?? 'Nao foi possivel abrir o grupo.'));
                        }

                        $group = $openResult['group'];
                        $total = app_stmt_one($conn, 'SELECT COUNT(*) AS total FROM agenda_grupo_pacientes WHERE clinica_id = ? AND grupo_id = ? AND status <> ?', 'iis', [$clinicId, (int) $group['id'], 'cancelado']);
                        $exists = app_stmt_one($conn, 'SELECT id FROM agenda_grupo_pacientes WHERE clinica_id = ? AND grupo_id = ? AND paciente_id = ? LIMIT 1', 'iii', [$clinicId, (int) $group['id'], $patientId]);

                        if ($exists) {
                            throw new RuntimeException('Paciente ja esta em um dos grupos da previa.');
                        }

                        if ((int) ($total['total'] ?? 0) >= (int) $group['capacidade']) {
                            throw new RuntimeException('Um dos grupos ficou lotado antes da confirmacao.');
                        }

                        $ok = app_stmt_execute(
                            $conn,
                            'INSERT INTO agenda_grupo_pacientes (clinica_id, grupo_id, paciente_id, guia_id, status, observacoes)
                             VALUES (?, ?, ?, ?, ?, ?)',
                            'iiiiss',
                            [$clinicId, (int) $group['id'], $patientId, $guideId, 'agendado', $notes]
                        );

                        if (!$ok) {
                            throw new RuntimeException('Nao foi possivel incluir o paciente na grade.');
                        }

                        $created++;
                    }

                    $conn->commit();
                    $postProfessional = app_stmt_one($conn, 'SELECT nome FROM profissionais WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $postProfessionalId]);
                    $professionalName = (string) ($postProfessional['nome'] ?? 'Profissional');
                    $serviceName = (string) ($postService['nome'] ?? 'Servico');
                    $message = $patient
                        ? app_group_quick_whatsapp_message($patient, $preview['rows'], $professionalName, $serviceName)
                        : '';
                    $_SESSION['app_group_quick_whatsapp_url'] = $patient ? app_group_whatsapp_link((string) ($patient['telefone'] ?? ''), $message) : null;
                    $_SESSION['app_group_quick_confirmed_rows'] = $preview['rows'];
                    $result = ['ok' => true, 'message' => $created . ' agendamento(s) em grupo confirmados.'];
                    $quickRedirectExtra['confirmado'] = 1;
                } catch (Throwable $exception) {
                    $conn->rollback();
                    $result = ['ok' => false, 'message' => $exception->getMessage()];
                }
            }
        }
    } elseif ($action === 'update_member_status') {
        $memberId = app_post_int('member_id');
        $status = app_request_post('status', 'agendado') ?? 'agendado';
        $guideId = app_post_int('guia_id');
        $member = app_stmt_one(
            $conn,
            'SELECT gp.*, g.profissional_id, g.servico_id, g.data_agendamento, g.hora_inicio, g.hora_fim
             FROM agenda_grupo_pacientes gp
             INNER JOIN agenda_grupos g ON g.id = gp.grupo_id AND g.clinica_id = gp.clinica_id
             WHERE gp.clinica_id = ? AND gp.id = ?
             LIMIT 1',
            'ii',
            [$clinicId, $memberId]
        );

        if (!$member) {
            $result = ['ok' => false, 'message' => 'Paciente do grupo nao encontrado.'];
        } elseif (!array_key_exists($status, $groupStatuses)) {
            $result = ['ok' => false, 'message' => 'Selecione um status valido.'];
        } elseif ($status !== 'realizado') {
            app_group_delete_attendance($conn, $clinicId, (int) ($member['atendimento_id'] ?? 0));
            $ok = app_stmt_execute(
                $conn,
                'UPDATE agenda_grupo_pacientes SET status = ?, atendimento_id = NULL WHERE clinica_id = ? AND id = ?',
                'sii',
                [$status, $clinicId, $memberId]
            );
            $result = $ok
                ? ['ok' => true, 'message' => 'Status do paciente atualizado.', 'group_id' => (int) $member['grupo_id']]
                : ['ok' => false, 'message' => 'Nao foi possivel atualizar o status.'];
        } else {
            $guide = $guideId > 0
                ? app_group_active_guide($conn, $clinicId, $guideId, (int) $member['paciente_id'], (int) $member['profissional_id'], (int) ($member['atendimento_id'] ?? 0), $memberId, (int) ($member['servico_id'] ?? 0))
                : null;

            if (!$guide) {
                $result = ['ok' => false, 'message' => 'Informe uma guia autorizada e com sessoes disponiveis para realizar.'];
            } else {
                $attendanceId = (int) ($member['atendimento_id'] ?? 0);
                $attendanceOk = $attendanceId > 0
                    ? app_group_update_attendance($conn, $clinicId, $attendanceId, $member, $member, $guide)
                    : (($attendanceId = app_group_create_attendance($conn, $clinicId, $member, $member, $guide)) > 0);

                if ($attendanceOk && $attendanceId > 0) {
                    app_stmt_execute($conn, 'UPDATE agenda_grupo_pacientes SET status = ?, guia_id = ?, atendimento_id = ? WHERE clinica_id = ? AND id = ?', 'siiii', ['realizado', $guideId, $attendanceId, $clinicId, $memberId]);
                    $result = ['ok' => true, 'message' => 'Atendimento realizado.', 'group_id' => (int) $member['grupo_id']];
                } else {
                    $result = ['ok' => false, 'message' => 'Nao foi possivel criar o atendimento.'];
                }
            }
        }
    } elseif ($action === 'realize_group') {
        $groupId = app_post_int('group_id');
        $members = app_stmt_all(
            $conn,
            'SELECT gp.*, g.profissional_id, g.servico_id, g.data_agendamento, g.hora_inicio, g.hora_fim
             FROM agenda_grupo_pacientes gp
             INNER JOIN agenda_grupos g ON g.id = gp.grupo_id AND g.clinica_id = gp.clinica_id
             WHERE gp.clinica_id = ? AND gp.grupo_id = ? AND gp.status <> ?
             ORDER BY gp.id',
            'iis',
            [$clinicId, $groupId, 'realizado']
        );
        $done = 0;
        $blocked = 0;

        foreach ($members as $member) {
            $guideId = (int) ($member['guia_id'] ?? 0);
            $guide = $guideId > 0
                ? app_group_active_guide($conn, $clinicId, $guideId, (int) $member['paciente_id'], (int) $member['profissional_id'], 0, (int) $member['id'], (int) ($member['servico_id'] ?? 0))
                : null;

            if (!$guide) {
                $blocked++;
                continue;
            }

            $attendanceId = app_group_create_attendance($conn, $clinicId, $member, $member, $guide);
            if ($attendanceId > 0) {
                app_stmt_execute($conn, 'UPDATE agenda_grupo_pacientes SET status = ?, atendimento_id = ? WHERE clinica_id = ? AND id = ?', 'siii', ['realizado', $attendanceId, $clinicId, (int) $member['id']]);
                $done++;
            } else {
                $blocked++;
            }
        }

        $result = [
            'ok' => $done > 0,
            'message' => $done . ' paciente(s) realizados. ' . $blocked . ' bloqueado(s) por falta de guia autorizada/disponivel.',
            'group_id' => $groupId,
        ];
    } elseif ($action === 'remove_member') {
        $memberId = app_post_int('member_id');
        $member = app_stmt_one($conn, 'SELECT * FROM agenda_grupo_pacientes WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $memberId]);

        if (!$member) {
            $result = ['ok' => false, 'message' => 'Paciente do grupo nao encontrado.'];
        } elseif (($member['status'] ?? '') === 'realizado') {
            $result = ['ok' => false, 'message' => 'Paciente realizado nao pode ser removido do grupo.'];
        } else {
            app_stmt_execute($conn, 'DELETE FROM agenda_grupo_pacientes WHERE clinica_id = ? AND id = ?', 'ii', [$clinicId, $memberId]);
            $result = ['ok' => true, 'message' => 'Paciente removido do grupo.', 'group_id' => (int) $member['grupo_id']];
        }
    }

    app_flash($result['ok'] ? 'success' : 'danger', $result['message']);
    $extra = [];

    if (!empty($result['group_id'])) {
        $extra['group_id'] = (int) $result['group_id'];
    }

    if ($quickRedirectExtra !== []) {
        $extra = array_merge($extra, $quickRedirectExtra);
    }

    app_redirect('secretaria_agenda_grupo.php?' . app_build_query($redirectBase, $extra));
}

$selectedProfessional = null;
foreach ($professionals as $professional) {
    if ((int) $professional['id'] === $selectedProfessionalId) {
        $selectedProfessional = $professional;
        break;
    }
}

$quickMode = app_request_query('modelo', '') === 'rapido';

if ($quickMode) {
    $quickPatientId = app_query_int('paciente_id');
    $quickGuideId = app_query_int('guia_id');
    $quickStartDate = app_group_quick_date_value(app_request_query('data_inicio', $weekStart), $weekStart);
    $quickTime = app_group_quick_time_value(app_request_query('hora_inicio', '08:00'));
    $quickWeekdays = app_group_quick_weekdays($_GET['dias_semana'] ?? [1, 3, 5]);
    $quickPatient = $quickPatientId > 0
        ? app_stmt_one($conn, 'SELECT id, nome, telefone FROM pacientes WHERE clinica_id = ? AND id = ? LIMIT 1', 'ii', [$clinicId, $quickPatientId])
        : null;
    $quickGuides = $quickPatientId > 0 && $selectedProfessionalId > 0
        ? app_group_available_guides($conn, $clinicId, $quickPatientId, $selectedProfessionalId, 0, 0, $selectedServiceId)
        : [];
    $quickPatientScheduleRows = $quickPatientId > 0
        ? app_group_patient_schedule_rows($conn, $clinicId, $quickPatientId, date('Y-m-d'), 20)
        : [];
    $quickSessionsDefault = 10;
    $quickSessionsLimit = 60;

    foreach ($quickGuides as $quickGuideOption) {
        if ((int) $quickGuideOption['id'] === $quickGuideId) {
            $quickSessionsDefault = max(1, (int) ($quickGuideOption['restantes'] ?? 1));
            $quickSessionsLimit = $quickSessionsDefault;
            break;
        }
    }

    $quickSessions = max(1, min($quickSessionsLimit, app_query_int('quantidade_sessoes', $quickSessionsDefault)));
    $quickCanPreview = $selectedService && $quickPatientId > 0 && $quickGuideId > 0 && $quickWeekdays !== [];
    $quickPreview = $quickCanPreview
        ? app_group_quick_build_preview($conn, $clinicId, $selectedProfessionalId, $selectedService, $quickPatientId, $quickStartDate, $quickTime, $quickSessions, $quickWeekdays)
        : ['rows' => [], 'skipped' => [], 'errors' => [], 'sessions' => $quickSessions];
    $quickConfirmed = app_request_query('confirmado', '') === '1';
    $quickConfirmedRows = $_SESSION['app_group_quick_confirmed_rows'] ?? null;
    unset($_SESSION['app_group_quick_confirmed_rows']);

    if ($quickConfirmed && is_array($quickConfirmedRows)) {
        $quickPreview = ['rows' => $quickConfirmedRows, 'skipped' => [], 'errors' => [], 'sessions' => count($quickConfirmedRows)];
    }

    $quickRows = $quickPreview['rows'] ?? [];
    $quickSkippedRows = $quickPreview['skipped'] ?? [];
    $quickRowsByMonth = [];
    $quickPatientScheduleByMonth = [];

    foreach ($quickRows as $quickRow) {
        $monthKey = date('Y-m', strtotime((string) $quickRow['date']));
        $quickRowsByMonth[$monthKey][] = $quickRow;
    }

    foreach ($quickPatientScheduleRows as $scheduleRow) {
        $scheduleDate = (string) ($scheduleRow['data_agendamento'] ?? '');
        $timestamp = strtotime($scheduleDate);

        if (!$timestamp) {
            continue;
        }

        $monthKey = date('Y-m', $timestamp);
        $dateKey = date('Y-m-d', $timestamp);
        $quickPatientScheduleByMonth[$monthKey][$dateKey][] = $scheduleRow;
    }

    $quickWhatsappUrl = $_SESSION['app_group_quick_whatsapp_url'] ?? null;
    unset($_SESSION['app_group_quick_whatsapp_url']);
    $quickLegacyUrl = 'secretaria_agenda_grupo.php?' . app_build_query([
        'professional_id' => $selectedProfessionalId,
        'service_id' => $selectedServiceId,
        'week_start' => $weekStart,
    ]);
    $menuFlashMode = 'manual';
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Cronograma rapido - Agenda em Grupo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(180deg, #f7fbfc 0%, #edf4f6 100%);
    color: #193542;
    min-height: 100vh;
}
.quick-shell {
    padding: 0.55rem 0.9rem 0.9rem;
}
.quick-head {
    align-items: flex-start;
    display: flex;
    gap: 0.75rem;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}
.quick-head h1 {
    color: #16333f;
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0;
}
.quick-head p {
    color: #627985;
    font-size: 0.78rem;
    margin: 0.18rem 0 0;
}
.quick-head-side {
    align-items: flex-end;
    display: grid;
    gap: 0.4rem;
    justify-items: end;
}
.quick-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    justify-content: flex-end;
}
.quick-tag {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    font-size: 0.78rem;
    font-weight: 800;
    min-height: 30px;
    padding: 0.28rem 0.75rem;
}
.quick-tag.professional {
    background: #e5effb;
    border: 1px solid #c8d9ef;
    color: #225c9d;
}
.quick-tag.service {
    background: #e0f4ea;
    border: 1px solid #bedfcd;
    color: #176b3c;
}
.quick-return {
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 800;
    min-height: 32px;
}
.quick-panel {
    background: #fff;
    border: 1px solid #d9e6eb;
    border-radius: 8px;
    box-shadow: 0 12px 26px rgba(24, 56, 69, 0.08);
    margin-bottom: 0.55rem;
    padding: 0.62rem;
}
.quick-builder {
    align-items: end;
    display: grid;
    gap: 0.45rem;
    grid-template-columns: 1fr;
}
.quick-form-heading {
    align-items: flex-start;
    display: flex;
    gap: 0.7rem;
    justify-content: space-between;
}
.quick-form-heading h2,
.quick-preview-title h2 {
    color: #16333f;
    font-size: 0.98rem;
    font-weight: 800;
    margin: 0;
}
.quick-form-heading p,
.quick-preview-title p {
    color: #627985;
    font-size: 0.76rem;
    margin: 0.18rem 0 0;
}
.quick-form-grid {
    display: grid;
    gap: 0.42rem 0.55rem;
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
.quick-field label,
.quick-days-label {
    color: #546d79;
    display: block;
    font-size: 0.76rem;
    font-weight: 800;
    margin-bottom: 0.18rem;
}
.quick-field.full {
    grid-column: 1 / -1;
}
.quick-field.wide {
    grid-column: span 2;
}
.quick-field .form-control,
.quick-field .form-select {
    background: #f8fbfc;
    border-color: #d9e6eb;
    border-radius: 8px;
    font-size: 0.84rem;
    min-height: 34px;
}
.quick-days {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 0.34rem;
}
.quick-day-option input {
    position: absolute;
    opacity: 0;
}
.quick-day-option span {
    background: #fff;
    border: 1px solid #d9e6eb;
    border-radius: 8px;
    color: #627985;
    cursor: pointer;
    display: inline-flex;
    font-weight: 800;
    justify-content: center;
    min-width: 40px;
    padding: 0.3rem 0.45rem;
}
.quick-day-option input:checked + span {
    background: #e0f4ea;
    border-color: #1f7a8c;
    color: #176b3c;
}
.quick-actions {
    align-items: end;
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}
.quick-actions .btn {
    border-radius: 8px;
    font-weight: 800;
    min-height: 34px;
    padding-left: 0.9rem;
    padding-right: 0.9rem;
}
.quick-preview-head {
    align-items: flex-start;
    display: flex;
    gap: 0.7rem;
    justify-content: space-between;
    margin-bottom: 0.45rem;
}
.quick-count {
    background: #e0f4ea;
    border: 1px solid #bedfcd;
    border-radius: 8px;
    color: #176b3c;
    display: inline-flex;
    font-size: 0.78rem;
    font-weight: 800;
    min-height: 30px;
    padding: 0.28rem 0.75rem;
}
.quick-calendar {
    display: grid;
    gap: 0.75rem;
}
.quick-month h3 {
    color: #546d79;
    font-size: 0.86rem;
    font-weight: 800;
    margin: 0 0 0.55rem;
    text-transform: uppercase;
}
.quick-day-grid {
    display: grid;
    gap: 0.72rem;
    grid-template-columns: repeat(auto-fit, minmax(176px, 1fr));
}
.quick-day-card {
    background: #fbfdfe;
    border: 1px solid #d9e6eb;
    border-left: 4px solid #1f9d6d;
    border-radius: 8px;
    display: grid;
    gap: 0.42rem;
    min-height: 112px;
    padding: 0.65rem;
}
.quick-day-card.is-create {
    border-left-color: #276fbf;
}
.quick-day-date {
    align-items: flex-start;
    display: flex;
    justify-content: space-between;
}
.quick-day-date strong {
    color: #16333f;
    display: block;
    font-size: 1.35rem;
    line-height: 1;
}
.quick-day-date span {
    color: #627985;
    display: block;
    font-size: 0.78rem;
    font-weight: 700;
    margin-top: 0.2rem;
}
.quick-session {
    background: #edf6f7;
    border-radius: 999px;
    color: #315766;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.2rem 0.5rem;
}
.quick-card-main strong {
    color: #16333f;
    display: block;
    font-size: 0.96rem;
}
.quick-card-main span {
    color: #627985;
    display: block;
    font-size: 0.82rem;
    margin-top: 0.12rem;
}
.quick-chip {
    border-radius: 999px;
    display: inline-flex;
    font-size: 0.78rem;
    font-weight: 800;
    padding: 0.24rem 0.62rem;
    width: fit-content;
}
.quick-chip.existing {
    background: #e0f4ea;
    color: #176b3c;
}
.quick-chip.create {
    background: #e5effb;
    color: #225c9d;
}
.quick-patient-agenda {
    border-top: 3px solid #276fbf;
}
.quick-patient-calendar {
    display: grid;
    gap: 0.55rem;
}
.quick-agenda-month h3 {
    color: #546d79;
    font-size: 0.78rem;
    font-weight: 800;
    margin: 0 0 0.4rem;
    text-transform: uppercase;
}
.quick-scheduled-days {
    display: grid;
    gap: 0.45rem;
    grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
}
.quick-scheduled-day {
    background: #fbfdfe;
    border: 1px solid #d9e6eb;
    border-left: 3px solid #276fbf;
    border-radius: 8px;
    padding: 0.45rem;
}
.quick-scheduled-day-head {
    align-items: baseline;
    display: flex;
    gap: 0.35rem;
    margin-bottom: 0.28rem;
}
.quick-scheduled-day-head strong {
    color: #16333f;
    font-size: 1rem;
    line-height: 1;
}
.quick-scheduled-day-head span {
    color: #8095a0;
    font-size: 0.72rem;
    font-weight: 800;
}
.quick-calendar-event {
    background: #eef5ff;
    border: 1px solid #c8d9ef;
    border-left: 3px solid #276fbf;
    border-radius: 6px;
    color: #193542;
    display: block;
    margin-top: 0.22rem;
    padding: 0.26rem 0.32rem;
    text-decoration: none;
}
.quick-calendar-event.is-grupo {
    background: #ecf8f1;
    border-color: #bedfcd;
    border-left-color: #1f9d6d;
}
.quick-calendar-event strong,
.quick-calendar-event span,
.quick-calendar-event small {
    display: block;
    line-height: 1.2;
}
.quick-calendar-event strong {
    color: #16333f;
    font-size: 0.76rem;
}
.quick-calendar-event span {
    color: #315766;
    font-size: 0.74rem;
    font-weight: 800;
    margin-top: 0.16rem;
}
.quick-calendar-event small {
    color: #627985;
    font-size: 0.68rem;
    margin-top: 0.16rem;
}
.quick-chip.individual {
    background: #e5effb;
    color: #225c9d;
}
.quick-chip.grupo {
    background: #e0f4ea;
    color: #176b3c;
}
.quick-empty {
    align-items: center;
    background: #f8fbfc;
    border: 1px dashed #c9dce3;
    border-radius: 8px;
    color: #627985;
    display: flex;
    min-height: 88px;
    padding: 0.75rem;
}
.quick-blocked {
    background: #fff9ed;
    border: 1px solid #f0d5a2;
    border-radius: 8px;
    color: #6e4a12;
    margin-bottom: 0.75rem;
    padding: 0.75rem 0.85rem;
}
.quick-blocked strong {
    color: #553707;
}
.quick-blocked ul {
    display: grid;
    gap: 0.35rem;
    margin: 0.6rem 0 0;
    padding-left: 1.1rem;
}
.quick-blocked li {
    line-height: 1.35;
}
.quick-inline-notice {
    bottom: 1rem;
    left: 50%;
    max-width: min(560px, calc(100vw - 2rem));
    position: fixed;
    transform: translateX(-50%);
    z-index: 1080;
}
.quick-autocomplete {
    position: relative;
}
.quick-autocomplete-menu {
    background: #fff;
    border: 1px solid #d9e6eb;
    border-radius: 8px;
    box-shadow: 0 18px 34px rgba(22, 51, 63, 0.16);
    display: none;
    left: 0;
    max-height: 220px;
    overflow: auto;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    z-index: 1060;
}
.quick-autocomplete-menu.is-open {
    display: block;
}
.quick-autocomplete-option {
    background: transparent;
    border: 0;
    color: #16333f;
    display: block;
    padding: 0.55rem 0.7rem;
    text-align: left;
    width: 100%;
}
.quick-preview-trigger {
    align-items: center;
    background: #fff;
    border: 1px solid #d9e6eb;
    border-radius: 8px;
    display: flex;
    gap: 0.7rem;
    justify-content: space-between;
    margin-bottom: 0.85rem;
    padding: 0.65rem 0.85rem;
}
.quick-preview-trigger strong {
    color: #16333f;
    display: block;
    line-height: 1.15;
}
.quick-preview-trigger span {
    color: #627985;
    display: block;
    font-size: 0.82rem;
    margin-top: 0.12rem;
}
.quick-preview-modal .modal-dialog {
    max-width: min(1040px, calc(100vw - 1rem));
}
.quick-preview-modal .modal-header,
.quick-preview-modal .modal-body,
.quick-preview-modal .modal-footer {
    padding: 0.85rem 1rem;
}
.quick-preview-modal .modal-body {
    background: #f8fbfc;
}
@media (max-width: 1100px) {
    .quick-builder,
    .quick-form-grid {
        grid-template-columns: 1fr;
    }
    .quick-field.wide {
        grid-column: 1;
    }
    .quick-head,
    .quick-form-heading,
    .quick-preview-head {
        align-items: stretch;
        flex-direction: column;
    }
    .quick-head-side {
        align-items: stretch;
        justify-items: stretch;
    }
    .quick-tags,
    .quick-actions {
        justify-content: flex-start;
    }
}
</style>
</head>
<body>
<?php include 'partials/menu.php'; ?>
<?php $quickFlash = $flash ?? null; ?>
<main class="quick-shell">
    <section class="quick-head">
        <div>
            <h1>Agenda em Grupo</h1>
            <p>Profissional ja selecionado. Ao identificar servico de grupo, o sistema abre este passo rapido.</p>
        </div>
        <div class="quick-head-side">
            <div class="quick-tags">
                <span class="quick-tag professional"><?= app_h((string) ($selectedProfessional['nome'] ?? 'Profissional')) ?></span>
                <span class="quick-tag service"><?= app_h((string) ($selectedService['nome'] ?? 'Servico em grupo')) ?></span>
            </div>
            <a class="btn btn-outline-primary quick-return" href="<?= app_h($quickLegacyUrl) ?>">Voltar para agenda de grupo</a>
        </div>
    </section>

    <?php if (!$selectedService): ?>
        <div class="alert alert-warning">Este profissional ainda nao tem servico de grupo vinculado.</div>
    <?php else: ?>
        <section class="quick-panel">
            <form method="GET" id="quickScheduleForm" class="quick-builder">
                <input type="hidden" name="modelo" value="rapido">
                <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                <input type="hidden" name="service_id" value="<?= (int) $selectedServiceId ?>">
                <input type="hidden" name="week_start" value="<?= app_h($weekStart) ?>">
                <div class="quick-form-heading">
                    <div>
                        <h2>Montar cronograma rapido</h2>
                        <p>Escolha a data de inicio, marque os dias da semana e gere a previa.</p>
                    </div>
                </div>
                <div class="quick-form-grid">
                    <div class="quick-field wide">
                        <label for="quickPatientSearch">Paciente</label>
                        <input type="hidden" name="paciente_id" id="quickPatientId" value="<?= (int) $quickPatientId ?>">
                        <div class="quick-autocomplete">
                            <input type="text" id="quickPatientSearch" class="form-control" autocomplete="off" value="<?= app_h((string) ($quickPatient['nome'] ?? '')) ?>" placeholder="Digite para buscar o paciente" required>
                            <div class="quick-autocomplete-menu" id="quickPatientMenu"></div>
                        </div>
                    </div>
                    <div class="quick-field wide">
                        <label for="quickGuide">Guia</label>
                        <select name="guia_id" id="quickGuide" class="form-select" required>
                            <?php if ($quickGuides === []): ?>
                                <option value="">Selecione o paciente</option>
                            <?php else: ?>
                                <option value="">Selecione a guia</option>
                                <?php foreach ($quickGuides as $guideOption): ?>
                                    <option value="<?= (int) $guideOption['id'] ?>" <?= (int) $quickGuideId === (int) $guideOption['id'] ? 'selected' : '' ?>>
                                        <?= app_h(($guideOption['codigo'] ?: ('GUIA #' . $guideOption['id'])) . ' - restam ' . (int) $guideOption['restantes'] . ' - ' . (!empty($guideOption['autorizada']) ? 'Autorizada' : 'Nao autorizada')) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="quick-field">
                        <label for="quickStartDate">Data de inicio</label>
                        <input type="date" name="data_inicio" id="quickStartDate" class="form-control" value="<?= app_h($quickStartDate) ?>" required>
                    </div>
                    <div class="quick-field">
                        <label for="quickSessions">Quantidade</label>
                        <input type="number" name="quantidade_sessoes" id="quickSessions" class="form-control" value="<?= (int) $quickSessions ?>" min="1" max="<?= (int) $quickSessionsLimit ?>" required>
                    </div>
                    <div class="quick-field">
                        <label for="quickTime">Horario do grupo</label>
                        <input type="time" name="hora_inicio" id="quickTime" class="form-control" value="<?= app_h($quickTime) ?>" required>
                    </div>
                    <div class="quick-field full">
                        <span class="quick-days-label">Dias da semana</span>
                        <div class="quick-days">
                            <?php foreach (app_group_quick_weekday_labels() as $dayValue => $dayLabel): ?>
                                <label class="quick-day-option">
                                    <input type="checkbox" name="dias_semana[]" value="<?= (int) $dayValue ?>" <?= in_array((int) $dayValue, $quickWeekdays, true) ? 'checked' : '' ?>>
                                    <span><?= app_h($dayLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="quick-field full quick-actions">
                        <button class="btn btn-primary" type="submit">Gerar previa</button>
                    </div>
                </div>
            </form>
        </section>

        <?php if ($quickPatientId > 0 && $quickPatient): ?>
            <section class="quick-panel quick-patient-agenda">
                <div class="quick-preview-head">
                    <div class="quick-preview-title">
                        <h2>Agenda do paciente</h2>
                        <p><?= app_h((string) $quickPatient['nome']) ?> - proximos agendamentos ativos a partir de <?= app_h(app_date_br(date('Y-m-d'))) ?>.</p>
                    </div>
                    <?php if ($quickPatientScheduleRows !== []): ?>
                        <div class="quick-actions">
                            <span class="quick-count"><?= count($quickPatientScheduleRows) ?> encontrado(s)</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($quickPatientScheduleRows === []): ?>
                    <div class="quick-empty">
                        Nenhum agendamento futuro encontrado para este paciente. Voce pode montar novos horarios abaixo.
                    </div>
                <?php else: ?>
                    <div class="quick-patient-calendar">
                        <?php foreach ($quickPatientScheduleByMonth as $monthKey => $monthDays): ?>
                            <div class="quick-agenda-month">
                                <h3><?= app_h(app_month_label($monthKey)) ?></h3>
                                <div class="quick-scheduled-days">
                                    <?php foreach ($monthDays as $dateKey => $daySchedules): ?>
                                        <?php $dayTimestamp = strtotime((string) $dateKey); ?>
                                        <article class="quick-scheduled-day">
                                            <div class="quick-scheduled-day-head">
                                                <strong><?= app_h($dayTimestamp ? date('d', $dayTimestamp) : substr((string) $dateKey, -2)) ?></strong>
                                                <span><?= app_h(app_date_br((string) $dateKey)) ?></span>
                                            </div>
                                            <?php foreach ($daySchedules as $scheduleRow): ?>
                                                <?php
                                                $scheduleType = (string) ($scheduleRow['tipo_agenda'] ?? 'individual');
                                                $scheduleDate = (string) ($scheduleRow['data_agendamento'] ?? '');
                                                $scheduleStart = app_time_br((string) ($scheduleRow['hora_inicio'] ?? ''));
                                                $scheduleEnd = app_time_br((string) ($scheduleRow['hora_fim'] ?? ''));
                                                $scheduleStatus = (string) ($scheduleRow['status'] ?? '');
                                                $scheduleOpenUrl = $scheduleType === 'grupo'
                                                    ? 'secretaria_agenda_grupo.php?' . app_build_query([
                                                        'professional_id' => (int) ($scheduleRow['profissional_id'] ?? 0),
                                                        'service_id' => (int) ($scheduleRow['servico_id'] ?? 0),
                                                        'week_start' => app_week_start($scheduleDate),
                                                        'group_id' => (int) ($scheduleRow['grupo_id'] ?? 0),
                                                    ])
                                                    : 'secretaria_agenda.php?' . app_build_query([
                                                        'professional_id' => (int) ($scheduleRow['profissional_id'] ?? 0),
                                                        'week_start' => app_week_start($scheduleDate),
                                                        'appointment_id' => (int) ($scheduleRow['item_id'] ?? 0),
                                                    ]);
                                                ?>
                                                <a class="quick-calendar-event is-<?= app_h($scheduleType) ?>" href="<?= app_h($scheduleOpenUrl) ?>">
                                                    <strong><?= app_h($scheduleStart) ?> as <?= app_h($scheduleEnd) ?></strong>
                                                    <span><?= app_h((string) ($scheduleRow['servico_nome'] ?: 'Servico nao informado')) ?></span>
                                                    <small>
                                                        <?= $scheduleType === 'grupo' ? 'Grupo' : 'Individual' ?>
                                                        - <?= app_h((string) ($scheduleRow['profissional_nome'] ?: 'Profissional nao informado')) ?>
                                                        - <?= app_h($groupStatuses[$scheduleStatus] ?? ucfirst($scheduleStatus ?: 'sem status')) ?>
                                                    </small>
                                                </a>
                                            <?php endforeach; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($quickCanPreview || $quickConfirmed): ?>
            <div class="quick-preview-trigger">
                <div>
                    <strong>Previa dos agendamentos</strong>
                    <span>
                        <?= $quickRows !== []
                            ? count($quickRows) . ' sessao(oes) pronta(s) para revisar.'
                            : 'Veja os avisos da previa antes de confirmar.' ?>
                    </span>
                </div>
                <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#quickPreviewModal">Ver previa</button>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php if ($quickCanPreview || $quickConfirmed): ?>
<div class="modal fade quick-preview-modal" id="quickPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Previa dos agendamentos</h5>
                    <small class="text-muted">Calendario compacto somente com os dias em que o paciente vai vir.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($quickPreview['errors'])): ?>
                    <div class="alert alert-warning"><?= app_h(implode(' ', $quickPreview['errors'])) ?></div>
                <?php endif; ?>

                <?php if (!empty($quickPreview['errors']) && $quickSkippedRows !== []): ?>
                    <div class="quick-blocked">
                        <strong>Datas que nao entraram na previa</strong>
                        <ul>
                            <?php foreach (array_slice($quickSkippedRows, 0, 6) as $blockedRow): ?>
                                <?php $blockedReason = (string) ($blockedRow['reason'] ?? 'Nao foi possivel usar esta data.'); ?>
                                <li>
                                    <?= app_h(app_date_br((string) $blockedRow['date'])) ?>
                                    as <?= app_h((string) $blockedRow['time']) ?>:
                                    <?= app_h($blockedReason) ?>
                                    <?= app_h(app_group_quick_reason_action($blockedReason)) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($quickRows === []): ?>
                    <div class="quick-empty">
                        <?= !empty($quickPreview['errors'])
                            ? 'Nenhuma data entrou na previa. Veja os motivos acima e ajuste a liberacao, o horario, os dias da semana ou a quantidade.'
                            : 'Escolha o paciente, a guia, a data inicial e os dias da semana para gerar a previa.' ?>
                    </div>
                <?php else: ?>
                    <div class="quick-calendar">
                        <?php foreach ($quickRowsByMonth as $monthKey => $monthRows): ?>
                            <div class="quick-month">
                                <h3><?= app_h(app_month_label($monthKey)) ?></h3>
                                <div class="quick-day-grid">
                                    <?php foreach ($monthRows as $row): ?>
                                        <?php
                                        $timestamp = strtotime((string) $row['date']);
                                        $isCreate = ($row['action'] ?? '') === 'Criar grupo';
                                        ?>
                                        <article class="quick-day-card<?= $isCreate ? ' is-create' : '' ?>">
                                            <div class="quick-day-date">
                                                <div>
                                                    <strong><?= app_h(date('d', $timestamp)) ?></strong>
                                                    <span><?= app_h(app_date_br((string) $row['date'])) ?></span>
                                                </div>
                                                <span class="quick-session">Sessao <?= (int) $row['session'] ?></span>
                                            </div>
                                            <div class="quick-card-main">
                                                <strong><?= app_h((string) $row['time']) ?> as <?= app_h((string) $row['end_time']) ?></strong>
                                                <span><?= app_h(app_group_quick_weekday_labels()[(int) $row['weekday']] ?? '') ?> - <?= (int) $row['used'] ?>/<?= (int) $row['capacity'] ?> ocupadas</span>
                                            </div>
                                            <span class="quick-chip <?= $isCreate ? 'create' : 'existing' ?>"><?= app_h((string) $row['action']) ?></span>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <?php if ($quickConfirmed && $quickWhatsappUrl): ?>
                    <a class="btn btn-success" href="<?= app_h($quickWhatsappUrl) ?>" target="_blank" rel="noopener noreferrer">Enviar grade ao paciente</a>
                <?php elseif ($quickConfirmed): ?>
                    <button class="btn btn-outline-secondary" type="button" disabled>Paciente sem WhatsApp</button>
                <?php elseif ($quickRows !== [] && empty($quickPreview['errors'])): ?>
                    <form method="POST" class="m-0">
                        <input type="hidden" name="action" value="quick_schedule">
                        <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                        <input type="hidden" name="service_id" value="<?= (int) $selectedServiceId ?>">
                        <input type="hidden" name="week_start" value="<?= app_h($weekStart) ?>">
                        <input type="hidden" name="paciente_id" value="<?= (int) $quickPatientId ?>">
                        <input type="hidden" name="guia_id" value="<?= (int) $quickGuideId ?>">
                        <input type="hidden" name="data_inicio" value="<?= app_h($quickStartDate) ?>">
                        <input type="hidden" name="hora_inicio" value="<?= app_h($quickTime) ?>">
                        <input type="hidden" name="quantidade_sessoes" value="<?= (int) $quickSessions ?>">
                        <?php foreach ($quickWeekdays as $quickWeekday): ?>
                            <input type="hidden" name="dias_semana[]" value="<?= (int) $quickWeekday ?>">
                        <?php endforeach; ?>
                        <button class="btn btn-success" type="submit">Confirmar cronograma</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($quickFlash): ?>
<div class="alert alert-<?= app_h($quickFlash['type']) ?> quick-inline-notice"><?= app_h($quickFlash['message']) ?></div>
<?php endif; ?>

<script>
const quickPatientInput = document.getElementById('quickPatientSearch');
const quickPatientId = document.getElementById('quickPatientId');
const quickPatientMenu = document.getElementById('quickPatientMenu');
const quickGuide = document.getElementById('quickGuide');
const shouldOpenQuickPreviewModal = <?= ($quickCanPreview || $quickConfirmed) ? 'true' : 'false' ?>;
let quickPatientController = null;

function closeQuickPatientMenu() {
    if (!quickPatientMenu) return;
    quickPatientMenu.classList.remove('is-open');
    quickPatientMenu.innerHTML = '';
}

function openQuickPatientAgenda(patientId) {
    const form = document.getElementById('quickScheduleForm');
    const url = new URL(window.location.origin + window.location.pathname);

    url.searchParams.set('modelo', 'rapido');
    url.searchParams.set('paciente_id', patientId);

    ['professional_id', 'service_id', 'week_start', 'data_inicio', 'quantidade_sessoes', 'hora_inicio'].forEach((name) => {
        const field = form?.querySelector(`[name="${name}"]`);
        if (field?.value) {
            url.searchParams.set(name, field.value);
        }
    });

    form?.querySelectorAll('input[name="dias_semana[]"]:checked').forEach((checkbox) => {
        url.searchParams.append('dias_semana[]', checkbox.value);
    });

    window.location.href = url.toString();
}

if (quickPatientInput && quickPatientId && quickPatientMenu) {
    quickPatientInput.addEventListener('input', () => {
        quickPatientId.value = '';
        if (quickGuide) {
            quickGuide.innerHTML = '<option value="">Selecione o paciente</option>';
        }
        const term = quickPatientInput.value.trim();

        if (term.length < 2) {
            closeQuickPatientMenu();
            return;
        }

        quickPatientController?.abort();
        quickPatientController = new AbortController();
        fetch('pacientes_busca.php?q=' + encodeURIComponent(term), { signal: quickPatientController.signal })
            .then((response) => response.json())
            .then((data) => {
                quickPatientMenu.innerHTML = '';
                (data.pacientes || []).forEach((patient) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'quick-autocomplete-option';
                    button.textContent = patient.nome;
                    button.addEventListener('click', () => {
                        quickPatientId.value = patient.id;
                        quickPatientInput.value = patient.nome;
                        closeQuickPatientMenu();
                        openQuickPatientAgenda(patient.id);
                    });
                    quickPatientMenu.appendChild(button);
                });
                quickPatientMenu.classList.toggle('is-open', quickPatientMenu.children.length > 0);
            })
            .catch(() => {});
    });

    document.addEventListener('click', (event) => {
        if (!quickPatientMenu.contains(event.target) && event.target !== quickPatientInput) {
            closeQuickPatientMenu();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const quickPreviewModal = document.getElementById('quickPreviewModal');

    if (shouldOpenQuickPreviewModal && quickPreviewModal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(quickPreviewModal).show();
    }
});
</script>
</body>
</html>
    <?php
    exit;
}

$weekDays = app_week_days($weekStart);
$groupRows = [];
$groupsBySlot = [];
$groupMembers = [];

if ($selectedProfessionalId > 0 && $selectedService) {
    $groupRows = app_stmt_all(
        $conn,
        'SELECT g.*,
                COALESCE(SUM(CASE WHEN gp.status <> ? THEN 1 ELSE 0 END), 0) AS pacientes,
                SUM(CASE WHEN gp.status = ? THEN 1 ELSE 0 END) AS realizados
         FROM agenda_grupos g
         LEFT JOIN agenda_grupo_pacientes gp ON gp.grupo_id = g.id AND gp.clinica_id = g.clinica_id
         WHERE g.clinica_id = ? AND g.profissional_id = ? AND g.servico_id = ?
           AND g.data_agendamento BETWEEN ? AND ?
         GROUP BY g.id
         ORDER BY g.data_agendamento, g.hora_inicio',
        'ssiiiss',
        ['cancelado', 'realizado', $clinicId, $selectedProfessionalId, $selectedServiceId, $weekStart, $weekEnd]
    );

    foreach ($groupRows as $group) {
        $groupsBySlot[$group['data_agendamento'] . ' ' . substr((string) $group['hora_inicio'], 0, 5)] = $group;
    }
}

$openGroup = null;
if ($openGroupId > 0) {
    $openGroup = app_stmt_one(
        $conn,
        'SELECT g.*, p.nome AS profissional_nome, s.nome AS servico_nome
         FROM agenda_grupos g
         INNER JOIN profissionais p ON p.id = g.profissional_id AND p.clinica_id = g.clinica_id
         INNER JOIN servicos s ON s.id = g.servico_id AND s.clinica_id = g.clinica_id
         WHERE g.clinica_id = ? AND g.id = ?
         LIMIT 1',
        'ii',
        [$clinicId, $openGroupId]
    );
}

if ($openGroup) {
    $groupMembers = app_stmt_all(
        $conn,
        'SELECT gp.*, pa.nome AS paciente_nome, pa.telefone AS paciente_telefone, COALESCE(gu.codigo, ?) AS guia_codigo, COALESCE(gu.autorizada, 0) AS guia_autorizada
         FROM agenda_grupo_pacientes gp
         INNER JOIN pacientes pa ON pa.id = gp.paciente_id AND pa.clinica_id = gp.clinica_id
         LEFT JOIN guias gu ON gu.id = gp.guia_id AND gu.clinica_id = gp.clinica_id
         WHERE gp.clinica_id = ? AND gp.grupo_id = ?
         ORDER BY pa.nome',
        'sii',
        ['', $clinicId, (int) $openGroup['id']]
    );
}

$hours = [];
for ($minutes = 7 * 60; $minutes <= 18 * 60; $minutes += 60) {
    $hours[] = app_group_minutes_to_time($minutes);
}

$calendarPrevUrl = 'secretaria_agenda_grupo.php?' . app_build_query([
    'professional_id' => $selectedProfessionalId,
    'service_id' => $selectedServiceId,
    'week_start' => date('Y-m-d', strtotime($weekStart . ' -7 days')),
]);
$calendarNextUrl = 'secretaria_agenda_grupo.php?' . app_build_query([
    'professional_id' => $selectedProfessionalId,
    'service_id' => $selectedServiceId,
    'week_start' => date('Y-m-d', strtotime($weekStart . ' +7 days')),
]);
$calendarResetUrl = 'secretaria_agenda_grupo.php?' . app_build_query([
    'professional_id' => $selectedProfessionalId,
    'service_id' => $selectedServiceId,
    'week_start' => app_week_start(date('Y-m-d')),
]);
$menuFlashMode = 'manual';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Agenda em Grupo</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<link href="assets/schedule-page.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(180deg, #f6fafb 0%, #eef4f6 100%);
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.group-shell {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 0.55rem;
    min-height: 0;
    padding: 0.52rem 0.72rem 0.72rem;
}
.group-topbar {
    align-items: flex-end;
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
    border-radius: 18px;
    box-shadow: 0 18px 36px rgba(18, 51, 62, 0.12);
    color: #fff;
    display: flex;
    flex: 0 0 auto;
    gap: 1rem;
    justify-content: space-between;
    padding: 0.62rem 0.82rem;
}
.group-kicker {
    font-size: 0.64rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    margin: 0 0 0.12rem;
    opacity: 0.78;
    text-transform: uppercase;
}
.group-topbar h3 {
    font-size: 1.12rem;
    margin: 0;
}
.group-topbar p {
    color: rgba(255, 255, 255, 0.82);
    font-size: 0.74rem;
    line-height: 1.18;
    margin: 0.16rem 0 0;
}
.group-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 0.42rem;
    justify-content: flex-end;
}
.group-actions .btn {
    border-radius: 999px;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.34rem 0.68rem;
}
.group-filter-card,
.group-calendar-card {
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 18px;
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.07);
}
.group-calendar-card {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.group-calendar-card .card-header {
    flex: 0 0 auto;
    padding: 0.58rem 0.72rem;
}
.group-calendar-card .card-body {
    display: flex;
    flex: 1;
    min-height: 0;
    padding: 0.58rem;
}
.group-filter-card .form-control,
.group-filter-card .form-select,
.group-modal .form-control,
.group-modal .form-select {
    border-color: #dbe7ec;
    border-radius: 12px;
    font-size: 0.78rem;
    min-height: 36px;
}
.group-grid {
    border: 1px solid #dbe7ec;
    border-radius: 16px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
    display: grid;
    flex: 1;
    grid-template-columns: 64px repeat(7, minmax(0, 1fr));
    min-height: 100%;
    overflow: hidden;
}
.group-head,
.group-hour,
.group-cell {
    border-bottom: 1px solid #e5eef2;
    border-right: 1px solid #e5eef2;
    min-height: 52px;
    padding: 0.3rem;
}
.group-head {
    background: #f5fafb;
    color: #16333f;
    font-size: 0.72rem;
    font-weight: 800;
    min-height: 42px;
    text-align: center;
}
.group-hour {
    align-items: center;
    background: #f5fafb;
    display: flex;
    font-size: 0.72rem;
    font-weight: 800;
    justify-content: center;
}
.group-cell {
    background: #fff;
}
.group-calendar-scroll {
    display: flex;
    flex: 1;
    min-height: 0;
    overflow: auto;
    width: 100%;
}
.group-slot {
    align-items: flex-start;
    border: 1px dashed #c7dce4;
    border-radius: 10px;
    color: #54707d;
    display: flex;
    flex-direction: column;
    font-size: 0.7rem;
    gap: 0.16rem;
    min-height: 42px;
    padding: 0.32rem;
    text-decoration: none;
    transition: transform 0.12s ease, box-shadow 0.12s ease, border-color 0.12s ease;
}
.group-slot:hover {
    border-color: #39a77b;
    box-shadow: 0 10px 20px rgba(24, 56, 69, 0.12);
    transform: translateY(-1px);
}
.group-slot.has-group {
    background: linear-gradient(135deg, #ecfbf3, #eef9fb);
    border-color: #39a77b;
    box-shadow: 0 8px 18px rgba(39, 174, 96, 0.12);
    color: #16333f;
}
.group-slot.is-full {
    background: linear-gradient(135deg, #fff1f1, #fff8f2);
    border-color: #e49a9a;
}
.group-slot.is-disabled {
    background: #f1f5f6;
    border-color: #d7e2e6;
    color: #8da1aa;
    cursor: not-allowed;
    pointer-events: none;
}
.group-slot strong {
    font-size: 0.72rem;
    line-height: 1.05;
}
.group-slot small {
    color: #68828f;
    font-size: 0.64rem;
    line-height: 1;
}
.group-count {
    border-radius: 999px;
    display: inline-flex;
    font-size: 0.64rem;
    font-weight: 800;
    padding: 0.12rem 0.42rem;
}
.group-count.ok {
    background: #d9f2e3;
    color: #176b3c;
}
.group-count.full {
    background: #ffd7d7;
    color: #9d1b1b;
}
.autocomplete-wrap {
    position: relative;
}
.autocomplete-menu {
    background: #fff;
    border: 1px solid #dbe7ec;
    border-radius: 14px;
    box-shadow: 0 18px 34px rgba(22, 51, 63, 0.16);
    display: none;
    left: 0;
    max-height: 220px;
    overflow: auto;
    position: absolute;
    right: 0;
    top: calc(100% + 4px);
    z-index: 1060;
}
.autocomplete-menu.is-open {
    display: block;
}
.autocomplete-option {
    background: transparent;
    border: 0;
    color: #16333f;
    font-size: 0.78rem;
    padding: 0.48rem 0.68rem;
    text-align: left;
    width: 100%;
}
.autocomplete-option:hover,
.autocomplete-option:focus {
    background: rgba(31, 122, 140, 0.1);
    outline: none;
}
.group-member-list {
    display: grid;
    gap: 0.45rem;
}
.group-member {
    align-items: center;
    border: 1px solid #e0ebef;
    border-radius: 12px;
    display: grid;
    gap: 0.4rem;
    grid-template-columns: minmax(180px, 1fr) minmax(280px, 1.2fr);
    padding: 0.5rem;
}
.group-member strong {
    font-size: 0.82rem;
}
.group-member small {
    color: #68828f;
    display: block;
    font-size: 0.7rem;
}
.group-member-actions,
.group-status-form {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    justify-content: flex-end;
}
.group-status-form .form-select {
    min-height: 30px;
    min-width: 132px;
}
.group-guide-field .form-select {
    min-width: 190px;
}
.group-modal .modal-content {
    border: 0;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 26px 56px rgba(22, 51, 63, 0.26);
}
.group-modal .modal-dialog {
    max-width: 1180px;
}
.group-modal .modal-header {
    align-items: flex-start;
    background: linear-gradient(135deg, #0d3f4d 0%, #1f7a8c 62%, #39a77b 100%);
    border-bottom: 0;
    color: #fff;
    padding: 0.72rem 0.9rem;
}
.group-modal .modal-body {
    background: linear-gradient(180deg, #f6fafb 0%, #eef6f7 100%);
    padding: 0.72rem 0.82rem 0.86rem;
}
.group-modal .btn-close {
    filter: invert(1) grayscale(1);
    opacity: 0.8;
}
.group-modal-kicker {
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    margin: 0 0 0.08rem;
    opacity: 0.78;
    text-transform: uppercase;
}
.group-modal .modal-title {
    font-size: 1.08rem;
    line-height: 1.12;
}
.group-modal-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.28rem;
    margin-top: 0.28rem;
}
.group-modal-meta span {
    background: rgba(255, 255, 255, 0.16);
    border-radius: 999px;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.18rem 0.46rem;
}
.group-modal-dashboard {
    align-items: center;
    background: #fff;
    border: 1px solid rgba(18, 73, 88, 0.08);
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(24, 56, 69, 0.08);
    display: grid;
    gap: 0.58rem;
    grid-template-columns: minmax(230px, 1.2fr) 120px 1fr repeat(2, minmax(76px, 0.35fr));
    margin-bottom: 0.58rem;
    padding: 0.58rem 0.68rem;
}
.group-professional-highlight {
    align-items: center;
    background: linear-gradient(135deg, #0f4c5c, #1f7a8c);
    border-radius: 12px;
    color: #fff;
    display: flex;
    gap: 0.58rem;
    min-width: 0;
    padding: 0.52rem 0.62rem;
}
.group-professional-highlight span {
    color: rgba(255, 255, 255, 0.76);
    display: block;
    font-size: 0.58rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.group-professional-highlight strong {
    display: block;
    font-size: 0.86rem;
    line-height: 1.08;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.group-professional-highlight small {
    color: rgba(255, 255, 255, 0.74);
    display: block;
    font-size: 0.66rem;
}
.group-professional-highlight .btn {
    border-radius: 999px;
    flex: 0 0 auto;
    font-size: 0.68rem;
    font-weight: 800;
    min-height: 30px;
    padding: 0.18rem 0.58rem;
}
.group-professional-highlight .btn.disabled {
    opacity: 0.5;
    pointer-events: none;
}
.group-occupancy-label span {
    color: #68828f;
    display: block;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
}
.group-occupancy-label strong {
    color: #143b49;
    display: block;
    font-size: 1.18rem;
    line-height: 1;
}
.group-progress-track {
    background: #e7f1f3;
    border-radius: 999px;
    height: 12px;
    overflow: hidden;
    position: relative;
}
.group-progress-track span {
    background: linear-gradient(90deg, #39a77b, #1f7a8c);
    border-radius: inherit;
    display: block;
    height: 100%;
    min-width: 8px;
}
.group-quick-stat {
    border-left: 1px solid rgba(18, 73, 88, 0.08);
    padding-left: 0.58rem;
}
.group-quick-stat span {
    color: #68828f;
    display: block;
    font-size: 0.62rem;
    font-weight: 800;
    text-transform: uppercase;
}
.group-quick-stat strong {
    color: #143b49;
    display: block;
    font-size: 0.98rem;
    line-height: 1.05;
}
.group-add-band {
    align-items: end;
    background: linear-gradient(135deg, #ffffff, #f4fbf8);
    border: 1px solid rgba(57, 167, 123, 0.18);
    border-radius: 14px;
    display: grid;
    gap: 0.42rem;
    grid-template-columns: minmax(240px, 1.1fr) minmax(190px, 0.85fr) minmax(180px, 0.75fr) auto;
    margin-bottom: 0.58rem;
    padding: 0.58rem;
}
.group-add-band .form-label {
    margin-bottom: 0.16rem;
}
.group-add-band .form-control,
.group-add-band .form-select,
.group-add-band .btn {
    min-height: 34px;
    font-size: 0.78rem;
}
.group-roster-head {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.42rem;
}
.group-roster-head strong,
.group-roster-head small {
    display: block;
}
.group-roster-head small {
    color: #68828f;
    font-size: 0.72rem;
}
.group-roster-grid {
    display: grid;
    gap: 0.42rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.group-seat-card {
    --status-start: #8fb9c5;
    --status-end: #1f7a8c;
    --status-shadow: rgba(31, 122, 140, 0.18);
    background: linear-gradient(180deg, #ffffff, #fbfdfe);
    border: 1px solid #dfeaf0;
    border-left: 4px solid var(--status-start);
    border-radius: 14px;
    box-shadow: 0 10px 22px rgba(24, 56, 69, 0.07);
    display: grid;
    gap: 0.38rem;
    min-height: 124px;
    padding: 0.52rem;
    position: relative;
}
.group-seat-card.status-agendado {
    --status-start: #b76a14;
    --status-end: #d88d1f;
    --status-shadow: rgba(184, 106, 20, 0.2);
}
.group-seat-card.status-confirmado {
    --status-start: #1862a8;
    --status-end: #2680c2;
    --status-shadow: rgba(24, 98, 168, 0.2);
}
.group-seat-card.status-realizado {
    --status-start: #17765d;
    --status-end: #1f9d6d;
    --status-shadow: rgba(23, 118, 93, 0.2);
}
.group-seat-card.status-cancelado {
    --status-start: #a22c39;
    --status-end: #d84e56;
    --status-shadow: rgba(162, 44, 57, 0.2);
    background: #fff7f7;
}
.group-seat-card.is-empty {
    background: repeating-linear-gradient(-45deg, #fbfdfe, #fbfdfe 9px, #eef5f7 9px, #eef5f7 18px);
    border-left-color: #c8d9df;
    color: #6b8591;
    min-height: 96px;
}
.group-seat-top {
    align-items: center;
    display: flex;
    justify-content: space-between;
}
.group-seat-top span {
    background: #edf6f7;
    border-radius: 999px;
    color: #315766;
    font-size: 0.62rem;
    font-weight: 800;
    padding: 0.14rem 0.38rem;
    text-transform: uppercase;
}
.group-seat-top strong {
    background: linear-gradient(180deg, var(--status-start) 0%, var(--status-end) 100%);
    border-radius: 999px;
    box-shadow: 0 8px 18px var(--status-shadow);
    color: #fff;
    font-size: 0.64rem;
    padding: 0.14rem 0.42rem;
}
.group-seat-patient strong,
.group-empty-slot strong {
    color: #16333f;
    display: block;
    font-size: 0.86rem;
    line-height: 1.08;
}
.group-seat-patient small,
.group-empty-slot small {
    color: #68828f;
    display: block;
    font-size: 0.68rem;
    line-height: 1.08;
    margin-top: 0.08rem;
}
.group-seat-card .group-status-form {
    align-items: stretch;
    display: grid;
    gap: 0.3rem;
    grid-template-columns: minmax(118px, 0.72fr) minmax(148px, 1fr) auto;
}
.group-seat-card .group-guide-field[hidden] {
    display: none;
}
.group-seat-card .group-guide-field .form-select,
.group-seat-card .group-status-form .form-select {
    font-size: 0.72rem;
    min-height: 30px;
    min-width: 0;
    width: 100%;
}
.group-seat-actions .btn,
.group-remove-form .btn,
.group-seat-footer .btn {
    border-radius: 999px;
    font-size: 0.68rem;
    min-height: 30px;
    padding: 0.18rem 0.52rem;
}
.group-seat-footer {
    align-items: center;
    display: flex;
    gap: 0.3rem;
    justify-content: space-between;
    margin-top: -0.12rem;
}
.group-seat-footer .btn.disabled {
    opacity: 0.5;
    pointer-events: none;
}
.group-remove-form {
    display: flex;
    margin: 0;
}
body.is-capturing-group-modal .group-capture-hide {
    display: none !important;
}
.group-inline-notice {
    bottom: 1rem;
    left: 50%;
    max-width: min(520px, calc(100vw - 2rem));
    position: fixed;
    transform: translateX(-50%);
    z-index: 1080;
}
@media (max-width: 991px) {
    .group-topbar {
        align-items: stretch;
        flex-direction: column;
    }
    .group-grid {
        min-width: 920px;
    }
    .group-add-band,
    .group-roster-grid,
    .group-modal-dashboard,
    .group-seat-card .group-status-form {
        grid-template-columns: 1fr;
    }
    .group-quick-stat {
        border-left: 0;
        border-top: 1px solid rgba(18, 73, 88, 0.08);
        padding-left: 0;
        padding-top: 0.4rem;
    }
    .group-professional-highlight {
        align-items: flex-start;
        flex-direction: column;
    }
    .group-member {
        grid-template-columns: 1fr;
    }
    .group-member-actions,
    .group-status-form {
        justify-content: flex-start;
    }
.group-calendar-scroll {
        overflow: auto;
    }
}
</style>
</head>
<body>
<?php include 'partials/menu.php'; ?>
<?php $groupFlash = $flash ?? null; ?>

<div class="container-fluid group-shell">
    <section class="group-topbar mb-3">
        <div>
            <p class="group-kicker">Agenda em grupo</p>
            <h3>Agenda em Grupo</h3>
            <p>Controle horarios com varios pacientes; agendamento exige guia e Realizado exige guia autorizada.</p>
        </div>
        <div class="group-actions">
            <a class="btn btn-outline-light" href="<?= app_h($calendarResetUrl) ?>">Semana atual</a>
            <a class="btn btn-outline-light" href="secretaria_agenda.php?<?= app_h(app_build_query(['professional_id' => $selectedProfessionalId, 'week_start' => $weekStart])) ?>">Agenda individual</a>
        </div>
    </section>

    <div class="card group-calendar-card">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div>
                <h5 class="mb-0">
                    <?= app_h((string) ($selectedProfessional['nome'] ?? 'Profissional')) ?>
                    <?php if ($selectedService): ?>
                        - <?= app_h((string) $selectedService['nome']) ?>
                    <?php endif; ?>
                </h5>
                <small class="text-muted"><?= app_h(app_date_br($weekStart)) ?> a <?= app_h(app_date_br($weekEnd)) ?></small>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="<?= app_h($calendarPrevUrl) ?>">&lt;</a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= app_h($calendarNextUrl) ?>">&gt;</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (!$selectedService): ?>
                <div class="alert alert-warning mb-0">Este profissional ainda nao tem servico de grupo vinculado.</div>
            <?php else: ?>
                <div class="group-calendar-scroll">
                    <div class="group-grid">
                        <div class="group-head">Hora</div>
                        <?php foreach ($weekDays as $day): ?>
                            <div class="group-head">
                                <div><?= app_h($day['label']) ?></div>
                                <strong><?= app_h(app_date_br($day['date'])) ?></strong>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($hours as $hour): ?>
                            <div class="group-hour"><?= app_h($hour) ?></div>
                            <?php foreach ($weekDays as $day): ?>
                                <?php
                                $slotKey = $day['date'] . ' ' . $hour;
                                $group = $groupsBySlot[$slotKey] ?? null;
                                $used = $group ? (int) ($group['pacientes'] ?? 0) : 0;
                                $capacity = $group ? (int) ($group['capacidade'] ?? 1) : (int) ($selectedService['capacidade_agendamento'] ?? 1);
                                $isFull = $used >= $capacity && $capacity > 0;
                                $isPastSlot = $day['date'] < date('Y-m-d');
                                $slotUrl = !$group && $isPastSlot
                                    ? '#'
                                    : ($group
                                    ? 'secretaria_agenda_grupo.php?' . app_build_query(['professional_id' => $selectedProfessionalId, 'service_id' => $selectedServiceId, 'week_start' => $weekStart, 'group_id' => (int) $group['id']])
                                    : 'secretaria_agenda_grupo.php?' . app_build_query(['professional_id' => $selectedProfessionalId, 'service_id' => $selectedServiceId, 'week_start' => $weekStart, 'slot_date' => $day['date'], 'slot_time' => $hour]));
                                ?>
                                <div class="group-cell">
                                    <a class="group-slot<?= $group ? ' has-group' : '' ?><?= $isFull ? ' is-full' : '' ?><?= !$group && $isPastSlot ? ' is-disabled' : '' ?>" href="<?= app_h($slotUrl) ?>">
                                        <?php if ($group): ?>
                                            <strong><?= app_h((string) $selectedService['nome']) ?></strong>
                                            <span class="group-count <?= $isFull ? 'full' : 'ok' ?>"><?= $used ?>/<?= $capacity ?> pacientes</span>
                                            <small><?= (int) ($group['realizados'] ?? 0) ?> realizados</small>
                                        <?php elseif ($isPastSlot): ?>
                                            <span>Data anterior</span>
                                            <small>Bloqueado</small>
                                        <?php else: ?>
                                            <span>Novo grupo</span>
                                            <small><?= app_h($hour) ?></small>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade agenda-filter-modal" id="calendarFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filtros da agenda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <form method="GET" class="agenda-toolbar-form" id="calendarFilterModalForm">
                    <div class="agenda-toolbar-field">
                        <label for="modalCalendarProfessional">Profissional</label>
                        <select name="professional_id" id="modalCalendarProfessional" class="form-select">
                            <?php foreach ($professionals as $professional): ?>
                                <option value="<?= (int) $professional['id'] ?>" <?= (int) $selectedProfessionalId === (int) $professional['id'] ? 'selected' : '' ?>>
                                    <?= app_h((string) $professional['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="agenda-toolbar-field" id="modalGroupServiceField" <?= $showGroupServiceSelect ? '' : 'style="display:none;"' ?>>
                        <label for="modalCalendarService">Servico</label>
                        <select name="service_id" id="modalCalendarService" class="form-select">
                            <?php foreach ($groupServices as $service): ?>
                                <option value="<?= (int) $service['id'] ?>" data-tipo="<?= app_h((string) ($service['tipo_agendamento'] ?? 'individual')) ?>" <?= (int) $selectedServiceId === (int) $service['id'] ? 'selected' : '' ?>>
                                    <?= app_h((string) $service['nome']) ?> - <?= ($service['tipo_agendamento'] ?? 'individual') === 'grupo' ? 'Grupo' : 'Individual' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="agenda-toolbar-field">
                        <label for="modalCalendarWeekStart">Semana</label>
                        <input type="date" name="week_start" id="modalCalendarWeekStart" class="form-control" value="<?= app_h($weekStart) ?>">
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Aplicar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedService && ($openGroup || ($openSlotDate !== '' && $openSlotTime !== ''))): ?>
<?php
$modalCapacity = max(1, (int) ($openGroup['capacidade'] ?? $selectedService['capacidade_agendamento'] ?? 1));
$modalUsed = count(array_filter($groupMembers, static fn (array $member): bool => ($member['status'] ?? '') !== 'cancelado'));
$modalFree = max(0, $modalCapacity - $modalUsed);
$modalPercent = min(100, max(0, (int) round(($modalUsed / $modalCapacity) * 100)));
$modalDate = (string) ($openGroup['data_agendamento'] ?? $openSlotDate);
$modalTime = app_time_br((string) ($openGroup['hora_inicio'] ?? $openSlotTime));
$modalProfessionalName = (string) ($selectedProfessional['nome'] ?? 'Profissional');
$modalServiceName = (string) ($selectedService['nome'] ?? 'Servico');
$professionalWhatsappPhone = app_normalize_phone((string) ($selectedProfessional['telefone'] ?? ''));
?>
<div class="modal fade group-modal" id="groupSlotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <p class="group-modal-kicker">Sessao em grupo</p>
                    <h5 class="modal-title"><?= app_h($modalTime) ?> - <?= app_h(app_date_br($modalDate)) ?></h5>
                    <div class="group-modal-meta">
                        <span><?= app_h((string) ($selectedProfessional['nome'] ?? 'Profissional')) ?></span>
                        <span><?= app_h((string) $selectedService['nome']) ?></span>
                        <span><?= $modalUsed ?>/<?= $modalCapacity ?> vagas</span>
                    </div>
                </div>
                <a class="btn-close group-capture-hide" href="secretaria_agenda_grupo.php?<?= app_h(app_build_query(['professional_id' => $selectedProfessionalId, 'service_id' => $selectedServiceId, 'week_start' => $weekStart])) ?>" aria-label="Fechar"></a>
            </div>
            <div class="modal-body">
                <div class="group-modal-dashboard">
                    <div class="group-professional-highlight">
                        <div>
                            <span>Profissional</span>
                            <strong><?= app_h($modalProfessionalName) ?></strong>
                            <small><?= trim((string) ($selectedProfessional['telefone'] ?? '')) !== '' ? app_h((string) $selectedProfessional['telefone']) : 'Telefone nao informado' ?></small>
                        </div>
                        <button
                            type="button"
                            class="btn btn-light group-capture-hide<?= $professionalWhatsappPhone !== '' ? '' : ' disabled' ?>"
                            id="groupProfessionalWhatsappBtn"
                            data-whatsapp-phone="<?= app_h($professionalWhatsappPhone) ?>"
                            title="<?= $professionalWhatsappPhone !== '' ? 'Enviar print do modal para o profissional' : 'Profissional sem telefone cadastrado' ?>"
                            aria-disabled="<?= $professionalWhatsappPhone !== '' ? 'false' : 'true' ?>"
                        >Enviar print</button>
                    </div>
                    <div class="group-occupancy-label">
                        <span>Ocupacao</span>
                        <strong><?= $modalPercent ?>%</strong>
                    </div>
                    <div class="group-progress-track" aria-label="Ocupacao do horario">
                        <span style="width: <?= $modalPercent ?>%;"></span>
                    </div>
                    <div class="group-quick-stat">
                        <span>Ocupadas</span>
                        <strong><?= $modalUsed ?></strong>
                    </div>
                    <div class="group-quick-stat">
                        <span>Livres</span>
                        <strong><?= $modalFree ?></strong>
                    </div>
                </div>

                <form method="POST" class="group-add-band group-capture-hide" id="addGroupPatientForm">
                    <input type="hidden" name="action" value="add_patient">
                    <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                    <input type="hidden" name="service_id" value="<?= (int) $selectedServiceId ?>">
                    <input type="hidden" name="week_start" value="<?= app_h($weekStart) ?>">
                    <input type="hidden" name="data_agendamento" value="<?= app_h($modalDate) ?>">
                    <input type="hidden" name="hora_inicio" value="<?= app_h(substr((string) ($openGroup['hora_inicio'] ?? $openSlotTime), 0, 5)) ?>">
                    <input type="hidden" name="paciente_id" id="groupPatientId" value="">

                    <div class="group-add-main">
                        <label class="form-label small text-muted">Adicionar paciente</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="groupPatientSearch" class="form-control" autocomplete="off" required placeholder="Digite o nome do paciente">
                            <div class="autocomplete-menu" id="groupPatientMenu"></div>
                        </div>
                    </div>
                    <div class="group-add-guide">
                        <label class="form-label small text-muted" for="groupPatientGuide">Guia</label>
                        <select name="guia_id" id="groupPatientGuide" class="form-select" required>
                            <option value="">Selecione o paciente</option>
                        </select>
                    </div>
                    <div class="group-add-notes">
                        <label class="form-label small text-muted">Observacoes</label>
                        <input type="text" name="observacoes" class="form-control" placeholder="Opcional">
                    </div>
                    <button class="btn btn-primary">Adicionar</button>
                </form>

                <div class="group-roster-head">
                    <div>
                        <strong>Vagas do horario</strong>
                        <small><?= $modalUsed ?> ocupada(s), <?= $modalFree ?> livre(s)</small>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary group-capture-hide" href="secretaria_agenda_grupo.php?<?= app_h(app_build_query(['professional_id' => $selectedProfessionalId, 'service_id' => $selectedServiceId, 'week_start' => $weekStart])) ?>">Fechar</a>
                </div>

                <div class="group-roster-grid">
                    <?php for ($seatIndex = 0; $seatIndex < $modalCapacity; $seatIndex++): ?>
                        <?php $member = $groupMembers[$seatIndex] ?? null; ?>
                        <?php if ($member): ?>
                            <?php
                            $memberStatus = (string) ($member['status'] ?? 'agendado');
                            $memberGuideOptions = app_group_authorized_guides(
                                $conn,
                                $clinicId,
                                (int) $member['paciente_id'],
                                $selectedProfessionalId,
                                (int) ($member['atendimento_id'] ?? 0),
                                (int) $member['id'],
                                $selectedServiceId
                            );
                            $patientWhatsappUrl = app_group_patient_whatsapp_link($member, $modalDate, $modalTime, $modalProfessionalName, $modalServiceName);
                            ?>
                            <article class="group-seat-card status-<?= app_h($memberStatus) ?>">
                                <div class="group-seat-top">
                                    <span>Vaga <?= $seatIndex + 1 ?></span>
                                    <strong><?= app_h($groupStatuses[$memberStatus] ?? ucfirst($memberStatus)) ?></strong>
                                </div>
                                <div class="group-seat-patient">
                                    <strong><?= app_h((string) $member['paciente_nome']) ?></strong>
                                    <small><?= $member['guia_codigo'] !== '' ? 'Guia ' . app_h((string) $member['guia_codigo']) . (!empty($member['guia_autorizada']) ? ' autorizada' : ' nao autorizada') : 'Sem guia no agendamento' ?></small>
                                </div>
                                <form method="POST" class="group-status-form">
                                    <input type="hidden" name="action" value="update_member_status">
                                    <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                                    <input type="hidden" name="service_id" value="<?= (int) $selectedServiceId ?>">
                                    <input type="hidden" name="week_start" value="<?= app_h($weekStart) ?>">
                                    <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
                                    <select name="status" class="form-select form-select-sm group-status-select" title="Mude o status do paciente neste horario. Guia sera exigida apenas para Realizado.">
                                        <?php foreach ($groupStatuses as $statusValue => $statusLabel): ?>
                                            <option value="<?= app_h($statusValue) ?>" <?= $memberStatus === $statusValue ? 'selected' : '' ?>>
                                                <?= app_h($statusLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="group-guide-field" <?= $memberStatus === 'realizado' ? '' : 'hidden' ?>>
                                        <select name="guia_id" class="form-select form-select-sm group-guide-select" title="Guia autorizada obrigatoria quando o status for Realizado.">
                                            <option value="">Guia autorizada</option>
                                            <?php foreach ($memberGuideOptions as $guideOption): ?>
                                                <option value="<?= (int) $guideOption['id'] ?>" <?= (int) ($member['guia_id'] ?? 0) === (int) $guideOption['id'] ? 'selected' : '' ?>>
                                                    <?= app_h(($guideOption['codigo'] ?: ('GUIA #' . $guideOption['id'])) . ' - restam ' . (int) $guideOption['restantes']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="group-seat-actions">
                                        <button class="btn btn-sm btn-primary">Salvar</button>
                                    </div>
                                </form>
                                <div class="group-seat-footer group-capture-hide">
                                    <?php if ($memberStatus !== 'realizado'): ?>
                                        <form method="POST" class="group-remove-form" onsubmit="return confirm('Remover paciente do grupo?')">
                                            <input type="hidden" name="action" value="remove_member">
                                            <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                                            <input type="hidden" name="service_id" value="<?= (int) $selectedServiceId ?>">
                                            <input type="hidden" name="week_start" value="<?= app_h($weekStart) ?>">
                                            <input type="hidden" name="member_id" value="<?= (int) $member['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Remover</button>
                                        </form>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>
                                    <a
                                        class="btn btn-sm btn-outline-success<?= $patientWhatsappUrl ? '' : ' disabled' ?>"
                                        href="<?= app_h($patientWhatsappUrl ?: '#') ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        title="<?= $patientWhatsappUrl ? 'Enviar confirmacao para o WhatsApp do paciente' : 'Paciente sem telefone cadastrado' ?>"
                                        aria-disabled="<?= $patientWhatsappUrl ? 'false' : 'true' ?>"
                                    >WhatsApp</a>
                                </div>
                            </article>
                        <?php else: ?>
                            <article class="group-seat-card is-empty">
                                <div class="group-seat-top">
                                    <span>Vaga <?= $seatIndex + 1 ?></span>
                                    <strong>Livre</strong>
                                </div>
                                <div class="group-empty-slot">
                                    <strong>Disponivel</strong>
                                    <small>Use o campo acima para incluir um paciente neste horario.</small>
                                </div>
                            </article>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($groupFlash): ?>
<div class="alert alert-<?= app_h($groupFlash['type']) ?> group-inline-notice"><?= app_h($groupFlash['message']) ?></div>
<?php endif; ?>

<script>
const selectedProfessionalServices = <?= json_encode($servicesForProfessional, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const groupServiceField = document.getElementById('groupServiceField');
const modalGroupServiceField = document.getElementById('modalGroupServiceField');
const groupProfessionalWhatsappBtn = document.getElementById('groupProfessionalWhatsappBtn');

function syncGroupServiceVisibility(select, field) {
    if (!select || !field) return;
    const options = Array.from(select.options).filter((option) => option.value !== '');
    const groupOptions = options.filter((option) => (option.dataset.tipo || 'individual') === 'grupo');
    const shouldShow = groupOptions.length > 1;

    if (!shouldShow && groupOptions.length === 1) {
        groupOptions[0].selected = true;
    } else if (!shouldShow && options.length === 1) {
        options[0].selected = true;
    }

    field.style.display = shouldShow ? '' : 'none';
}

function fillServiceOptions(select, professionalId, selectedServiceId = '') {
    if (!select) return;
    fetch('buscar_servicos_profissional.php?profissional_id=' + encodeURIComponent(professionalId))
        .then((response) => response.json())
        .then((services) => {
            select.innerHTML = '';
            services.forEach((service) => {
                const option = document.createElement('option');
                option.value = service.id;
                option.dataset.tipo = service.tipo_agendamento || 'individual';
                option.dataset.capacidade = service.capacidade_agendamento || '1';
                option.textContent = `${service.nome} - ${option.dataset.tipo === 'grupo' ? 'Grupo' : 'Individual'}`;
                if (String(service.id) === String(selectedServiceId)) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            syncGroupServiceVisibility(select, select.id === 'calendarService' ? groupServiceField : modalGroupServiceField);
        });
}

function bindDirectionalFilter(form, serviceSelect) {
    if (!form || !serviceSelect) return;
    form.addEventListener('submit', () => {
        const selected = serviceSelect.selectedOptions[0];
        const tipo = selected?.dataset.tipo || 'individual';
        form.action = tipo === 'grupo' ? 'secretaria_agenda_grupo.php' : 'secretaria_agenda.php';
    });
}

const calendarProfessional = document.getElementById('calendarProfessional');
const calendarService = document.getElementById('calendarService');
const modalProfessional = document.getElementById('modalCalendarProfessional');
const modalService = document.getElementById('modalCalendarService');

calendarProfessional?.addEventListener('change', () => fillServiceOptions(calendarService, calendarProfessional.value));
modalProfessional?.addEventListener('change', () => fillServiceOptions(modalService, modalProfessional.value));
bindDirectionalFilter(document.getElementById('groupFilterForm'), calendarService);
bindDirectionalFilter(document.getElementById('calendarFilterModalForm'), modalService);
syncGroupServiceVisibility(calendarService, groupServiceField);
syncGroupServiceVisibility(modalService, modalGroupServiceField);

function loadGroupCaptureScript() {
    if (window.html2canvas) {
        return Promise.resolve();
    }

    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-group-capture="1"]');

        if (existing) {
            existing.addEventListener('load', resolve, { once: true });
            existing.addEventListener('error', reject, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
        script.async = true;
        script.dataset.groupCapture = '1';
        script.onload = resolve;
        script.onerror = () => reject(new Error('Nao foi possivel preparar o print do modal.'));
        document.head.appendChild(script);
    });
}

function downloadGroupBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

async function captureGroupModalBlob() {
    const modalContent = document.querySelector('#groupSlotModal .modal-content');

    if (!modalContent) {
        throw new Error('Modal do grupo nao encontrado.');
    }

    await loadGroupCaptureScript();
    document.body.classList.add('is-capturing-group-modal');

    try {
        const canvas = await window.html2canvas(modalContent, {
            backgroundColor: '#ffffff',
            scale: Math.min(2, window.devicePixelRatio || 1.5),
            useCORS: true,
        });

        return await new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error('Nao foi possivel gerar o JPG do modal.'));
                }
            }, 'image/jpeg', 0.92);
        });
    } finally {
        document.body.classList.remove('is-capturing-group-modal');
    }
}

if (groupProfessionalWhatsappBtn) {
    groupProfessionalWhatsappBtn.addEventListener('click', async () => {
        const phone = groupProfessionalWhatsappBtn.dataset.whatsappPhone || '';

        if (!phone) {
            alert('Profissional sem telefone cadastrado.');
            return;
        }

        groupProfessionalWhatsappBtn.disabled = true;

        try {
            const blob = await captureGroupModalBlob();
            const filename = 'agenda-grupo-' + new Date().toISOString().slice(0, 10) + '.jpg';
            const file = new File([blob], filename, { type: 'image/jpeg' });

            if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
                await navigator.share({ files: [file] });
                return;
            }

            downloadGroupBlob(blob, filename);
            window.open('https://wa.me/' + encodeURIComponent(phone), '_blank');
        } catch (error) {
            alert(error.message || 'Nao foi possivel gerar o print do modal.');
        } finally {
            groupProfessionalWhatsappBtn.disabled = false;
        }
    });
}

function setupPatientAutocomplete() {
    const input = document.getElementById('groupPatientSearch');
    const patientId = document.getElementById('groupPatientId');
    const menu = document.getElementById('groupPatientMenu');
    const guideSelect = document.getElementById('groupPatientGuide');
    const form = document.getElementById('addGroupPatientForm');
    let controller = null;
    let guideController = null;

    if (!input || !patientId || !menu) return;

    function closeMenu() {
        menu.classList.remove('is-open');
        menu.innerHTML = '';
    }

    function setGuideOptions(message) {
        if (!guideSelect) return;
        guideSelect.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = message;
        guideSelect.appendChild(option);
    }

    function loadPatientGuides(selectedPatientId) {
        if (!guideSelect || !form) return;
        const professional = form.querySelector('[name="professional_id"]')?.value || '';
        const service = form.querySelector('[name="service_id"]')?.value || '';
        const params = new URLSearchParams({
            ajax: 'group_guides',
            paciente_id: selectedPatientId,
            professional_id: professional,
            service_id: service,
        });

        setGuideOptions('Carregando guias...');
        guideController?.abort();
        guideController = new AbortController();

        fetch('secretaria_agenda_grupo.php?' + params.toString(), { signal: guideController.signal })
            .then((response) => response.json())
            .then((data) => {
                const guides = data.guides || [];
                guideSelect.innerHTML = '';

                if (!guides.length) {
                    setGuideOptions('Nenhuma guia disponivel');
                    return;
                }

                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'Selecione a guia';
                guideSelect.appendChild(empty);

                guides.forEach((guide) => {
                    const option = document.createElement('option');
                    option.value = guide.id;
                    option.textContent = guide.label;
                    guideSelect.appendChild(option);
                });
            })
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    setGuideOptions('Nao foi possivel carregar guias');
                }
            });
    }

    function render(items) {
        menu.innerHTML = '';
        if (!items.length) {
            closeMenu();
            return;
        }

        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'autocomplete-option';
            button.textContent = item.nome;
            button.addEventListener('click', () => {
                patientId.value = item.id;
                input.value = item.nome;
                closeMenu();
                loadPatientGuides(item.id);
            });
            menu.appendChild(button);
        });
        menu.classList.add('is-open');
    }

    input.addEventListener('input', () => {
        patientId.value = '';
        setGuideOptions('Selecione o paciente');
        const term = input.value.trim();

        if (term.length < 2) {
            closeMenu();
            return;
        }

        controller?.abort();
        controller = new AbortController();
        fetch('pacientes_busca.php?q=' + encodeURIComponent(term), { signal: controller.signal })
            .then((response) => response.json())
            .then((data) => render(data.pacientes || []))
            .catch(() => {});
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && event.target !== input) {
            closeMenu();
        }
    });
}

setupPatientAutocomplete();

function syncGroupStatusForms() {
    document.querySelectorAll('.group-status-form').forEach((form) => {
        const status = form.querySelector('.group-status-select');
        const guideField = form.querySelector('.group-guide-field');
        const guideSelect = form.querySelector('.group-guide-select');
        if (!status || !guideField || !guideSelect) return;
        const requireGuide = status.value === 'realizado';
        const card = form.closest('.group-seat-card');
        const statusBadge = card?.querySelector('.group-seat-top strong');

        if (card) {
            card.classList.remove('status-agendado', 'status-confirmado', 'status-realizado', 'status-cancelado');
            card.classList.add('status-' + status.value);
        }

        if (statusBadge) {
            statusBadge.textContent = status.selectedOptions?.[0]?.textContent?.trim() || status.value;
        }

        guideField.hidden = !requireGuide;
        guideSelect.required = requireGuide;
    });
}

document.querySelectorAll('.group-status-select').forEach((select) => {
    select.addEventListener('change', syncGroupStatusForms);
});
syncGroupStatusForms();

document.addEventListener('DOMContentLoaded', () => {
    const groupSlotModal = document.getElementById('groupSlotModal');
    if (groupSlotModal && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(groupSlotModal).show();
    }

    const filterModal = document.getElementById('calendarFilterModal');
    if (filterModal && window.bootstrap && <?= (!$selectedService ? 'true' : 'false') ?>) {
        bootstrap.Modal.getOrCreateInstance(filterModal).show();
    }
});
</script>
</body>
</html>
