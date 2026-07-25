<?php

namespace App\Modules\Uitgifte\Models;

use App\Core\Database;
use App\Core\Model;

class UitgifteModel extends Model
{
    protected static string $table = 'uitgiften';
    protected static array $fillable = [
        'voorraad_item_id', 'medewerker_naam', 'uitgegeven_op', 'teruggegeven_op', 'opmerking', 'toestemming_manager', 'uitgegeven_door_id',
    ];

    private const SELECT = "
        SELECT u.*, vi.barcode, vi.variant, vi.serienummer, vt.naam AS type_naam,
               (CASE WHEN u.teruggegeven_op IS NULL THEN 'uitgegeven' ELSE 'geretourneerd' END) AS status
        FROM uitgiften u
        LEFT JOIN voorraad_items vi ON vi.id = u.voorraad_item_id
        LEFT JOIN voorraad_types vt ON vt.id = vi.type_id
    ";

    public static function allWithRelations(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY u.created_at DESC')->fetchAll();
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE u.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Uitgiftehistorie van één voorraad-item, nieuwste eerst — voor het Voorraad-detailscherm. */
    public static function forVoorraadItem(int $voorraadItemId): array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE u.voorraad_item_id = ? ORDER BY u.created_at DESC');
        $stmt->execute([$voorraadItemId]);
        return $stmt->fetchAll();
    }

    /** Uitgiften van een medewerker — matcht op naam, zie medewerker-detailpagina. */
    public static function forMedewerkerNaam(string $naam): array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE LOWER(u.medewerker_naam) = LOWER(?) ORDER BY u.created_at DESC');
        $stmt->execute([$naam]);
        return $stmt->fetchAll();
    }

    public static function setTeruggegeven(int $id, string $datum, ?string $opmerking = null): void
    {
        $stmt = Database::pdo()->prepare('UPDATE uitgiften SET teruggegeven_op = ?, retour_opmerking = ? WHERE id = ?');
        $stmt->execute([$datum, $opmerking, $id]);
    }

    public static function countAll(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM uitgiften')->fetchColumn();
    }

    /** Nog niet geretourneerd. */
    public static function countOpen(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM uitgiften WHERE teruggegeven_op IS NULL')->fetchColumn();
    }

    /** Deze kalenderweek uitgegeven. */
    public static function countDezeWeek(): int
    {
        return (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM uitgiften WHERE YEARWEEK(uitgegeven_op, 1) = YEARWEEK(CURDATE(), 1)"
        )->fetchColumn();
    }

    public static function countGeretourneerd(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM uitgiften WHERE teruggegeven_op IS NOT NULL')->fetchColumn();
    }

    /** Top uitgegeven voorraadtypen (aller tijden, ongeacht of het item al geretourneerd is), voor het dashboard. */
    public static function topUitgegeven(int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare("
            SELECT vt.id, vt.naam, vt.code, COUNT(u.id) AS aantal
            FROM uitgiften u
            LEFT JOIN voorraad_items vi ON vi.id = u.voorraad_item_id
            LEFT JOIN voorraad_types vt ON vt.id = vi.type_id
            GROUP BY vt.id, vt.naam, vt.code
            ORDER BY aantal DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
