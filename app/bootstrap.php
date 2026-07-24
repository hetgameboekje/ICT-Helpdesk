<?php

define('APP_ROOT', dirname(__DIR__));

// Nederlandse tijd voor date()/strtotime()/DateTime (incl. automatische zomertijd) i.p.v. de
// servertijdzone — zie ook App\Core\Database::pdo() voor de bijbehorende MySQL-sessie-tijdzone.
date_default_timezone_set('Europe/Amsterdam');

// .env is optioneel (staat in .gitignore) — handig om per omgeving (bv. Hostnet-productie)
// eigen DB_* / dev-instellingen te zetten zonder config/config.php aan te passen.
$envFile = APP_ROOT . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim(trim($value), "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}
unset($envFile);

// APP_ENV kiest welk omgevingsprofiel geldt (zie config/config.php) — hier alvast nodig om te
// weten of PHP-fouten getoond mogen worden, want dat gebeurt vóór config.php geladen wordt.
// hostnet = productie: nooit fouten tonen. Alles anders (incl. local) = tonen.
if (strtolower(trim((string) (getenv('APP_ENV') ?: 'local'))) !== 'hostnet') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    // SameSite=Lax voorkomt dat de sessiecookie meegestuurd wordt bij cross-site POSTs (de
    // klassieke CSRF-vector) — het CSRF-token (zie App\Core\Csrf) dekt de rest. Geen "Secure"
    // omdat deze omgeving over HTTP draait (zie public/assets/js/app.js voor dezelfde reden).
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
