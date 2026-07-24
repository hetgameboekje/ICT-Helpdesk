<?php

namespace App\Api\V1;

use App\Core\Csrf;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Shared\Auth\AuthService;
use App\Shared\Auth\Models\PersonalAccessTokenModel;
use App\Shared\Rechten\Models\RechtenModel;

/**
 * Presentation/API-laag basisklasse: HTTP in/uit, JSON-envelope, statuscodes. Geen businesslogica —
 * die staat in de Service-laag (bv. App\Modules\Ticket\TicketService), die deze klasse aanroept en
 * waarvan de exceptions (ValidationException/NotFoundException/ForbiddenException) hieronder naar de
 * juiste HTTP-status worden vertaald.
 *
 * Auth: twee modi. (1) dezelfde sessiecookie als de server-rendered routes (same-origin, gebruikt
 * door de browser-JS in public/assets/js/pages/*) — CSRF wordt dan expliciet gecontroleerd omdat de
 * Router alle `/api/*`-paden standaard vrijstelt (bedoeld voor de bestaande machine-to-machine
 * endpoints met een API-sleutel). (2) een `Authorization: Bearer <token>`-header met een per-gebruiker
 * token (App\Shared\Auth\Models\PersonalAccessTokenModel, uitgegeven via POST /api/v1/auth/login) voor
 * niet-browserclients (CLI/desktop/Android) — geen CSRF-check nodig, want zo'n header kan een browser
 * nooit automatisch cross-site meesturen (dat is precies wat CSRF-tokens tegen sessiecookies dekken).
 */
abstract class ApiController
{
    /** True zodra requireAuth() een gebruiker via een bearer-token vond i.p.v. de sessiecookie. */
    private bool $viaToken = false;

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
        $tokenUser = $this->userFromBearerToken();
        if ($tokenUser !== null) {
            $this->viaToken = true;
            return $tokenUser;
        }

        $user = $_SESSION['user'] ?? null;
        if ($user === null) {
            $this->error(401, 'Niet ingelogd.');
            exit;
        }

        return $user;
    }

    /**
     * @return array{id:int,naam:string,rol:string,foto:?string,afdeling_id:?int}|null zelfde vorm als
     *     $_SESSION['user'] (zie AuthController::login()), zodat Service-laagcode als
     *     TicketService::scopeAllowed() niet hoeft te weten via welke authmodus de request binnenkwam.
     */
    private function userFromBearerToken(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? null) : null);
        if (!is_string($header) || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $plainToken = trim(substr($header, strlen('Bearer ')));
        $user = PersonalAccessTokenModel::vindGebruiker($plainToken);

        return $user === null ? null : AuthService::userPayload($user);
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

    /**
     * Voor POST/PUT/DELETE — GET-requests zijn side-effect-vrij en hebben geen CSRF-check nodig.
     * Overgeslagen bij bearer-token-auth: CSRF beschermt tegen een browser die ongevraagd de
     * sessiecookie meestuurt bij een cross-site request; een Authorization-header stuurt geen enkele
     * browser automatisch mee, dus dat scenario speelt hier niet.
     */
    protected function requireCsrf(): void
    {
        if ($this->viaToken) {
            return;
        }

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
