<?php

namespace App\Shared\AssetScan\Models;

use App\Core\Model;

/**
 * Audittrail voor elke barcode-scan + de automatische suggestie die AssetEnrichmentService erbij
 * gaf. Puur logging voor debugging/nazorg — geen enkel veld hier is ooit de bron van waarheid voor
 * een definitieve uitgifte/voorraad-registratie (dat blijven uitgiften/voorraad_items zelf, pas
 * geschreven nadat een gebruiker de suggestie heeft bevestigd of gewijzigd).
 */
class DeviceScanModel extends Model
{
    protected static string $table = 'device_scans';
    protected static array $fillable = [
        'raw_scan_value', 'serienummer', 'product_id', 'beschrijving', 'mac_address', 'extra_parts_json', 'device_candidate', 'suggested_asset_type',
        'match_source', 'match_confidence', 'matched_voorraad_item_id', 'matched_schijfgebruik_device_id', 'matched_barcode_template_id',
        'suggested_medewerker_id', 'last_logged_on_user', 'context', 'gebruikt_door_id',
    ];

    /** @param array<string,mixed> $result AssetEnrichmentService::analyseer()-resultaat. */
    public static function log(array $result, string $context, ?int $gebruiktDoorId): int
    {
        return self::create([
            'raw_scan_value' => $result['raw_scan_value'],
            'serienummer' => $result['serial_number'],
            'product_id' => $result['product_id'],
            'beschrijving' => $result['description'],
            'mac_address' => $result['mac_address'] ?? null,
            // Bewaart álle extra CSV-delen achter serienummer/product-ID als JSON, ook als een
            // toekomstige scannervariant er nog meer meestuurt dan de huidige 3-delen-vorm.
            'extra_parts_json' => $result['extra_parts'] !== [] ? json_encode($result['extra_parts']) : null,
            'device_candidate' => $result['device_candidate'] ? 1 : 0,
            'suggested_asset_type' => $result['suggested_asset_type'],
            'match_source' => $result['match_source'],
            'match_confidence' => $result['match_confidence'],
            'matched_voorraad_item_id' => $result['match']['voorraad_item_id'] ?? null,
            'matched_schijfgebruik_device_id' => $result['match']['schijfgebruik_device_id'] ?? null,
            'matched_barcode_template_id' => $result['matched_template']['id'] ?? null,
            'suggested_medewerker_id' => $result['suggested_employee']['id'] ?? null,
            'last_logged_on_user' => $result['last_logged_on_user'],
            'context' => $context,
            'gebruikt_door_id' => $gebruiktDoorId,
        ]);
    }
}
