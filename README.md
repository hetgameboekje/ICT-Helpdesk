# Leen van Punt Intranet Ticketsysteem

Een modulair opgezet intranet in plain PHP (zonder framework) met MySQL/MariaDB.
De applicatie ondersteunt ticketbeheer en meerdere ondersteunende modules voor IT en CRM.

## Voor wie is dit project?

Dit project is geschikt voor organisaties die:
- een intern ticketsysteem willen gebruiken en uitbreiden;
- extra domeinen (zoals kennisbank, voorraad en agenda) als losse modules willen beheren;
- volledige controle willen houden over een eenvoudige, frameworkloze PHP-codebase.

## Belangrijkste functies

- Ticketbeheer met standaard CRUD-flow
- Logregistratie per item (tickets, verbeterpunten, reflecties, kennisbank, cyberrisico)
- Kennisbank-koppeling aan tickets
- E-mailintake voor tickets via API-endpoint
- Exporteer- en importeerfunctionaliteit voor tickets
- Beheeromgeving voor:
  - gebruikers/rechten
  - API-sleutels voor externe scripts (scoped per endpoint)
  - database parsen/toepassen
  - e-mailqueue-overzicht
  - logoverzicht
- Overige modules:
  - Verbeterpunten
  - Reflecties
  - Kennisbank
  - Hardware-uitgaven
  - Medewerkers
  - Voorraad (incl. barcode)
  - Printers
  - Uitgiften
  - Agenda
  - Accountbeheer
  - Tools (telefoonlijst en e-mailhandtekeningen)

## Architectuur (hoog niveau)

De applicatie gebruikt een modulegerichte MVC-opzet:

- **`public/index.php`** is de front controller en registreert alle routes.
- **`app/Core`** bevat gedeelde infrastructuur, zoals Router, Controller, Model en database-laag.
- **`app/Shared`** bevat domeinoverstijgende onderdelen (zoals auth, dashboard, legal en automation).
- **`app/Modules`** bevat businessmodules; elke module heeft eigen controllers, models en views.
- **`app/Views/layouts`** bevat globale layouts.

### Routering

De custom router (`app/Core/Router.php`) ondersteunt:
- `GET` en `POST` routes
- `{id}` routeparameters (numeriek)
- dispatch naar controller-acties op basis van URI en requestmethode

### Datalaag

- Database draait op MySQL/MariaDB.
- SQL-schema wordt beheerd via XML-definities in `database/xml`.
- `database/parse.php` zet XML om naar `database/.parsed/schema.sql`.
- `database/seed.php` maakt demo-gebruikers aan.

### API-laag (`/api/v1/*`)

Naast de server-rendered routes bouwt de app een JSON-API-laag op, 3 lagen dik: Presentation
(`App\Api\V1\*Controller`, alleen HTTP in/uit), Service/Business (`App\Modules\<Module>\<Naam>Service`,
validatie/autorisatie, geen HTTP-concepten) en Data Access (de bestaande `*Model`-classes). **Tickets**
(referentiepatroon, live geverifieerd: lijst/filter/paginate, ticket openen, opmerking toevoegen, status
wijzigen, tijd registreren — allemaal zonder page reload, CSRF getest) en **Kennisbank** (thin-shell
views, split-view lijst+detail met pushState/popstate) zijn hierop overgezet; de overige 16 modules
draaien nog op het oude server-rendered patroon en volgen stap voor stap. Zie CLAUDE.md voor het volledige
patroon, de envelope-vorm en het "SOLID-review"-punt over de gedeelde `findOrFail()`-helper in
`TicketService` (voorkomt dat elke methode zijn eigen find-of-404 + scope-check herhaalt).

**Auth voor niet-browserclients (CLI/desktop/Android):** naast de sessiecookie accepteert de
`/api/v1/*`-laag ook een `Authorization: Bearer <token>`-header. Token ophalen via
`POST /api/v1/auth/login` (body `email`/`wachtwoord`/optioneel `device_naam`), intrekken via
`POST /api/v1/auth/logout`. Zo'n token draagt de identiteit van precies één gebruiker, dus alle
bestaande rechten-/scope-logica (rol, afdeling) blijft gewoon gelden — er is geen aparte, beperktere
autorisatie voor tokenclients. Zie CLAUDE.md voor de volledige uitleg (waarom geen CSRF-check nodig is
voor tokenauth, en wat er nog ontbreekt: geen tokenbeheerscherm, geen expiry, en de meeste modules
hebben nog steeds geen `/api/v1/*`-laag om tegen in te loggen).

## Projectstructuur

```text
app/
  Core/                     # Gedeelde infrastructuur
  Shared/                   # Auth, dashboard, legal, automation, etc.
  Modules/                  # Domeinmodules (Ticket, Kennisbank, Voorraad, ...)
  Views/layouts/            # Globale layouts
config/
  config.php                # Config + env-fallbacks
database/
  xml/                      # XML bron voor tabellen
  parse.php                 # Genereert .parsed/schema.sql
  seed.php                  # Seedt demo-gebruikers
public/
  index.php                 # Front controller
  router.php                # Router script voor php -S
```

## Snelle start (lokaal)

### Vereisten

- PHP met extensie `pdo_mysql`
- MySQL/MariaDB
- Bij voorkeur Laragon (maar niet verplicht)

### 1) Database opzetten

1. Maak database `leenvanpunt` aan.
2. Voer `database/schema.sql` uit op database `leenvanpunt`.
3. (Optioneel) Wijzig je tabellen via `database/xml/*`, draai daarna:

```bash
php database/parse.php
```

Voer vervolgens `database/.parsed/schema.sql` uit.

> De Beheer-pagina ("Database toepassen") voegt automatisch ontbrekende tabellen/kolommen toe,
> maar wijzigt geen bestaand kolomtype. Na het toevoegen van encryptie is `tickets.opdrachtgever_naam`
> gewijzigd van `VARCHAR(150)` naar `TEXT` — pas dit één keer handmatig toe:
> `ALTER TABLE tickets MODIFY opdrachtgever_naam TEXT NOT NULL;`. Draai daarna, met
> `APP_ENCRYPTION_KEY` gezet, eenmalig `php database/encrypt_existing_tickets.php --apply` om
> bestaande (plaintext) tickets te versleutelen.

### 2) Configuratie

`config/config.php` gebruikt standaard lokale Laragon-waardes, of leest uit env:

- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `APP_ENV` — `local` of `hostnet`, kiest welk `LOCAL_*`/`HOSTNET_*`-blok in `.env` wordt gelezen en
  bepaalt automatisch `dev`/`gitPullEnabled`/`display_errors` (zie "Omgevingsgedrag" hieronder) —
  er zijn geen losse `APP_DEV`/`APP_GIT_PULL_ENABLED`/`APP_DEBUG`-sleutels meer, die zijn hierin
  samengevoegd omdat ze in de praktijk altijd gelijk liepen met `APP_ENV`.
- `APP_ENCRYPTION_KEY` — sleutel voor het versleutelen van gevoelige ticketvelden
  (`omschrijving`, `opdrachtgever_naam` — zie `App\Shared\Crypto\FieldEncryptor`). Genereer
  met `openssl rand -base64 32`; gebruik dezelfde sleutel op elke omgeving die dezelfde
  database gebruikt — wijzigen maakt bestaande versleutelde tickets onleesbaar.

Kopieer `.env.example` naar `.env` om dit per omgeving te beheren.

### Dev-tools script (Windows)

`scripts/dev-tools/dev-tools.ps1` bundelt de dagelijkse lokale dev-taken in één interactief menu
(pijltjes = navigeren, spatie = selecteren, Enter = uitvoeren):

```powershell
powershell -ExecutionPolicy Bypass -File scripts\dev-tools\dev-tools.ps1
```

- **Database parsen** — genereert `database/.parsed/schema.sql` uit `database/xml/*`.
- **Git pull & fetch** — `git fetch --all --prune` + `git pull`.
- **Rebuild .env** — voegt sleutels toe die in `.env.example` staan maar nog niet in je
  `.env`, zonder bestaande waarden te overschrijven.
- **Database legen + schema herbouwen** — verwijdert alle lokale tabellen/data en herbouwt
  het schema (`database/clear.php --force`).
- **Live database ophalen** — haalt een volledige dump van de live database op via
  `GET /api/database/export` en importeert die lokaal. Vereist `LIVE_DB_EXPORT_URL` en
  `LIVE_DB_EXPORT_KEY` in `.env`; de sleutel maak je aan via Beheer > API-sleutels op de
  live server met scope `database_export`. Bevat ongefilterde productiedata — deel deze
  sleutel niet en trek 'm in zodra je 'm niet meer gebruikt.

### 3) Demo-data laden

```bash
php database/seed.php
```

Demo-login (standaardwaarden uit `database/seed.php`, override via `SEED_USER_*` in `.env`):
- E-mail: `timo@bergthaler.nl`
- Wachtwoord: `demo123`

### 4) Applicatie starten

**Optie A: Laragon/Apache**
- Start Apache + MySQL
- Open je lokale project-URL (bijv. `http://ticketsystemleenvanpunt.test`)

**Optie B: PHP built-in server**

```bash
php -S localhost:8000 -t public public/router.php
```

Ga naar: http://localhost:8000

## Omgevingsgedrag

### `APP_ENV`

Eén sleutel bepaalt het gedrag per omgeving — er is geen aparte `APP_DEV`/`APP_GIT_PULL_ENABLED` meer:

- `local` (of elke andere waarde dan `hostnet`) telt als dev-omgeving: bij `/login` wordt dev-sync
  uitgevoerd (git pull + DB parse/toepassen), git pull-acties zijn toegestaan, en PHP-fouten worden
  getoond (`display_errors`).
- `hostnet`: dev-sync, git pull en `display_errors` staan alle drie uit; database parsen/toepassen
  blijft wel handmatig beschikbaar via Beheer.

## Deployen (Hostnet / shared hosting)

Voor shared hosting zonder SSH:
- zet `APP_ENV=hostnet`;
- upload via SFTP/FTP;
- configureer DB-gegevens via `.env`;
- voer schema uit via phpMyAdmin;
- zorg dat `public/uploads/` beschrijfbaar is;
- activeer HTTPS.

## Beveiliging en aandachtspunten

- Gebruik HTTPS in alle niet-lokale omgevingen.
- Gebruik sterke wachtwoorden voor productiegebruikers.
- Zet `APP_ENV=hostnet` in productie (schakelt dev-sync, git pull en `display_errors` in één keer uit).
- Schakel git pull alleen in waar shell-toegang en juiste repo-rechten aanwezig zijn.

## Huidige status / openstaande punten

- **API-laag + redesign per module**: Tickets en Kennisbank draaien op de nieuwe `/api/v1`-laag met
  bijbehorend Lovable-gebaseerd redesign (emerald + altijd-donkere sidebar, Inter/JetBrains Mono); de
  overige 16 modules staan nog op het oude server-rendered patroon en worden één voor één omgezet (zie
  "API-laag" hierboven en het rolloutplan in `CLAUDE.md`).
- **MailMind (`app/Modules/EmailVerwerking`)**: alle 5 fases zijn opgeleverd en lokaal smoke-getest.
  Grootste openstaande blocker: `N8N_WEBHOOK_URL` is nergens ingevuld, dus elke inkomende e-mail blijft
  hangen op status `failed` in `processing_logs` (fail-safe, geen crash) totdat de n8n-workflow zelf is
  gebouwd en geconfigureerd.
- **Categorie-zoekfunctie** (Ticket/Kennisbank/Verbeterpunt) gebruikt momenteel een debounce van ~200ms
  in plaats van de gewenste ~2s — verhogen als dit tijdens typen te veel requests oplevert.
- **Geocoding/routing-API** (OpenCage voor adres → coördinaten, ANWB routing voor reistijd/afstand) is
  alleen verkend voor een eventuele reistijd-indicatie bij Urenstaat/locaties — nog geen keuze gemaakt,
  geen integratie bouwen totdat hier bewust voor gekozen wordt.

## Licentie

Zie [LICENSE](LICENSE).
