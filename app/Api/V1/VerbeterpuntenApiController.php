<?php

namespace App\Api\V1;

use App\Modules\Verbeterpunt\VerbeterpuntService;

/**
 * Presentation/API-laag voor verbeterpunten — /api/v1/verbeterpunten*. Bevat alleen
 * requestafhandeling; alle businesslogica staat in VerbeterpuntService.
 */
class VerbeterpuntenApiController extends ApiController
{
    private VerbeterpuntService $service;

    public function __construct()
    {
        $this->service = new VerbeterpuntService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'verbeterpunten', 'lezen');

            $result = $this->service->list($user, $_GET);
            $this->success($result['items'], [
                'pagination' => $result['pagination'],
                'statusCounts' => $result['statusCounts'],
            ]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'verbeterpunten', 'lezen');

            $this->success($this->service->find($id, $user));
        });
    }

    public function store(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'verbeterpunten', 'schrijven');
            $this->requireCsrf();

            $id = $this->service->create($this->jsonBody(), $user);
            $this->success($this->service->find($id, $user), status: 201);
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'verbeterpunten', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody(), $user));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'verbeterpunten', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id, $user);
            $this->success(null, status: 204);
        });
    }

    public function addLog(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'verbeterpunten', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->addLog($id, $this->jsonBody(), $user), status: 201);
        });
    }
}
