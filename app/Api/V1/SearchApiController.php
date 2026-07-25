<?php

namespace App\Api\V1;

use App\Shared\Search\SearchService;

class SearchApiController extends ApiController
{
    public function index(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();

            $service = new SearchService();
            $data = $service->search($user, (string) ($_GET['q'] ?? ''));

            $this->success($data);
        });
    }
}
