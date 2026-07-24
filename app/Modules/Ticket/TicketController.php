<?php

namespace App\Modules\Ticket;

use App\Core\CrudController;
use App\Core\TableQuery;
use App\Modules\Kennisbank\Models\KennisbankModel;
use App\Modules\Ticket\Models\TicketKennisbankModel;
use App\Modules\Ticket\Models\TicketLogModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Modules\Ticket\Models\TicketTijdModel;
use App\Shared\Afdeling\Models\AfdelingModel;
use App\Shared\User\Models\UserModel;

class TicketController extends CrudController
{
    protected string $modelClass = TicketModel::class;
    protected string $viewDir = 'Modules/Ticket/Views/TicketView';
    protected string $routeBase = 'tickets';
    protected string $activeModule = 'tickets';
    protected string $pageTitle = 'Tickets';
    protected array $breadcrumbs = ['Support'];

    private const STATUS_LABELS = [
        'open' => 'Open',
        'in_behandeling' => 'In behandeling',
        'wacht_op_info' => 'Wacht op info',
        'afgehandeld' => 'Afgehandeld',
    ];

    private const PRIORITEIT_LABELS = [
        'laag' => 'Laag',
        'normaal' => 'Normaal',
        'hoog' => 'Hoog',
        'kritiek' => 'Kritiek',
    ];

    protected function scopeAllowed(array $item): bool
    {
        $user = $this->currentUser();
        if (($user['rol'] ?? '') === 'admin') {
            return true;
        }

        $userId = (int) $this->currentUserId();

        return ($item['afdeling_id'] ?? null) == ($user['afdeling_id'] ?? null)
            || (int) ($item['behandelaar_id'] ?? 0) === $userId
            || (int) ($item['aangemaakt_door_id'] ?? 0) === $userId;
    }

    protected function filterOptions(array $allItems): array
    {
        $afdelingen = array_values(array_unique(array_filter(array_column($allItems, 'afdeling_naam'))));
        sort($afdelingen);

        $behandelaars = array_values(array_unique(array_filter(array_column($allItems, 'behandelaar_naam'))));
        sort($behandelaars);

        return [
            'status' => ['alle' => 'Alle statussen'] + self::STATUS_LABELS,
            'prioriteit' => self::PRIORITEIT_LABELS,
            'afdeling_naam' => array_combine($afdelingen, $afdelingen),
            'behandelaar_naam' => array_combine($behandelaars, $behandelaars),
        ];
    }

    protected function applyDefaultFilters(array $items): array
    {
        $status = $_GET['status'] ?? '';

        if ($status === 'alle') {
            // 'alle' is een expliciete keuze voor "geen statusfilter". TableQuery::filter()
            // negeert deze waarde zelf al, dus $_GET blijft ongewijzigd — anders raakt de
            // dropdown zijn geselecteerde optie kwijt zodra de pagina opnieuw rendert.
            return $items;
        }

        if ($status === '') {
            return array_values(array_filter($items, fn (array $t) => $t['status'] !== 'afgehandeld'));
        }

        return $items;
    }

    /** Aantallen per status voor de statusfilter-tabs (zie Views/TicketView/index.php) — geteld in het
     *  al opgehaalde, scope-gefilterde array, dus geen extra database-query's per status nodig. */
    protected function extraViewData(array $allItems): array
    {
        $counts = ['alle' => count($allItems)];
        foreach (array_keys(self::STATUS_LABELS) as $status) {
            $counts[$status] = 0;
        }
        foreach ($allItems as $item) {
            if (isset($counts[$item['status']])) {
                $counts[$item['status']]++;
            }
        }

        return ['statusCounts' => $counts];
    }

    public function show(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $item = TicketModel::findWithRelations($id);

        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        if (!$this->scopeAllowed($item)) {
            $this->forbidden();
            return;
        }

        $gekoppeld = TicketKennisbankModel::gekoppeld($id);
        $gekoppeldeIds = array_column($gekoppeld, 'id');

        $this->render("{$this->viewDir}/show", [
            'item' => $item,
            'logs' => TicketLogModel::forTicket($id),
            'tijdregistraties' => TicketTijdModel::forTicket($id),
            'tijdTotaal' => TicketTijdModel::sumForTicket($id),
            'gekoppeldeArtikelen' => $gekoppeld,
            'suggestiesArtikelen' => array_filter(
                TicketKennisbankModel::suggesties($id, $item['categorie']),
                fn (array $a) => !in_array($a['id'], $gekoppeldeIds)
            ),
            'activeModule' => $this->activeModule,
            'pageTitle' => "#{$item['id']} — {$item['titel']}",
            'routeBase' => $this->routeBase,
            'breadcrumbs' => ['Support', 'Tickets'],
        ]);
    }

    public function update(int $id): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        $huidig = TicketModel::find($id);
        if ($huidig === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        if (!$this->scopeAllowed($huidig)) {
            $this->forbidden();
            return;
        }

        $data = TicketModel::alleenGewijzigdeVelden($huidig, $this->validatedData($_POST, isUpdate: true));
        if ($data !== []) {
            TicketModel::update($id, $data);
        }

        $this->redirect("/tickets/{$id}");
    }

    /** Koppelt een kennisbankartikel aan dit ticket — via het "Gerelateerde kennisbank artikelen"-blok op de detailpagina. */
    public function kennisbankKoppel(int $id): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        if (TicketModel::find($id) === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $artikelId = (int) ($_POST['kennisbank_artikel_id'] ?? 0);
        if ($artikelId > 0 && KennisbankModel::find($artikelId) !== null) {
            TicketKennisbankModel::koppel($id, $artikelId);
        }

        $this->redirect("/tickets/{$id}");
    }

    public function kennisbankOntkoppel(int $id, int $artikelId): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');
        TicketKennisbankModel::ontkoppel($id, $artikelId);
        $this->redirect("/tickets/{$id}");
    }

    public function export(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');

        // Zelfde filter/zoek-pijplijn als index(), zodat de export exact de huidige weergave bevat
        // (m.u.v. paginering — de export bevat alle gefilterde rijen, niet alleen de huidige pagina).
        $allItems = TicketModel::allWithRelations();
        $items = $this->applyDefaultFilters($allItems);
        $items = TableQuery::apply($items, $_GET, $this->searchColumn);

        $content = TicketExcel::export($items, $this->currentUser()['naam'] ?? 'Ticketsysteem Leen van Punt');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="tickets-export-' . date('Y-m-d') . '.xlsx"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }

    public function import(): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        if (empty($_FILES['bestand']['tmp_name']) || $_FILES['bestand']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Geen geldig Excel-bestand ontvangen.';
            $this->redirect('/tickets');
        }

        $extension = strtolower(pathinfo($_FILES['bestand']['name'], PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            $_SESSION['flash_error'] = 'Alleen .xlsx-bestanden worden ondersteund.';
            $this->redirect('/tickets');
        }

        try {
            $result = TicketExcel::import($_FILES['bestand']['tmp_name'], (int) $this->currentUserId());
        } catch (\RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $this->redirect('/tickets');
        }

        $_SESSION['flash_success'] = "Import voltooid: {$result['created']} ticket(s) aangemaakt, "
            . "{$result['duplicates']} overgeslagen als duplicaat (zelfde taak + opdrachtgever bestond al), "
            . "{$result['skipped']} rij(en) overgeslagen (geen taaknaam).";
        $this->redirect('/tickets');
    }

    public function categorieen(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $q = trim($_GET['q'] ?? '');

        header('Content-Type: application/json');
        echo json_encode(KennisbankModel::distinctCategorieen($q));
    }

    protected function formData(): array
    {
        return [
            'afdelingen' => AfdelingModel::all(),
            'gebruikers' => UserModel::all('naam ASC'),
        ];
    }

    protected function validatedData(array $post, bool $isUpdate = false): array
    {
        $data = [
            'titel' => trim($post['titel'] ?? ''),
            'omschrijving' => trim($post['omschrijving'] ?? ''),
            'opdrachtgever_naam' => trim($post['opdrachtgever_naam'] ?? ''),
            'categorie' => trim($post['categorie'] ?? '') ?: 'Algemeen',
            'afdeling_id' => $post['afdeling_id'] !== '' ? (int) $post['afdeling_id'] : null,
            'prioriteit' => $post['prioriteit'] ?? 'normaal',
            'impact' => $post['impact'] ?? 'Normaal',
            'schatting_minuten' => $post['schatting_minuten'] !== '' ? (int) $post['schatting_minuten'] : null,
            'deadline' => $post['deadline'] !== '' ? $post['deadline'] : null,
            'behandelaar_id' => !empty($post['behandelaar_id']) ? (int) $post['behandelaar_id'] : null,
            'is_cyberrisico' => !empty($post['is_cyberrisico']) ? 1 : 0,
        ];

        if (!$isUpdate) {
            $data['status'] = 'open';
            $data['aangemaakt_door_id'] = $this->currentUserId();
        }

        return $data;
    }
}
