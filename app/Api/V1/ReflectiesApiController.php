<?php

namespace App\Api\V1;

use App\Modules\Reflectie\ReflectieService;

/** Presentation/API-laag voor reflecties — /api/v1/reflecties*. Businesslogica in ReflectieService. */
class ReflectiesApiController extends ApiController
{
    private ReflectieService $service;

    public function __construct()
    {
        $this->service = new ReflectieService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'reflecties', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], ['pagination' => $result['pagination']]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'reflecties', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function store(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'reflecties', 'schrijven');
            $this->requireCsrf();

            $id = $this->service->create($this->jsonBody(), $user);
            $this->success($this->service->find($id), status: 201);
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'reflecties', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody()));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'reflecties', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }

    public function addLog(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'reflecties', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->addLog($id, $this->jsonBody(), $user), status: 201);
        });
    }
}
