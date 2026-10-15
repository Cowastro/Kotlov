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
        .crm-mail-conversation .fi-section-content { padding: 0; min-width: 0; }
        .crm-mail-conversation .fi-in-repeatable.fi-contained > .fi-in-repeatable-item {
            min-width: 0;
            padding: 0;
            border-radius: 0;
            background: transparent;
            box-shadow: none;
        }
    }
</style>
