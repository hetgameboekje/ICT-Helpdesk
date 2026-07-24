<?php

namespace App\Api\V1;

use App\Modules\Medewerker\MedewerkerService;

/** Presentation/API-laag voor medewerkers — /api/v1/medewerkers*. Businesslogica in MedewerkerService. */
class MedewerkersApiController extends ApiController
{
    private MedewerkerService $service;

    public function __construct()
    {
        $this->service = new MedewerkerService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'medewerkers', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], ['pagination' => $result['pagination'], 'stats' => $result['stats']]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'medewerkers', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'medewerkers', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }
}
