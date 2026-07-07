<div class="modal fade" id="attendanceCalendarModal" tabindex="-1" aria-labelledby="attendanceCalendarLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="attendanceCalendarLabel">Employee Monthly Calendar</h5>
                    <p class="mb-0 attendance-calendar-subtitle" id="attendanceCalendarSubtitle">Loading attendance details...</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-3 mb-3" id="attendanceCalendarSummary"></div>
                <div class="attendance-calendar-weekdays mb-2">
                    <div>Sun</div>
                    <div>Mon</div>
                    <div>Tue</div>
                    <div>Wed</div>
                    <div>Thu</div>
                    <div>Fri</div>
                    <div>Sat</div>
                </div>
                <div class="attendance-calendar-grid" id="attendanceCalendarGrid"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="attendanceDayDetailsModal" tabindex="-1" aria-labelledby="attendanceDayDetailsLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="attendanceDayDetailsLabel">Attendance Day Details</h5>
                    <p class="mb-0 attendance-calendar-subtitle" id="attendanceDayDetailsSubtitle">Loading details...</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3" id="attendanceDayDetailsBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="attendanceInfoModal" tabindex="-1" aria-labelledby="attendanceInfoModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="attendanceInfoModalLabel">Attendance Detail</h5>
                    <p class="mb-0 attendance-calendar-subtitle" id="attendanceInfoModalSubtitle">Quick detail view</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3" id="attendanceInfoModalBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="attendanceImagePreviewModal" tabindex="-1" aria-labelledby="attendanceImagePreviewLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title" id="attendanceImagePreviewLabel">Attendance Image</h5>
                    <p class="mb-0 attendance-calendar-subtitle" id="attendanceImagePreviewSubtitle">Preview</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3 text-center">
                <img id="attendanceImagePreviewElement" src="" alt="Attendance preview"
                    class="img-fluid attendance-full-image" data-admin-image-fallback
                    data-admin-image-alt="No Image">
            </div>
        </div>
    </div>
</div>

<style>
    #attendanceCalendarModal .modal-content,
    #attendanceDayDetailsModal .modal-content,
    #attendanceInfoModal .modal-content,
    #attendanceImagePreviewModal .modal-content {
        background: var(--admin-surface-color, #ffffff) !important;
        color: var(--admin-text-color, #172033) !important;
        border: 1px solid var(--admin-border-color, rgba(23, 32, 51, 0.08)) !important;
        border-radius: 22px;
    }

    #attendanceCalendarModal .modal-title,
    #attendanceDayDetailsModal .modal-title,
    #attendanceInfoModal .modal-title,
    #attendanceImagePreviewModal .modal-title,
    .attendance-calendar-subtitle,
    .attendance-calendar-summary-card h4,
    .attendance-calendar-summary-card p,
    .attendance-calendar-day,
    .attendance-calendar-day small,
    .attendance-calendar-day .attendance-calendar-day-number,
    .attendance-calendar-day .attendance-calendar-meta,
    .attendance-day-details-card h6,
    .attendance-day-details-card p,
    .attendance-day-details-card a,
    .attendance-day-details-card button {
        color: var(--admin-text-color, #172033) !important;
    }

    .attendance-calendar-summary-card {
        background: var(--admin-background-color, #f7f9fc);
        border: 1px solid var(--admin-border-color, rgba(23, 32, 51, 0.08));
        border-radius: 18px;
    }

    .attendance-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 12px;
    }

    .attendance-calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 12px;
    }

    .attendance-calendar-weekdays div {
        color: var(--admin-muted-text-color, #172033) !important;
        font-size: 0.82rem;
        font-weight: 700;
        text-align: center;
        padding: 8px 0;
    }

    .attendance-calendar-day {
        border-radius: 18px;
        border: 1px solid var(--admin-border-color, rgba(92, 107, 126, 0.15));
        padding: 14px;
        min-height: 230px;
        background: var(--admin-surface-color, #fff);
        box-shadow: 0 12px 30px rgba(23, 32, 51, 0.04);
        display: grid;
        grid-template-rows: auto 1fr auto;
        gap: 10px;
        overflow: hidden;
    }

    .attendance-calendar-empty {
        border-radius: 18px;
        min-height: 230px;
        background: rgba(var(--admin-primary-color-rgb, 13, 110, 253), 0.05);
        border: 1px dashed var(--admin-border-color, rgba(92, 107, 126, 0.14));
    }

    .attendance-calendar-day.full_day {
        background: rgba(25, 135, 84, 0.08);
        border-color: rgba(25, 135, 84, 0.2);
    }

    .attendance-calendar-day.full_day_remote {
        background: rgba(13, 110, 253, 0.08);
        border-color: rgba(13, 110, 253, 0.2);
    }

    .attendance-calendar-day.half_day {
        background: rgba(255, 193, 7, 0.12);
        border-color: rgba(255, 193, 7, 0.24);
    }

    .attendance-calendar-day.single_punch {
        background: rgba(13, 110, 253, 0.08);
        border-color: rgba(13, 110, 253, 0.18);
    }

    .attendance-calendar-day.absent {
        background: rgba(220, 53, 69, 0.08);
        border-color: rgba(220, 53, 69, 0.18);
    }

    .attendance-calendar-day.weekoff {
        background: rgba(108, 117, 125, 0.08);
        border-color: rgba(108, 117, 125, 0.2);
    }

    .attendance-calendar-day.not_joined {
        background: rgba(108, 117, 125, 0.06);
        border-color: rgba(108, 117, 125, 0.16);
    }

    .attendance-calendar-day.future {
        background: rgba(108, 117, 125, 0.08);
        border-color: rgba(108, 117, 125, 0.18);
    }

    .attendance-calendar-day-number {
        font-size: 1.35rem;
        font-weight: 700;
    }

    .attendance-calendar-meta {
        color: var(--admin-muted-text-color, rgba(23, 32, 51, 0.72)) !important;
    }

    .attendance-calendar-label {
        display: inline-flex;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
        margin-top: 8px;
    }

    .attendance-calendar-label.full_day {
        color: #0f6a42;
        background: rgba(25, 135, 84, 0.16);
    }

    .attendance-calendar-label.full_day_remote {
        color: #0c58ca;
        background: rgba(13, 110, 253, 0.16);
    }

    .attendance-calendar-label.half_day {
        color: #8a6300;
        background: rgba(255, 193, 7, 0.22);
    }

    .attendance-calendar-label.single_punch {
        color: #0c58ca;
        background: rgba(13, 110, 253, 0.16);
    }

    .attendance-calendar-label.absent {
        color: #b42318;
        background: rgba(220, 53, 69, 0.16);
    }

    .attendance-calendar-label.weekoff {
        color: #525f7a;
        background: rgba(108, 117, 125, 0.18);
    }

    .attendance-calendar-label.not_joined {
        color: #525f7a;
        background: rgba(108, 117, 125, 0.14);
    }

    .attendance-calendar-label.future {
        color: #6c757d;
        background: rgba(108, 117, 125, 0.16);
    }

    .attendance-calendar-regularized-badge {
        display: inline-flex;
        margin-top: 8px;
        padding: 3px 9px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        color: #7a1f1f;
        background: rgba(198, 100, 43, 0.14);
        border: 1px solid rgba(198, 100, 43, 0.22);
    }

    .attendance-day-details-card {
        background: var(--admin-background-color, #f7f9fc);
        border: 1px solid var(--admin-border-color, rgba(23, 32, 51, 0.08));
        border-radius: 18px;
        padding: 16px;
        height: 100%;
    }

    .attendance-calendar-day-body {
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .attendance-calendar-day-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: flex-end;
    }

    .attendance-calendar-day-actions .btn {
        width: 100%;
        white-space: normal;
        line-height: 1.2;
        margin-top: 0 !important;
        justify-content: center;
    }

    .attendance-meta-link {
        font-weight: 600;
        color: #0d6efd !important;
        text-decoration: underline;
        text-underline-offset: 3px;
    }

    .attendance-meta-link:hover {
        color: #0a58ca !important;
    }

    .attendance-meta-empty {
        color: var(--admin-muted-text-color, rgba(23, 32, 51, 0.55)) !important;
    }

    .attendance-image-thumb {
        border: 1px solid var(--admin-border-color, rgba(23, 32, 51, 0.08));
        border-radius: 18px;
        overflow: hidden;
        background: var(--admin-surface-color, #fff);
        box-shadow: 0 10px 26px rgba(23, 32, 51, 0.05);
    }

    .attendance-image-thumb button {
        display: block;
        width: 100%;
        border: 0;
        background: transparent;
        padding: 0;
        text-align: left;
    }

    .attendance-image-thumb img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        display: block;
        background: var(--admin-background-color, #f7f9fc);
    }

    .attendance-image-thumb-copy {
        padding: 12px 14px 14px;
    }

    .attendance-image-thumb-copy strong {
        display: block;
        margin-bottom: 4px;
    }

    .attendance-full-image {
        max-height: 75vh;
        border-radius: 18px;
        box-shadow: 0 18px 40px rgba(23, 32, 51, 0.16);
    }

    .attendance-info-content {
        white-space: pre-line;
        line-height: 1.6;
        color: var(--admin-text-color, #172033);
    }

    @media (max-width: 991.98px) {
        .attendance-calendar-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .attendance-calendar-weekdays,
        .attendance-calendar-empty {
            display: none;
        }
    }

    @media (max-width: 575.98px) {
        .attendance-calendar-grid {
            grid-template-columns: minmax(0, 1fr);
        }

        .attendance-calendar-day {
            min-height: auto;
        }
    }
</style>

<script>
    const attendanceCalendarRouteTemplate = @json($calendarRouteTemplate ?? route('admin-attendance-calendar', ['empId' => '__EMP__']));
    const attendanceCalendarOverrideRoute = @json(route('admin-attendance-calendar-override'));
    const attendanceCalendarCsrfToken = @json(csrf_token());
    let attendanceCalendarModalInstance = null;
    let attendanceDayDetailsModalInstance = null;
    let attendanceInfoModalInstance = null;
    let attendanceImagePreviewModalInstance = null;
    let attendanceCalendarDays = {};
    let attendanceCalendarCurrentContext = {
        empId: '',
        month: '',
        branchId: '',
    };

    function getBootstrapModal(id, existingInstance) {
        const modalElement = document.getElementById(id);

        if (!modalElement || typeof bootstrap === 'undefined') {
            return null;
        }

        return existingInstance || new bootstrap.Modal(modalElement);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function inlineHandler(functionName, ...args) {
        const encodedArgs = args
            .map((arg) => JSON.stringify(arg ?? ''))
            .join(', ');

        return escapeHtml(`${functionName}(${encodedArgs})`);
    }

    function summaryCard(label, value) {
        return `
            <div class="col-lg col-md-4 col-sm-6">
                <div class="attendance-calendar-summary-card p-3 h-100">
                    <p class="mb-1">${escapeHtml(label)}</p>
                    <h4 class="mb-0">${escapeHtml(value)}</h4>
                </div>
            </div>
        `;
    }

    function detailLink(label, title, detail, externalUrl = '') {
        if (!label || label === '--') {
            return '<span class="attendance-meta-empty">--</span>';
        }

        return `
            <button
                type="button"
                class="btn btn-link p-0 attendance-meta-link"
                onclick="${inlineHandler('openAttendanceInfo', title, detail || label, externalUrl || '')}"
            >
                ${escapeHtml(label)}
            </button>
        `;
    }

    function imageThumb(title, imageUrl) {
        if (!imageUrl) {
            return `
                <div class="attendance-image-thumb h-100">
                    <div class="attendance-image-thumb-copy">
                        <strong>${escapeHtml(title)}</strong>
                        <span class="attendance-meta-empty">No image available.</span>
                    </div>
                </div>
            `;
        }

        return `
            <div class="attendance-image-thumb h-100">
                <button type="button" onclick="${inlineHandler('openAttendanceImagePreview', title, imageUrl)}">
                    <img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(title)}" data-admin-image-fallback data-admin-image-alt="No Image">
                </button>
                <div class="attendance-image-thumb-copy">
                    <strong>${escapeHtml(title)}</strong>
                    <a class="attendance-meta-link"
                        href="${escapeHtml(imageUrl)}"
                        target="_blank"
                        rel="noopener"
                    >
                        View full size
                    </a>
                </div>
            </div>
        `;
    }

    function calendarDayCard(day) {
        const detailsButton = day.has_details
            ? `<button type="button" class="btn btn-sm btn-primary" onclick="${inlineHandler('openAttendanceDayDetails', day.date)}">View Details</button>`
            : '';
        const markPresentButton = day.can_mark_present
            ? `<button type="button" class="btn btn-sm btn-success" onclick="${inlineHandler('markAttendanceDayPresent', day.date)}">Mark Present</button>`
            : '';
        const markFullDayButton = day.can_mark_full_day
            ? `<button type="button" class="btn btn-sm btn-success" onclick="${inlineHandler('markAttendanceDayFullDay', day.date)}">Mark Full Day</button>`
            : '';
        const markHalfDayButton = day.can_mark_half_day
            ? `<button type="button" class="btn btn-sm btn-warning" onclick="${inlineHandler('markAttendanceDayHalfDay', day.date)}">Mark Half Day</button>`
            : '';
        const markAbsentButton = day.can_mark_absent
            ? `<button type="button" class="btn btn-sm btn-danger" onclick="${inlineHandler('markAttendanceDayAbsent', day.date)}">Mark Absent</button>`
            : '';
        const clearOverrideButton = day.can_clear_day_override
            ? `<button type="button" class="btn btn-sm btn-outline-secondary" onclick="${inlineHandler('clearAttendanceDayOverride', day.date)}">Clear Override</button>`
            : '';
        const actionButtons = [detailsButton, markPresentButton, markFullDayButton, markHalfDayButton, markAbsentButton, clearOverrideButton].filter(Boolean).join('');
        const actions = actionButtons
            ? `<div class="attendance-calendar-day-actions">${actionButtons}</div>`
            : '';
        const regularizedBadge = day.is_regularized
            ? `<div class="attendance-calendar-regularized-badge">${escapeHtml(day.regularized_label ?? 'Regularized')}</div>`
            : '';

        return `
            <div class="attendance-calendar-day ${escapeHtml(day.status)}">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="attendance-calendar-day-number">${escapeHtml(day.day)}</div>
                    <span class="attendance-calendar-meta small">${escapeHtml(day.weekday)}</span>
                </div>
                <div class="attendance-calendar-day-body">
                    <div class="attendance-calendar-label ${escapeHtml(day.status)}">${escapeHtml(day.label)}</div>
                    ${regularizedBadge}
                    <div class="mt-3 small attendance-calendar-meta">Check In: ${escapeHtml(day.check_in ?? '--')}</div>
                    <div class="small attendance-calendar-meta">Check Out: ${escapeHtml(day.check_out ?? '--')}</div>
                    <div class="small attendance-calendar-meta">Worked: ${escapeHtml(day.worked_time ?? '--')}</div>
                </div>
                ${actions}
            </div>
        `;
    }

    function emptyCalendarDayCard() {
        return '<div class="attendance-calendar-empty"></div>';
    }

    function getWeekdayIndex(dateString) {
        const [year, month, day] = String(dateString).split('-').map(Number);
        return new Date(year, month - 1, day).getDay();
    }

    function renderCalendarGrid(days) {
        if (!Array.isArray(days) || days.length === 0) {
            return '';
        }

        const cells = [];
        const leadingEmptyDays = getWeekdayIndex(days[0].date);

        for (let index = 0; index < leadingEmptyDays; index += 1) {
            cells.push(emptyCalendarDayCard());
        }

        days.forEach((day) => {
            cells.push(calendarDayCard(day));
        });

        while (cells.length % 7 !== 0) {
            cells.push(emptyCalendarDayCard());
        }

        return cells.join('');
    }

    async function openAttendanceCalendar(empId, month, branchId, fromDate = '', toDate = '') {
        const subtitle = document.getElementById('attendanceCalendarSubtitle');
        const summary = document.getElementById('attendanceCalendarSummary');
        const grid = document.getElementById('attendanceCalendarGrid');
        attendanceCalendarModalInstance = getBootstrapModal('attendanceCalendarModal', attendanceCalendarModalInstance);
        const params = new URLSearchParams({
            month,
            branch_id: branchId || '',
        });

        if (fromDate) {
            params.set('from_date', fromDate);
        }

        if (toDate) {
            params.set('to_date', toDate);
        }

        const url = attendanceCalendarRouteTemplate.replace('__EMP__', encodeURIComponent(empId))
            + `?${params.toString()}`;

        attendanceCalendarCurrentContext = {
            empId,
            month,
            branchId: branchId || '',
            fromDate: fromDate || '',
            toDate: toDate || '',
        };
        subtitle.textContent = 'Loading attendance details...';
        summary.innerHTML = '';
        grid.innerHTML = '<div class="alert alert-light border mb-0">Loading calendar...</div>';
        attendanceCalendarDays = {};

        if (!attendanceCalendarModalInstance) {
            alert('Calendar modal could not be initialized.');
            return;
        }

        attendanceCalendarModalInstance.show();

        try {
            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            const payload = await response.json();
            payload.days.forEach((day) => {
                attendanceCalendarDays[day.date] = day;
            });

            subtitle.textContent = `${payload.employee.name} (${payload.employee.empId}) - ${payload.month}`
                + (payload.period?.label ? ` (${payload.period.label})` : '');
            summary.innerHTML = [
                summaryCard('Full Days', payload.summary.full_days),
                summaryCard('Half Days', payload.summary.half_days),
                summaryCard('Single Punch Days', payload.summary.single_punch_days),
                summaryCard('Week Off Days', payload.summary.week_off_days ?? 0),
                summaryCard('Absent Days', payload.summary.absent_days),
                summaryCard('Regularized Days', payload.summary.regularized_days ?? 0),
            ].join('');
            grid.innerHTML = renderCalendarGrid(payload.days);
        } catch (error) {
            subtitle.textContent = 'Unable to load employee calendar';
            summary.innerHTML = '';
            grid.innerHTML = '<div class="alert alert-danger mb-0">Unable to fetch attendance calendar right now.</div>';
        }
    }

    async function updateAttendanceDayOverride(dateKey, overrideStatus) {
        if (!attendanceCalendarCurrentContext.empId) {
            return;
        }

        try {
            const response = await fetch(attendanceCalendarOverrideRoute, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': attendanceCalendarCsrfToken,
                },
                body: JSON.stringify({
                    emp_id: attendanceCalendarCurrentContext.empId,
                    attendance_date: dateKey,
                    override_status: overrideStatus,
                    reason: overrideStatus === 'full_day'
                        ? 'Marked present from admin calendar'
                        : (overrideStatus === 'half_day'
                            ? 'Marked half day from admin calendar'
                            : (overrideStatus === 'absent' ? 'Marked absent from admin calendar' : '')),
                }),
            });

            if (!response.ok) {
                throw new Error('Request failed');
            }

            await openAttendanceCalendar(
                attendanceCalendarCurrentContext.empId,
                attendanceCalendarCurrentContext.month,
                attendanceCalendarCurrentContext.branchId,
                attendanceCalendarCurrentContext.fromDate,
                attendanceCalendarCurrentContext.toDate
            );
        } catch (error) {
            alert('Unable to update attendance day right now.');
        }
    }

    function markAttendanceDayPresent(dateKey) {
        updateAttendanceDayOverride(dateKey, 'full_day');
    }

    function markAttendanceDayFullDay(dateKey) {
        updateAttendanceDayOverride(dateKey, 'full_day');
    }

    function markAttendanceDayHalfDay(dateKey) {
        updateAttendanceDayOverride(dateKey, 'half_day');
    }

    function markAttendanceDayAbsent(dateKey) {
        updateAttendanceDayOverride(dateKey, 'absent');
    }

    function clearAttendanceDayOverride(dateKey) {
        updateAttendanceDayOverride(dateKey, '');
    }

    function openAttendanceDayDetails(dateKey) {
        const day = attendanceCalendarDays[dateKey];
        const subtitle = document.getElementById('attendanceDayDetailsSubtitle');
        const body = document.getElementById('attendanceDayDetailsBody');
        attendanceDayDetailsModalInstance = getBootstrapModal('attendanceDayDetailsModal', attendanceDayDetailsModalInstance);

        if (!day || !attendanceDayDetailsModalInstance) {
            return;
        }

        subtitle.textContent = `${day.date} - ${day.label}`;
        body.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="attendance-day-details-card">
                        <h6 class="mb-2">Login Time</h6>
                        <div>${detailLink(day.check_in ?? '--', 'Check-In Time', day.check_in_datetime ?? day.check_in ?? '--')}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="attendance-day-details-card">
                        <h6 class="mb-2">Logout Time</h6>
                        <div>${detailLink(day.check_out ?? '--', 'Check-Out Time', day.check_out_datetime ?? day.check_out ?? '--')}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="attendance-day-details-card">
                        <h6 class="mb-2">Time Logged In</h6>
                        <p class="mb-0">${escapeHtml(day.worked_time ?? '--')}</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="attendance-day-details-card">
                        <h6 class="mb-2">Check-In Branch</h6>
                        <div>${detailLink(day.check_in_branch ?? '--', 'Check-In Branch', day.check_in_branch_details ?? day.check_in_branch ?? '--', day.check_in_branch_url ?? '')}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="attendance-day-details-card">
                        <h6 class="mb-2">Check-Out Branch</h6>
                        <div>${detailLink(day.check_out_branch ?? '--', 'Check-Out Branch', day.check_out_branch_details ?? day.check_out_branch ?? '--', day.check_out_branch_url ?? '')}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="attendance-day-details-card">
                        <h6 class="mb-2">Check-In Location</h6>
                        <div>${detailLink(day.check_in_location ?? '--', 'Check-In Location', day.check_in_location_details ?? day.check_in_location ?? '--', day.check_in_location_url ?? '')}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="attendance-day-details-card">
                        <h6 class="mb-2">Check-Out Location</h6>
                        <div>${detailLink(day.check_out_location ?? '--', 'Check-Out Location', day.check_out_location_details ?? day.check_out_location ?? '--', day.check_out_location_url ?? '')}</div>
                    </div>
                </div>
                <div class="col-md-6">
                    ${imageThumb('Check-In Image', day.login_image_url)}
                </div>
                <div class="col-md-6">
                    ${imageThumb('Check-Out Image', day.logout_image_url)}
                </div>
            </div>
        `;
        attendanceDayDetailsModalInstance.show();
    }

    function openAttendanceInfo(title, detail, externalUrl = '') {
        const subtitle = document.getElementById('attendanceInfoModalSubtitle');
        const body = document.getElementById('attendanceInfoModalBody');
        attendanceInfoModalInstance = getBootstrapModal('attendanceInfoModal', attendanceInfoModalInstance);

        if (!attendanceInfoModalInstance) {
            return;
        }

        subtitle.textContent = title || 'Attendance detail';
        body.innerHTML = `
            <div class="attendance-day-details-card">
                <div class="attendance-info-content">${escapeHtml(detail || '--')}</div>
                ${externalUrl
                    ? `<div class="mt-3"><a class="btn btn-outline-primary btn-sm" href="${escapeHtml(externalUrl)}" target="_blank" rel="noopener">Open link</a></div>`
                    : ''}
            </div>
        `;
        attendanceInfoModalInstance.show();
    }

    function openAttendanceImagePreview(title, imageUrl) {
        const subtitle = document.getElementById('attendanceImagePreviewSubtitle');
        const image = document.getElementById('attendanceImagePreviewElement');
        attendanceImagePreviewModalInstance = getBootstrapModal('attendanceImagePreviewModal', attendanceImagePreviewModalInstance);

        if (!attendanceImagePreviewModalInstance || !imageUrl) {
            return;
        }

        subtitle.textContent = title || 'Attendance image';
        image.removeAttribute('data-admin-fallback-applied');
        image.src = imageUrl;
        image.alt = title || 'Attendance image';
        attendanceImagePreviewModalInstance.show();
    }
</script>
