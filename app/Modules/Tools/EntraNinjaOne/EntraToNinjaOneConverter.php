<?php

namespace App\Modules\Tools\EntraNinjaOne;

use App\Modules\Tools\EntraNinjaOne\Exceptions\SkippedRowException;

/**
 * Orchestreert NinjaOneUserMapper over alle rijen uit EntraCsvParser::parseEntraCsv(): verzamelt de
 * geldige NinjaOne-gebruikers én, apart, de overgeslagen rijen met reden — zodat de UI beide kan
 * tonen (preview + foutenlijst) zonder de mapper zelf HTTP/sessie te laten kennen.
 */
class EntraToNinjaOneConverter
{
    /**
     * @param array<int, array<string, mixed>> $rows uit EntraCsvParser::parseEntraCsv()
     * @param callable|null $nameSplitter zie NinjaOneUserMapper::splitName()
     * @param bool $includeGuests zie NinjaOneUserMapper::mapToNinjaOneUser()
     * @return array{
     *     users: array<int, array{firstName:string,lastName:string,email:string,phone:string}>,
     *     skipped: array<int, array{rij:int, reden:string, weergave:string}>
     * }
     */
    public static function convertRows(array $rows, ?callable $nameSplitter = null, bool $includeGuests = false): array
    {
        $users = [];
        $skipped = [];

        foreach (array_values($rows) as $i => $row) {
            try {
                $users[] = NinjaOneUserMapper::mapToNinjaOneUser($row, $nameSplitter, $includeGuests);
            } catch (SkippedRowException $e) {
                $skipped[] = [
                    // +2: 1-based regelnummer + de headerregel zelf meegeteld, zodat dit overeenkomt
                    // met het regelnummer dat je in het brongenerator (Excel/CSV-editor) ziet.
                    'rij' => $i + 2,
                    'reden' => $e->getMessage(),
                    'weergave' => (string) ($row['displayname'] ?? $row['userprincipalname'] ?? ''),
                ];
            }
        }

        return ['users' => $users, 'skipped' => $skipped];
    }
}
