<?php

namespace App\Modules\Voorraad;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Modules\Voorraad\Models\VoorraadItemModel;
use App\Modules\Voorraad\Models\VoorraadTypeModel;

/**
 * Service/Business-laag voor voorraad. create()/update() dekken dezelfde serienummer-uniekheid/
 * barcode-opbouw als VoorraadController::store()/update(), maar zonder de DxDiag-bestandsupload —
 * dat is een multipart-bestand, niet iets dat via een JSON-body past. Een DxDiag-rapport toevoegen
 * blijft daarom op het oude formulier (/voorraad/{id}/edit); create/update van de kernvelden (type,
 * variant, serienummer, locatie, opmerking, aantal-bulk bij create) is wel via de API beschikbaar
 * voor andere surfaces (bv. mobiel/CLI barcode-scannen).
 */
class VoorraadService
{
    /** @return array{items:array,pagination:array,kpis:array} */
    public function list(array $queryParams): array
    {
        $allItems = VoorraadItemModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'barcode');
        $pagination = TableQuery::paginate($items, $queryParams);

        $statusCounts = ['op_voorraad' => 0, 'uitgegeven' => 0, 'afgeschreven' => 0];
        foreach ($allItems as $item) {
            if (isset($statusCounts[$item['status']])) {
                $statusCounts[$item['status']]++;
            }
        }

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'kpis' => [
                'aantal' => count($allItems),
                'opVoorraad' => $statusCounts['op_voorraad'],
                'uitgegeven' => $statusCounts['uitgegeven'],
                'afgeschreven' => $statusCounts['afgeschreven'],
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = VoorraadItemModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Voorraaditem {$id} niet gevonden.");
        }

        return [
            'item' => $item,
            'uitgiften' => UitgifteModel::forVoorraadItem($id),
        ];
    }

    /** @return array{ids:int[],barcode:string} het laatst gebruikte barcode + alle aangemaakte id's. */
    public function create(array $input, array $currentUser): array
    {
        $type = VoorraadTypeModel::find((int) ($input['type_id'] ?? 0));
        if ($type === null) {
            throw new ValidationException(['type_id' => ['Kies een geldig type.']]);
        }

        $variant = trim((string) ($input['variant'] ?? '')) ?: null;
        $locatie = trim((string) ($input['locatie'] ?? '')) ?: null;
        $opmerking = trim((string) ($input['opmerking'] ?? '')) ?: null;
        $aantal = max(1, (int) ($input['aantal'] ?? 1));

        $serienummers = array_values(array_filter(array_map(
            fn ($s) => trim((string) $s) ?: null,
            is_array($input['serienummers'] ?? null) ? $input['serienummers'] : []
        ), fn (?string $s) => $s !== null));

        if ($serienummers !== [] && count($serienummers) !== $aantal) {
            throw new ValidationException(['serienummers' => ["Vul voor alle {$aantal} items een serienummer in, of laat ze allemaal leeg."]]);
        }
        if (count($serienummers) !== count(array_unique($serienummers))) {
            throw new ValidationException(['serienummers' => ['Je hebt hetzelfde serienummer meerdere keren ingevuld — een serienummer is uniek per stuk.']]);
        }
        foreach ($serienummers as $serienummer) {
            if (VoorraadItemModel::serienummerExists($serienummer)) {
                throw new ValidationException(['serienummers' => ["Serienummer {$serienummer} bestaat al bij een ander item."]]);
            }
        }

        $ids = [];
        $barcode = '';
        for ($i = 0; $i < $aantal; $i++) {
            $serienummer = $serienummers[$i] ?? null;
            $barcode = self::buildBarcode($type['code'], $variant, $serienummer);

            $ids[] = VoorraadItemModel::create([
                'type_id' => (int) $type['id'],
                'variant' => $variant,
                'serienummer' => $serienummer,
                'barcode' => $barcode,
                'locatie' => $locatie,
                'opmerking' => $opmerking,
                'aangemaakt_door_id' => $currentUser['id'],
            ]);
        }

        return ['ids' => $ids, 'barcode' => $barcode];
    }

    public function update(int $id, array $input): array
    {
        if (VoorraadItemModel::find($id) === null) {
            throw new NotFoundException("Voorraaditem {$id} niet gevonden.");
        }

        $type = VoorraadTypeModel::find((int) ($input['type_id'] ?? 0));
        if ($type === null) {
            throw new ValidationException(['type_id' => ['Kies een geldig type.']]);
        }

        $variant = trim((string) ($input['variant'] ?? '')) ?: null;
        $serienummer = trim((string) ($input['serienummer'] ?? '')) ?: null;
        $locatie = trim((string) ($input['locatie'] ?? '')) ?: null;
        $opmerking = trim((string) ($input['opmerking'] ?? '')) ?: null;

        if ($serienummer !== null && VoorraadItemModel::serienummerExists($serienummer, $id)) {
            throw new ValidationException(['serienummer' => ["Serienummer {$serienummer} bestaat al bij een ander item."]]);
        }

        VoorraadItemModel::update($id, [
            'type_id' => (int) $type['id'],
            'variant' => $variant,
            'serienummer' => $serienummer,
            'barcode' => self::buildBarcode($type['code'], $variant, $serienummer),
            'locatie' => $locatie,
            'opmerking' => $opmerking,
        ]);

        return $this->find($id);
    }

    /** Zelfde opbouw als VoorraadController::buildBarcode(). */
    private static function buildBarcode(string $typeCode, ?string $variant, ?string $serienummer): string
    {
        if ($serienummer !== null && $serienummer !== '') {
            return strtoupper($typeCode . '-' . preg_replace('/[^A-Za-z0-9]/', '', $serienummer));
        }

        $suffix = ($variant !== null && $variant !== '')
            ? '-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $variant))
            : '';

        return strtoupper($typeCode) . $suffix;
    }
}
