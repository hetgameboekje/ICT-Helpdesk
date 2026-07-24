<?php

namespace App\Modules\Printer;

use App\Core\CrudController;
use App\Modules\Printer\Models\PrinterModel;

class PrinterController extends CrudController
{
    protected string $modelClass = PrinterModel::class;
    protected string $viewDir = 'Modules/Printer/Views/PrinterView';
    protected string $routeBase = 'printers';
    protected string $activeModule = 'printers';
    protected string $pageTitle = 'Printers';
    protected array $breadcrumbs = ['Assets & Beheer'];
    protected ?string $searchColumn = 'naam';

    protected function filterOptions(array $allItems): array
    {
        $types = array_values(array_unique(array_filter(array_column($allItems, 'type'))));
        sort($types);

        $servers = array_values(array_unique(array_filter(array_column($allItems, 'computer_naam'))));
        sort($servers);

        return [
            'type' => array_combine($types, $types),
            'computer_naam' => array_combine($servers, $servers),
        ];
    }

    protected function validatedData(array $post, bool $isUpdate = false): array
    {
        $data = [
            'naam' => trim($post['naam'] ?? ''),
            'computer_naam' => trim($post['computer_naam'] ?? '') ?: null,
            'type' => trim($post['type'] ?? '') ?: 'Local',
            'driver_naam' => trim($post['driver_naam'] ?? '') ?: null,
            'ip_adres' => trim($post['ip_adres'] ?? '') ?: null,
            'opmerking' => trim($post['opmerking'] ?? '') ?: null,
        ];

        if (!$isUpdate) {
            $data['aangemaakt_door_id'] = $this->currentUserId();
        }

        return $data;
    }
}
