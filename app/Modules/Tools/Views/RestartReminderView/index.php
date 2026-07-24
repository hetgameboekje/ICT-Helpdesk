<?php
/** @var array $apparaten */
/** @var array $instellingen */
require_once APP_ROOT . '/app/Views/partials/tools-tabs.php';

$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<?= toolsTabs('herstart') ?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/tools" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Herstart-herinneringen</div>
  </div>
</div>

<?php if ($flashSuccess): ?>
  <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
  <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:14px">
  <div class="card-header"><span class="card-title">Apparaten die herstart nodig hebben (<?= count($apparaten) ?>)</span></div>
  <?php if (empty($apparaten)): ?>
    <div class="empty-state">Geen apparaten die momenteel herstart nodig hebben.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Medewerker</th>
            <th>E-mail</th>
            <th>Apparaat</th>
            <th>Dagen sinds boot</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($apparaten as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['medewerker_naam'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['email'] ?? '—') ?></td>
            <td><?= htmlspecialchars($a['naam']) ?></td>
            <td><?= (int) $a['dagen_sinds_boot'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <div style="padding:12px 16px;display:flex;gap:8px">
    <a class="btn" href="/tools/herstart-herinneringen/export">Exporteer CSV</a>
    <form method="post" action="/tools/herstart-herinneringen/versturen" onsubmit="return confirm('Herinneringsmail versturen naar ' + <?= count($apparaten) ?> + ' medewerker(s)?')">
      <button class="btn btn-primary" type="submit" <?= empty($apparaten) ? 'disabled' : '' ?>>Verstuur herinneringen</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header"><span class="card-title">Mailtemplate</span></div>
  <form method="post" action="/tools/herstart-herinneringen/instellingen" style="padding:16px">
    <div class="form-group">
      <label class="form-label">Onderwerp</label>
      <input type="text" name="onderwerp" value="<?= htmlspecialchars($instellingen['onderwerp']) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label">Inhoud (HTML)</label>
      <textarea name="inhoud" rows="6" required><?= htmlspecialchars($instellingen['inhoud']) ?></textarea>
      <div style="font-size:12px;color:var(--color-text-secondary);margin-top:4px">
        Beschikbare placeholders: <code>{naam}</code>, <code>{apparaat}</code>, <code>{dagen}</code>
      </div>
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Cc</label>
        <input type="text" name="cc" value="<?= htmlspecialchars($instellingen['cc'] ?? '') ?>" placeholder="komma-gescheiden e-mailadressen">
      </div>
      <div class="form-group">
        <label class="form-label">Bcc</label>
        <input type="text" name="bcc" value="<?= htmlspecialchars($instellingen['bcc'] ?? '') ?>" placeholder="komma-gescheiden e-mailadressen">
      </div>
    </div>
    <button class="btn btn-primary" type="submit">Instellingen opslaan</button>
  </form>
</div>
