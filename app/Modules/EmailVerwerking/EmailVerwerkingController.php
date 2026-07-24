<?php

namespace App\Modules\EmailVerwerking;

use App\Core\Controller;
use App\Core\TableQuery;
use App\Modules\EmailVerwerking\Models\EmailAiAnalysisModel;
use App\Modules\EmailVerwerking\Models\EmailAttachmentModel;
use App\Modules\EmailVerwerking\Models\ImportedEmailModel;
use App\Modules\EmailVerwerking\Models\KbArticleDraftModel;
use App\Modules\EmailVerwerking\Models\KbArticleSourceModel;
use App\Modules\EmailVerwerking\Models\ProcessingLogModel;

/** UI achter sessie-auth voor de e-mail-/kennisbankverwerkingspipeline (rechtenmatrix-module 'email_verwerking'). */
class EmailVerwerkingController extends Controller
{
    private const MODULE = 'email_verwerking';
    private const VIEW_DIR = 'Modules/EmailVerwerking/Views/EmailVerwerkingView';

    public function dashboard(): void
    {
        $this->requirePermission(self::MODULE, 'lezen');

        $statusTellingen = ImportedEmailModel::telPerStatus();
        $draftTellingen = KbArticleDraftModel::telPerStatus();
        $config = require APP_ROOT . '/config/config.php';

        // Pipeline-kolommen (zie Lovable mailmind.tsx): dezelfde 4 fases als het bestaande
        // statusmodel al kent, alleen nu ook als losse item-lijsten i.p.v. alleen aantallen.
        $ontvangen = array_merge(
            ImportedEmailModel::metStatus('received'),
            ImportedEmailModel::metStatus('parsed'),
            ImportedEmailModel::metStatus('stored')
        );
        $conceptDrafts = array_merge(
            KbArticleDraftModel::metStatus('draft_created'),
            KbArticleDraftModel::metStatus('in_review')
        );
        $gepubliceerdDrafts = KbArticleDraftModel::metStatus('published');

        $this->render(self::VIEW_DIR . '/dashboard', [
            'activeModule' => self::MODULE,
            'pageTitle' => 'E-mail & kennisbank verwerking',
            'nieuweVandaag' => ImportedEmailModel::countVandaag(),
            'aantalGeanalyseerd' => ($statusTellingen['analyzed'] ?? 0) + ($statusTellingen['draft_created'] ?? 0)
                + ($statusTellingen['in_review'] ?? 0) + ($statusTellingen['published'] ?? 0),
            'conceptenKlaar' => $draftTellingen['draft_created'] ?? 0,
            'wachtOpReview' => $draftTellingen['in_review'] ?? 0,
            'gemiddeldeConfidence' => EmailAiAnalysisModel::gemiddeldeConfidence(),
            'aantalMislukt' => $statusTellingen['failed'] ?? 0,
            'confidenceDrempel' => $config['ai']['confidenceDrempel'],
            'recenteEmails' => array_slice(ImportedEmailModel::allWithRelations(), 0, 8),
            'recenteFouten' => ProcessingLogModel::recenteFouten(5),
            'pipeline' => [
                'ontvangen' => array_slice($ontvangen, -8),
                'geanalyseerd' => array_slice(ImportedEmailModel::metStatus('analyzed'), -8),
                'concept' => array_slice($conceptDrafts, -8),
                'gepubliceerd' => array_slice($gepubliceerdDrafts, -8),
            ],
            'laatsteConcept' => $laatsteConcept = (end($conceptDrafts) ?: null),
            'laatsteConceptBron' => $laatsteConcept
                ? $this->eersteBronEmail((int) $laatsteConcept['id'])
                : null,
        ]);
    }

    /** Eerste bron-e-mail van een concept, voor de "laatste conceptartikel"-preview op het dashboard. */
    private function eersteBronEmail(int $draftId): ?array
    {
        $bronnen = KbArticleSourceModel::forDraft($draftId);
        if (empty($bronnen)) {
            return null;
        }

        return ImportedEmailModel::findWithRelations((int) $bronnen[0]['imported_email_id']);
    }

    public function inbox(): void
    {
        $this->requirePermission(self::MODULE, 'lezen');

        $items = array_values(array_filter(
            ImportedEmailModel::allWithRelations(),
            fn (array $r) => in_array($r['status'], ['received', 'parsed', 'stored'], true)
        ));
        $items = TableQuery::apply($items, $_GET, 'onderwerp');
        $pagination = TableQuery::paginate($items, $_GET);

        $this->render(self::VIEW_DIR . '/inbox', [
            'activeModule' => self::MODULE,
            'pageTitle' => 'Inbox verwerking',
            'items' => $pagination['items'],
            'pagination' => $pagination,
            'sort' => $_GET['sort'] ?? null,
            'dir' => ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ]);
    }

    public function logboek(): void
    {
        $this->requirePermission(self::MODULE, 'lezen');

        $items = ImportedEmailModel::allWithRelations();
        $items = TableQuery::apply($items, $_GET, 'onderwerp');
        $pagination = TableQuery::paginate($items, $_GET);

        $this->render(self::VIEW_DIR . '/logboek', [
            'activeModule' => self::MODULE,
            'pageTitle' => 'E-mail logboek',
            'search' => $_GET['q'] ?? '',
            'items' => $pagination['items'],
            'pagination' => $pagination,
            'sort' => $_GET['sort'] ?? null,
            'dir' => ($_GET['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ]);
    }

    public function show(int $id): void
    {
        $this->requirePermission(self::MODULE, 'lezen');

        $item = ImportedEmailModel::findWithRelations($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $this->render(self::VIEW_DIR . '/show', [
            'activeModule' => self::MODULE,
            'pageTitle' => $item['onderwerp'],
            'item' => $item,
            'analyse' => EmailAiAnalysisModel::forEmail($id),
            'bijlagen' => EmailAttachmentModel::forEmail($id),
            'logs' => ProcessingLogModel::forEmail($id),
            'gekoppeldConcept' => KbArticleSourceModel::draftForEmail($id),
        ]);
    }

    public function review(): void
    {
        $this->requirePermission(self::MODULE, 'lezen');

        $this->render(self::VIEW_DIR . '/review', [
            'activeModule' => self::MODULE,
            'pageTitle' => 'Artikelen reviewen',
            'items' => array_merge(
                KbArticleDraftModel::metStatus('draft_created'),
                KbArticleDraftModel::metStatus('in_review')
            ),
        ]);
    }

    public function conceptBewerken(int $id): void
    {
        $this->requirePermission(self::MODULE, 'lezen');

        $item = KbArticleDraftModel::findWithRelations($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        $this->render(self::VIEW_DIR . '/concept_bewerken', [
            'activeModule' => self::MODULE,
            'pageTitle' => $item['titel'],
            'item' => $item,
            'bronnen' => KbArticleSourceModel::forDraft($id),
            'magSchrijven' => $this->hasRecht(self::MODULE, 'schrijven'),
        ]);
    }

    public function conceptOpslaan(int $id): void
    {
        $this->requirePermission(self::MODULE, 'schrijven');

        $item = KbArticleDraftModel::find($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        KbArticleDraftModel::update($id, [
            'titel' => trim($_POST['titel'] ?? $item['titel']),
            'categorie' => trim($_POST['categorie'] ?? $item['categorie']),
            'subcategorie' => trim($_POST['subcategorie'] ?? '') ?: null,
            'samenvatting' => trim($_POST['samenvatting'] ?? ''),
            'probleem' => trim($_POST['probleem'] ?? ''),
            'oplossing' => trim($_POST['oplossing'] ?? ''),
            'stappenplan' => trim($_POST['stappenplan'] ?? ''),
            'tags' => trim($_POST['tags'] ?? ''),
            'status' => 'in_review',
            'reviewer_id' => $this->currentUserId(),
            'versie' => (int) $item['versie'] + 1,
        ]);

        $_SESSION['flash_success'] = 'Conceptartikel opgeslagen en klaargezet voor review.';
        $this->redirect("/email-verwerking/concepten/{$id}");
    }

    public function publiceren(int $id): void
    {
        $this->requirePermission(self::MODULE, 'schrijven');

        $item = KbArticleDraftModel::find($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        KbArticleDraftModel::publiceer($id, $this->currentUserId());

        $_SESSION['flash_success'] = 'Artikel gepubliceerd in de kennisbank.';
        $this->redirect('/email-verwerking/review');
    }

    public function afwijzen(int $id): void
    {
        $this->requirePermission(self::MODULE, 'schrijven');

        $item = KbArticleDraftModel::find($id);
        if ($item === null) {
            http_response_code(404);
            echo 'Niet gevonden.';
            return;
        }

        KbArticleDraftModel::update($id, ['status' => 'afgewezen', 'reviewer_id' => $this->currentUserId()]);

        $_SESSION['flash_success'] = 'Conceptartikel afgewezen.';
        $this->redirect('/email-verwerking/review');
    }
}
