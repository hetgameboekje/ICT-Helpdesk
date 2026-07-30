<?php

namespace App\Modules\Tools;

use App\Core\Controller;
use App\Modules\Tools\EntraNinjaOne\EntraCsvParser;
use App\Modules\Tools\EntraNinjaOne\EntraToNinjaOneConverter;
use App\Modules\Tools\EntraNinjaOne\NinjaOneExporter;

/**
 * Upload een Entra ID-gebruikersexport (CSV), zet die om naar NinjaOne's "Import technicians / end
 * users"-formaat en toont een preview + TAB-delimited copy/paste-tekst + downloadknop. Stateless
 * (geen DB-tabel, zelfde overweging als Schijfgebruik's CSV-import): het geconverteerde resultaat
 * wordt kort in de sessie bewaard tussen de upload-POST en de eropvolgende preview-/download-GET's,
 * er is geen geschiedenis/hergebruik-vereiste die een aparte tabel zou rechtvaardigen.
 */
class EntraNinjaOneController extends Controller
{
    private const SESSION_KEY = 'entra_ninjaone_export';

    public function index(): void
    {
        $this->requireAuth();

        $this->render('Modules/Tools/Views/EntraNinjaOneView/index', [
            'activeModule' => 'tools',
            'pageTitle' => 'Entra ID naar NinjaOne',
            'export' => $_SESSION[self::SESSION_KEY] ?? null,
        ]);
    }

    public function upload(): void
    {
        $this->requireAuth();

        if (empty($_FILES['bestand']['tmp_name']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Kies eerst een CSV-bestand.';
            $this->redirect('/tools/entra-ninjaone');
        }

        try {
            $rows = EntraCsvParser::parseEntraCsv($_FILES['bestand']['tmp_name']);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('/tools/entra-ninjaone');
        }

        $includeGuests = !empty($_POST['include_guests']);
        $resultaat = EntraToNinjaOneConverter::convertRows($rows, null, $includeGuests);

        $_SESSION[self::SESSION_KEY] = [
            'users' => $resultaat['users'],
            'skipped' => $resultaat['skipped'],
            'include_guests' => $includeGuests,
            'bestandsnaam' => $_FILES['bestand']['name'] ?? null,
            'gegenereerd_op' => date('Y-m-d H:i:s'),
        ];

        $_SESSION['flash_success'] = count($resultaat['users']) . ' gebruiker(s) omgezet, '
            . count($resultaat['skipped']) . ' overgeslagen.';
        $this->redirect('/tools/entra-ninjaone');
    }

    public function download(): void
    {
        $this->requireAuth();

        $export = $_SESSION[self::SESSION_KEY] ?? null;
        if ($export === null || empty($export['users'])) {
            $_SESSION['flash_error'] = 'Nog niets geconverteerd om te downloaden — upload eerst een CSV-bestand.';
            $this->redirect('/tools/entra-ninjaone');
        }

        $content = NinjaOneExporter::exportTabDelimited($export['users']);

        header('Content-Type: text/tab-separated-values; charset=utf-8');
        header('Content-Disposition: attachment; filename="ninjaone-import-' . date('Y-m-d') . '.tsv"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }
}
