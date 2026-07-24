<?php

namespace App\Api\V1;

use App\Modules\Kennisbank\KennisbankService;

/**
 * Presentation/API-laag voor de kennisbank — /api/v1/kennisbank*. Bevat alleen requestafhandeling
 * (auth, CSRF, JSON parsen, statuscodes); alle businesslogica staat in KennisbankService.
 */
class KennisbankApiController extends ApiController
{
    private KennisbankService $service;

    public function __construct()
    {
        $this->service = new KennisbankService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'kennisbank', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], [
                'pagination' => $result['pagination'],
                'filterOptions' => $result['filterOptions'],
                'kpis' => $result['kpis'],
            ]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'kennisbank', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function store(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'kennisbank', 'schrijven');
            $this->requireCsrf();

            $id = $this->service->create($this->jsonBody(), $user);
            $this->success($this->service->find($id), status: 201);
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'kennisbank', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody()));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'kennisbank', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }
}
