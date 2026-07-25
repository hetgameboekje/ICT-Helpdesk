<?php

namespace App\Api\V1;

use App\Modules\Voorraad\VoorraadService;

/**
 * Presentation/API-laag voor voorraad — /api/v1/voorraad*. create/update dekken de kernvelden, geen
 * DxDiag-upload (multipart) — zie VoorraadService.
 */
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
            $this->success($result['items'], ['pagination' => $result['pagination'], 'kpis' => $result['kpis']]);
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

    public function store(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'schrijven');
            $this->requireCsrf();

            $result = $this->service->create($this->jsonBody(), $user);
            $this->success($this->service->find($result['ids'][count($result['ids']) - 1]), status: 201);
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody()));
        });
    }
}
