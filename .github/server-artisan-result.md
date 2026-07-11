# Server Artisan Result

- Time: 2026-07-11 14:16:00 UTC
- Task: `artisan-apply`
- Artisan args: `product:enrich-content --all --only=content --limit=25 --offset=50 --min-specs=3 --rewrite-thin=350 --source-context --require-source-context --min-source-context-chars=120 --ai-model=deepseek-chat`
- Log file: `storage/logs/server-artisan-deepseek-thin-source-apply-25d.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   6cb69a9..04ead43  main       -> origin/main
Updating 6cb69a9..04ead43
Fast-forward
 .github/server-artisan-result.md | 71 ++++++++++++++++++++--------------------
 .github/server-artisan-task.json |  6 ++--
 2 files changed, 38 insertions(+), 39 deletions(-)
Provider: deepseek-chat (api.deepseek.com)
Candidates: 154 | processing: 25 (offset=50)
[1/25] id=15141 Радиатор панельный Royal Thermo VENTIL COMPACT VC21-500-800 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[2/25] id=15165 Дизайн-радиатор Royal Thermo Shift R22 C2050 - 12 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[3/25] id=15166 Дизайн-радиатор Royal Thermo Shift R22 C2050 - 16 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[4/25] id=15176 Дизайн-радиатор Royal Thermo Shift R22 VC2180 - 04 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[5/25] id=15179 Дизайн-радиатор Royal Thermo Shift R22 VC2180 - 06 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[6/25] id=15181 Дизайн-радиатор Royal Thermo Shift R22 VC2180 - 08 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[7/25] id=15182 Дизайн-радиатор Royal Thermo Shift R22 VC2180 - 10 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[8/25] id=15194 Дизайн-радиатор Royal Thermo Shift R22 VC2030 - 18 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[9/25] id=15202 Дизайн-радиатор Royal Thermo Shift Q30 C2180 - 06 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[10/25] id=15210 Дизайн-радиатор Royal Thermo Shift Q30 C2180 - 14 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[11/25] id=15216 Дизайн-радиатор Royal Thermo Shift Q30 C2050 - 28 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[12/25] id=15225 Дизайн-радиатор Royal Thermo Shift Q30 VC2180 - 06 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[13/25] id=15226 Дизайн-радиатор Royal Thermo Shift Q30 VC2180 - 08 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[14/25] id=15236 Дизайн-радиатор Royal Thermo Shift Q30 VC2050 - 16 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[15/25] id=15239 Дизайн-радиатор Royal Thermo Shift Q30 VC2050 - 28 секц. RAL
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[16/25] id=15245 Дизайн-радиатор Royal Thermo Insignia C2180 - 04 секц. RAL90
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[17/25] id=15249 Дизайн-радиатор Royal Thermo Insignia C2180 - 08 секц. RAL90
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[18/25] id=15252 Дизайн-радиатор Royal Thermo Insignia C2180 - 10 секц. RAL90
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[19/25] id=15258 Дизайн-радиатор Royal Thermo Insignia C3050 - 14 секц. RAL90
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[20/25] id=15261 Дизайн-радиатор Royal Thermo Insignia C3050 - 26 секц. RAL90
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[21/25] id=15264 Дизайн-радиатор Royal Thermo Insignia C3030 - 14 секц. RAL90
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[22/25] id=15270 Дизайн-радиатор Royal Thermo Insignia VC3180 - 06 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[23/25] id=15271 Дизайн-радиатор Royal Thermo Insignia VC3180 - 08 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[24/25] id=15272 Дизайн-радиатор Royal Thermo Insignia VC3180 - 10 секц. RAL9
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[25/25] id=15277 Дизайн-радиатор Royal Thermo Insignia VC2180 - 04 секц. Tech
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
+---------+-------+
| action  | count |
+---------+-------+
| updated | 0     |
| skipped | 25    |
| errors  | 0     |
+---------+-------+

79 more remain. Continue with --offset=75

```
