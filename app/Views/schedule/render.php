<?php

function schedule_day_start(): string
{
    return '07:00:00';
}

function schedule_day_end(): string
{
    return '22:00:00';
}

function schedule_slot_minutes(): int
{
    return 30;
}

function schedule_pixels_per_minute(): float
{
    return 0.6;
}

function schedule_row_height(?float $scale = null, ?int $slotMinutes = null): int
{
    $scale = $scale ?? schedule_pixels_per_minute();
    $slotMinutes = $slotMinutes ?? schedule_slot_minutes();

    return (int) round($slotMinutes * $scale);
}

function schedule_minutes_from_start(string $time, ?string $dayStart = null): int
{
    $dayStart = $dayStart ?? schedule_day_start();

    return max(0, app_minutes_between($dayStart, $time));
}

function schedule_block_style(string $startTime, string $endTime, ?string $dayStart = null, ?float $scale = null): string
{
    $dayStart = $dayStart ?? schedule_day_start();
    $scale = $scale ?? schedule_pixels_per_minute();
    $top = (int) round(schedule_minutes_from_start($startTime, $dayStart) * $scale);
    $height = (int) round(max(30, app_minutes_between($startTime, $endTime)) * $scale);

    return sprintf('top:%dpx;height:%dpx;', $top, max(24, $height));
}

function schedule_time_value(string $time): int
{
    $timestamp = strtotime('2000-01-01 ' . $time);

    return $timestamp === false ? 0 : $timestamp;
}

function schedule_block_overlaps_window(string $startTime, string $endTime, string $windowStart, string $windowEnd): bool
{
    return schedule_time_value($startTime) < schedule_time_value($windowEnd)
        && schedule_time_value($endTime) > schedule_time_value($windowStart);
}

function schedule_clamped_block_style(string $startTime, string $endTime, string $windowStart, string $windowEnd, ?float $scale = null): string
{
    $start = max(schedule_time_value($startTime), schedule_time_value($windowStart));
    $end = min(schedule_time_value($endTime), schedule_time_value($windowEnd));

    return schedule_block_style(date('H:i:s', $start), date('H:i:s', $end), $windowStart, $scale);
}

function schedule_time_slots(?string $dayStart = null, ?string $dayEnd = null, ?int $slotMinutes = null): array
{
    $dayStart = $dayStart ?? schedule_day_start();
    $dayEnd = $dayEnd ?? schedule_day_end();
    $slotMinutes = $slotMinutes ?? schedule_slot_minutes();
    $slots = [];
    $current = strtotime('2000-01-01 ' . $dayStart);
    $end = strtotime('2000-01-01 ' . $dayEnd);

    while ($current < $end) {
        $slots[] = date('H:i', $current);
        $current = strtotime('+' . $slotMinutes . ' minutes', $current);
    }

    return $slots;
}

function render_schedule_metrics(array $calendarData): string
{
    $appointments = $calendarData['appointments'] ?? [];
    $availabilities = $calendarData['availabilities'] ?? [];
    $confirmed = 0;
    $realized = 0;

    foreach ($appointments as $appointment) {
        if (($appointment['status'] ?? '') === 'confirmado') {
            $confirmed++;
        }

        if (($appointment['status'] ?? '') === 'realizado') {
            $realized++;
        }
    }

    ob_start();
    ?>
    <div class="calendar-summary">
        <div class="metric-card metric-accent">
            <span class="small text-uppercase">Agendamentos ativos</span>
            <strong><?= count($appointments) ?></strong>
            <span class="text-muted small">sem cancelados</span>
        </div>
        <div class="metric-card metric-info">
            <span class="small text-uppercase">Faixas liberadas</span>
            <strong><?= count($availabilities) ?></strong>
            <span class="text-muted small">janelas disponiveis</span>
        </div>
        <div class="metric-card metric-warning">
            <span class="small text-uppercase">Confirmados</span>
            <strong><?= $confirmed ?></strong>
            <span class="text-muted small">aguardando atendimento</span>
        </div>
        <div class="metric-card metric-success">
            <span class="small text-uppercase">Realizados</span>
            <strong><?= $realized ?></strong>
            <span class="text-muted small">fechados na semana</span>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function render_week_calendar(array $weekDays, array $calendarData, ?int $selectedAppointmentId, bool $canManageAppointments, bool $canManageAvailability, string $weekStart, ?int $selectedAvailabilityId = null, int $selectedProfessionalId = 0, array $extraQuery = [], ?string $displayStart = null, ?string $displayEnd = null, ?float $pixelsPerMinute = null, ?int $slotMinutes = null): string
{
    $displayStart = $displayStart ?? schedule_day_start();
    $displayEnd = $displayEnd ?? schedule_day_end();
    $pixelsPerMinute = $pixelsPerMinute ?? schedule_pixels_per_minute();
    $slotMinutes = max(5, $slotMinutes ?? schedule_slot_minutes());
    $timeSlots = schedule_time_slots($displayStart, $displayEnd, $slotMinutes);
    $canUseSlots = $canManageAppointments || $canManageAvailability;
    $basePath = app_current_page();
    $rowHeight = schedule_row_height($pixelsPerMinute, $slotMinutes);
    $bodyMinHeight = count($timeSlots) * $rowHeight;

    $availabilityByDate = [];
    foreach ($calendarData['availabilities'] as $availability) {
        $availabilityByDate[$availability['data_disponivel']][] = $availability;
    }

    $appointmentsByDate = [];
    foreach ($calendarData['appointments'] as $appointment) {
        $appointmentsByDate[$appointment['data_agendamento']][] = $appointment;
    }

    ob_start();
    ?>
    <div
        class="calendar-shell"
        style="--calendar-row-height: <?= (int) $rowHeight ?>px; --calendar-body-min-height: <?= (int) $bodyMinHeight ?>px;"
        data-day-start="<?= app_h($displayStart) ?>"
        data-slot-minutes="<?= (int) $slotMinutes ?>"
    >
        <div class="calendar-head">
            <div class="fw-semibold">Hora</div>
            <?php foreach ($weekDays as $index => $day): ?>
                <div class="day-head" data-col-date="<?= app_h($day['date']) ?>">
                    <span><?= app_h($day['label']) ?></span>
                    <strong><?= app_h($day['day']) ?>/<?= app_h($day['month']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="calendar-body">
            <div class="calendar-hours">
                <?php foreach ($timeSlots as $slot): ?>
                    <?php $isFullHour = substr($slot, -2) === '00'; ?>
                    <div class="<?= $isFullHour ? 'is-full-hour' : 'is-half-hour' ?>"><?= ($slotMinutes >= 60 || $isFullHour) ? app_h($slot) : '' ?></div>
                <?php endforeach; ?>
            </div>
            <?php foreach ($weekDays as $day): ?>
                <div
                    class="calendar-day"
                    data-col-date="<?= app_h($day['date']) ?>"
                    <?= $canUseSlots ? '' : 'data-readonly="1"' ?>
                >
                    <?php foreach ($timeSlots as $slot): ?>
                        <div
                            class="calendar-slot"
                            data-date="<?= app_h($day['date']) ?>"
                            data-time="<?= app_h($slot) ?>"
                            <?= $canUseSlots ? '' : 'data-readonly="1"' ?>
                        ></div>
                    <?php endforeach; ?>

                    <?php foreach ($availabilityByDate[$day['date']] ?? [] as $availability): ?>
                        <?php if (!schedule_block_overlaps_window((string) $availability['hora_inicio'], (string) $availability['hora_fim'], $displayStart, $displayEnd)) { continue; } ?>
                        <a
                            href="#"
                            class="calendar-availability <?= $selectedAvailabilityId !== null && (int) $availability['id'] === $selectedAvailabilityId ? 'is-active' : '' ?>"
                            style="<?= app_h(schedule_clamped_block_style($availability['hora_inicio'], $availability['hora_fim'], $displayStart, $displayEnd, $pixelsPerMinute)) ?>"
                            data-availability-id="<?= (int) $availability['id'] ?>"
                            data-date="<?= app_h((string) $availability['data_disponivel']) ?>"
                            data-start="<?= app_h(app_time_br((string) $availability['hora_inicio'])) ?>"
                            data-end="<?= app_h(app_time_br((string) $availability['hora_fim'])) ?>"
                            data-json="<?= app_h(json_encode($availability, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                        >
                            <strong><?= app_time_br($availability['hora_inicio']) ?> - <?= app_time_br($availability['hora_fim']) ?></strong>
                            <?php if (!empty($availability['observacoes'])): ?>
                                <small><?= app_h($availability['observacoes']) ?></small>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>

                    <?php foreach ($appointmentsByDate[$day['date']] ?? [] as $appointment): ?>
                        <?php if (!schedule_block_overlaps_window((string) $appointment['hora_inicio'], (string) $appointment['hora_fim'], $displayStart, $displayEnd)) { continue; } ?>
                        <?php
                        $activeClass = $selectedAppointmentId !== null && (int) $appointment['id'] === $selectedAppointmentId ? ' is-active' : '';
                        $statusClass = 'status-' . app_h($appointment['status']);
                        ?>
                        <a
                            href="#"
                            class="calendar-event <?= $statusClass . $activeClass ?>"
                            style="<?= app_h(schedule_clamped_block_style($appointment['hora_inicio'], $appointment['hora_fim'], $displayStart, $displayEnd, $pixelsPerMinute)) ?>"
                            data-appointment-id="<?= (int) $appointment['id'] ?>"
                            data-json="<?= app_h(json_encode($appointment, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                            draggable="<?= $canManageAppointments ? 'true' : 'false' ?>"
                        >
                            <strong><?= app_time_br($appointment['hora_inicio']) ?></strong>
                            <span class="calendar-event-patient"><?= app_h($appointment['paciente_nome']) ?></span>
                            <small class="calendar-event-service"><?= app_h($appointment['servico_nome']) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}
