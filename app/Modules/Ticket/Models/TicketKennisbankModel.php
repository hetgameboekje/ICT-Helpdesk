<?php

namespace App\Modules\Ticket\Models;

use App\Core\Database;

/** Koppeltabel tussen tickets en kennisbank_artikelen, zie ticket_kennisbank_artikelen.xml. */
class TicketKennisbankModel
{
    public static function gekoppeld(int $ticketId): array
    {
        $stmt = Database::pdo()->prepare("
            SELECT k.id, k.titel, k.categorie, tka.id AS koppeling_id
            FROM ticket_kennisbank_artikelen tka
            JOIN kennisbank_artikelen k ON k.id = tka.kennisbank_artikel_id
            WHERE tka.ticket_id = ? AND k.deleted_at IS NULL
            ORDER BY tka.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    /** Omgekeerde richting van gekoppeld(): tickets die aan een kennisbankartikel gekoppeld zijn. */
    public static function gekoppeldeTicketsVoorArtikel(int $artikelId): array
    {
        $stmt = Database::pdo()->prepare("
            SELECT t.id, t.titel, t.status
            FROM ticket_kennisbank_artikelen tka
            JOIN tickets t ON t.id = tka.ticket_id
            WHERE tka.kennisbank_artikel_id = ? AND t.deleted_at IS NULL
            ORDER BY tka.created_at ASC
        ");
        $stmt->execute([$artikelId]);
        return $stmt->fetchAll();
    }

    /** Artikelen uit dezelfde categorie als het ticket, die nog niet gekoppeld zijn. */
    public static function suggesties(int $ticketId, string $categorie, int $limiet = 5): array
    {
        $stmt = Database::pdo()->prepare("
            SELECT k.id, k.titel, k.categorie
            FROM kennisbank_artikelen k
            WHERE k.deleted_at IS NULL
              AND k.categorie = ?
              AND k.id NOT IN (
                  SELECT kennisbank_artikel_id FROM ticket_kennisbank_artikelen WHERE ticket_id = ?
              )
            ORDER BY k.created_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $categorie);
        $stmt->bindValue(2, $ticketId, \PDO::PARAM_INT);
        $stmt->bindValue(3, $limiet, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function koppel(int $ticketId, int $artikelId): void
    {
        $stmt = Database::pdo()->prepare('
            SELECT 1 FROM ticket_kennisbank_artikelen WHERE ticket_id = ? AND kennisbank_artikel_id = ?
        ');
        $stmt->execute([$ticketId, $artikelId]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        Database::pdo()
            ->prepare('INSERT INTO ticket_kennisbank_artikelen (ticket_id, kennisbank_artikel_id) VALUES (?, ?)')
            ->execute([$ticketId, $artikelId]);
    }

    public static function ontkoppel(int $ticketId, int $artikelId): void
    {
        Database::pdo()
            ->prepare('DELETE FROM ticket_kennisbank_artikelen WHERE ticket_id = ? AND kennisbank_artikel_id = ?')
            ->execute([$ticketId, $artikelId]);
    }
}
