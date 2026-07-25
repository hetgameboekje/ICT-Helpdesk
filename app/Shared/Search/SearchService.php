<?php

namespace App\Shared\Search;

use App\Modules\Kennisbank\Models\KennisbankModel;
use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Shared\Rechten\Models\RechtenModel;

/**
 * Backend voor de globale zoekbalk rechtsboven (topbar-search, app/Views/layouts/app.php). Bundelt
 * per module een eigen SQL LIKE-zoekopdracht (TicketModel::search()/KennisbankModel::search()/
 * MedewerkerModel::search()) i.p.v. alles op te halen en in PHP te filteren zoals TableQuery::search()
 * elders doet — dat zou bij een globale zoekbalk over meerdere modules tegelijk onnodig zwaar zijn.
 * Elke module wordt alleen doorzocht als de gebruiker er leesrecht voor heeft (of admin is); Tickets
 * past bovendien dezelfde rij-scope toe als TicketService::scopeAllowed(), direct in de SQL-query.
 */
class SearchService
{
    private const LIMIT_PER_MODULE = 5;

    public function search(array $currentUser, string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['tickets' => [], 'medewerkers' => [], 'kennisbank' => []];
        }

        return [
            'tickets' => $this->magLezen($currentUser, 'tickets')
                ? TicketModel::search($q, $currentUser, self::LIMIT_PER_MODULE)
                : [],
            'medewerkers' => $this->magLezen($currentUser, 'medewerkers')
                ? MedewerkerModel::search($q, self::LIMIT_PER_MODULE)
                : [],
            'kennisbank' => $this->magLezen($currentUser, 'kennisbank')
                ? KennisbankModel::search($q, self::LIMIT_PER_MODULE)
                : [],
        ];
    }

    private function magLezen(array $currentUser, string $module): bool
    {
        return ($currentUser['rol'] ?? '') === 'admin'
            || RechtenModel::has((int) ($currentUser['id'] ?? 0), $module, 'lezen');
    }
}
