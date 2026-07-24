<?php

namespace App\Modules\CyberRisico\Models;

use App\Core\Database;
use App\Core\Model;

class CyberRisicoModel extends Model
{
    protected static string $table = 'cyberrisicos';
    protected static array $fillable = [
        'titel', 'omschrijving', 'categorie', 'prioriteit', 'status', 'locatie', 'gemeld_door',
        'afdeling_id', 'eigenaar_id', 'datum_geconstateerd', 'datum_gemeld', 'oplossingsadvies',
        'bewijs_notities', 'is_gevoelig', 'aangemaakt_door_id',
    ];
    protected static bool $softDeletes = true;

    private const SELECT = "
        SELECT c.*, u.naam AS eigenaar_naam, a.naam AS afdeling_naam
        FROM cyberrisicos c
        LEFT JOIN users u ON u.id = c.eigenaar_id
        LEFT JOIN afdelingen a ON a.id = c.afdeling_id
        WHERE c.deleted_at IS NULL
    ";

    public static function allWithRelations(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY c.created_at DESC')->fetchAll();
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND c.id = ?');
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

    public static function countOpen(): int
    {
        $cyberrisicos = (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM cyberrisicos WHERE deleted_at IS NULL AND status NOT IN ('opgelost', 'geaccepteerd')"
        )->fetchColumn();

        $tickets = (int) Database::pdo()->query(
            "SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL AND is_cyberrisico = 1 AND status <> 'afgehandeld'"
        )->fetchColumn();

        return $cyberrisicos + $tickets;
    }

    /**
     * Aantal gemelde incidenten per dag over de afgelopen 30 dagen (incl. vandaag) — telt zowel
     * cyberrisicos als tickets met is_cyberrisico=1 mee, met dagen zonder meldingen op 0 gevuld
     * zodat de grafiek geen gaten heeft.
     * @return array<int, array{datum: string, aantal: int}>
     */
    public static function countLast30Days(): array
    {
        $sql = "
            SELECT dag, COUNT(*) AS aantal FROM (
                SELECT COALESCE(datum_gemeld, DATE(created_at)) AS dag
                FROM cyberrisicos
                WHERE deleted_at IS NULL
                  AND COALESCE(datum_gemeld, DATE(created_at)) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
                UNION ALL
                SELECT DATE(created_at) AS dag
                FROM tickets
                WHERE deleted_at IS NULL AND is_cyberrisico = 1
                  AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            ) gecombineerd
            GROUP BY dag
        ";
        $rows = Database::pdo()->query($sql)->fetchAll();
        $byDate = array_column($rows, 'aantal', 'dag');

        $result = [];
        for ($i = 29; $i >= 0; $i--) {
            $datum = date('Y-m-d', strtotime("-{$i} days"));
            $result[] = ['datum' => $datum, 'aantal' => (int) ($byDate[$datum] ?? 0)];
        }

        return $result;
    }

    private const TICKET_STATUS_LABELS = [
        'open' => 'Open',
        'in_behandeling' => 'In behandeling',
        'wacht_op_info' => 'Wacht op info',
        'afgehandeld' => 'Afgehandeld',
    ];

    private const TICKET_STATUS_BADGE_CLASSES = [
        'open' => 'text-bg-primary',
        'in_behandeling' => 'text-bg-warning',
        'wacht_op_info' => 'text-bg-warning',
        'afgehandeld' => 'text-bg-success',
    ];

    private const CYBER_STATUS_LABELS = [
        'nieuw' => 'Nieuw',
        'in_onderzoek' => 'In onderzoek',
        'bevestigd' => 'Bevestigd',
        'opgelost' => 'Opgelost',
        'geaccepteerd' => 'Geaccepteerd risico',
    ];

    private const CYBER_STATUS_BADGE_CLASSES = [
        'nieuw' => 'text-bg-primary',
        'in_onderzoek' => 'text-bg-warning',
        'bevestigd' => 'text-bg-danger',
        'opgelost' => 'text-bg-success',
        'geaccepteerd' => 'text-bg-secondary',
    ];

    private const CYBER_PRIORITEIT_LABELS = ['laag' => 'Laag', 'middel' => 'Middel', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek'];

    private const CYBER_PRIORITEIT_BADGE_CLASSES = [
        'laag' => 'text-bg-secondary',
        'middel' => 'text-bg-warning',
        'hoog' => 'text-bg-danger',
        'kritiek' => 'text-bg-dark',
    ];

    private const TICKET_PRIORITEIT_LABELS = ['laag' => 'Laag', 'normaal' => 'Normaal', 'hoog' => 'Hoog', 'kritiek' => 'Kritiek'];

    /**
     * Gemelde incidenten van de afgelopen 30 dagen, gegroepeerd per dag — gebruikt om bij een klik
     * op een balk in de dashboard-grafiek de incidenten van die dag te tonen. Combineert cyberrisicos
     * met tickets die als cyber risico zijn gemarkeerd; elke rij krijgt kant-en-klare labels/badge-
     * classes/links, zodat de dashboard-JS niet apart met de twee brontabellen hoeft om te gaan.
     * @return array<string, array<int, array{id: int, titel: string, statusLabel: string, prioriteitLabel: string, statusBadgeClass: string, prioriteitBadgeClass: string, link: string}>>
     */
    public static function listLast30DaysGrouped(): array
    {
        $sql = "
            SELECT id, titel, prioriteit, status, COALESCE(datum_gemeld, DATE(created_at)) AS dag, 'cyberrisico' AS bron
            FROM cyberrisicos
            WHERE deleted_at IS NULL
              AND COALESCE(datum_gemeld, DATE(created_at)) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            UNION ALL
            SELECT id, titel, prioriteit, status, DATE(created_at) AS dag, 'ticket' AS bron
            FROM tickets
            WHERE deleted_at IS NULL AND is_cyberrisico = 1
              AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            ORDER BY dag ASC, id ASC
        ";
        $rows = Database::pdo()->query($sql)->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $isTicket = $row['bron'] === 'ticket';
            $grouped[$row['dag']][] = [
                'id' => (int) $row['id'],
                'titel' => $row['titel'],
                'statusLabel' => $isTicket
                    ? (self::TICKET_STATUS_LABELS[$row['status']] ?? $row['status'])
                    : (self::CYBER_STATUS_LABELS[$row['status']] ?? $row['status']),
                'prioriteitLabel' => $isTicket
                    ? (self::TICKET_PRIORITEIT_LABELS[$row['prioriteit']] ?? $row['prioriteit'])
                    : (self::CYBER_PRIORITEIT_LABELS[$row['prioriteit']] ?? $row['prioriteit']),
                'statusBadgeClass' => $isTicket
                    ? (self::TICKET_STATUS_BADGE_CLASSES[$row['status']] ?? 'text-bg-light')
                    : (self::CYBER_STATUS_BADGE_CLASSES[$row['status']] ?? 'text-bg-light'),
                'prioriteitBadgeClass' => self::CYBER_PRIORITEIT_BADGE_CLASSES[$row['prioriteit']] ?? 'text-bg-light',
                'link' => $isTicket ? "/tickets/{$row['id']}" : "/cyberrisicos/{$row['id']}",
            ];
        }

        return $grouped;
    }
}
