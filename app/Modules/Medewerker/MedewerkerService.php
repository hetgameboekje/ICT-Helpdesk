<?php

namespace App\Modules\Medewerker;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\Device\Models\DeviceModel;
use App\Modules\Medewerker\Models\MedewerkerModel;
use App\Modules\Schijfgebruik\Models\SchijfgebruikDeviceModel;
use App\Modules\Ticket\Models\TicketModel;
use App\Modules\Uitgifte\Models\UitgifteModel;

/**
 * Service/Business-laag voor medewerkers. Let op t.o.v. de Lovable-mockup
 * (modules.medewerker.tsx): het echte status-veld kent alleen actief/inactief, geen "verlof" — de
 * KPI-rij en statusindicator zijn daarop aangepast. De hiërarchie (manager_id/is_keyuser) bestaat
 * al echt (zie CLAUDE.md-roadmap, MedewerkerController::hierarchie()) en wordt hier per
 * geselecteerde medewerker afgeleid i.p.v. verzonnen. "In behandeling nu" is een echte, live
 * telling van open tickets op naam van de gekoppelde login (behandelaar_id), niet mockdata.
 */
class MedewerkerService
{
    /** @return array{items:array,pagination:array,stats:array} */
    public function list(array $queryParams): array
    {
        $allItems = MedewerkerModel::allWithRelations();
        $items = TableQuery::apply($allItems, $queryParams, 'achternaam');
        $pagination = TableQuery::paginate($items, $queryParams);

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'stats' => [
                'totaal' => count($allItems),
                'keyusers' => count(array_filter($allItems, fn (array $m) => !empty($m['is_keyuser']))),
                'actief' => count(array_filter($allItems, fn (array $m) => $m['status'] === 'actief')),
                'inactief' => count(array_filter($allItems, fn (array $m) => $m['status'] === 'inactief')),
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = MedewerkerModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Medewerker {$id} niet gevonden.");
        }

        $naam = trim($item['voornaam'] . ' ' . $item['achternaam']);
        $hostnames = array_filter(array_map('trim', explode(',', $item['apparaat_hostnames'] ?? '')));
        $schijfgebruik = array_values(array_filter(array_map(
            fn (string $hostnaam) => SchijfgebruikDeviceModel::findByNaam($hostnaam),
            $hostnames
        )));

        $alleMedewerkers = MedewerkerModel::allWithRelations();
        $team = array_values(array_filter($alleMedewerkers, fn (array $m) => $m['manager_id'] === $item['id']));

        return [
            'item' => $item,
            'team' => $team,
            'inBehandeling' => $item['user_id'] !== null ? $this->tellenInBehandeling((int) $item['user_id']) : 0,
            'uitgiften' => UitgifteModel::forMedewerkerNaam($naam),
            'apparaten' => DeviceModel::forMedewerker($id),
            'schijfgebruik' => $schijfgebruik,
        ];
    }

    public function create(array $input): int
    {
        $data = $this->validate($input);
        $data['user_id'] = $this->gekoppeldeUserId($data['email'], null);

        return MedewerkerModel::create($data);
    }

    public function update(int $id, array $input): array
    {
        if (MedewerkerModel::find($id) === null) {
            throw new NotFoundException("Medewerker {$id} niet gevonden.");
        }

        $data = $this->validate($input);
        $data['user_id'] = $this->gekoppeldeUserId($data['email'], $id);
        if ($data['manager_id'] === $id) {
            $data['manager_id'] = null;
        }

        MedewerkerModel::update($id, $data);

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (MedewerkerModel::find($id) === null) {
            throw new NotFoundException("Medewerker {$id} niet gevonden.");
        }

        MedewerkerModel::delete($id);
    }

    /** Zelfde koppellogica als MedewerkerController::gekoppeldeUserId(). */
    private function gekoppeldeUserId(string $email, ?int $exceptMedewerkerId): ?int
    {
        if ($email === '' || MedewerkerModel::loginStatusVoorEmail($email, $exceptMedewerkerId) !== 'gevonden') {
            return null;
        }

        return MedewerkerModel::userIdVoorEmail($email);
    }

    /** Zelfde velden als MedewerkerController::validatedData(), met verplichte-veldcheck toegevoegd
     *  t.b.v. clients zonder formuliervalidatie (bv. een mobiele app). */
    private function validate(array $input): array
    {
        $voornaam = trim((string) ($input['voornaam'] ?? ''));
        $achternaam = trim((string) ($input['achternaam'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));

        $errors = [];
        if ($voornaam === '') {
            $errors['voornaam'][] = 'Voornaam is verplicht.';
        }
        if ($achternaam === '') {
            $errors['achternaam'][] = 'Achternaam is verplicht.';
        }
        if ($email === '') {
            $errors['email'][] = 'E-mailadres is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'voornaam' => $voornaam,
            'achternaam' => $achternaam,
            'email' => $email,
            'telefoon' => trim((string) ($input['telefoon'] ?? '')),
            'functie' => trim((string) ($input['functie'] ?? '')),
            'afdeling_id' => ($input['afdeling_id'] ?? '') !== '' ? (int) $input['afdeling_id'] : null,
            'manager_id' => ($input['manager_id'] ?? '') !== '' ? (int) $input['manager_id'] : null,
            'is_keyuser' => !empty($input['is_keyuser']) ? 1 : 0,
            'startdatum' => ($input['startdatum'] ?? '') !== '' ? $input['startdatum'] : null,
            'status' => in_array($input['status'] ?? '', ['actief', 'inactief'], true) ? $input['status'] : 'actief',
        ];
    }

    private function tellenInBehandeling(int $userId): int
    {
        $tickets = TicketModel::allWithRelations();

        return count(array_filter(
            $tickets,
            fn (array $t) => (int) ($t['behandelaar_id'] ?? 0) === $userId && $t['status'] === 'in_behandeling'
        ));
    }
}
