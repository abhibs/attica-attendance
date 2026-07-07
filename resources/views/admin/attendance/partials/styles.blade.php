<style>
    .attendance-page .card {
        border: 1px solid var(--admin-border-color);
    }

    .attendance-page .attendance-title,
    .attendance-page .breadcrumb-title,
    .attendance-page .breadcrumb-item,
    .attendance-page .breadcrumb-item a,
    .attendance-page label.form-label,
    .attendance-page .table,
    .attendance-page .table th,
    .attendance-page .table td,
    .attendance-page .dataTables_wrapper,
    .attendance-page .dataTables_info,
    .attendance-page .dataTables_filter label,
    .attendance-page .dataTables_length label,
    .attendance-page .dataTables_paginate,
    .attendance-page .dt-buttons .btn {
        color: var(--admin-text-color) !important;
    }

    .attendance-page .attendance-section-subtitle,
    .attendance-page .attendance-muted,
    .attendance-page .text-muted,
    .attendance-page small,
    .attendance-page .form-text {
        color: var(--admin-muted-text-color) !important;
    }

    .attendance-page .attendance-stat-card p,
    .attendance-page .attendance-stat-card h4 {
        color: var(--admin-text-color) !important;
    }

    .attendance-page .form-control,
    .attendance-page .form-select {
        color: var(--admin-text-color);
        background-color: var(--admin-surface-color);
        border-color: var(--admin-border-color);
    }

    .attendance-page .form-control::placeholder {
        color: var(--admin-muted-text-color);
    }

    .attendance-page .form-select option {
        color: var(--admin-text-color);
        background-color: var(--admin-surface-color);
    }

    .attendance-page .table thead th {
        color: var(--admin-text-color) !important;
    }

    .attendance-page .datatable-toolbar .dt-buttons,
    .attendance-page .dt-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .attendance-page .datatable-toolbar .btn,
    .attendance-page .dt-buttons .btn {
        border-color: var(--admin-border-color);
        background: transparent;
    }

    .attendance-page .attendance-status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .attendance-page .attendance-status-pill.full_day {
        background: rgba(25, 135, 84, 0.2);
        color: #7ef2a8 !important;
    }

    .attendance-page .attendance-status-pill.full_day_remote {
        background: rgba(13, 110, 253, 0.18);
        color: #9ac3ff !important;
    }

    .attendance-page .attendance-status-pill.half_day {
        background: rgba(255, 193, 7, 0.2);
        color: #ffd86b !important;
    }

    .attendance-page .attendance-status-pill.single_punch {
        background: rgba(13, 110, 253, 0.18);
        color: #9ac3ff !important;
    }

    .attendance-page .attendance-status-pill.absent {
        background: rgba(220, 53, 69, 0.18);
        color: #ff98a4 !important;
    }

    .attendance-page .attendance-status-pill.blocked {
        background: rgba(220, 53, 69, 0.22);
        color: #ffadb6 !important;
    }

    .attendance-page .attendance-status-pill.active {
        background: rgba(214, 51, 132, 0.2);
        color: #ffb3df !important;
    }

    .attendance-page .attendance-status-pill.pending {
        background: rgba(255, 193, 7, 0.2);
        color: #ffd86b !important;
    }

    .attendance-page .attendance-status-pill.approved {
        background: rgba(25, 135, 84, 0.2);
        color: #7ef2a8 !important;
    }

    .attendance-page .attendance-status-pill.rejected {
        background: rgba(220, 53, 69, 0.18);
        color: #ff98a4 !important;
    }

    .attendance-page .table .text-muted {
        color: var(--admin-muted-text-color) !important;
    }

    .attendance-page .alert {
        border-radius: 14px;
    }
</style>
