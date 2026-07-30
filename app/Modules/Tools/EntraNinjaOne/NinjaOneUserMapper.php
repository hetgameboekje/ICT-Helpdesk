<?php

namespace App\Modules\Tools\EntraNinjaOne;

use App\Modules\Tools\EntraNinjaOne\Exceptions\SkippedRowException;

/**
 * Zet één Entra-rij (uit EntraCsvParser::parseEntraCsv()) om naar de NinjaOne-vorm
 * {firstName, lastName, email, phone}, of gooit een SkippedRowException als de rij niet aan de
 * business rules voldoet. Puur functioneel (geen HTTP/sessie/bestand-I/O), dus per methode apart te
 * unit-testen — zie scripts/tests/entra_naar_ninjaone_voorbeeld.php voor voorbeeldaanroepen.
 *
 * "Last name" is verplicht (NinjaOne-import vereist dit veld) — een rij waarvoor geen betrouwbare
 * achternaam is af te leiden (bv. een gedeelde mailbox als inkoop@vhe.nl) wordt daarom uitgesloten
 * i.p.v. met een lege achternaam meegenomen, zie resolveName().
 */
class NinjaOneUserMapper
{
    /** Entra-kolomnamen (lowercase, zie EntraCsvParser) die eventueel een telefoonnummer bevatten. */
    private const TELEFOON_KOLOMMEN = ['mobilephone', 'businessphones', 'telephonenumber', 'phone'];

    /**
     * @param array<string, mixed> $entraRow rij uit EntraCsvParser::parseEntraCsv() (lowercase sleutels)
     * @param callable|null $nameSplitter optionele eigen split-logica, zie splitName()
     * @param bool $includeGuests als false (standaard) worden rijen met userType != 'Member' geweigerd
     * @return array{firstName:string,lastName:string,email:string,phone:string}
     * @throws SkippedRowException als de rij niet meegenomen mag worden — getMessage() is de reden
     */
    public static function mapToNinjaOneUser(array $entraRow, ?callable $nameSplitter = null, bool $includeGuests = false): array
    {
        $upn = trim((string) ($entraRow['userprincipalname'] ?? ''));
        if ($upn === '') {
            throw new SkippedRowException('Ontbrekende userPrincipalName.');
        }
        if (filter_var($upn, FILTER_VALIDATE_EMAIL) === false) {
            throw new SkippedRowException("Ongeldig e-mailadres in userPrincipalName: \"{$upn}\".");
        }

        $userType = trim((string) ($entraRow['usertype'] ?? ''));
        if (!$includeGuests && strcasecmp($userType, 'Member') !== 0) {
            $weergave = $userType !== '' ? $userType : '(leeg)';
            throw new SkippedRowException("Gebruikerstype \"{$weergave}\" is geen 'Member' — guests worden standaard uitgesloten.");
        }

        $naam = self::resolveName($entraRow, $upn, $nameSplitter);
        if ($naam === null || trim($naam['lastName']) === '') {
            // "Last name" is verplicht in NinjaOne — een gedeelde/functionele mailbox zoals
            // inkoop@vhe.nl (geen "voor.achter"-patroon in de e-mail én geen meerwoordige
            // displayName) levert dus nooit een geldige rij op, i.p.v. met een lege achternaam
            // te worden meegenomen (bewuste correctie t.o.v. de eerdere versie van deze klasse).
            throw new SkippedRowException(
                "Kan geen achternaam afleiden voor \"{$upn}\" (displayName: \"" . (string) ($entraRow['displayname'] ?? '') . "\") "
                . '— waarschijnlijk een gedeelde/functionele mailbox, geen echte gebruiker.'
            );
        }

        return [
            'firstName' => $naam['firstName'],
            'lastName' => $naam['lastName'],
            'email' => $upn,
            'phone' => self::extractPhone($entraRow),
        ];
    }

    /**
     * Bepaalt firstName/lastName uit de beschikbare bronnen, met de displayName als voorkeursbron
     * (natuurlijke hoofdlettergebruik, bewaart tussenvoegsels als "de Vries") en het
     * e-mail-patroon (`voornaam.achternaam@domein`, zoals in dit bedrijf gebruikt) als terugval
     * zodra de displayName geen bruikbare twee-woorden-naam oplevert (ontbrekend, één woord, of een
     * generieke naam als "Inkoop"/"Projectenbureau"). Bij een "afkorting"-e-mailadres zonder punt
     * (bv. mkee@vhe.nl) levert het e-mail-patroon niets op, dus dan telt alleen de displayName nog.
     *
     * @return array{firstName:string,lastName:string}|null null als geen van beide bronnen een
     *     bruikbare (niet-lege) achternaam oplevert
     */
    private static function resolveName(array $entraRow, string $upn, ?callable $nameSplitter): ?array
    {
        $displayName = self::normalizeDisplayName((string) ($entraRow['displayname'] ?? ''));
        if ($displayName !== '') {
            $viaDisplayName = self::splitName($displayName, $nameSplitter);
            if (trim($viaDisplayName['lastName']) !== '') {
                return $viaDisplayName;
            }
        }

        return self::deriveNameFromEmailLocalPart($upn);
    }

    /**
     * Leidt firstName/lastName af uit het lokale deel van een e-mailadres volgens de
     * `voornaam.achternaam@domein`-conventie. Geeft null terug zodra het lokale deel geen punt
     * bevat (een "afkorting" zoals "mkee") — er is dan simpelweg niets af te leiden uit de e-mail
     * zelf. Een eventueel derde/vierde punt-gescheiden deel (tussenvoegsels als "jan.van.dijk")
     * wordt samengevoegd tot de achternaam i.p.v. genegeerd.
     *
     * @return array{firstName:string,lastName:string}|null
     */
    private static function deriveNameFromEmailLocalPart(string $upn): ?array
    {
        $atPositie = strpos($upn, '@');
        $lokaalDeel = $atPositie === false ? $upn : substr($upn, 0, $atPositie);

        if (!str_contains($lokaalDeel, '.')) {
            return null;
        }

        [$voorRuw, $achterRuw] = array_pad(explode('.', $lokaalDeel, 2), 2, '');
        $voor = trim($voorRuw);
        $achter = trim(str_replace('.', ' ', $achterRuw));

        if ($voor === '' || $achter === '') {
            return null;
        }

        return [
            'firstName' => self::titleCase($voor),
            'lastName' => self::titleCase($achter),
        ];
    }

    /** Zet "jan"/"van dijk" om naar "Jan"/"Van Dijk" — eenvoudige titel-hoofdletter per woord. */
    private static function titleCase(string $waarde): string
    {
        return implode(' ', array_map(
            static fn (string $woord): string => $woord === ''
                ? ''
                : mb_strtoupper(mb_substr($woord, 0, 1)) . mb_strtolower(mb_substr($woord, 1)),
            explode(' ', $waarde)
        ));
    }

    /**
     * Normaliseert een ruwe displayName: trimt, strip een voorloop van niet-letter/cijfer-tekens
     * (bv. "# Projectenbureau" -> "Projectenbureau" — Entra-exports bevatten soms een voorvoegsel-
     * symbool bij gedeelde mailboxen/resource-accounts) en vouwt meervoudige spaties samen. Bewust
     * géén unicode-stripping van accenten/diakrieten — dat zou echte namen (bv. "Renée") beschadigen.
     */
    public static function normalizeDisplayName(string $raw): string
    {
        $value = trim($raw);
        $value = preg_replace('/^[^\p{L}\p{N}]+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Splitst een genormaliseerde displayName in firstName/lastName: eerste woord = firstName, de
     * rest = lastName; bij één woord blijft lastName leeg. `$customSplitter` is de haak om hier later
     * eigen split-logica voor te zetten (bv. "Achternaam, Voornaam"-notatie) zonder deze klasse te
     * hoeven wijzigen — signatuur: `fn(string $displayName): array{firstName:string,lastName:string}`.
     *
     * @return array{firstName:string,lastName:string}
     */
    public static function splitName(string $displayName, ?callable $customSplitter = null): array
    {
        if ($customSplitter !== null) {
            return $customSplitter($displayName);
        }

        $delen = preg_split('/\s+/u', trim($displayName), -1, PREG_SPLIT_NO_EMPTY);
        if ($delen === false || $delen === []) {
            return ['firstName' => '', 'lastName' => ''];
        }

        $eerste = array_shift($delen);

        return [
            'firstName' => $eerste,
            'lastName' => implode(' ', $delen),
        ];
    }

    /**
     * Zoekt een telefoonnummer in de gebruikelijke Entra-exportkolommen (geen van deze staat in het
     * minimale kolomvoorbeeld uit de aanvraag, dus dit is een bewuste, veilige aanname: als geen van
     * deze kolommen bestaat of gevuld is, blijft phone leeg — nooit een fout, telefoon is optioneel).
     */
    private static function extractPhone(array $entraRow): string
    {
        foreach (self::TELEFOON_KOLOMMEN as $kolom) {
            $waarde = trim((string) ($entraRow[$kolom] ?? ''), " \t\n\r\0\x0B[]\"");
            if ($waarde !== '') {
                return $waarde;
            }
        }

        return '';
    }
}
