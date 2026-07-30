<?php

namespace App\Api\V1;

use App\Core\Exceptions\ValidationException;
use App\Shared\AssetScan\AssetEnrichmentService;

/**
 * Presentation/API-laag voor barcode-scan-herkenning — /api/v1/asset-scan. Geen businesslogica
 * hier, alleen requestafhandeling; alle parsing/matching/verrijking + audit-logging staat in
 * App\Shared\AssetScan\AssetEnrichmentService (zie die klasse voor de architectuur). Gebruikt door
 * zowel het Uitgifte- als het Voorraad-formulier (uitgiften-index.js, VoorraadView/create.php) —
 * daarom geen module-specifieke Service, maar een gedeelde laag onder App\Shared.
 */
class AssetScanApiController extends ApiController
{
    private const TOEGESTANE_CONTEXTEN = ['uitgifte', 'voorraad'];

    private AssetEnrichmentService $service;

    public function __construct()
    {
        $this->service = new AssetEnrichmentService();
    }

    public function analyseer(): void
    {
        $this->handle(function () {
            $user = $this->requireAuth();
            $this->requireCsrf();

            $body = $this->jsonBody();
            $raw = trim((string) ($body['raw'] ?? ''));
            $context = in_array($body['context'] ?? '', self::TOEGESTANE_CONTEXTEN, true)
                ? $body['context']
                : 'uitgifte';

            $this->requirePermission($user, $context === 'voorraad' ? 'voorraad' : 'uitgiften', 'lezen');

            if ($raw === '') {
                throw new ValidationException(['raw' => ['Scanwaarde is verplicht.']]);
            }

            $result = $this->service->analyseer($raw, $context, (int) $user['id']);
            $this->success($result);
        });
    }
}
