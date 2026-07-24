<?php

require __DIR__ . '/../app/bootstrap.php';

use App\Api\V1\KennisbankApiController;
use App\Api\V1\ReflectiesApiController;
use App\Api\V1\TicketsApiController;
use App\Api\V1\VerbeterpuntenApiController;
use App\Api\V1\VoorraadApiController;
use App\Core\Router;
use App\Modules\Account\AccountController;
use App\Modules\Agenda\AgendaController;
use App\Modules\Beheer\ApiSleutelController;
use App\Modules\Beheer\BeheerController;
use App\Modules\Beheer\BeveiligingController;
use App\Modules\Beheer\EmailQueueController;
use App\Modules\Beheer\ExportController;
use App\Modules\Beheer\LocatieController;
use App\Modules\Beheer\LogController;
use App\Modules\Beheer\RechtenController;
use App\Modules\CyberRisico\CyberRisicoController;
use App\Modules\CyberRisico\CyberRisicoLogController;
use App\Modules\Device\DeviceController;
use App\Modules\EmailVerwerking\EmailAnalysisController;
use App\Modules\EmailVerwerking\EmailImportController;
use App\Modules\EmailVerwerking\EmailVerwerkingController;
use App\Modules\HardwareUitgave\HardwareUitgaveController;
use App\Modules\Kennisbank\KennisbankController;
use App\Modules\Kennisbank\KennisbankLogController;
use App\Modules\Medewerker\MedewerkerController;
use App\Modules\Printer\PrinterController;
use App\Modules\Reflectie\ReflectieController;
use App\Modules\Reflectie\ReflectieLogController;
use App\Modules\Schijfgebruik\SchijfgebruikController;
use App\Modules\Script\ScriptController;
use App\Modules\Ticket\TicketController;
use App\Modules\Ticket\TicketEmailIntakeController;
use App\Modules\Ticket\TicketLogController;
use App\Modules\Ticket\TicketTijdController;
use App\Modules\Tools\InstallatieController;
use App\Modules\Tools\PhonebookController;
use App\Modules\Tools\RestartReminderController;
use App\Modules\Tools\SignatureController;
use App\Modules\Tools\ToolsController;
use App\Modules\Uitgifte\UitgifteController;
use App\Modules\Urenstaat\UrenstaatController;
use App\Modules\Verbeterpunt\VerbeterpuntController;
use App\Modules\Verbeterpunt\VerbeterpuntLogController;
use App\Modules\Verbeterpunt\VerbeterpuntTijdController;
use App\Modules\Voorraad\VoorraadController;
use App\Shared\Automation\AutomationController;
use App\Shared\Auth\AuthController;
use App\Shared\Dashboard\DashboardController;
use App\Shared\Legal\LegalController;
use App\Shared\Overview\OverviewController;

$router = new Router();

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/', [DashboardController::class, 'index']);

$modules = [
    'tickets' => TicketController::class,
    'verbeterpunten' => VerbeterpuntController::class,
    'reflecties' => ReflectieController::class,
    'kennisbank' => KennisbankController::class,
    'hardware-uitgaven' => HardwareUitgaveController::class,
    'medewerkers' => MedewerkerController::class,
    'voorraad' => VoorraadController::class,
    'apparaten' => DeviceController::class,
    'printers' => PrinterController::class,
    'cyberrisicos' => CyberRisicoController::class,
    'scripts' => ScriptController::class,
    'urenstaat' => UrenstaatController::class,
];

foreach ($modules as $routeBase => $controller) {
    $router->get("/{$routeBase}", [$controller, 'index']);
    $router->get("/{$routeBase}/create", [$controller, 'create']);
    $router->post("/{$routeBase}", [$controller, 'store']);
    $router->get("/{$routeBase}/{id}", [$controller, 'show']);
    $router->get("/{$routeBase}/{id}/edit", [$controller, 'edit']);
    $router->post("/{$routeBase}/{id}", [$controller, 'update']);
    $router->post("/{$routeBase}/{id}/verwijderen", [$controller, 'destroy']);
}

$router->post('/tickets/{id}/log', [TicketLogController::class, 'store']);
$router->post('/tickets/{id}/kennisbank', [TicketController::class, 'kennisbankKoppel']);
$router->post('/tickets/{id}/kennisbank/{id}/verwijderen', [TicketController::class, 'kennisbankOntkoppel']);
$router->post('/api/tickets/vanuit-email', [TicketEmailIntakeController::class, 'store']);
$router->post('/api/tickets/vanuit-aca-email', [TicketEmailIntakeController::class, 'storeAcaUpdate']);
$router->post('/api/email-queue/verwerken', [AutomationController::class, 'emailQueueVerwerken']);
$router->post('/api/tickets/herinneringen', [AutomationController::class, 'ticketHerinneringenGenereren']);
$router->post('/api/logs/opschonen', [AutomationController::class, 'logsOpschonen']);
$router->get('/api/database/export', [AutomationController::class, 'databaseExport']);

// E-mail & kennisbank verwerking (MailMind): webhook + achtergrondtaak, zelfde patroon als
// TicketEmailIntakeController/AutomationController hierboven.
$router->post('/api/email-import/inbound', [EmailImportController::class, 'store']);
$router->post('/api/email-analysis/verwerken', [EmailAnalysisController::class, 'verwerken']);

$router->get('/email-verwerking', [EmailVerwerkingController::class, 'dashboard']);
$router->get('/email-verwerking/inbox', [EmailVerwerkingController::class, 'inbox']);
$router->get('/email-verwerking/logboek', [EmailVerwerkingController::class, 'logboek']);
$router->get('/email-verwerking/review', [EmailVerwerkingController::class, 'review']);
$router->get('/email-verwerking/concepten/{id}', [EmailVerwerkingController::class, 'conceptBewerken']);
$router->post('/email-verwerking/concepten/{id}', [EmailVerwerkingController::class, 'conceptOpslaan']);
$router->post('/email-verwerking/concepten/{id}/publiceren', [EmailVerwerkingController::class, 'publiceren']);
$router->post('/email-verwerking/concepten/{id}/afwijzen', [EmailVerwerkingController::class, 'afwijzen']);
$router->get('/email-verwerking/{id}', [EmailVerwerkingController::class, 'show']);

$router->get('/overzicht', [OverviewController::class, 'index']);

$router->get('/tools', [ToolsController::class, 'index']);

$router->get('/tools/telefoonlijst', [PhonebookController::class, 'index']);
$router->post('/tools/telefoonlijst', [PhonebookController::class, 'store']);
$router->get('/tools/telefoonlijst/{id}', [PhonebookController::class, 'show']);
$router->get('/tools/telefoonlijst/{id}/download', [PhonebookController::class, 'download']);
$router->post('/tools/telefoonlijst/{id}/verwijderen', [PhonebookController::class, 'destroy']);

$router->get('/tools/handtekeningen', [SignatureController::class, 'index']);
$router->get('/tools/handtekeningen/nieuw', [SignatureController::class, 'create']);
$router->post('/tools/handtekeningen', [SignatureController::class, 'store']);
$router->get('/tools/handtekeningen/{id}/bewerken', [SignatureController::class, 'edit']);
$router->post('/tools/handtekeningen/{id}', [SignatureController::class, 'update']);
$router->post('/tools/handtekeningen/{id}/verwijderen', [SignatureController::class, 'destroy']);
$router->post('/tools/handtekeningen/logos', [SignatureController::class, 'uploadLogo']);
$router->post('/tools/handtekeningen/logos/{id}/verwijderen', [SignatureController::class, 'destroyLogo']);

$router->get('/tools/herstart-herinneringen', [RestartReminderController::class, 'index']);
$router->post('/tools/herstart-herinneringen/instellingen', [RestartReminderController::class, 'instellingenOpslaan']);
$router->get('/tools/herstart-herinneringen/export', [RestartReminderController::class, 'exportCsv']);
$router->post('/tools/herstart-herinneringen/versturen', [RestartReminderController::class, 'versturen']);

$router->get('/tools/installatie', [InstallatieController::class, 'index']);
$router->post('/tools/installatie/applicaties', [InstallatieController::class, 'applicatieStore']);
$router->post('/tools/installatie/applicaties/{id}/verwijderen', [InstallatieController::class, 'applicatieDestroy']);
$router->post('/tools/installatie/profielen', [InstallatieController::class, 'profielStore']);
$router->post('/tools/installatie/profielen/{id}/verwijderen', [InstallatieController::class, 'profielDestroy']);
$router->post('/tools/installatie/profielen/{id}/items', [InstallatieController::class, 'profielItemStore']);
$router->post('/tools/installatie/profielen/{id}/items/{id}/verwijderen', [InstallatieController::class, 'profielItemDestroy']);
$router->get('/tools/installatie/apparaten', [InstallatieController::class, 'apparatenZoeken']);
$router->get('/tools/installatie/print', [InstallatieController::class, 'print']);
$router->get('/tools/installatie/opdrachten/nieuw', [InstallatieController::class, 'opdrachtCreate']);
$router->post('/tools/installatie/opdrachten', [InstallatieController::class, 'opdrachtStore']);
$router->get('/tools/installatie/opdrachten/{id}', [InstallatieController::class, 'opdrachtShow']);
$router->get('/tools/installatie/opdrachten/{id}/print', [InstallatieController::class, 'opdrachtPrint']);
$router->post('/tools/installatie/opdrachten/{id}/items/{id}/toggle', [InstallatieController::class, 'opdrachtItemToggle']);
$router->post('/tools/installatie/opdrachten/{id}/verwijderen', [InstallatieController::class, 'opdrachtDestroy']);
$router->get('/tickets/export', [TicketController::class, 'export']);
$router->post('/tickets/import', [TicketController::class, 'import']);
$router->get('/tickets/categorieen', [TicketController::class, 'categorieen']);
$router->post('/tickets/{id}/tijd', [TicketTijdController::class, 'store']);

// JSON-API v1 (referentiepatroon: 3-laags architectuur, zie CLAUDE.md > API-architectuur).
// Zelfde sessie-auth als de routes hierboven; CSRF wordt binnen TicketsApiController zelf
// gecontroleerd omdat Router alle /api/*-paden standaard vrijstelt (bedoeld voor de bestaande
// machine-to-machine endpoints met API-sleutel, zie App\Shared\ApiKey).
$router->get('/api/v1/tickets', [TicketsApiController::class, 'index']);
$router->post('/api/v1/tickets', [TicketsApiController::class, 'store']);
$router->get('/api/v1/tickets/{id}', [TicketsApiController::class, 'show']);
$router->add('PUT', '/api/v1/tickets/{id}', [TicketsApiController::class, 'update']);
$router->add('DELETE', '/api/v1/tickets/{id}', [TicketsApiController::class, 'destroy']);
$router->post('/api/v1/tickets/{id}/log', [TicketsApiController::class, 'addLog']);
$router->post('/api/v1/tickets/{id}/tijd', [TicketsApiController::class, 'addTijd']);
$router->post('/api/v1/tickets/{id}/kennisbank/{id}', [TicketsApiController::class, 'kennisbankKoppel']);
$router->add('DELETE', '/api/v1/tickets/{id}/kennisbank/{id}', [TicketsApiController::class, 'kennisbankOntkoppel']);

$router->get('/api/v1/kennisbank', [KennisbankApiController::class, 'index']);
$router->post('/api/v1/kennisbank', [KennisbankApiController::class, 'store']);
$router->get('/api/v1/kennisbank/{id}', [KennisbankApiController::class, 'show']);
$router->add('PUT', '/api/v1/kennisbank/{id}', [KennisbankApiController::class, 'update']);
$router->add('DELETE', '/api/v1/kennisbank/{id}', [KennisbankApiController::class, 'destroy']);

$router->get('/api/v1/verbeterpunten', [VerbeterpuntenApiController::class, 'index']);
$router->post('/api/v1/verbeterpunten', [VerbeterpuntenApiController::class, 'store']);
$router->get('/api/v1/verbeterpunten/{id}', [VerbeterpuntenApiController::class, 'show']);
$router->add('PUT', '/api/v1/verbeterpunten/{id}', [VerbeterpuntenApiController::class, 'update']);
$router->add('DELETE', '/api/v1/verbeterpunten/{id}', [VerbeterpuntenApiController::class, 'destroy']);
$router->post('/api/v1/verbeterpunten/{id}/log', [VerbeterpuntenApiController::class, 'addLog']);
$router->post('/api/v1/verbeterpunten/{id}/tijd', [VerbeterpuntenApiController::class, 'addTijd']);

$router->get('/api/v1/reflecties', [ReflectiesApiController::class, 'index']);
$router->post('/api/v1/reflecties', [ReflectiesApiController::class, 'store']);
$router->get('/api/v1/reflecties/{id}', [ReflectiesApiController::class, 'show']);
$router->add('PUT', '/api/v1/reflecties/{id}', [ReflectiesApiController::class, 'update']);
$router->add('DELETE', '/api/v1/reflecties/{id}', [ReflectiesApiController::class, 'destroy']);
$router->post('/api/v1/reflecties/{id}/log', [ReflectiesApiController::class, 'addLog']);

$router->get('/api/v1/voorraad', [VoorraadApiController::class, 'index']);
$router->get('/api/v1/voorraad/{id}', [VoorraadApiController::class, 'show']);
$router->add('PUT', '/api/v1/voorraad/{id}/status', [VoorraadApiController::class, 'updateStatus']);
$router->add('DELETE', '/api/v1/voorraad/{id}', [VoorraadApiController::class, 'destroy']);

$router->get('/verbeterpunten/categorieen', [VerbeterpuntController::class, 'categorieen']);
$router->post('/verbeterpunten/{id}/tijd', [VerbeterpuntTijdController::class, 'store']);
$router->post('/verbeterpunten/{id}/log', [VerbeterpuntLogController::class, 'store']);
$router->post('/reflecties/{id}/log', [ReflectieLogController::class, 'store']);
$router->get('/kennisbank/categorieen', [KennisbankController::class, 'categorieen']);
$router->get('/kennisbank/subcategorieen', [KennisbankController::class, 'subcategorieen']);
$router->get('/kennisbank/tags', [KennisbankController::class, 'tags']);
$router->post('/kennisbank/{id}/log', [KennisbankLogController::class, 'store']);
$router->post('/kennisbank/{id}/log/volgorde', [KennisbankLogController::class, 'reorder']);
$router->post('/cyberrisicos/{id}/log', [CyberRisicoLogController::class, 'store']);
$router->get('/apparaten/software', [DeviceController::class, 'softwareIndex']);
$router->post('/apparaten/software-import', [DeviceController::class, 'softwareImport']);
$router->post('/apparaten/software/leegmaken', [DeviceController::class, 'softwareLeegmaken']);

$router->get('/voorraad/{id}/barcode', [VoorraadController::class, 'barcode']);

$router->post('/urenstaat/starten', [UrenstaatController::class, 'starten']);
$router->post('/urenstaat/stoppen', [UrenstaatController::class, 'stoppen']);
$router->post('/urenstaat/{id}/stoppen', [UrenstaatController::class, 'stoppenAjax']);

$router->get('/schijfgebruik', [SchijfgebruikController::class, 'index']);
$router->post('/schijfgebruik/import', [SchijfgebruikController::class, 'upload']);
$router->get('/schijfgebruik/{id}', [SchijfgebruikController::class, 'show']);
$router->post('/schijfgebruik/{id}/medewerker', [SchijfgebruikController::class, 'koppelMedewerker']);

$router->get('/medewerkers/login-check', [MedewerkerController::class, 'loginCheck']);
$router->post('/medewerkers/import', [MedewerkerController::class, 'import']);
$router->get('/medewerkers/hierarchie', [MedewerkerController::class, 'hierarchie']);

$router->get('/uitgiften', [UitgifteController::class, 'index']);
$router->get('/uitgiften/namen', [UitgifteController::class, 'namen']);
$router->get('/uitgiften/items', [UitgifteController::class, 'items']);
$router->get('/uitgiften/create', [UitgifteController::class, 'create']);
$router->post('/uitgiften', [UitgifteController::class, 'store']);
$router->get('/uitgiften/{id}', [UitgifteController::class, 'show']);
$router->post('/uitgiften/{id}/retour', [UitgifteController::class, 'retour']);

$router->get('/agenda', [AgendaController::class, 'index']);
$router->get('/agenda/events', [AgendaController::class, 'events']);
$router->get('/agenda/team-events', [AgendaController::class, 'teamEvents']);
$router->post('/agenda', [AgendaController::class, 'store']);
$router->post('/agenda/{id}', [AgendaController::class, 'update']);
$router->post('/agenda/{id}/verwijderen', [AgendaController::class, 'destroy']);

$router->get('/account', [AccountController::class, 'profiel']);
$router->get('/account/bewerken', [AccountController::class, 'bewerken']);
$router->post('/account', [AccountController::class, 'bijwerken']);
$router->get('/account/locaties', [LocatieController::class, 'indexZelf']);
$router->get('/account/locaties/nieuw', [LocatieController::class, 'nieuwZelf']);
$router->post('/account/locaties', [LocatieController::class, 'aanmakenZelf']);
$router->get('/account/locaties/{id}/bewerken', [LocatieController::class, 'bewerkenZelf']);
$router->post('/account/locaties/{id}', [LocatieController::class, 'opslaanZelf']);
$router->post('/account/locaties/{id}/verwijderen', [LocatieController::class, 'verwijderenZelf']);

$router->get('/beheer', [BeheerController::class, 'index']);
$router->post('/beheer/git-pull', [BeheerController::class, 'gitPull']);
$router->post('/beheer/database-parsen', [BeheerController::class, 'databaseParsen']);
$router->post('/beheer/database-toepassen', [BeheerController::class, 'databaseToepassen']);

$router->get('/beheer/rechten', [RechtenController::class, 'index']);
$router->get('/beheer/rechten/nieuw', [RechtenController::class, 'aanmaken']);
$router->post('/beheer/rechten', [RechtenController::class, 'opslaan']);
$router->get('/beheer/rechten/{id}', [RechtenController::class, 'bewerken']);
$router->post('/beheer/rechten/{id}', [RechtenController::class, 'bijwerken']);
$router->post('/beheer/rechten/{id}/gebruiker', [RechtenController::class, 'gebruikerBijwerken']);
$router->post('/beheer/rechten/{id}/wachtwoord', [RechtenController::class, 'wachtwoordWijzigen']);
$router->post('/beheer/rechten/{id}/verwijderen', [RechtenController::class, 'verwijderen']);
$router->post('/beheer/rechten/{id}/heractiveren', [RechtenController::class, 'heractiveren']);

$router->get('/beheer/api-sleutels', [ApiSleutelController::class, 'index']);
$router->get('/beheer/api-sleutels/nieuw', [ApiSleutelController::class, 'aanmaken']);
$router->post('/beheer/api-sleutels', [ApiSleutelController::class, 'opslaan']);
$router->post('/beheer/api-sleutels/{id}/intrekken', [ApiSleutelController::class, 'intrekken']);
$router->post('/beheer/api-sleutels/{id}/heractiveren', [ApiSleutelController::class, 'heractiveren']);

$router->get('/beheer/locaties', [LocatieController::class, 'index']);
$router->get('/beheer/locaties/nieuw', [LocatieController::class, 'nieuw']);
$router->post('/beheer/locaties', [LocatieController::class, 'aanmaken']);
$router->get('/beheer/locaties/{id}/bewerken', [LocatieController::class, 'bewerken']);
$router->post('/beheer/locaties/{id}', [LocatieController::class, 'opslaan']);
$router->post('/beheer/locaties/{id}/verwijderen', [LocatieController::class, 'verwijderen']);

$router->get('/beheer/log', [LogController::class, 'index']);
$router->get('/beheer/beveiliging', [BeveiligingController::class, 'index']);

$router->get('/beheer/exporteren', [ExportController::class, 'index']);
$router->post('/beheer/exporteren/uitvoeren', [ExportController::class, 'export']);

$router->get('/beheer/emails', [EmailQueueController::class, 'index']);
$router->post('/beheer/emails/test', [EmailQueueController::class, 'test']);

$router->get('/privacybeleid', [LegalController::class, 'privacybeleid']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
