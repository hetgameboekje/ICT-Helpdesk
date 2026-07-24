<?php

namespace App\Modules\Agenda;

use App\Core\Controller;
use App\Modules\Agenda\Models\AgendaItemModel;
use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Modules\Verbeterpunt\Models\VerbeterpuntModel;
use App\Shared\Rechten\Models\RechtenModel;
use App\Shared\User\Models\UserModel;

class AgendaController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('agenda', 'lezen');

        $openTickets = array_values(array_filter(
            TicketModel::all('titel ASC'),
            fn (array $t) => $t['status'] !== 'afgehandeld'
        ));

        $this->render('Modules/Agenda/Views/AgendaView/index', [
            'activeModule' => 'agenda',
            'pageTitle' => 'Agenda',
            'gebruikers' => UserModel::all('naam ASC'),
            'tickets' => $openTickets,
            'verbeterpunten' => VerbeterpuntModel::all('titel ASC'),
            'huidigeGebruikerId' => $this->currentUserId(),
            'types' => AgendaItemModel::TYPES,
            'teamVandaag' => MedewerkerModel::alleMetInBehandelingTellingen(),
        ]);
    }

    /** JSON-feed voor FullCalendar: GET /agenda/events?user_id=&start=&end= */
    public function events(): void
    {
        $this->requirePermission('agenda', 'lezen');

        $userId = (int) ($_GET['user_id'] ?? $this->currentUserId());
        $vanaf = $_GET['start'] ?? null;
        $tot = $_GET['end'] ?? null;

        $items = AgendaItemModel::forUser($userId, $vanaf, $tot);

        $kleuren = ['afspraak' => '#378ADD', 'ticket' => '#e2a94a', 'verbeterpunt' => '#7ecb57'];

        $events = array_map(static function (array $item) use ($kleuren): array {
            return [
                'id' => $item['id'],
                'title' => $item['titel'],
                'start' => str_replace(' ', 'T', $item['start_op']),
                'end' => str_replace(' ', 'T', $item['eind_op']),
                'color' => $kleuren[$item['type']] ?? '#378ADD',
                'extendedProps' => [
                    'omschrijving' => $item['omschrijving'],
                    'type' => $item['type'],
                    'gekoppeld_id' => $item['gekoppeld_id'],
                    'locatie' => $item['locatie'],
                    'user_id' => (int) $item['user_id'],
                    'user_naam' => $item['user_naam'],
                ],
            ];
        }, $items);

        header('Content-Type: application/json');
        echo json_encode($events);
    }

    /** JSON-feed voor FullCalendar, teamoverzicht: GET /agenda/team-events?start=&end=&alleen_in_behandeling=1 */
    public function teamEvents(): void
    {
        $this->requirePermission('agenda', 'lezen');

        $vanaf = $_GET['start'] ?? null;
        $tot = $_GET['end'] ?? null;
        $alleenInBehandeling = ($_GET['alleen_in_behandeling'] ?? '') === '1';

        $items = AgendaItemModel::forTeam($vanaf, $tot, $alleenInBehandeling);

        $kleuren = ['afspraak' => '#378ADD', 'ticket' => '#e2a94a', 'verbeterpunt' => '#7ecb57'];

        $events = array_map(static function (array $item) use ($kleuren): array {
            $titel = $item['user_naam'] . ': ' . $item['titel'];
            if (!empty($item['gekoppeld_titel'])) {
                $titel .= ' (' . $item['gekoppeld_titel'] . ')';
            }

            return [
                'id' => $item['id'],
                'title' => $titel,
                'start' => str_replace(' ', 'T', $item['start_op']),
                'end' => str_replace(' ', 'T', $item['eind_op']),
                'color' => $kleuren[$item['type']] ?? '#378ADD',
                'extendedProps' => [
                    'omschrijving' => $item['omschrijving'],
                    'type' => $item['type'],
                    'gekoppeld_id' => $item['gekoppeld_id'],
                    'gekoppeld_titel' => $item['gekoppeld_titel'],
                    'gekoppeld_status' => $item['gekoppeld_status'],
                    'locatie' => $item['locatie'],
                    'user_id' => (int) $item['user_id'],
                    'user_naam' => $item['user_naam'],
                ],
            ];
        }, $items);

        header('Content-Type: application/json');
        echo json_encode($events);
    }

    public function store(): void
    {
        $this->requireJsonPermission('agenda', 'schrijven');

        $data = $this->readJsonOrPost();
        $item = $this->validated($data);

        if ($item['titel'] === '' || $item['start_op'] === '' || $item['eind_op'] === '') {
            $this->jsonError('Titel, startdatum en einddatum zijn verplicht.');
            return;
        }

        $item['aangemaakt_door_id'] = $this->currentUserId();
        $id = AgendaItemModel::create($item);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'id' => $id]);
    }

    public function update(int $id): void
    {
        $this->requireJsonPermission('agenda', 'schrijven');

        $bestaand = AgendaItemModel::find($id);
        if ($bestaand === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Niet gevonden.']);
            return;
        }

        // Slepen/resizen in de kalender stuurt bv. alleen start_op/eind_op mee — merge met het
        // bestaande item zodat niet-meegestuurde velden (titel, koppeling, ...) niet worden gewist.
        $data = array_merge($bestaand, $this->readJsonOrPost());
        $item = $this->validated($data, isUpdate: true);

        AgendaItemModel::update($id, $item);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    public function destroy(int $id): void
    {
        $this->requireJsonPermission('agenda', 'verwijderen');

        AgendaItemModel::delete($id);

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }

    /** Zoals requirePermission(), maar geeft bij ontbrekend recht een JSON 403 i.p.v. een HTML-pagina — nodig
     *  voor deze fetch()-endpoints, anders faalt r.json() in de kalender stil zonder duidelijke foutmelding. */
    private function requireJsonPermission(string $module, string $actie): void
    {
        $this->requireAuth();

        if (($this->currentUser()['rol'] ?? '') === 'admin') {
            return;
        }

        if (!RechtenModel::has((int) $this->currentUserId(), $module, $actie)) {
            $this->jsonError('Je hebt geen rechten om de agenda te wijzigen.', 403);
        }
    }

    private function readJsonOrPost(): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        return is_array($json) ? $json : $_POST;
    }

    /** @param array{titel?:mixed, ...} $data */
    private function validated(array $data, bool $isUpdate = false): array
    {
        $type = in_array($data['type'] ?? '', array_keys(AgendaItemModel::TYPES), true) ? $data['type'] : 'afspraak';

        $item = [
            'titel' => trim((string) ($data['titel'] ?? '')),
            'omschrijving' => trim((string) ($data['omschrijving'] ?? '')) ?: null,
            'start_op' => str_replace('T', ' ', (string) ($data['start_op'] ?? '')),
            'eind_op' => str_replace('T', ' ', (string) ($data['eind_op'] ?? '')),
            'type' => $type,
            'gekoppeld_id' => $type !== 'afspraak' && !empty($data['gekoppeld_id']) ? (int) $data['gekoppeld_id'] : null,
            'locatie' => trim((string) ($data['locatie'] ?? '')) ?: null,
        ];

        if (!$isUpdate || isset($data['user_id'])) {
            $item['user_id'] = !empty($data['user_id']) ? (int) $data['user_id'] : $this->currentUserId();
        }

        return $item;
    }

    private function jsonError(string $message, int $status = 422): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
