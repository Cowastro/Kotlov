# TD submersible pump audit — 2026-11-22

Read-only production audit: 58 exactly linked products; all have imported technical specifications.

| Category | Products | Rendering issue |
|---|---:|---|
| Borehole | 37 | Fixed in PR #422; supplier-labelled facts |
| Drainage | 8 | Additional label aliases needed for ECO / ASILAK |
| Sewage / fecal | 6 | Generic attributes already visible |
| Well / vibration | 6 | Generic attributes already visible |
| Sewage lifting unit | 1 | Generic attributes already visible |

The drainage fix preserves original supplier units and values. It does not promote supplier claims into the manufacturer passport registry or numeric picker filters. Exact identity checks are retained. All eight drainage cards have valid exact bindings.

Two ECO 3-in-1 pumps have mode-specific particle sizes. Ambiguous multi-colon values are omitted rather than combining modes into a single fabricated maximum. ASILAK remains in drainage: the supplier explicitly warns against fecal use. No categories, prices, stock, products or supplier records were changed.

HTTP checks of representative borehole, drainage, fecal, well and lifting-unit pages returned 200. The AGELESS page contains 115 m and the TD provenance notice after #422. Drainage is rechecked after the additional deployment.

Validation: 17 focused tests, 11838 assertions.

| ID | Supplier article | Category | Product |
|---:|---|---|---|
| 43703 | DI-903 | drenajnyie | Насос погружной для загрязненной воды, нерж. ECO DI-903, 900Вт |
| 43704 | DP-753 | drenajnyie | Насос погружной для загрязненной воды ECO DP-753, 750Вт |
| 43705 | DP-601 | drenajnyie | Насос погружной для загрязненной воды ECO DP-601, 600 Вт |
| 43706 | CP-405 | drenajnyie | Насос погружной для чистой воды ECO CP-405, 400 Вт |
| 43803 | EC4210-1 | drenajnyie | Насос погружной 3-в-1 ECO DP-606i |
| 43804 | EC4210-2 | drenajnyie | Насос погружной 3-в-1 ECO DP-916i |
| 43810 | AS4410-1 | drenajnyie | Насос погружной для грязной воды ASILAK SLP-1500 |
| 43811 | AS4410-2 | drenajnyie | Насос погружной для грязной воды ASILAK SLP-2001 |
| 43774 | AP02A01 | fekalnye-nasosy | Насос погружной фекальный Marvel 7000 A&P |
| 43775 | AP02A02 | fekalnye-nasosy | Насос погружной фекальный Marvel CD 1.1 A&P |
| 43776 | AP02A03 | fekalnye-nasosy | Насос погружной фекальный Marvel CD 1.3 A&P |
| 43777 | AP02A06 | fekalnye-nasosy | Насос погружной фекальный Marvel 1200 FE 333/12 A&P |
| 43778 | AP02A04 | fekalnye-nasosy | Насос погружной фекальный Marvel 600 FC 150/8 A&P |
| 43779 | AP02A05 | fekalnye-nasosy | Насос погружной фекальный Marvel 800 FE 250/10 A&P |
| 43839 | AP02A08 | kanalizatsionnye-nasosy | Канализационная насосная установка Marvel SmartLift, A&P |
| 43725 | AP01C06 | nasosy-dlya-kolodtsa | Насос погружной для чистой воды GOOD WORK 3-4800/20-7/3 A&P |
| 43809 | EC4213-1 | nasosy-dlya-kolodtsa | Насос вибрационный ECO CPV-330 (10м, верхний забор) |
| 43814 | EC4213-4 | nasosy-dlya-kolodtsa | Насос вибрационный ECO CPV-331 (15м, верхний забор) |
| 43815 | EC4213-5 | nasosy-dlya-kolodtsa | Насос вибрационный ECO CPV-332 (25м, верхний забор) |
| 43816 | EC4213-6 | nasosy-dlya-kolodtsa | Насос вибрационный ECO CPV-333 (10м, нижний забор) |
| 43844 | 456816 | nasosy-dlya-kolodtsa | Насос вибрационный Вихрь ВН-10В 68/8/1 (10м, верхний забор) |
| 43718 | AP01B06 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/31-3/11 A&P |
| 43719 | AP01B07 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/45-3/16 A&P |
| 43720 | AP01B08 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/60-3/21 A&P |
| 43721 | AP01C02 | skvajinnye-nasosy | Насос скважинный центробежный GOOD WORK 3-2100/32-2/11 A&P |
| 43722 | AP01C03 | skvajinnye-nasosy | Насос скважинный центробежный GOOD WORK 3-2100/43-2/15 A&P |
| 43723 | AP01C04 | skvajinnye-nasosy | Насос скважинный центробежный GOOD WORK 3-2700/23-3/8 A&P |
| 43724 | AP01C05 | skvajinnye-nasosy | Насос скважинный центробежный GOOD WORK 3-2700/31-3/11 A&P |
| 43728 | AP01B02 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/32-2/11 A&P |
| 43729 | AP01B03 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/43-2/15 A&P |
| 43730 | AP01B04 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/60-2/21 A&P |
| 43738 | AP01A13 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/74-3/26-A A&P |
| 43739 | AP01A14 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/88-3/31-A A&P |
| 43740 | AP01A15 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/105-3/37-A A&P |
| 43741 | AP01A16 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-2.5-1500/52-2/31-A A&P |
| 43742 | AP01A18 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-3600/62-4/20-A A&P |
| 43743 | AP01A19 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-3600/75-4/24-A A&P |
| 43744 | AP01A20 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-3600/87-4/28-A A&P |
| 43745 | AP01A01 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/23-2/8-A A&P |
| 43746 | AP01A02 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/32-2/11-A A&P |
| 43747 | AP01A03 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/43-2/15-A A&P |
| 43748 | AP01A04 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/60-2/21-A A&P |
| 43749 | AP01A09 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/23-3/8-A A&P |
| 43750 | AP01A10 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/31-3/11-A A&P |
| 43751 | AP01A11 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/45-3/16-A A&P |
| 43752 | AP01A12 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2700/60-3/21-A A&P |
| 43753 | AP01A05 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/77-2/27-A A&P |
| 43754 | AP01A06 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/95-2/33-A A&P |
| 43755 | AP01A07 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/109-2/38-A A&P |
| 43756 | AP01A08 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/132-2/46-A A&P |
| 43818 | DG4310-2 | skvajinnye-nasosy | Насос скважинный винтовой DGM BP-55W |
| 43819 | DG4310-3 | skvajinnye-nasosy | Насос скважинный винтовой DGM BP-75W |
| 43820 | DG4310-1 | skvajinnye-nasosy | Насос скважинный винтовой DGM BP-37W |
| 43832 | AP01A17 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-2.5-1500/40-2/24-A A&P |
| 43833 | AP01A21 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-2.5-1500/67-2/40-A A&P |
| 43834 | AP01C07 | skvajinnye-nasosy | Насос скважинный центробежный GOOD WORK 3-2100/60-2/21 A&P |
| 43841 | AP01C01 | skvajinnye-nasosy | Насос скважинный центробежный GOOD WORK 3-2100/23-2/8 A&P |
| 44388 | AP01B01 | skvajinnye-nasosy | Насос скважинный центробежный AGELESS-3-2100/23-2/8 A&P |
