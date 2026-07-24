<?php

namespace App\Modules\Verbeterpunt;

use App\Core\CrudController;
use App\Modules\Kennisbank\Models\KennisbankModel;
use App\Modules\Verbeterpunt\Models\VerbeterpuntModel;
use App\Shared\Afdeling\Models\AfdelingModel;

class VerbeterpuntController extends CrudController
{
    protected string $modelClass = VerbeterpuntModel::class;
    protected string $viewDir = 'Modules/Verbeterpunt/Views/VerbeterpuntView';
    protected string $routeBase = 'verbeterpunten';
    protected string $activeModule = 'verbeterpunten';
    protected string $pageTitle = 'Verbeterpunten';
    protected array $breadcrumbs = ['Support'];

    protected function scopeAllowed(array $item): bool
    {
        $user = $this->currentUser();
        if (($user['rol'] ?? '') === 'admin') {
            return true;
        }

        $userId = (int) $this->currentUserId();

        return ($item['afdeling_id'] ?? null) == ($user['afdeling_id'] ?? null)
            || (int) ($item['ingediend_door_id'] ?? 0) === $userId;
    }

    public function show(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $item = VerbeterpuntModel::find($id);

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

    public function categorieen(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $q = trim($_GET['q'] ?? '');

        header('Content-Type: application/json');
        echo json_encode(KennisbankModel::distinctCategorieen($q));
    }

    protected function formData(): array
    {
        return ['afdelingen' => AfdelingModel::all()];
    }

    protected function validatedData(array $post, bool $isUpdate = false): array
    {
        $data = [
            'titel' => trim($post['titel'] ?? ''),
            'omschrijving' => trim($post['omschrijving'] ?? ''),
            'categorie' => trim($post['categorie'] ?? '') ?: 'Algemeen',
            'afdeling_id' => $post['afdeling_id'] !== '' ? (int) $post['afdeling_id'] : null,
            'status' => $post['status'] ?? 'nieuw',
        ];

        if (!$isUpdate) {
            $data['status'] = 'nieuw';
            $data['ingediend_door_id'] = $this->currentUserId();
        }

        return $data;
    }
}
