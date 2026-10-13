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
        overflow: hidden;
    }

    .fi-ta-content-ctn {
        max-width: 100%;
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
</style>
