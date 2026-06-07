<?php
// ============================================================
//  VAR ROOM — Shared Footer
//  includes/footer.php
// ============================================================
$footer_stats = $_SESSION['site_stats'] ?? ['total_reviews'=>0,'total_votes'=>0,'total_users'=>0];
?>

<!-- ── Footer ───────────────────────────────────────────────── -->
<footer class="footer" role="contentinfo">
  <div class="footer__inner">

    <!-- Brand col -->
    <div class="footer__brand">
      <a href="<?= SITE_URL ?>/index.php" class="nav__logo" style="display:inline-flex; margin-bottom:12px;">
        <div class="nav__logo-icon">VR</div>
        <span class="nav__logo-text">VAR <span>Room</span></span>
      </a>
      <p class="footer__tagline">
        The community court for football's most debated referee and VAR decisions.
        Your verdict matters.
      </p>

      <!-- Live stats -->
      <div style="display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;">
        <div>
          <div style="font-family:var(--font-display);font-size:1.4rem;color:var(--text-primary);">
            <?= number_format((int)($footer_stats['total_reviews'] ?? 0)) ?>
          </div>
          <div style="font-family:var(--font-condensed);font-size:0.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            Controversies
          </div>
        </div>
        <div style="width:1px;background:var(--border-default);"></div>
        <div>
          <div style="font-family:var(--font-display);font-size:1.4rem;color:var(--text-primary);">
            <?= number_format((int)($footer_stats['total_votes'] ?? 0)) ?>
          </div>
          <div style="font-family:var(--font-condensed);font-size:0.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            Votes Cast
          </div>
        </div>
        <div style="width:1px;background:var(--border-default);"></div>
        <div>
          <div style="font-family:var(--font-display);font-size:1.4rem;color:var(--text-primary);">
            <?= number_format((int)($footer_stats['total_users'] ?? 0)) ?>
          </div>
          <div style="font-family:var(--font-condensed);font-size:0.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);">
            Members
          </div>
        </div>
      </div>
    </div>

    <!-- Navigate col -->
    <div>
      <p class="footer__heading">Navigate</p>
      <ul class="footer__links">
        <li><a href="<?= SITE_URL ?>/index.php"                class="footer__link">Home</a></li>
        <li><a href="<?= SITE_URL ?>/index.php?sort=trending"  class="footer__link">Trending</a></li>
        <li><a href="<?= SITE_URL ?>/index.php?sort=latest"    class="footer__link">Latest</a></li>
        <?php if (is_logged_in()): ?>
        <li><a href="<?= SITE_URL ?>/upload.php"               class="footer__link">Submit Controversy</a></li>
        <?php else: ?>
        <li><a href="<?= SITE_URL ?>/auth/register.php"        class="footer__link">Join Free</a></li>
        <?php endif; ?>
      </ul>
    </div>

    <!-- Competitions col -->
    <div>
      <p class="footer__heading">Competitions</p>
      <ul class="footer__links">
        <?php foreach (array_slice(COMPETITIONS, 0, 7) as $comp): ?>
        <li>
          <a href="<?= SITE_URL ?>/index.php?competition=<?= urlencode($comp) ?>"
             class="footer__link"><?= e($comp) ?></a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

  </div>

  <!-- Bottom bar -->
  <div class="footer__bottom">
    <span>© <?= date('Y') ?> VAR Room. For discussion purposes only.</span>
    <span style="display:flex;align-items:center;gap:6px;">
      <span style="width:6px;height:6px;border-radius:50%;background:var(--neon-green);"></span>
      All systems live
    </span>
  </div>
</footer>

<!-- ── Global Scripts ────────────────────────────────────────── -->
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
