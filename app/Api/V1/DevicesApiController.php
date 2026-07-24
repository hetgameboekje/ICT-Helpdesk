<?php

namespace App\Api\V1;

use App\Modules\Device\DeviceService;

/** Presentation/API-laag voor apparaten — /api/v1/apparaten*. Businesslogica in DeviceService. */
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
            $this->success($result['items'], ['pagination' => $result['pagination'], 'filterOptions' => $result['filterOptions']]);
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
