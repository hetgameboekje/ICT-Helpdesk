<?php

namespace App\Modules\Tools\EntraNinjaOne;

/**
 * Parseert een Entra ID-gebruikersexport (CSV) naar rijen met lowercase kolomnamen als sleutel —
 * zelfde tolerante opzet als App\Modules\Schijfgebruik\SchijfgebruikImport: BOM op de eerste
 * headercel wordt gestript, lege regels worden overgeslagen, en een rij met meer/minder velden dan
 * de header wordt bijgeknipt/aangevuld i.p.v. een fatale array_combine()-fout te geven. Puur
 * bestand-naar-array — geen validatie/filtering hier, dat is de taak van NinjaOneUserMapper.
 */
class EntraCsvParser
{
    /**
     * @return array<int, array<string, string>> één array per rij, sleutels = lowercase kolomnamen
     *     uit de header (bv. 'displayname', 'userprincipalname', 'usertype', 'onpremisessyncenabled')
     */
    public static function parseEntraCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Kan het CSV-bestand niet lezen.');
        }

        $headerLine = fgetcsv($handle);
        if ($headerLine === false || $headerLine === null) {
            fclose($handle);
            throw new \RuntimeException('Het CSV-bestand is leeg.');
        }

        // Entra-/Excel-exports beginnen vaak met een UTF-8 BOM, die anders aan de eerste
        // kolomnaam blijft plakken (bv. "\xEF\xBB\xBFid" i.p.v. "id").
        $headerLine[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerLine[0]) ?? $headerLine[0];
        $headers = array_map(static fn ($h) => strtolower(trim((string) $h)), $headerLine);
        $kolomAantal = count($headers);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue; // lege regel
            }

            // Beschermt tegen een array_combine()-fatal als een rij toevallig meer/minder velden
            // heeft dan de header (bv. een extra komma in een niet-gequote vrije-tekstkolom).
            $row = array_slice(array_pad($row, $kolomAantal, ''), 0, $kolomAantal);
            $rows[] = array_combine($headers, $row);
        }
        fclose($handle);

        if ($rows === []) {
            throw new \RuntimeException('Geen gebruikersrijen gevonden in dit CSV-bestand.');
        }

        return $rows;
    }
}
