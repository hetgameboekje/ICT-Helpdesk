<?php

namespace App\Shared\Auth;

use App\Core\Mailer;
use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Shared\Auth\Models\LoginAttemptModel;
use App\Shared\User\Models\UserModel;

/**
 * Credential-check + lockout/audit-logging, gedeeld door de sessie-login (AuthController, HTML) en de
 * bearer-token-login (App\Api\V1\AuthApiController, JSON) — beide moeten dezelfde
 * brute-force-bescherming en login_attempts-logging krijgen, dus die logica stond hier al vóór de
 * API-variant werd toegevoegd, i.p.v. 'm te dupliceren.
 */
class AuthService
{
    private const MAX_MISLUKTE_POGINGEN = 5;
    private const LOCKOUT_MINUTEN = 15;

    /** @return array{success:bool, locked:bool, user:?array} user heeft dezelfde vorm als UserModel::findByEmail(). */
    public function attemptLogin(string $email, string $wachtwoord, string $ip, ?string $userAgent): array
    {
        $mislukteAantal = LoginAttemptModel::recentFailedCount($email, self::LOCKOUT_MINUTEN);
        if ($mislukteAantal >= self::MAX_MISLUKTE_POGINGEN) {
            $this->logPoging($email, null, $ip, $userAgent, success: false, isNewIp: false);
            // Alleen bij het moment dat de lockout ingaat waarschuwen, niet bij elke volgende poging
            // tijdens de lockout (anders loopt de mailbox vol zolang iemand blijft proberen).
            if ($mislukteAantal === self::MAX_MISLUKTE_POGINGEN) {
                $this->waarschuwVerdachteLogin($email, $ip);
            }

            return ['success' => false, 'locked' => true, 'user' => null];
        }

        $user = UserModel::authenticate($email, $wachtwoord);
        if ($user === null) {
            $this->logPoging($email, null, $ip, $userAgent, success: false, isNewIp: false);

            return ['success' => false, 'locked' => false, 'user' => null];
        }

        $userId = (int) $user['id'];
        $isNewIp = LoginAttemptModel::hasAnyPriorSuccessfulLogin($userId)
            && !LoginAttemptModel::hasSuccessfulLoginFromIp($userId, $ip);
        $this->logPoging($email, $userId, $ip, $userAgent, success: true, isNewIp: $isNewIp);

        return ['success' => true, 'locked' => false, 'user' => $user];
    }

    /**
     * @return array{id:int,naam:string,rol:string,foto:?string,afdeling_id:?int} de vorm die zowel
     *     $_SESSION['user'] als de bearer-token-auth (App\Api\V1\ApiController::userFromBearerToken())
     *     gebruiken, zodat Service-laagcode (bv. TicketService::scopeAllowed()) niet hoeft te weten
     *     via welke authmodus de huidige gebruiker binnenkwam.
     */
    public static function userPayload(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'naam' => $user['naam'],
            'rol' => $user['rol'],
            'foto' => $user['foto'] ?? null,
            'afdeling_id' => MedewerkerModel::afdelingIdVoorUser((int) $user['id']),
        ];
    }

    private function logPoging(string $email, ?int $userId, string $ip, ?string $userAgent, bool $success, bool $isNewIp): void
    {
        try {
            LoginAttemptModel::record($email, $userId, $ip, $userAgent, $success, $isNewIp);
        } catch (\Throwable $e) {
            // Logging mag de normale afhandeling van het inloggen nooit breken.
        }
    }

    private function waarschuwVerdachteLogin(string $email, string $ip): void
    {
        $config = require APP_ROOT . '/config/config.php';
        $admin = $config['mail']['admin_address'];

        if ($admin === '') {
            return;
        }

        try {
            Mailer::verstuur(
                $admin,
                'Ticketsysteem Leen van Punt: verdachte inlogpogingen gedetecteerd',
                "Voor het account {$email} zijn binnen " . self::LOCKOUT_MINUTEN . " minuten "
                . self::MAX_MISLUKTE_POGINGEN . " mislukte inlogpogingen geregistreerd vanaf IP-adres {$ip}.<br>"
                . 'Het account is tijdelijk geblokkeerd. Bekijk Beheer &gt; Beveiliging voor details.'
            );
        } catch (\Throwable $e) {
            // Als zelfs de waarschuwing niet verstuurd kan worden, is er niets meer te doen vanuit hier.
        }
    }
}
