<?php
// Eenmalig (of herhaald, dit script is idempotent) uitvoeren: php database/seed_demo_data.php
//
// Vult kennisbank, devices, printers en tickets met realistische demo-content, gebaseerd op de
// daadwerkelijk meest voorkomende IT-helpdesk-categorieën (wachtwoord/account, e-mail, hardware,
// printers, software/VPN/wifi) — zie o.a. Whatfix "13 Common Help Desk Tickets" en FlairsTech
// "Top 20 IT Help Desk Statistics". Puur voor een gevulde demo-/ontwikkelomgeving, niet voor
// productiegebruik.
//
// Powershell
// & "C:\xampp\php\php.exe" database\seed_demo_data.php

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Modules\Kennisbank\Models\KennisbankModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Modules\Device\Models\DeviceModel;

$pdo = Database::pdo();

function eersteId(\PDO $pdo, string $sql): ?int
{
    $row = $pdo->query($sql)->fetch();
    return $row === false ? null : (int) $row['id'];
}

$auteurId = eersteId($pdo, "SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1");
$afdelingIct = eersteId($pdo, "SELECT id FROM afdelingen WHERE naam = 'ICT' LIMIT 1");
$afdelingHr = eersteId($pdo, "SELECT id FROM afdelingen WHERE naam = 'HR' LIMIT 1");
$afdelingFinance = eersteId($pdo, "SELECT id FROM afdelingen WHERE naam = 'Finance' LIMIT 1");
$afdelingFacilitair = eersteId($pdo, "SELECT id FROM afdelingen WHERE naam = 'Facilitair' LIMIT 1");
$medewerkerId = eersteId($pdo, "SELECT id FROM medewerkers WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1");

// --- Kennisbank: veelvoorkomende, echte helpdesk-onderwerpen -------------------------------------

$artikelen = [
    [
        'titel' => 'Wachtwoord resetten via Zelfbedieningsportaal',
        'categorie' => 'Account & Toegang',
        'subcategorie' => 'Wachtwoord',
        'samenvatting' => 'Stappen om zelf een vergeten wachtwoord te resetten zonder tussenkomst van de helpdesk.',
        'tags' => 'wachtwoord, reset, account, self-service',
        'inhoud' => "Wachtwoordresets zijn de meest voorkomende helpdesk-melding. In de meeste gevallen kan de gebruiker dit zelf oplossen:\n\n1. Ga naar het zelfbedieningsportaal (portal.leenvanpunt.local) en klik op 'Wachtwoord vergeten'.\n2. Vul het werk-e-mailadres in en volg de link die per e-mail wordt verzonden.\n3. Kies een nieuw wachtwoord van minimaal 12 tekens met een hoofdletter, cijfer en leesteken.\n4. Log opnieuw in op alle apparaten (laptop, telefoon, Outlook) met het nieuwe wachtwoord.\n\nWerkt de resetlink niet, controleer dan of het account niet is vergrendeld na te veel mislukte pogingen (zie 'Account vergrendeld na meerdere mislukte inlogpogingen').",
    ],
    [
        'titel' => 'Account vergrendeld na meerdere mislukte inlogpogingen',
        'categorie' => 'Account & Toegang',
        'subcategorie' => 'Accountbeheer',
        'samenvatting' => 'Een account wordt automatisch vergrendeld na 5 foutieve inlogpogingen binnen 15 minuten.',
        'tags' => 'account, vergrendeld, lockout, inloggen',
        'inhoud' => "Na 5 mislukte inlogpogingen binnen 15 minuten wordt een account 30 minuten vergrendeld als beveiligingsmaatregel tegen brute-force-pogingen.\n\nOplossing:\n1. Wacht 30 minuten en probeer opnieuw, of vraag de helpdesk om het account handmatig te deblokkeren.\n2. Controleer of Caps Lock niet per ongeluk aanstaat.\n3. Als het wachtwoord daadwerkelijk vergeten is, gebruik dan eerst de resetprocedure in plaats van te blijven proberen.\n\nBlijft een account herhaaldelijk vergrendelen zonder duidelijke oorzaak, controleer dan of er nog een oud opgeslagen wachtwoord in een e-mailapp of gekoppelde dienst (bijv. een oude telefoon) automatisch wordt geprobeerd.",
    ],
    [
        'titel' => 'Multifactor-authenticatie (MFA) opnieuw instellen na nieuwe telefoon',
        'categorie' => 'Account & Toegang',
        'subcategorie' => 'MFA',
        'samenvatting' => 'MFA-app moet opnieuw gekoppeld worden wanneer een gebruiker een nieuwe telefoon heeft.',
        'tags' => 'mfa, 2fa, authenticator, telefoon',
        'inhoud' => "Bij een nieuwe of gereset telefoon werkt de oude authenticator-koppeling niet meer.\n\n1. Meld dit bij de helpdesk zodat de bestaande MFA-registratie op naam van de gebruiker verwijderd kan worden (dit kan de gebruiker niet zelf).\n2. Installeer de authenticator-app opnieuw op de nieuwe telefoon.\n3. Log in met het account, kies 'MFA opnieuw instellen' en scan de QR-code.\n4. Bewaar de backup-codes op een veilige plek (niet in dezelfde mailbox).\n\nZonder identiteitscontrole (telefonisch of persoonlijk) wordt een MFA-reset nooit uitgevoerd, ook niet op dringend verzoek per e-mail — dit is een bekend social-engineering-risico.",
    ],
    [
        'titel' => 'Outlook synchroniseert niet / e-mail komt vertraagd binnen',
        'categorie' => 'E-mail',
        'subcategorie' => 'Outlook',
        'samenvatting' => 'Veelvoorkomende oorzaken en oplossingen wanneer Outlook niet actueel synchroniseert.',
        'tags' => 'outlook, e-mail, synchroniseren, exchange',
        'inhoud' => "Meestvoorkomende oorzaken van een niet-synchroniserende Outlook:\n\n1. Verbindingsstatus rechtsonder in Outlook controleren ('Verbonden' vs 'Bezig met verbinden').\n2. Outlook volledig afsluiten (ook via Taakbeheer) en opnieuw opstarten.\n3. Cached Exchange Mode controleren: Bestand > Instellingen account > E-mail bewerken > schuif 'Mail om zoveel maanden bijhouden' terug naar bijv. 3 maanden bij een erg grote mailbox.\n4. Outlook-profiel opnieuw laten aanmaken via Configuratiescherm > Mail als bovenstaande niet werkt.\n\nBij een volledig lege inbox of ontbrekende mappen: dit duidt vaak op een kapot .ost-bestand — dit bestand mag verwijderd worden (Outlook bouwt het automatisch opnieuw op vanaf de server), nooit het .pst-archief.",
    ],
    [
        'titel' => 'Grote bijlage versturen die door de e-mailserver geweigerd wordt',
        'categorie' => 'E-mail',
        'subcategorie' => 'Bijlagen',
        'samenvatting' => 'Bijlagen boven 20 MB worden geweigerd; alternatief is een gedeelde link.',
        'tags' => 'e-mail, bijlage, groot bestand, versturen',
        'inhoud' => "De mailserver weigert bijlagen groter dan 20 MB (zowel verzenden als ontvangen).\n\nAlternatieven:\n1. Bestand plaatsen in de gedeelde SharePoint/Teams-map en een link versturen in plaats van het bestand zelf.\n2. Bestand comprimeren naar .zip — werkt vaak goed bij Office-documenten en foto's, minder bij reeds gecomprimeerde bestanden (video, foto-archieven).\n3. Bij structureel grote bestanden (bijv. tekeningen, video) een vast overdrachtsproces met de klant afspreken in plaats van e-mail.\n\nEen foutmelding 'bericht kon niet worden afgeleverd' met vermelding van de bestandsgrootte bevestigt deze oorzaak.",
    ],
    [
        'titel' => 'Printer offline of drukt niet af',
        'categorie' => 'Printers',
        'subcategorie' => 'Storing',
        'samenvatting' => 'Stappenplan voor een printer die als offline wordt weergegeven of print-opdrachten niet verwerkt.',
        'tags' => 'printer, offline, afdrukken, wachtrij',
        'inhoud' => "Printer- en scanproblemen behoren tot de meest gemelde helpdeskissues. Controleer in deze volgorde:\n\n1. Printer fysiek aan en netwerkkabel/wifi-verbinding aanwezig (lampje/display controleren).\n2. Afdrukwachtrij op de computer legen: Instellingen > Printers en scanners > wachtrij openen > alle taken annuleren.\n3. Spooler-service herstarten (Services.msc > 'Print Spooler' > Opnieuw starten) — lost het merendeel van vastgelopen wachtrijen op.\n4. IP-adres van de printer controleren via het display en vergelijken met het geconfigureerde adres op de computer; DHCP-printers krijgen soms een nieuw adres na een stroomstoring.\n5. Nieuw papierstoring-lampje of tonermelding op het display kan de reden zijn dat de printer 'offline' lijkt terwijl hij online is.\n\nBlijft de printer na een spooler-restart en verse wachtrij nog steeds offline, dan is vervanging van de driver of een netwerkstoring waarschijnlijk — dit is een taak voor de helpdesk, niet zelf op te lossen.",
    ],
    [
        'titel' => 'Scannen naar e-mail werkt niet vanaf de multifunctional',
        'categorie' => 'Printers',
        'subcategorie' => 'Scannen',
        'samenvatting' => 'Scan-naar-e-mail faalt meestal door een verlopen SMTP-wachtwoord op de printer zelf.',
        'tags' => 'printer, scannen, e-mail, multifunctional',
        'inhoud' => "Scan-naar-e-mail gebruikt een apart, op de printer opgeslagen mailaccount om te versturen. Bij een foutmelding op het display na het scannen:\n\n1. Controleer of het wachtwoord van dit mailaccount recent gewijzigd is (jaarlijkse wachtwoordwijziging breekt dit meestal).\n2. Scan in plaats daarvan tijdelijk op naar een USB-stick of gedeelde netwerkmap als die optie beschikbaar is.\n3. Meld dit bij de helpdesk zodat het SMTP-wachtwoord op de printer bijgewerkt kan worden — dit is geen gebruikersinstelling.\n\nScannen náár netwerkmap blijft meestal wel werken wanneer alleen scan-naar-e-mail kapot is, omdat dat een ander protocol gebruikt (SMB in plaats van SMTP).",
    ],
    [
        'titel' => 'Laptop is traag geworden',
        'categorie' => 'Hardware',
        'subcategorie' => 'Prestaties',
        'samenvatting' => 'Meestvoorkomende oorzaken van een trage laptop en wat de gebruiker zelf kan controleren.',
        'tags' => 'laptop, traag, prestaties, opstarten',
        'inhoud' => "Voordat hardwarevervanging overwogen wordt, eerst deze stappen controleren:\n\n1. Vrije schijfruimte controleren (Deze pc > schijf C:) — onder de 10-15% vrije ruimte veroorzaakt merkbare vertraging in Windows.\n2. Aantal opstartprogramma's bekijken via Taakbeheer > tabblad 'Opstarten' en niet-noodzakelijke items uitschakelen.\n3. Windows Update en het aantal openstaande updates controleren — een lang uitgestelde update-installatie kan achtergrond-CPU-gebruik veroorzaken.\n4. Achtergrondprocessen in Taakbeheer sorteren op CPU/geheugen om een specifiek programma te identificeren.\n5. Laptop volledig herstarten (niet alleen in slaapstand) — bij meerdere weken continu gebruik lost dit vaak al veel op.\n\nBij een laptop ouder dan 4-5 jaar met een mechanische harde schijf (geen SSD) is een structurele vertraging vaak een hardware-levensduur-issue — dit is een vervangingsadvies, geen storing.",
    ],
    [
        'titel' => 'Externe monitor of USB-apparaat wordt niet herkend',
        'categorie' => 'Hardware',
        'subcategorie' => 'Randapparatuur',
        'samenvatting' => 'Stappen bij een niet-herkende monitor, dockingstation of USB-apparaat.',
        'tags' => 'monitor, usb, dockingstation, randapparatuur',
        'inhoud' => "1. Kabel(s) controleren op een andere poort/dockingstation aansluiten om een kapotte kabel of poort uit te sluiten.\n2. Bij een dockingstation: de laptop volledig loskoppelen (ook stroom) en na 10 seconden opnieuw aansluiten.\n3. Windows-instellingen > Systeem > Beeldscherm > 'Detecteren' gebruiken als een monitor wel stroom heeft maar geen beeld toont.\n4. Apparaatbeheer controleren op een geel uitroepteken bij de betreffende poort/driver.\n5. Op een ander apparaat testen om te bepalen of het probleem in de laptop, het dockingstation of de monitor/randapparaat zelf zit.\n\nWerkt een dockingstation ineens niet meer na een Windows-update, dan is vaak een driver-herinstallatie nodig — dit meldt de helpdesk actief op zodra bekend voor een bepaald model.",
    ],
    [
        'titel' => 'VPN-verbinding lukt niet vanaf thuis',
        'categorie' => 'Netwerk',
        'subcategorie' => 'VPN',
        'samenvatting' => 'Controlepunten wanneer de VPN-client geen verbinding maakt vanaf een thuisnetwerk.',
        'tags' => 'vpn, thuiswerken, netwerk, verbinding',
        'inhoud' => "1. Controleer of de internetverbinding zelf werkt (een willekeurige website openen) voordat de VPN-client geprobeerd wordt.\n2. VPN-client volledig afsluiten via het systeemvak en opnieuw opstarten in plaats van alleen opnieuw te verbinden.\n3. Controleer of het account-wachtwoord recent gewijzigd is — de VPN-client onthoudt soms het oude wachtwoord totdat opnieuw ingelogd wordt.\n4. Sommige thuisrouters blokkeren standaard bepaalde VPN-protocollen; een andere thuisrouterinstelling (bijv. 'VPN passthrough') kan hiervoor nodig zijn.\n5. Bij een 'certificaatfout': dit wijst meestal op een verlopen of nog niet uitgegeven clientcertificaat — dit lost de gebruiker niet zelf op.\n\nWerkt VPN op kantoor-wifi wel maar thuis niet, dan is het probleem vrijwel zeker het thuisnetwerk en niet het account.",
    ],
    [
        'titel' => 'Wifi verbindt niet of valt steeds weg',
        'categorie' => 'Netwerk',
        'subcategorie' => 'Wifi',
        'samenvatting' => 'Basiscontroles bij een wegvallende of niet tot stand komende wifi-verbinding op kantoor.',
        'tags' => 'wifi, netwerk, verbinding, kantoor',
        'inhoud' => "1. Controleer of andere apparaten in dezelfde ruimte hetzelfde probleem hebben — dat wijst op een lokale access point-storing in plaats van een individueel apparaat.\n2. Wifi op de laptop uit- en weer aanzetten (niet alleen opnieuw verbinden met hetzelfde netwerk).\n3. Het opgeslagen wifi-profiel 'vergeten' en opnieuw verbinden, vooral na een wachtwoordwijziging van het bedrijfsnetwerk.\n4. Netwerkadapter-driver bijwerken via Apparaatbeheer als het probleem zich alleen op één specifieke laptop voordoet.\n\nValt de verbinding specifiek weg bij het lopen tussen verdiepingen/ruimtes, dan is dit vaak een overdracht (roaming) tussen access points — geef de exacte locatie door zodat dit gericht onderzocht kan worden.",
    ],
    [
        'titel' => 'Phishing-mail melden en herkennen',
        'categorie' => 'Beveiliging',
        'subcategorie' => 'Phishing',
        'samenvatting' => 'Hoe een verdachte e-mail te herkennen en veilig te melden zonder schade aan te richten.',
        'tags' => 'phishing, beveiliging, e-mail, veiligheid',
        'inhoud' => "Veelvoorkomende kenmerken van phishing: onverwachte bijlage/link, urgentie ('binnen 24 uur reageren'), een licht afwijkend e-mailadres van de afzender, of een verzoek om in te loggen via een bijgevoegde link in plaats van rechtstreeks naar de bekende website te gaan.\n\nBij twijfel:\n1. Niet klikken op links of bijlagen openen.\n2. De e-mail doorsturen naar de helpdesk (of gebruik de 'Phishing melden'-knop in Outlook indien geïnstalleerd) en daarna verwijderen.\n3. Is er al op een link geklikt of zijn gegevens ingevuld: direct melden, ook al voelt dit ongemakkelijk — hoe eerder gemeld, hoe kleiner de mogelijke schade. Er volgt geen sanctie op het te goeder trouw melden van een fout.\n4. Wachtwoord wijzigen als daadwerkelijk inloggegevens zijn ingevuld op een phishing-pagina, ook als het onschuldig leek.\n\nEen melding wordt altijd serieus genomen, ook als achteraf blijkt dat de mail legitiem was — liever tien keer onnodig gemeld dan één keer gemiste phishing.",
    ],
    [
        'titel' => 'Nieuwe software-installatie aanvragen',
        'categorie' => 'Software',
        'subcategorie' => 'Installatie',
        'samenvatting' => 'Procedure voor het aanvragen van software die niet standaard geïnstalleerd is.',
        'tags' => 'software, installatie, aanvraag, licentie',
        'inhoud' => "Gebruikers hebben doorgaans geen lokale beheerdersrechten om zelf software te installeren — dit is een bewuste beveiligingsmaatregel.\n\n1. Dien een ticket in met de exacte softwarenaam, het doel (welk werkproces) en of een licentie al aanwezig is (bijv. via de leidinggevende aangeschaft).\n2. De helpdesk controleert compatibiliteit met bestaande software en of een bedrijfslicentie beschikbaar of nodig is.\n3. Bij goedkeuring wordt de installatie op afstand of via een vooraf ingericht installatiepakket uitgevoerd.\n\nSoftware die niet via de officiële kanalen wordt gedownload en geïnstalleerd (bijv. gratis varianten van betaalde tools) wordt niet ondersteund en kan om beveiligingsredenen automatisch verwijderd worden.",
    ],
];

$kbIds = [];
$selectKb = $pdo->prepare('SELECT id FROM kennisbank_artikelen WHERE titel = ? AND deleted_at IS NULL');
foreach ($artikelen as $artikel) {
    $selectKb->execute([$artikel['titel']]);
    $bestaand = $selectKb->fetch();
    if ($bestaand !== false) {
        echo "Kennisbank: bestaat al, overgeslagen: {$artikel['titel']}\n";
        $kbIds[] = (int) $bestaand['id'];
        continue;
    }

    $id = KennisbankModel::create([
        'titel' => $artikel['titel'],
        'categorie' => $artikel['categorie'],
        'subcategorie' => $artikel['subcategorie'],
        'samenvatting' => $artikel['samenvatting'],
        'tags' => $artikel['tags'],
        'inhoud' => $artikel['inhoud'],
        'auteur_id' => $auteurId,
    ]);
    $kbIds[] = $id;
    echo "Kennisbank: aangemaakt: {$artikel['titel']} (id {$id})\n";
}

// --- Printers: veelvoorkomende merken/modellen in een kantooromgeving ---------------------------

$printers = [
    ['naam' => 'HP LaserJet Pro M404 - 1e verdieping', 'computer_naam' => null, 'type' => 'Network', 'driver_naam' => 'HP LaserJet Pro M404-M405 PCL-6', 'ip_adres' => '10.10.1.51', 'opmerking' => 'Zwart-wit, dichtst bij de balie op de 1e verdieping.'],
    ['naam' => 'Canon imageRUNNER C3226i - Kantine', 'computer_naam' => null, 'type' => 'Network', 'driver_naam' => 'Canon Generic Plus PCL6', 'ip_adres' => '10.10.1.52', 'opmerking' => 'Kleurenprinter/multifunctional, ook scannen naar e-mail.'],
    ['naam' => 'Brother HL-L2350DW - Magazijn', 'computer_naam' => null, 'type' => 'Network', 'driver_naam' => 'Brother HL-L2350DW series', 'ip_adres' => '10.10.2.20', 'opmerking' => 'Wifi-printer, staat los van het bekabelde netwerk.'],
];

$selectPrinter = $pdo->prepare('SELECT id FROM printers WHERE naam = ? AND deleted_at IS NULL');
$insertPrinter = $pdo->prepare(
    'INSERT INTO printers (naam, computer_naam, type, driver_naam, ip_adres, opmerking, aangemaakt_door_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
);
foreach ($printers as $printer) {
    $selectPrinter->execute([$printer['naam']]);
    if ($selectPrinter->fetch() !== false) {
        echo "Printer: bestaat al, overgeslagen: {$printer['naam']}\n";
        continue;
    }

    $insertPrinter->execute([
        $printer['naam'], $printer['computer_naam'], $printer['type'],
        $printer['driver_naam'], $printer['ip_adres'], $printer['opmerking'], $auteurId,
    ]);
    echo "Printer: aangemaakt: {$printer['naam']} (id {$pdo->lastInsertId()})\n";
}

// --- Devices + geïnstalleerde software -----------------------------------------------------------

$devices = [
    [
        'naam' => 'Laptop Demo - Sales 01', 'extern_apparaat_id' => 'DEMO-DEV-0001',
        'software' => [
            ['publisher' => 'Microsoft Corporation', 'naam' => 'Windows 11 Pro', 'versie' => '23H2', 'platform' => 'Windows', 'system_component' => 1],
            ['publisher' => 'Microsoft Corporation', 'naam' => 'Microsoft 365 Apps for enterprise', 'versie' => '2407', 'platform' => 'Windows', 'system_component' => 0],
            ['publisher' => 'Google LLC', 'naam' => 'Google Chrome', 'versie' => '126.0.6478.127', 'platform' => 'Windows', 'system_component' => 0],
            ['publisher' => 'Microsoft Corporation', 'naam' => 'Microsoft Teams', 'versie' => '24175.1508.2941.1204', 'platform' => 'Windows', 'system_component' => 0],
            ['publisher' => 'CrowdStrike, Inc.', 'naam' => 'CrowdStrike Falcon Sensor', 'versie' => '7.16.0', 'platform' => 'Windows', 'system_component' => 1],
        ],
    ],
    [
        'naam' => 'Desktop Demo - Finance 01', 'extern_apparaat_id' => 'DEMO-DEV-0002',
        'software' => [
            ['publisher' => 'Microsoft Corporation', 'naam' => 'Windows 11 Pro', 'versie' => '23H2', 'platform' => 'Windows', 'system_component' => 1],
            ['publisher' => 'Microsoft Corporation', 'naam' => 'Microsoft 365 Apps for enterprise', 'versie' => '2407', 'platform' => 'Windows', 'system_component' => 0],
            ['publisher' => 'Adobe Inc.', 'naam' => 'Adobe Acrobat Reader DC', 'versie' => '24.002.20933', 'platform' => 'Windows', 'system_component' => 0],
            ['publisher' => 'Exact Software', 'naam' => 'Exact Online Vertaalassistent', 'versie' => '4.2.1', 'platform' => 'Windows', 'system_component' => 0],
        ],
    ],
    [
        'naam' => 'Laptop Demo - ICT Beheer 01', 'extern_apparaat_id' => 'DEMO-DEV-0003',
        'software' => [
            ['publisher' => 'Microsoft Corporation', 'naam' => 'Windows 11 Pro', 'versie' => '23H2', 'platform' => 'Windows', 'system_component' => 1],
            ['publisher' => 'PuTTY', 'naam' => 'PuTTY', 'versie' => '0.81', 'platform' => 'Windows', 'system_component' => 0],
            ['publisher' => 'Microsoft Corporation', 'naam' => 'Visual Studio Code', 'versie' => '1.91.0', 'platform' => 'Windows', 'system_component' => 0],
            ['publisher' => 'WinSCP', 'naam' => 'WinSCP', 'versie' => '6.3.3', 'platform' => 'Windows', 'system_component' => 0],
        ],
    ],
];

$selectDeviceSoftware = $pdo->prepare('SELECT id FROM device_software WHERE device_id = ? AND naam = ?');
$insertDeviceSoftware = $pdo->prepare(
    'INSERT INTO device_software (device_id, publisher, naam, versie, platform, system_component, eerst_gezien, laatst_gezien) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
);

foreach ($devices as $device) {
    $bestaand = DeviceModel::findByExternId($device['extern_apparaat_id']);
    if ($bestaand !== null) {
        echo "Device: bestaat al, overgeslagen: {$device['naam']}\n";
        $deviceId = (int) $bestaand['id'];
    } else {
        $deviceId = DeviceModel::create([
            'naam' => $device['naam'],
            'extern_apparaat_id' => $device['extern_apparaat_id'],
            'medewerker_id' => $medewerkerId,
            'laatst_geimporteerd_op' => date('Y-m-d H:i:s'),
        ]);
        echo "Device: aangemaakt: {$device['naam']} (id {$deviceId})\n";
    }

    foreach ($device['software'] as $sw) {
        $selectDeviceSoftware->execute([$deviceId, $sw['naam']]);
        if ($selectDeviceSoftware->fetch() !== false) {
            continue;
        }
        $insertDeviceSoftware->execute([
            $deviceId, $sw['publisher'], $sw['naam'], $sw['versie'], $sw['platform'], $sw['system_component'],
        ]);
    }
}

// --- Tickets: realistische, veelvoorkomende meldingen --------------------------------------------

$tickets = [
    ['titel' => 'Wachtwoord vergeten, kan niet inloggen', 'omschrijving' => "Ik ben mijn wachtwoord vergeten na de vakantie en kom er niet meer in, ook de resetlink lijkt niet aan te komen in mijn privémail.", 'opdrachtgever_naam' => 'Sanne Verhoeven', 'categorie' => 'Account & Toegang', 'afdeling_id' => $afdelingHr, 'prioriteit' => 'hoog', 'impact' => 'Hoog', 'status' => 'open'],
    ['titel' => 'Account vergrendeld, meerdere keren fout wachtwoord ingevoerd', 'omschrijving' => "Na het instellen van een nieuw wachtwoord blijft mijn account vergrendeld, waarschijnlijk omdat mijn telefoon nog het oude wachtwoord probeert.", 'opdrachtgever_naam' => 'Bram Willemsen', 'categorie' => 'Account & Toegang', 'afdeling_id' => $afdelingIct, 'prioriteit' => 'normaal', 'impact' => 'Normaal', 'status' => 'in_behandeling'],
    ['titel' => 'MFA-app moet opnieuw gekoppeld worden, nieuwe telefoon', 'omschrijving' => "Ik heb een nieuwe diensttelefoon gekregen en mijn authenticator-app werkt niet meer, kan hierdoor nergens inloggen.", 'opdrachtgever_naam' => 'Femke de Boer', 'categorie' => 'Account & Toegang', 'afdeling_id' => $afdelingFinance, 'prioriteit' => 'hoog', 'impact' => 'Hoog', 'status' => 'open'],
    ['titel' => 'Outlook synchroniseert al twee dagen niet', 'omschrijving' => "Mijn inbox lijkt al twee dagen niet bijgewerkt te worden, collega's zeggen dat ze mij mailen maar ik zie niets binnenkomen.", 'opdrachtgever_naam' => 'Ruben Peters', 'categorie' => 'E-mail', 'afdeling_id' => $afdelingFinance, 'prioriteit' => 'hoog', 'impact' => 'Hoog', 'status' => 'in_behandeling'],
    ['titel' => 'Grote bijlage wordt niet verzonden naar externe klant', 'omschrijving' => "Ik probeer een tekeningbestand van 45 MB naar een klant te sturen maar krijg een foutmelding terug dat het bericht niet afgeleverd kon worden.", 'opdrachtgever_naam' => 'Marloes Jansen', 'categorie' => 'E-mail', 'afdeling_id' => $afdelingFacilitair, 'prioriteit' => 'laag', 'impact' => 'Laag', 'status' => 'opgelost'],
    ['titel' => 'Printer 1e verdieping geeft offline aan', 'omschrijving' => "De HP-printer bij de balie op de 1e verdieping staat al de hele ochtend op offline, meerdere collega's kunnen niet printen.", 'opdrachtgever_naam' => 'Tom Hendriks', 'categorie' => 'Printers', 'afdeling_id' => $afdelingFacilitair, 'prioriteit' => 'hoog', 'impact' => 'Hoog', 'status' => 'open'],
    ['titel' => 'Scannen naar e-mail werkt niet meer vanaf de Canon in de kantine', 'omschrijving' => "Als ik een document scan en naar mezelf wil mailen krijg ik een foutmelding op het scherm van de printer, scannen naar USB werkt wel.", 'opdrachtgever_naam' => 'Lisa Mulder', 'categorie' => 'Printers', 'afdeling_id' => $afdelingHr, 'prioriteit' => 'normaal', 'impact' => 'Normaal', 'status' => 'in_behandeling'],
    ['titel' => 'Laptop is de laatste weken erg traag geworden', 'omschrijving' => "Opstarten duurt inmiddels 5-10 minuten en programma's blijven vaak hangen, is al een paar keer helemaal vastgelopen tijdens een videocall.", 'opdrachtgever_naam' => 'Daan Koster', 'categorie' => 'Hardware', 'afdeling_id' => $afdelingIct, 'prioriteit' => 'normaal', 'impact' => 'Normaal', 'status' => 'open'],
    ['titel' => 'Dockingstation herkent externe monitor niet meer', 'omschrijving' => "Sinds gisteren geeft mijn externe monitor geen beeld meer via het dockingstation, op de laptop zelf werkt het beeld gewoon.", 'opdrachtgever_naam' => 'Iris van Dijk', 'categorie' => 'Hardware', 'afdeling_id' => $afdelingFinance, 'prioriteit' => 'normaal', 'impact' => 'Normaal', 'status' => 'opgelost'],
    ['titel' => 'VPN maakt geen verbinding vanaf thuis', 'omschrijving' => "Sinds ik thuis werk krijg ik een certificaatfout zodra ik verbinding probeer te maken met de VPN, op kantoor werkt alles prima.", 'opdrachtgever_naam' => 'Niels Bakker', 'categorie' => 'Netwerk', 'afdeling_id' => $afdelingIct, 'prioriteit' => 'hoog', 'impact' => 'Hoog', 'status' => 'open'],
    ['titel' => 'Wifi valt steeds weg op de 2e verdieping', 'omschrijving' => "Meerdere collega's op de 2e verdieping klagen dat de wifi elke paar minuten kort wegvalt, vooral rond de vergaderruimtes.", 'opdrachtgever_naam' => 'Sophie de Groot', 'categorie' => 'Netwerk', 'afdeling_id' => $afdelingFacilitair, 'prioriteit' => 'normaal', 'impact' => 'Normaal', 'status' => 'in_behandeling'],
    ['titel' => 'Verdachte e-mail met betaalverzoek van "de directeur"', 'omschrijving' => "Ik kreeg een e-mail die lijkt te komen van de directeur met een dringend verzoek om een betaling te doen aan een onbekend rekeningnummer, dit voelt niet goed.", 'opdrachtgever_naam' => 'Kevin Smit', 'categorie' => 'Beveiliging', 'afdeling_id' => $afdelingFinance, 'prioriteit' => 'urgent', 'impact' => 'Kritiek', 'status' => 'open'],
    ['titel' => 'Aanvraag installatie Adobe Acrobat Pro voor contractwerk', 'omschrijving' => "Voor het nakijken en ondertekenen van contracten heb ik graag Adobe Acrobat Pro geïnstalleerd, de gratis Reader is niet toereikend.", 'opdrachtgever_naam' => 'Anouk Willems', 'categorie' => 'Software', 'afdeling_id' => $afdelingHr, 'prioriteit' => 'laag', 'impact' => 'Laag', 'status' => 'gesloten'],
];

$selectTicket = $pdo->prepare('SELECT id FROM tickets WHERE titel = ? AND deleted_at IS NULL');
foreach ($tickets as $ticket) {
    $selectTicket->execute([$ticket['titel']]);
    if ($selectTicket->fetch() !== false) {
        echo "Ticket: bestaat al, overgeslagen: {$ticket['titel']}\n";
        continue;
    }

    $id = TicketModel::create([
        'titel' => $ticket['titel'],
        'omschrijving' => $ticket['omschrijving'],
        'opdrachtgever_naam' => $ticket['opdrachtgever_naam'],
        'categorie' => $ticket['categorie'],
        'afdeling_id' => $ticket['afdeling_id'],
        'prioriteit' => $ticket['prioriteit'],
        'impact' => $ticket['impact'],
        'status' => $ticket['status'],
        'aangemaakt_door_id' => $auteurId,
        'behandelaar_id' => $ticket['status'] === 'open' ? null : $auteurId,
    ]);
    echo "Ticket: aangemaakt: {$ticket['titel']} (id {$id})\n";
}

echo "\nKlaar. Demo-data toegevoegd (kennisbank, printers, devices, tickets).\n";
