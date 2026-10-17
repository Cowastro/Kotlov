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

    /* The order queue is information-dense, so keep its rhythm compact without
       making controls or the rest of the admin panel smaller. */
    .orders-workspace .fi-ta-header-cell {
        padding: 0.625rem;
    }

    .orders-workspace .fi-ta-text:not(.fi-inline),
    .orders-workspace .fi-ta-icon {
        padding: 0.75rem 0.625rem;
    }

    .orders-workspace .fi-ta-cell-onec-sync-state,
    .orders-workspace .fi-ta-header-cell-onec-sync-state {
        min-width: 3.5rem;
        white-space: nowrap;
    }
    @media (max-width: 700px) {
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
        .crm-mail-thread-page .fi-header-actions-ctn > .fi-ac {
            grid-template-columns: minmax(0, 1fr);
        }

        .crm-mail-thread-page .fi-header-actions-ctn .crm-thread-action {
            grid-column: 1;
        }
    }
</style>
