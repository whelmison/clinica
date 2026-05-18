<?php
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$selectedAppointmentJson = $selectedAppointment ? json_encode($selectedAppointment, $jsonFlags) : 'null';
$selectedAvailabilityJson = $selectedAvailability ? json_encode($selectedAvailability, $jsonFlags) : 'null';
$selectedAppointmentPatientId = (int) ($selectedAppointment['cliente_id'] ?? 0);
$selectedAppointmentName = (string) (($selectedAppointment['paciente_nome'] ?? '') ?: ($selectedAppointment['cliente_nome'] ?? ''));
$selectedAppointmentPhone = (string) (($selectedAppointment['paciente_telefone'] ?? '') ?: ($selectedAppointment['cliente_telefone'] ?? ''));
$selectedAppointmentPreferenceDay = (string) ($selectedAppointment['paciente_dia_preferencia'] ?? '');
$selectedAppointmentPreferenceTime = (string) ($selectedAppointment['paciente_horario_preferencia'] ?? '');
$hasAppointmentOperation = $canManageAppointments;
$hasAvailabilityOperation = $canManageAvailability;
$showOperationTabs = $hasAppointmentOperation && $hasAvailabilityOperation;
$operationMode = $hasAvailabilityOperation && ($selectedAvailability || !$hasAppointmentOperation)
    ? 'availability'
    : 'appointment';
$pageTitle = $pageTitle ?? 'Agenda Clinica';
$operationTitle = $operationTitle ?? 'Operacao de agenda';
$operationDescription = $operationDescription ?? 'Cadastro rapido.';
$calendarTitle = $calendarTitle ?? 'Grade semanal';
$calendarTitleProfessional = trim((string) ($calendarTitleProfessional ?? ''));
$calendarDescription = $calendarDescription ?? 'Clique ou arraste.';
$calendarResetPath = $calendarResetPath ?? app_current_page();
$operationEmptyMessage = $operationEmptyMessage ?? '';
$reportReferenceDate = $reportReferenceDate ?? date('Y-m-d');
$agendaWhatsappLinks = $agendaWhatsappLinks ?? ['day' => null, 'week' => null, 'month' => null];
$reportPeriodLabels = $reportPeriodLabels ?? ['day' => '', 'week' => '', 'month' => ''];
$scheduleMetricsHtml = $scheduleMetricsHtml ?? '';
$selectedProfessionalName = app_first_name((string) ($currentProfessional['nome'] ?? 'Profissional'));
$selectedProfessionalPhone = app_normalize_phone((string) ($currentProfessional['telefone'] ?? ''));
$autoSubmitProfessionalSelect = $autoSubmitProfessionalSelect ?? false;
$appointmentFormDateValue = $appointmentFormDateValue ?? (string) ($selectedAppointment['data_agendamento'] ?? $weekStart);
$appointmentFormTimeValue = $appointmentFormTimeValue ?? app_time_br((string) ($selectedAppointment['hora_inicio'] ?? '08:00:00'));
$calendarOnlyLayout = $calendarOnlyLayout ?? false;
$showTopCalendarFilters = $showTopCalendarFilters ?? false;
$showCalendarFilterModal = $showCalendarFilterModal ?? false;
$calendarFilterServices = $calendarFilterServices ?? $selectedProfessionalServices;
$selectedCalendarServiceId = (int) ($selectedCalendarServiceId ?? 0);
$showCalendarServiceSelect = $showCalendarServiceSelect ?? (count($calendarFilterServices) > 1);
$hasCalendarGroupServices = array_values(array_filter(
    $calendarFilterServices,
    static fn (array $service): bool => ($service['tipo_agendamento'] ?? 'individual') === 'grupo'
)) !== [];
$selectedCalendarServiceIsGroup = false;
foreach ($calendarFilterServices as $calendarFilterService) {
    if ((int) ($calendarFilterService['id'] ?? 0) === $selectedCalendarServiceId) {
        $selectedCalendarServiceIsGroup = ($calendarFilterService['tipo_agendamento'] ?? 'individual') === 'grupo';
        break;
    }
}
$showOperationPanel = $showOperationPanel ?? true;
$appointmentModalHighlightSubtitle = $appointmentModalHighlightSubtitle ?? false;
$appointmentUnavailableMessage = $appointmentUnavailableMessage ?? 'Este horario ainda nao foi liberado para agendamento.';
$autoOpenSelectedAppointmentModal = $autoOpenSelectedAppointmentModal ?? false;
$floatingFlashMessages = $floatingFlashMessages ?? false;
$autoOpenCalendarFilterModal = $autoOpenCalendarFilterModal ?? false;
$calendarFilterMessage = $calendarFilterMessage ?? '';
$appointmentReturnTo = $appointmentReturnTo ?? '';
$appointmentTimeStepSeconds = max(60, (int) ($appointmentTimeStepSeconds ?? 300));
$menuFlashMode = $floatingFlashMessages ? 'manual' : ($menuFlashMode ?? 'inline');
$calendarNavigationParams = $calendarNavigationQuery ?? [];
$calendarPrevUrl = app_current_page() . '?' . app_build_query(array_merge($calendarNavigationParams, [
    'professional_id' => $selectedProfessionalId,
    'week_start' => date('Y-m-d', strtotime($weekStart . ' -7 days')),
]));
$calendarNextUrl = app_current_page() . '?' . app_build_query(array_merge($calendarNavigationParams, [
    'professional_id' => $selectedProfessionalId,
    'week_start' => date('Y-m-d', strtotime($weekStart . ' +7 days')),
]));
$bodyClass = $hasAvailabilityOperation && !$hasAppointmentOperation ? 'agenda-availability-page' : 'agenda-schedule-page';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= app_h($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/clinic-modern.css" rel="stylesheet">
<link href="assets/schedule-page.css" rel="stylesheet">
</head>
<body class="<?= app_h($bodyClass) ?>">

<?php include 'partials/menu.php'; ?>
<?php $scheduleFlash = $flash ?? null; ?>

<div class="container-fluid agenda-module">
    <?php if ($showTopCalendarFilters): ?>
        <div class="card agenda-panel agenda-toolbar-panel">
            <div class="card-body">
                <div class="agenda-toolbar-layout">
                    <div class="agenda-toolbar-copy">
                        <h4><?= app_h($pageTitle) ?></h4>
                        <p><?= app_h($calendarDescription) ?></p>
                    </div>
                    <form method="GET" class="agenda-toolbar-form" id="calendarFilterForm">
                        <div class="agenda-toolbar-field">
                            <label for="calendarProfessional">Profissional</label>
                            <select name="professional_id" id="calendarProfessional" class="form-select" data-page-autofocus="1" <?= app_is_professional_user() ? 'disabled' : '' ?>>
                                <?php foreach ($professionals as $professional): ?>
                                    <option value="<?= (int) $professional['id'] ?>" <?= (int) $selectedProfessionalId === (int) $professional['id'] ? 'selected' : '' ?>>
                                        <?= app_h(app_first_name((string) $professional['nome'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (app_is_professional_user()): ?>
                                <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                            <?php endif; ?>
                        </div>
                        <div class="agenda-toolbar-field">
                            <label for="calendarWeekStart">Semana</label>
                            <input type="date" name="week_start" id="calendarWeekStart" class="form-control" value="<?= app_h($weekStart) ?>">
                        </div>
                        <input type="hidden" name="report_date" id="calendarReportDate" value="<?= app_h($reportReferenceDate) ?>">
                        <button class="btn btn-primary" type="submit">Atualizar grade</button>
                        <a class="btn btn-outline-secondary" href="<?= app_h($calendarResetPath) ?>">Semana atual</a>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="agenda-main-grid<?= $calendarOnlyLayout ? ' is-calendar-only' : '' ?>">
        <div class="card agenda-panel agenda-hidden-panel"<?= $showOperationPanel ? '' : ' hidden' ?>>
            <div class="card-header">
                <div class="agenda-panel-title">
                    <div>
                        <h5><?= app_h($operationTitle) ?></h5>
                        <p><?= app_h($operationDescription) ?></p>
                    </div>
                    <?php if (($selectedAppointment && $hasAppointmentOperation) || ($selectedAvailability && $hasAvailabilityOperation)): ?>
                        <span class="agenda-chip">Editando</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body agenda-operation-body">
                <?php if (!$showTopCalendarFilters && $showOperationPanel): ?>
                    <form method="GET" class="agenda-filter-form" id="calendarFilterForm">
                        <div class="agenda-section-card">
                            <div class="agenda-section-head">
                                <div>
                                    <h6>Pesquisa da grade</h6>
                                </div>
                            </div>
                            <div class="agenda-filter-grid">
                                    <?php if (app_is_professional_user()): ?>
                                <div>
                                    <label for="calendarProfessionalSearch">Profissional</label>
                                        <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                                        <input type="text" id="calendarProfessionalSearch" class="form-control" value="<?= app_h((string) ($currentProfessional['nome'] ?? $selectedProfessionalName)) ?>" readonly>
                                    <?php else: ?>
                                <div>
                                    <label for="calendarProfessionalSearch">Profissional</label>
                                    <select name="professional_id" id="calendarProfessional" class="form-select" style="display:none;" tabindex="-1" aria-hidden="true">
                                        <option value="">Selecione o profissional</option>
                                        <?php foreach ($professionals as $professional): ?>
                                            <option value="<?= (int) $professional['id'] ?>" <?= (int) $selectedProfessionalId === (int) $professional['id'] ? 'selected' : '' ?>>
                                                <?= app_h((string) $professional['nome']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="autocomplete-wrap">
                                        <input
                                            type="text"
                                            id="calendarProfessionalSearch"
                                            class="form-control"
                                            data-page-autofocus="1"
                                            placeholder="Digite o nome do profissional"
                                            autocomplete="off"
                                            value="<?= app_h((string) ($currentProfessional['nome'] ?? '')) ?>"
                                        >
                                        <div class="autocomplete-menu" id="calendarProfessionalMenu"></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <label for="calendarWeekStart">Semana</label>
                                    <input type="date" name="week_start" id="calendarWeekStart" class="form-control" value="<?= app_h($weekStart) ?>">
                                </div>
                                <input type="hidden" name="report_date" id="calendarReportDate" value="<?= app_h($reportReferenceDate) ?>">
                                <div class="full agenda-actions">
                                    <button class="btn btn-primary" type="submit">Atualizar grade</button>
                                    <a class="btn btn-outline-secondary" href="<?= app_h($calendarResetPath) ?>">Semana atual</a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>

                <div class="agenda-section-card">
                    <div class="agenda-section-head">
                        <div>
                            <h6>Operacao</h6>
                        </div>
                    </div>

                    <?php if ($showOperationTabs): ?>
                        <div class="agenda-mode-switch">
                            <button type="button" class="btn btn-outline-secondary<?= $operationMode === 'appointment' ? ' is-active' : '' ?>" data-schedule-mode="appointment">Agendamento</button>
                            <button type="button" class="btn btn-outline-secondary<?= $operationMode === 'availability' ? ' is-active' : '' ?>" data-schedule-mode="availability">Liberacao</button>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasAppointmentOperation): ?>
                    <div id="appointmentFormHost">
                    <form method="POST" class="agenda-form-grid agenda-form-panel" id="appointmentForm" data-mode-panel="appointment"<?= $showOperationTabs && $operationMode !== 'appointment' ? ' style="display:none;"' : '' ?>>
                        <input type="hidden" name="action" id="appointmentAction" value="<?= $selectedAppointment ? 'update_appointment' : 'create_appointment' ?>">
                        <input type="hidden" name="appointment_id" id="appointmentId" value="<?= (int) ($selectedAppointment['id'] ?? 0) ?>">
                        <input type="hidden" name="profissional_id" id="appointmentProfessional" value="<?= (int) $selectedProfessionalId ?>">
                        <input type="hidden" name="return_to" id="appointmentReturnTo" value="<?= app_h($appointmentReturnTo) ?>">

                        <div class="full">
                            <label for="appointmentPatientSearch">Paciente</label>
                            <input
                                type="hidden"
                                name="cliente_id"
                                id="appointmentPatient"
                                value="<?= $selectedAppointmentPatientId ?>"
                                data-name="<?= app_h($selectedAppointmentName) ?>"
                                data-phone="<?= app_h($selectedAppointmentPhone) ?>"
                                data-pref-dia="<?= app_h($selectedAppointmentPreferenceDay) ?>"
                                data-pref-hora="<?= app_h($selectedAppointmentPreferenceTime) ?>"
                            >
                            <div class="autocomplete-wrap">
                                <input
                                    type="text"
                                    id="appointmentPatientSearch"
                                    class="form-control"
                                    autocomplete="off"
                                    required
                                    value="<?= app_h($selectedAppointmentName) ?>"
                                    title="Digite parte do nome e escolha o paciente da lista."
                                    placeholder="Digite para buscar o paciente"
                                >
                                <div class="autocomplete-menu" id="appointmentPatientMenu"></div>
                            </div>
                        </div>

                        <div id="patientPreferenceCard" class="agenda-patient-preference full">
                            <strong>Preferencia do paciente</strong>
                            <small id="patientPreferenceText">Sem preferencia cadastrada.</small>
                        </div>

                        <div class="agenda-readonly-box full">
                            <div>
                                <label for="appointmentName">Nome</label>
                                <input type="text" name="cliente_nome" id="appointmentName" class="form-control bg-patient-readonly" value="<?= app_h($selectedAppointmentName) ?>" readonly required>
                            </div>
                            <div>
                                <label for="appointmentPhone">Telefone</label>
                                <input type="text" name="cliente_telefone" id="appointmentPhone" class="form-control bg-patient-readonly" value="<?= app_h($selectedAppointmentPhone) ?>" readonly required>
                            </div>
                            <div>
                                <label for="appointmentPreferenceDay">Dia da semana de preferencia</label>
                                <input type="text" id="appointmentPreferenceDay" class="form-control bg-patient-readonly" value="<?= app_h($selectedAppointmentPatientId > 0 ? ($selectedAppointmentPreferenceDay !== '' ? $selectedAppointmentPreferenceDay : 'Nao informado') : '') ?>" readonly>
                            </div>
                            <div>
                                <label for="appointmentPreferenceTime">Horario de preferencia</label>
                                <input type="text" id="appointmentPreferenceTime" class="form-control bg-patient-readonly" value="<?= app_h($selectedAppointmentPatientId > 0 ? ($selectedAppointmentPreferenceTime !== '' ? $selectedAppointmentPreferenceTime : 'Nao informado') : '') ?>" readonly>
                            </div>
                        </div>

                        <div>
                            <label for="appointmentService">Servico</label>
                            <select name="servico_id" id="appointmentService" class="form-select" required>
                                <?php $individualServices = array_values(array_filter($selectedProfessionalServices, static fn (array $service): bool => ($service['tipo_agendamento'] ?? 'individual') !== 'grupo')); ?>
                                <?php if (!empty($individualServices)): ?>
                                    <?php foreach ($individualServices as $service): ?>
                                        <option value="<?= (int) $service['id'] ?>" <?= (int) ($selectedAppointment['servico_id'] ?? 0) === (int) $service['id'] ? 'selected' : '' ?>>
                                            <?= app_h((string) $service['nome']) ?> (<?= (int) $service['tempo_minutos'] ?> min)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">Sem servicos individuais vinculados</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div>
                            <label for="appointmentStatus">Status</label>
                            <select name="status" id="appointmentStatus" class="form-select">
                                <?php foreach ($statuses as $value => $label): ?>
                                    <option value="<?= app_h($value) ?>" <?= ($selectedAppointment['status'] ?? 'agendado') === $value ? 'selected' : '' ?>>
                                        <?= app_h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="full" id="appointmentGuideWrap" hidden>
                            <label for="appointmentGuide">Guia do atendimento</label>
                            <select
                                name="guia_atendimento_id"
                                id="appointmentGuide"
                                class="form-select"
                                title="Escolha a guia ativa que sera usada para registrar o atendimento realizado."
                            >
                                <option value="">Selecione o paciente primeiro</option>
                            </select>
                            <div class="agenda-helper-note">Obrigatoria quando o status for Realizado.</div>
                        </div>

                        <div>
                            <label for="appointmentDate">Data</label>
                            <input type="date" name="data_agendamento" id="appointmentDate" class="form-control" value="<?= app_h($appointmentFormDateValue) ?>" min="<?= app_h(date('Y-m-d')) ?>" required>
                        </div>

                        <div>
                            <label for="appointmentTime">Horario</label>
                            <input type="time" name="hora_inicio" id="appointmentTime" class="form-control" value="<?= app_h($appointmentFormTimeValue) ?>" step="<?= (int) $appointmentTimeStepSeconds ?>" required>
                        </div>

                        <div class="full">
                            <label for="appointmentNotes">Observacoes</label>
                            <textarea name="observacoes" id="appointmentNotes"><?= app_h((string) ($selectedAppointment['observacoes'] ?? '')) ?></textarea>
                        </div>

                        <div class="full agenda-actions">
                            <button class="btn btn-outline-secondary" type="button" id="appointmentResetBtn">Novo</button>
                            <a
                                class="btn btn-outline-success<?= $selectedAppointment ? '' : ' disabled' ?>"
                                id="appointmentWhatsappBtn"
                                href="#"
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-disabled="<?= $selectedAppointment ? 'false' : 'true' ?>"
                                style="display: <?= $selectedAppointment ? 'inline-flex' : 'none' ?>;"
                            >WhatsApp paciente</a>
                            <button class="btn btn-outline-danger" type="button" id="appointmentDeleteBtn" style="display: <?= $selectedAppointment ? 'inline-flex' : 'none' ?>;">Excluir</button>
                            <button class="btn btn-primary" type="submit" id="appointmentSubmitBtn"><?= $selectedAppointment ? 'Atualizar' : 'Salvar' ?></button>
                        </div>
                    </form>
                    </div>
                    <?php endif; ?>

                    <?php if ($hasAvailabilityOperation): ?>
                    <form method="POST" class="agenda-form-grid agenda-form-panel" id="availabilityForm" data-mode-panel="availability"<?= $showOperationTabs && $operationMode !== 'availability' ? ' style="display:none;"' : '' ?>>
                        <input type="hidden" name="action" id="availabilityAction" value="<?= $selectedAvailability ? 'update_availability' : 'create_availability' ?>">
                        <input type="hidden" name="availability_id" id="availabilityId" value="<?= (int) ($selectedAvailability['id'] ?? 0) ?>">
                        <input type="hidden" name="profissional_id" id="availabilityProfessional" value="<?= (int) $selectedProfessionalId ?>">
                        <input type="hidden" name="ativo" value="1">

                        <div>
                            <label for="availabilityScope">Abrangencia</label>
                            <select name="abrangencia" id="availabilityScope" class="form-select">
                                <option value="data_unica">Somente esta data</option>
                                <option value="intervalo_datas">Intervalo de datas</option>
                                <option value="semana_inteira">Semana inteira</option>
                                <option value="mes_inteiro">Mes inteiro</option>
                            </select>
                            <div class="agenda-helper-note">Use uma data, um intervalo, uma semana ou um mes inteiro.</div>
                        </div>

                        <div>
                            <label for="availabilityPeriod">Periodo</label>
                            <select name="periodo_liberacao" id="availabilityPeriod" class="form-select">
                                <option value="personalizado">Horario personalizado</option>
                                <option value="manha">So manha</option>
                                <option value="tarde">So tarde</option>
                                <option value="dia_todo">Dia todo</option>
                            </select>
                            <div class="agenda-helper-note">Escolha rapido: um horario, um turno ou o dia inteiro.</div>
                        </div>

                        <div class="full" id="availabilityBusinessDaysWrap" style="display:none;">
                            <label class="agenda-inline-check" for="availabilityBusinessDays">
                                <input type="checkbox" name="somente_dias_uteis" id="availabilityBusinessDays" checked>
                                <span>Ao repetir, liberar somente dias uteis quando nenhum dia especifico estiver marcado</span>
                            </label>
                        </div>

                        <div class="full" id="availabilityWeekdaysWrap" style="display:none;">
                            <label>Dias da semana</label>
                            <div class="agenda-weekday-options">
                                <?php foreach ([1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sab', 7 => 'Dom'] as $dayValue => $dayLabel): ?>
                                    <label class="agenda-weekday-option">
                                        <input type="checkbox" name="dias_semana[]" value="<?= (int) $dayValue ?>" <?= $dayValue <= 5 ? 'checked' : '' ?>>
                                        <span><?= app_h($dayLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="agenda-helper-note">Opcional. Para liberar terca, quinta e sexta no mes, marque Ter, Qui e Sex.</div>
                        </div>

                        <div id="availabilityDateWrap">
                            <label for="availabilityDate">Data inicial</label>
                            <input type="date" name="data_disponivel" id="availabilityDate" class="form-control" value="<?= app_h((string) ($selectedAvailability['data_disponivel'] ?? $weekStart)) ?>" required>
                        </div>

                        <div id="availabilityEndDateWrap" style="display:none;">
                            <label for="availabilityEndDate">Data final</label>
                            <input type="date" name="data_final" id="availabilityEndDate" class="form-control" value="<?= app_h((string) ($selectedAvailability['data_disponivel'] ?? $weekEnd ?? $weekStart)) ?>">
                        </div>

                        <div id="availabilityMonthWrap" style="display:none;">
                            <label for="availabilityMonth">Mes a liberar</label>
                            <input type="month" name="mes_referencia" id="availabilityMonth" class="form-control" value="<?= app_h(substr((string) ($selectedAvailability['data_disponivel'] ?? $weekStart), 0, 7)) ?>">
                        </div>

                        <div id="availabilityStartWrap">
                            <label for="availabilityStart">Inicio</label>
                            <input type="time" name="hora_inicio" id="availabilityStart" class="form-control" value="<?= app_h(app_time_br((string) ($selectedAvailability['hora_inicio'] ?? '08:00:00'))) ?>" step="300" required>
                        </div>

                        <div id="availabilityEndWrap">
                            <label for="availabilityEnd">Fim</label>
                            <input type="time" name="hora_fim" id="availabilityEnd" class="form-control" value="<?= app_h(app_time_br((string) ($selectedAvailability['hora_fim'] ?? '09:00:00'))) ?>" step="300" required>
                        </div>

                        <div>
                            <label for="availabilityOwner">Responsavel</label>
                            <input type="text" id="availabilityOwner" class="form-control bg-patient-readonly" value="<?= app_h((string) ($currentProfessional['nome'] ?? '')) ?>" readonly>
                        </div>

                        <div class="full">
                            <label for="availabilityNotes">Observacoes</label>
                            <textarea name="observacoes" id="availabilityNotes"><?= app_h((string) ($selectedAvailability['observacoes'] ?? '')) ?></textarea>
                        </div>

                        <div class="full agenda-actions">
                            <button class="btn btn-outline-secondary" type="button" id="availabilityResetBtn">Nova faixa</button>
                            <button class="btn btn-outline-danger" type="button" id="availabilityDeletePeriodBtn">Excluir periodo</button>
                            <button class="btn btn-outline-danger" type="button" id="availabilityDeleteBtn" style="display: <?= $selectedAvailability ? 'inline-flex' : 'none' ?>;">Excluir</button>
                            <button class="btn btn-primary" type="submit" id="availabilitySubmitBtn"><?= $selectedAvailability ? 'Atualizar' : 'Liberar' ?></button>
                        </div>
                    </form>
                    <?php endif; ?>

                    <?php if (!$hasAppointmentOperation && !$hasAvailabilityOperation && $operationEmptyMessage !== ''): ?>
                        <div class="text-muted small mt-2"><?= app_h($operationEmptyMessage) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card agenda-panel agenda-calendar-card">
            <div class="card-header">
                <div class="agenda-panel-title">
                    <div>
                        <h5 class="agenda-calendar-title">
                            <span><?= app_h($calendarTitle) ?></span>
                            <?php if ($calendarTitleProfessional !== ''): ?>
                                <span class="agenda-calendar-professional">- <?= app_h($calendarTitleProfessional) ?></span>
                            <?php endif; ?>
                        </h5>
                        <?php if (!$showTopCalendarFilters && !$showCalendarFilterModal): ?>
                            <p><?= app_h($calendarDescription) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="agenda-header-actions">
                        <span class="agenda-chip"><?= app_h(app_date_br($weekStart)) ?> a <?= app_h(app_date_br($weekEnd)) ?></span>
                        <?php if ($showCalendarFilterModal): ?>
                            <button class="btn agenda-actions-toggle" type="button" data-bs-toggle="modal" data-bs-target="#calendarFilterModal">Filtros</button>
                        <?php endif; ?>
                        <div class="dropdown">
                            <button class="btn agenda-actions-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Acoes</button>
                            <ul class="dropdown-menu dropdown-menu-end agenda-actions-menu">
                                <li class="agenda-action-menu-title">Saida da agenda</li>
                                <li>
                                    <button type="button" class="dropdown-item" id="printCalendarBtn">
                                        <strong>Imprimir agenda completa</strong>
                                        <small>Gera a imagem inteira antes de abrir a impressao.</small>
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item" id="exportJpgBtn">
                                        <strong>Baixar JPG completo</strong>
                                        <small>Inclui horarios que estao fora da tela.</small>
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item" id="exportPdfBtn">
                                        <strong>Baixar PDF</strong>
                                        <small>Usa a mesma imagem completa da agenda.</small>
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button type="button" class="dropdown-item agenda-action-primary" id="shareWhatsappJpgBtn">
                                        <strong>Compartilhar JPG no WhatsApp</strong>
                                        <small>Abre o compartilhamento com a agenda em imagem JPG.</small>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="agenda-calendar-report" id="agendaCalendarReport" data-file-name="<?= app_h('agenda-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($selectedProfessionalName ?: 'profissional')) . '-' . $weekStart) ?>">
                    <div class="agenda-calendar-stage">
                        <a class="calendar-nav-button is-prev" href="<?= app_h($calendarPrevUrl) ?>" title="Semana anterior" aria-label="Semana anterior">&lt;</a>
                        <a class="calendar-nav-button is-next" href="<?= app_h($calendarNextUrl) ?>" title="Proxima semana" aria-label="Proxima semana">&gt;</a>
                        <div class="agenda-calendar-wrapper">
                            <?= $calendarHtml ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($hasAppointmentOperation): ?>
<div class="modal fade agenda-appointment-modal" id="appointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="appointmentModalTitle">Novo agendamento</h5>
                    <p class="modal-subtitle<?= $appointmentModalHighlightSubtitle ? ' is-highlight' : '' ?>" id="appointmentModalSubtitle">Selecione paciente, servico e horario para salvar.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div id="appointmentModalMount"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($showCalendarFilterModal): ?>
<div class="modal fade agenda-filter-modal" id="calendarFilterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filtros da agenda</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <?php if (trim((string) $calendarFilterMessage) !== ''): ?>
                    <div class="alert alert-warning py-2 mb-3"><?= app_h((string) $calendarFilterMessage) ?></div>
                <?php endif; ?>
                <form method="GET" class="agenda-toolbar-form" id="calendarFilterForm">
                    <div class="agenda-toolbar-field">
                        <label for="calendarProfessionalSearch">Profissional</label>
                        <?php if (app_is_professional_user()): ?>
                            <input type="hidden" name="professional_id" value="<?= (int) $selectedProfessionalId ?>">
                            <input type="text" id="calendarProfessionalSearch" class="form-control" value="<?= app_h((string) ($currentProfessional['nome'] ?? $selectedProfessionalName)) ?>" readonly>
                        <?php else: ?>
                        <select name="professional_id" id="calendarProfessional" class="form-select" style="display:none;" tabindex="-1" aria-hidden="true">
                            <option value="">Selecione o profissional</option>
                            <?php foreach ($professionals as $professional): ?>
                                <option value="<?= (int) $professional['id'] ?>" <?= (int) $selectedProfessionalId === (int) $professional['id'] ? 'selected' : '' ?>>
                                    <?= app_h((string) $professional['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="autocomplete-wrap">
                            <input
                                type="text"
                                id="calendarProfessionalSearch"
                                class="form-control"
                                data-page-autofocus="1"
                                placeholder="Digite o nome do profissional"
                                autocomplete="off"
                                value="<?= app_h((string) ($currentProfessional['nome'] ?? '')) ?>"
                            >
                            <div class="autocomplete-menu" id="calendarProfessionalMenu"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="agenda-toolbar-field" id="calendarServiceField" <?= $showCalendarServiceSelect ? '' : 'style="display:none;"' ?>>
                        <label for="calendarService">Servico</label>
                        <select name="service_id" id="calendarService" class="form-select" <?= $showCalendarServiceSelect ? 'required' : '' ?>>
                            <option value="">Selecione o servico</option>
                            <?php foreach ($calendarFilterServices as $service): ?>
                                <option
                                    value="<?= (int) $service['id'] ?>"
                                    data-tipo="<?= app_h((string) ($service['tipo_agendamento'] ?? 'individual')) ?>"
                                    data-capacidade="<?= (int) ($service['capacidade_agendamento'] ?? 1) ?>"
                                    <?= $selectedCalendarServiceId > 0 && (int) $service['id'] === $selectedCalendarServiceId ? 'selected' : '' ?>
                                >
                                    <?= app_h((string) $service['nome']) ?> - <?= ($service['tipo_agendamento'] ?? 'individual') === 'grupo' ? 'Grupo' : 'Individual' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="agenda-toolbar-field" id="calendarGroupQuickField" <?= $hasCalendarGroupServices && $selectedCalendarServiceIsGroup ? '' : 'style="display:none;"' ?>>
                        <label for="calendarGroupQuick">Modelo de grupo</label>
                        <label class="form-check d-flex align-items-center gap-2 mb-0" style="min-height:38px;">
                            <input class="form-check-input mt-0" type="checkbox" name="modelo" id="calendarGroupQuick" value="rapido" checked>
                            <span class="form-check-label small">Cronograma rapido</span>
                        </label>
                    </div>
                    <div class="agenda-toolbar-field">
                        <label for="calendarWeekStart">Semana</label>
                        <input type="date" name="week_start" id="calendarWeekStart" class="form-control" value="<?= app_h($weekStart) ?>">
                    </div>
                    <input type="hidden" name="report_date" id="calendarReportDate" value="<?= app_h($reportReferenceDate) ?>">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-primary flex-fill" type="submit">Aplicar</button>
                        <a class="btn btn-outline-secondary flex-fill" href="<?= app_h($calendarResetPath) ?>">Semana atual</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="agenda-hover-clock" id="agendaHoverClock" hidden></div>
<div class="agenda-inline-notice" id="agendaInlineNotice" hidden></div>

<form id="moveAppointmentForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="move_appointment">
    <input type="hidden" name="skip_whatsapp" value="1">
    <input type="hidden" name="return_to" value="<?= app_h($appointmentReturnTo) ?>">
    <input type="hidden" name="appointment_id" id="moveAppId">
    <input type="hidden" name="data_agendamento" id="moveAppDate">
    <input type="hidden" name="hora_inicio" id="moveAppTime">
</form>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
<?php include __DIR__ . '/page-script.php'; ?>

</body>
</html>
