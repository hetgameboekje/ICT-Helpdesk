<?php

namespace App\Modules\Tools\EntraNinjaOne;

/**
 * Zet een lijst NinjaOne-gebruikers ({firstName,lastName,email,phone}) om naar TAB-delimited tekst
 * in de exacte kolomvolgorde die NinjaOne's "Import technicians / end users" verwacht: First name,
 * Last name, Email, Phone. Bruikbaar zowel om te tonen in een copy/paste-textarea als om als
 * .tsv-bestand te downloaden — beide gebruiken dezelfde string.
 */
class NinjaOneExporter
{
    private const KOLOMMEN = ['First name', 'Last name', 'Email', 'Phone'];

    /** @param array<int, array{firstName:string,lastName:string,email:string,phone:string}> $users */
    public static function exportTabDelimited(array $users, bool $includeHeader = true): string
    {
        $regels = [];
        if ($includeHeader) {
            $regels[] = implode("\t", self::KOLOMMEN);
        }

        foreach ($users as $user) {
            $regels[] = implode("\t", [
                self::sanitizeVeld($user['firstName'] ?? ''),
                self::sanitizeVeld($user['lastName'] ?? ''),
                self::sanitizeVeld($user['email'] ?? ''),
                self::sanitizeVeld($user['phone'] ?? ''),
            ]);
        }

        return $regels === [] ? '' : implode("\r\n", $regels) . "\r\n";
    }

    /** Voorkomt dat een tab/newline in een veld de TAB-delimited kolomstructuur breekt. */
    private static function sanitizeVeld(string $waarde): string
    {
        return trim(str_replace(["\t", "\r", "\n"], ' ', $waarde));
    }
}
