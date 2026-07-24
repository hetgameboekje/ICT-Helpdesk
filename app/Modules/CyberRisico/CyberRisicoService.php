<?php

namespace App\Modules\CyberRisico;

use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\CyberRisico\Models\CyberRisicoLogModel;
use App\Modules\CyberRisico\Models\CyberRisicoModel;

/**
 * Service/Business-laag voor cyberrisico's: validatie, scope-autorisatie (afdeling/eigenaar/
 * aanmaker) en de vaste status/prioriteit/categorie-enums. Kent geen HTTP-concepten — de API-laag
 * (App\Api\V1\CyberRisicosApiController) parsed de request en roept dit aan.
 */
class CyberRisicoService
{
    private const STATUSSEN = ['nieuw', 'in_onderzoek', 'bevestigd', 'opgelost', 'geaccepteerd'];
    private const GESLOTEN_STATUSSEN = ['opgelost', 'geaccepteerd'];
    private const PRIORITEITEN = ['laag', 'middel', 'hoog', 'kritiek'];
    private const CATEGORIEEN = [
        'fysieke_toegang', 'social_engineering', 'onveilige_opslag', 'papieren_informatie',
        'device_exposure', 'overig',
    ];

    /** @return array{items:array,pagination:array,statusCounts:array} */
    public function list(array $currentUser, array $queryParams): array
    {
        $allItems = array_values(array_filter(
            CyberRisicoModel::allWithRelations(),
            fn (array $item) => $this->scopeAllowed($item, $currentUser)
        ));

        $status = $queryParams['status'] ?? '';
        $items = $status === ''
            ? array_values(array_filter($allItems, fn (array $r) => !in_array($r['status'], self::GESLOTEN_STATUSSEN, true)))
            : $allItems;

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
            'statusCounts' => $this->prioriteitCounts($allItems),
        ];
    }

    public function find(int $id, array $currentUser): array
    {
        $item = $this->findScoped($id, $currentUser);

        return [
            'item' => $item,
            'logs' => CyberRisicoLogModel::forCyberRisico($id),
        ];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input);
        $data['status'] = 'nieuw';
        $data['aangemaakt_door_id'] = $currentUser['id'];

        return CyberRisicoModel::create($data);
    }

    public function update(int $id, array $input, array $currentUser): array
    {
        $huidig = $this->findScoped($id, $currentUser);

        $data = $this->validate($input);
        if (in_array($input['status'] ?? '', self::STATUSSEN, true)) {
            $data['status'] = $input['status'];
        }

        $data = CyberRisicoModel::alleenGewijzigdeVelden($huidig, $data);
        if ($data !== []) {
            CyberRisicoModel::update($id, $data);
        }

        return $this->find($id, $currentUser);
    }

    public function delete(int $id, array $currentUser): void
    {
        $this->findScoped($id, $currentUser);
        CyberRisicoModel::delete($id);
    }

    public function addLog(int $id, array $input, array $currentUser): array
    {
        $this->findScoped($id, $currentUser);

        $titel = trim((string) ($input['titel'] ?? ''));
        $omschrijving = trim((string) ($input['omschrijving'] ?? ''));
        if ($titel === '' || $omschrijving === '') {
            throw new ValidationException([
                'titel' => $titel === '' ? ['Titel is verplicht.'] : [],
                'omschrijving' => $omschrijving === '' ? ['Omschrijving is verplicht.'] : [],
            ]);
        }

        CyberRisicoLogModel::create([
            'cyberrisico_id' => $id,
            'user_id' => $currentUser['id'],
            'titel' => $titel,
            'omschrijving' => $omschrijving,
        ]);

        return $this->find($id, $currentUser);
    }

    private function findScoped(int $id, array $currentUser): array
    {
        $item = CyberRisicoModel::find($id);
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

    private function prioriteitCounts(array $allItems): array
    {
        $counts = ['alle' => count($allItems)];
        foreach (self::PRIORITEITEN as $p) {
            $counts[$p] = 0;
        }
        foreach ($allItems as $item) {
            if (isset($counts[$item['prioriteit']])) {
                $counts[$item['prioriteit']]++;
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
            'categorie' => in_array($input['categorie'] ?? '', self::CATEGORIEEN, true) ? $input['categorie'] : 'overig',
            'prioriteit' => in_array($input['prioriteit'] ?? '', self::PRIORITEITEN, true) ? $input['prioriteit'] : 'middel',
            'locatie' => trim((string) ($input['locatie'] ?? '')) ?: null,
            'gemeld_door' => trim((string) ($input['gemeld_door'] ?? '')) ?: null,
            'afdeling_id' => !empty($input['afdeling_id']) ? (int) $input['afdeling_id'] : null,
            'eigenaar_id' => !empty($input['eigenaar_id']) ? (int) $input['eigenaar_id'] : null,
            'datum_geconstateerd' => !empty($input['datum_geconstateerd']) ? $input['datum_geconstateerd'] : null,
            'datum_gemeld' => !empty($input['datum_gemeld']) ? $input['datum_gemeld'] : null,
            'oplossingsadvies' => trim((string) ($input['oplossingsadvies'] ?? '')) ?: null,
            'bewijs_notities' => trim((string) ($input['bewijs_notities'] ?? '')) ?: null,
            'is_gevoelig' => !empty($input['is_gevoelig']) ? 1 : 0,
        ];
    }
}
