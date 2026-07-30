<?php

namespace App\Shared\AssetScan;

/**
 * Parseert scanner-invoer voor fysieke apparaat-barcodes (bv. een fabrieksassetlabel
 * "1H86265279,E08ZGET#ABH": serienummer + product-ID, komma-gescheiden). Puur functioneel, geen
 * database-toegang — deze klasse beslist alleen of invoer een "device candidate" is.
 * AssetMatchService/AssetEnrichmentService gebruiken het resultaat om te zoeken/verrijken.
 *
 * Herkent het 2-delen-komma-formaat (serienummer,product-ID) en de variant met extra delen erachter
 * — bv. "5CD340274M,6B8B3EA#ABH,HP Zbook Power 15.6 inch G9", waarbij het 3e (en eventuele verdere)
 * deel een vrije omschrijving is die de scanner meestuurt. Een deel dat er als een MAC-adres uitziet
 * (bv. "5CG329ZW43,N28963-002,HP USB-C G5 Essential Dock,5C-28-86-3A-2E-AC" — een HP-dockingstation-
 * label) wordt er apart uitgelicht als `mac_address` i.p.v. in de omschrijving te blijven staan. Al
 * het overige vanaf het 3e deel wordt zowel als losse `extra_parts`-lijst als samengevoegde
 * `description` teruggegeven, zodat de aanroeper niets van de ruwe scan hoeft weg te gooien (zie
 * AssetEnrichmentService/DeviceScanModel — alles wordt gelogd, ook als een toekomstige variant nog
 * meer delen toevoegt). Een enkele barcode zonder komma (bv. onze eigen "TYPECODE-SERIENUMMER", of
 * een kaal fabrikant-serienummer/EAN-barcode als "BCYUH0ARZCL0AX"/"1155984821038") blijft hier
 * device_candidate=false — die kale-token-herkenning gebeurt niet hier (puur functioneel, geen
 * database-toegang) maar in AssetEnrichmentService via BarcodeTemplateMatcher.
 */
class BarcodeScanParser
{
    private const MIN_DEEL_LENGTE = 4;
    private const MAC_PATROON = '/^[0-9A-Fa-f]{2}([:-][0-9A-Fa-f]{2}){5}$/';

    /**
     * @return array{
     *     raw_scan_value:string, serial_number:?string, product_id:?string, description:?string,
     *     mac_address:?string, extra_parts:string[], device_candidate:bool, suggested_asset_type:?string
     * }
     */
    public static function parse(string $rawInput): array
    {
        $raw = trim($rawInput);
        $delen = array_map('trim', explode(',', $raw));

        if (count($delen) >= 2 && strlen($delen[0]) >= self::MIN_DEEL_LENGTE && strlen($delen[1]) >= self::MIN_DEEL_LENGTE) {
            $extraDelen = array_values(array_filter(array_slice($delen, 2), fn (string $d) => $d !== ''));
            [$macAddress, $beschrijvingDelen] = self::splitMacAddress($extraDelen);

            return [
                'raw_scan_value' => $raw,
                'serial_number' => $delen[0],
                'product_id' => $delen[1],
                'description' => $beschrijvingDelen !== [] ? implode(', ', $beschrijvingDelen) : null,
                'mac_address' => $macAddress,
                'extra_parts' => $extraDelen,
                'device_candidate' => true,
                // Voorlopige suggestie — nooit blind definitief, de gebruiker bevestigt/wijzigt dit in de UI.
                'suggested_asset_type' => 'Laptop',
            ];
        }

        return [
            'raw_scan_value' => $raw,
            'serial_number' => null,
            'product_id' => null,
            'description' => null,
            'mac_address' => null,
            'extra_parts' => [],
            'device_candidate' => false,
            'suggested_asset_type' => null,
        ];
    }

    /**
     * Licht het eerste deel dat er als MAC-adres uitziet (bv. "5C-28-86-3A-2E-AC") uit de overige
     * scandelen, zodat het niet als platte tekst in de omschrijving terechtkomt.
     *
     * @param string[] $delen
     * @return array{0: ?string, 1: string[]} [gevonden MAC-adres of null, overige delen]
     */
    private static function splitMacAddress(array $delen): array
    {
        $macAddress = null;
        $overig = [];

        foreach ($delen as $deel) {
            if ($macAddress === null && preg_match(self::MAC_PATROON, $deel) === 1) {
                $macAddress = strtoupper($deel);
                continue;
            }
            $overig[] = $deel;
        }

        return [$macAddress, $overig];
    }
}
