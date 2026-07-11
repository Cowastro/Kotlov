# Server Artisan Result

- Time: 2026-07-11 12:46:16 UTC
- Task: `artisan-dry-run`
- Artisan args: `supplier:enrich-rusklimat --brand=Ballu --skip-content --limit=20 --dry-run`
- Log file: `storage/logs/server-artisan-rusklimat-ballu-preview.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   9009f7c..618258d  main       -> origin/main
Updating 9009f7c..618258d
Fast-forward
 .github/server-artisan-result.md | 65 +++++++++++++---------------------------
 .github/server-artisan-task.json |  6 ++--
 2 files changed, 24 insertions(+), 47 deletions(-)
[dry-run] No changes will be written.
Loading rusklimat.by sitemap…
Sitemap: 540 product URLs loaded.

Products to enrich: 34 (processing 20, offset 0, brand=Ballu)
  match split: 34 active + 285 archived (default skips archived; pass --include-archived to enrich them)

[1/20] Ballu Ballu BWH/S 80 Lorica
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[2/20] Ballu Ballu BEC/EVU-1500
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[3/20] Ballu Ballu BEC/EVU-2000
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[4/20] Ballu Ballu BEC/EVU-2500
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[5/20] Ballu Ballu BFT/EVUR
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[6/20] Ballu Ballu BEC/SMT-2500
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[7/20] Ballu Ballu CWM-02
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[8/20] Ballu Ballu BEC/EZMR-1500 (SC)
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[9/20] Ballu Ballu BEC/ETMR-1500
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[10/20] Ballu Ballu BEC/ETER-1500
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[11/20] Ballu Ballu BEC/EMT-2000
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[12/20] Ballu Ballu BEC/EMT-2500
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[13/20] Ballu Ballu BOH/CL-05WRN
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[14/20] Ballu Ballu BOH/CL-11WRN
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[15/20] Ballu Ballu BOH/CB-07W
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[16/20] Ballu Ballu BOH/CB-09W
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[17/20] Ballu Ballu UHB-340 MT
  selected: missing_photo, missing_specs
  ✗ not found on rusklimat.by
  — nothing to update
[18/20] Ballu Ballu UHB-1100 AURA
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[19/20] Ballu Ballu BPAC-07 EPW/N6 white
  selected: missing_photo
  ✗ not found on rusklimat.by
  — nothing to update
[20/20] Ballu Ballu BPAC-18 CE
  selected: missing_photo
  ✓ scraped specs:37 img:yes url:mobil-nyj-kondicioner-ballu-bpac-07-cd
  ✓ image: ballu-bpac-18-ce.jpg
  [dry-run] would update: images

+---------------+-------+
| metric        | count |
+---------------+-------+
| processed     | 20    |
| scraped       | 1     |
| ai_used       | 0     |
| images        | 1     |
| specs         | 0     |
| built_content | 0     |
| built_short   | 0     |
| skipped       | 19    |
| errors        | 0     |
+---------------+-------+

14 more products remain. Run with --offset=20 to continue.


```
