<?php

namespace App\Shared\AssetScan;

use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Modules\Uitgifte\Models\UitgifteModel;
use App\Modules\Voorraad\Models\VoorraadItemModel;
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
     *     mac_address:?string, extra_parts:string[], device_candidate:bool,
     *     suggested_asset_type:?string, match_source:?string, match_confidence:?string,
     *     match:?array, matched_template:?array, suggested_employee:?array,
     *     last_logged_on_user:?string, warning:?string
     * }
     */
    public function analyseer(string $rawInput, string $context, ?int $gebruiktDoorId): array
    {
        $scan = BarcodeScanParser::parse($rawInput);
        $matchedTemplate = null;

        if (!$scan['device_candidate']) {
            // Kale token zonder komma (geen "serienummer,product-ID"-formaat): eerst kijken of dit
            // al een bekende eigen voorraadbarcode is (dan is er niets te suggereren, bestaand
            // gedrag blijft ongewijzigd) — pas als dat niet zo is, tegen de beheerbare
            // barcode-sjablonen leggen (BarcodeTemplateMatcher) voor bv. een kaal
            // fabrikant-serienummer (toetsenbord) of EAN-barcode (monitor).
            ['scan' => $scan, 'matchedTemplate' => $matchedTemplate] = $this->verrijkKaleToken($scan);
        }

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
            'mac_address' => $scan['mac_address'] ?? null,
            'extra_parts' => $scan['extra_parts'],
            'device_candidate' => $scan['device_candidate'],
            'suggested_asset_type' => $scan['suggested_asset_type'],
            'match_source' => $match['source'] ?? ($matchedTemplate !== null ? 'barcode_template' : null),
            'match_confidence' => $match['confidence'] ?? ($matchedTemplate !== null ? 'gemiddeld' : null),
            'match' => $match !== null ? $this->buildMatchBlock($match) : null,
            'matched_template' => $matchedTemplate,
            'suggested_employee' => $suggestedEmployee,
            'last_logged_on_user' => $lastLoggedOnUser,
            'warning' => $warning,
        ];

        DeviceScanModel::log($result, $context, $gebruiktDoorId);

        return $result;
    }

    /**
     * Verrijkt een kale, komma-loze token: als de token al een bekende eigen voorraadbarcode is
     * blijft alles ongewijzigd (geen suggestie nodig — de bestaande /items-autocomplete dekt dat
     * geval al). Anders wordt tegen de beheerbare barcode-sjablonen gelegd; bij een match wordt de
     * token als serienummer behandeld en het voorgestelde type/omschrijving overgenomen, zodat de
     * rest van analyseer() (zoeken op serienummer, suggestieblok opbouwen) hetzelfde pad volgt als
     * bij een "echte" device-candidate-scan.
     *
     * @return array{scan: array, matchedTemplate: ?array}
     */
    private function verrijkKaleToken(array $scan): array
    {
        $raw = $scan['raw_scan_value'];
        if ($raw === '' || VoorraadItemModel::findByBarcode($raw) !== null) {
            return ['scan' => $scan, 'matchedTemplate' => null];
        }

        $template = BarcodeTemplateMatcher::match($raw);
        if ($template === null) {
            return ['scan' => $scan, 'matchedTemplate' => null];
        }

        $scan['serial_number'] = $raw;
        $scan['description'] = $template['omschrijving'] ?: $template['naam'];
        $scan['device_candidate'] = true;
        // Voorlopige suggestie op basis van het sjabloon — kan null zijn als het sjabloon geen
        // voorraadtype heeft (bv. "herkend, maar kies zelf het type"); nooit blind definitief.
        $scan['suggested_asset_type'] = $template['voorraad_type_naam'];

        return [
            'scan' => $scan,
            'matchedTemplate' => [
                'id' => $template['id'],
                'naam' => $template['naam'],
                'suggested_asset_type' => $template['voorraad_type_naam'],
            ],
        ];
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
