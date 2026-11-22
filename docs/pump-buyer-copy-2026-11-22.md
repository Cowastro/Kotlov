# Buyer-facing pump descriptions

Replace import/passport-workflow notices in pump cards with useful category-appropriate
buyer copy, model-specific specifications and plain selection guidance. The same facts
feed meta descriptions and Product structured data. No inferred digits from model names,
unreviewed imported claims, manufacturer-verification flags or numeric filters are added.

The exact selected model facts remain the source: manufacturer registry where available,
otherwise the already identity-checked TD supplier detail fields. Source records and
editorial content remain in the database, unchanged. A missing passport does not produce
a public workflow status or a fabricated numeric description. Actual manual links remain.

Remove the public supplier/export and unfinished-check notices, including the short
text beside the price. Keep useful technical fields; render malformed unit typography
as m³/h / м³/ч and мкФ. The neutral label «Артикул» replaces «Артикул поставщика».

AGELESS AP01A20 example uses only its own source facts: 1500 W, max head 115 m,
max flow 5.4 m³/h, diameter 3 inches / 7.62 cm, length 1.73 m, immersion 75 m.
Selection guidance explains that actual delivery depends on lift and pipe length;
it does not promise simultaneous maximum head and flow or a matching bore size.

Validation: TdPumpSpecificationsTest and HouseholdPumpPickerTest, 17 tests.
Only the affected pump tests are run, per the user's request to avoid full-suite runs.
