<?php

namespace App\Modules\Medewerker;

use App\Core\Exceptions\NotFoundException;
use App\Core\TableQuery;
use App\Modules\Device\Models\DeviceModel;
use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Modules\Schijfgebruik\Models\SchijfgebruikDeviceModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Modules\Uitgifte\Models\UitgifteModel;

/**
 * Service/Business-laag voor medewerkers. Let op t.o.v. de Lovable-mockup
 * (modules.medewerker.tsx): het echte status-veld kent alleen actief/inactief, geen "verlof" — de
 * KPI-rij en statusindicator zijn daarop aangepast. De hiërarchie (manager_id/is_keyuser) bestaat
 * al echt (zie CLAUDE.md-roadmap, MedewerkerController::hierarchie()) en wordt hier per
 * geselecteerde medewerker afgeleid i.p.v. verzonnen. "In behandeling nu" is een echte, live
 * telling van open tickets op naam van de gekoppelde login (behandelaar_id), niet mockdata.
 */
class MedewerkerService
{
    /** @return array{items:array,pagination:array,stats:array} */
    public function list(array $queryParams): array
    {
        $allItems = MedewerkerModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'achternaam');
        $pagination = TableQuery::paginate($items, $queryParams);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'stats' => [
                'totaal' => count($allItems),
                'keyusers' => count(array_filter($allItems, fn (array $m) => !empty($m['is_keyuser']))),
                'actief' => count(array_filter($allItems, fn (array $m) => $m['status'] === 'actief')),
                'inactief' => count(array_filter($allItems, fn (array $m) => $m['status'] === 'inactief')),
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = MedewerkerModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Medewerker {$id} niet gevonden.");
        }

        $naam = trim($item['voornaam'] . ' ' . $item['achternaam']);
        $hostnames = array_filter(array_map('trim', explode(',', $item['apparaat_hostnames'] ?? '')));
        $schijfgebruik = array_values(array_filter(array_map(
            fn (string $hostnaam) => SchijfgebruikDeviceModel::findByNaam($hostnaam),
            $hostnames
        )));

        $alleMedewerkers = MedewerkerModel::allWithRelations();
        $team = array_values(array_filter($alleMedewerkers, fn (array $m) => $m['manager_id'] === $item['id']));

        return [
            'item' => $item,
            'team' => $team,
            'inBehandeling' => $item['user_id'] !== null ? $this->tellenInBehandeling((int) $item['user_id']) : 0,
            'uitgiften' => UitgifteModel::forMedewerkerNaam($naam),
            'apparaten' => DeviceModel::forMedewerker($id),
            'schijfgebruik' => $schijfgebruik,
        ];
    }

    public function delete(int $id): void
    {
        if (MedewerkerModel::find($id) === null) {
            throw new NotFoundException("Medewerker {$id} niet gevonden.");
        }

        MedewerkerModel::delete($id);
    }

    private function tellenInBehandeling(int $userId): int
    {
        $tickets = TicketModel::allWithRelations();

        return count(array_filter(
            $tickets,
            fn (array $t) => (int) ($t['behandelaar_id'] ?? 0) === $userId && $t['status'] === 'in_behandeling'
        ));
    }
}
