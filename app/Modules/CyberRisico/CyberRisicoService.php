<?php

namespace App\Modules\CyberRisico;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\CyberRisico\Models\CyberRisicoLogModel;
use App\Modules\CyberRisico\Models\CyberRisicoModel;

/**
 * Service/Business-laag voor cyberrisico's, zelfde patroon als TicketService/VerbeterpuntService
 * (scope-autorisatie: eigen afdeling, eigenaar of aanmaker). Let op: het echte datamodel kent geen
 * kans×impact-risicomatrix — enkel een "prioriteit"-enum (laag/middel/hoog/kritiek). De Lovable-
 * mockup (modules.cyberrisico.tsx) berekent een niveau uit kans*impact en toont een 5x5-matrix; die
 * cijfers bestaan hier niet, dus de matrix-visualisatie is weggelaten i.p.v. nagemaakt met
 * verzonnen scores — de KPI-rij (4 niveaus) blijft wel intact, want die is 1-op-1 de echte
 * prioriteit-verdeling.
 */
class CyberRisicoService
{
    private const STATUSSEN = ['nieuw', 'in_onderzoek', 'bevestigd', 'opgelost', 'geaccepteerd'];
    private const STATUS_LABELS = [
        'nieuw' => 'Nieuw', 'in_onderzoek' => 'In onderzoek', 'bevestigd' => 'Bevestigd',
        'opgelost' => 'Opgelost', 'geaccepteerd' => 'Geaccepteerd risico',
    ];
    private const PRIORITEITEN = ['laag', 'middel', 'hoog', 'kritiek'];

    /** @return array{items:array,pagination:array,prioriteitCounts:array,filterOptions:array} */
    public function list(array $currentUser, array $queryParams): array
    {
        $allItems = array_values(array_filter(
            CyberRisicoModel::allWithRelations(),
            fn (array $item) => $this->scopeAllowed($item, $currentUser)
        ));

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
            'prioriteitCounts' => $this->prioriteitCounts($allItems),
            'filterOptions' => ['status' => self::STATUS_LABELS],
        ];
    }

    public function find(int $id, array $currentUser): array
    {
        $item = $this->findOrFail($id, $currentUser);

        return ['item' => $item, 'logs' => CyberRisicoLogModel::forCyberRisico($id)];
    }

    public function addLog(int $id, array $input, array $currentUser): array
    {
        $this->findOrFail($id, $currentUser);

        $titel = trim((string) ($input['titel'] ?? ''));
        $omschrijving = trim((string) ($input['omschrijving'] ?? ''));

        $errors = [];
        if ($titel === '') {
            $errors['titel'][] = 'Titel is verplicht.';
        }
        if ($omschrijving === '') {
            $errors['omschrijving'][] = 'Omschrijving is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        CyberRisicoLogModel::create([
            'cyberrisico_id' => $id,
            'user_id' => $currentUser['id'],
            'titel' => $titel,
            'omschrijving' => $omschrijving,
        ]);

        return $this->find($id, $currentUser);
    }

    public function setStatus(int $id, string $status, array $currentUser): array
    {
        $this->findOrFail($id, $currentUser);

        if (!in_array($status, self::STATUSSEN, true)) {
            throw new ValidationException(['status' => ["Ongeldige status: {$status}."]]);
        }

        CyberRisicoModel::update($id, ['status' => $status]);

        return $this->find($id, $currentUser);
    }

    public function delete(int $id, array $currentUser): void
    {
        $this->findOrFail($id, $currentUser);
        CyberRisicoModel::delete($id);
    }

    private function findOrFail(int $id, array $currentUser): array
    {
        $item = CyberRisicoModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Cyberrisico {$id} niet gevonden.");
        }
        if (!$this->scopeAllowed($item, $currentUser)) {
            throw new ForbiddenException("Geen toegang tot cyberrisico {$id}.");
        }

        return $item;
    }

    /** Zelfde regel als CyberRisicoController::scopeAllowed(). */
    private function scopeAllowed(array $item, array $currentUser): bool
    {
        if (($currentUser['rol'] ?? '') === 'admin') {
            return true;
        }

        $userId = (int) ($currentUser['id'] ?? 0);

        return ($item['afdeling_id'] ?? null) == ($currentUser['afdeling_id'] ?? null)
            || (int) ($item['eigenaar_id'] ?? 0) === $userId
            || (int) ($item['aangemaakt_door_id'] ?? 0) === $userId;
    }

    /** Zelfde regel als CyberRisicoController::applyDefaultFilters(): zonder expliciete statusfilter geen opgeloste/geaccepteerde risico's tonen. */
    private function applyDefaultFilters(array $items, array $queryParams): array
    {
        if (($queryParams['status'] ?? '') === '') {
            return array_values(array_filter($items, fn (array $r) => !in_array($r['status'], ['opgelost', 'geaccepteerd'], true)));
        }

        return $items;
    }

    private function prioriteitCounts(array $allItems): array
    {
        $counts = array_fill_keys(self::PRIORITEITEN, 0);
        foreach ($allItems as $item) {
            if (isset($counts[$item['prioriteit']])) {
                $counts[$item['prioriteit']]++;
            }
        }

        return $counts;
    }
}
