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

    .fi-ta-cell {
        overflow-wrap: anywhere;
    }
</style>
