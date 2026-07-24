<?php

namespace App\Api\V1;

use App\Core\Csrf;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Shared\Rechten\Models\RechtenModel;

/**
 * Presentation/API-laag basisklasse: HTTP in/uit, JSON-envelope, statuscodes. Geen businesslogica —
 * die staat in de Service-laag (bv. App\Modules\Ticket\TicketService), die deze klasse aanroept en
 * waarvan de exceptions (ValidationException/NotFoundException/ForbiddenException) hieronder naar de
 * juiste HTTP-status worden vertaald.
 *
 * Auth: dezelfde sessiecookie als de server-rendered routes (same-origin op Hostnet, geen apart
 * tokensysteem nodig). CSRF wordt hier expliciet gecontroleerd omdat de Router alle `/api/*`-paden
 * standaard vrijstelt van CSRF (bedoeld voor de bestaande machine-to-machine endpoints met een
 * API-sleutel) — deze sessie-geauthenticeerde routes hebben dus zelf een check nodig.
 */
abstract class ApiController
{
    protected function handle(\Closure $action): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $action();
        } catch (ValidationException $e) {
            $this->error(422, $e->getMessage(), $e->errors());
        } catch (NotFoundException $e) {
            $this->error(404, $e->getMessage());
        } catch (ForbiddenException $e) {
            $this->error(403, $e->getMessage());
        }
    }

    protected function requireAuth(): array
    {
        $user = $_SESSION['user'] ?? null;
        if ($user === null) {
            $this->error(401, 'Niet ingelogd.');
            exit;
        }

        return $user;
    }

    protected function requirePermission(array $currentUser, string $module, string $actie): void
    {
        if (($currentUser['rol'] ?? '') === 'admin') {
            return;
        }

        if (!RechtenModel::has((int) $currentUser['id'], $module, $actie)) {
            $this->error(403, 'Geen rechten voor deze actie.');
            exit;
        }
    }

    /** Voor POST/PUT/DELETE — GET-requests zijn side-effect-vrij en hebben geen CSRF-check nodig. */
    protected function requireCsrf(): void
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::verify(is_string($header) ? $header : null)) {
            $this->error(419, 'Ongeldig of verlopen beveiligingstoken. Herlaad de pagina en probeer het opnieuw.');
            exit;
        }
    }

    /** @return array<string,mixed> geparsete JSON-body, of lege array als er geen (geldige) JSON-body is. */
    protected function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function success(mixed $data, array $meta = [], int $status = 200): void
    {
        http_response_code($status);
        $payload = ['status' => 'success', 'data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }
        echo json_encode($payload);
    }

    protected function error(int $status, string $message, array $errors = []): void
    {
        http_response_code($status);
        $payload = ['status' => 'error', 'message' => $message];
        if ($errors !== []) {
            $payload['errors'] = $errors;
        }
        echo json_encode($payload);
    }
}
