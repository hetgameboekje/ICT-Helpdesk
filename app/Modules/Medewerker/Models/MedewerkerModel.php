<?php

namespace App\Modules\Medewerker\Models;

use App\Core\Database;
use App\Core\Model;

class MedewerkerModel extends Model
{
    protected static string $table = 'medewerkers';
    protected static array $fillable = [
        'voornaam', 'achternaam', 'email', 'telefoon', 'functie', 'afdeling_id', 'manager_id', 'is_keyuser',
        'startdatum', 'status', 'user_id', 'apparaat_hostnames',
    ];
    protected static bool $softDeletes = true;

    private const SELECT = "
        SELECT m.*, a.naam AS afdeling_naam, u.naam AS login_naam, u.email AS login_email, u.deleted_at AS login_deleted_at,
               CONCAT(mgr.voornaam, ' ', mgr.achternaam) AS manager_naam
        FROM medewerkers m
        LEFT JOIN afdelingen a ON a.id = m.afdeling_id
        LEFT JOIN users u ON u.id = m.user_id
        LEFT JOIN medewerkers mgr ON mgr.id = m.manager_id
        WHERE m.deleted_at IS NULL
    ";

    public static function allWithRelations(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY m.achternaam ASC')->fetchAll();
    }

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND m.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * 'gevonden' — actieve login met dit e-mailadres bestaat en is nog aan geen (andere) medewerker gekoppeld.
     * 'bezet' — de login bestaat, maar is al aan een andere medewerker gekoppeld.
     * 'niet_gevonden' — geen actieve login met dit e-mailadres.
     */
    public static function loginStatusVoorEmail(string $email, ?int $exceptMedewerkerId = null): string
    {
        $userId = self::userIdVoorEmail($email);
        if ($userId === null) {
            return 'niet_gevonden';
        }

        $sql = 'SELECT 1 FROM medewerkers WHERE user_id = ? AND deleted_at IS NULL';
        $params = [$userId];
        if ($exceptMedewerkerId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptMedewerkerId;
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false ? 'bezet' : 'gevonden';
    }

    /** Gebruikt door de CSV-import om te bepalen of een rij een bestaande medewerker bijwerkt of een nieuwe aanmaakt. */
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM medewerkers WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public static function userIdVoorEmail(string $email): ?int
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    /** Afdeling van de medewerker gekoppeld aan deze login — gebruikt om $_SESSION['user']['afdeling_id'] te vullen. */
    public static function afdelingIdVoorUser(int $userId): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT afdeling_id FROM medewerkers WHERE user_id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? null : (int) $value;
    }

    /** Alle actieve medewerkers met naam, afdeling en manager-naam, voor de hiërarchie-view. */
    public static function alleVoorHierarchie(): array
    {
        $sql = "
            SELECT m.id, m.voornaam, m.achternaam, m.functie, m.afdeling_id, m.manager_id, m.is_keyuser,
                   a.naam AS afdeling_naam
            FROM medewerkers m
            LEFT JOIN afdelingen a ON a.id = m.afdeling_id
            WHERE m.deleted_at IS NULL
            ORDER BY m.achternaam ASC
        ";
        return Database::pdo()->query($sql)->fetchAll();
    }

    /** Medewerkers als keuzelijst voor een manager-select, met optionele uitsluiting (zichzelf niet als eigen manager). */
    public static function alleVoorManagerSelect(?int $exceptId = null): array
    {
        $sql = 'SELECT id, voornaam, achternaam FROM medewerkers WHERE deleted_at IS NULL';
        $params = [];
        if ($exceptId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $exceptId;
        }
        $sql .= ' ORDER BY achternaam ASC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Actieve medewerkers gemarkeerd als keyuser, voor koppeling vanuit bv. Urenstaat. */
    public static function alleKeyusers(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT id, voornaam, achternaam FROM medewerkers
             WHERE deleted_at IS NULL AND is_keyuser = 1 ORDER BY achternaam ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Actieve medewerkers met een gekoppelde login, met het aantal tickets dat nu bij hen "in
     * behandeling" staat — voor het "Team vandaag"-paneel in de Agenda-module (zie
     * AgendaController::index()). Alleen medewerkers met minstens 1 in-behandeling-ticket eerst,
     * dan de rest, zodat de drukste collega's bovenaan staan.
     */
    public static function alleMetInBehandelingTellingen(int $limit = 8): array
    {
        $sql = "
            SELECT m.id, m.voornaam, m.achternaam, m.functie,
                   (SELECT COUNT(*) FROM tickets t WHERE t.behandelaar_id = m.user_id AND t.status = 'in_behandeling' AND t.deleted_at IS NULL) AS in_behandeling
            FROM medewerkers m
            WHERE m.deleted_at IS NULL AND m.status = 'actief' AND m.user_id IS NOT NULL
            ORDER BY in_behandeling DESC, m.achternaam ASC
            LIMIT ?
        ";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function searchNamen(string $q): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT CONCAT(voornaam, ' ', achternaam) AS naam FROM medewerkers
             WHERE CONCAT(voornaam, ' ', achternaam) LIKE ? AND deleted_at IS NULL ORDER BY achternaam ASC LIMIT 10"
        );
        $stmt->execute(['%' . $q . '%']);
        return array_column($stmt->fetchAll(), 'naam');
    }
}
