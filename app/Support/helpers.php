<?php

use Clinic\Core\Database;

function app_pdo(): PDO
{
    return Database::pdo();
}

function app_money_br(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function app_date_br(?string $date): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return '-';
    }

    $time = strtotime($date);

    return $time ? date('d/m/Y', $time) : '-';
}

function app_only_digits(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?: '';
}

function app_cpf_valid(?string $cpf): bool
{
    $digits = app_only_digits($cpf);

    if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
        return false;
    }

    for ($position = 9; $position <= 10; $position++) {
        $sum = 0;

        for ($index = 0; $index < $position; $index++) {
            $sum += (int) $digits[$index] * (($position + 1) - $index);
        }

        $check = ($sum * 10) % 11;
        $check = $check === 10 ? 0 : $check;

        if ($check !== (int) $digits[$position]) {
            return false;
        }
    }

    return true;
}

function app_format_cpf(?string $cpf): string
{
    $digits = app_only_digits($cpf);

    if (strlen($digits) !== 11) {
        return trim((string) $cpf);
    }

    return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
}

function app_cnpj_valid(?string $cnpj): bool
{
    $digits = app_only_digits($cnpj);

    if (strlen($digits) !== 14 || preg_match('/^(\d)\1{13}$/', $digits)) {
        return false;
    }

    $weights = [
        [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    ];

    for ($position = 12; $position <= 13; $position++) {
        $sum = 0;

        for ($index = 0; $index < $position; $index++) {
            $sum += (int) $digits[$index] * $weights[$position - 12][$index];
        }

        $rest = $sum % 11;
        $check = $rest < 2 ? 0 : 11 - $rest;

        if ($check !== (int) $digits[$position]) {
            return false;
        }
    }

    return true;
}

function app_format_cnpj(?string $cnpj): string
{
    $digits = app_only_digits($cnpj);

    if (strlen($digits) !== 14) {
        return trim((string) $cnpj);
    }

    return substr($digits, 0, 2) . '.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/' . substr($digits, 8, 4) . '-' . substr($digits, 12, 2);
}

function app_cpf_cnpj_valid(?string $document): bool
{
    $digits = app_only_digits($document);

    return strlen($digits) === 11 ? app_cpf_valid($digits) : (strlen($digits) === 14 && app_cnpj_valid($digits));
}

function app_format_cpf_cnpj(?string $document): string
{
    $digits = app_only_digits($document);

    if (strlen($digits) === 11) {
        return app_format_cpf($digits);
    }

    if (strlen($digits) === 14) {
        return app_format_cnpj($digits);
    }

    return trim((string) $document);
}

function app_format_phone_br(?string $phone): string
{
    $digits = app_only_digits($phone);

    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '55') && strlen($digits) > 11) {
        $digits = substr($digits, 2);
    }

    if (strlen($digits) === 11) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 5) . '-' . substr($digits, 7, 4);
    }

    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 2) . ') ' . substr($digits, 2, 4) . '-' . substr($digits, 6, 4);
    }

    return trim((string) $phone);
}

function app_parse_date_br(?string $date): ?string
{
    $date = trim((string) $date);

    if ($date === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        [$year, $month, $day] = array_map('intval', explode('-', $date));

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }

    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        return null;
    }

    $day = (int) $matches[1];
    $month = (int) $matches[2];
    $year = (int) $matches[3];

    return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
}

function app_time_br(?string $time): string
{
    if ($time === null || $time === '') {
        return '-';
    }

    return substr($time, 0, 5);
}

function app_request_query(string $key, ?string $default = null): ?string
{
    $value = $_GET[$key] ?? $default;

    if ($value === null) {
        return null;
    }

    return trim((string) $value);
}

function app_request_post(string $key, ?string $default = null): ?string
{
    $value = $_POST[$key] ?? $default;

    if ($value === null) {
        return null;
    }

    return trim((string) $value);
}

function app_request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function app_request_int(array $source, string $key, int $default = 0): int
{
    if (!isset($source[$key]) || $source[$key] === '') {
        return $default;
    }

    return (int) $source[$key];
}

function app_query_int(string $key, int $default = 0): int
{
    return app_request_int($_GET, $key, $default);
}

function app_post_int(string $key, int $default = 0): int
{
    return app_request_int($_POST, $key, $default);
}

function app_bool_value(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array((string) $value, ['1', 'true', 'on', 'yes'], true);
}

function app_build_query(array $params, array $overrides = []): string
{
    $query = array_merge($params, $overrides);

    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        }
    }

    return http_build_query($query);
}

function app_pagination(int $page, int $perPage, int $total, string $path, array $query = [], string $pageParam = 'page'): array
{
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'path' => $path,
        'query' => $query,
        'page_param' => $pageParam,
        'offset' => ($page - 1) * $perPage,
    ];
}

function app_render_pagination(array $pagination): string
{
    if (($pagination['total_pages'] ?? 1) <= 1) {
        return '';
    }

    ob_start();
    $page = (int) $pagination['page'];
    $totalPages = (int) $pagination['total_pages'];
    $path = (string) $pagination['path'];
    $query = $pagination['query'] ?? [];
    $pageParam = $pagination['page_param'] ?? 'page';
    ?>
    <nav aria-label="Paginacao" class="mt-3">
        <ul class="pagination pagination-sm flex-wrap mb-0">
            <?php for ($current = 1; $current <= $totalPages; $current++): ?>
                <?php
                $url = $path . '?' . app_build_query($query, [$pageParam => $current]);
                $active = $current === $page ? ' active' : '';
                ?>
                <li class="page-item<?= $active ?>">
                    <a class="page-link" href="<?= app_h($url) ?>"><?= $current ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php

    return (string) ob_get_clean();
}

function app_is_ajax_request(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || app_request_query('ajax') === '1';
}

function app_json(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function app_csrf_token(): string
{
    if (!isset($_SESSION['app_csrf_token']) || !is_string($_SESSION['app_csrf_token']) || $_SESSION['app_csrf_token'] === '') {
        $_SESSION['app_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['app_csrf_token'];
}

function app_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . app_h(app_csrf_token()) . '">';
}

function app_csrf_request_token(): ?string
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);

    if ($token === null) {
        return null;
    }

    return trim((string) $token);
}

function app_is_state_changing_request(): bool
{
    return in_array(app_request_method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
}

function app_validate_csrf_request(): void
{
    if (app_is_cli() || !app_is_state_changing_request()) {
        return;
    }

    $requestToken = app_csrf_request_token();
    $sessionToken = $_SESSION['app_csrf_token'] ?? null;
    $isValid = is_string($requestToken)
        && is_string($sessionToken)
        && $requestToken !== ''
        && hash_equals($sessionToken, $requestToken);

    if ($isValid) {
        return;
    }

    if (app_is_ajax_request()) {
        app_json(['ok' => false, 'message' => 'Sessao expirada. Atualize a pagina e tente novamente.'], 419);
    }

    app_flash('danger', 'Sua sessao expirou para esta acao. Atualize a pagina e tente novamente.');
    app_redirect($_SERVER['HTTP_REFERER'] ?? app_current_page());
}

function app_inject_csrf_fields(string $html): string
{
    if (stripos($html, '<form') !== false) {
        $html = (string) preg_replace_callback(
            '/<form\b(?=[^>]*\bmethod\s*=\s*(["\']?)post\1)([^>]*)>/i',
            static function (array $matches): string {
                $tag = $matches[0];

                if (stripos($tag, 'data-no-csrf') !== false) {
                    return $tag;
                }

                return $tag . app_csrf_field();
            },
            $html
        );
    }

    if (stripos($html, 'form-enter-navigation.js') !== false || !preg_match('/<(?:!DOCTYPE|html|body)\b/i', $html)) {
        return $html;
    }

    $scriptTag = '<script src="assets/form-enter-navigation.js"></script>';

    $bodyPos = strripos($html, '</body>');
    if ($bodyPos !== false) {
        return substr($html, 0, $bodyPos) . $scriptTag . "\n" . substr($html, $bodyPos);
    }

    $htmlPos = strripos($html, '</html>');
    if ($htmlPos !== false) {
        return substr($html, 0, $htmlPos) . $scriptTag . "\n" . substr($html, $htmlPos);
    }

    return $html . $scriptTag;
}

function app_start_csrf_output_buffer(): void
{
    static $bufferStarted = false;

    if (app_is_cli()) {
        return;
    }

    if ($bufferStarted) {
        return;
    }

    ob_start('app_inject_csrf_fields');
    $bufferStarted = true;
}

function app_month_label(string $month): string
{
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return $month;
    }

    $date = DateTime::createFromFormat('Y-m-d', $month . '-01');

    if (!$date) {
        return $month;
    }

    if (!class_exists('IntlDateFormatter')) {
        $months = [
            '01' => 'Janeiro',
            '02' => 'Fevereiro',
            '03' => 'Marco',
            '04' => 'Abril',
            '05' => 'Maio',
            '06' => 'Junho',
            '07' => 'Julho',
            '08' => 'Agosto',
            '09' => 'Setembro',
            '10' => 'Outubro',
            '11' => 'Novembro',
            '12' => 'Dezembro',
        ];

        return ($months[$date->format('m')] ?? $date->format('m')) . ' de ' . $date->format('Y');
    }

    $formatter = new IntlDateFormatter(
        'pt_BR',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        date_default_timezone_get(),
        IntlDateFormatter::GREGORIAN,
        'MMMM \'de\' yyyy'
    );

    return ucfirst($formatter->format($date));
}

function app_week_start(?string $date = null): string
{
    $base = $date ? new DateTime($date) : new DateTime();
    $dayOfWeek = (int) $base->format('N');
    $base->modify('-' . ($dayOfWeek - 1) . ' days');

    return $base->format('Y-m-d');
}

function app_schedule_report_periods(): array
{
    return [
        'day' => 'Diario',
        'week' => 'Semanal',
        'month' => 'Mensal',
    ];
}

function app_schedule_report_range(?string $period, ?string $referenceDate = null): array
{
    $periods = app_schedule_report_periods();
    $normalizedPeriod = array_key_exists((string) $period, $periods) ? (string) $period : 'week';
    $baseTimestamp = strtotime((string) $referenceDate);
    $normalizedReferenceDate = $baseTimestamp ? date('Y-m-d', $baseTimestamp) : date('Y-m-d');

    if ($normalizedPeriod === 'day') {
        return [
            'period' => 'day',
            'reference_date' => $normalizedReferenceDate,
            'start_date' => $normalizedReferenceDate,
            'end_date' => $normalizedReferenceDate,
            'label' => app_date_br($normalizedReferenceDate),
        ];
    }

    if ($normalizedPeriod === 'month') {
        $month = date('Y-m', strtotime($normalizedReferenceDate));

        return [
            'period' => 'month',
            'reference_date' => $normalizedReferenceDate,
            'start_date' => date('Y-m-01', strtotime($normalizedReferenceDate)),
            'end_date' => date('Y-m-t', strtotime($normalizedReferenceDate)),
            'label' => app_month_label($month),
        ];
    }

    $weekStart = app_week_start($normalizedReferenceDate);
    $weekEnd = date('Y-m-d', strtotime($weekStart . ' +6 days'));

    return [
        'period' => 'week',
        'reference_date' => $normalizedReferenceDate,
        'start_date' => $weekStart,
        'end_date' => $weekEnd,
        'label' => app_date_br($weekStart) . ' a ' . app_date_br($weekEnd),
    ];
}

function app_week_days(string $weekStart): array
{
    $start = new DateTime($weekStart);
    $days = [];
    $formatter = null;

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            'pt_BR',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN,
            'EEE'
        );
    }

    $weekdayLabels = [
        1 => 'seg',
        2 => 'ter',
        3 => 'qua',
        4 => 'qui',
        5 => 'sex',
        6 => 'sab',
        7 => 'dom',
    ];

    for ($i = 0; $i < 7; $i++) {
        $day = clone $start;
        $day->modify('+' . $i . ' days');
        $days[] = [
            'date' => $day->format('Y-m-d'),
            'label' => $formatter
                ? str_replace('.', '', $formatter->format($day))
                : $weekdayLabels[(int) $day->format('N')],
            'day' => $day->format('d'),
            'month' => $day->format('m'),
        ];
    }

    return $days;
}

function app_minutes_between(string $startTime, string $endTime): int
{
    $start = DateTime::createFromFormat('H:i:s', $startTime) ?: DateTime::createFromFormat('H:i', $startTime);
    $end = DateTime::createFromFormat('H:i:s', $endTime) ?: DateTime::createFromFormat('H:i', $endTime);

    if (!$start || !$end) {
        return 0;
    }

    return (int) (($end->getTimestamp() - $start->getTimestamp()) / 60);
}

function app_normalize_phone(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

    if ($digits === '') {
        return '';
    }

    $digits = ltrim($digits, '0');

    if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
        return $digits;
    }

    if (strlen($digits) === 10 || strlen($digits) === 11) {
        return '55' . $digits;
    }

    return $digits;
}
