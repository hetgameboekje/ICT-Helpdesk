<?php

namespace App\Api\V1;

use App\Modules\HardwareUitgave\HardwareUitgaveService;

/** Presentation/API-laag voor hardware-uitgaven — /api/v1/hardware-uitgaven*. Businesslogica in HardwareUitgaveService. */
class HardwareUitgavenApiController extends ApiController
{
    private HardwareUitgaveService $service;

    public function __construct()
    {
        $this->service = new HardwareUitgaveService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'hardware', 'lezen');

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
            $this->requirePermission($user, 'hardware', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function updateStatus(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'hardware', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->setStatus($id, (string) ($this->jsonBody()['status'] ?? '')));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'hardware', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }
}
