<?php

declare(strict_types=1);

return [
    'agenda_week_start' => static function (): void {
        test_assert_same('2026-05-04', app_week_start('2026-05-06'), 'A semana da agenda deve iniciar na segunda-feira.');
    },
    'agenda_week_days_count' => static function (): void {
        test_assert_same(7, count(app_week_days('2026-05-04')), 'A grade semanal precisa ter sete dias.');
    },
    'agenda_report_range_day' => static function (): void {
        $range = app_schedule_report_range('day', '2026-05-06');

        test_assert_same('2026-05-06', $range['start_date'], 'O filtro diario deve iniciar no mesmo dia.');
        test_assert_same('2026-05-06', $range['end_date'], 'O filtro diario deve terminar no mesmo dia.');
    },
    'agenda_report_range_week' => static function (): void {
        $range = app_schedule_report_range('week', '2026-05-06');

        test_assert_same('2026-05-04', $range['start_date'], 'O filtro semanal deve iniciar na segunda-feira.');
        test_assert_same('2026-05-10', $range['end_date'], 'O filtro semanal deve terminar no domingo.');
    },
    'agenda_report_range_month' => static function (): void {
        $range = app_schedule_report_range('month', '2026-05-06');

        test_assert_same('2026-05-01', $range['start_date'], 'O filtro mensal deve iniciar no primeiro dia do mes.');
        test_assert_same('2026-05-31', $range['end_date'], 'O filtro mensal deve terminar no ultimo dia do mes.');
    },
    'agenda_whatsapp_phone_normalization' => static function (): void {
        test_assert_same('5511999990000', app_normalize_phone('(11) 99999-0000'), 'O telefone do WhatsApp deve sair com DDI 55.');
    },
];
