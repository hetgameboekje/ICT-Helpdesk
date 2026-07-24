<?php

namespace App\Modules\Beheer;

use App\Core\Controller;
use App\Core\Table;
use App\Shared\Rechten\Models\RechtenModel;
use App\Shared\User\Models\UserModel;

class RechtenController extends Controller
{
    public function index(): void
    {
        $this->requireAdmin();
        require_once APP_ROOT . '/app/Views/partials/beheer-tabs.php';

        $gebruikers = UserModel::all('naam ASC');
        $gedeactiveerd = UserModel::allGedeactiveerd('naam ASC');

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $table = (new Table())
            ->emptyText('Geen gebruikers gevonden.')
            ->rowUrl(fn (array $g) => '/beheer/rechten/' . (int) $g['id'])
            ->column('naam', 'Naam', fn (array $g) => htmlspecialchars($g['naam']), ['class' => 'col-3', 'sortable' => false])
            ->column('email', 'E-mailadres', fn (array $g) => htmlspecialchars($g['email']), ['sortable' => false])
            ->column('rol', 'Rol', fn (array $g) => htmlspecialchars(ucfirst($g['rol'])), ['class' => 'col-2', 'sortable' => false])
            ->column('acties', '', function (array $g): string {
                if ((int) $g['id'] === $this->currentUserId()) {
                    return '';
                }

                return '<form method="post" action="/beheer/rechten/' . (int) $g['id'] . '/verwijderen" '
                    . 'onsubmit="return confirm(\'Login van ' . htmlspecialchars(addslashes($g['naam']), ENT_QUOTES) . ' deactiveren? De gebruiker kan dan niet meer inloggen, maar blijft zichtbaar in de historie.\')">'
                    . '<button class="btn" type="submit" title="Deactiveren"><i class="bi bi-trash"></i></button></form>';
            }, ['class' => 'col-1', 'sortable' => false])
            ->rows($gebruikers);

        $content = beheerTabs('rechten')
            . '<div class="page-header"><div class="page-title">Rechten</div>'
            . '<a class="btn btn-primary" href="/beheer/rechten/nieuw">+ Nieuwe gebruiker</a></div>'
            . ($flashSuccess ? '<div class="alert alert-success">' . htmlspecialchars($flashSuccess) . '</div>' : '')
            . ($flashError ? '<div class="alert alert-error">' . htmlspecialchars($flashError) . '</div>' : '')
            . '<div class="card">' . $table->render() . '</div>';

        if ($gedeactiveerd !== []) {
            $deactiefTable = (new Table())
                ->rowUrl(fn (array $g) => '/beheer/rechten/' . (int) $g['id'])
                ->column('naam', 'Naam', fn (array $g) => htmlspecialchars($g['naam']), ['class' => 'col-3', 'sortable' => false])
                ->column('email', 'E-mailadres', fn (array $g) => htmlspecialchars($g['email']), ['sortable' => false])
                ->column('acties', '', fn (array $g) => '<form method="post" action="/beheer/rechten/' . (int) $g['id'] . '/heractiveren">'
                    . '<button class="btn" type="submit" title="Heractiveren"><i class="bi bi-arrow-counterclockwise"></i></button></form>',
                    ['class' => 'col-1', 'sortable' => false])
                ->rows($gedeactiveerd);

            $content .= '<div class="page-header" style="margin-top:24px"><div class="page-title" style="font-size:16px">Gedeactiveerde gebruikers</div></div>'
                . '<div class="card">' . $deactiefTable->render() . '</div>';
        }

        $this->renderContent($content, [
            'activeModule' => 'beheer',
            'pageTitle' => 'Rechten',
        ]);
    }

    public function aanmaken(): void
    {
        $this->requireAdmin();

        $this->render('Modules/Beheer/Views/RechtenView/aanmaken', [
            'activeModule' => 'beheer',
            'pageTitle' => 'Nieuwe gebruiker',
            'oud' => ['naam' => '', 'email' => '', 'rol' => 'medewerker'],
            'fout' => null,
        ]);
    }

    public function opslaan(): void
    {
        $this->requireAdmin();

        $naam = trim((string) ($_POST['naam'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $wachtwoord = (string) ($_POST['wachtwoord'] ?? '');
        $rol = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'medewerker';

        $fout = $this->valideerGebruiker($naam, $email, $wachtwoord);

        if ($fout !== null) {
            $this->render('Modules/Beheer/Views/RechtenView/aanmaken', [
                'activeModule' => 'beheer',
                'pageTitle' => 'Nieuwe gebruiker',
                'oud' => ['naam' => $naam, 'email' => $email, 'rol' => $rol],
                'fout' => $fout,
            ]);
            return;
        }

        $id = UserModel::create([
            'naam' => $naam,
            'email' => $email,
            'wachtwoord_hash' => password_hash($wachtwoord, PASSWORD_DEFAULT),
            'rol' => $rol,
        ]);

        $_SESSION['flash_success'] = "Gebruiker {$naam} aangemaakt.";
        $this->redirect('/beheer/rechten/' . $id);
    }

    public function bewerken(int $id): void
    {
        $this->requireAdmin();

        $gebruiker = UserModel::findIncludingDeleted($id);
        if ($gebruiker === null) {
            http_response_code(404);
            echo 'Gebruiker niet gevonden.';
            return;
        }

        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $this->render('Modules/Beheer/Views/RechtenView/bewerken', [
            'activeModule' => 'beheer',
            'pageTitle' => 'Rechten — ' . $gebruiker['naam'],
            'gebruiker' => $gebruiker,
            'modules' => RechtenModel::MODULES,
            'rechten' => RechtenModel::forUser($id),
            'magVerwijderen' => $id !== $this->currentUserId(),
            'succes' => $flashSuccess,
            'fout' => $flashError,
        ]);
    }

    public function gebruikerBijwerken(int $id): void
    {
        $this->requireAdmin();

        $gebruiker = UserModel::findIncludingDeleted($id);
        if ($gebruiker === null) {
            http_response_code(404);
            echo 'Gebruiker niet gevonden.';
            return;
        }

        $naam = trim((string) ($_POST['naam'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $rol = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'medewerker';

        $fout = $this->valideerGebruiker($naam, $email, null, $id);

        if ($fout !== null) {
            $_SESSION['flash_error'] = $fout;
            $this->redirect('/beheer/rechten/' . $id);
            return;
        }

        UserModel::update($id, ['naam' => $naam, 'email' => $email, 'rol' => $rol]);

        if ($id === $this->currentUserId()) {
            $_SESSION['user']['naam'] = $naam;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['rol'] = $rol;
        }

        $_SESSION['flash_success'] = "Gegevens van {$naam} bijgewerkt.";
        $this->redirect('/beheer/rechten/' . $id);
    }

    public function wachtwoordWijzigen(int $id): void
    {
        $this->requireAdmin();

        $gebruiker = UserModel::findIncludingDeleted($id);
        if ($gebruiker === null) {
            http_response_code(404);
            echo 'Gebruiker niet gevonden.';
            return;
        }

        $wachtwoord = (string) ($_POST['wachtwoord'] ?? '');
        $bevestiging = (string) ($_POST['wachtwoord_bevestiging'] ?? '');

        if (strlen($wachtwoord) < 8) {
            $_SESSION['flash_error'] = 'Wachtwoord moet minstens 8 tekens lang zijn.';
            $this->redirect('/beheer/rechten/' . $id);
            return;
        }

        if ($wachtwoord !== $bevestiging) {
            $_SESSION['flash_error'] = 'Wachtwoorden komen niet overeen.';
            $this->redirect('/beheer/rechten/' . $id);
            return;
        }

        UserModel::update($id, ['wachtwoord_hash' => password_hash($wachtwoord, PASSWORD_DEFAULT)]);

        $_SESSION['flash_success'] = "Wachtwoord van {$gebruiker['naam']} gewijzigd.";
        $this->redirect('/beheer/rechten/' . $id);
    }

    /** "Verwijderen" deactiveert de login (soft delete): de rij, rechten en historische verwijzingen blijven bewaard. */
    public function verwijderen(int $id): void
    {
        $this->requireAdmin();

        $gebruiker = UserModel::find($id);
        if ($gebruiker === null) {
            http_response_code(404);
            echo 'Gebruiker niet gevonden.';
            return;
        }

        if ($id === $this->currentUserId()) {
            $_SESSION['flash_error'] = 'Je kunt je eigen account niet deactiveren.';
            $this->redirect('/beheer/rechten');
            return;
        }

        UserModel::delete($id);

        $_SESSION['flash_success'] = "Gebruiker {$gebruiker['naam']} gedeactiveerd. De login kan niet meer inloggen, maar blijft zichtbaar in de historie.";
        $this->redirect('/beheer/rechten');
    }

    public function heractiveren(int $id): void
    {
        $this->requireAdmin();

        $gebruiker = UserModel::findIncludingDeleted($id);
        if ($gebruiker === null) {
            http_response_code(404);
            echo 'Gebruiker niet gevonden.';
            return;
        }

        UserModel::restore($id);

        $_SESSION['flash_success'] = "Gebruiker {$gebruiker['naam']} heractiveerd en kan weer inloggen.";
        $this->redirect('/beheer/rechten/' . $id);
    }

    public function bijwerken(int $id): void
    {
        $this->requireAdmin();

        $gebruiker = UserModel::findIncludingDeleted($id);
        if ($gebruiker === null) {
            http_response_code(404);
            echo 'Gebruiker niet gevonden.';
            return;
        }

        $moduleRechten = [];
        foreach (array_keys(RechtenModel::MODULES) as $module) {
            $moduleRechten[$module] = [
                'lezen' => !empty($_POST['rechten'][$module]['lezen']),
                'schrijven' => !empty($_POST['rechten'][$module]['schrijven']),
                'verwijderen' => !empty($_POST['rechten'][$module]['verwijderen']),
            ];
        }

        RechtenModel::setForUser($id, $moduleRechten);

        $_SESSION['flash_success'] = "Rechten van {$gebruiker['naam']} bijgewerkt.";
        $this->redirect('/beheer/rechten');
    }

    private function valideerGebruiker(string $naam, string $email, ?string $wachtwoord, ?int $exceptId = null): ?string
    {
        if ($naam === '' || $email === '') {
            return 'Naam en e-mailadres zijn verplicht.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Voer een geldig e-mailadres in.';
        }

        if (UserModel::emailExists($email, $exceptId)) {
            return 'Er bestaat al een gebruiker met dit e-mailadres.';
        }

        if ($wachtwoord !== null && strlen($wachtwoord) < 8) {
            return 'Wachtwoord moet minstens 8 tekens lang zijn.';
        }

        return null;
    }
}
