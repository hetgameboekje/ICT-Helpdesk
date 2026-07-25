<?php

namespace App\Modules\Kennisbank\Models;

use App\Core\Database;
use App\Core\Model;

class KennisbankModel extends Model
{
    protected static string $table = 'kennisbank_artikelen';
    protected static array $fillable = ['titel', 'categorie', 'subcategorie', 'samenvatting', 'tags', 'inhoud', 'auteur_id'];
    protected static bool $softDeletes = true;

    private const SELECT = "
        SELECT k.*, u.naam AS auteur_naam
        FROM kennisbank_artikelen k
        LEFT JOIN users u ON u.id = k.auteur_id
        WHERE k.deleted_at IS NULL
    ";

    public static function allWithRelations(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY k.created_at DESC')->fetchAll();
    }

    /** Globale zoekbalk (zie App\Shared\Search\SearchService) — geen scope-autorisatie per item, zie klasse-docblock elders. */
    public static function search(string $q, int $limit = 5): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, titel, categorie FROM kennisbank_artikelen
             WHERE deleted_at IS NULL AND (titel LIKE ? OR samenvatting LIKE ? OR tags LIKE ?)
             ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        $needle = '%' . $q . '%';
        $stmt->execute([$needle, $needle, $needle]);
        return $stmt->fetchAll();
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

    public static function findWithRelations(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' AND k.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Artikelen die al langer dan X maanden niet meer bijgewerkt zijn — voor de dashboard-KPI. */
    public static function countOutdated(int $maanden = 6): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM kennisbank_artikelen WHERE deleted_at IS NULL AND updated_at < DATE_SUB(NOW(), INTERVAL ? MONTH)'
        );
        $stmt->execute([$maanden]);
        return (int) $stmt->fetchColumn();
    }

    /** Bestaande categorieën, voor het voorstellen van dezelfde naam bij tickets (zie TicketController). */
    public static function distinctCategorieen(string $q = ''): array
    {
        $sql = "SELECT DISTINCT categorie FROM kennisbank_artikelen WHERE deleted_at IS NULL";
        $params = [];
        if ($q !== '') {
            $sql .= " AND categorie LIKE ?";
            $params[] = '%' . $q . '%';
        }
        $sql .= " ORDER BY categorie ASC";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Bestaande subcategorieën binnen een categorie, voor de autocomplete in create/edit. */
    public static function distinctSubcategorieen(string $categorie, string $q = ''): array
    {
        $sql = "SELECT DISTINCT subcategorie FROM kennisbank_artikelen
                WHERE deleted_at IS NULL AND subcategorie IS NOT NULL AND subcategorie <> ''
                AND categorie = ?";
        $params = [$categorie];
        if ($q !== '') {
            $sql .= " AND subcategorie LIKE ?";
            $params[] = '%' . $q . '%';
        }
        $sql .= " ORDER BY subcategorie ASC";

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Alle losse tags over alle artikelen heen (tags staan comma-separated in de kolom), voor autocomplete. */
    public static function distinctTags(string $q = ''): array
    {
        $stmt = Database::pdo()->query(
            "SELECT tags FROM kennisbank_artikelen WHERE deleted_at IS NULL AND tags IS NOT NULL AND tags <> ''"
        );

        $tags = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $raw) {
            foreach (self::splitTags($raw) as $tag) {
                $tags[mb_strtolower($tag)] = $tag;
            }
        }

        if ($q !== '') {
            $needle = mb_strtolower($q);
            $tags = array_filter($tags, fn (string $tag) => str_contains(mb_strtolower($tag), $needle));
        }

        $tags = array_values($tags);
        sort($tags, SORT_STRING | SORT_FLAG_CASE);

        return $tags;
    }

    /** Splitst de comma-separated tags-kolom naar een schone array (getrimd, lege waarden eruit). */
    public static function splitTags(?string $tags): array
    {
        if ($tags === null || trim($tags) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $tags)), fn (string $t) => $t !== ''));
    }

    /** Normaliseert vrije tags-invoer (comma-separated string) naar een unieke, hoofdletterongevoelig gededupliceerde comma-separated string. */
    public static function normalizeTags(string $rawInput): ?string
    {
        $unique = [];
        foreach (self::splitTags($rawInput) as $tag) {
            $unique[mb_strtolower($tag)] ??= $tag;
        }

        return $unique === [] ? null : implode(', ', $unique);
    }

    /**
     * Categorie/subcategorie-boom met aantallen voor de kennisbank-navigatie (linkerkolom).
     * @return array<int, array{naam: string, aantal: int, subcategorieen: array<int, array{naam: string, aantal: int}>}>
     */
    public static function categorieBoom(): array
    {
        $rows = Database::pdo()->query(
            "SELECT categorie, subcategorie, COUNT(*) AS aantal
             FROM kennisbank_artikelen
             WHERE deleted_at IS NULL
             GROUP BY categorie, subcategorie"
        )->fetchAll();

        $boom = [];
        foreach ($rows as $row) {
            $categorie = $row['categorie'];
            if (!isset($boom[$categorie])) {
                $boom[$categorie] = ['naam' => $categorie, 'aantal' => 0, 'subcategorieen' => []];
            }
            $boom[$categorie]['aantal'] += (int) $row['aantal'];

            $sub = trim((string) ($row['subcategorie'] ?? ''));
            if ($sub !== '') {
                $boom[$categorie]['subcategorieen'][$sub] = ['naam' => $sub, 'aantal' => (int) $row['aantal']];
            }
        }

        ksort($boom, SORT_STRING | SORT_FLAG_CASE);
        foreach ($boom as &$cat) {
            ksort($cat['subcategorieen'], SORT_STRING | SORT_FLAG_CASE);
            $cat['subcategorieen'] = array_values($cat['subcategorieen']);
        }

        return array_values($boom);
    }
}
