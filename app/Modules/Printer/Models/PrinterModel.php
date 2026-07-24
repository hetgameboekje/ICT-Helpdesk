<?php

namespace App\Modules\Printer\Models;

use App\Core\Database;
use App\Core\Model;

class PrinterModel extends Model
{
    protected static string $table = 'printers';
    protected static array $fillable = [
        'naam', 'computer_naam', 'type', 'driver_naam', 'ip_adres', 'opmerking', 'aangemaakt_door_id',
    ];
    protected static bool $softDeletes = true;

    private const SELECT = "
        SELECT p.*, u.naam AS aangemaakt_door_naam
        FROM printers p
        LEFT JOIN users u ON u.id = p.aangemaakt_door_id
        WHERE p.deleted_at IS NULL
    ";

    public static function allWithRelations(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY p.naam ASC')->fetchAll();
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND p.id = ?');
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

    /** Bouwt het rundll32-commando om de printer te verbinden. Gebruikt de servernaam (UNC-share),
     * of het IP-adres als de printer niet via een print-server gaat.
     */
    public static function buildInstallCommand(array $printer): string
    {
        $host = !empty($printer['computer_naam']) ? $printer['computer_naam'] : (string) ($printer['ip_adres'] ?? '');
        $bs = '\\';

        return 'rundll32 printui.dll,PrintUIEntry /y /n "' . $bs . $bs . $host . $bs . $printer['naam'] . '"';
    }
}
