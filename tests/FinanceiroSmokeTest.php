<?php

declare(strict_types=1);

return [
    'finance_parse_money' => static function (): void {
        test_assert_same(1234.56, app_parse_money('R$ 1.234,56'), 'O parser de moeda deve converter valores brasileiros.');
    },
    'finance_net_amount' => static function (): void {
        test_assert_same(95.0, app_financial_net_amount(100.0, 10.0, 5.0, 20.0), 'O valor liquido deve considerar juros, multa e desconto.');
    },
    'finance_status_meta' => static function (): void {
        $meta = app_financial_status_meta('pago');
        test_assert_same('chip-success', $meta['chip'], 'Status pago deve usar chip de sucesso.');
    },
];
