<?php

namespace App\Modules\Schijfgebruik\Models;

use App\Core\Database;
use App\Core\Model;

class SchijfgebruikSchijfModel extends Model
{
    protected static string $table = 'schijfgebruik_schijven';
    protected static array $fillable = [
        'device_id', 'letter', 'disk_type', 'capaciteit_bytes', 'capaciteit_label', 'gebruik_percentage',
    ];

    /** @return array<int, array<string, mixed>> alle schijven van één apparaat, gesorteerd op letter. */
    public static function forDevice(int $deviceId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM schijfgebruik_schijven WHERE device_id = ? ORDER BY letter ASC');
        $stmt->execute([$deviceId]);
        return $stmt->fetchAll();
    }

    /** Geaggregeerde totalen voor de dashboard-KPI-tegels — zelfde berekening als SchijfgebruikService::stats(), maar in SQL i.p.v. over alle rijen in PHP. */
    public static function dashboardStats(): array
    {
        $row = Database::pdo()->query("
            SELECT COUNT(DISTINCT s.device_id) AS devices,
                   COALESCE(SUM(s.capaciteit_bytes), 0) AS totaal_bytes,
                   COALESCE(SUM(s.capaciteit_bytes * s.gebruik_percentage / 100), 0) AS gebruikt_bytes,
                   SUM(CASE WHEN s.gebruik_percentage >= 90 THEN 1 ELSE 0 END) AS kritiek
            FROM schijfgebruik_schijven s
        ")->fetch();

        return [
            'devices' => (int) ($row['devices'] ?? 0),
            'totaalTb' => round(((float) ($row['totaal_bytes'] ?? 0)) / 1e12, 1),
            'gebruiktTb' => round(((float) ($row['gebruikt_bytes'] ?? 0)) / 1e12, 1),
            'kritiek' => (int) ($row['kritiek'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> één rij per schijf, met de bijbehorende apparaatgegevens erbij gejoined. */
    public static function allWithDevice(): array
    {
        return Database::pdo()->query("
            SELECT
                s.id AS schijf_id, s.letter, s.disk_type, s.capaciteit_bytes, s.capaciteit_label, s.gebruik_percentage,
                d.id AS device_id, d.extern_id, d.organisatie, d.locatie, d.naam, d.type, d.rol, d.beleid,
                d.laatst_online, d.laatst_update, d.laatste_boot, d.garantie_tot, d.tags,
                d.laatste_login, d.merk, d.model, d.serienummer, d.os_naam
            FROM schijfgebruik_schijven s
            JOIN schijfgebruik_devices d ON d.id = s.device_id
            ORDER BY s.gebruik_percentage DESC
        ")->fetchAll();
    }
}
