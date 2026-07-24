<?php

namespace App\Modules\Printer;

use App\Core\Exceptions\NotFoundException;
use App\Core\TableQuery;
use App\Modules\Printer\Models\PrinterModel;

/**
 * Service/Business-laag voor printers. Let op: het echte datamodel is een driver/netwerk-
 * registratie (naam, print-server, driver, ip-adres) met een gegenereerd Windows-installatie-
 * commando — geen live monitoring. De Lovable-mockup (modules.printer.tsx) toont online/offline-
 * status, tonerniveaus en printjobs, die hier niet bestaan; deze Service dekt daarom alleen
 * lijst/detail/verwijderen. Aanmaken/bewerken blijft op de bestaande server-rendered formulieren.
 */
class PrinterService
{
    /** @return array{items:array,pagination:array,filterOptions:array} */
    public function list(array $queryParams): array
    {
        $allItems = PrinterModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'naam');
        $pagination = TableQuery::paginate($items, $queryParams);

        $types = array_values(array_unique(array_filter(array_column($allItems, 'type'))));
        sort($types);
        $servers = array_values(array_unique(array_filter(array_column($allItems, 'computer_naam'))));
        sort($servers);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'filterOptions' => [
                'type' => array_combine($types, $types),
                'computer_naam' => array_combine($servers, $servers),
            ],
            'stats' => [
                'totaal' => count($allItems),
                'servers' => count($servers),
                'types' => count($types),
                'zonderIp' => count(array_filter($allItems, fn (array $p) => empty($p['ip_adres']))),
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = PrinterModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Printer {$id} niet gevonden.");
        }

        return ['item' => $item, 'installCommand' => PrinterModel::buildInstallCommand($item)];
    }

    public function delete(int $id): void
    {
        if (PrinterModel::find($id) === null) {
            throw new NotFoundException("Printer {$id} niet gevonden.");
        }

        PrinterModel::delete($id);
    }
}
