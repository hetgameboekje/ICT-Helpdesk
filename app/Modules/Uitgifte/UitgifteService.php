<?php

namespace App\Modules\Uitgifte;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Modules\Voorraad\Models\VoorraadItemModel;
use App\Shared\AssetScan\BarcodeScanParser;
use App\Shared\AssetScan\BarcodeTemplateMatcher;

/**
 * Service/Business-laag voor uitgiften: elke uitgifte is een dunne, transactionele koppeling naar
 * een geserialiseerd voorraad-item (barcode-scan), geen zelfstandig record — zie
 * UitgifteController::store()/retour() (het patroon dat hier 1-op-1 is overgenomen). Geen
 * scope-autorisatie: elk ingelogd account met leesrecht ziet alle uitgiften.
 */
class UitgifteService
{
    /** @return array{items:array,pagination:array,kpis:array} */
    public function list(array $queryParams): array
    {
        $allItems = UitgifteModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'medewerker_naam');
        $pagination = TableQuery::paginate($items, $queryParams);

        $openstaand = count(array_filter($allItems, fn (array $u) => $u['status'] === 'uitgegeven'));

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
                'openstaand' => $openstaand,
                'geretourneerd' => count($allItems) - $openstaand,
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = UitgifteModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Uitgifte {$id} niet gevonden.");
        }

        return ['item' => $item];
    }

    /**
     * Zelfde flow als UitgifteController::store(), uitgebreid met herkenning van een fabrieks-
     * apparaatscan (serienummer + product-ID, komma-gescheiden — zie App\Shared\AssetScan): het
     * "barcode"-veld blijft één invoerveld (scan, typ, of zoek op naam, zie create.php/
     * uitgiften-index.js), maar wordt hier eerst door BarcodeScanParser gehaald om te bepalen of het
     * onze eigen barcode is (bestaand gedrag, ongewijzigd) of een device-candidate-scan.
     *
     * Bij een device-candidate-scan: eerst op serienummer zoeken (nooit blind aannemen dat het
     * apparaat nieuw is, zie business rules) — bestaat het al en staat het als 'uitgegeven'
     * geregistreerd, dan wordt de aanvraag geweigerd (voorkomt een stil dubbele-uitgifte-record i.p.v.
     * de bestaande te hergebruiken); bestaat het nog niet, dan wordt het aangemaakt onder het door de
     * gebruiker bevestigde apparaattype (`bevestigd_asset_type`, standaard 'Laptop' — nooit blind
     * vastgezet zonder gebruikersbevestiging, zie AssetEnrichmentService/de suggestie-UI die hieraan
     * voorafgaat via POST /api/v1/asset-scan).
     */
    public function create(array $input, array $currentUser): int
    {
        $barcode = trim((string) ($input['barcode'] ?? ''));
        $medewerkerNaam = trim((string) ($input['medewerker_naam'] ?? ''));

        $errors = [];
        if ($barcode === '') {
            $errors['barcode'][] = 'Barcode is verplicht.';
        }
        if ($medewerkerNaam === '') {
            $errors['medewerker_naam'][] = 'Naam is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $scan = BarcodeScanParser::parse($barcode);
        $onbekend = false;

        if ($scan['device_candidate']) {
            $item = VoorraadItemModel::findBySerienummer($scan['serial_number']);

            if ($item !== null && $item['status'] === 'uitgegeven') {
                throw new ValidationException(['barcode' => [
                    "Dit apparaat (serienummer {$scan['serial_number']}) staat al als uitgegeven geregistreerd. Neem eerst retour via de bestaande uitgifte.",
                ]]);
            }

            if ($item === null) {
                $bevestigdAssetType = trim((string) ($input['bevestigd_asset_type'] ?? '')) ?: (string) $scan['suggested_asset_type'];
                $itemId = VoorraadItemModel::createVoorApparaatKandidaat(
                    $scan['serial_number'],
                    $scan['product_id'],
                    $scan['description'],
                    $bevestigdAssetType,
                    $currentUser['id']
                );
                $item = VoorraadItemModel::findWithRelations($itemId);
                $onbekend = true;
            }
        } else {
            // Kale token zonder komma: kan onze eigen barcode zijn (bestaand gedrag), maar ook een
            // eerder al via een barcode-sjabloon geregistreerd fabrikant-serienummer (bv. een
            // toetsenbord/monitor) — dat item staat dan onder zijn éígen "TYPECODE-serienummer"-
            // barcode, dus zoek ook op serienummer, anders zou een herscan hem niet vinden en per
            // ongeluk een tweede registratie proberen aan te maken voor hetzelfde fysieke apparaat.
            $item = VoorraadItemModel::findAvailableByBarcode($barcode)
                ?? VoorraadItemModel::findBySerienummer($barcode);

            if ($item !== null && $item['status'] === 'uitgegeven') {
                throw new ValidationException(['barcode' => [
                    "Dit item ({$barcode}) staat al als uitgegeven geregistreerd. Neem eerst retour via de bestaande uitgifte.",
                ]]);
            }

            if ($item === null) {
                // Onbekende kale token: eerst tegen de beheerbare barcode-sjablonen leggen
                // (BarcodeTemplateMatcher) vóór de generieke 'Overig'-vangnet — zo krijgt een
                // herkend fabrikant-serienummer (keyboard/monitor) meteen het juiste type i.p.v.
                // in de ongetypeerde 'Overig'-bak te belanden.
                $template = BarcodeTemplateMatcher::match($barcode);

                if ($template !== null) {
                    $bevestigdAssetType = trim((string) ($input['bevestigd_asset_type'] ?? '')) ?: ($template['voorraad_type_naam'] ?? 'Overig');
                    $itemId = VoorraadItemModel::createVoorApparaatKandidaat(
                        $barcode,
                        null,
                        $template['omschrijving'] ?: $template['naam'],
                        $bevestigdAssetType,
                        $currentUser['id']
                    );
                } else {
                    $itemId = VoorraadItemModel::createOnbekend($barcode, $currentUser['id']);
                }

                $item = VoorraadItemModel::findWithRelations($itemId);
                $onbekend = true;
            }
        }

        $id = UitgifteModel::create([
            'voorraad_item_id' => $item['id'],
            'medewerker_naam' => $medewerkerNaam,
            'uitgegeven_op' => !empty($input['uitgegeven_op']) ? $input['uitgegeven_op'] : date('Y-m-d'),
            'opmerking' => trim((string) ($input['opmerking'] ?? '')) ?: null,
            'toestemming_manager' => !empty($input['toestemming_manager']) ? 1 : 0,
            'uitgegeven_door_id' => $currentUser['id'],
        ]);

        if (!$onbekend) {
            VoorraadItemModel::setStatus((int) $item['id'], 'uitgegeven');
        }

        return $id;
    }

    /** Zelfde flow als UitgifteController::retour(). */
    public function retour(int $id, array $input): array
    {
        $uitgifte = UitgifteModel::findWithRelations($id);
        if ($uitgifte === null) {
            throw new NotFoundException("Uitgifte {$id} niet gevonden.");
        }

        $opmerking = trim((string) ($input['opmerking'] ?? '')) ?: null;
        $resultaat = ($input['resultaat'] ?? '') === 'afgeschreven' ? 'afgeschreven' : 'op_voorraad';

        UitgifteModel::setTeruggegeven($id, date('Y-m-d'), $opmerking);
        VoorraadItemModel::setStatus((int) $uitgifte['voorraad_item_id'], $resultaat);

        return $this->find($id);
    }
}
