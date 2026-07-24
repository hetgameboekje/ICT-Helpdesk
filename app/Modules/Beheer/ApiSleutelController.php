<?php

namespace App\Modules\Beheer;

use App\Core\Controller;
use App\Core\Table;
use App\Shared\ApiKey\Models\ApiKeyModel;

class ApiSleutelController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        require_once APP_ROOT . '/app/Views/partials/beheer-tabs.php';

        $sleutels = ApiKeyModel::all('naam ASC');
        $gedeactiveerd = ApiKeyModel::allGedeactiveerd('naam ASC');

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $nieuweSleutel = $_SESSION['flash_nieuwe_sleutel'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_nieuwe_sleutel']);

        $labelVoorScopes = fn (array $s): string => implode(', ', array_map(
            fn (string $scope) => ApiKeyModel::SCOPES[$scope] ?? $scope,
            explode(',', $s['scopes'])
        ));

        $table = (new Table())
            ->emptyText('Geen API-sleutels aangemaakt.')
            ->column('naam', 'Naam', fn (array $s) => htmlspecialchars($s['naam']), ['class' => 'col-3', 'sortable' => false])
            ->column('prefix', 'Sleutel', fn (array $s) => '<code>' . htmlspecialchars($s['key_prefix']) . '…</code>', ['class' => 'col-2', 'sortable' => false])
            ->column('scopes', 'Toegang', fn (array $s) => htmlspecialchars($labelVoorScopes($s)), ['sortable' => false])
            ->column('laatst_gebruikt', 'Laatst gebruikt', fn (array $s) => $s['laatst_gebruikt_at'] ? htmlspecialchars($s['laatst_gebruikt_at']) : '—', ['class' => 'col-2', 'sortable' => false])
            ->column('acties', '', fn (array $s) => '<form method="post" action="/beheer/api-sleutels/' . (int) $s['id'] . '/intrekken" '
                . 'onsubmit="return confirm(\'API-sleutel ' . htmlspecialchars(addslashes($s['naam']), ENT_QUOTES) . ' intrekken? Scripts die deze sleutel gebruiken werken dan niet meer.\')">'
                . '<button class="btn" type="submit" title="Intrekken"><i class="bi bi-trash"></i></button></form>',
                ['class' => 'col-1', 'sortable' => false])
            ->rows($sleutels);

        $content = beheerTabs('api-sleutels')
            . '<div class="page-header"><div class="page-title">API-sleutels</div>'
            . '<a class="btn btn-primary" href="/beheer/api-sleutels/nieuw">+ Nieuwe sleutel</a></div>'
            . ($flashSuccess ? '<div class="alert alert-success">' . htmlspecialchars($flashSuccess) . '</div>' : '');

        if ($nieuweSleutel) {
            $content .= '<div class="alert alert-success">'
                . 'Nieuwe sleutel aangemaakt — kopieer hem nu, hij wordt niet nogmaals getoond:<br>'
                . '<code style="font-size:14px;user-select:all">' . htmlspecialchars($nieuweSleutel) . '</code>'
                . '</div>';
        }

        $content .= '<div class="card">' . $table->render() . '</div>';

        if ($gedeactiveerd !== []) {
            $deactiefTable = (new Table())
                ->column('naam', 'Naam', fn (array $s) => htmlspecialchars($s['naam']), ['class' => 'col-3', 'sortable' => false])
                ->column('prefix', 'Sleutel', fn (array $s) => '<code>' . htmlspecialchars($s['key_prefix']) . '…</code>', ['sortable' => false])
                ->column('acties', '', fn (array $s) => '<form method="post" action="/beheer/api-sleutels/' . (int) $s['id'] . '/heractiveren">'
                    . '<button class="btn" type="submit" title="Heractiveren"><i class="bi bi-arrow-counterclockwise"></i></button></form>',
                    ['class' => 'col-1', 'sortable' => false])
                ->rows($gedeactiveerd);

            $content .= '<div class="page-header" style="margin-top:24px"><div class="page-title" style="font-size:16px">Ingetrokken sleutels</div></div>'
                . '<div class="card">' . $deactiefTable->render() . '</div>';
        }

        $this->renderContent($content, [
            'activeModule' => 'beheer',
            'pageTitle' => 'API-sleutels',
        ]);
    }

    public function aanmaken(): void
    {
        $this->requireAdmin();

        $this->render('Modules/Beheer/Views/ApiSleutelView/aanmaken', [
            'activeModule' => 'beheer',
            'pageTitle' => 'Nieuwe API-sleutel',
            'oud' => ['naam' => '', 'scopes' => []],
            'fout' => null,
        ]);
    }

    public function opslaan(): void
    {
        $this->requireAdmin();

        $naam = trim((string) ($_POST['naam'] ?? ''));
        $scopes = array_values(array_intersect((array) ($_POST['scopes'] ?? []), array_keys(ApiKeyModel::SCOPES)));

        if ($naam === '' || $scopes === []) {
            $this->render('Modules/Beheer/Views/ApiSleutelView/aanmaken', [
                'activeModule' => 'beheer',
                'pageTitle' => 'Nieuwe API-sleutel',
                'oud' => ['naam' => $naam, 'scopes' => $scopes],
                'fout' => 'Naam en minstens één scope zijn verplicht.',
            ]);
            return;
        }

        $resultaat = ApiKeyModel::generate($naam, $scopes, $this->currentUserId());

        $_SESSION['flash_success'] = "API-sleutel \"{$naam}\" aangemaakt.";
        $_SESSION['flash_nieuwe_sleutel'] = $resultaat['plaintext'];
        $this->redirect('/beheer/api-sleutels');
    }

    public function intrekken(int $id): void
    {
        $this->requireAdmin();

        $sleutel = ApiKeyModel::find($id);
        if ($sleutel === null) {
            http_response_code(404);
            echo 'API-sleutel niet gevonden.';
            return;
        }

        ApiKeyModel::delete($id);

        $_SESSION['flash_success'] = "API-sleutel \"{$sleutel['naam']}\" ingetrokken.";
        $this->redirect('/beheer/api-sleutels');
    }

    public function heractiveren(int $id): void
    {
        $this->requireAdmin();

        $sleutel = ApiKeyModel::findIncludingDeleted($id);
        if ($sleutel === null) {
            http_response_code(404);
            echo 'API-sleutel niet gevonden.';
            return;
        }

        ApiKeyModel::restore($id);

        $_SESSION['flash_success'] = "API-sleutel \"{$sleutel['naam']}\" heractiveerd.";
        $this->redirect('/beheer/api-sleutels');
    }
}
