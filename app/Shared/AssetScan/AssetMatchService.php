<?php

namespace App\Shared\AssetScan;

use App\Core\Database;

/**
 * Zoekservice op serienummer. Doorzoekt bewust alleen de tabellen die daadwerkelijk een
 * serienummer-kolom hebben — geverifieerd tegen database/xml/*.xml, niet aangenomen:
 * - voorraad_items: onze eigen actieve asset-/voorraadcatalogus (uniek per stuk).
 * - schijfgebruik_devices: NinjaRMM/NinjaOne "Devices"-CSV-import (zie SchijfgebruikImport),
 *   met merk/model/laatste_login/gekoppelde medewerker.
 * `devices` en de software-importtabellen hebben geen serienummer-kolom en worden dus niet
 * doorzocht — een match daarop zou een niet-bestaande koppeling suggereren. Historische uitgiftes
 * zijn geen aparte bron: die worden via het gevonden voorraad_item opgezocht (zie
 * AssetEnrichmentService), niet los op serienummer.
 */
class AssetMatchService
{
    /**
     * Exacte match in de actieve voorraadcatalogus weegt zwaarder dan een NinjaOne/RMM-importmatch
     * (zie de confidence-ranking uit de aanvraag) — voorraad_items is de bron die de app zelf
     * beheert en dus het meest actueel/betrouwbaar is over de huidige status van het item.
     *
     * @return array{
     *     source: 'voorraad_items'|'schijfgebruik_devices',
     *     confidence: 'hoog'|'gemiddeld',
     *     voorraad_item: ?array,
     *     schijfgebruik_device: ?array
     * }|null
     */
    public static function findBySerienummer(string $serienummer): ?array
    {
        $voorraadItem = self::findVoorraadItem($serienummer);

        if ($voorraadItem !== null) {
            return [
                'source' => 'voorraad_items',
                'confidence' => 'hoog',
                'voorraad_item' => $voorraadItem,
                'schijfgebruik_device' => self::findSchijfgebruikDevice($serienummer),
            ];
        }

        $schijfgebruikDevice = self::findSchijfgebruikDevice($serienummer);
        if ($schijfgebruikDevice !== null) {
            return [
                'source' => 'schijfgebruik_devices',
                'confidence' => 'gemiddeld',
                'voorraad_item' => null,
                'schijfgebruik_device' => $schijfgebruikDevice,
            ];
        }

        return null;
    }

    private static function findVoorraadItem(string $serienummer): ?array
    {
        $stmt = Database::pdo()->prepare("
            SELECT vi.*, vt.naam AS type_naam, vt.code AS type_code, d.naam AS device_naam
            FROM voorraad_items vi
            LEFT JOIN voorraad_types vt ON vt.id = vi.type_id
            LEFT JOIN devices d ON d.id = vi.device_id
            WHERE vi.deleted_at IS NULL AND vi.serienummer = ?
            LIMIT 1
        ");
        $stmt->execute([$serienummer]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private static function findSchijfgebruikDevice(string $serienummer): ?array
    {
        $stmt = Database::pdo()->prepare("
            SELECT sd.*, CONCAT(m.voornaam, ' ', m.achternaam) AS medewerker_naam
            FROM schijfgebruik_devices sd
            LEFT JOIN medewerkers m ON m.id = sd.medewerker_id AND m.deleted_at IS NULL
            WHERE sd.serienummer = ?
            LIMIT 1
        ");
        $stmt->execute([$serienummer]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
