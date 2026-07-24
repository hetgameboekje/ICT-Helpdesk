<?php

namespace App\Api\V1;

use App\Modules\Device\DeviceService;

/**
 * Presentation/API-laag voor apparaten — /api/v1/apparaten*. Alleen index/show/update/destroy:
 * aanmaken loopt via CSV-upload op de oude server-rendered route (zie DeviceService).
 */
class DevicesApiController extends ApiController
{
    private DeviceService $service;

    public function __construct()
    {
        $this->service = new DeviceService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'apparaten', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], ['pagination' => $result['pagination'], 'kpis' => $result['kpis']]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'apparaten', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'apparaten', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody()));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'apparaten', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }
}
