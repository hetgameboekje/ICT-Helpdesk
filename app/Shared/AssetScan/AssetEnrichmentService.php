<?php

namespace App\Shared\AssetScan;

use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Shared\AssetScan\Models\DeviceScanModel;

/**
 * Orchestreert BarcodeScanParser (parsing) + AssetMatchService (zoeken) tot één suggestieblok voor
 * het uitgifte-/voorraadformulier, en logt elke scan naar device_scans (audit). Geen enkel veld in
 * het resultaat is definitief — de UI laat de gebruiker suggested_asset_type/suggested_employee
 * altijd bevestigen of overschrijven vóórdat er iets in uitgiften/voorraad_items belandt.
 */
class AssetEnrichmentService
{
    /**
     * @return array{
     *     raw_scan_value:string, serial_number:?string, product_id:?string, description:?string,
     *     extra_parts:string[], device_candidate:bool,
     *     suggested_asset_type:?string, match_source:?string, match_confidence:?string,
     *     match:?array, suggested_employee:?array, last_logged_on_user:?string, warning:?string
     * }
     */
    public function analyseer(string $rawInput, string $context, ?int $gebruiktDoorId): array
    {
        $scan = BarcodeScanParser::parse($rawInput);

        $match = $scan['serial_number'] !== null
            ? AssetMatchService::findBySerienummer($scan['serial_number'])
            : null;

        $warning = null;
        $suggestedEmployee = null;
        $lastLoggedOnUser = null;

        if ($match !== null) {
            $voorraadItem = $match['voorraad_item'];
            $schijfgebruikDevice = $match['schijfgebruik_device'];

            if ($voorraadItem !== null && $voorraadItem['status'] === 'uitgegeven') {
                $warning = 'Dit apparaat staat momenteel als "uitgegeven" geregistreerd.';
            }

            // Rang 1 voor de medewerker-suggestie: de laatst bekende uitgifte in ons eigen register
            // (alleen relevant zolang het item nog niet geretourneerd is).
            if ($voorraadItem !== null) {
                $laatsteUitgifte = UitgifteModel::forVoorraadItem((int) $voorraadItem['id'])[0] ?? null;
                if ($laatsteUitgifte !== null && $laatsteUitgifte['status'] === 'uitgegeven') {
                    $suggestedEmployee = [
                        'id' => null,
                        'naam' => $laatsteUitgifte['medewerker_naam'],
                        'bron' => 'Laatste uitgifte (intern register)',
                    ];
                }
            }

            // Rang 2: de medewerker die in de NinjaOne/RMM-import aan dit apparaat gekoppeld staat.
            if ($suggestedEmployee === null && $schijfgebruikDevice !== null && !empty($schijfgebruikDevice['medewerker_id'])) {
                $medewerker = MedewerkerModel::findWithRelations((int) $schijfgebruikDevice['medewerker_id']);
                if ($medewerker !== null) {
                    $suggestedEmployee = [
                        'id' => $medewerker['id'],
                        'naam' => trim($medewerker['voornaam'] . ' ' . $medewerker['achternaam']),
                        'afdeling' => $medewerker['afdeling_naam'],
                        'bron' => 'NinjaOne — gekoppelde medewerker (schijfgebruik-import)',
                    ];
                }
            }

            // "Last logged on user" is het ruwe NinjaOne-veld — een suggestie, geen bevestigde
            // eigenaar (kan een ander account zijn dan de gekoppelde medewerker hierboven).
            if ($schijfgebruikDevice !== null && !empty($schijfgebruikDevice['laatste_login'])) {
                $lastLoggedOnUser = $schijfgebruikDevice['laatste_login'];
            }
        }

        $result = [
            'raw_scan_value' => $scan['raw_scan_value'],
            'serial_number' => $scan['serial_number'],
            'product_id' => $scan['product_id'],
            'description' => $scan['description'],
            'extra_parts' => $scan['extra_parts'],
            'device_candidate' => $scan['device_candidate'],
            'suggested_asset_type' => $scan['suggested_asset_type'],
            'match_source' => $match['source'] ?? null,
            'match_confidence' => $match['confidence'] ?? null,
            'match' => $match !== null ? $this->buildMatchBlock($match) : null,
            'suggested_employee' => $suggestedEmployee,
            'last_logged_on_user' => $lastLoggedOnUser,
            'warning' => $warning,
        ];

        DeviceScanModel::log($result, $context, $gebruiktDoorId);

        return $result;
    }

    private function buildMatchBlock(array $match): array
    {
        $voorraadItem = $match['voorraad_item'];
        $schijfgebruikDevice = $match['schijfgebruik_device'];

        return [
            'bron' => $match['source'] === 'voorraad_items' ? 'Actieve voorraadcatalogus' : 'NinjaOne / RMM-import (schijfgebruik)',
            'voorraad_item_id' => $voorraadItem['id'] ?? null,
            'schijfgebruik_device_id' => $schijfgebruikDevice['id'] ?? null,
            'status' => $voorraadItem['status'] ?? null,
            'barcode' => $voorraadItem['barcode'] ?? null,
            'asset_naam' => $voorraadItem['device_naam'] ?? ($schijfgebruikDevice['naam'] ?? null),
            'fabrikant' => $schijfgebruikDevice['merk'] ?? null,
            'model' => $schijfgebruikDevice['model'] ?? null,
            'locatie' => $voorraadItem['locatie'] ?? ($schijfgebruikDevice['locatie'] ?? null),
            'laatst_gezien_ninjaone_op' => $schijfgebruikDevice['laatst_online'] ?? null,
        ];
    }
}
