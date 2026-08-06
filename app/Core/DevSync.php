<?php

namespace App\Core;

/**
 * Bij het laden van /login wordt het databaseschema altijd stilzwijgend geparsed + toegepast
 * (additive-only: CREATE TABLE IF NOT EXISTS + ontbrekende kolommen toevoegen, nooit een bestaand
 * kolomtype wijzigen — zie SchemaParser::applyToDatabase) zodat een deploy (ook op Hostnet, zonder
 * shell-toegang) na de eerstvolgende login automatisch de live database bijwerkt naar de XML-stand
 * in de gedeployde code, zonder handmatige stap via de Beheer-pagina. In dev-modus (config
 * 'dev' => true) wordt daarnaast ook "git pull" gedaan. Alles wordt alleen naar error_log
 * geschreven, nooit naar de response — een mislukte sync mag een login nooit blokkeren.
 */
class DevSync
{
    public static function isEnabled(): bool
    {
        $config = require APP_ROOT . '/config/config.php';
        return (bool) ($config['dev'] ?? false);
    }

    public static function isGitPullEnabled(): bool
    {
        $config = require APP_ROOT . '/config/config.php';
        return (bool) ($config['gitPullEnabled'] ?? false);
    }

    /** @return string[] logregels, alleen voor foutopsporing (wordt niet aan gebruikers getoond) */
    public static function run(): array
    {
        $log = [];

        if (self::isGitPullEnabled()) {
            $huidigeMap = getcwd();
            chdir(APP_ROOT);
            $output = [];
            $exitCode = 0;
            exec('git pull 2>&1', $output, $exitCode);
            chdir($huidigeMap);
            $log[] = 'git pull (' . ($exitCode === 0 ? 'OK' : 'FOUT') . '): ' . implode(' | ', $output);
        }

        try {
            $sql = SchemaParser::generateSql();
            SchemaParser::writeSchemaFile($sql);
            $result = SchemaParser::applyToDatabase($sql);
            $log[] = "database parsen: {$result['applied']} statement(s) uitgevoerd, {$result['skipped']} overgeslagen";
        } catch (\Throwable $e) {
            $log[] = 'database parsen mislukt: ' . $e->getMessage();
        }

        return $log;
    }
}
