<?php

namespace App\Api\V1;

use App\Modules\CyberRisico\CyberRisicoService;

/** Presentation/API-laag voor cyberrisico's — /api/v1/cyberrisicos*. Businesslogica in CyberRisicoService. */
class CyberRisicosApiController extends ApiController
{
    private CyberRisicoService $service;

    public function __construct()
    {
        $this->service = new CyberRisicoService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'cyberrisicos', 'lezen');

            $result = $this->service->list($user, $_GET);
            $this->success($result['items'], [
                'pagination' => $result['pagination'],
                'prioriteitCounts' => $result['prioriteitCounts'],
                'filterOptions' => $result['filterOptions'],
            ]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'cyberrisicos', 'lezen');

            $this->success($this->service->find($id, $user));
        });
    }

    public function addLog(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'cyberrisicos', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->addLog($id, $this->jsonBody(), $user), status: 201);
        });
    }

    public function updateStatus(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'cyberrisicos', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->setStatus($id, (string) ($this->jsonBody()['status'] ?? ''), $user));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'cyberrisicos', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id, $user);
            $this->success(null, status: 204);
        });
    }
}
