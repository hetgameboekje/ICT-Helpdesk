<?php

namespace App\Api\V1;

use App\Modules\Script\ScriptService;

/** Presentation/API-laag voor scripts — /api/v1/scripts*. Businesslogica in ScriptService. */
class ScriptsApiController extends ApiController
{
    private ScriptService $service;

    public function __construct()
    {
        $this->service = new ScriptService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'scripts', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], ['pagination' => $result['pagination'], 'typeCounts' => $result['typeCounts']]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'scripts', 'lezen');

            $this->success($this->service->find($id));
        });
    }

    public function store(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'scripts', 'schrijven');
            $this->requireCsrf();

            $id = $this->service->create($this->jsonBody(), $user);
            $this->success($this->service->find($id), status: 201);
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'scripts', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody()));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'scripts', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id);
            $this->success(null, status: 204);
        });
    }
}
