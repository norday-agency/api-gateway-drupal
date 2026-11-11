# Includes kiezen via de gateway

`2026-11-11` · Marco Koopman · aanleiding: norday-agency/prinsenhof#36

## Waarom

- Een lijst uit de gateway stuurt geen `include` naar Drupal, dus Drupal laadt bij elk item de volledige `default_include` mee.
- Voor een overzicht met kaarten is dat veel te veel: 11 verhalen op Prinsenhof geven 451 meegeladen items (1 MB), terwijl een kaart alleen de headerafbeelding nodig heeft (33 items).
- Op acceptatie liep die lijst daardoor na 28 seconden vast (504).
- Projecten lossen dat nu op met een eigen controller per type, die de gateway-logica kopieert. Meer projecten lopen hier tegenaan.
- Met een `include`-parameter vraagt de frontend alleen op wat hij nodig heeft, voor elk project op dezelfde manier.

## Scope

- **Wel:** `?include=` op lijst- en detail-endpoints van content types in de gateway.
- **Wel:** een gateway-endpoint dat per type de beschikbare includes teruggeeft, gevuld vanuit Drupal.
- **Wel:** een Drupal-endpoint in `drupal_laravel_cache` dat de `default_include` per resource teruggeeft.
- **Wel:** `?limit=` op lijsten, en een instelling om een lege pagina als 200 terug te geven.
- **Wel:** een README-sectie voor de frontend.
- **Niet:** alleen bepaalde velden ophalen (`fields[...]`).
- **Niet:** de vertalingen per item die app-resources ophalen; dat is projectcode.
- **Niet:** de `feature/wip-config`-branch.
- **Niet:** de bestaande controllers in projecten weghalen; dat doet elk project na de release.

## Acceptatiecriteria

Drupal (`drupal_laravel_cache`):

1. `GET /{locale}/gateway/includes` geeft zonder login JSON terug met per JSON:API-resourcepad de `default_include`-lijst, bijvoorbeeld `{"stories": ["header_image.image.image", "components", ...]}`.
2. Resources zonder `default_include` staan er met een lege lijst in. Er staat geen andere configuratie in.

Gateway (`api-gateway-drupal`):

3. `GET /admin/v1.0/includes` geeft per type uit `cms.types` de lijst van Drupal terug, als `{ data: { stories: [...] }, statusCode: 200 }`.
4. Die lijst wordt `cms.cache.ttl` gecachet met de tag `includes`.
5. Zonder `include` werken lijst en detail precies zoals nu.
6. Met `?include=a,b` stuurt de gateway `include=a,b` naar Drupal, bij een lijst en bij een detail (`/nl/verhalen?include=...` en `/nl/verhalen/{slug}?include=...`).
7. Met een leeg `?include=` stuurt de gateway een lege `include`, en laadt Drupal niets mee.
8. Een pad mag als het in de lijst staat of het begin is van een pad in de lijst: `header_image` mag als `header_image.image.image` erin staat.
9. Vraagt de frontend een pad dat niet mag, dan geeft de gateway `400` met `{ statusCode: 400, statusText: "Invalid include", data: { invalid: [...], allowed: [...] } }`. Drupal wordt dan niet aangeroepen.
10. Kan de gateway de lijst niet ophalen (bijvoorbeeld een oude `drupal_laravel_cache`), dan negeert hij `?include` en werkt hij zoals nu. `GET /admin/v1.0/includes` geeft dan `503`.
11. Aanvragen met een andere `include` of `limit` delen geen cache-entry.
12. `?limit=n` op een lijst gebruikt `n` als die tussen 1 en `cms.pagination.max_limit` (standaard 48) ligt. Anders geldt `cms.pagination.limit`. `pagination.limit` in het antwoord is de gebruikte waarde.
13. Staat `cms.pagination.empty_page_ok` aan, dan geeft een lijstpagina zonder items `200` met `data: []` en `pagination`. Standaard staat die uit en blijft het `404`.
14. De README legt uit hoe de frontend includes opzoekt en meegeeft, met een voorbeeld voor een kaartenlijst.

## Domeinmodel

- **Include-pad:** een relatie met punten ertussen, zoals `header_image.image.image` (paragraaf → media → bestand).
- **Default includes:** wat Drupal meelaadt als er geen `include` wordt meegegeven; ingesteld per resource in JSON:API Extras.
