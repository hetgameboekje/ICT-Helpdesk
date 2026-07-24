<?php

namespace App\Core;

abstract class CrudController extends Controller
{
    protected string $modelClass;
    protected string $viewDir;
    protected string $routeBase;
    protected string $activeModule;
    protected string $pageTitle;
    protected ?string $searchColumn = 'titel';
    /** Topbar-breadcrumb (bv. ['Support']) — zie app/Views/layouts/app.php. Leeg = geen breadcrumb-regel. */
    protected array $breadcrumbs = [];

    /**
     * Rij-niveau toegangscontrole bovenop requirePermission() — bv. afdeling/eigenaarschap.
     * Default: geen extra restrictie (bestaand gedrag voor modules die dit niet overriden).
     */
    protected function scopeAllowed(array $item): bool
    {
        return true;
    }

    public function index(): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $allItems = array_values(array_filter(
            ($this->modelClass)::allWithRelations(),
            fn (array $item) => $this->scopeAllowed($item)
        ));
        $filterOptions = $this->filterOptions($allItems);

        $items = $this->applyDefaultFilters($allItems);
        $items = TableQuery::apply($items, $_GET, $this->searchColumn);
        $pagination = TableQuery::paginate($items, $_GET);

        $this->render("{$this->viewDir}/index", array_merge([
            'items' => $pagination['items'],
            'pagination' => $pagination,
            'filterOptions' => $filterOptions,
            'search' => $_GET['q'] ?? '',
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
            'breadcrumbs' => $this->breadcrumbs,
            'sort' => $_GET['sort'] ?? null,
            'dir' => ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ], $this->extraViewData($allItems)));
    }

    protected function filterOptions(array $allItems): array
    {
        return [];
    }

    protected function applyDefaultFilters(array $items): array
    {
        return $items;
    }

    protected function extraViewData(array $allItems): array
    {
        return [];
    }

    public function create(): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        $this->render("{$this->viewDir}/create", array_merge($this->formData(), [
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
            'breadcrumbs' => $this->breadcrumbs,
        ]));
    }

    public function store(): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');
        $data = $this->validatedData($_POST);
        $id = ($this->modelClass)::create($data);
        $this->redirect("/{$this->routeBase}/{$id}");
    }

    public function show(int $id): void
    {
        $this->requirePermission($this->activeModule, 'lezen');
        $item = ($this->modelClass)::findWithRelations($id);

        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        if (!$this->scopeAllowed($item)) {
            $this->forbidden();
            return;
        }

        $this->render("{$this->viewDir}/show", array_merge(['item' => $item], [
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
            'breadcrumbs' => $this->breadcrumbs,
        ]));
    }

    public function edit(int $id): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');
        $item = ($this->modelClass)::find($id);

        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        if (!$this->scopeAllowed($item)) {
            $this->forbidden();
            return;
        }

        $this->render("{$this->viewDir}/edit", array_merge(['item' => $item], $this->formData(), [
            'activeModule' => $this->activeModule,
            'pageTitle' => $this->pageTitle,
            'routeBase' => $this->routeBase,
            'breadcrumbs' => $this->breadcrumbs,
        ]));
    }

    public function update(int $id): void
    {
        $this->requirePermission($this->activeModule, 'schrijven');

        $item = ($this->modelClass)::find($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }
        if (!$this->scopeAllowed($item)) {
            $this->forbidden();
            return;
        }

        $data = $this->validatedData($_POST, isUpdate: true);
        ($this->modelClass)::update($id, $data);
        $this->redirect("/{$this->routeBase}/{$id}");
    }

    public function destroy(int $id): void
    {
        $this->requirePermission($this->activeModule, 'verwijderen');

        $item = ($this->modelClass)::find($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }
        if (!$this->scopeAllowed($item)) {
            $this->forbidden();
            return;
        }

        ($this->modelClass)::delete($id);
        $_SESSION['flash_success'] = 'Item is inactief gezet en niet meer zichtbaar in het overzicht.';
        $this->redirect("/{$this->routeBase}");
    }

    protected function formData(): array
    {
        return [];
    }

    abstract protected function validatedData(array $post, bool $isUpdate = false): array;
}
