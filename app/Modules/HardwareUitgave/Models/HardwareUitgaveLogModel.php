<?php

namespace App\Modules\HardwareUitgave\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Automatisch bijgehouden statushistorie voor hardware-uitgaven (aangevraagd → goedgekeurd/
 * afgekeurd → besteld → geleverd), zelfde patroon als CyberRisicoLogModel/TicketLogModel. Er is
 * geen handmatige "opmerking toevoegen"-vorm zoals bij Cyberrisico/Ticket — elke rij wordt
 * automatisch geschreven door HardwareUitgaveService::setStatus() bij een echte statuswijziging.
 */
class HardwareUitgaveLogModel extends Model
{
    protected static string $table = 'hardware_uitgave_logs';
    protected static array $fillable = ['hardware_uitgave_id', 'user_id', 'status_van', 'status_naar'];

    public static function forHardwareUitgave(int $hardwareUitgaveId): array
    {
        $stmt = Database::pdo()->prepare("
            SELECT l.*, u.naam AS user_naam
            FROM hardware_uitgave_logs l
            LEFT JOIN users u ON u.id = l.user_id
            WHERE l.hardware_uitgave_id = ?
            ORDER BY l.created_at DESC
        ");
        $stmt->execute([$hardwareUitgaveId]);
        return $stmt->fetchAll();
    }
}
