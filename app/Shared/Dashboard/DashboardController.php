<?php

namespace App\Shared\Dashboard;

use App\Core\Controller;
use App\Modules\Beheer\Models\LocatieModel;
use App\Modules\CyberRisico\Models\CyberRisicoModel;
use App\Modules\EmailVerwerking\Models\EmailAiAnalysisModel;
use App\Modules\EmailVerwerking\Models\ImportedEmailModel;
use App\Modules\EmailVerwerking\Models\KbArticleDraftModel;
use App\Modules\Kennisbank\Models\KennisbankModel;
use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Modules\Schijfgebruik\Models\SchijfgebruikSchijfModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Modules\Verbeterpunt\Models\VerbeterpuntModel;
use App\Modules\Voorraad\Models\VoorraadItemModel;
use App\Modules\Voorraad\Models\VoorraadTypeModel;
use App\Modules\Urenstaat\Models\UrenstaatModel;
use App\Shared\Afdeling\Models\AfdelingModel;
use App\Shared\User\Models\UserModel;

class DashboardController extends Controller
{
    private const CYBER_CATEGORIE_LABELS = [
        'fysieke_toegang' => 'Fysieke toegang',
        'social_engineering' => 'Social engineering',
        'onveilige_opslag' => 'Onveilige opslag',
        'papieren_informatie' => 'Papieren informatie',
        'device_exposure' => 'Device exposure',
        'overig' => 'Overig',
    ];

    private const CYBER_PRIORITEIT_LABELS = [
        'laag' => 'Laag',
        'middel' => 'Middel',
        'hoog' => 'Hoog',
        'kritiek' => 'Kritiek',
    ];

    public function index(): void
    {
        if (empty($_SESSION['user'])) {
            $this->renderLandingpagina();
            return;
        }

        $this->requireAuth();

        $mag = [
            'tickets' => ['lezen' => $this->hasRecht('tickets'), 'schrijven' => $this->hasRecht('tickets', 'schrijven')],
            'verbeterpunten' => ['lezen' => $this->hasRecht('verbeterpunten')],
            'medewerkers' => ['lezen' => $this->hasRecht('medewerkers')],
            'voorraad' => ['lezen' => $this->hasRecht('voorraad')],
            'uitgiften' => ['lezen' => $this->hasRecht('uitgiften')],
            'cyberrisicos' => ['lezen' => $this->hasRecht('cyberrisicos'), 'schrijven' => $this->hasRecht('cyberrisicos', 'schrijven')],
            'agenda' => ['lezen' => $this->hasRecht('agenda'), 'schrijven' => $this->hasRecht('agenda', 'schrijven')],
            'urenstaat' => ['lezen' => $this->hasRecht('urenstaat'), 'schrijven' => $this->hasRecht('urenstaat', 'schrijven')],
            'kennisbank' => ['lezen' => $this->hasRecht('kennisbank')],
            'printers' => ['lezen' => $this->hasRecht('printers')],
            'email_verwerking' => ['lezen' => $this->hasRecht('email_verwerking')],
            'schijfgebruik' => ['lezen' => $this->hasRecht('schijfgebruik')],
        ];

        $naam = (string) ($this->currentUser()['naam'] ?? '');
        $voornaam = trim(explode(' ', $naam)[0] ?? $naam) ?: $naam;
        $uur = (int) date('G');
        $dagdeel = $uur < 12 ? 'Goedemorgen' : ($uur < 18 ? 'Goedemiddag' : 'Goedenavond');

        $draftTellingen = $mag['email_verwerking']['lezen'] ? KbArticleDraftModel::telPerStatus() : [];
        $mailTellingen = $mag['email_verwerking']['lezen'] ? ImportedEmailModel::telPerStatus() : [];
        $voorraadTellingen = $mag['voorraad']['lezen'] ? VoorraadItemModel::telPerStatus() : [];
        $cyberPrioriteitTellingen = $mag['cyberrisicos']['lezen'] ? CyberRisicoModel::countByPrioriteit() : [];

        $this->render('Views/dashboard/index', [
            'activeModule' => 'dashboard',
            'pageTitle' => $voornaam !== '' ? "{$dagdeel}, {$voornaam} \u{1F44B}" : $dagdeel,
            'breadcrumbs' => ['Werkplek'],
            'mag' => $mag,
            'stats' => [
                'tickets_open' => $mag['tickets']['lezen'] ? TicketModel::countByStatus('open') : 0,
                'tickets_wacht_op_info' => $mag['tickets']['lezen'] ? TicketModel::countByStatus('wacht_op_info') : 0,
                'tickets_in_behandeling' => $mag['tickets']['lezen'] ? TicketModel::countByStatus('in_behandeling') : 0,
                'verbeterpunten' => $mag['verbeterpunten']['lezen'] ? count(VerbeterpuntModel::all()) : 0,
                'medewerkers' => $mag['medewerkers']['lezen'] ? count(MedewerkerModel::all()) : 0,
            ],
            'actieveTickets' => $mag['tickets']['lezen'] ? TicketModel::actief(5) : [],
            'topUitgegevenHardware' => $mag['uitgiften']['lezen'] ? UitgifteModel::topUitgegeven(5) : [],
            'cyberrisicosOpen' => $mag['cyberrisicos']['lezen'] ? CyberRisicoModel::countOpen() : 0,
            'cyberrisicosPerDag' => $mag['cyberrisicos']['lezen'] ? CyberRisicoModel::countLast30Days() : [],
            'cyberrisicosByDate' => $mag['cyberrisicos']['lezen'] ? CyberRisicoModel::listLast30DaysGrouped() : [],
            'afdelingen' => AfdelingModel::all(),
            'gebruikers' => UserModel::all('naam ASC'),
            'cyberCategorieen' => self::CYBER_CATEGORIE_LABELS,
            'cyberPrioriteiten' => self::CYBER_PRIORITEIT_LABELS,
            'urenstaatLocaties' => $mag['urenstaat']['schrijven'] ? LocatieModel::visibleForUser((int) $this->currentUserId()) : [],
            'urenstaatOpen' => $mag['urenstaat']['schrijven'] ? UrenstaatModel::openForUser((int) $this->currentUserId()) : null,
            'mailmindInReview' => ($draftTellingen['draft_created'] ?? 0) + ($draftTellingen['in_review'] ?? 0),
            'kennisbankStats' => $mag['kennisbank']['lezen'] ? [
                'artikelen' => count(KennisbankModel::all()),
                'aiConcepten' => ($draftTellingen['draft_created'] ?? 0) + ($draftTellingen['in_review'] ?? 0),
                'categorieen' => count(KennisbankModel::distinctCategorieen()),
                'verouderd' => KennisbankModel::countOutdated(6),
            ] : null,
            'mailmindStats' => $mag['email_verwerking']['lezen'] ? [
                'ontvangen' => array_sum($mailTellingen),
                'aiAnalyse' => count(EmailAiAnalysisModel::all()),
                'conceptArtikel' => ($draftTellingen['draft_created'] ?? 0) + ($draftTellingen['in_review'] ?? 0),
                'gepubliceerd' => $draftTellingen['published'] ?? 0,
            ] : null,
            'verbeterpuntStats' => $mag['verbeterpunten']['lezen'] ? (function () {
                $perStatus = VerbeterpuntModel::telPerStatus();
                return [
                    'voorgesteld' => $perStatus['nieuw'] ?? 0,
                    'inUitvoering' => $perStatus['goedgekeurd'] ?? 0,
                    'afgerondDitKwartaal' => VerbeterpuntModel::countAfgerondDitKwartaal(),
                    'doorlooptijd' => VerbeterpuntModel::gemiddeldeDoorlooptijdDagen(),
                ];
            })() : null,
            'voorraadStats' => $mag['voorraad']['lezen'] ? [
                'typen' => count(VoorraadTypeModel::all()),
                'totaal' => VoorraadItemModel::countAll(),
                'uitgegeven' => $voorraadTellingen['uitgegeven'] ?? 0,
                'afgeschreven' => $voorraadTellingen['afgeschreven'] ?? 0,
            ] : null,
            'uitgifteStats' => $mag['uitgiften']['lezen'] ? [
                'totaal' => UitgifteModel::countAll(),
                'openstaand' => UitgifteModel::countOpen(),
                'dezeWeek' => UitgifteModel::countDezeWeek(),
                'geretourneerd' => UitgifteModel::countGeretourneerd(),
            ] : null,
            'cyberPrioriteitStats' => $mag['cyberrisicos']['lezen'] ? [
                'laag' => $cyberPrioriteitTellingen['laag'] ?? 0,
                'middel' => $cyberPrioriteitTellingen['middel'] ?? 0,
                'hoog' => $cyberPrioriteitTellingen['hoog'] ?? 0,
                'kritiek' => $cyberPrioriteitTellingen['kritiek'] ?? 0,
            ] : null,
            'schijfgebruikStats' => $mag['schijfgebruik']['lezen'] ? SchijfgebruikSchijfModel::dashboardStats() : null,
        ]);
    }

    /**
     * Publieke landingspagina voor niet-ingelogde bezoekers op "/" — een losstaand HTML-bestand
     * (zie CLAUDE.md > Frontend design direction), bewust buiten de app-layout om (geen nav/sidebar).
     */
    private function renderLandingpagina(): void
    {
        readfile(APP_ROOT . '/docs/design/ticketsysteem-overzicht.html');
    }
}
