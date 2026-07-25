<?php

namespace App\Modules\Script;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Script\Models\ScriptModel;

/**
 * Service/Business-laag voor scripts. Let op t.o.v. de Lovable-mockup (modules.script.tsx): er is
 * geen uitvoeringsgeschiedenis in dit systeem — scripts zijn een kopieer-en-plak-bibliotheek
 * (titel/omschrijving/type/inhoud/auteur), geen remote-executie. Daarom geen "laatst uitgevoerd"/
 * status ok-fout-nooit-KPI's en geen "Uitvoeren"-knop (zou niets doen) — vervangen door een echte
 * verdeling per script-type en de bestaande "Kopiëren"-actie.
 */
class ScriptService
{
    private const TYPE_LABELS = ['powershell' => 'PowerShell', 'batch' => 'Batch', 'bash' => 'Bash', 'overig' => 'Overig'];

    /** @return array{items:array,pagination:array,typeCounts:array} */
    public function list(array $queryParams): array
    {
        $allItems = ScriptModel::allWithRelations();
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
            'typeCounts' => $this->typeCounts($allItems),
        ];
    }

    public function find(int $id): array
    {
        $item = ScriptModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Script {$id} niet gevonden.");
        }

        return ['item' => $item];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input);
        $data['auteur_id'] = $currentUser['id'];

        return ScriptModel::create($data);
    }

    public function update(int $id, array $input): array
    {
        if (ScriptModel::find($id) === null) {
            throw new NotFoundException("Script {$id} niet gevonden.");
        }

        ScriptModel::update($id, $this->validate($input));

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (ScriptModel::find($id) === null) {
            throw new NotFoundException("Script {$id} niet gevonden.");
        }

        ScriptModel::delete($id);
    }

    private function validate(array $input): array
    {
        $titel = trim((string) ($input['titel'] ?? ''));
        $inhoud = trim((string) ($input['inhoud'] ?? ''));

        $errors = [];
        if ($titel === '') {
            $errors['titel'][] = 'Titel is verplicht.';
        }
        if ($inhoud === '') {
            $errors['inhoud'][] = 'Inhoud is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $type = (string) ($input['type'] ?? '');

        return [
            'titel' => $titel,
            'omschrijving' => trim((string) ($input['omschrijving'] ?? '')) ?: null,
            'type' => in_array($type, array_keys(self::TYPE_LABELS), true) ? $type : 'overig',
            'inhoud' => $inhoud,
        ];
    }

    private function typeCounts(array $allItems): array
    {
        $counts = array_fill_keys(array_keys(self::TYPE_LABELS), 0);
        $counts['alle'] = count($allItems);
        foreach ($allItems as $item) {
            $type = $item['type'] ?? 'overig';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
        }

        return $counts;
    }
}
