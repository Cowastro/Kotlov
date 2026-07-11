# Server Artisan Result

- Time: 2026-07-11 14:07:53 UTC
- Task: `artisan-apply`
- Artisan args: `product:enrich-content --all --only=content --limit=25 --min-specs=3 --rewrite-thin=350 --source-context --require-source-context --min-source-context-chars=120 --ai-model=deepseek-chat`
- Log file: `storage/logs/server-artisan-deepseek-thin-source-apply-25b.log`
- Exit code: `0`

```text
From https://github.com/Cowastro/Kotlov
   a24cbe0..d2de862  main       -> origin/main
Updating a24cbe0..d2de862
Fast-forward
 .github/server-artisan-result.md                   | 155 +++++++++++++++------
 .github/server-artisan-task.json                   |   6 +-
 .../Commands/EnrichProductContentCommand.php       |  13 ++
 3 files changed, 132 insertions(+), 42 deletions(-)
Provider: deepseek-chat (api.deepseek.com)
Candidates: 154 | processing: 25 (offset=0)
[1/25] id=13728 Радиатор биметаллический Royal Thermo PianoForte 500 Серебри
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[2/25] id=13730 Радиатор биметаллический Royal Thermo PianoForte 500 Серебри
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[3/25] id=13731 Радиатор биметаллический Royal Thermo PianoForte 500 Серебри
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[4/25] id=13737 Радиатор Royal Thermo PianoForte 500 Bianco Traffico VDR80 -
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[5/25] id=13765 Радиатор Royal Thermo PianoForte Tower 200 /Noir Sable - 18 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[6/25] id=13766 Радиатор Royal Thermo PianoForte Tower 200 /Noir Sable - 22 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[7/25] id=13767 Радиатор Royal Thermo PianoForte Tower 200 /Silver Satin - 1
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[8/25] id=13768 Радиатор Royal Thermo PianoForte Tower 200 /Silver Satin - 2
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[9/25] id=13769 Радиатор Royal Thermo PianoForte Tower 300 /Bianco Traffico 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[10/25] id=13771 Радиатор Royal Thermo PianoForte Tower 300 /Noir Sable - 18 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[11/25] id=13772 Радиатор Royal Thermo PianoForte Tower 300 /Noir Sable - 22 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[12/25] id=13773 Радиатор Royal Thermo PianoForte Tower 300 /Silver Satin - 1
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[13/25] id=13775 Радиатор Royal Thermo PianoForte Tower 500 new/Bianco Traffi
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[14/25] id=13777 Радиатор Royal Thermo PianoForte Tower 500 new/Silver Satin 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[15/25] id=13779 Радиатор Royal Thermo PianoForte Tower 500 new/Noir Sable - 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[16/25] id=13791 Радиатор биметаллический Royal Thermo Infinity 500 Silver Sa
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[17/25] id=13798 Радиатор биметаллический Royal Thermo Infinity 300 Bianco Tr
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[18/25] id=13799 Радиатор биметаллический Royal Thermo Infinity 300 Bianco Tr
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[19/25] id=13805 Радиатор биметаллический Royal Thermo Infinity 300 Noir Sabl
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[20/25] id=13809 Радиатор биметаллический Royal Thermo Infinity 300 Silver Sa
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[21/25] id=13810 Радиатор биметаллический Royal Thermo Infinity 300 Silver Sa
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[22/25] id=13811 Радиатор биметаллический Royal Thermo PianoForte 500 Белый V
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[23/25] id=13846 Радиатор биметаллический Royal Thermo PianoForte 200 Чёрный 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[24/25] id=13901 Комплект настенных регулируемых кронштейнов Royal Thermo Des
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
[25/25] id=13981 Радиатор биметаллический Royal Thermo BILINER B 350 Белый - 
  source context: https://rusklimat.by/ (90 chars, 7 specs)
  skipped: source context is too short (90 chars, min is 120)
+---------+-------+
| action  | count |
+---------+-------+
| updated | 0     |
| skipped | 25    |
| errors  | 0     |
+---------+-------+

129 more remain. Continue with --offset=25

```
