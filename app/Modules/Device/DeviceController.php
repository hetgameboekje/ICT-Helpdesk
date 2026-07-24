<?php

namespace App\Modules\Device;

use App\Core\CrudController;
use App\Core\TableQuery;
use App\Modules\Device\Models\DeviceModel;
use App\Modules\Device\Models\DeviceSoftwareModel;
use App\Modules\Device\Models\SoftwareInventarisModel;
use App\Modules\Medewerker\Models\MedewerkerModel;

class DeviceController extends CrudController
{
    protected string $modelClass = DeviceModel::class;
    protected string $viewDir = 'Modules/Device/Views/DeviceView';
    protected string $routeBase = 'apparaten';
    protected string $activeModule = 'apparaten';
    protected string $pageTitle = 'Applicaties';
    protected array $breadcrumbs = ['Assets & Beheer'];
    protected ?string $searchColumn = 'naam';

    protected function filterOptions(array $allItems): array
    {
        $namen = array_values(array_unique(array_filter(array_column($allItems, 'medewerker_naam'))));
        sort($namen);

        return ['medewerker_naam' => array_combine($namen, $namen)];
    }

    protected function formData(): array
    {
        return ['medewerkers' => MedewerkerModel::all('achternaam ASC')];
    }

    public function show(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');

        if (DeviceModel::find($id) === null) {
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

    /**
     * Vervangt CrudController::store() volledig: hier komt zowel het aanmaken/hergebruiken van een
     * apparaat als het importeren van de CSV-software-inventaris samen (zie DeviceSoftwareImport).
     * Eén CSV-upload hoort bij precies één apparaat; een volgende upload van dezelfde computer wordt
     * herkend via het apparaat-ID uit de "Devices"-kolom (DeviceModel::findByExternId()).
     */
    public function store(): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        if (empty($_FILES['bestand']['tmp_name']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Geen geldig CSV-bestand ontvangen.';
            $this->redirect('/apparaten/create');
        }

        $extension = strtolower(pathinfo($_FILES['bestand']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $_SESSION['flash_error'] = 'Alleen .csv-bestanden worden ondersteund.';
            $this->redirect('/apparaten/create');
        }

        try {
            $parsed = DeviceSoftwareImport::parse($_FILES['bestand']['tmp_name']);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('/apparaten/create');
        }

        $naam = trim($_POST['naam'] ?? '');
        $medewerkerId = ($_POST['medewerker_id'] ?? '') !== '' ? (int) $_POST['medewerker_id'] : null;

        $device = $parsed['extern_apparaat_id'] !== null
            ? DeviceModel::findByExternId($parsed['extern_apparaat_id'])
            : null;

        if ($device === null) {
            if ($naam === '') {
                $_SESSION['flash_error'] = 'Vul een naam in voor dit apparaat (bv. "Laptop Timo Bergthaler").';
                $this->redirect('/apparaten/create');
            }

            $deviceId = DeviceModel::create([
                'naam' => $naam,
                'extern_apparaat_id' => $parsed['extern_apparaat_id'],
                'medewerker_id' => $medewerkerId,
            ]);
        } else {
            $deviceId = (int) $device['id'];
            if (!empty($device['deleted_at'])) {
                DeviceModel::restore($deviceId);
            }
            DeviceModel::update($deviceId, [
                'naam' => $naam !== '' ? $naam : $device['naam'],
                'medewerker_id' => $medewerkerId,
            ]);
        }

        DeviceSoftwareModel::replaceForDevice($deviceId, $parsed['rows']);
        DeviceModel::update($deviceId, ['laatst_geimporteerd_op' => date('Y-m-d H:i:s')]);

        $_SESSION['flash_success'] = count($parsed['rows']) . ' software-item(s) geïmporteerd.';
        $this->redirect("/apparaten/{$deviceId}");
    }

    protected function validatedData(array $post, bool $isUpdate = false): array
    {
        return [
            'naam' => trim($post['naam'] ?? ''),
            'medewerker_id' => ($post['medewerker_id'] ?? '') !== '' ? (int) $post['medewerker_id'] : null,
        ];
    }

    /**
     * Read-only CRUD-lijst van de globale (fleet-brede) software-inventaris, los van de
     * per-apparaat software die via het bestaande CSV-uploadformulier (store()) wordt beheerd.
     * Er is bewust geen create/edit/destroy per rij — de inhoud wordt uitsluitend beheerd via
     * softwareImport() (nieuwe import, vervangt alles) en softwareLeegmaken() (tabel leegmaken).
     */
    public function softwareIndex(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');

        $allItems = SoftwareInventarisModel::all();

        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $allItems = array_values(array_filter(
                $allItems,
                fn (array $row) => stripos($row['naam'], $search) !== false
                    || stripos((string) $row['publisher'], $search) !== false
            ));
        }

        $platforms = array_values(array_unique(array_filter(array_column($allItems, 'platform'))));
        sort($platforms);

        $items = TableQuery::apply($allItems, $_GET);
        $pagination = TableQuery::paginate($items, $_GET);

        $this->render("{$this->viewDir}/software", [
            'items' => $pagination['items'],
            'pagination' => $pagination,
            'filterOptions' => ['platform' => array_combine($platforms, $platforms)],
            'search' => $search,
            'sort' => $_GET['sort'] ?? null,
            'dir' => ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            'softwareTotaalAantal' => SoftwareInventarisModel::totaalAantal(),
            'softwareLaatstGeimporteerd' => SoftwareInventarisModel::laatstGeimporteerdOp(),
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
        ]);
    }

    /**
     * Globale (fleet-brede) software-import, los van de per-apparaat CSV-upload in store(): één
     * rij hier kan op meerdere apparaten tegelijk geïnstalleerd staan, dus dit legt bewust geen
     * koppeling met devices-records (zie SoftwareInventarisImport).
     */
    public function softwareImport(): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        if (empty($_FILES['bestand']['tmp_name']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Geen geldig CSV-bestand ontvangen.';
            $this->redirect('/apparaten/software');
        }

        $extension = strtolower(pathinfo($_FILES['bestand']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            $_SESSION['flash_error'] = 'Alleen .csv-bestanden worden ondersteund.';
            $this->redirect('/apparaten/software');
        }

        try {
            $rows = SoftwareInventarisImport::parse($_FILES['bestand']['tmp_name']);
            $aantal = SoftwareInventarisModel::replaceAll($rows);
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('/apparaten/software');
        }

        $_SESSION['flash_success'] = "Software-inventaris geïmporteerd: {$aantal} item(s).";
        $this->redirect('/apparaten/software');
    }

    public function softwareLeegmaken(): void
    {
        $this->requirePermission($this->activeModule, 'verwijderen');

        $aantal = SoftwareInventarisModel::leegmaken();

        $_SESSION['flash_success'] = "Software-inventaris geleegd: {$aantal} item(s) verwijderd.";
        $this->redirect('/apparaten/software');
    }
}
