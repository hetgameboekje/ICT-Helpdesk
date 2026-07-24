<?php
/** @var array $emails */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
require_once APP_ROOT . '/app/Views/partials/beheer-tabs.php';

$statusLabels = [
    'pending' => 'In wachtrij',
    'sent' => 'Verzonden',
    'error' => 'Fout',
    'test' => 'Test',
];

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<?= beheerTabs('emails') ?>
<div class="page-header">
  <div class="page-title">E-mails</div>
  <div>
    <form method="post" action="/beheer/emails/test" style="display:inline">
      <button type="submit" class="btn">Stuur testmail naar mezelf</button>
    </form>
    <a class="btn" href="/beheer">&larr; Beheer</a>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="card">
  <?php if (empty($emails)): ?>
    <div class="empty-state">Nog geen e-mails in de wachtrij geweest.</div>
  <?php else: ?>
    <table class="table">
      <thead>
        <tr>
          <th>Aangemaakt</th>
          <th>Verzonden</th>
          <th>Ontvanger</th>
          <th>Onderwerp</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($emails as $mail): ?>
        <tr>
          <td><?= formatDatumTijd($mail['created_at']) ?></td>
          <td><?= $mail['sent_at'] ? formatDatumTijd($mail['sent_at']) : '—' ?></td>
          <td><?= htmlspecialchars($mail['ontvanger']) ?></td>
          <td>
            <?= htmlspecialchars($mail['onderwerp']) ?>
            <?php if (($mail['status'] ?? null) === 'error' && !empty($mail['foutmelding'])): ?>
              <div style="font-size:11px;color:#b3261e"><?= htmlspecialchars($mail['foutmelding']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="badge badge-<?= htmlspecialchars($mail['status'] ?? '') ?>"><?= htmlspecialchars($statusLabels[$mail['status'] ?? ''] ?? ($mail['status'] ?? '')) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
