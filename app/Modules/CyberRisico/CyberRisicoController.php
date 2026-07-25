<?php

namespace App\Modules\CyberRisico;

use App\Core\CrudController;
use App\Modules\CyberRisico\CyberRisicoService;
use App\Modules\CyberRisico\Models\CyberRisicoModel;
use App\Shared\Afdeling\Models\AfdelingModel;
use App\Shared\User\Models\UserModel;

class CyberRisicoController extends CrudController
{
    protected string $modelClass = CyberRisicoModel::class;
    protected string $viewDir = 'Modules/CyberRisico/Views/CyberRisicoView';
    protected string $routeBase = 'cyberrisicos';
    protected string $activeModule = 'cyberrisicos';
    protected string $pageTitle = "Cyberrisico's";
    protected array $breadcrumbs = ['Assets & Beheer'];
    protected ?string $searchColumn = 'titel';

    protected function scopeAllowed(array $item): bool
    {
        $user = $this->currentUser();
        if (($user['rol'] ?? '') === 'admin') {
            return true;
        }

        $userId = (int) $this->currentUserId();

        return ($item['afdeling_id'] ?? null) == ($user['afdeling_id'] ?? null)
            || (int) ($item['eigenaar_id'] ?? 0) === $userId
            || (int) ($item['aangemaakt_door_id'] ?? 0) === $userId;
    }

    public function show(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $item = CyberRisicoModel::find($id);

        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        if (!$this->scopeAllowed($item)) {
            $this->forbidden();
            return;
        }

        $this->render("{$this->viewDir}/show", [
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
        ]);
    }

    private const STATUS_LABELS = [
        'nieuw' => 'Nieuw',
        'in_onderzoek' => 'In onderzoek',
        'bevestigd' => 'Bevestigd',
        'opgelost' => 'Opgelost',
        'geaccepteerd' => 'Geaccepteerd risico',
    ];

    private const PRIORITEIT_LABELS = [
        'laag' => 'Laag',
        'middel' => 'Middel',
        'hoog' => 'Hoog',
        'kritiek' => 'Kritiek',
    ];

    private const CATEGORIE_LABELS = [
        'fysieke_toegang' => 'Fysieke toegang',
        'social_engineering' => 'Social engineering',
        'onveilige_opslag' => 'Onveilige opslag',
        'papieren_informatie' => 'Papieren informatie',
        'device_exposure' => 'Device exposure',
        'overig' => 'Overig',
    ];

    protected function filterOptions(array $allItems): array
    {
        $locaties = array_values(array_unique(array_filter(array_column($allItems, 'locatie'))));
        sort($locaties);

        return [
            'status' => self::STATUS_LABELS,
            'prioriteit' => self::PRIORITEIT_LABELS,
            'categorie' => self::CATEGORIE_LABELS,
            'locatie' => array_combine($locaties, $locaties),
        ];
    }

    protected function applyDefaultFilters(array $items): array
    {
        if (($_GET['status'] ?? '') === '') {
            return array_values(array_filter(
                $items,
                fn (array $r) => !in_array($r['status'], ['opgelost', 'geaccepteerd'], true)
            ));
        }

        return $items;
    }

    public function store(): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');
        $data = $this->validatedData($_POST);

        if ($data['titel'] === '' || $data['omschrijving'] === '') {
            $_SESSION['flash_error'] = 'Titel en omschrijving zijn verplicht.';
            $this->redirect('/cyberrisicos/create');
        }

        $id = CyberRisicoModel::create($data);
        $_SESSION['flash_success'] = 'Risico geregistreerd.';
        $this->redirect("/cyberrisicos/{$id}");
    }

    public function update(int $id): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        $huidig = CyberRisicoModel::find($id);
        if ($huidig === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }
        if (!$this->scopeAllowed($huidig)) {
            $this->forbidden();
            return;
        }

        $data = $this->validatedData($_POST, isUpdate: true);

        if ($data['titel'] === '' || $data['omschrijving'] === '') {
            $_SESSION['flash_error'] = 'Titel en omschrijving zijn verplicht.';
            $this->redirect("/cyberrisicos/{$id}/edit");
        }

        CyberRisicoModel::update($id, $data);
        $_SESSION['flash_success'] = 'Risico bijgewerkt.';
        $this->redirect("/cyberrisicos/{$id}");
    }

    protected function formData(): array
    {
        return [
            'gebruikers' => UserModel::all('naam ASC'),
            'afdelingen' => AfdelingModel::all(),
            'statussen' => self::STATUS_LABELS,
            'prioriteiten' => self::PRIORITEIT_LABELS,
            'categorieen' => self::CATEGORIE_LABELS,
        ];
    }

    protected function validatedData(array $post, bool $isUpdate = false): array
    {
        $kans = min(5, max(1, (int) ($post['kans'] ?? 3) ?: 3));
        $impact = min(5, max(1, (int) ($post['impact'] ?? 3) ?: 3));

        $data = [
            'titel' => trim($post['titel'] ?? ''),
            'omschrijving' => trim($post['omschrijving'] ?? ''),
            'categorie' => in_array($post['categorie'] ?? '', array_keys(self::CATEGORIE_LABELS), true)
                ? $post['categorie']
                : 'overig',
            'kans' => $kans,
            'impact' => $impact,
            'prioriteit' => CyberRisicoService::prioriteitVanMatrix($kans, $impact),
            'locatie' => trim($post['locatie'] ?? '') ?: null,
            'gemeld_door' => trim($post['gemeld_door'] ?? '') ?: null,
            'afdeling_id' => !empty($post['afdeling_id']) ? (int) $post['afdeling_id'] : null,
            'eigenaar_id' => !empty($post['eigenaar_id']) ? (int) $post['eigenaar_id'] : null,
            'datum_geconstateerd' => ($post['datum_geconstateerd'] ?? '') !== '' ? $post['datum_geconstateerd'] : null,
            'datum_gemeld' => ($post['datum_gemeld'] ?? '') !== '' ? $post['datum_gemeld'] : null,
            'oplossingsadvies' => trim($post['oplossingsadvies'] ?? '') ?: null,
            'bewijs_notities' => trim($post['bewijs_notities'] ?? '') ?: null,
            'is_gevoelig' => !empty($post['is_gevoelig']) ? 1 : 0,
        ];

        if ($isUpdate) {
            $data['status'] = in_array($post['status'] ?? '', array_keys(self::STATUS_LABELS), true)
                ? $post['status']
                : 'nieuw';
        } else {
            $data['status'] = 'nieuw';
            $data['aangemaakt_door_id'] = $this->currentUserId();
        }

        return $data;
    }
}
