# Internal order shortcuts

The orders list and detail page expose an explicit “Подтвердить все” action.
Its single confirmation explains that the manager declares this an internal order
and confirms availability for all remaining lines; this is not a supplier stock refresh.
No order is automatically classified from the customer's name or account role.

A private `orders.is_internal` flag defaults to false and is not mass assignable.
The manager's existing chosen supplier, fulfillment route and input price are retained.
Ambiguous/missing suppliers stop the entire transaction without partial confirmation.
Already confirmed lines are left unchanged; explicit unavailable lines need correction.
Each newly confirmed line records the acting manager and fulfillment history.

“Закрыть успешно” is available for active internal orders only, after confirmation.
It sets the normal successful final status (`delivered`) and clears pending deal control.
It does not mark payment paid, change prices/quantities, reserve or deduct stock,
send customer email/SMS, or create/export 1C documents. Existing 1C document fields
are untouched; the usual explicit 1C actions remain separate.

Both operations check active staff permissions and lock the order in a transaction.
Cancelled, delivered, archived, Deal.by and legacy B2B orders cannot use the shortcut.

Read-only audit of ORD-2026-0217: checking, 17 lines, 1 confirmed, 16 remaining;
all remaining lines have an active selected supplier. No production order was changed.

Validation covers 17-line operation, preserved previous confirmation and commercial
data, repeated confirmation, missing supplier rollback, customer/inactive staff denial,
manager access, no customer messages, and normal workflow/SMS regression tests.
