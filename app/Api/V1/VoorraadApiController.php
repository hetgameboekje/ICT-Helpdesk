<?php

namespace App\Api\V1;

use App\Modules\Voorraad\VoorraadService;

/** Presentation/API-laag voor voorraad — /api/v1/voorraad*. Businesslogica in VoorraadService. */
class VoorraadApiController extends ApiController
{
    private VoorraadService $service;

    public function __construct()
    {
        $this->service = new VoorraadService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], [
                'pagination' => $result['pagination'],
                'statusCounts' => $result['statusCounts'],
                'filterOptions' => $result['filterOptions'],
            ]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function updateStatus(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->setStatus($id, (string) ($this->jsonBody()['status'] ?? '')));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }
}
