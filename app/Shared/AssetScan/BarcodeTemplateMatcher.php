<?php

namespace App\Shared\AssetScan;

use App\Modules\Voorraad\Models\BarcodeTemplateModel;

/**
 * Test een kale barcode-token (geen komma-formaat, zie BarcodeScanParser) tegen de beheerbare
 * `barcode_templates` (BarcodeTemplateModel) om alsnog een apparaattype te kunnen voorstellen —
 * bv. een HP-toetsenbordserienummer ("BCYUH0ARZCL0AX") of een EAN-barcode van een monitor
 * ("1155984821038"). Wordt pas geraadpleegd nadat is vastgesteld dat de barcode nog niet als eigen
 * voorraad-item bekend is (zie AssetEnrichmentService) — een al bekende barcode heeft dit niet nodig.
 */
class BarcodeTemplateMatcher
{
    /** @return array{id:int,naam:string,omschrijving:?string,voorraad_type_id:?int,voorraad_type_naam:?string}|null */
    public static function match(string $rawToken): ?array
    {
        foreach (BarcodeTemplateModel::activeOrdered() as $template) {
            if (self::matchesPatroon($template['patroon'], $rawToken)) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Een door de gebruiker ingevoerd regex-patroon kan ongeldig zijn (typefout in de admin-UI) —
     * dat mag een scan nooit laten crashen, dus een ongeldig patroon wordt overgeslagen i.p.v. een
     * fatale fout te geven. `preg_match()` geeft in dat geval `false` terug i.p.v. te gooien.
     */
    private static function matchesPatroon(string $patroon, string $waarde): bool
    {
        $resultaat = @preg_match('/' . $patroon . '/u', $waarde);
        return $resultaat === 1;
    }
}
