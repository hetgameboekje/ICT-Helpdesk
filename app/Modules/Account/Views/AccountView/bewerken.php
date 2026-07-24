<?php
/**
 * @var array $user
 * Herbouwd o.b.v. src/routes/modules.account.tsx (Lovable MCP) — de kaartsecties "Profiel" en
 * "Beveiliging" (wachtwoord) bestaan hier echt en zijn overgenomen als één formulier (zoals het
 * al was: AccountController::bijwerken() verwerkt beide in dezelfde POST). Lovable's secties
 * "Tweestapsverificatie", "Actieve sessies" en "Notificaties" zijn weggelaten: er is geen
 * MFA/sessiebeheer/notificatievoorkeuren-backend in dit systeem (sessie-cookie-auth zonder 2FA,
 * geen aparte notificatie-instellingen-tabel) — zie CLAUDE.md > "Nog te herdesignen".
 */
?>
<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px">
    <a class="btn" href="/account" style="padding:6px 10px">&larr;</a>
    <div class="page-title">Profiel bewerken</div>
  </div>
</div>

<form method="post" action="/account" enctype="multipart/form-data" style="max-width:640px">
  <div class="card" style="padding:20px;margin-bottom:16px;display:flex;align-items:center;gap:16px">
    <?php if (!empty($user['foto'])): ?>
      <img src="<?= htmlspecialchars($user['foto']) ?>" alt="Profielfoto" style="width:56px;height:56px;border-radius:50%;object-fit:cover">
    <?php else: ?>
      <div class="avatar" style="width:56px;height:56px;font-size:18px">
        <?= htmlspecialchars(mb_strtoupper(mb_substr($user['naam'], 0, 1))) ?>
      </div>
    <?php endif; ?>
    <div style="flex:1">
      <label class="form-label">Profielfoto</label>
      <input type="file" name="foto" accept=".jpg,.jpeg,.png,.gif,.webp">
    </div>
  </div>

  <div class="card" style="padding:20px;margin-bottom:16px">
    <div class="card-title" style="margin-bottom:14px;padding-bottom:10px;border-bottom:0.5px solid var(--color-border-tertiary)">
      <i class="bi bi-person-circle" style="margin-right:6px;color:var(--color-text-tertiary)"></i>Profiel
    </div>
    <div class="form-grid">
      <div class="form-group">
        <label class="form-label">Naam</label>
        <input type="text" name="naam" required value="<?= htmlspecialchars($user['naam']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">E-mailadres</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Telefoonnummer</label>
        <input type="text" name="telefoon" value="<?= htmlspecialchars($user['telefoon'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Adres</label>
        <input type="text" name="adres" value="<?= htmlspecialchars($user['adres'] ?? '') ?>">
      </div>
    </div>
  </div>

  <div class="card" style="padding:20px;margin-bottom:16px">
    <div class="card-title" style="margin-bottom:14px;padding-bottom:10px;border-bottom:0.5px solid var(--color-border-tertiary)">
      <i class="bi bi-shield-check" style="margin-right:6px;color:var(--color-text-tertiary)"></i>Beveiliging
    </div>
    <div class="form-group">
      <label class="form-label">Nieuw wachtwoord</label>
      <input type="password" name="wachtwoord" placeholder="Laat leeg om huidige wachtwoord te behouden" autocomplete="new-password">
    </div>
  </div>

  <div style="display:flex;gap:8px">
    <button class="btn btn-accent" type="submit">Opslaan</button>
    <a class="btn" href="/account">Annuleren</a>
  </div>
</form>
