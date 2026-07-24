<?php

namespace App\Modules\Kennisbank;

use App\Core\CrudController;
use App\Core\TableQuery;
use App\Modules\EmailVerwerking\Models\KbArticleDraftModel;
use App\Modules\Kennisbank\Models\KennisbankLogModel;
use App\Modules\Kennisbank\Models\KennisbankModel;

class KennisbankController extends CrudController
{
    protected string $modelClass = KennisbankModel::class;
    protected string $viewDir = 'Modules/Kennisbank/Views/KennisbankView';
    protected string $routeBase = 'kennisbank';
    protected string $activeModule = 'kennisbank';
    protected string $pageTitle = 'Kennisbank';

    /**
     * Override van CrudController::index(): 'tag' wordt al door applyDefaultFilters() verwerkt.
     * TableQuery::apply() behandelt élke onbekende $_GET-key als exacte kolomfilter, en zou
     * 'tag' — geen echte kolom — gebruiken om alles weg te filteren. Daarom hier zelf de query
     * params voor TableQuery opschonen i.p.v. de generieke CrudController-flow te gebruiken.
     */
    public function index(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $allItems = KennisbankModel::allWithRelations();

        $items = $this->applyDefaultFilters($allItems);
        $tableQueryParams = array_diff_key($_GET, ['tag' => null]);
        $items = TableQuery::apply($items, $tableQueryParams, $this->searchColumn);
        $pagination = TableQuery::paginate($items, $_GET);

        $this->render("{$this->viewDir}/index", array_merge([
            'items' => $pagination['items'],
            'pagination' => $pagination,
            'search' => $_GET['q'] ?? '',
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
            'sort' => $_GET['sort'] ?? null,
            'dir' => ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ], $this->extraViewData($allItems)));
    }

    public function show(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $item = KennisbankModel::findWithRelations($id);

        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $this->render("{$this->viewDir}/show", [
            'item' => $item,
            'logs' => KennisbankLogModel::forArtikel($id),
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

    public function subcategorieen(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $categorie = trim($_GET['categorie'] ?? '');
        $q = trim($_GET['q'] ?? '');

        header('Content-Type: application/json');
        echo json_encode($categorie === '' ? [] : KennisbankModel::distinctSubcategorieen($categorie, $q));
    }

    public function tags(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $q = trim($_GET['q'] ?? '');

        header('Content-Type: application/json');
        echo json_encode(KennisbankModel::distinctTags($q));
    }

    protected function applyDefaultFilters(array $items): array
    {
        $categorie = trim($_GET['categorie'] ?? '');
        if ($categorie !== '') {
            $items = array_values(array_filter($items, fn (array $item) => $item['categorie'] === $categorie));
        }

        $subcategorie = trim($_GET['subcategorie'] ?? '');
        if ($subcategorie !== '') {
            $items = array_values(array_filter($items, fn (array $item) => ($item['subcategorie'] ?? '') === $subcategorie));
        }

        $tags = $this->activeTags();
        if (!empty($tags)) {
            $needles = array_map('mb_strtolower', $tags);
            $items = array_values(array_filter(
                $items,
                fn (array $item) => array_intersect($needles, array_map('mb_strtolower', KennisbankModel::splitTags($item['tags'] ?? null))) !== []
            ));
        }

        return $items;
    }

    protected function extraViewData(array $allItems): array
    {
        $categorieBoom = KennisbankModel::categorieBoom();
        // "AI-concepten" (zie Lovable modules.kennisbank.tsx) leven bij ons niet tussen de gepubliceerde
        // artikelen, maar in een eigen reviewwachtrij (App\Modules\EmailVerwerking) — vandaar de
        // cross-module telling i.p.v. een concept-vlag op kennisbank_artikelen zelf.
        $draftTellingen = KbArticleDraftModel::telPerStatus();

        return [
            'categorieBoom' => $categorieBoom,
            'activeCategorie' => trim($_GET['categorie'] ?? ''),
            'activeSubcategorie' => trim($_GET['subcategorie'] ?? ''),
            'activeTags' => $this->activeTags(),
            'kpiAantalArtikelen' => count($allItems),
            'kpiAantalCategorieen' => count($categorieBoom),
            'kpiConceptenInReview' => ($draftTellingen['draft_created'] ?? 0) + ($draftTellingen['in_review'] ?? 0),
        ];
    }

    /** Geselecteerde tags uit de query string ('tag' kan een losse waarde of een array ('tag[]=...') zijn). */
    private function activeTags(): array
    {
        $tags = $_GET['tag'] ?? [];
        if (is_string($tags)) {
            $tags = $tags === '' ? [] : [$tags];
        }

        return array_values(array_filter(array_map('trim', (array) $tags), fn (string $t) => $t !== ''));
    }

    protected function validatedData(array $post, bool $isUpdate = false): array
    {
        $data = [
            'titel' => trim($post['titel'] ?? ''),
            'categorie' => trim($post['categorie'] ?? '') ?: 'Algemeen',
            'subcategorie' => trim($post['subcategorie'] ?? '') ?: null,
            'samenvatting' => trim($post['samenvatting'] ?? '') ?: null,
            'tags' => KennisbankModel::normalizeTags($post['tags'] ?? ''),
            'inhoud' => trim($post['inhoud'] ?? ''),
        ];

        if (!$isUpdate) {
            $data['auteur_id'] = $this->currentUserId();
        }

        return $data;
    }
}
