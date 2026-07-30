<?php

namespace App\Modules\Voorraad\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Beheerbare "barcode-sjablonen": een regex-patroon (`patroon`, zonder de buitenste `/../`-
 * delimiters) gekoppeld aan een voorgesteld voorraadtype + omschrijving, zodat een kale
 * barcode-scan zonder komma (bv. een fabrikant-eigen serienummer als "BCYUH0ARZCL0AX" of een
 * EAN-barcode als "1155984821038") toch herkend kan worden — zie
 * App\Shared\AssetScan\BarcodeTemplateMatcher. Beheerd via /voorraad/barcode-templates
 * (BarcodeTemplateController), geen redeploy nodig om een nieuw type barcode te herkennen.
 */
class BarcodeTemplateModel extends Model
{
    protected static string $table = 'barcode_templates';
    protected static array $fillable = ['naam', 'patroon', 'voorraad_type_id', 'omschrijving', 'actief', 'volgorde'];

    private const SELECT = "
        SELECT bt.*, vt.naam AS voorraad_type_naam
        FROM barcode_templates bt
        LEFT JOIN voorraad_types vt ON vt.id = bt.voorraad_type_id
    ";

    public static function allWithType(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY bt.volgorde ASC, bt.naam ASC')->fetchAll();
    }

    public static function findWithType(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE bt.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Actieve sjablonen in controlevolgorde (laag volgnummer eerst) — zie BarcodeTemplateMatcher::match(). */
    public static function activeOrdered(): array
    {
        $stmt = Database::pdo()->query(self::SELECT . ' WHERE bt.actief = 1 ORDER BY bt.volgorde ASC, bt.id ASC');
        return $stmt->fetchAll();
    }
}
