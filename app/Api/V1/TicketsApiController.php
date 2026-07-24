<?php

namespace App\Api\V1;

use App\Modules\Ticket\TicketService;

/**
 * Presentation/API-laag voor tickets — /api/v1/tickets*. Bevat alleen requestafhandeling (auth,
 * CSRF, JSON parsen, statuscodes); alle businesslogica staat in TicketService.
 */
class TicketsApiController extends ApiController
{
    private TicketService $service;

    public function __construct()
    {
        $this->service = new TicketService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'lezen');

            $result = $this->service->list($user, $_GET);
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
            $this->requirePermission($user, 'tickets', 'lezen');

            $this->success($this->service->find($id, $user));
        });
    }

    public function store(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'schrijven');
            $this->requireCsrf();

            $id = $this->service->create($this->jsonBody(), $user);
            $this->success($this->service->find($id, $user), status: 201);
        });
    }

    public function update(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->update($id, $this->jsonBody(), $user));
        });
    }

    public function destroy(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'verwijderen');
            $this->requireCsrf();

            $this->service->delete($id, $user);
            $this->success(null, status: 204);
        });
    }

    public function addLog(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->addLog($id, $this->jsonBody(), $user), status: 201);
        });
    }

    public function addTijd(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->addTijd($id, $this->jsonBody(), $user), status: 201);
        });
    }

    public function kennisbankKoppel(int $id, int $artikelId): void
    {
        $this->handle(function () use ($id, $artikelId) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->kennisbankKoppel($id, $artikelId, $user), status: 201);
        });
    }

    public function kennisbankOntkoppel(int $id, int $artikelId): void
    {
        $this->handle(function () use ($id, $artikelId) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'tickets', 'schrijven');
            $this->requireCsrf();

            $this->success($this->service->kennisbankOntkoppel($id, $artikelId, $user));
        });
    }
}
