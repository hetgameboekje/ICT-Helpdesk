<?php

namespace App\Api\V1;

use App\Modules\Voorraad\VoorraadService;

/**
 * Presentation/API-laag voor voorraad — /api/v1/voorraad*. Alleen index/show (read-only), zie
 * VoorraadService voor waarom create/update hier niet zitten.
 */
class VoorraadApiController extends ApiController
{
    private VoorraadService $service;

    public function __construct()
    {
        $this->service = new VoorraadService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], ['pagination' => $result['pagination'], 'kpis' => $result['kpis']]);
        });
    }

    public function show(int $id): void
    {
        $this->handle(function () use ($id) {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'voorraad', 'lezen');

            $this->success($this->service->find($id));
        });
    }
}
