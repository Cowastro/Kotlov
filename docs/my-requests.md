# My requests: carts, demand, waiting and reservations

The account entry “Мои заявки” lists editable CustomerSelections and existing B2bSavedCart snapshots. Existing snapshots open through an idempotent, owner-scoped conversion; the source is preserved. New B2B saves use CustomerSelection. Legacy restore URLs open this conversion instead of silently skipping unavailable goods or replacing the active cart.

A request stores the full requested quantity. An explicit availability check records ready/waiting quantities in metadata. Only current authorized B2B offers from the originally selected supply option count. Unknown balances remain waiting for confirmation. Stock is not summed across feeds or silently switched to another supplier. Active CRM reservations are subtracted by the existing resolver. Supplier minimums and integer multiples are respected.

Preparing a partial cart rechecks current stock and prices on the server, keeps the full original demand, saves an existing different cart before replacement and selects prepared lines for checkout. The partial cart is intentionally not linked as an editable replacement of the full request. Revisions reject stale submissions. All filtering is presentation-only, while bulk selection of shown rows preserves hidden rows.

Waiting is a saved demand, not a reservation. The separate reserve list shows actual active CRM order reservations within the existing account/company permissions. A CRM reservation does not by itself establish a reservation in a supplier’s external system. No automatic supplier booking or order placement is added here.

The migration widens selection metadata from TEXT to LONGTEXT: MySQL's 64 KiB TEXT limit is insufficient for 150-line availability snapshots. Rollback deliberately does not shrink the column, preserving existing large snapshots.

Validation: feature coverage includes legacy conversion, missing products, partial quantities, current prices, stale stock, ownership, selection revisions, excluded lines, preserving an earlier cart, active reservations, and 150-line allocation. Browser checks at 360/390 and 1440 pixels verified search, waiting filters, selection persistence and a 150-line partial cart (650 available of 1,500 requested, 850 waiting) without page overflow. Production orders were not created during testing.

## Finding requests and exporting parts of demand

The hub can filter current and legacy requests by client/object name without widening ownership permissions; pagination retains the search. The working table supports normalized article search, availability counters and a reset that changes only presentation, preserving selected rows and requested quantities.

An explicit export scope selects all demand, ready quantities or waiting quantities from checked, selected lines. Print and Excel use the same projection, retain saved prices and comments, identify the saved check, and never create reservations or change the request. Invalidated/unchecked allocations are omitted from partial exports with a visible warning; an empty partial export returns to the request with an explanation. The screen previews exported positions, units and value independently from the full demand total.
