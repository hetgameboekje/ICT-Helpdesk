<?php

namespace App\Modules\Ticket\Models;

use App\Core\Database;
use App\Core\Model;
use App\Shared\Crypto\FieldEncryptor;

class TicketModel extends Model
{
    protected static string $table = 'tickets';
    protected static array $fillable = [
        'titel', 'omschrijving', 'opdrachtgever_naam', 'categorie', 'afdeling_id', 'prioriteit',
        'impact', 'schatting_minuten', 'deadline', 'behandelaar_id', 'status', 'aangemaakt_door_id',
        'escalatie_nummer', 'escalatie_instantie', 'is_cyberrisico',
    ];
    protected static bool $softDeletes = true;

    /** Velden die versleuteld in de database staan (zie App\Shared\Crypto\FieldEncryptor). */
    private const VERSLEUTELDE_VELDEN = ['omschrijving', 'opdrachtgever_naam'];

    private static function encryptFields(array $data): array
    {
        foreach (self::VERSLEUTELDE_VELDEN as $veld) {
            if (array_key_exists($veld, $data)) {
                $data[$veld] = FieldEncryptor::encrypt($data[$veld]);
            }
        }
        return $data;
    }

    private static function decryptRow(array $row): array
    {
        foreach (self::VERSLEUTELDE_VELDEN as $veld) {
            if (array_key_exists($veld, $row)) {
                $row[$veld] = FieldEncryptor::decrypt($row[$veld]);
            }
        }
        return $row;
    }

    public static function create(array $data): int
    {
        return parent::create(self::encryptFields($data));
    }

    public static function update(int $id, array $data): void
    {
        parent::update($id, self::encryptFields($data));
    }

    public static function find(int $id): ?array
    {
        $row = parent::find($id);
        return $row === null ? null : self::decryptRow($row);
    }

    /**
     * Laat velden die leeg zijn ingevuld, of niet afwijken van de huidige waarde, weg uit een
     * update — zo overschrijft een per ongeluk leeg gelaten veld (bv. omdat een ander formulier op
     * dezelfde pagina werd verzonden) nooit een eerder ingevulde waarde. Gebruikt door zowel
     * TicketController (bewerken/escalatie) als TicketLogController (opmerking + status + escalatie
     * delen dezelfde formulier-submit).
     */
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

    // 'opgelost'/'gesloten' zijn oude statuswaarden van vóór het samenvoegen tot 'afgehandeld' (zie
    // TicketExcel::STATUS_ALIASSEN). Bestaande rijen met die waarde worden hier genormaliseerd naar
    // 'afgehandeld' zodat filtering/weergave overal consistent blijft, ook zonder dataconversie.
    private const SELECT = "
        SELECT t.*, a.naam AS afdeling_naam, b.naam AS behandelaar_naam,
            CASE WHEN t.status IN ('opgelost', 'gesloten') THEN 'afgehandeld' ELSE t.status END AS status
        FROM tickets t
        LEFT JOIN afdelingen a ON a.id = t.afdeling_id
        LEFT JOIN users b ON b.id = t.behandelaar_id
        WHERE t.deleted_at IS NULL
    ";

    public static function allWithRelations(): array
    {
        $sql = self::SELECT . ' ORDER BY t.created_at DESC';
        return array_map([self::class, 'decryptRow'], Database::pdo()->query($sql)->fetchAll());
    }

    public static function recent(int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' ORDER BY t.created_at DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map([self::class, 'decryptRow'], $stmt->fetchAll());
    }

    /**
     * Actieve tickets voor het dashboard: eerst alles 'in_behandeling', dan alles 'open',
     * dan alles 'wacht_op_info' — elk blok intern gesorteerd op meest recent. Afgehandelde
     * (incl. de oude 'opgelost'/'gesloten' waarden) tickets komen hier nooit in voor.
     */
    public static function actief(int $limit = 5): array
    {
        $join = 'LEFT JOIN afdelingen a ON a.id = t.afdeling_id LEFT JOIN users b ON b.id = t.behandelaar_id';
        $branch = fn (string $status, int $volgorde) => "
            (SELECT t.*, a.naam AS afdeling_naam, b.naam AS behandelaar_naam, {$volgorde} AS volgorde
             FROM tickets t {$join}
             WHERE t.deleted_at IS NULL AND t.status = '{$status}'
             ORDER BY t.created_at DESC)
        ";

        $sql = 'SELECT * FROM ('
            . $branch('in_behandeling', 0) . 'UNION ALL' . $branch('open', 1) . 'UNION ALL' . $branch('wacht_op_info', 2)
            . ') gecombineerd ORDER BY volgorde ASC, created_at DESC LIMIT ?';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return array_map([self::class, 'decryptRow'], $stmt->fetchAll());
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND t.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : self::decryptRow($row);
    }

    public static function countByStatus(string $status): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM tickets WHERE status = ? AND deleted_at IS NULL');
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    public static function setCreatedAt(int $id, string $date): void
    {
        $stmt = Database::pdo()->prepare('UPDATE tickets SET created_at = ? WHERE id = ?');
        $stmt->execute([$date . ' 00:00:00', $id]);
    }

    /**
     * opdrachtgever_naam staat versleuteld (niet-deterministisch) opgeslagen, dus kan niet
     * met SQL WHERE/LIKE vergeleken worden. Filtert daarom op titel (onversleuteld) in SQL —
     * een kleine kandidatenset — en vergelijkt opdrachtgever_naam na decryptie in PHP.
     */
    public static function existsByTitelEnOpdrachtgever(string $titel, string $opdrachtgever): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT opdrachtgever_naam FROM tickets WHERE LOWER(titel) = LOWER(?) AND deleted_at IS NULL'
        );
        $stmt->execute([trim($titel)]);

        return self::matchesAnyOpdrachtgever($stmt->fetchAll(\PDO::FETCH_COLUMN), $opdrachtgever);
    }

    /** Dedupe-check voor de e-mail-intake (zie TicketEmailIntakeController): zelfde afzender+titel in de afgelopen $dagen. */
    public static function existsRecentByAfzenderEnTitel(string $titel, string $afzender, int $dagen = 30): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT opdrachtgever_naam FROM tickets
             WHERE LOWER(titel) = LOWER(?) AND created_at >= (NOW() - INTERVAL ? DAY) AND deleted_at IS NULL'
        );
        $stmt->execute([trim($titel), $dagen]);

        return self::matchesAnyOpdrachtgever($stmt->fetchAll(\PDO::FETCH_COLUMN), $afzender);
    }

    private static function matchesAnyOpdrachtgever(array $encryptedOpdrachtgevers, string $opdrachtgever): bool
    {
        $target = mb_strtolower(trim($opdrachtgever));
        foreach ($encryptedOpdrachtgevers as $encrypted) {
            if (mb_strtolower(trim(FieldEncryptor::decrypt($encrypted))) === $target) {
                return true;
            }
        }
        return false;
    }

    /**
     * Groepeert tickets op titel+opdrachtgever (na decryptie) om duplicaten te vinden — gebruikt
     * door database/dedupe_tickets.php. Leest alle actieve tickets, want de groepering kan niet
     * in SQL (opdrachtgever_naam is versleuteld); voor een intranet-ticketvolume is dit prima.
     */
    public static function findDuplicateGroups(): array
    {
        $stmt = Database::pdo()->query('SELECT titel, opdrachtgever_naam FROM tickets WHERE deleted_at IS NULL');

        $groups = [];
        foreach ($stmt->fetchAll() as $row) {
            $titelKey = mb_strtolower(trim($row['titel']));
            $opdrachtgeverKey = mb_strtolower(trim(FieldEncryptor::decrypt($row['opdrachtgever_naam'])));
            $key = $titelKey . "\0" . $opdrachtgeverKey;

            $groups[$key] ??= ['titel_key' => $titelKey, 'opdrachtgever_key' => $opdrachtgeverKey, 'aantal' => 0];
            $groups[$key]['aantal']++;
        }

        return array_values(array_filter($groups, fn (array $g) => $g['aantal'] > 1));
    }

    /**
     * Globale zoekbalk (zie App\Shared\Search\SearchService) — zoekt alleen op titel, want
     * omschrijving/opdrachtgever_naam staan versleuteld in de database (zie VERSLEUTELDE_VELDEN) en
     * zijn dus niet met SQL LIKE te doorzoeken. Past dezelfde scope toe als TicketService::scopeAllowed()
     * (admin ziet alles, anderen alleen eigen afdeling/tickets) — direct in SQL i.p.v. achteraf in PHP.
     */
    public static function search(string $q, array $currentUser, int $limit = 5): array
    {
        $sql = 'SELECT id, titel, status FROM tickets WHERE deleted_at IS NULL AND titel LIKE ?';
        $params = ['%' . $q . '%'];

        if (($currentUser['rol'] ?? '') !== 'admin') {
            $sql .= ' AND (afdeling_id <=> ? OR behandelaar_id = ? OR aangemaakt_door_id = ?)';
            array_push($params, $currentUser['afdeling_id'] ?? null, (int) ($currentUser['id'] ?? 0), (int) ($currentUser['id'] ?? 0));
        }

        $sql .= ' ORDER BY updated_at DESC LIMIT ' . (int) $limit;

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Zoekt een ticket op escalatienummer (bv. het CAS-nummer uit een ACA-case-update-mail, zie TicketEmailIntakeController::storeAcaUpdate). */
    public static function findByEscalatieNummer(string $escalatieNummer): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM tickets WHERE escalatie_nummer = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$escalatieNummer]);
        $row = $stmt->fetch();
        return $row === false ? null : self::decryptRow($row);
    }

    public static function findByTitelEnOpdrachtgeverKey(string $titelKey, string $opdrachtgeverKey): array
    {
        $stmt = Database::pdo()->prepare("
            SELECT t.*, (SELECT COUNT(*) FROM ticket_logs l WHERE l.ticket_id = t.id) AS log_count
            FROM tickets t
            WHERE LOWER(TRIM(t.titel)) = ? AND t.deleted_at IS NULL
            ORDER BY t.id ASC
        ");
        $stmt->execute([$titelKey]);

        $rows = array_map(fn (array $r) => self::decryptRow($r), $stmt->fetchAll());

        return array_values(array_filter(
            $rows,
            fn (array $r) => mb_strtolower(trim($r['opdrachtgever_naam'])) === $opdrachtgeverKey
        ));
    }
}
