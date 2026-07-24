<?php

namespace App\Modules\Verbeterpunt;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Verbeterpunt\Models\VerbeterpuntLogModel;
use App\Modules\Verbeterpunt\Models\VerbeterpuntModel;

/**
 * Service/Business-laag voor verbeterpunten: validatie, scope-autorisatie en de status-flow
 * (nieuw → in_overweging → goedgekeurd/afgewezen → uitgevoerd). Kent geen HTTP-concepten — de
 * API-laag (App\Api\V1\VerbeterpuntenApiController) parsed de request en roept dit aan.
 */
class VerbeterpuntService
{
    private const STATUSSEN = ['nieuw', 'in_overweging', 'goedgekeurd', 'afgewezen', 'uitgevoerd'];

    /** @return array{items:array,pagination:array,statusCounts:array} */
    public function list(array $currentUser, array $queryParams): array
    {
        $allItems = array_values(array_filter(
            VerbeterpuntModel::allWithRelations(),
            fn (array $item) => $this->scopeAllowed($item, $currentUser)
        ));

        $items = TableQuery::apply($allItems, $queryParams, 'titel');
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
        ];
    }

    public function find(int $id, array $currentUser): array
    {
        $item = $this->findScoped($id, $currentUser);

        return [
            'item' => $item,
            'logs' => VerbeterpuntLogModel::forVerbeterpunt($id),
        ];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input);
        $data['status'] = 'nieuw';
        $data['ingediend_door_id'] = $currentUser['id'];

        return VerbeterpuntModel::create($data);
    }

    public function update(int $id, array $input, array $currentUser): array
    {
        $huidig = $this->findScoped($id, $currentUser);

        $data = VerbeterpuntModel::alleenGewijzigdeVelden($huidig, $this->validate($input));
        if ($data !== []) {
            VerbeterpuntModel::update($id, $data);
        }

        return $this->find($id, $currentUser);
    }

    public function delete(int $id, array $currentUser): void
    {
        $this->findScoped($id, $currentUser);
        VerbeterpuntModel::delete($id);
    }

    /** Opmerking toevoegen en/of status wijzigen — zelfde regels als VerbeterpuntLogController::store(). */
    public function addLog(int $id, array $input, array $currentUser): array
    {
        $item = $this->findScoped($id, $currentUser);

        $titel = trim((string) ($input['titel'] ?? ''));
        $opmerking = trim((string) ($input['opmerking'] ?? ''));
        $opmerkingGeldig = $titel !== '';
        $nieuweStatus = (string) ($input['status'] ?? '');

        if ($nieuweStatus !== '' && !in_array($nieuweStatus, self::STATUSSEN, true)) {
            throw new ValidationException(['status' => ["Ongeldige status: {$nieuweStatus}."]]);
        }
        $statusGewijzigd = $nieuweStatus !== '' && $nieuweStatus !== $item['status'];

        if ($opmerking !== '' && !$opmerkingGeldig) {
            throw new ValidationException(['titel' => ['Vul een titel in om deze opmerking op te slaan.']]);
        }

        if ($opmerkingGeldig || $statusGewijzigd) {
            VerbeterpuntLogModel::create([
                'verbeterpunt_id' => $id,
                'user_id' => $currentUser['id'],
                'titel' => $opmerkingGeldig ? $titel : null,
                'opmerking' => $opmerkingGeldig ? $opmerking : 'Status bijgewerkt.',
                'status_van' => $statusGewijzigd ? $item['status'] : null,
                'status_naar' => $statusGewijzigd ? $nieuweStatus : null,
            ]);
        }

        if ($statusGewijzigd) {
            VerbeterpuntModel::update($id, ['status' => $nieuweStatus]);
        }

        return $this->find($id, $currentUser);
    }

    private function findScoped(int $id, array $currentUser): array
    {
        $item = VerbeterpuntModel::find($id);
        if ($item === null) {
            throw new NotFoundException("Verbeterpunt {$id} niet gevonden.");
        }
        if (!$this->scopeAllowed($item, $currentUser)) {
            throw new ForbiddenException("Geen toegang tot verbeterpunt {$id}.");
        }

        return $item;
    }

    /** Zelfde regel als VerbeterpuntController::scopeAllowed(). */
    private function scopeAllowed(array $item, array $currentUser): bool
    {
        if (($currentUser['rol'] ?? '') === 'admin') {
            return true;
        }

        $userId = (int) ($currentUser['id'] ?? 0);

        return ($item['afdeling_id'] ?? null) == ($currentUser['afdeling_id'] ?? null)
            || (int) ($item['ingediend_door_id'] ?? 0) === $userId;
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

    private function validate(array $input): array
    {
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

        return [
            'titel' => $titel,
            'omschrijving' => $omschrijving,
            'categorie' => trim((string) ($input['categorie'] ?? '')) ?: 'Algemeen',
            'afdeling_id' => !empty($input['afdeling_id']) ? (int) $input['afdeling_id'] : null,
        ];
    }
}
