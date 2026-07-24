<?php

namespace App\Shared\Auth\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * Bearer-tokens voor niet-browserclients (CLI/desktop/Android) tegen `/api/v1/*` — los van de
 * sessiecookie die de server-rendered routes en de browser-JS gebruiken. Zelfde opzet als
 * App\Shared\ApiKey\Models\ApiKeyModel (hash+prefix, intrekken via soft delete), maar per gebruiker
 * i.p.v. een vlakke scope: een token draagt de identiteit van precies één account, dus de bestaande
 * rol-/afdelingsscope in de Service-laag (bv. TicketService::scopeAllowed()) blijft ongewijzigd gelden.
 */
class PersonalAccessTokenModel extends Model
{
    protected static string $table = 'personal_access_tokens';
    protected static array $fillable = ['user_id', 'naam', 'token_hash', 'token_prefix', 'laatst_gebruikt_at'];
    protected static bool $softDeletes = true;

    /** @return array{id:int,plaintext:string} */
    public static function generate(int $userId, string $naam): array
    {
        $plaintext = bin2hex(random_bytes(32));

        $id = self::create([
            'user_id' => $userId,
            'naam' => $naam !== '' ? $naam : 'Onbenoemd apparaat',
            'token_hash' => hash('sha256', $plaintext),
            'token_prefix' => substr($plaintext, 0, 8),
        ]);

        return ['id' => $id, 'plaintext' => $plaintext];
    }

    /** Vindt de gebruiker bij een actief, niet-ingetrokken token; registreert direct het gebruik. */
    public static function vindGebruiker(string $plainToken): ?array
    {
        if ($plainToken === '') {
            return null;
        }

        $stmt = Database::pdo()->prepare(
            "SELECT u.* FROM personal_access_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.deleted_at IS NULL AND u.deleted_at IS NULL"
        );
        $stmt->execute([hash('sha256', $plainToken)]);
        $user = $stmt->fetch();

        if ($user === false) {
            return null;
        }

        self::registreerGebruik($plainToken);

        return $user;
    }

    public static function intrekken(string $plainToken): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE personal_access_tokens SET deleted_at = NOW() WHERE token_hash = ? AND deleted_at IS NULL'
        );
        $stmt->execute([hash('sha256', $plainToken)]);
    }

    private static function registreerGebruik(string $plainToken): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE personal_access_tokens SET laatst_gebruikt_at = NOW() WHERE token_hash = ?'
        );
        $stmt->execute([hash('sha256', $plainToken)]);
    }
}
