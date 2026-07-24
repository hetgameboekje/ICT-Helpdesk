<?php

namespace App\Modules\Voorraad;

use App\Core\Barcode;
use App\Core\CrudController;
use App\Modules\Voorraad\DxDiagParser;
use App\Modules\Voorraad\Models\VoorraadItemModel;
use App\Modules\Voorraad\Models\VoorraadTypeModel;

class VoorraadController extends CrudController
{
    protected string $modelClass = VoorraadItemModel::class;
    protected string $viewDir = 'Modules/Voorraad/Views/VoorraadView';
    protected string $routeBase = 'voorraad';
    protected string $activeModule = 'voorraad';
    protected string $pageTitle = 'Voorraad';
    protected array $breadcrumbs = ['Assets & Beheer'];
    protected ?string $searchColumn = 'barcode';

    private const STATUS_LABELS = [
        'op_voorraad' => 'Op voorraad',
        'uitgegeven' => 'Uitgegeven',
        'afgeschreven' => 'Afgeschreven',
    ];

    protected function filterOptions(array $allItems): array
    {
        $types = VoorraadTypeModel::all();
        $typeOptions = [];
        foreach ($types as $t) {
            $typeOptions[$t['naam']] = $t['naam'];
        }

        return [
            'type_naam' => $typeOptions,
            'status' => self::STATUS_LABELS,
        ];
    }

    public function store(): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        $typeId = (int) ($_POST['type_id'] ?? 0);
        $type = VoorraadTypeModel::find($typeId);

        if ($type === null) {
            $_SESSION['flash_error'] = 'Kies een geldig type.';
            $this->redirect('/voorraad/create');
        }

        $variant = trim($_POST['variant'] ?? '') ?: null;
        $locatie = trim($_POST['locatie'] ?? '') ?: null;
        $opmerking = trim($_POST['opmerking'] ?? '') ?: null;
        $aantal = max(1, (int) ($_POST['aantal'] ?? 1));

        $serienummers = array_map(
            fn (string $s) => trim($s) ?: null,
            $_POST['serienummers'] ?? []
        );
        $serienummers = array_filter($serienummers, fn (?string $s) => $s !== null);

        if ($serienummers !== [] && count($serienummers) !== $aantal) {
            $_SESSION['flash_error'] = "Vul voor alle {$aantal} items een serienummer in, of laat ze allemaal leeg.";
            $this->redirect('/voorraad/create');
        }

        if (count($serienummers) !== count(array_unique($serienummers))) {
            $_SESSION['flash_error'] = 'Je hebt hetzelfde serienummer meerdere keren ingevuld — een serienummer is uniek per stuk.';
            $this->redirect('/voorraad/create');
        }

        foreach ($serienummers as $serienummer) {
            if (VoorraadItemModel::serienummerExists($serienummer)) {
                $_SESSION['flash_error'] = "Serienummer {$serienummer} bestaat al bij een ander item.";
                $this->redirect('/voorraad/create');
            }
        }

        $serienummers = array_values($serienummers);

        $specificaties = null;
        if (!empty($_FILES['dxdiag_bestand']['tmp_name']) && $_FILES['dxdiag_bestand']['error'] === UPLOAD_ERR_OK) {
            if ($aantal > 1) {
                $_SESSION['flash_error'] = 'Een DxDiag-rapport hoort bij precies één apparaat — verwijder het bestand of zet het aantal op 1.';
                $this->redirect('/voorraad/create');
            }

            try {
                $specificaties = json_encode(DxDiagParser::parse($_FILES['dxdiag_bestand']['tmp_name']), JSON_THROW_ON_ERROR);
            } catch (\RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                $this->redirect('/voorraad/create');
            }
        }

        $lastId = null;
        $barcode = null;
        for ($i = 0; $i < $aantal; $i++) {
            $serienummer = $serienummers[$i] ?? null;
            $barcode = self::buildBarcode($type['code'], $variant, $serienummer);

            $lastId = VoorraadItemModel::create([
                'type_id' => $typeId,
                'variant' => $variant,
                'serienummer' => $serienummer,
                'barcode' => $barcode,
                'locatie' => $locatie,
                'opmerking' => $opmerking,
                'specificaties' => $specificaties,
                'aangemaakt_door_id' => $this->currentUserId(),
            ]);
        }

        $_SESSION['flash_success'] = $aantal > 1
            ? "{$aantal} items toegevoegd."
            : "Item toegevoegd met barcode {$barcode}.";
        $this->redirect("/voorraad/{$lastId}");
    }

    public function update(int $id): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');
        $item = VoorraadItemModel::find($id);

        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $typeId = (int) ($_POST['type_id'] ?? 0);
        $type = VoorraadTypeModel::find($typeId);

        if ($type === null) {
            $_SESSION['flash_error'] = 'Kies een geldig type.';
            $this->redirect("/voorraad/{$id}/edit");
        }

        $variant = trim($_POST['variant'] ?? '') ?: null;
        $serienummer = trim($_POST['serienummer'] ?? '') ?: null;
        $locatie = trim($_POST['locatie'] ?? '') ?: null;
        $opmerking = trim($_POST['opmerking'] ?? '') ?: null;

        if ($serienummer !== null && VoorraadItemModel::serienummerExists($serienummer, $id)) {
            $_SESSION['flash_error'] = "Serienummer {$serienummer} bestaat al bij een ander item.";
            $this->redirect("/voorraad/{$id}/edit");
        }

        $barcode = self::buildBarcode($type['code'], $variant, $serienummer);

        $updateData = [
            'type_id' => $typeId,
            'variant' => $variant,
            'serienummer' => $serienummer,
            'barcode' => $barcode,
            'locatie' => $locatie,
            'opmerking' => $opmerking,
        ];

        if (!empty($_FILES['dxdiag_bestand']['tmp_name']) && $_FILES['dxdiag_bestand']['error'] === UPLOAD_ERR_OK) {
            try {
                $updateData['specificaties'] = json_encode(DxDiagParser::parse($_FILES['dxdiag_bestand']['tmp_name']), JSON_THROW_ON_ERROR);
            } catch (\RuntimeException $e) {
                $_SESSION['flash_error'] = $e->getMessage();
                $this->redirect("/voorraad/{$id}/edit");
            }
        }

        VoorraadItemModel::update($id, $updateData);

        $_SESSION['flash_success'] = "Item bijgewerkt (barcode: {$barcode}).";
        $this->redirect("/voorraad/{$id}");
    }

    public function show(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');

        if (VoorraadItemModel::find($id) === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $this->render("{$this->viewDir}/show", [
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
        ]);
    }

    public function barcode(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $item = VoorraadItemModel::findWithRelations($id);

        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $svg = Barcode::code128Svg($item['barcode'], 3, 70);
        $naam = htmlspecialchars($item['type_naam'] . ($item['variant'] ? ' (' . $item['variant'] . ')' : ''));

        echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>Barcode ' . htmlspecialchars($item['barcode']) . '</title>'
            . '<style>body{font-family:sans-serif;text-align:center;padding:24px}.label{display:inline-block;border:1px dashed #ccc;padding:12px 16px;margin:8px}
               .naam{font-size:13px;margin-bottom:6px}
               @media print{.no-print{display:none}}</style></head><body>'
            . '<div class="no-print"><button onclick="window.print()">Printen</button></div>'
            . '<div class="label"><div class="naam">' . $naam . '</div>' . $svg . '</div>'
            . '</body></html>';
    }

    protected function formData(): array
    {
        return ['types' => VoorraadTypeModel::all()];
    }

    protected function validatedData(array $post, bool $isUpdate = false): array
    {
        return [];
    }

    private static function buildBarcode(string $typeCode, ?string $variant, ?string $serienummer): string
    {
        if ($serienummer !== null && $serienummer !== '') {
            return strtoupper($typeCode . '-' . preg_replace('/[^A-Za-z0-9]/', '', $serienummer));
        }

        $suffix = ($variant !== null && $variant !== '')
            ? '-' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $variant))
            : '';

        return strtoupper($typeCode) . $suffix;
    }
}
