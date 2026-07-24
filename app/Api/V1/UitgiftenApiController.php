<?php

namespace App\Api\V1;

use App\Modules\Uitgifte\UitgifteService;

/** Presentation/API-laag voor uitgiften — /api/v1/uitgiften*. Businesslogica in UitgifteService. */
class UitgiftenApiController extends ApiController
{
    private UitgifteService $service;

    public function __construct()
    {
        $this->service = new UitgifteService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'uitgiften', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], [
                'pagination' => $result['pagination'],
                'stats' => $result['stats'],
                'filterOptions' => $result['filterOptions'],
            ]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'uitgiften', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function retour(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'uitgiften', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->retour($id, $this->jsonBody()));
        });
    }
}
