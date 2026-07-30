<?php

namespace App\Modules\Voorraad;

use App\Core\Controller;
use App\Modules\Voorraad\Models\BarcodeTemplateModel;
use App\Modules\Voorraad\Models\VoorraadTypeModel;

/**
 * Beheerscherm voor barcode-sjablonen (/voorraad/barcode-templates) — waarmee een regex-patroon
 * gekoppeld wordt aan een voorgesteld voorraadtype + omschrijving, zodat een kale barcode-scan
 * zonder komma (bv. "BCYUH0ARZCL0AX" voor een toetsenbord, "1155984821038" voor een EAN-monitor)
 * alsnog een typesuggestie krijgt (zie App\Shared\AssetScan\BarcodeTemplateMatcher). Losse,
 * eenvoudige lijst+inline-formulier-pagina i.p.v. CrudController: er is geen aparte "show"-pagina
 * nodig voor een sjabloonrij, bewerken via een los formulier volstaat.
 */
class BarcodeTemplateController extends Controller
{
    public function index(): void
    {
        $this->requirePermission('voorraad', 'lezen');

        $this->render('Modules/Voorraad/Views/BarcodeTemplateView/index', [
            'activeModule' => 'voorraad',
            'pageTitle' => 'Barcode-sjablonen',
            'templates' => BarcodeTemplateModel::allWithType(),
            'types' => VoorraadTypeModel::all(),
        ]);
    }

    public function store(): void
    {
        $this->requirePermission('voorraad', 'schrijven');

        $errors = $this->valideerInvoer($_POST);
        if ($errors !== []) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $this->redirect('/voorraad/barcode-templates');
        }

        BarcodeTemplateModel::create($this->genormaliseerdeData($_POST));

        $_SESSION['flash_success'] = 'Barcode-sjabloon toegevoegd.';
        $this->redirect('/voorraad/barcode-templates');
    }

    public function edit(int $id): void
    {
        $this->requirePermission('voorraad', 'schrijven');
        $template = BarcodeTemplateModel::findWithType($id);

        if ($template === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $this->render('Modules/Voorraad/Views/BarcodeTemplateView/edit', [
            'activeModule' => 'voorraad',
            'pageTitle' => 'Barcode-sjabloon bewerken',
            'template' => $template,
            'types' => VoorraadTypeModel::all(),
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission('voorraad', 'schrijven');

        if (BarcodeTemplateModel::find($id) === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $errors = $this->valideerInvoer($_POST);
        if ($errors !== []) {
            $_SESSION['flash_error'] = implode(' ', $errors);
            $this->redirect("/voorraad/barcode-templates/{$id}/edit");
        }

        BarcodeTemplateModel::update($id, $this->genormaliseerdeData($_POST));

        $_SESSION['flash_success'] = 'Barcode-sjabloon bijgewerkt.';
        $this->redirect('/voorraad/barcode-templates');
    }

    public function destroy(int $id): void
    {
        $this->requirePermission('voorraad', 'schrijven');

        if (BarcodeTemplateModel::find($id) === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        BarcodeTemplateModel::delete($id);

        $_SESSION['flash_success'] = 'Barcode-sjabloon verwijderd.';
        $this->redirect('/voorraad/barcode-templates');
    }

    /** @return string[] foutmeldingen, leeg als de invoer geldig is */
    private function valideerInvoer(array $post): array
    {
        $errors = [];

        if (trim($post['naam'] ?? '') === '') {
            $errors[] = 'Naam is verplicht.';
        }

        $patroon = trim($post['patroon'] ?? '');
        if ($patroon === '') {
            $errors[] = 'Patroon (regex) is verplicht.';
        } elseif (@preg_match('/' . $patroon . '/u', '') === false) {
            $errors[] = "Patroon \"{$patroon}\" is geen geldige reguliere expressie.";
        }

        return $errors;
    }

    private function genormaliseerdeData(array $post): array
    {
        return [
            'naam' => trim($post['naam'] ?? ''),
            'patroon' => trim($post['patroon'] ?? ''),
            'voorraad_type_id' => !empty($post['voorraad_type_id']) ? (int) $post['voorraad_type_id'] : null,
            'omschrijving' => trim($post['omschrijving'] ?? '') ?: null,
            'actief' => !empty($post['actief']) ? 1 : 0,
            'volgorde' => (int) ($post['volgorde'] ?? 0),
        ];
    }
}
