<?php

namespace App\Modules\HardwareUitgave;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\HardwareUitgave\Models\HardwareUitgaveLogModel;
use App\Modules\HardwareUitgave\Models\HardwareUitgaveModel;

/**
 * Service/Business-laag voor hardware-uitgaven. Let op: dit is een aankoopaanvraag-tracker
 * (omschrijving, leverancier, bedrag, aankoopdatum, status aangevraagd→goedgekeurd/afgekeurd→
 * besteld→geleverd) — niet het "hardware aan medewerker uitgeven met retour"-concept dat de
 * Lovable-mockup (modules.hardware-uitgaven.tsx, gebaseerd op de generieke uitgiften-mockdata)
 * suggereert. Dat laatste bestaat al als een apart, echt module: App\Modules\Uitgifte. Deze
 * Service dekt lijst/detail/statuswijziging/verwijderen; aanmaken/bewerken blijft op de bestaande
 * server-rendered formulieren.
 */
class HardwareUitgaveService
{
    private const STATUSSEN = ['aangevraagd', 'goedgekeurd', 'afgekeurd', 'besteld', 'geleverd'];
    private const STATUS_LABELS = [
        'aangevraagd' => 'Aangevraagd', 'goedgekeurd' => 'Goedgekeurd', 'afgekeurd' => 'Afgekeurd',
        'besteld' => 'Besteld', 'geleverd' => 'Geleverd',
    ];

    /** @return array{items:array,pagination:array,statusCounts:array} */
    public function list(array $queryParams): array
    {
        $allItems = HardwareUitgaveModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'omschrijving');
        $pagination = TableQuery::paginate($items, $queryParams);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'statusCounts' => $this->statusCounts($allItems),
            'filterOptions' => ['status' => self::STATUS_LABELS],
        ];
    }

    public function find(int $id): array
    {
        $item = HardwareUitgaveModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Hardware-uitgave {$id} niet gevonden.");
        }

        return ['item' => $item, 'logs' => HardwareUitgaveLogModel::forHardwareUitgave($id)];
    }

    /** @param array{id:int} $currentUser */
    public function setStatus(int $id, string $status, array $currentUser): array
    {
        $bestaand = HardwareUitgaveModel::find($id);
        if ($bestaand === null) {
            throw new NotFoundException("Hardware-uitgave {$id} niet gevonden.");
        }
        if (!in_array($status, self::STATUSSEN, true)) {
            throw new ValidationException(['status' => ["Ongeldige status: {$status}."]]);
        }

        if ($bestaand['status'] !== $status) {
            HardwareUitgaveModel::update($id, ['status' => $status]);
            HardwareUitgaveLogModel::create([
                'hardware_uitgave_id' => $id,
                'user_id' => $currentUser['id'],
                'status_van' => $bestaand['status'],
                'status_naar' => $status,
            ]);
        }

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (HardwareUitgaveModel::find($id) === null) {
            throw new NotFoundException("Hardware-uitgave {$id} niet gevonden.");
        }

        HardwareUitgaveModel::delete($id);
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

        return $counts;
    }
}
