# Catalogue card specifications

Cards share `fireplace-card-specs` and `CardSpecificationSummary`. Render at most three existing facts, one line each, in a fixed-height slot. Long values are ellipsized and retained in the title and accessible label. Product titles have a consistent slot (three desktop lines, four mobile lines); the full name remains in the link and title.

Pump summaries compare one head, one flow and motor/input power. Prefer maximum values when present; retain the original nominal or tabulated label when that is the only supported measurement. Never convert nominal values into maxima. Extra facts, evidence and complete specification tables remain on the product page. Other groups retain their established parameter order and ΔT labels.

Unknown models use the same slot with a neutral “Параметры пока не подтверждены” message. Missing values are not inferred from model numbers or sibling products. Filters continue to use the complete original facts, not the three-field presentation summary.

TD Komplekt catalogue version 4 also removes the specifically identified trailing list of sealing-material catalogue entries from generated descriptions. The full feed description stays in the offer's raw data. A lost inch fraction is restored only if the identical fraction is explicit in the product title. Editorial descriptions are preserved.

Use `php artisan supplier:repair-td-descriptions` to preview the existing-card repair, and add `--apply` to apply it. The repair touches only generated content/specs belonging to TD Komplekt, shares the import lock, skips quarantined identities, and stores private per-product before/after backups. It changes no prices, stock or category assignments.
