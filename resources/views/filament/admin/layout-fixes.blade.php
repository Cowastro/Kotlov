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

    /* Product management must stay scan-friendly even with long supplier data. */
    .products-workspace .fi-ta-header-cell {
        padding: 0.5rem;
    }

    .products-workspace .fi-ta-text:not(.fi-inline),
    .products-workspace .fi-ta-icon,
    .products-workspace .fi-ta-image {
        padding: 0.625rem 0.5rem;
    }

    .products-workspace .fi-ta-cell-name,
    .products-workspace .fi-ta-header-cell-name {
        min-width: 20rem;
        max-width: 20rem;
    }

    .products-workspace .fi-ta-cell-suppliers-list,
    .products-workspace .fi-ta-header-cell-suppliers-list {
        min-width: 14rem;
        max-width: 14rem;
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
