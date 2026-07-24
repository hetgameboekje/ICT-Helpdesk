<?php

namespace App\Api\V1;

use App\Modules\Printer\PrinterService;

/**
 * Presentation/API-laag voor printers — /api/v1/printers*. Bevat alleen requestafhandeling; alle
 * businesslogica staat in PrinterService.
 */
class PrintersApiController extends ApiController
{
    private PrinterService $service;

    public function __construct()
    {
        $this->service = new PrinterService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'printers', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], ['pagination' => $result['pagination'], 'kpis' => $result['kpis']]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'printers', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function store(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'printers', 'schrijven');
            $this->requireCsrf();

            $id = $this->service->create($this->jsonBody(), $user);
            $this->success($this->service->find($id), status: 201);
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'printers', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody()));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'printers', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }
}
