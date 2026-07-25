<?php

namespace App\Modules\Voorraad\Models;

use App\Core\Database;
use App\Core\Model;

class VoorraadItemModel extends Model
{
    protected static string $table = 'voorraad_items';
    protected static array $fillable = [
        'type_id', 'device_id', 'variant', 'serienummer', 'barcode', 'status', 'locatie', 'opmerking', 'specificaties', 'aangemaakt_door_id',
    ];
    protected static bool $softDeletes = true;

    private const SELECT = "
        SELECT vi.*, vt.naam AS type_naam, vt.code AS type_code, d.naam AS device_naam
        FROM voorraad_items vi
        LEFT JOIN voorraad_types vt ON vt.id = vi.type_id
        LEFT JOIN devices d ON d.id = vi.device_id
        WHERE vi.deleted_at IS NULL
    ";

    public static function allWithRelations(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY vi.created_at DESC')->fetchAll();
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND vi.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function countAll(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM voorraad_items WHERE deleted_at IS NULL')->fetchColumn();
    }

    /** @return array<string, int> status => aantal, voor de dashboard-KPI's. */
    public static function telPerStatus(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT status, COUNT(*) AS aantal FROM voorraad_items WHERE deleted_at IS NULL GROUP BY status'
        );
        return array_column($stmt->fetchAll(), 'aantal', 'status');
    }

    public static function countByType(): array
    {
        $sql = "
            SELECT vt.id, vt.naam, vt.code,
                   COUNT(vi.id) AS totaal,
                   SUM(CASE WHEN vi.status = 'op_voorraad' THEN 1 ELSE 0 END) AS beschikbaar
            FROM voorraad_types vt
            LEFT JOIN voorraad_items vi ON vi.type_id = vt.id AND vi.deleted_at IS NULL
            GROUP BY vt.id, vt.naam, vt.code
            ORDER BY vt.naam ASC
        ";
        return Database::pdo()->query($sql)->fetchAll();
    }

    public static function searchAvailable(string $q): array
    {
        $stmt = Database::pdo()->prepare(
            self::SELECT . " AND vi.status = 'op_voorraad' AND (vi.barcode LIKE ? OR vt.naam LIKE ?)
             ORDER BY vt.naam ASC, vi.barcode ASC LIMIT 15"
        );
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll();
    }

    public static function findAvailableByBarcode(string $barcode): ?array
    {
        $stmt = Database::pdo()->prepare(
            self::SELECT . " AND vi.barcode = ? AND vi.status = 'op_voorraad' ORDER BY vi.id ASC LIMIT 1"
        );
        $stmt->execute([$barcode]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function serienummerExists(string $serienummer, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM voorraad_items WHERE serienummer = ? AND deleted_at IS NULL';
        $params = [$serienummer];
        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }

        $stmt = Database::pdo()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE voorraad_items SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    public static function findByDeviceId(int $deviceId): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND vi.device_id = ? LIMIT 1');
        $stmt->execute([$deviceId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Maakt automatisch een voorraad-item aan voor een apparaat waaraan net een installatie-opdracht
     * is gekoppeld en dat nog niet in de voorraadcatalogus stond (zie InstallatieController::opdrachtStore()).
     * Komt net als createOnbekend() onder het vaste type 'Overig' te staan.
     */
    public static function createVoorApparaat(int $deviceId, string $naam, ?int $aangemaaktDoorId): int
    {
        $typeId = VoorraadTypeModel::findOrCreateOverig();

        return self::create([
            'type_id' => $typeId,
            'device_id' => $deviceId,
            'variant' => substr($naam, 0, 50),
            'barcode' => 'APPARAAT-' . uniqid(),
            'status' => 'op_voorraad',
            'opmerking' => "Automatisch aangemaakt vanuit installatie-opdracht voor apparaat \"{$naam}\".",
            'aangemaakt_door_id' => $aangemaaktDoorId,
        ]);
    }

    /**
     * Maakt direct-uitgegeven voorraad aan voor een item dat niet in de catalogus voorkomt (zie
     * UitgifteController::store()). Komt onder het vaste type 'Overig' te staan — dat type zelf
     * wordt niet uitgebreid met nieuwe namen, de getypte naam komt als variant op het item te staan.
     */
    public static function createOnbekend(string $naam, ?int $aangemaaktDoorId): int
    {
        $typeId = VoorraadTypeModel::findOrCreateOverig();

        return self::create([
            'type_id' => $typeId,
            'variant' => substr($naam, 0, 50),
            'barcode' => 'ONBEKEND-' . uniqid(),
            'status' => 'uitgegeven',
            'opmerking' => "Automatisch aangemaakt vanuit uitgifte: \"{$naam}\" stond niet in de voorraadcatalogus.",
            'aangemaakt_door_id' => $aangemaaktDoorId,
        ]);
    }
}
