<?php
// Voorbeelddata + testcases voor de Entra ID -> NinjaOne-conversie (App\Modules\Tools\EntraNinjaOne\*).
// Dit project heeft geen testrunner (zie CLAUDE.md) — dit is een standalone script in dezelfde stijl
// als database/parse.php: draai het met `php scripts/tests/entra_naar_ninjaone_voorbeeld.php` en lees
// de PASS/FAIL-regels. Non-zero exitcode zodra één test faalt, zodat het ook bruikbaar is als
// smoke-test in een eventuele toekomstige CI-stap.
//
// Update (2026-07-30): "Last name" is nu verplicht — een rij zonder betrouwbaar af te leiden
// achternaam (gedeelde/functionele mailbox zoals inkoop@vhe.nl of projectenbureau@vhe.nl) wordt
// voortaan uitgesloten i.p.v. met een lege achternaam meegenomen. Dit is een bewuste correctie t.o.v.
// de eerdere versie van deze test (die "Projectenbureau" nog als geldig voorbeeld gebruikte).

require __DIR__ . '/../../app/bootstrap.php';

use App\Modules\Tools\EntraNinjaOne\EntraCsvParser;
use App\Modules\Tools\EntraNinjaOne\EntraToNinjaOneConverter;
use App\Modules\Tools\EntraNinjaOne\NinjaOneExporter;
use App\Modules\Tools\EntraNinjaOne\NinjaOneUserMapper;

$gefaald = false;

function test(string $naam, bool $conditie): void
{
    global $gefaald;
    echo ($conditie ? '[PASS] ' : '[FAIL] ') . $naam . PHP_EOL;
    if (!$conditie) {
        $gefaald = true;
    }
}

// --- Voorbeeld-CSV: dekt alle door de gebruiker genoemde gevallen ---
// Rij 1: het oorspronkelijke voorbeeld uit de aanvraag — nu bewust EXCLUDED (gedeelde mailbox,
//        geen "voor.achter"-patroon in de e-mail én displayName is maar één woord).
// a1:    normale medewerker, volledige displayName -> INCLUDED via displayName-split.
// a2:    guest -> EXCLUDED (tenzij includeGuests: true).
// a3:    geen userPrincipalName -> EXCLUDED.
// a4:    ongeldig e-mailadres -> EXCLUDED.
// a5:    GEEN displayName, maar e-mail volgt wel het voor.achter-patroon -> INCLUDED via e-mail.
// a6:    "Inkoop" (functionele mailbox, letterlijk het voorbeeld uit de aanvraag) -> EXCLUDED.
// a7:    e-mail is een afkorting (mkee, geen punt) maar displayName is een echte naam -> INCLUDED
//        via displayName (dit is het "probeer je uit de csv resterende informatie te halen"-geval).
// a8:    e-mail is een afkorting ÉN geen displayName -> EXCLUDED (niets om uit af te leiden).
// a9:    e-mail met tussenvoegsel (jan.van.dijk) -> INCLUDED, achternaam wordt "Van Dijk".
$csv = <<<CSV
id,displayName,userPrincipalName,userType,onPremisesSyncEnabled,identities,companyName,creationType
3c3eb183-b605-4d7f-9e49-52073c81abaf,# Projectenbureau,projectenbureau@vhe.nl,Member,True,"[{""signInType"":""userPrincipalName"",""issuer"":""vheindustrialautomationbv.onmicrosoft.com"",""issuerAssignedId"":""projectenbureau@vhe.nl""}]",,

a1,Jan de Vries,jan.devries@vhe.nl,Member,True,"[]",,
a2,Externe Adviseur,adviseur@extern.nl,Guest,False,"[]",,
a3,Naam Zonder Email,,Member,True,"[]",,
a4,Kapotte Rij,niet-een-emailadres,Member,True,"[]",,
a5,,jan.zondernaam@vhe.nl,Member,True,"[]",,
a6,Inkoop,inkoop@vhe.nl,Member,True,"[]",,
a7,Michael Kee,mkee@vhe.nl,Member,True,"[]",,
a8,,mkee@vhe.nl,Member,True,"[]",,
a9,,jan.van.dijk@vhe.nl,Member,True,"[]",,

CSV;

$tmpFile = tempnam(sys_get_temp_dir(), 'entra_test_');
file_put_contents($tmpFile, $csv);

// --- 1. parseEntraCsv(): CSV-parsing, incl. gequote JSON-veld en lege regels ---
$rows = EntraCsvParser::parseEntraCsv($tmpFile);
unlink($tmpFile);

test('parseEntraCsv() leest 10 datarijen (lege regels overgeslagen)', count($rows) === 10);
test('parseEntraCsv() parseert het gequote identities-veld correct (komma\'s binnen quotes bewaard)', str_contains($rows[0]['identities'], 'vheindustrialautomationbv.onmicrosoft.com'));
test('parseEntraCsv() gebruikt lowercase kolomnamen', array_key_exists('userprincipalname', $rows[0]));

// --- 2. normalizeDisplayName(): voorloop-symbool + spaties ---
test(
    'normalizeDisplayName() strip het voorloop-"#" en de spatie erna',
    NinjaOneUserMapper::normalizeDisplayName('# Projectenbureau') === 'Projectenbureau'
);
test(
    'normalizeDisplayName() vouwt meervoudige spaties samen',
    NinjaOneUserMapper::normalizeDisplayName('Jan   de   Vries') === 'Jan de Vries'
);
test(
    'normalizeDisplayName() bewaart accenten/diakrieten (geen unicode-stripping)',
    NinjaOneUserMapper::normalizeDisplayName('Renée Bakker') === 'Renée Bakker'
);

// --- 3. splitName(): standaardregel + custom splitter-haak ---
$split = NinjaOneUserMapper::splitName('Projectenbureau');
test('splitName() zet één woord in firstName, lastName blijft leeg', $split['firstName'] === 'Projectenbureau' && $split['lastName'] === '');

$split = NinjaOneUserMapper::splitName('Jan de Vries');
test('splitName() zet eerste woord in firstName, rest in lastName', $split['firstName'] === 'Jan' && $split['lastName'] === 'de Vries');

$customSplitter = static function (string $naam): array {
    // Voorbeeld van eigen split-logica: "Achternaam, Voornaam"-notatie i.p.v. de standaardregel.
    if (str_contains($naam, ',')) {
        [$achternaam, $voornaam] = array_map('trim', explode(',', $naam, 2));
        return ['firstName' => $voornaam, 'lastName' => $achternaam];
    }
    return NinjaOneUserMapper::splitName($naam);
};
$split = NinjaOneUserMapper::splitName('Bakker, Renée', $customSplitter);
test('splitName() gebruikt de meegegeven custom splitter', $split['firstName'] === 'Renée' && $split['lastName'] === 'Bakker');

// --- 4. mapToNinjaOneUser() + convertRows(): business rules end-to-end ---
$resultaat = EntraToNinjaOneConverter::convertRows($rows);

test('convertRows() houdt precies 4 geldige gebruikers over (a1, a5, a7, a9)', count($resultaat['users']) === 4);
test('convertRows() sluit 6 rijen uit (voorbeeldrij, guest, geen email, ongeldige email, inkoop, mkee-zonder-naam)', count($resultaat['skipped']) === 6);

$emails = array_column($resultaat['users'], 'email');
test('Projectenbureau (gedeelde mailbox, geen afleidbare achternaam) is uitgesloten', !in_array('projectenbureau@vhe.nl', $emails, true));
test('Inkoop (het letterlijke voorbeeld uit de aanvraag) is uitgesloten', !in_array('inkoop@vhe.nl', $emails, true));

$janDeVries = current(array_filter($resultaat['users'], static fn ($u) => $u['email'] === 'jan.devries@vhe.nl'));
test('Jan de Vries: firstName/lastName via displayName-split (behoudt "de Vries")', $janDeVries['firstName'] === 'Jan' && $janDeVries['lastName'] === 'de Vries');

$janZonderNaam = current(array_filter($resultaat['users'], static fn ($u) => $u['email'] === 'jan.zondernaam@vhe.nl'));
test('Geen displayName, maar e-mail volgt voor.achter-patroon -> afgeleid uit e-mail', $janZonderNaam['firstName'] === 'Jan' && $janZonderNaam['lastName'] === 'Zondernaam');

$michaelKee = current(array_filter($resultaat['users'], static fn ($u) => $u['email'] === 'mkee@vhe.nl'));
test('E-mail is een afkorting (mkee), maar displayName is een echte naam -> gebruikt displayName', $michaelKee['firstName'] === 'Michael' && $michaelKee['lastName'] === 'Kee');

$janVanDijk = current(array_filter($resultaat['users'], static fn ($u) => $u['email'] === 'jan.van.dijk@vhe.nl'));
test('E-mail met tussenvoegsel (jan.van.dijk) -> achternaam "Van Dijk"', $janVanDijk['firstName'] === 'Jan' && $janVanDijk['lastName'] === 'Van Dijk');

$redenen = array_column($resultaat['skipped'], 'reden');
test('Guest wordt uitgesloten met duidelijke reden', (bool) array_filter($redenen, static fn ($r) => str_contains($r, "geen 'Member'")));
test('Ontbrekende userPrincipalName wordt uitgesloten met duidelijke reden', (bool) array_filter($redenen, static fn ($r) => str_contains($r, 'Ontbrekende userPrincipalName')));
test('Ongeldig e-mailadres wordt uitgesloten met duidelijke reden', (bool) array_filter($redenen, static fn ($r) => str_contains($r, 'Ongeldig e-mailadres')));
test('Geen afleidbare achternaam wordt uitgesloten met duidelijke reden (gedeelde mailbox)', (bool) array_filter($redenen, static fn ($r) => str_contains($r, 'Kan geen achternaam afleiden')));

// --- 5. include_guests=true: de guest-rij heeft wél een bruikbare displayName, dus telt nu mee ---
$resultaatMetGuests = EntraToNinjaOneConverter::convertRows($rows, null, true);
test('convertRows(includeGuests: true) neemt de guest (met bruikbare naam) wél mee', count($resultaatMetGuests['users']) === 5);

// --- 6. exportTabDelimited(): exacte kolomvolgorde + TAB-scheiding ---
$tab = NinjaOneExporter::exportTabDelimited($resultaat['users']);
$regels = explode("\r\n", trim($tab));

test('exportTabDelimited() begint met de NinjaOne-headerregel', $regels[0] === "First name\tLast name\tEmail\tPhone");
test('exportTabDelimited() scheidt velden met een TAB en laat nooit een lege Last name door', $regels[1] === "Jan\tde Vries\tjan.devries@vhe.nl\t");
test('exportTabDelimited() bevat evenveel datarijen als gebruikers', count($regels) === count($resultaat['users']) + 1);

echo PHP_EOL . ($gefaald ? 'Eén of meer tests gefaald.' : 'Alle tests geslaagd.') . PHP_EOL;
exit($gefaald ? 1 : 0);
