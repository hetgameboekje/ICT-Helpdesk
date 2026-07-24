<?php
/**
 * @var array $user
 * Herbouwd o.b.v. src/routes/modules.account.tsx — zie bewerken.php voor de bewuste afwijkingen
 * (geen 2FA/sessies/notificaties in dit systeem).
 */
require_once APP_ROOT . '/app/Views/partials/ticket-helpers.php';
?>
<div class="page-header">
  <div class="page-title">Mijn profiel</div>
  <div style="display:flex;gap:8px">
    <a class="btn" href="/account/locaties">Mijn locaties</a>
    <a class="btn btn-accent" href="/account/bewerken">Bewerken</a>
  </div>
</div>

<div class="card" style="max-width:640px;padding:20px;display:flex;align-items:center;gap:16px;margin-bottom:16px">
  <?php if (!empty($user['foto'])): ?>
    <img src="<?= htmlspecialchars($user['foto']) ?>" alt="Profielfoto" style="width:56px;height:56px;border-radius:50%;object-fit:cover">
  <?php else: ?>
    <div class="avatar" style="width:56px;height:56px;font-size:18px">
      <?= htmlspecialchars(mb_strtoupper(mb_substr($user['naam'], 0, 1))) ?>
    </div>
  <?php endif; ?>
  <div>
    <div style="font-size:16px;font-weight:600"><?= htmlspecialchars($user['naam']) ?></div>
    <div style="color:var(--color-text-secondary);font-size:12.5px"><?= htmlspecialchars(ucfirst($user['rol'])) ?> &middot; <?= htmlspecialchars($user['email']) ?></div>
  </div>
</div>

<div class="card" style="max-width:640px">
  <div class="card-header"><span class="card-title">Profiel</span></div>
  <div style="padding:0 16px">
    <div class="meta-row"><span class="meta-key">Rol</span><span><?= htmlspecialchars(ucfirst($user['rol'])) ?></span></div>
    <div class="meta-row"><span class="meta-key">Telefoonnummer</span><span><?= htmlspecialchars($user['telefoon'] ?? '—') ?></span></div>
    <div class="meta-row"><span class="meta-key">Adres</span><span><?= htmlspecialchars($user['adres'] ?? '—') ?></span></div>
    <div class="meta-row"><span class="meta-key">Account aangemaakt</span><span><?= formatDatum($user['created_at']) ?></span></div>
  </div>
</div>
