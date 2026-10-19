# Deal.by integration roadmap

Permanent implementation ID: `DEAL-BY-ORDER-INBOX`
Status: `IN_PROGRESS`

## Objective

Capture every new or changed Deal.by order in KOTLOV.BY without modifying Deal.by and without creating operational orders automatically. Product matching, local order conversion, 1C delivery and product writes remain separate later gates.

## Current stage: encrypted order inbox

Implemented locally:

- bearer-authenticated Deal.by client with read-only probe;
- durable order cursor with a 30-day initial lookback;
- 15-minute overlap on incremental polling;
- pagination in batches of 100;
- idempotency by immutable Deal.by order ID;
- encrypted raw payload storage because it contains customer data;
- payload hashing to distinguish changed and unchanged orders;
- capture switch disabled by default;
- a two-minute schedule that runs only after explicit enablement;
- aggregate health output without customer data;
- a separate read-only admin section for reviewing captured Deal.by orders;
- no writes to Deal.by, `orders`, `order_items` or the 1C queue.

Targeted verification: 8 tests / 46 assertions passed locally on 19.10.2026. It covers encryption, idempotency, pagination, disabled mode, API failure and malformed-response cursor safety.

## Production gates

1. Add the repository secret `DEAL_BY_API_TOKEN`.
2. Commit and pass CI on PHP 8.3 and 8.4.
3. Run `configure-deal-by`; this installs the token while keeping capture disabled.
4. Run `deploy-safe`; migrations create only the isolated inbox and cursor tables.
5. Run `deal:probe` on production.
6. Run one controlled `deal:pull-orders --force` capture.
7. Run `deal:order-inbox-status` and compare aggregate counts with the Deal.by cabinet. Do not print payloads.
8. Inspect the server log for errors and wait for explicit approval.
9. Run `enable-deal-by-orders`; automatic capture then starts every two minutes.
10. Observe at least 24 hours before designing conversion into operational orders.

## Later stages

1. Dry-run conversion of inbox records into local order DTOs.
2. Idempotent creation of operational orders with explicit `deal_by` origin.
3. Product mapping review; unknown items must never block inbox capture.
4. Controlled routing into the existing 1C delivery queue.
5. Dry-run status mapping back to Deal.by, then a small pilot.
6. Permanent Deal.by product links and only then price/stock updates.

## Completion evidence

`DEAL-BY-ORDER-INBOX` can become `DONE` only after code is committed, targeted and full tests pass, CI and `deploy-safe` succeed, the production token is configured without appearing in logs, one manual capture succeeds, automatic capture is enabled, and a 24-hour production observation shows no missed IDs, duplicates, unencrypted payloads or writes to operational orders.
