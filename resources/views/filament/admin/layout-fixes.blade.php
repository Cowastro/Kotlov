<style>
    /* Keep wide Filament tables inside the content area instead of widening the whole admin layout. */
    .fi-main-ctn,
    .fi-main,
    .fi-page,
    .fi-page-main,
    .fi-page-content,
    .fi-page-content > * {
        min-width: 0;
        max-width: 100%;
    }

    .fi-ta-ctn {
        min-width: 0;
        max-width: 100%;
        /* Filament renders the filters dropdown inside this container. Hiding
           overflow clips the dropdown at the bottom edge of short tables. */
        overflow: visible;
    }

    .fi-ta-content-ctn {
        max-width: 100%;
        overflow-x: auto;
        overscroll-behavior-inline: contain;
    }

    /* Do not crush words and phone numbers into one-character lines in narrow cells. */
    .fi-ta-cell {
        overflow-wrap: break-word;
        word-break: normal;
    }

    /* Desktop is the manager's dense workspace: keep all Filament components
       on the same compact type scale instead of shrinking individual pages.
       Touch targets and the original type scale stay unchanged on phones. */
    @media (min-width: 1024px) {
        .fi-body {
            --text-xs: 0.6875rem;
            --text-xs--line-height: 1rem;
            --text-sm: 0.8125rem;
            --text-sm--line-height: 1.125rem;
            --text-base: 0.9375rem;
            --text-base--line-height: 1.375rem;
            --text-lg: 1.0625rem;
            --text-lg--line-height: 1.5rem;
            --text-xl: 1.125rem;
            --text-xl--line-height: 1.625rem;
            --text-2xl: 1.375rem;
            --text-2xl--line-height: 1.75rem;
            --text-3xl: 1.625rem;
            --text-3xl--line-height: 2rem;
        }

        .fi-sidebar-nav,
        .fi-sidebar-nav-groups {
            row-gap: 1rem;
        }

        .fi-sidebar-nav {
            padding-block: 1rem;
        }

        .fi-sidebar-item-btn,
        .fi-sidebar-group-btn {
            gap: 0.625rem;
            padding-block: 0.375rem;
        }
    }

    /* The order queue is information-dense, so keep its rhythm compact without
       making controls or the rest of the admin panel smaller. */
    .order-queue-nav {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
    }

    .order-queue-nav__more {
        min-width: 13rem;
        margin-inline-start: auto;
    }

    .order-deal {
        color: var(--gray-700);
        text-align: start;
    }

    .dark .order-deal {
        color: var(--gray-200);
    }

    .order-deal--compact {
        contain: inline-size;
        padding: .4rem .375rem;
        min-width: 15rem;
        font-size: .75rem;
        line-height: 1.1rem;
    }

    .order-deal__heading,
    .order-deal__control {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: .5rem;
    }

    .order-deal__heading > span {
        font-size: .7rem;
        opacity: .65;
        white-space: nowrap;
    }

    .order-deal__track {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: .25rem;
    }

    .order-deal__step {
        min-height: 24px;
        cursor: pointer;
        text-align: center;
    }

    .order-deal__step:disabled {
        cursor: default;
    }

    .order-deal__step:focus-visible {
        outline: 2px solid #f59e0b;
        outline-offset: 2px;
        border-radius: .25rem;
    }

    .order-deal__bar {
        display: block;
        height: 6px;
        border-radius: 4px;
        background: var(--gray-200);
    }

    .dark .order-deal__bar {
        background: var(--gray-700);
    }

    .order-deal__step.is-done .order-deal__bar {
        background: #22c55e;
    }

    .order-deal__step.is-current .order-deal__bar {
        background: #f59e0b;
        box-shadow: 0 0 0 2px rgb(245 158 11 / .18);
    }

    .order-deal__step:not(:disabled):hover .order-deal__bar {
        background: #fbbf24;
    }

    .order-deal__step-label {
        display: block;
        margin-top: .5rem;
        font-size: .8125rem;
    }

    .order-deal--compact .order-deal__step-label {
        display: none;
    }

    .order-deal__control {
        font-size: .75rem;
        color: var(--gray-500);
    }

    .order-deal__control > button {
        color: #b45309;
        text-decoration: underline;
        cursor: pointer;
        flex-shrink: 0;
    }

    .dark .order-deal__control > button {
        color: #fbbf24;
    }

    .order-deal__control.is-today {
        color: #b45309;
    }

    .dark .order-deal__control.is-today {
        color: #fbbf24;
    }

    .order-deal__control.is-overdue {
        color: #dc2626;
        font-weight: 600;
    }

    .dark .order-deal__control.is-overdue {
        color: #f87171;
    }

    .order-deal__next {
        margin-top: .25rem;
        overflow-wrap: anywhere;
    }

    .order-deal--compact .order-deal__next {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 1;
        overflow: hidden;
    }

    .order-deal__facts {
        display: flex;
        flex-wrap: wrap;
        column-gap: .6rem;
        row-gap: .1rem;
        margin-top: .25rem;
        font-size: .6875rem;
        opacity: .8;
    }

    .order-deal--compact .order-deal__fulfillment-status {
        display: none;
    }

    .order-deal__changed {
        margin-top: .5rem;
        color: var(--gray-500);
        font-size: .75rem;
    }

    @media (max-width: 640px) {
        .order-deal--full .order-deal__step-label {
            font-size: .625rem;
            overflow-wrap: anywhere;
        }
    }

    .orders-workspace .fi-ta-header-cell {
        padding: .5rem .375rem;
    }

    .orders-workspace .fi-ta-text:not(.fi-inline),
    .orders-workspace .fi-ta-icon {
        padding: .4rem .375rem;
    }

    .orders-workspace .fi-ta-actions {
        gap: 0.25rem;
        white-space: nowrap;
    }

    .orders-workspace .fi-ta-actions .fi-btn {
        padding-inline: 0.55rem;
    }

    .orders-workspace .fi-ta-header-toolbar .fi-ta-search-field {
        width: min(24rem, 62vw);
    }

    .orders-workspace .order-cell-line {
        display: block;
        max-width: 9rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .orders-workspace .order-cell-details {
        max-width: 100%;
        text-align: start;
    }

    .orders-workspace .order-cell-details > summary {
        cursor: pointer;
        line-height: 1.1rem;
        color: inherit;
    }

    .orders-workspace .order-cell-details__body {
        margin-top: .375rem;
        padding-inline-start: .375rem;
        overflow-wrap: anywhere;
    }

    .orders-workspace .order-cell-details__body > hr {
        margin-block: .375rem;
        opacity: .2;
    }

    .orders-workspace .fi-ta-cell-items-list {
        min-width: 8rem;
    }

    .orders-workspace .order-item-preview {
        /* Use the space allocated by the table without letting long product names widen it. */
        contain: inline-size;
    }

    .orders-workspace .order-item-preview > summary .order-cell-line {
        display: inline-block;
        max-width: calc(100% - 1.2rem);
        vertical-align: bottom;
    }

    .orders-workspace .order-item-preview__count {
        display: block;
        padding-inline-start: .8rem;
        font-size: .6875rem;
        font-weight: 500;
        opacity: .75;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .orders-workspace .order-summary-status,
    .orders-workspace .order-summary-total {
        display: none;
    }

    .orders-workspace .order-payment-confirmed {
        color: var(--success-600);
        font-weight: 600;
    }

    .orders-workspace .order-control-indicator {
        display: block;
        color: var(--warning-600);
        white-space: nowrap;
    }

    .orders-workspace .fi-ta-cell-customer-name a[href^="tel:"],
    .orders-workspace .fi-ta-cell-economics .order-cell-details > summary {
        white-space: nowrap;
    }

    .dark .orders-workspace .order-payment-confirmed {
        color: var(--success-400);
    }

    .dark .orders-workspace .order-control-indicator {
        color: var(--warning-400);
    }

    .orders-workspace .fi-ta-content-ctn {
        container-type: inline-size;
    }

    @container (width < 64rem) {
        .orders-workspace :is(.fi-ta-header-cell-economics, .fi-ta-cell-economics) {
            display: none;
        }

        .orders-workspace .order-summary-total {
            display: block;
            font-weight: 600;
        }
    }

    @container (min-width: 80rem) {
        .orders-workspace .fi-ta-header-cell-number {
            min-width: 9rem;
        }

        .orders-workspace .fi-ta-header-cell-customer-name {
            min-width: 12rem;
        }

        .orders-workspace .fi-ta-header-cell-economics {
            min-width: 11rem;
        }

        .orders-workspace .fi-ta-header-cell-status {
            min-width: 10rem;
        }

        .orders-workspace .fi-ta-header-cell-responsible {
            min-width: 8.5rem;
        }

        .orders-workspace .fi-ta-cell-customer-name .order-cell-line {
            max-width: 100%;
        }

        .orders-workspace .fi-ta-cell-economics .fi-ta-text-item {
            white-space: nowrap;
        }

        .orders-workspace .order-item-preview > summary .order-cell-line {
            display: -webkit-inline-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            white-space: normal;
            overflow-wrap: anywhere;
            vertical-align: top;
        }
    }

    @media (max-width: 1279px) {
        .orders-workspace .order-summary-total {
            display: block;
            font-weight: 600;
        }
    }

    @media (max-width: 767px) {
        .order-queue-nav__more {
            width: 100%;
        }

        .orders-workspace .order-summary-status {
            display: block;
            max-width: 8rem;
        }

        .orders-workspace .fi-ta-actions {
            gap: 0;
        }
    }

    .orders-workspace .fi-ta-cell-responsible .fi-ta-text-item-label,
    .orders-workspace .fi-ta-cell-management-attention .fi-ta-text-item-label {
        line-height: 1.1rem;
    }

    .orders-workspace .fi-ta-cell-onec-sync-state,
    .orders-workspace .fi-ta-header-cell-onec-sync-state {
        min-width: 3.5rem;
        white-space: nowrap;
    }

    /* Product management must stay scan-friendly even with long supplier data. */
    .products-workspace .fi-ta-header-cell {
        padding: 0.5rem 0.375rem;
    }

    .products-workspace .fi-ta-text:not(.fi-inline),
    .products-workspace .fi-ta-icon,
    .products-workspace .fi-ta-image {
        padding: 0.625rem 0.375rem;
    }

    .products-workspace .fi-ta-cell-name,
    .products-workspace .fi-ta-header-cell-name {
        min-width: 17.5rem;
        max-width: 17.5rem;
    }

    .products-workspace .fi-ta-cell-suppliers-list,
    .products-workspace .fi-ta-header-cell-suppliers-list {
        min-width: 12rem;
        max-width: 12rem;
    }

    .fi-resource-contact-requests.fi-resource-list-records-page .fi-ta-header-cell {
        padding-block: .5rem;
    }

    .fi-resource-contact-requests.fi-resource-list-records-page .fi-ta-text:not(.fi-inline) {
        padding-block: .4rem;
    }

    .fi-resource-contact-requests.fi-resource-list-records-page .fi-ta-selection-cell,
    .fi-resource-contact-requests.fi-resource-list-records-page .fi-ta-cell:has(> .fi-ta-actions) {
        padding-block: .375rem;
    }

    .fi-resource-contact-requests.fi-resource-list-records-page .fi-ta-cell-message {
        max-width: min(34rem, 38vw);
    }

    .fi-resource-contact-requests.fi-resource-list-records-page .contact-request-needs-attention {
        box-shadow: inset 3px 0 #f59e0b;
    }

    /* Mail is reviewed on narrower office displays. Keep operational columns
       compact so the table fits without sacrificing sender and subject. */
    .crm-mail-list-page .crm-mail-row-unread > td {
        background-color: #fffbeb;
    }

    .crm-mail-list-page .crm-mail-row-unread:hover > td {
        background-color: #fef3c7;
    }

    .dark .crm-mail-list-page .crm-mail-row-unread > td {
        background-color: #342b1d;
    }

    .dark .crm-mail-list-page .crm-mail-row-unread:hover > td {
        background-color: #403322;
    }

    .crm-mail-list-page .crm-mail-row-unread > td:first-child {
        box-shadow: inset 3px 0 #f59e0b;
    }

    .crm-mail-list-page .crm-mail-unread-label {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        color: #92400e;
        font-weight: 700;
        white-space: nowrap;
    }

    .dark .crm-mail-list-page .crm-mail-unread-label {
        color: #fcd34d;
    }

    .crm-mail-list-page .crm-mail-unread-label::before {
        content: '';
        width: .375rem;
        height: .375rem;
        border-radius: 50%;
        background-color: #f59e0b;
        flex-shrink: 0;
    }

    @media (min-width: 1024px) {
        .crm-mail-queue__nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
        }

        .crm-mail-queue__folders {
            margin-inline-start: auto;
            min-width: 12rem;
        }

        .crm-mail-queue__hint {
            margin-top: .6rem;
            font-size: .8125rem;
            color: var(--gray-600);
        }

        .dark .crm-mail-queue__hint {
            color: var(--gray-400);
        }

        .crm-mail-queue__health {
            margin-top: .6rem;
            font-size: .8125rem;
        }

        .crm-mail-queue__health summary {
            cursor: pointer;
            width: fit-content;
        }

        .crm-mail-queue__health > div {
            margin-top: .75rem;
        }

        .crm-mail-list-page .fi-ta-header-toolbar .fi-ta-search-field {
            width: min(28rem, 62vw);
        }

        @media (max-width: 640px) {
            .crm-mail-queue__folders {
                width: 100%;
            }
        }

        .crm-mail-list-page .fi-ta-header-cell {
            padding: 0.5rem 0.375rem;
            font-size: 0.72rem;
            line-height: 1rem;
        }

        .crm-mail-list-page .fi-ta-text:not(.fi-inline),
        .crm-mail-list-page .fi-ta-icon {
            padding: 0.55rem 0.375rem;
        }

        .crm-mail-list-page .fi-ta-cell .fi-ta-text-item-label {
            font-size: 0.75rem;
            line-height: 1.05rem;
        }

        .crm-mail-list-page .fi-ta-cell .fi-ta-text-item-description {
            font-size: 0.675rem;
            line-height: 0.95rem;
        }

        .crm-mail-list-page .fi-ta-actions {
            gap: 0.125rem;
            padding-inline: 0.25rem;
            white-space: nowrap;
        }

        .crm-mail-list-page .fi-ta-actions .fi-icon-btn {
            width: 1.75rem;
            height: 1.75rem;
            padding: 0.25rem;
        }

        .crm-mail-list-page .fi-ta-actions .fi-icon {
            width: 1rem;
            height: 1rem;
        }
    }

    /* Order view is an operational screen: keep the primary facts dense and
       let explicitly collapsible technical sections carry the long detail. */
    .order-view-page .fi-main {
        padding-top: 1rem;
    }

    .order-view-page .fi-sc {
        gap: 0.75rem;
    }

    .order-view-page .fi-section-header {
        padding: 0.7rem 1rem;
    }

    .order-view-page .fi-section-content {
        padding: 0.8rem 1rem;
    }

    .order-view-page .fi-in-repeatable.fi-contained > .fi-in-repeatable-item {
        padding: 0.8rem;
    }

    .order-view-page .fi-in-entry-wrp {
        gap: 0.2rem;
    }

    @media (max-width: 700px) {
        /* Order actions follow one predictable mobile hierarchy: operational
           steps use full rows, while the two secondary closing actions share
           the final row. */
        .order-view-page .fi-header-actions-ctn {
            width: 100%;
            align-items: stretch;
        }

        .order-view-page .fi-header-actions-ctn > .fi-ac {
            display: grid;
            width: 100%;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .order-view-page .fi-header-actions-ctn .order-view-action {
            width: 100%;
            min-width: 0;
            min-height: 48px;
            justify-content: center;
            padding: 10px 13px;
            border-radius: 12px;
            line-height: 1.2;
            text-align: center;
            white-space: normal;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.08);
        }

        .order-view-page .fi-header-actions-ctn .order-view-action--primary {
            grid-column: 1 / -1;
        }

        .order-view-page .fi-header-actions-ctn .order-view-action > .fi-icon {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
        }

        .order-view-page .fi-header-actions-ctn .order-view-action--manual {
            background: transparent;
            box-shadow: inset 0 0 0 1px rgb(148 163 184 / 0.45);
        }

        /* The message has its own card: avoid two extra padded cards on phones. */
        .crm-mail-thread-page .fi-main { padding-inline: 8px; }

        /* Keep lead actions predictable and easy to tap instead of letting every
           label create a different-width button row. */
        .crm-mail-thread-page .fi-header-actions-ctn {
            width: 100%;
            align-items: stretch;
        }

        .crm-mail-thread-page .fi-header-actions-ctn > .fi-ac {
            display: grid;
            width: 100%;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action {
            width: 100%;
            min-width: 0;
            min-height: 46px;
            padding: 10px 12px;
            border-radius: 12px;
            line-height: 1.2;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.08);
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action > .fi-icon {
            width: 20px;
            height: 20px;
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--notes,
        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--lead,
        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--supplier-email {
            grid-column: 1 / -1;
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--lead {
            background: #fff;
            color: #1f2937;
            box-shadow: inset 0 0 0 1px #d1d5db, 0 1px 2px rgb(15 23 42 / 0.06);
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--lead > .fi-icon {
            color: #64748b;
        }

        /* Spam is reversible, so it should not compete visually with deletion. */
        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--spam {
            background: #fff1f2;
            color: #be123c;
            box-shadow: inset 0 0 0 1px #fecdd3;
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--spam > .fi-icon {
            color: #e11d48;
        }

        .dark .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--lead {
            background: rgb(255 255 255 / 0.06);
            color: #f8fafc;
            box-shadow: inset 0 0 0 1px #475569;
        }

        .dark .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--spam {
            background: rgb(159 18 57 / 0.18);
            color: #fda4af;
            box-shadow: inset 0 0 0 1px rgb(251 113 133 / 0.35);
        }

        .dark .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action--spam > .fi-icon {
            color: #fda4af;
        }

        .crm-mail-conversation .fi-section-content { padding: 0; min-width: 0; }
        .crm-mail-conversation .fi-in-repeatable.fi-contained > .fi-in-repeatable-item {
            min-width: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }
    }

    @media (max-width: 360px) {
        .order-view-page .fi-header-actions-ctn > .fi-ac {
            grid-template-columns: minmax(0, 1fr);
        }

        .order-view-page .fi-header-actions-ctn .order-view-action {
            grid-column: 1;
        }

        .crm-mail-thread-page .fi-header-actions-ctn > .fi-ac {
            grid-template-columns: minmax(0, 1fr);
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action {
            grid-column: 1;
        }
    }
</style>

@include('partials.ios-input-zoom-fix')
