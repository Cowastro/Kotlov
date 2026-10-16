# KOTLOV XO Ceramic PRO boiler package — 2026-10-16

User authorised publication under KOTLOV, retail **25,600 BYN including VAT**, with a **1.5 m³ hopper included**. The source DOC contains 25,650 BYN in one column; the explicit user price takes precedence. No purchase price or 1C link is inferred.

Sources:

- User file `Котлов.doc`: base boiler KСТБ-95, boiler warranty 30 months. It includes a different burner/controller. Its conflicting dimensions and generic KСТБ-10 parameter heading are not copied to the new package.
- https://pkmercury.by/product/kotel-kstb-pelletnyj-drevesnyj-i-agro-pellet/ — base steel boiler construction and installation layout. The generic page lists 20 kW, so its nominal-power field is not used for this 95 kW offer. Its burner, efficiency and control specifications are not transferred.
- https://kotlov.by/akcii/kotlov-xo-ceramic-pro and the linked 100 kW burner card — Ceramic PRO 100, 30–100 kW burner range, wood pellets 6–8 mm, AISI 310S moving grates, ceramic ignition, removable firebox, XO Wi-Fi control, 24-month burner warranty. The separate burner discount is not applied to the package.

Rated boiler power remains 95 kW, not 100 kW. Burner setup must respect boiler capacity. No unverified package efficiency, heating area, overall dimensions, agricultural-pellet compatibility, automated ash removal or certification is advertised.

User additionally requested prominent controller features: internet/Wi-Fi included by default without a separate module; online temperature/status/graphs and on/off control; fuel/air tuning; unified ignition/feed/cleaning/pump control; sensor/protection capabilities conditional on the installed configuration. A working Wi-Fi network with internet access is required. These capabilities are already described on the linked KOTLOV promotion page; no additional cloud service or mobile-app claims are made.

The package has no confirmed warehouse balance: initial stock is zero with a consultation/order status. Reruns preserve subsequent stock changes. The command does not modify the standalone burner, integrations, sources or supplier costs.

## Images

Built-in imagegen used with the imagegen skill. The primary asset is explicitly labelled a visualization, not a photograph of an installed KOTLOV package. Supporting gallery images show the actual burner, firebox and XO controller.

Reference inputs: the source installation photograph, actual Ceramic PRO 100 product image, real XO controller photograph and full controller housing illustration from the XO manual cover. No technical document for a different burner is published as the package passport.

Final correction brief: preserve source boiler, hopper, auger and Ceramic PRO housing; remove the invented external circular fan and all mounted controller depictions. The user's final choice is a separate complete XO controller inset below the boiler/hopper, taken from the manual cover, labelled «Контроллер XO — в комплекте». Preserve natural enclosure proportions, KOTLOV branding and «Визуализация комплекта».

Final saved asset: `public/img/products/kotlov-xo-boiler-95/kit-visualization-v5.png`.

Exact final edit prompt:

> Use case: compositing, exact product reference. Image 1 is edit target (boiler with hopper visualization). Image 2 is the instruction manual cover; only the central complete controller enclosure in this cover is the supporting insert. User asks NOT to mount controller on boiler or hopper. Remove the controller from the red hopper wall and restore plain matching red steel there. Keep boiler, hopper, auger and burner exactly unchanged, no new fans or other hardware. Extend the light studio canvas DOWNWARD to create a clean lower product-information strip. In this lower strip display the complete controller enclosure from the CENTRAL illustration in image 2 as a separate small clear product inset, NOT physically attached to anything, preserving its entire natural proportions, broad grey metal cabinet, narrow left display/buttons panel, connectors and wiring exit. Take the controller directly from that manual illustration as faithfully as possible; do not redesign, elongate or narrow it. Exclude all other manual cover content: no icons, optional panel, accessory block, title text or page furniture. Beside the standalone controller place the exact caption "Контроллер XO — в комплекте". Leave generous padding. Keep the existing bottom disclaimer "Визуализация комплекта" readable, reposition to lower edge if needed. The upper kit visualization remains primary and is unchanged except the controller removed from hopper. Clean factual catalog composition, no fictional controls, no additional hardware.

## Publication

Additional real workshop photograph: `public/img/products/kotlov-xo-boiler-95/installation-retouched-v1.png`, lightly retouched with built-in imagegen. It remains an example of the base boiler/hopper layout, with explicit disclosure that its burner and automation differ from the offered KOTLOV kit. It is the second gallery image, not presented as evidence of an installed current XO package.

Retouch prompt:

> undefined

Command: `catalog:publish-kotlov-ceramic-boiler` (dry run); add `--apply` to publish. Existing exact identity only; conflicting SKU/slug/brand or an existing integration link blocks writes. Images and existing KOTLOV brand / pellet-boiler category are required. Repeated application creates no duplicate.

Public URL: `/kotly-na-pelletah/pelletnyy-kotel-kotlov-xo-ceramic-pro-95-kvt-bunker-1500`.

## Technical expansion and SEO

On user request, added the original unmodified base-boiler cutaway extracted from the supplied DOC as `public/img/products/kotlov-xo-boiler-95/base-boiler-cutaway.png`, both in the gallery and in the description with an enlargement link and explanatory numbered components. It describes the base body, not the exact XO mounting configuration.

Flue-gas temperature is **at least 140 °C**, not a maximum or a fixed setpoint. This agrees in the supplied DOC and the manufacturer's generic pellet-boiler page. Working water pressure **up to 0.25 MPa (2.5 bar)** and minimum water temperature **55 °C** agree in both sources and are explicitly scoped to the base body. The two sources disagree on maximum water temperature (95 vs 90 °C) and draft (20 vs 40 Pa), so neither contradictory value is published. In particular, no full package certification or efficiency is inferred from these fields.

The KOTLOV promotion page separately confirms the burner flow **up to 335 m³/h at 200 °C**; 200 °C is the reference condition for that flow, not a boiler operating limit. Burner-only dimensions, mass and electrical power are labelled separately from the package. Added technical headings, visible FAQs and revised title/description without changing retail, nominal boiler power or stock.

## Manufacturer photographs and correction — 2026-10-16

This section supersedes the earlier visualization publication notes. The user supplied five current installation photographs and the manufacturer's correction: preserve the lower ash collector and original low base; use the current all-red hopper, not the obsolete two-tone visualization. The source installation uses a different burner and controller.

An imagegen compositing attempt incorrectly exposed the combustion chamber outside the boiler. The user rejected that orientation and requested no invented construction. **No new generated installation image is published.** Existing v4/v5 visualization and the old retouched installation are removed from the product gallery, but source files are preserved. All five supplied photographs are copied unchanged to `public/img/products/kotlov-xo-boiler-95/installation-current-*-original.jpg`; the primary is `installation-current-wide-original.jpg`. The exact Ceramic PRO burner, real firebox and real XO controller remain separate gallery entries. A prominent product note and article-cover caption explicitly distinguish the actual base-boiler photographs from the offered XO configuration.

Built-in imagegen (imagegen skill) was used, not CLI. The unpublished studio draft is `public/img/products/kotlov-xo-boiler-95/kit-visualization-v6.png`; it is not staged for deployment. Compositing brief: use manufacturer photos for exact boiler/ash collector/current red hopper geometry; replace only the other burner with the reference Ceramic PRO, combustion chamber entirely inside boiler; preserve original low supports; retain XO controller as a separate inset and label the image a visualization. The workshop draft violated burner-orientation constraints and was rejected. A targeted closed-housing correction was generated for review only and is not referenced by the site.

The photo migration updates only `images` and the explanatory content, retaining unrelated gallery entries and manager text. Retail, purchases, warehouse balances and integrations are untouched. A separate additive migration publishes the article `/blog/pelletnyy-kotel-95-kvt-kotlov-xo-ceramic-pro-podbor-kotelnoy` and adds a useful selection block/link to the card. Editorial articles/drafts with the same slug are never overwritten or auto-published. No efficiency, heating-area or fixed-autonomy promises are invented.
