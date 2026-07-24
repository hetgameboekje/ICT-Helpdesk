<?php

namespace App\Api\V1;

use App\Shared\Auth\AuthService;
use App\Shared\Auth\Models\PersonalAccessTokenModel;

/**
 * Presentation/API-laag voor niet-browserclients (CLI/desktop/Android): geeft een bearer-token uit
 * i.p.v. de sessiecookie die de server-rendered routes en de browser-JS gebruiken (zie de
 * auth-uitleg in ApiController). Geen CSRF-check nodig — de Router stelt alle `/api/*`-paden daar al
 * van vrij, en er bestaat vóór het inloggen sowieso nog geen sessie om een token uit te lezen.
 */
class AuthApiController extends ApiController
{
    public function login(): void
    {
        $this->handle(function () {
            $body = $this->jsonBody();
            $email = trim((string) ($body['email'] ?? ''));
            $wachtwoord = (string) ($body['wachtwoord'] ?? '');
            $deviceNaam = trim((string) ($body['device_naam'] ?? ''));
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            try {
                $result = (new AuthService())->attemptLogin($email, $wachtwoord, $ip, $userAgent);
            } catch (\PDOException $e) {
                error_log('[Login] databaseverbinding mislukt: ' . $e->getMessage());
                $this->error(503, 'Inloggen is momenteel niet mogelijk. Probeer het later opnieuw.');
                return;
            }

            if ($result['locked']) {
                $this->error(429, 'Te veel mislukte inlogpogingen. Probeer het over enkele minuten opnieuw.');
                return;
            }
            if (!$result['success']) {
                $this->error(401, 'E-mailadres of wachtwoord is onjuist.');
                return;
            }

            $token = PersonalAccessTokenModel::generate((int) $result['user']['id'], $deviceNaam);

            $this->success([
                'token' => $token['plaintext'],
                'user' => AuthService::userPayload($result['user']),
            ], status: 201);
        });
    }

    public function logout(): void
    {
        $this->handle(function () {
            $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            $plainToken = str_starts_with($header, 'Bearer ') ? trim(substr($header, strlen('Bearer '))) : '';
            if ($plainToken === '') {
                $this->error(401, 'Geen bearer-token meegegeven.');
                return;
            }

            $this->requireAuth();
            PersonalAccessTokenModel::intrekken($plainToken);

            $this->success(null, status: 204);
        });
    }
}
