<?php

namespace App\Api\V1;

use App\Modules\Printer\PrinterService;

/** Presentation/API-laag voor printers — /api/v1/printers*. Businesslogica in PrinterService. */
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
            $this->success($result['items'], [
                'pagination' => $result['pagination'],
                'filterOptions' => $result['filterOptions'],
                'stats' => $result['stats'],
            ]);
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
