<?php

namespace App\Modules\Kennisbank;

use App\Core\Exceptions\NotFoundException;
use App\Core\Exceptions\ValidationException;
use App\Core\TableQuery;
use App\Modules\EmailVerwerking\Models\KbArticleDraftModel;
use App\Modules\Kennisbank\Models\KennisbankLogModel;
use App\Modules\Kennisbank\Models\KennisbankModel;
use App\Modules\Ticket\Models\TicketKennisbankModel;

/**
 * Service/Business-laag voor de kennisbank: validatie en data-samenstelling voor de API-laag
 * (App\Api\V1\KennisbankApiController). Kent geen HTTP-concepten. Kennisbank heeft, anders dan
 * tickets, geen scope-autorisatie per item (elk ingelogd account met leesrecht ziet alle artikelen).
 */
class KennisbankService
{
    /** @return array{items:array,pagination:array,filterOptions:array,kpis:array} */
    public function list(array $queryParams): array
    {
        $allItems = KennisbankModel::allWithRelations();

        $items = $this->applyDefaultFilters($allItems, $queryParams);
        $tableQueryParams = array_diff_key($queryParams, ['tag' => null]);
        $items = TableQuery::apply($items, $tableQueryParams, 'titel');
        $pagination = TableQuery::paginate($items, $queryParams);

        $categorieBoom = KennisbankModel::categorieBoom();
        $draftTellingen = KbArticleDraftModel::telPerStatus();

        return [
            'items' => $pagination['items'],
            'pagination' => [
                'page' => $pagination['page'],
                'perPage' => $pagination['perPage'],
                'totalPages' => $pagination['totalPages'],
                'total' => $pagination['total'],
            ],
            'filterOptions' => [
                'categorieBoom' => $categorieBoom,
            ],
            'kpis' => [
                'aantalArtikelen' => count($allItems),
                'aantalCategorieen' => count($categorieBoom),
                'conceptenInReview' => ($draftTellingen['draft_created'] ?? 0) + ($draftTellingen['in_review'] ?? 0),
            ],
        ];
    }

    public function find(int $id): array
    {
        $item = KennisbankModel::findWithRelations($id);
        if ($item === null) {
            throw new NotFoundException("Kennisbankartikel {$id} niet gevonden.");
        }

        return [
            'item' => $item,
            'logs' => KennisbankLogModel::forArtikel($id),
            'gekoppeldeTickets' => TicketKennisbankModel::gekoppeldeTicketsVoorArtikel($id),
        ];
    }

    public function create(array $input, array $currentUser): int
    {
        $data = $this->validate($input);
        $data['auteur_id'] = $currentUser['id'];

        return KennisbankModel::create($data);
    }

    public function update(int $id, array $input): array
    {
        $huidig = KennisbankModel::find($id);
        if ($huidig === null) {
            throw new NotFoundException("Kennisbankartikel {$id} niet gevonden.");
        }

        $data = KennisbankModel::alleenGewijzigdeVelden($huidig, $this->validate($input));
        if ($data !== []) {
            KennisbankModel::update($id, $data);
        }

        return $this->find($id);
    }

    public function delete(int $id): void
    {
        if (KennisbankModel::find($id) === null) {
            throw new NotFoundException("Kennisbankartikel {$id} niet gevonden.");
        }

        KennisbankModel::delete($id);
    }

    private function applyDefaultFilters(array $items, array $queryParams): array
    {
        $categorie = trim((string) ($queryParams['categorie'] ?? ''));
        if ($categorie !== '') {
            $items = array_values(array_filter($items, fn (array $item) => $item['categorie'] === $categorie));
        }

        $subcategorie = trim((string) ($queryParams['subcategorie'] ?? ''));
        if ($subcategorie !== '') {
            $items = array_values(array_filter($items, fn (array $item) => ($item['subcategorie'] ?? '') === $subcategorie));
        }

        $tags = $this->normalizeTagParam($queryParams['tag'] ?? []);
        if ($tags !== []) {
            $needles = array_map('mb_strtolower', $tags);
            $items = array_values(array_filter(
                $items,
                fn (array $item) => array_intersect(
                    $needles,
                    array_map('mb_strtolower', KennisbankModel::splitTags($item['tags'] ?? null))
                ) !== []
            ));
        }

        return $items;
    }

    private function normalizeTagParam(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = $tags === '' ? [] : [$tags];
        }

        return array_values(array_filter(array_map('trim', (array) $tags), fn (string $t) => $t !== ''));
    }

    private function validate(array $input): array
    {
        $titel = trim((string) ($input['titel'] ?? ''));
        $inhoud = trim((string) ($input['inhoud'] ?? ''));

        $errors = [];
        if ($titel === '') {
            $errors['titel'][] = 'Titel is verplicht.';
        }
        if ($inhoud === '') {
            $errors['inhoud'][] = 'Inhoud is verplicht.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'titel' => $titel,
            'categorie' => trim((string) ($input['categorie'] ?? '')) ?: 'Algemeen',
            'subcategorie' => trim((string) ($input['subcategorie'] ?? '')) ?: null,
            'samenvatting' => trim((string) ($input['samenvatting'] ?? '')) ?: null,
            'tags' => KennisbankModel::normalizeTags((string) ($input['tags'] ?? '')),
            'inhoud' => $inhoud,
        ];
    }
}
