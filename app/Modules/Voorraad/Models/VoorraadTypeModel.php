<?php

namespace App\Modules\Voorraad\Models;

use App\Core\Database;
use App\Core\Model;

class VoorraadTypeModel extends Model
{
    protected static string $table = 'voorraad_types';
    protected static array $fillable = ['naam', 'code'];

    public static function all(string $orderBy = 'naam ASC'): array
    {
        return parent::all($orderBy);
    }

    /** Vangnet-type voor uitgiftes van items die niet in de voorraadcatalogus staan (zie UitgifteController::store()). */
    public static function findOrCreateOverig(): int
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM voorraad_types WHERE LOWER(naam) = LOWER(?)');
        $stmt->execute(['Overig']);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        return self::create(['naam' => 'Overig', 'code' => 'OVERIG']);
    }

    public static function findByNaam(string $naam): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM voorraad_types WHERE LOWER(naam) = LOWER(?) LIMIT 1');
        $stmt->execute([$naam]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
