<?php

namespace App\Modules\Verbeterpunt\Models;

use App\Core\Database;
use App\Core\Model;

class VerbeterpuntModel extends Model
{
    protected static string $table = 'verbeterpunten';
    protected static array $fillable = ['titel', 'omschrijving', 'categorie', 'afdeling_id', 'ingediend_door_id', 'status'];
    protected static bool $softDeletes = true;

    private const SELECT = "
        SELECT v.*, a.naam AS afdeling_naam, u.naam AS ingediend_door_naam
        FROM verbeterpunten v
        LEFT JOIN afdelingen a ON a.id = v.afdeling_id
        LEFT JOIN users u ON u.id = v.ingediend_door_id
        WHERE v.deleted_at IS NULL
    ";

    public static function allWithRelations(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY v.created_at DESC')->fetchAll();
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND v.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Zelfde regel als TicketModel::alleenGewijzigdeVelden(): lege/ongewijzigde velden niet meesturen in een update. */
    public static function alleenGewijzigdeVelden(array $huidig, array $nieuw): array
    {
        foreach ($nieuw as $veld => $waarde) {
            if ($waarde === '' || $waarde === null) {
                unset($nieuw[$veld]);
                continue;
            }

            if (array_key_exists($veld, $huidig) && (string) $huidig[$veld] === (string) $waarde) {
                unset($nieuw[$veld]);
            }
        }

        return $nieuw;
    }
}
