<?php

namespace App\Modules\Ticket;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Kennisbank\Models\KennisbankModel;
use App\Modules\Ticket\Models\TicketKennisbankModel;
use App\Modules\Ticket\Models\TicketLogModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Modules\Ticket\Models\TicketTijdModel;

/**
 * Service/Business-laag voor tickets: validatie, scope-autorisatie en statusovergangs-logica.
 * Kent geen HTTP-concepten ($_GET/$_POST/header()) — de API-laag (App\Api\V1\TicketsApiController)
 * parsed de request en roept deze methods aan met platte arrays. Data-toegang loopt uitsluitend via
 * TicketModel en de bijbehorende koppel-modellen (de Data Access/Repository-laag).
 */
class TicketService
{
    private const STATUSSEN = ['open', 'in_behandeling', 'wacht_op_info', 'afgehandeld'];

    /** @return array{items:array,pagination:array,statusCounts:array,filterOptions:array} */
    public function list(array $currentUser, array $queryParams): array
    {
        $allItems = array_values(array_filter(
            TicketModel::allWithRelations(),
            fn (array $item) => $this->scopeAllowed($item, $currentUser)
        ));

        $filterOptions = $this->filterOptions($allItems);
        $statusCounts = $this->statusCounts($allItems);

        $items = $this->applyDefaultFilters($allItems, $queryParams);
        $items = TableQuery::apply($items, $queryParams, 'titel');
        $pagination = TableQuery::paginate($items, $queryParams);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'statusCounts' => $statusCounts,
            'filterOptions' => $filterOptions,
        ];
    }

    public function find(int $id, array $currentUser): array
    {
        $item = TicketModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Ticket {$id} niet gevonden.");
        }
        if (!$this->scopeAllowed($item, $currentUser)) {
            throw new ForbiddenException("Geen toegang tot ticket {$id}.");
        }

        $gekoppeld = TicketKennisbankModel::gekoppeld($id);
        $gekoppeldeIds = array_column($gekoppeld, 'id');
        $suggesties = array_values(array_filter(
            TicketKennisbankModel::suggesties($id, $item['categorie']),
            fn (array $a) => !in_array($a['id'], $gekoppeldeIds, true)
        ));

        return [
            'item' => $item,
            'logs' => TicketLogModel::forTicket($id),
            'tijdregistraties' => TicketTijdModel::forTicket($id),
            'tijdTotaal' => TicketTijdModel::sumForTicket($id),
            'gekoppeldeArtikelen' => $gekoppeld,
            'suggestiesArtikelen' => $suggesties,
        ];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input, isUpdate: false);
        $data['status'] = 'open';
        $data['aangemaakt_door_id'] = $currentUser['id'];

        return TicketModel::create($data);
    }

    public function update(int $id, array $input, array $currentUser): array
    {
        $huidig = $this->findOrFail($id, $currentUser);

        $data = TicketModel::alleenGewijzigdeVelden($huidig, $this->validate($input, isUpdate: true));
        if ($data !== []) {
            TicketModel::update($id, $data);
        }

        return TicketModel::findWithRelations($id);
    }

    public function delete(int $id, array $currentUser): void
    {
        $this->findOrFail($id, $currentUser);

        TicketModel::delete($id);
    }

    /**
     * Opmerking toevoegen en/of status wijzigen — zelfde regels als de bestaande
     * TicketLogController::store(): titel is verplicht om een opmerking op te slaan, een
     * statuswijziging staat los daarvan en wordt altijd gelogd als hij daadwerkelijk verandert.
     */
    public function addLog(int $id, array $input, array $currentUser): array
    {
        $ticket = $this->findOrFail($id, $currentUser);

        $titel = trim((string) ($input['titel'] ?? ''));
        $opmerking = trim((string) ($input['opmerking'] ?? ''));
        $opmerkingGeldig = $titel !== '';
        $nieuweStatus = (string) ($input['status'] ?? '');

        if ($nieuweStatus !== '' && !in_array($nieuweStatus, self::STATUSSEN, true)) {
            throw new ValidationException(['status' => ["Ongeldige status: {$nieuweStatus}."]]);
        }
        $statusGewijzigd = $nieuweStatus !== '' && $nieuweStatus !== $ticket['status'];

        if ($opmerking !== '' && !$opmerkingGeldig && !$statusGewijzigd) {
            throw new ValidationException(['titel' => ['Vul een titel in om deze opmerking op te slaan.']]);
        }

        if ($opmerkingGeldig || $statusGewijzigd) {
            $logOpmerking = 'Status bijgewerkt.';
            if ($opmerkingGeldig) {
                $logOpmerking = $opmerking;
            } elseif ($opmerking !== '') {
                // Titelloze tekst die is ingetikt terwijl alleen de status werd gewijzigd:
                // meesturen i.p.v. laten vallen.
                $logOpmerking = "Status bijgewerkt.\n\n{$opmerking}";
            }

            TicketLogModel::create([
                'ticket_id' => $id,
                'user_id' => $currentUser['id'],
                'titel' => $opmerkingGeldig ? $titel : null,
                'opmerking' => $logOpmerking,
                'status_van' => $statusGewijzigd ? $ticket['status'] : null,
                'status_naar' => $statusGewijzigd ? $nieuweStatus : null,
            ]);
        }

        $escalatieData = TicketModel::alleenGewijzigdeVelden($ticket, [
            'escalatie_nummer' => trim((string) ($input['escalatie_nummer'] ?? '')),
            'escalatie_instantie' => trim((string) ($input['escalatie_instantie'] ?? '')),
        ]);
        $ticketUpdate = $escalatieData;
        if ($statusGewijzigd) {
            $ticketUpdate['status'] = $nieuweStatus;
        }
        if ($ticketUpdate !== []) {
            TicketModel::update($id, $ticketUpdate);
        }

        return $this->find($id, $currentUser);
    }

    public function addTijd(int $id, array $input, array $currentUser): array
    {
        $this->findOrFail($id, $currentUser);

        $minuten = (int) ($input['minuten'] ?? 0);
        if ($minuten <= 0) {
            throw new ValidationException(['minuten' => ['Minuten moet een positief getal zijn.']]);
        }

        TicketTijdModel::create(['ticket_id' => $id, 'user_id' => $currentUser['id'], 'minuten' => $minuten]);

        return ['tijdregistraties' => TicketTijdModel::forTicket($id), 'tijdTotaal' => TicketTijdModel::sumForTicket($id)];
    }

    public function kennisbankKoppel(int $id, int $artikelId, array $currentUser): array
    {
        $this->findOrFail($id, $currentUser);
        if (KennisbankModel::find($artikelId) === null) {
            throw new NotFoundException("Kennisbankartikel {$artikelId} niet gevonden.");
        }

        TicketKennisbankModel::koppel($id, $artikelId);

        return ['gekoppeldeArtikelen' => TicketKennisbankModel::gekoppeld($id)];
    }

    public function kennisbankOntkoppel(int $id, int $artikelId, array $currentUser): array
    {
        $this->findOrFail($id, $currentUser);

        TicketKennisbankModel::ontkoppel($id, $artikelId);

        return ['gekoppeldeArtikelen' => TicketKennisbankModel::gekoppeld($id)];
    }

    /**
     * Haalt het ticket op en past direct de scope-check toe — gedeeld door elke methode die op een
     * bestaand ticket opereert (update/delete/addLog/addTijd/kennisbankKoppel/kennisbankOntkoppel),
     * zodat de not-found/forbidden-regels op één plek staan in plaats van per methode herhaald.
     *
     * @throws NotFoundException als het ticket niet bestaat
     * @throws ForbiddenException als de gebruiker buiten scope valt
     */
    private function findOrFail(int $id, array $currentUser): array
    {
        $item = TicketModel::find($id);
        if ($item === null) {
            throw new NotFoundException("Ticket {$id} niet gevonden.");
        }
        if (!$this->scopeAllowed($item, $currentUser)) {
            throw new ForbiddenException("Geen toegang tot ticket {$id}.");
        }

        return $item;
    }

    /** Zelfde regel als TicketController::scopeAllowed(): admin ziet alles, anderen alleen eigen afdeling/tickets. */
    private function scopeAllowed(array $item, array $currentUser): bool
    {
        if (($currentUser['rol'] ?? '') === 'admin') {
            return true;
        }

        $userId = (int) ($currentUser['id'] ?? 0);

        return ($item['afdeling_id'] ?? null) == ($currentUser['afdeling_id'] ?? null)
            || (int) ($item['behandelaar_id'] ?? 0) === $userId
            || (int) ($item['aangemaakt_door_id'] ?? 0) === $userId;
    }

    private function applyDefaultFilters(array $items, array $queryParams): array
    {
        $status = $queryParams['status'] ?? '';

        if ($status === 'alle') {
            return $items;
        }
        if ($status === '') {
            return array_values(array_filter($items, fn (array $t) => $t['status'] !== 'afgehandeld'));
        }

        return $items;
    }

    private function filterOptions(array $allItems): array
    {
        $afdelingen = array_values(array_unique(array_filter(array_column($allItems, 'afdeling_naam'))));
        sort($afdelingen);
        $behandelaars = array_values(array_unique(array_filter(array_column($allItems, 'behandelaar_naam'))));
        sort($behandelaars);

        return [
            'status' => [
                'alle' => 'Alle statussen', 'open' => 'Open', 'in_behandeling' => 'In behandeling',
                'wacht_op_info' => 'Wacht op info', 'afgehandeld' => 'Afgehandeld',
            ],
            'prioriteit' => ['laag' => 'Laag', 'normaal' => 'Normaal', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek'],
            'afdeling_naam' => array_combine($afdelingen, $afdelingen),
            'behandelaar_naam' => array_combine($behandelaars, $behandelaars),
        ];
    }

    private function statusCounts(array $allItems): array
    {
        $counts = ['alle' => count($allItems)];
        foreach (self::STATUSSEN as $status) {
            $counts[$status] = 0;
        }
        foreach ($allItems as $item) {
            if (isset($counts[$item['status']])) {
                $counts[$item['status']]++;
            }
        }
        $counts['actief'] = $counts['alle'] - $counts['afgehandeld'];

        return $counts;
    }

    /** @return array<string,mixed> */
    private function validate(array $input, bool $isUpdate): array
    {
        $errors = [];
        $titel = trim((string) ($input['titel'] ?? ''));
        $opdrachtgever = trim((string) ($input['opdrachtgever_naam'] ?? ''));

        if ($titel === '') {
            $errors['titel'][] = 'Taak (titel) is verplicht.';
        }
        if ($opdrachtgever === '') {
            $errors['opdrachtgever_naam'][] = 'Opdrachtgever is verplicht.';
        }
        $prioriteit = (string) ($input['prioriteit'] ?? 'normaal');
        if (!in_array($prioriteit, ['laag', 'normaal', 'hoog', 'kritiek'], true)) {
            $errors['prioriteit'][] = 'Ongeldige prioriteit.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $data = [
            'titel' => $titel,
            'omschrijving' => trim((string) ($input['omschrijving'] ?? '')),
            'opdrachtgever_naam' => $opdrachtgever,
            'categorie' => trim((string) ($input['categorie'] ?? '')) ?: 'Algemeen',
            'afdeling_id' => !empty($input['afdeling_id']) ? (int) $input['afdeling_id'] : null,
            'prioriteit' => $prioriteit,
            'impact' => (string) ($input['impact'] ?? 'Normaal'),
            'schatting_minuten' => !empty($input['schatting_minuten']) ? (int) $input['schatting_minuten'] : null,
            'deadline' => !empty($input['deadline']) ? $input['deadline'] : null,
            'behandelaar_id' => !empty($input['behandelaar_id']) ? (int) $input['behandelaar_id'] : null,
            'is_cyberrisico' => !empty($input['is_cyberrisico']) ? 1 : 0,
        ];

        return $data;
    }
}
