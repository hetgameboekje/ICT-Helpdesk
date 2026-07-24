<?php

namespace App\Api\V1;

use App\Modules\Schijfgebruik\SchijfgebruikService;

/** Presentation/API-laag voor schijfgebruik — /api/v1/schijfgebruik. Businesslogica in SchijfgebruikService. */
class SchijfgebruikApiController extends ApiController
{
    private SchijfgebruikService $service;

    public function __construct()
    {
        $this->service = new SchijfgebruikService();
    }

    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requirePermission($user, 'schijfgebruik', 'lezen');

            $result = $this->service->list($_GET);
            $this->success($result['items'], [
                'pagination' => $result['pagination'],
                'stats' => $result['stats'],
                'filterOptions' => $result['filterOptions'],
            ]);
        });
    }
}
