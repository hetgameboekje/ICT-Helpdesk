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
validatie/autorisatie, geen HTTP-concepten) en Data Access (de bestaande `*Model`-classes). Tickets en
Kennisbank zijn hierop overgezet; overige modules volgen stap voor stap. Zie CLAUDE.md voor het volledige
patroon, de envelope-vorm en het "SOLID-review"-punt over de gedeelde `findOrFail()`-helper in
`TicketService` (voorkomt dat elke methode zijn eigen find-of-404 + scope-check herhaalt).

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
- `APP_DEV`
- `APP_GIT_PULL_ENABLED`
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

Demo-login:
- E-mail: `admin@intranet.local`
- Wachtwoord: `wachtwoord123`

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

### `APP_DEV`

- `true`: bij `/login` wordt dev-sync uitgevoerd (git pull + DB parse/toepassen, afhankelijk van instellingen)
- `false`: deze acties alleen handmatig via Beheer

### `APP_GIT_PULL_ENABLED`

- `true`: git pull-acties toegestaan (omgevingen met shell-toegang)
- `false`: git pull uitgeschakeld, database parsen blijft beschikbaar

## Deployen (Hostnet / shared hosting)

Voor shared hosting zonder SSH:
- zet `APP_DEV=false` en `APP_GIT_PULL_ENABLED=false`;
- upload via SFTP/FTP;
- configureer DB-gegevens via `.env`;
- voer schema uit via phpMyAdmin;
- zorg dat `public/uploads/` beschrijfbaar is;
- activeer HTTPS.

## Beveiliging en aandachtspunten

- Gebruik HTTPS in alle niet-lokale omgevingen.
- Gebruik sterke wachtwoorden voor productiegebruikers.
- Laat `APP_DEV` in productie uit staan.
- Schakel git pull alleen in waar shell-toegang en juiste repo-rechten aanwezig zijn.

## Licentie

Zie [LICENSE](LICENSE).
