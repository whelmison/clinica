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

function app_report_issued_at(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Araguaina')))->format('d/m/Y H:i');
}

function app_report_clinic(mysqli $conn): array
{
    $logoColumn = app_column_exists($conn, 'clinicas', 'logotipo') ? 'logotipo' : 'NULL AS logotipo';

    return app_stmt_one(
        $conn,
        'SELECT nome_fantasia, razao_social, cnpj, telefone, whatsapp, email, endereco, cidade, estado, ' . $logoColumn . '
         FROM clinicas
         WHERE id = ?
         LIMIT 1',
        'i',
        [app_active_clinic_id()]
    ) ?? [];
}

function app_report_logo_src(?string $path): string
{
    $logo = trim((string) $path);

    if ($logo === '') {
        return '';
    }

    if (preg_match('/^(?:https?:)?\/\//i', $logo)) {
        return $logo;
    }

    $logo = ltrim(str_replace('\\', '/', $logo), '/');
    $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $logo);

    return is_file($fullPath) ? $logo : '';
}

function app_report_initials(string $name): string
{
    $initials = '';

    foreach (preg_split('/\s+/', trim($name)) ?: [] as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(substr($part, 0, 1));

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return substr($initials !== '' ? $initials : 'CL', 0, 3);
}

function app_report_print_header(mysqli $conn, string $title, array $lines = [], array $metrics = [], ?string $issuedAt = null): string
{
    $clinic = app_report_clinic($conn);
    $clinicName = trim((string) ($clinic['nome_fantasia'] ?? app_current_clinic_name()));
    $legalName = trim((string) ($clinic['razao_social'] ?? ''));
    $logoSrc = app_report_logo_src($clinic['logotipo'] ?? '');
    $contacts = [];
    $address = [];

    if (!empty($clinic['cnpj'])) {
        $contacts[] = 'CNPJ ' . app_format_cnpj((string) $clinic['cnpj']);
    }

    if (!empty($clinic['telefone'])) {
        $contacts[] = 'Tel. ' . app_format_phone_br((string) $clinic['telefone']);
    }

    if (!empty($clinic['whatsapp']) && (string) $clinic['whatsapp'] !== (string) ($clinic['telefone'] ?? '')) {
        $contacts[] = 'WhatsApp ' . app_format_phone_br((string) $clinic['whatsapp']);
    }

    if (!empty($clinic['email'])) {
        $contacts[] = (string) $clinic['email'];
    }

    if (!empty($clinic['endereco'])) {
        $address[] = (string) $clinic['endereco'];
    }

    $cityState = trim(implode('-', array_filter([
        trim((string) ($clinic['cidade'] ?? '')),
        trim((string) ($clinic['estado'] ?? '')),
    ])));

    if ($cityState !== '') {
        $address[] = $cityState;
    }

    $reportLines = array_values(array_filter(array_map(static fn ($line): string => trim((string) $line), $lines)));
    $reportMetrics = array_values(array_filter(array_map(static fn ($line): string => trim((string) $line), $metrics)));
    $reportMetrics[] = 'Emissao: ' . ($issuedAt ?: app_report_issued_at());

    ob_start();
    ?>
    <div class="app-report-print-header">
        <div class="app-report-print-brand">
            <div class="app-report-print-logo">
                <?php if ($logoSrc !== ''): ?>
                    <img src="<?= app_h($logoSrc) ?>" alt="Logotipo">
                <?php else: ?>
                    <span><?= app_h(app_report_initials($clinicName)) ?></span>
                <?php endif; ?>
            </div>
            <div class="app-report-print-company">
                <strong><?= app_h($clinicName) ?></strong>
                <?php if ($legalName !== ''): ?>
                    <span><?= app_h($legalName) ?></span>
                <?php endif; ?>
                <?php if ($contacts !== []): ?>
                    <span><?= app_h(implode(' | ', $contacts)) ?></span>
                <?php endif; ?>
                <?php if ($address !== []): ?>
                    <span><?= app_h(implode(', ', $address)) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="app-report-print-title">
            <h2><?= app_h($title) ?></h2>
            <?php foreach ($reportLines as $line): ?>
                <p><?= app_h($line) ?></p>
            <?php endforeach; ?>
            <?php foreach ($reportMetrics as $line): ?>
                <p><?= app_h($line) ?></p>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function app_report_print_header_css(): string
{
    return <<<'CSS'
.app-report-print-header {
    display: none;
    grid-template-columns: minmax(320px, 1fr) minmax(280px, 0.9fr);
    gap: 1rem;
    padding: 0.95rem 1rem 1rem;
    border-bottom: 1px solid rgba(19, 74, 89, 0.16);
    margin-bottom: 0.4rem;
}
.app-report-print-brand {
    display: flex;
    gap: 0.82rem;
    align-items: center;
    min-width: 0;
}
.app-report-print-logo {
    width: 118px;
    height: 64px;
    flex: 0 0 auto;
    border: 1px solid rgba(19, 74, 89, 0.16);
    border-radius: 8px;
    background: #f5fafb;
    color: #1f7a8c;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-weight: 800;
    font-size: 1.1rem;
}
.app-report-print-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.app-report-print-company,
.app-report-print-title {
    min-width: 0;
}
.app-report-print-company strong {
    display: block;
    color: #173642;
    font-size: 0.9rem;
    line-height: 1.18;
    text-transform: uppercase;
}
.app-report-print-company span,
.app-report-print-title p {
    display: block;
    color: #526b76;
    font-size: 0.72rem;
    line-height: 1.35;
    overflow-wrap: anywhere;
}
.app-report-print-title {
    align-self: center;
}
.app-report-print-title h2 {
    color: #102f3a;
    font-size: 1.2rem;
    font-weight: 800;
    line-height: 1.12;
    margin: 0 0 0.45rem;
}
.app-report-print-title p {
    margin: 0;
}
.app-print-only {
    display: none !important;
}
@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
    }
    html,
    body.app-print-page {
        height: auto !important;
        min-height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
        background: #fff !important;
    }
    body.app-print-page {
        display: block !important;
        color: #111 !important;
        font-family: Arial, Helvetica, sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body.app-print-page nav,
    body.app-print-page .topbar-clinica,
    body.app-print-page .app-print-hide,
    body.app-print-page .no-print,
    body.app-print-page .modal,
    body.app-print-page .dropdown-menu,
    body.app-print-page script {
        display: none !important;
    }
    body.app-print-page .app-print-page-shell,
    body.app-print-page .page-shell,
    body.app-print-page .container,
    body.app-print-page .container-fluid {
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        overflow: visible !important;
    }
    body.app-print-page .app-print-report-area {
        display: block !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        overflow: visible !important;
    }
    body.app-print-page .app-print-report-area .card,
    body.app-print-page .app-print-report-area .soft-card,
    body.app-print-page .app-print-report-area .card-body {
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
        background: #fff !important;
    }
    body.app-print-page .app-print-report-area .table-responsive {
        overflow: visible !important;
    }
    body.app-print-page .app-report-print-header {
        display: grid !important;
        grid-template-columns: 45% 1fr;
        align-items: stretch;
        gap: 7mm;
        padding: 0 0 6mm;
        margin: 0 0 5mm;
        border-bottom: 2px solid #12333e;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    body.app-print-page .app-report-print-logo {
        width: 38mm;
        height: 22mm;
        border: 1px solid #8aa9b2;
        border-radius: 3mm;
        background: #fff;
        color: #12333e;
        font-size: 13pt;
    }
    body.app-print-page .app-report-print-company strong {
        color: #12333e;
        font-size: 11pt;
        margin-bottom: 1.2mm;
    }
    body.app-print-page .app-report-print-company span,
    body.app-print-page .app-report-print-title p {
        color: #213f49;
        font-size: 8.4pt;
        line-height: 1.32;
    }
    body.app-print-page .app-report-print-title {
        border-left: 1px solid #c9d9de;
        padding-left: 7mm;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    body.app-print-page .app-report-print-title h2 {
        color: #12333e;
        font-size: 16pt;
        text-transform: uppercase;
        margin-bottom: 2.4mm;
    }
    body.app-print-page .app-print-report-area table {
        width: 100% !important;
        min-width: 0 !important;
        border-collapse: collapse !important;
        border-spacing: 0 !important;
        table-layout: fixed;
        font-size: 8.4pt;
        line-height: 1.25;
    }
    body.app-print-page .app-print-report-area table thead {
        display: table-header-group;
    }
    body.app-print-page .app-print-report-area table thead th {
        position: static !important;
        background: #12333e !important;
        color: #fff !important;
        border: 1px solid #12333e !important;
        font-size: 8pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0;
    }
    body.app-print-page .app-print-report-area table th,
    body.app-print-page .app-print-report-area table td {
        padding: 5px 7px !important;
        border: 1px solid #d6e0e4 !important;
        overflow-wrap: anywhere;
        vertical-align: top;
    }
    body.app-print-page .app-print-report-area table tr {
        break-inside: avoid;
        page-break-inside: avoid;
    }
    body.app-print-page .app-print-report-area tfoot td {
        font-weight: 800;
        color: #12333e;
        background: #f5fafb !important;
    }
    body.app-print-page .app-print-actions,
    body.app-print-page .acoes,
    body.app-print-page .app-print-report-area .btn,
    body.app-print-page .app-print-report-area form,
    body.app-print-page .app-print-report-area input[type="checkbox"] {
        display: none !important;
    }
    body.app-print-page .app-print-only {
        display: inline !important;
    }
    body.app-print-page .app-print-report-area::after {
        content: "Relatorio gerado pelo sistema Clinica Fisiolife";
        display: block;
        margin-top: 5mm;
        padding-top: 2mm;
        border-top: 1px solid #d6e0e4;
        color: #526b76;
        font-size: 7.6pt;
        text-align: right;
    }
}
CSS;
}
