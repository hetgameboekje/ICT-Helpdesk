# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Leen van Punt Intranet Ticketsysteem — a modular intranet in plain PHP (no framework), MySQL/MariaDB. Core domain is ticket management, plus supporting modules (kennisbank, voorraad, agenda, CRM, etc.). Documentation, code comments, routes and UI text are in Dutch; keep new user-facing text and route segments in Dutch for consistency.

There is no composer.json, no autoloaded third-party dependencies, and no automated test suite — verification is done by running the app locally and exercising the affected flow.

## Commands

Run the app locally (no Apache/Laragon required):
```bash
php -S localhost:8000 -t public public/router.php
```

Database:
```bash
php database/parse.php     # regenerate database/.parsed/schema.sql from database/xml/*.xml
php database/seed.php      # seed demo user (timo@bergthaler.nl / demo123, override via SEED_USER_* in .env)
php database/clear.php --force   # drop all local tables and rebuild schema
php database/rename_database.php <nieuwe_naam> [--drop-old]   # rename the live DB in place (no dump/restore)
```
For Hostnet (no php-cli access), `database/rename_database_hostnet.sql` does the same rename via plain `RENAME TABLE` statements, runnable from phpMyAdmin's SQL tab — see the comment header in that file for the required steps (new database must be created via Hostnet's control panel first).
Table definitions live as XML in `database/xml/*.xml`; edit those, not `database/schema.sql` directly, then run `php database/parse.php`. The Beheer UI ("Database toepassen") can also add missing tables/columns automatically but never alters an existing column type.

Windows dev helper (interactive menu for the above plus git pull, `.env` rebuild, pulling a live DB dump):
```powershell
powershell -ExecutionPolicy Bypass -File scripts\dev-tools\dev-tools.ps1
```

No build step, linter, or test runner is configured — there's nothing to run beyond starting the PHP server and using the app/browser to verify changes.

## Architecture

Front controller is `public/index.php`, which requires `app/bootstrap.php` (PSR-4-ish autoloader under `App\`, `.env` loading, session start, timezone) and then `public/index.php` itself registers every route on a custom `Router` (`app/Core/Router.php`) before calling `dispatch()`. There is no separate routes file beyond `public/index.php` — new endpoints are added there.

- `app/Core` — shared infrastructure: `Router` (regex-based, `{id}` → numeric param), `Model` (static active-record-style base class: `all/find/create/update/delete/restore`, supports `$fillable` and soft deletes via `$softDeletes`/`deleted_at`), `Database` (lazy singleton PDO, sets MySQL session timezone to match `Europe/Amsterdam`), `DevSync` (dev-only auto git-pull + schema sync on `/login`), `Table`/`TableQuery` (list/table rendering helpers), `Mailer`, `Barcode`, `Xlsx`.
- `app/Shared` — cross-cutting concerns reused by multiple modules: `Auth` (session-based login), `User`/`Rechten` (permissions), `ApiKey` (scoped API keys for external scripts, e.g. email intake), `Crypto\FieldEncryptor` (field-level encryption for sensitive ticket columns), `Mail\EmailQueueProcessor` (queued outbound mail), `Log\PaginaBezoekLogger` (every dispatched request is logged), `Automation` (email intake / reminder cron endpoints), `Dashboard`, `Overview`, `Legal`.
- `app/Modules/<Name>` — one folder per business module (Ticket, Verbeterpunt, Reflectie, Kennisbank, HardwareUitgave, Medewerker, Voorraad, Device, Printer, CyberRisico, Uitgifte, Agenda, Account, Beheer, Tools, Script, Schijfgebruik, EmailVerwerking). Each module has its own `*Controller.php`, `Models/*Model.php` (extends `App\Core\Model`), and `Views/<Name>View/*.php`. Most modules with loggable activity have a paired `*LogController` + log model (ticket_logs, verbeterpunt_logs, etc.) following the same pattern — copy an existing module's shape (e.g. Reflectie or CyberRisico) when adding a new one.
- `app/Modules/EmailVerwerking` — "E-mail & kennisbank verwerking" (MailMind): turns incoming support e-mail into logged history, AI classification, and Kennisbank drafts pending review. `EmailImportController`/`EmailAnalysisController` are machine-to-machine (API-key scopes `email_import`/`email_analysis`, same webhook/cron pattern as `TicketEmailIntakeController`/`AutomationController`); `EmailVerwerkingController` is the session-auth UI (rechtenmatrix module `email_verwerking`). `Services\AiAnalysisService` posts each e-mail to an external n8n webhook (`config/config.php` 'n8n', `N8N_WEBHOOK_URL`/`N8N_API_KEY`) which performs the actual classification/extraction and is expected to return the same fixed JSON schema `analyseer()` validates; `Services\KbDraftGenerator` turns a classification into (or attaches it to an existing) `kb_article_drafts` row, later published into `kennisbank_artikelen` via `KbArticleDraftModel::publiceer()`. The Outlook intake script (`scripts/automation/outlook-intake/outlook_intake.py`) posts end-user mail to `/api/email-import/inbound` alongside creating the ticket (best-effort, never blocks ticket creation); a separate Taakplanner-style cron hits `/api/email-analysis/verwerken` to run AI analysis in batches.
- `app/Views/layouts` — shared page layouts included by module views.

Routing convention: most modules are wired via the `$modules` array in `public/index.php`, which mechanically registers the standard `index/create/store/show/edit/update/destroy` routes (`GET /X`, `GET /X/create`, `POST /X`, `GET /X/{id}`, `GET /X/{id}/edit`, `POST /X/{id}`, `POST /X/{id}/verwijderen`) for each controller. Module-specific extra routes (log entries, exports, nested actions) are added individually below that loop.

### Config and environments

`config/config.php` reads from `getenv()`; `.env` (gitignored, copy from `.env.example`) is one file shared across environments, holding both a `LOCAL_*` and a `HOSTNET_*` block (DB/mail/URL). `APP_ENV=local|hostnet` is the one line set manually per place — it picks which prefixed block `config.php` reads, and also fully derives `dev`/`gitPullEnabled` behavior (and, in `app/bootstrap.php`, `display_errors`): anything other than `hostnet` counts as a dev environment (auto git pull + schema-sync on `/login`, PHP errors shown), `hostnet` always has all three off. There's no separate `APP_DEV`/`APP_GIT_PULL_ENABLED`/`APP_DEBUG` key anymore — they used to be settable per environment but in practice always tracked `APP_ENV`, so they were collapsed into it.
- `App\Core\DevSync` reads `config('dev')`/`config('gitPullEnabled')` — unaffected by the collapse, still booleans.
- `APP_ENCRYPTION_KEY` — base64 32-byte key for `App\Shared\Crypto\FieldEncryptor`, used to encrypt sensitive ticket fields (`omschrijving`, `opdrachtgever_naam`). Must be identical across all environments sharing a database — rotating it makes existing encrypted tickets unreadable. Generate with `openssl rand -base64 32`. Not per-environment-prefixed.
- `{LOCAL,HOSTNET}_APP_URL` — base URL used to build absolute links in emails (e.g. reminder emails), since there's no active HTTP request context there.
- `N8N_WEBHOOK_URL` / `N8N_API_KEY` / `AI_CONFIDENCE_DREMPEL` — `App\Modules\EmailVerwerking\Services\AiAnalysisService` posts the e-mail to this n8n webhook instead of calling an AI provider directly; n8n owns ingestion/extraction/knowledge-matching/internet-lookup and must return the schema `analyseer()` validates. Not per-environment-prefixed, same as `APP_ENCRYPTION_KEY`. Empty `N8N_WEBHOOK_URL` fails closed: the analysis endpoint logs the error to `processing_logs` and leaves the e-mail on status `stored` for the next cron run, instead of throwing.

Deployment target is Hostnet shared hosting (no SSH): `.env` on that server has `APP_ENV=hostnet`, deploy via SFTP, schema applied through phpMyAdmin, `public/uploads/` must be writable.

## Frontend design direction

**Superseded (2026-07-24):** the navy/amber/teal palette and Space Grotesk typography described
below (originally prototyped in `docs/design/ticketsysteem-overzicht.html`) have been replaced by
a new concept designed in Lovable — project "Leen van Punt Hub"
(`https://lovable.dev/projects/4675b36f-276e-4fc5-9606-d83a98f9d801`, preview:
`https://id-preview--4675b36f-276e-4fc5-9606-d83a98f9d801.lovable.app`): emerald primary + amber
statuskleur op een warm-wit canvas, een altijd-donkere linker sidebar (vervangt de top-navbar),
Inter + JetBrains Mono. `docs/design/ticketsysteem-overzicht.html` is no longer the source of truth
for tokens — treat the Lovable project's `src/styles.css` / `src/components/app-sidebar.tsx` /
`src/components/status-badge.tsx` as that instead (fetch via the Lovable MCP `read_file` tool).
The full phased rollout plan lives at `C:\Users\Timo\.claude\plans\federated-waddling-anchor.md`.

**Design tokens** (CSS custom properties in `public/assets/css/app.css`, both light and
`[data-bs-theme="dark"]` blocks — the `--color-*` naming and the `data-bs-theme`/`localStorage.theme`
toggle are kept as-is, values replaced):
- `--color-primary`/`--color-accent-*` — emerald (`oklch(0.58 0.13 165)` light, brighter in dark),
  solid-fill button style now (not the previous soft-tint amber `.btn-accent`).
- `--color-status-open/wachtend/behandeling/opgelost/gesloten` (+ `-bg` pairs) — the fixed
  status→color mapping, reused by every module's status badges via `.badge-open`,
  `.badge-progress`/`.badge-in_behandeling`/etc. (see the alias groups in `app.css`).
  `--color-background-info`/`--color-text-info` now just alias `--color-status-open*`.
- `--color-risk-laag/gemiddeld/hoog/kritiek` (+ `-bg`) — CyberRisico's own scale, deliberately not
  reusing the status colors. `--color-stock-ok/low` (+ `-bg`) — Voorraad indicators.
- `--color-sidebar-*` — defined once in `:root` (not duplicated in the dark block): the sidebar
  stays dark in both light and dark app theme, per the Lovable design.
- Typography: `--font-display` now just aliases `--font-body` (Inter) — Lovable has no separate
  display font. `--font-mono` is JetBrains Mono (was IBM Plex Mono). Google Fonts `<link>` updated
  in both `app/Views/layouts/app.php` and `guest.php`.
- Radius bumped slightly (`--border-radius-md:8px`, `--border-radius-lg:12px`) to match the
  mockup's roomier rounded corners.

**Reusable component patterns** from the Lovable project, to reuse rather than reinvent per module:
grouped sidebar nav (Werkplek/Support/Assets & Beheer/HR & CRM/Systeem) with search; KPI/stat cards
(icon top-right in a colored square, big value, small sublabel); status badges with a colored dot
(`.badge::before`) — reuse the same badge-to-status mapping everywhere, don't invent new per-module
colors; data tables with muted uppercase headers and row hover; split-view detail pages (form +
timeline/log + related records); risk-matrix component for CyberRisico; terminal-style code blocks
for scripts/commands.

**Klikbaar sorteren op tabelkoppen — bewuste uitbreiding bovenop de mockup (2026-07-25):** de
Lovable-mockup zelf heeft nergens klikbaar sorteren (geverifieerd via Lovable MCP `read_file` tegen
zowel `src/routes/tickets.index.tsx` als `src/routes/modules.voorraad.tsx` — platte `<th>`, geen
`onClick`/sorteericoon). Dit is dus geen 1-op-1-conversie maar een eigen toevoeging bovenop de
mockup, op uitdrukkelijk verzoek. Geïmplementeerd als één herbruikbare helper,
`public/assets/js/table-sort.js` (`sortableHeaderHtml()` + `bindSortableHeaders()`), die bewust
dezelfde `.th-sort`/`.sort-arrow`-CSS-klassen hergebruikt als de al bestaande server-rendered
`sortLink()`-helper (`app/Views/partials/ticket-helpers.php`) — zodat het patroon er identiek
uitziet op modules die nog niet naar de JSON-API zijn overgezet. Backend-ondersteuning loopt via de
generieke `sort`/`dir`-querystring die `App\Core\TableQuery::apply()` al afhandelt voor elk
lijst-endpoint dat `TableQuery` gebruikt; alleen kolommen die corresponderen met een echte
databasekolom (geen client-side-only afgeleide waarden) zijn sorteerbaar gemaakt.
Toegepast op: Tickets (`tickets-index.js`, kolommen Nummer/Onderwerp/Melder/Behandelaar/Categorie/
Status/Bijgewerkt) en Voorraad (`voorraad-index.js`, kolommen Artikel/Status/Locatie).
**Vervolgstap (nog niet gedaan):** hetzelfde patroon toepassen op de overige al-naar-JSON-API-
geconverteerde modules zodra die client-side lijst-pagina's krijgen (momenteel alleen Kennisbank,
zie hierboven); voor de nog server-rendered modules (Verbeterpunt, Reflectie, Device, Printer,
CyberRisico, Uitgifte) bestaat de PHP-variant van dit patroon al via `sortLink()`.

**Rollout plan (see the plan file above for full detail; step by step, verify manually after each
since there's no test suite):**
0. ✅ Done — tokens/typography replaced in `public/assets/css/app.css`, fonts swapped in both layouts.
1. ✅ Done — sidebar navigation. **Dashboard** (`app/Views/dashboard/index.php`, rendered by
   `app/Shared/Dashboard/DashboardController.php`) rebuilt 1-op-1 against Lovable's
   `src/routes/index.tsx` (fetched live via the Lovable MCP `read_file` tool, not the committed
   `docs/design/` copy — see the "echte Lovable-conversie"-besluit onder API-architectuur hieronder
   voor de reden dat gecommitte kopie niet betrouwbaar is). Gebouwd: topbar-titel "Goedemorgen/middag/
   avond, {voornaam} 👋" + breadcrumb "Werkplek" via `$pageTitle`/`$breadcrumbs`; 4-koloms KPI-rij
   (`.kpi-grid`/`.kpi-card`, nieuwe iconklassen `.kpi-icon-wachtend`/`.kpi-icon-risk` toegevoegd naast
   de bestaande `-open`/`-behandeling`/`-opgelost`/`-neutral`); 2-koloms `.dashboard-grid` met "Recente
   tickets" (rij-items i.p.v. `<table>`, nieuwe `.row-list`/`.row-list-item`-klassen) en "Agenda
   vandaag" (`.agenda-today-item` met kleur-accentrand per event-type + een "nu"-indicator voor het
   lopende item); "Snelkoppelingen" als kaartgrid (`.shortcut-grid`/`.shortcut-card`). **Bewuste
   afwijking van de mockup-KPI's:** Lovable toont "Wacht op reactie" en "Opgelost vandaag" met
   verzonnen delta-getallen ("+6 vs. gisteren"). "Wacht op reactie" bleek wél een echte, bestaande
   status te hebben (`tickets.status = 'wacht_op_info'`, al gebruikt door `TicketModel::actief()`/de
   statusbadges) en is dus met een echte telling gebouwd (`TicketModel::countByStatus('wacht_op_info')`).
   Voor "Opgelost vandaag" bestaat geen vergelijkbare telling (`opgelost`/`gesloten` zijn oude,
   samengevoegde statuswaarden zonder datumfilter) — vervangen door "Open cyberrisico's"
   (`CyberRisicoModel::countOpen()`, al elders op het dashboard gebruikt), zelfde aanpak als de
   Kennisbank-KPI-vervanging hieronder. Delta-teksten zijn overal weggelaten — geen enkele bestaande
   telling heeft een vergelijkingswaarde, dus er is nergens een getal verzonnen. **Echte
   functionaliteit die de mockup niet toont, bewust behouden** (het omgekeerde van de normale
   weglaat-regel, expliciet gevraagd voor dit scherm): de "Nieuw ticket"/"Risico melden"/"Dag starten"/
   "Dag afsluiten"-knoppen + modals (nu in een `.dashboard-actionbar` boven de KPI's i.p.v. naast de
   titel), de klikbare Chart.js-cyberrisicografiek (opent een incidenten-modal per dag), en de
   agenda-widget met volledige CRUD (`/agenda/events` fetch + opslaan/verwijderen) — dit blijft
   server-rendered PHP + vanilla JS (geen JSON-API-laag zoals bij Tickets, zie "API-architectuur"
   hieronder). Lokaal geverifieerd (ingelogd via geseede gebruiker, HTML-response gecontroleerd op
   PHP-fouten/warnings): dashboard rendert met echte data (open tickets, wacht_op_info, in
   behandeling, cyberrisico's, recente tickets, top-hardware, telefoonlijst, snelkoppelingen). Geen
   visuele browser-check in licht/donker thema gedaan in deze sessie (geen headed browser
   beschikbaar) — nog te doen voordat dit als volledig afgerond geldt.
2. ✅ Done — Ticket module (list + detail), the 3-laags/API reference pattern (see
   "API-architectuur" below) copied into later modules.
3. ✅ Kennisbank done (3-laags + echte Lovable-conversie, zie "API-architectuur" hieronder);
   EmailVerwerking/MailMind (`src/routes/mailmind.tsx`) nog niet opgepakt.
4. ✅ Done — Verbeterpunt, Reflectie (3-laags + echte Lovable-conversie, zie "API-architectuur").
5. ✅ Voorraad, Device, Printer, Uitgifte, CyberRisico gedaan (zie "API-architectuur" hieronder).
   HardwareUitgave was aanvankelijk overgeslagen (geen Lovable-mockup voor dat concept) maar is
   alsnog gedaan in stap 7 hieronder — zie de sectie "HardwareUitgave" daar voor de aanpak.
6. ✅ Done — Medewerker (3-laags), Agenda (sidebar toegevoegd aan de bestaande FullCalendar-pagina,
   geen 3-laags herbouw — zie hieronder), Account (herstyled, geen 3-laags nodig — singleton-pagina).
7. ✅ Done — Beheer, Tools (gedeelde tabbladnavigatie over bestaande pagina's, geen 3-laags herbouw
   — zie hieronder), Script, Schijfgebruik (3-laags), en alsnog HardwareUitgave (3-laags, zie
   hieronder).

Each step is a visual/structural pass only: keep existing routes, controllers, Dutch UI text, and
behavior unchanged unless a bug is found along the way. Click through the affected module locally
after each step (`php -S localhost:8000 -t public public/router.php`, check both
`data-bs-theme="light"` and `"dark"`) before moving to the next one — there's no automated test
suite to catch a visual regression.

**Uitvoeringsregel:** elk scherm wordt 1-op-1 uit het Lovable-project nagebouwd (layout, toolbar,
interactiepatronen — niet alleen kleuren/tokens hergebruiken van bestaande PHP-views). Waar een
Lovable-scherm iets toont dat geen echte backend heeft (in de mockup zelf, of in onze database), wordt
dat element weggelaten of vereenvoudigd tot wat wél echt werkt — nooit nagemaakt met verzonnen data of
een knop die niets doet. Elke keer dat dit gebeurt, komt er een regel bij in de lijst hieronder.

### Nog te herdesignen/uit te werken met Lovable
Plekken waar het PHP-scherm bewust afwijkt van de Lovable-mockup (`https://lovable.dev/projects/4675b36f-276e-4fc5-9606-d83a98f9d801`)
omdat de mockup iets toont zonder (volledige) echte functionaliteit erachter. Gebruik dit als startpunt
voor een vervolgprompt in Lovable om de UX daadwerkelijk uit te werken, waarna de PHP-kant het opnieuw
1-op-1 kan overnemen.

- **Topbar — globale zoekbalk (⌘K)**: staat overal zichtbaar (`app/Views/layouts/app.php`) maar is
  puur decoratief (disabled input) — geen backend-zoekindex over tickets/medewerkers/KB-artikelen.
  Ook in de Lovable-mockup zelf niet functioneel (ongecontroleerde input zonder resultaten).
- **Ticket-detail — bijlagen**: Lovable toont een paperclip-icoon met voorbeeldbestand
  (`screenshot-fout.png · 214 KB`) bij het oorspronkelijke bericht. Er is geen bijlage-kolom/-opslag
  in het datamodel (`tickets`-tabel) — weggelaten in `app/Modules/Ticket/Views/TicketView/show.php`.
- **Ticket-detail — "Antwoorden" / "@Vermelding"**: de opmerking-composer in Lovable heeft drie tabs
  (Antwoorden naar melder per e-mail, Interne notitie, Vermelding); alleen "Interne notitie" bestaat
  echt (het bestaande opmerkingen-logboek). Rechtstreeks een e-mail terugsturen naar de melder of
  iemand @vermelden bestaat niet — de composer toont daarom alleen de werkende opmerking-flow.
  ⛅ Hangt samen met de MailMind-pipeline (`app/Modules/EmailVerwerking`) — mogelijk een logische plek
  om dit later aan te haken in plaats van los te bouwen.
- **Ticket-detail — "Gerelateerd"-paneel**: Lovable toont een kaart met verzonnen gekoppelde tickets/
  verbeterpunten. Geen backend voor willekeurige cross-links tussen items — paneel is weggelaten i.p.v.
  nagemaakt met voorbeelddata.
- **Ticket-detail — "Kanaal"**: Details-kaart in Lovable toont hoe het ticket is binnengekomen
  (e-mail/telefoon/etc.); geen kolom hiervoor in `tickets` — weggelaten.
- **Ticket-detail — directe "Toewijzen"-actie**: Lovable suggereert instant behandelaar-toewijzing
  vanaf de detailpagina; bij ons kan behandelaar alleen via de volledige bewerk-pagina gewijzigd
  worden (`/tickets/{id}/edit`) — de knop linkt daarom door i.p.v. een quick-assign te faken.
- **Kennisbank — "Views deze week" / "Verouderd (>6 mnd)"-KPI's**: Lovable's KPI-rij toont paginaweergaven
  en een "verouderd"-badge per artikel. Er is geen view-telling en geen laatst-bijgewerkt-drempel in het
  datamodel (`kennisbank_artikelen`) — deze twee KPI's zijn vervangen door "Categorieën" en "AI-concepten
  in review" (echte, beschikbare tellingen; die laatste linkt door naar de reviewwachtrij in
  `app/Modules/EmailVerwerking`).
- **Kennisbank — inline voorbeeldpaneel**: Lovable's lijst-view opent een artikel direct in een
  voorbeeldpaneel naast de lijst (split-view, geen page-load). Onze lijst linkt nog door naar een aparte
  detailpagina (`app/Modules/Kennisbank/Views/KennisbankView/show.php`) — geen backend-wijziging nodig
  om dit alsnog te bouwen, maar bewust niet meegenomen in deze fase (grotere JS/UX-wijziging dan een
  visuele restyle).

## API-architectuur (nieuw, 2026-07-24 — Tickets is het referentiepatroon)

Naast de bestaande server-rendered routes wordt de app stap voor stap omgebouwd naar een 3-laags
architectuur met een JSON-API-laag, zodat frontend en backend ontkoppeld zijn. **Tickets is het
eerste en tot nu toe enige module dat dit patroon volledig heeft** — de overige 17 modules draaien
nog volledig op het oude server-rendered patroon (zie "Frontend design direction" hierboven) totdat
dit patroon is gevalideerd en er bewust voor gekozen wordt het per module over te zetten.

**Waarom geen React/TanStack Start in productie:** het gedownloade Lovable-project (broncode ter
referentie gecommit onder `docs/design/`) gebruikt `@tanstack/react-start` + Nitro met een privé
Lovable-buildplugin die standaard op Cloudflare target — een Node/edge-runtime toolchain die niet op
Hostnet (shared hosting, geen SSH, geen Node) kan draaien. De frontend is daarom in Bootstrap +
vanilla JS gebouwd, met dezelfde CSS/HTML-taal als de rest van de app (`public/assets/css/app.css`).
De React-broncode blijft wel het visuele/structurele referentiepunt.

**Drie lagen:**
1. **Presentation/API** — `App\Api\V1\*Controller` (bv. `TicketsApiController`, extends
   `App\Api\V1\ApiController`). Parsed de request, roept de service aan, zet het resultaat om in de
   envelope hieronder. Geen businesslogica.
2. **Service/Business** — `App\Modules\<Module>\<Naam>Service` (bv. `TicketService`). Alle validatie,
   scope-autorisatie, statusovergangs-logica. Geen HTTP-concepten. Gooit
   `App\Core\Exceptions\{ValidationException,NotFoundException,ForbiddenException}` die de API-laag
   naar de juiste HTTP-status vertaalt.
3. **Data Access/Repository** — de bestaande `*Model`-klassen (bv. `TicketModel`, extends
   `App\Core\Model`). Kenden al geen HTTP-/UI-concepten, dus dit is niet herschreven — de Service-laag
   roept ze aan i.p.v. de oude Controller.

**Routes**: `/api/v1/...` in `public/index.php`, zelfde `Router`/front controller als de
server-rendered routes. Let op: `App\Core\Router::dispatch()` stelt alle `/api/*`-paden standaard
vrij van CSRF-verificatie (bedoeld voor de bestaande machine-to-machine endpoints met een
API-sleutel, zie `App\Shared\ApiKey`) — de nieuwe sessie-geauthenticeerde `/api/v1/*`-routes
controleren CSRF daarom zelf, via `ApiController::requireCsrf()`.

**Auth**: dezelfde sessiecookie als de server-rendered routes (same-origin op Hostnet, geen apart
tokensysteem). `ApiController::requireAuth()` geeft `401` JSON i.p.v. een redirect.

**Envelope**: succes `{"status":"success","data":...,"meta":{...}}` (meta bevat o.a. `pagination`,
`statusCounts`, `filterOptions` bij lijst-endpoints); fout `{"status":"error","message":"...",
"errors":{"veld":["..."]}}`. Statuscodes: 200/201/204/401/403/404/422/419 (CSRF).

**Frontend**: `app/Modules/Ticket/Views/TicketView/{index,show}.php` zijn nu lege shells (layout +
een leeg `<div id="...">` + één `<script type="module">`). `public/assets/js/api/client.js` is de
gedeelde fetch-wrapper (CSRF-header wordt al automatisch toegevoegd door het bestaande
`public/assets/js/csrf.js`, dat monkey-patcht `window.fetch` — de client hoeft dat dus niet zelf te
doen). `public/assets/js/pages/tickets-index.js` / `tickets-show.js` bevatten per pagina de
render-/state-logica (loading/empty/error-states, URL-querystring als filterstate). Export/import
(Excel) op de tickets-lijst blijven bewust op de oude server-rendered routes
(`/tickets/export`, `/tickets/import`) — bestandsdownload/-upload hoort niet in deze JSON-API-scope.

**Live geverifieerd** (lokale DB, browser): lijst laden/filteren/pagineren, ticket openen, opmerking
toevoegen, status wijzigen (incl. tijdlijn-update), tijd registreren — allemaal via de nieuwe API,
zonder page reload. CSRF-check getest (419 zonder token, 201 met), 401/404/422 getest via curl.

**SOLID-review (2026-07-24):** `TicketService` had een herhaald find-of-404 + scope-autorisatiecheck
(dezelfde 6 regels) in `update`/`delete`/`addLog`/`addTijd`/`kennisbankKoppel`/`kennisbankOntkoppel` —
een SRP/DRY-schending: elke methode droeg zowel "ticket ophalen + autoriseren" als de eigenlijke actie.
Opgelost met een private `findOrFail(int $id, array $currentUser): array` die `NotFoundException`/
`ForbiddenException` op één plek gooit; de zes methoden roepen die nu aan i.p.v. de check te herhalen.
Overige SOLID-observaties uit dezelfde review (bewust nog niet doorgevoerd): `ApiController::requireAuth()`/
`requirePermission()` gebruiken `exit` na `error()` i.p.v. een exception zoals de rest van `handle()` —
zou voor een uniform foutpad ook via een exception moeten lopen, maar is functioneel niet fout; Services
zijn hard-coupled aan static Model-classes (bewust, zie hierboven — pas relevant zodra er unit-tests
voor Services komen).

**Vervolg**: zodra dit patroon is goedgekeurd, kan het per module herhaald worden (Service + API-
controller + JS-pagina + shell-view). De oude server-rendered routes per module blijven intact totdat
die module is overgezet — geen big-bang-migratie.

**Besluit (2026-07-24) — methodiek wordt "echte Lovable-conversie", niet handmatig natekenen:**
vanaf nu wordt de daadwerkelijke Lovable-broncode (`docs/design/*.tsx`, opgehaald via de Lovable MCP
`read_file`-tool, project `4675b36f-276e-4fc5-9606-d83a98f9d801`) 1-op-1 omgezet naar de platte
HTML/CSS/vanilla-JS-pagina's die de `/api/v1/*`-laag aanroepen, in plaats van het scherm met de hand
na te bouwen op basis van het visuele resultaat. Uitleveringsmodel blijft de dunne PHP-shell (zoals nu
al bij Tickets): PHP doet alleen sessie-gate + gedeelde layout-include, de rest van de pagina is
statische markup + JS. Reden om niet voor volledig losse `.html`-bestanden zonder PHP te kiezen: dat
zou laag-duplicatie van de sidebar/layout betekenen zonder build-stap, en de sessie-gate zou dan
client-side (na een 401) moeten in plaats van server-side vóór de eerste render.

**Waarom nu, en met het oog op een toekomstige Android-app:** de `/api/v1/*`-laag is bewust
framework-/frontend-onafhankelijk (JSON in/uit, geen HTML). Een native app kan dezelfde endpoints
hergebruiken. **Update (2026-07-24) — bearer-token-auth toegevoegd:** `ApiController::requireAuth()`
accepteert nu, náást de sessiecookie, ook een `Authorization: Bearer <token>`-header voor
niet-browserclients (CLI/desktop/Android). Nieuwe onderdelen:
- `personal_access_tokens`-tabel (`database/xml/personal_access_tokens.xml`) + `App\Shared\Auth\Models\PersonalAccessTokenModel`
  (hash+prefix, intrekken via soft delete) — zelfde opzet als `App\Shared\ApiKey`, maar per gebruiker
  i.p.v. een vlakke scope: het token draagt de identiteit van precies één account, dus de bestaande
  rol-/afdelingsscope in de Service-laag (bv. `TicketService::scopeAllowed()`, `RechtenModel::has()`)
  blijft ongewijzigd gelden — geen aparte autorisatielaag voor tokenclients nodig.
- `App\Shared\Auth\AuthService::attemptLogin()` — credential-check + lockout/audit-logging
  (`login_attempts`), geëxtraheerd uit `AuthController::login()` zodat de sessie-login (HTML) en de
  nieuwe `POST /api/v1/auth/login` (JSON) dezelfde brute-force-bescherming delen i.p.v. hem te dupliceren.
  `AuthService::userPayload()` bouwt de gebruikersvorm (`id/naam/rol/foto/afdeling_id`) die zowel
  `$_SESSION['user']` als de tokenauth gebruiken.
- `POST /api/v1/auth/login` (body: `email`, `wachtwoord`, optioneel `device_naam`) → `201` met
  `{token, user}`; `POST /api/v1/auth/logout` (met de bearer-header) trekt dat ene token in. Geen
  CSRF-check op deze routes (Router stelt `/api/*` daar al van vrij, en vóór het inloggen bestaat er
  nog geen sessie om een token uit te lezen); `ApiController::requireCsrf()` slaat de check ook over
  zodra `requireAuth()` een tokengebruiker vond — een `Authorization`-header kan een browser nooit
  ongevraagd cross-site meesturen, dus dat is precies het scenario dat CSRF-tokens dekken en hier niet
  van toepassing is.
- `personal_access_tokens.user_id` heeft bewust geen DB-niveau FK naar `users.id` — zelfde reden als
  `kb_article_drafts.reviewer_id`/`kennisbank_artikelen.auteur_id` (zie die tabellen): `users` dateert
  van vóór het XML-schemasysteem en het werkelijke id-kolomtype kan per omgeving afwijken.
- Lokaal end-to-end geverifieerd tegen een echte lokale database: login geeft token, `GET /api/v1/tickets`
  en `/api/v1/kennisbank` werken met alleen de bearer-header (geen sessiecookie), `requirePermission()`
  weigert een token zonder rechten net als een sessie zou doen (403), een POST met bearer-token en zónder
  CSRF-header komt voorbij `requireCsrf()`, en na `logout` geeft hetzelfde token weer 401.
- **Nog niet gebouwd:** geen tokenoverzicht/-intrekscherm in Beheer (nu alleen via directe DB-query of
  een toekomstig `GET /api/v1/auth/tokens`), geen token-expiry (tokens blijven geldig tot expliciet
  ingetrokken), en de overige 16 modules hebben nog steeds geen `/api/v1/*`-laag — dit lost alleen het
  auth-blokkerende punt op, niet de functionaliteitsdekking (zie "API-laag"-sectie in README.md).

**Belangrijk — `docs/design/*.tsx` staat niet betrouwbaar synchroon met Lovable, gebruik altijd de
Lovable MCP:** `docs/design/modules.kennisbank.tsx` bleek bij de conversie hieronder de verkeerde
content te bevatten (de `/mailmind`-pagina, niet `/modules/kennisbank`) — vermoedelijk een fout bij
het platslaan van de geëxporteerde `src/routes/*`-structuur naar een flat directory. Bevestigd via
`mcp__claude_ai_Lovable__list_files`/`read_file` tegen project `4675b36f-276e-4fc5-9606-d83a98f9d801`:
de echte bron staat op `src/routes/modules.<naam>.tsx` (en `src/routes/mailmind.tsx`,
`src/routes/tickets.*.tsx`, `src/components/*`). **Gebruik voor elke volgende moduleconversie de
Lovable MCP `list_files`/`read_file`-tools rechtstreeks tegen dat project, niet de gecommitte kopie
onder `docs/design/`** — die laatste is alleen nog bruikbaar voor de gedeelde UI-componenten/tokens
(`design-system.tsx`, `mock-data.ts`) waarvan niet gebleken is dat ze afwijken.

**Kennisbank (afgerond, stap 3 van de rollout hierboven):** volledig 3-laags overgezet naar het
Tickets-patroon.
- Backend: `KennisbankService` + `Api\V1\KennisbankApiController` (`GET/POST /api/v1/kennisbank`,
  `GET/PUT/DELETE /api/v1/kennisbank/{id}`) + `TicketKennisbankModel::gekoppeldeTicketsVoorArtikel()`
  (omgekeerde richting van de bestaande ticket→KB-koppeling, voor het "Gekoppelde tickets"-paneel).
- Frontend: `Views/KennisbankView/index.php` én `show.php` zijn nu identieke thin shells;
  `public/assets/js/pages/kennisbank-index.js` bouwt de split-view (artikellijst + detailpaneel in
  één scherm) uit `src/routes/modules.kennisbank.tsx`, zonder page-load bij het wisselen van artikel
  (`pushState`/`popstate`) — dit lost meteen het eerder genoteerde "inline voorbeeldpaneel"-punt op.
  `/kennisbank/{id}` (vanuit `tickets-show.js`) en `/kennisbank` renderen dezelfde shell; het
  artikel-id wordt uit het URL-pad gelezen, niet server-side doorgegeven.
- Bewuste afwijkingen t.o.v. de mockup (zie ook de code-comment bovenaan `kennisbank-index.js`):
  geen "alle/gepubliceerd/concept"-filtertabs en geen inline "Goedkeuren/Afwijzen" (AI-conceptartikelen
  leven in een aparte reviewwachtrij, `/email-verwerking`, niet als status op een gepubliceerd
  artikel); categoriefilter als dropdown i.p.v. Lovable's ontbrekende boomstructuur; de twee
  verzonnen KPI's blijven vervangen door "Categorieën"/"AI-concepten in review" (eerder al zo
  besloten, nu ook zo geïmplementeerd).
- Lokaal geverifieerd: `php -l` schoon op alle bestanden, server boot zonder fatale fouten op zowel
  onbeauthenticeerde HTML-routes (302 naar /login) als de nieuwe API-routes (401 JSON). Een volledige
  ingelogde klik-door-test kon niet lokaal afgerond worden (lokale DB/seed-gebruiker niet beschikbaar
  in deze omgeving) — nog te doen door QA in een omgeving met een werkende lokale database.
- **Nog niet meegenomen:** MailMind-pagina (`src/routes/mailmind.tsx`) staat los van deze conversie
  en hoort bij `App\Modules\EmailVerwerking` — apart op te pakken. `/kennisbank/create` en
  `/kennisbank/{id}/edit` staan nog op de oude server-rendered formulieren (niet in scope van de
  mockup-conversie, die toont geen formulier).

**Verbeterpunt + Reflectie (afgerond, stap 4 van de rollout hierboven):** zelfde 3-laags patroon,
bron `src/routes/modules.verbeterpunt.tsx` / `modules.reflectie.tsx` via de Lovable MCP.
- Backend: `VerbeterpuntService` + `Api\V1\VerbeterpuntenApiController`
  (`GET/POST /api/v1/verbeterpunten`, `GET/PUT/DELETE /api/v1/verbeterpunten/{id}`,
  `POST /api/v1/verbeterpunten/{id}/log`); `ReflectieService` + `Api\V1\ReflectiesApiController`
  (`GET/POST /api/v1/reflecties`, `GET/PUT/DELETE /api/v1/reflecties/{id}`, geen log-endpoint, zie
  hieronder). Beide modellen kregen een `alleenGewijzigdeVelden()` net als Ticket/Kennisbank.
- Frontend: `Views/VerbeterpuntView/{index,show}.php` en `Views/ReflectieView/{index,show}.php` zijn
  nu identieke thin shells; `verbeterpunten-index.js` en `reflecties-index.js` bouwen elk een
  split-view (lijst + item in één scherm, `pushState`/`popstate`, geen page-load per item) —
  hetzelfde patroon als Kennisbank.
- Bewuste afwijkingen t.o.v. de mockup:
  - Verbeterpunt: onze status-flow heeft 5 stappen (`nieuw`/`in_overweging`/`goedgekeurd`/
    `afgewezen`/`uitgevoerd`, zie `VerbeterpuntLogController`/`edit.php` vóór deze conversie), niet
    Lovable's 3 (`voorgesteld`/`in-uitvoering`/`afgerond`) — de 3-staps voortgangsbalk uit de mockup
    is vervangen door een statusbadge + een echt select+opmerking-formulier tegen
    `POST .../log` (zelfde flow als Tickets). Geen "Eigenaar"/"Impact"-velden of
    "Gem. doorlooptijd"-KPI: die bestaan niet in het datamodel. Het logboek toont de echte
    `verbeterpunt_logs` i.p.v. Lovable's drie hardcoded voorbeeldregels. Tijdregistratie
    (`verbeterpunt_tijdregistraties`) bestaat wel in de backend maar niet in de mockup — niet
    meegenomen in dit scherm.
  - Reflectie: het bestaande opmerkingen-logboek (`reflectie_logs`, `ReflectieLogController`) komt
    niet voor in de mockup (alleen titel+inhoud inline bewerken) — dus ook niet meegenomen in
    `reflecties-index.js`; de oude `/reflecties/{id}/log`-route blijft bestaan maar wordt door dit
    scherm niet meer aangeroepen.
- Lokaal geverifieerd door QA (2026-07-24, XAMPP `php.exe` met openssl, lokale DB + seed-gebruiker):
  `php -l` schoon op alle betrokken bestanden; `GET /api/v1/verbeterpunten` en `/api/v1/reflecties`
  geven zonder sessie een nette `401`-envelope (geen fatal error); met een ingelogde sessie geven
  beide `200` met de juiste envelope-vorm (`status/data/meta`, incl. `meta.statusCounts` bij
  Verbeterpunt); detail-endpoints (`GET .../{id}`) en een niet-bestaand id (`404` met foutmelding)
  getest; `POST .../log` (Verbeterpunt) doorlopen via de UI-flow-code. Alle status-badgeklassen
  (`badge-nieuw`, `badge-in_overweging`, `badge-goedgekeurd`, `badge-afgewezen`, `badge-uitgevoerd`)
  bestaan in `app.css` en hergebruiken de gedeelde statuskleuren, geen losse per-module kleuren.
  `/verbeterpunten/create`, `/reflecties/create` en de bijbehorende lijst-/detailpagina's laden
  allemaal `200`.
- **Extra bevinding QA:** Reflectie's mockup (`modules.reflectie.tsx`) heeft een decoratieve
  hero-kaart bovenaan ("Wat viel op deze week?" + toelichtingstekst + de "Nieuwe reflectie"-knop
  erin) — `reflecties-index.js` laat deze kaart volledig weg (de knop staat wel, maar los in de
  page-header). Dit is geen "verzonnen data/nep-knop"-overtreding van de Uitvoeringsregel (de kaart
  bevat geen data, puur uitleg), dus geen [BESLISSING NODIG]-taak, maar wel een 1-op-1-layoutgat dat
  nog niet was genoteerd — op te pakken bij een volgende polish-pas op deze module.
- **Nog niet meegenomen:** `/verbeterpunten/create`/`/{id}/edit` en `/reflecties/create`/`/{id}/edit`
  staan nog op de oude server-rendered formulieren.

**Voorraad, Apparaten, Printers, CyberRisico, Uitgifte (afgerond, stap 5 van de rollout hierboven):**
zelfde 3-laags patroon; bron `src/routes/modules.voorraad.tsx`, `modules.device.tsx`,
`modules.printer.tsx`, `modules.cyberrisico.tsx`, `modules.uitgifte.tsx` +
`modules.hardware-uitgaven.tsx` via de Lovable MCP.

**Naamcollisie ontdekt:** Lovable's route `/modules/hardware-uitgaven` is inhoudelijk gewoon een
hardware-gefilterde subview van `/modules/uitgifte` (zelfde `uitgiften`-mockdata, "Hardware-specifiek
overzicht"-link ertussen) — het modelleert dus onze **Uitgifte**-module (item met barcode uitgeven
aan een medewerker, retour nemen). Onze module die letterlijk `HardwareUitgave` heet is een heel
ander concept (inkoop-aanvraagworkflow: `aangevraagd → goedgekeurd/afgekeurd → besteld → geleverd`,
bedrag/leverancier, geen medewerker/serienummer-koppeling) — daar bestaat **geen** Lovable-mockup
voor. Er is dus maar één conversie gedaan (Uitgifte, op `/uitgiften`); `HardwareUitgave` blijft
volledig ongewijzigd op de oude server-rendered routes en staat hieronder toegevoegd aan
"Nog te herdesignen/uit te werken met Lovable" — eerst een eigen Lovable-prompt nodig voor het
inkoop-aanvraagscherm voordat dit omgezet kan worden.

Per module, grote afwijkingen t.o.v. de mockup (in alle vijf gevallen: widgets zonder echte data
weggelaten i.p.v. nagemaakt, real KPI's/acties in de plaats):
- **Voorraad**: Lovable gaat uit van kwantiteitsbeheer (aantal/minimum, bijboeken/afboeken,
  voorraadbalk) — `voorraad_items` is juist stuksgewijs geserialiseerd (één rij per fysiek item,
  eigen barcode/serienummer, status `op_voorraad`/`uitgegeven`/`afgeschreven`). Bijboeken/afboeken
  bestaat niet; de "mutatiehistorie" is vervangen door de echte uitgiftehistorie van het item
  (nieuwe `UitgifteModel::forVoorraadItem()`). **Alleen lezen** (list/find) in de API — aanmaken/
  bewerken heeft eigen serienummer-uniekheid/barcode-opbouw/DxDiag-upload-logica en blijft op de
  oude formulieren (`/voorraad/create`, `/voorraad/{id}/edit`).
- **Apparaten (Device)**: Lovable gaat uit van live monitoring (online/offline, status, OS,
  serienummer, locatie, herstart/wipe) — `devices` is puur een CSV-import-resultaat (naam,
  gekoppelde medewerker, laatste import, geïmporteerde software). Al die fictieve velden/acties zijn
  weggelaten; de echte geïmporteerde software (die Lovable niet toont) staat wel in het
  detailpaneel. **Geen `create` in de API** — aanmaken loopt alleen via CSV-upload
  (`DeviceController::store()`), "Registreer device" linkt door naar die pagina.
- **Printers**: Lovable gaat uit van live printer-telemetrie (online/offline, tonerpercentages,
  printjobs, herstart/testpagina) — niets daarvan bestaat (`printers` is een simpele catalogus:
  naam/computernaam/type/driver/ip/opmerking). Vervangen door echte velden + de bestaande (in de
  mockup niet voorkomende) rundll32-installcommando-generator met kopieerknop.
- **CyberRisico**: Lovable gaat uit van een kans(1-5)×impact(1-5)-risicomatrix met afgeleid niveau —
  die twee kolommen bestaan niet; wij hebben een direct `prioriteit`-veld (laag/middel/hoog/kritiek)
  en een los `status`-veld. De risicomatrix-widget is vervangen door een statusbadge + het echte
  logboek (`cyberrisico_logs`, zelfde patroon als Tickets). Prioriteits-KPI's/-badges hergebruiken de
  bestaande `--color-risk-*`/`badge-risico-*`-tokens ("middel" hergebruikt de "gemiddeld"-klasse).
- **Uitgifte**: geen "soort"-tabs (hardware/telefoon/toegangspas/overig) — dat onderscheid bestaat
  niet in het datamodel. "Nieuwe uitgifte" is een echt werkend inline formulier (barcode + naam,
  `<datalist>`-autocomplete tegen de bestaande `/uitgiften/items`/`/uitgiften/namen`-lookups,
  POST naar de nieuwe `/api/v1/uitgiften`) i.p.v. een dode knop. Retour nemen blijft de bestaande
  dual-write-actie (zet zowel de uitgifte als het gekoppelde voorraad-item bij).

Lokaal geverifieerd voor alle vijf: `php -l` schoon, server boot zonder fatale fouten op alle nieuwe
HTML- en API-routes (302 resp. 401 zonder sessie). Volledige ingelogde klik-door-test nog te doen
door QA (zelfde beperking als Kennisbank/Verbeterpunt/Reflectie hierboven — geen lokale DB in deze
omgeving).

**HardwareUitgave, Medewerker, Agenda, Account, Beheer, Tools, Script, Schijfgebruik (afgerond,
stappen 6-7 van de rollout hierboven):** sluit de rollout af — alle 17 modules zijn nu langs het
Lovable-project gehaald, hetzij als volledige 3-laags-conversie, hetzij als bewuste restyle zonder
3-laags-herbouw waar het scherm of de onderliggende data daar niet 1-op-1 op aansloot.

Volledig 3-laags (Service + `Api\V1\*Controller` + thin-shell view + split-view JS, bron opgehaald
via de Lovable MCP tegen `src/routes/modules.<naam>.tsx`):
- **HardwareUitgave**: `HardwareUitgaveService` + `Api\V1\HardwareUitgavenApiController`
  (`/api/v1/hardware-uitgaven*`) + `hardware-uitgave-index.js`. Lost de eerder genoteerde
  naamcollisie op (zie de "Voorraad, Apparaten, Printers, CyberRisico, Uitgifte"-sectie hierboven):
  geen Lovable-mockup voor dit concept, dus omgezet naar het echte aankoopaanvraag-trackermodel
  (omschrijving/leverancier/bedrag/aankoopdatum, status `aangevraagd → goedgekeurd/afgekeurd →
  besteld → geleverd`) i.p.v. de mockup's "uitgifte aan medewerker met retour"-concept dat al apart
  bestaat als `Uitgifte`.
- **Medewerker**: `MedewerkerService` + `Api\V1\MedewerkersApiController` + kaarten-grid/detail JS.
- **Script**: `ScriptService` + `Api\V1\ScriptsApiController` + `script-index.js` (terminal-style
  detail). Geen uitvoeringsgeschiedenis/"Uitvoeren"-knop — Script is een kopieer-en-plak-bibliotheek,
  geen executie-omgeving.
- **Schijfgebruik**: `SchijfgebruikService` + `Api\V1\SchijfgebruikApiController` +
  `schijfgebruik-index.js` (ring-gauge lijst). Alleen de lijst is overgezet — de detailpagina met
  medewerker-koppeling blijft server-rendered.

Bewust géén 3-laags-herbouw (bestaande functionaliteit ging boven mockup-gelijkheid):
- **Agenda**: draait al op een volwaardige FullCalendar-integratie (drag/resize/click-to-create,
  maand/week/dag) die functioneel verder gaat dan Lovable's statische week-grid-mockup — vervangen
  zou functionaliteit kosten. Alleen de ontbrekende sidebar is toegevoegd ("Team vandaag" met een
  echte in-behandeling-telling per medewerker via de nieuwe
  `MedewerkerModel::alleMetInBehandelingTellingen()`, plus een legenda met de bestaande
  event-kleuren).
- **Account**: singleton-pagina (geen lijst, dus geen Service/API-laag nodig) — alleen de bestaande
  profiel/bewerken-views herstyled naar Lovable's kaartsecties. 2FA/actieve sessies/notificatie-
  voorkeuren uit de mockup bestaan niet in dit systeem (sessie-cookie-auth zonder MFA) en zijn
  weggelaten i.p.v. nagemaakt.
- **Beheer en Tools**: de mockups bundelen meerdere features als client-side tabs op één scherm
  (Gebruikers&rechten/API-sleutels/E-mailqueue/Logs, resp. Telefoonlijst/Handtekening/Herstart-
  herinneringen). Dit waren al losse, volledig werkende server-rendered pagina's — samenvoegen tot
  één client-side app zou onnodig regressierisico geven. Opgelost met gedeelde tab-navigatie-partials
  (`app/Views/partials/beheer-tabs.php`, `tools-tabs.php`) die dezelfde tabs tonen als gewone
  paginalinks tussen de bestaande pagina's, plus een extra tab voor het echte onderdeel zonder
  Lovable-tegenhanger (Systeembeheer resp. Installatie).

Overige bewuste datamodel-afwijkingen (mockdata paste niet op het echte schema): Medewerker-status
kent geen "verlof", alleen actief/inactief.

Lokaal geverifieerd: `php -l` schoon op alle betrokken bestanden, server boot zonder fatale fouten op
de nieuwe HTML- en API-routes. Volledige ingelogde klik-door-test nog te doen door QA (zelfde
beperking als de eerdere modules — geen lokale DB in deze omgeving).

## Roadmap / openstaande verbeterpunten

**Geleverd** (fases 1–4, gecontroleerd tegen de code): CRM-hiërarchie/stamboom voor medewerkers (`manager_id`/`is_keyuser`, `GET /medewerkers/hierarchie`); Urenstaat-koppeling aan keyuser/klant (`urenstaat_registraties.keyuser_id`); Agenda-teamoverzicht "in behandeling" (`GET /agenda/team-events`); Tools herstart-mail export en verzending (`RestartReminderController`, `GET/POST /tools/herstart-herinneringen*`, met `Mailer::verstuur()` cc/bcc-support).

**Geleverd — E-mail & kennisbank verwerking / MailMind** (alle 5 fases, gecontroleerd tegen de code en smoke-getest tegen een lokale database): nieuwe module `app/Modules/EmailVerwerking` met 7 tabellen (`email_import_batches`, `imported_emails`, `email_attachments`, `email_ai_analysis`, `kb_article_drafts`, `kb_article_sources`, `processing_logs`), webhook `POST /api/email-import/inbound` (scope `email_import`), cron `POST /api/email-analysis/verwerken` (scope `email_analysis`), UI onder `/email-verwerking` (rechtenmatrix-module `email_verwerking`, ook toegevoegd aan de Service-dropdown in de navigatie). De Outlook-intake (`outlook_intake.py`) post eindgebruikersmail voortaan ook naar de nieuwe pipeline naast de bestaande ticketaanmaak (best-effort, blokkeert tickets niet bij falen). **Update:** de classificatie loopt niet meer via een directe AI-provider-call, maar via een externe n8n-webhook (`Services\AiAnalysisService`, env `N8N_WEBHOOK_URL`/`N8N_API_KEY`) — n8n verzorgt ingestie, extractie, kennis-koppeling en internet-lookup, en moet exact het JSON-schema teruggeven dat `analyseer()` afdwingt. **Nog niet gedaan:** `N8N_WEBHOOK_URL` is nergens ingevuld — zonder webhook-URL blijft elke e-mail hangen op status `failed` met een duidelijke reden in `processing_logs` (fail-safe, geen crash), en moet de n8n-workflow zelf nog gebouwd en getest worden. `kb_article_drafts.kennisbank_artikel_id`/`reviewer_id` hebben bewust geen DB-niveau FOREIGN KEY (zie het commentaar in `database/xml/kb_article_drafts.xml`) — zelfde aanpak als `kennisbank_artikelen.auteur_id`/`tickets.behandelaar_id`, omdat sommige lokale databases hier al gemigreerd zijn naar een ander kolomtype dan `SchemaParser::LEGACY_PLAIN_INT_TABLES` aanneemt.

**Open aandachtspunt:** de categorie-zoekfunctie (Ticket/Kennisbank/Verbeterpunt) gebruikt een debounce van ~200ms i.p.v. de gewenste ~2s — verhogen als dit te veel requests tijdens typen oplevert.

**Losse verkenning — geocoding/routing API (geen fase toegewezen):** nog te onderzoeken voor een eventuele reistijd-indicatie bij Urenstaat/locaties; coördinaten worden nu handmatig ingevuld naast het adresveld (zie `LocatieModel`). Geen API-integratie bouwen totdat hier bewust voor gekozen wordt.
- OpenCage Geocoding API voor adres → coördinaten (`api.opencagedata.com/geocode/v1/json`); vereist eigen API-key, rate limits nog niet uitgezocht.
- ANWB routing-API voor reistijd/afstand (incl. tolwegen) via `https://api.anwb.nl/routing/route/v1/route/car` met header `x-anwb-caller-id: routing/traffic-info-web` — werkend getest via PowerShell `Invoke-RestMethod`, maar de header is ongedocumenteerd/publiek en stabiliteit/gebruiksvoorwaarden voor productiegebruik zijn niet bevestigd.

