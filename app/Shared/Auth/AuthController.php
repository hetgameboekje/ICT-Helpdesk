<?php

namespace App\Shared\Auth;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\DevSync;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        try {
            foreach (DevSync::run() as $line) {
                error_log('[DevSync] ' . $line);
            }
        } catch (\Throwable $e) {
            error_log('[DevSync] mislukt: ' . $e->getMessage());
        }

        if (!empty($_SESSION['user'])) {
            $this->redirect('/');
        }

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        ob_start();
        require APP_ROOT . '/app/Views/auth/login.php';
        $content = ob_get_clean();
        $csrfToken = Csrf::token();
        require APP_ROOT . '/app/Views/layouts/guest.php';
    }

    public function login(): void
    {
        $email = trim($_POST['email'] ?? '');
        $wachtwoord = $_POST['wachtwoord'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        try {
            $result = (new AuthService())->attemptLogin($email, $wachtwoord, $ip, $userAgent);
        } catch (\PDOException $e) {
            error_log('[Login] databaseverbinding mislukt: ' . $e->getMessage());
            $_SESSION['login_error'] = 'Inloggen is momenteel niet mogelijk (databaseverbinding mislukt). Probeer het later opnieuw.';
            $this->redirect('/login');
        }

        if ($result['locked']) {
            $_SESSION['login_error'] = 'Te veel mislukte inlogpogingen. Probeer het over enkele minuten opnieuw.';
            $this->redirect('/login');
        }
        if (!$result['success']) {
            $_SESSION['login_error'] = 'E-mailadres of wachtwoord is onjuist.';
            $this->redirect('/login');
        }

        $_SESSION['user'] = AuthService::userPayload($result['user']);

        $this->redirect('/');
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
        session_destroy();
        $this->redirect('/login');
    }
}
