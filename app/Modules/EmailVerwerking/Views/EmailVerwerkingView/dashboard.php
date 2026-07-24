<?php
/** @var int $nieuweVandaag */
/** @var int $aantalGeanalyseerd */
/** @var int $conceptenKlaar */
/** @var int $wachtOpReview */
/** @var float $gemiddeldeConfidence */
/** @var int $aantalMislukt */
/** @var float $confidenceDrempel */
/** @var array $recenteEmails */
/** @var array $recenteFouten */
/** @var array<string,array> $pipeline */
/** @var array|null $laatsteConcept */
/** @var array|null $laatsteConceptBron */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';

$stages = [
    'ontvangen' => ['titel' => 'Ontvangen', 'sub' => 'Uit e-mailqueue', 'icon' => 'bi-inbox', 'tone' => 'open'],
    'geanalyseerd' => ['titel' => 'AI-analyse', 'sub' => 'Classificatie & samenvatting', 'icon' => 'bi-cpu', 'tone' => 'behandeling'],
    'concept' => ['titel' => 'Conceptartikel', 'sub' => 'Wacht op review', 'icon' => 'bi-file-earmark-text', 'tone' => 'wachtend'],
    'gepubliceerd' => ['titel' => 'Gepubliceerd', 'sub' => 'Live in kennisbank', 'icon' => 'bi-check-circle', 'tone' => 'opgelost'],
];
?>
<div class="page-header">
  <div class="page-title">E-mail &amp; kennisbank verwerking</div>
  <div style="display:flex;gap:8px">
    <a class="btn" href="/email-verwerking/inbox">Inbox verwerking</a>
    <a class="btn btn-accent" href="/email-verwerking/review">Artikelen reviewen</a>
  </div>
</div>

<div class="intro-banner" style="margin-bottom:16px;border-radius:12px">
  <span class="intro-banner-icon"><i class="bi bi-stars"></i></span>
  <div>
    <div class="intro-banner-title">E-mail &rarr; AI-classificatie &rarr; Kennisbankconcept</div>
    <div class="intro-banner-desc">MailMind leest binnenkomende support-mails, herkent het onderwerp en stelt automatisch een conceptartikel voor. Jij hoeft alleen te reviewen en te publiceren.</div>
  </div>
  <div class="intro-banner-stats">
    <div class="intro-banner-stat">
      <div class="intro-banner-stat-val"><?= (int) $nieuweVandaag ?></div>
      <div class="intro-banner-stat-label">Vandaag</div>
    </div>
    <div class="intro-banner-stat">
      <div class="intro-banner-stat-val" style="color:var(--color-primary)"><?= number_format($gemiddeldeConfidence * 100, 0) ?>%</div>
      <div class="intro-banner-stat-label">Vertrouwen</div>
    </div>
    <div class="intro-banner-stat">
      <div class="intro-banner-stat-val"><?= count($pipeline['gepubliceerd']) ?></div>
      <div class="intro-banner-stat-label">Gepubliceerd</div>
    </div>
  </div>
</div>

<div class="pipeline-grid">
  <?php foreach ($stages as $key => $stage): $items = $pipeline[$key]; ?>
    <div>
      <div class="pipeline-col-head">
        <span class="pipeline-col-icon" style="background:var(--color-status-<?= $stage['tone'] ?>-bg);color:var(--color-status-<?= $stage['tone'] ?>)"><i class="bi <?= $stage['icon'] ?>"></i></span>
        <div>
          <div class="pipeline-col-title"><?= $stage['titel'] ?> <span class="pipeline-col-count"><?= count($items) ?></span></div>
          <div class="pipeline-col-sub"><?= $stage['sub'] ?></div>
        </div>
      </div>
      <div class="pipeline-col-body">
        <?php if (empty($items)): ?>
          <div class="pipeline-empty">Leeg</div>
        <?php else: ?>
          <?php foreach ($items as $it): ?>
            <?php if ($key === 'ontvangen' || $key === 'geanalyseerd'): ?>
              <a class="pipeline-card" href="/email-verwerking/<?= $it['id'] ?>" style="text-decoration:none;color:inherit;display:block">
                <div class="pipeline-card-head">
                  <span class="pipeline-card-id">E-<?= $it['id'] ?></span>
                  <?php if (!empty($it['ai_confidence'])): ?>
                    <span class="pipeline-card-cat" style="<?= $it['ai_confidence'] >= 0.95 ? 'background:var(--color-status-opgelost-bg);color:var(--color-status-opgelost)' : ($it['ai_confidence'] >= 0.85 ? 'background:var(--color-status-behandeling-bg);color:var(--color-status-behandeling)' : 'background:var(--color-status-wachtend-bg);color:var(--color-status-wachtend)') ?>"><?= number_format($it['ai_confidence'] * 100, 0) ?>%</span>
                  <?php endif; ?>
                </div>
                <div class="pipeline-card-title"><?= htmlspecialchars($it['onderwerp']) ?></div>
                <div class="pipeline-card-sub"><?= htmlspecialchars($it['afzender_naam'] ?: $it['afzender_email']) ?></div>
                <div class="pipeline-card-meta">
                  <?php if (!empty($it['ai_categorie'])): ?><span class="pipeline-card-cat"><?= htmlspecialchars($it['ai_categorie']) ?></span><?php endif; ?>
                  <span class="pipeline-card-sub" style="margin-top:0"><?= formatDatum($it['created_at']) ?></span>
                </div>
              </a>
            <?php else: ?>
              <a class="pipeline-card" href="/email-verwerking/concepten/<?= $it['id'] ?>" style="text-decoration:none;color:inherit;display:block">
                <div class="pipeline-card-head">
                  <span class="pipeline-card-id">C-<?= $it['id'] ?></span>
                  <?php if (!empty($it['confidence'])): ?>
                    <span class="pipeline-card-cat" style="<?= $it['confidence'] >= 0.95 ? 'background:var(--color-status-opgelost-bg);color:var(--color-status-opgelost)' : ($it['confidence'] >= 0.85 ? 'background:var(--color-status-behandeling-bg);color:var(--color-status-behandeling)' : 'background:var(--color-status-wachtend-bg);color:var(--color-status-wachtend)') ?>"><?= number_format($it['confidence'] * 100, 0) ?>%</span>
                  <?php endif; ?>
                </div>
                <div class="pipeline-card-title"><?= htmlspecialchars($it['titel']) ?></div>
                <div class="pipeline-card-meta">
                  <span class="pipeline-card-cat"><?= htmlspecialchars($it['categorie']) ?></span>
                  <span class="pipeline-card-sub" style="margin-top:0"><?= formatDatum($it['created_at']) ?></span>
                </div>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($laatsteConcept): ?>
<div class="card">
  <div class="card-header">
    <div>
      <span class="card-title">Laatste conceptartikel</span>
      <?php if ($laatsteConceptBron): ?>
        <div style="font-size:11.5px;color:var(--color-text-tertiary);margin-top:2px">
          Gegenereerd uit e-mail van <?= htmlspecialchars($laatsteConceptBron['afzender_naam'] ?: $laatsteConceptBron['afzender_email']) ?>
          <?php if (!empty($laatsteConcept['confidence'])): ?> &middot; vertrouwen <?= number_format($laatsteConcept['confidence'] * 100, 0) ?>%<?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
    <a class="btn" href="/email-verwerking/concepten/<?= $laatsteConcept['id'] ?>">Bekijken &rarr;</a>
  </div>
  <div class="concept-preview">
    <div>
      <div class="concept-preview-label"><i class="bi bi-envelope"></i> ORIGINEEL BERICHT</div>
      <?php if ($laatsteConceptBron): ?>
        <p style="font-size:13px;line-height:1.7;color:var(--color-text-secondary);white-space:pre-wrap;margin:0"><?= htmlspecialchars(truncateWoorden($laatsteConceptBron['body_schoon'] ?: $laatsteConceptBron['body_ruw'] ?: '', 80)) ?></p>
      <?php else: ?>
        <p style="font-size:13px;color:var(--color-text-tertiary)">Geen brone-mail gevonden.</p>
      <?php endif; ?>
    </div>
    <div>
      <div class="concept-preview-label accent"><i class="bi bi-stars"></i> AI-CONCEPTARTIKEL</div>
      <h3 style="font-size:14px;font-weight:600;margin:0 0 8px"><?= htmlspecialchars($laatsteConcept['titel']) ?></h3>
      <div style="font-size:13px;line-height:1.6;color:var(--color-text-secondary)">
        <?php if (!empty($laatsteConcept['samenvatting'])): ?><?= nl2br(htmlspecialchars(truncateWoorden($laatsteConcept['samenvatting'], 60))) ?><?php else: ?><?= nl2br(htmlspecialchars(truncateWoorden($laatsteConcept['oplossing'] ?? '', 60))) ?><?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <span class="card-title">Recente e-mails</span>
    <a href="/email-verwerking/logboek" style="font-size:12.5px">Volledig logboek &rarr;</a>
  </div>
  <?php if (empty($recenteEmails)): ?>
    <div class="empty-state">Nog geen e-mails binnengehaald.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Datum</th><th>Afzender</th><th>Onderwerp</th><th>AI-categorie</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recenteEmails as $email): ?>
          <tr onclick="window.location='/email-verwerking/<?= $email['id'] ?>'" style="cursor:pointer">
            <td><?= formatDatumTijd($email['created_at']) ?></td>
            <td><?= htmlspecialchars($email['afzender_naam'] ?: $email['afzender_email']) ?></td>
            <td><?= htmlspecialchars(truncateWoorden($email['onderwerp'], 10)) ?></td>
            <td><?= htmlspecialchars($email['ai_categorie'] ?? '—') ?></td>
            <td><?= statusBadge($email['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Foutmeldingen</span></div>
  <?php if (empty($recenteFouten)): ?>
    <div class="empty-state">Geen recente fouten.</div>
  <?php else: ?>
    <?php foreach ($recenteFouten as $fout): ?>
      <div class="log-item">
        <div class="log-meta">
          <span class="log-user"><?= htmlspecialchars($fout['onderwerp'] ?? 'Onbekende e-mail') ?></span>
          <span class="log-time"><?= formatDatumTijd($fout['created_at']) ?></span>
        </div>
        <div style="font-size:13px;color:var(--color-text-secondary)"><?= htmlspecialchars($fout['bericht']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
