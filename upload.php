<?php
// ============================================================
//  VAR ROOM — Submit Controversy (Upload Form)
//  upload.php
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

require_login(SITE_URL . '/auth/login.php?redirect=' . urlencode(SITE_URL . '/upload.php'));

$errors = [];
$values = [
    'title'        => '',
    'match_name'   => '',
    'competition'  => '',
    'incident_min' => '',
    'description'  => '',
];

// ── Handle submission ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Collect & sanitize text fields
    $values['title']        = trim($_POST['title']        ?? '');
    $values['match_name']   = trim($_POST['match_name']   ?? '');
    $values['competition']  = trim($_POST['competition']  ?? '');
    $values['incident_min'] = trim($_POST['incident_min'] ?? '');
    $values['description']  = trim($_POST['description']  ?? '');

    // ── Validate fields ────────────────────────────────────
    if (empty($values['title']) || mb_strlen($values['title']) < 5) {
        $errors['title'] = 'Title must be at least 5 characters.';
    } elseif (mb_strlen($values['title']) > 200) {
        $errors['title'] = 'Title is too long (max 200 chars).';
    }

    if (empty($values['match_name'])) {
        $errors['match_name'] = 'Match name is required.';
    } elseif (mb_strlen($values['match_name']) > 200) {
        $errors['match_name'] = 'Match name too long (max 200 chars).';
    }

    if (empty($values['competition']) || !in_array($values['competition'], COMPETITIONS, true)) {
        $errors['competition'] = 'Please select a valid competition.';
    }

    $min = (int) $values['incident_min'];
    if ($values['incident_min'] === '' || $min < 1 || $min > MAX_INCIDENT_MIN) {
        $errors['incident_min'] = 'Enter a minute between 1 and ' . MAX_INCIDENT_MIN . '.';
    }

    if (empty($values['description']) || mb_strlen($values['description']) < 20) {
        $errors['description'] = 'Description must be at least 20 characters.';
    } elseif (mb_strlen($values['description']) > 2000) {
        $errors['description'] = 'Description too long (max 2000 chars).';
    }

    // ── Validate image ─────────────────────────────────────
    if (empty($_FILES['image']['name']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['image'] = 'Please upload an image of the incident.';
    } else {
        $upload = handle_image_upload($_FILES['image']);
        if (!$upload['success']) {
            $errors['image'] = $upload['error'];
        }
    }

    // ── Insert if valid ────────────────────────────────────
    if (empty($errors)) {
        $review_id = db_insert(
            "INSERT INTO match_reviews
                (user_id, title, description, match_name, competition, incident_min, image_path, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
            [
                current_user_id(),
                $values['title'],
                $values['description'],
                $values['match_name'],
                $values['competition'],
                $min,
                $upload['path'],
            ]
        );

        set_flash('success', '✓ Controversy submitted! It will appear after admin approval.');
        redirect(SITE_URL . '/index.php');
    }
}

$page_title  = 'Submit Controversy';
$active_page = 'upload';
require_once __DIR__ . '/includes/header.php';
?>

<main style="padding:var(--space-xl) 0 var(--space-2xl);">
  <div class="container container--narrow">

    <!-- Page header -->
    <div style="margin-bottom:var(--space-xl);">
      <div class="section-header__eyebrow">Community Submission</div>
      <h1 style="font-size:clamp(2rem,4vw,3.2rem);margin-bottom:var(--space-sm);">Submit a Controversy</h1>
      <p>Share a disputed referee or VAR decision and let the community vote on whether it was correct.</p>
    </div>

    <!-- Guidelines banner -->
    <div style="background:var(--neon-amber-glow);border:1px solid rgba(255,184,0,.3);border-left:3px solid var(--neon-amber);border-radius:var(--radius-sm);padding:var(--space-md) var(--space-lg);margin-bottom:var(--space-xl);display:flex;gap:var(--space-md);align-items:flex-start;">
      <span style="font-size:1.2rem;flex-shrink:0;">⚠</span>
      <div>
        <div style="font-family:var(--font-condensed);font-size:.85rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--neon-amber);margin-bottom:4px;">Submission Guidelines</div>
        <p style="font-size:.875rem;color:var(--text-secondary);margin:0;">
          All submissions are reviewed before going live. Focus on the decision itself — not the players or teams involved. Include a clear image showing the incident. Duplicate or off-topic submissions will be rejected.
        </p>
      </div>
    </div>

    <!-- ── Form ─────────────────────────────────────────── -->
    <form method="POST" enctype="multipart/form-data" novalidate id="upload-form">
      <?= csrf_field() ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-lg);">

        <!-- Left column -->
        <div>

          <!-- Title -->
          <div class="form-group">
            <label class="form-label" for="title">
              Incident Title <span class="required">*</span>
            </label>
            <input
              type="text"
              id="title"
              name="title"
              class="form-control <?= isset($errors['title']) ? 'error' : '' ?>"
              value="<?= e($values['title']) ?>"
              placeholder="e.g. Penalty Not Given for Clear Handball"
              maxlength="200"
              required
            >
            <?php if (isset($errors['title'])): ?>
              <div class="form-error">⚠ <?= e($errors['title']) ?></div>
            <?php else: ?>
              <div class="form-help">Be specific and descriptive. Max 200 characters.</div>
            <?php endif; ?>
          </div>

          <!-- Match name -->
          <div class="form-group">
            <label class="form-label" for="match_name">
              Match <span class="required">*</span>
            </label>
            <input
              type="text"
              id="match_name"
              name="match_name"
              class="form-control <?= isset($errors['match_name']) ? 'error' : '' ?>"
              value="<?= e($values['match_name']) ?>"
              placeholder="e.g. Arsenal vs Liverpool"
              maxlength="200"
              required
            >
            <?php if (isset($errors['match_name'])): ?>
              <div class="form-error">⚠ <?= e($errors['match_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Competition -->
          <div class="form-group">
            <label class="form-label" for="competition">
              Competition <span class="required">*</span>
            </label>
            <select
              id="competition"
              name="competition"
              class="form-control <?= isset($errors['competition']) ? 'error' : '' ?>"
              required
            >
              <option value="">— Select Competition —</option>
              <?php foreach (COMPETITIONS as $comp): ?>
                <option value="<?= e($comp) ?>" <?= $values['competition'] === $comp ? 'selected' : '' ?>>
                  <?= e($comp) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['competition'])): ?>
              <div class="form-error">⚠ <?= e($errors['competition']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Minute of incident -->
          <div class="form-group">
            <label class="form-label" for="incident_min">
              Minute of Incident <span class="required">*</span>
            </label>
            <div style="display:flex;align-items:center;gap:var(--space-sm);">
              <input
                type="number"
                id="incident_min"
                name="incident_min"
                class="form-control <?= isset($errors['incident_min']) ? 'error' : '' ?>"
                value="<?= e($values['incident_min']) ?>"
                placeholder="45"
                min="1"
                max="<?= MAX_INCIDENT_MIN ?>"
                style="max-width:120px;"
                required
              >
              <span style="font-family:var(--font-display);font-size:1.4rem;color:var(--text-muted);" id="min-display">
                <?= $values['incident_min'] ? $values['incident_min'] . "'" : "—'" ?>
              </span>
            </div>
            <?php if (isset($errors['incident_min'])): ?>
              <div class="form-error">⚠ <?= e($errors['incident_min']) ?></div>
            <?php else: ?>
              <div class="form-help">Regular time: 1–90. Extra time: up to <?= MAX_INCIDENT_MIN ?>.</div>
            <?php endif; ?>
          </div>

        </div>

        <!-- Right column -->
        <div>

          <!-- Image upload -->
          <div class="form-group">
            <label class="form-label">
              Incident Image <span class="required">*</span>
            </label>

            <div class="upload-zone" id="upload-zone">
              <input type="file" name="image" id="image-input" accept="image/*" required>
              <span class="upload-zone__icon">📸</span>
              <div class="upload-zone__text">Drop image here or click to browse</div>
              <div class="upload-zone__hint">JPG, PNG, WEBP, GIF — max <?= UPLOAD_MAX_MB ?>MB</div>
            </div>

            <div class="upload-preview" id="upload-preview">
              <img id="preview-img" src="" alt="Preview">
              <button type="button" class="upload-preview__remove" id="remove-preview" aria-label="Remove image">✕</button>
            </div>

            <?php if (isset($errors['image'])): ?>
              <div class="form-error">⚠ <?= e($errors['image']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Description -->
          <div class="form-group">
            <label class="form-label" for="description">
              Description <span class="required">*</span>
            </label>
            <textarea
              id="description"
              name="description"
              class="form-control <?= isset($errors['description']) ? 'error' : '' ?>"
              placeholder="Describe what happened, what the referee/VAR decided, and why it's controversial. Include the context of the match situation..."
              maxlength="2000"
              style="min-height:180px;"
              required
            ><?= e($values['description']) ?></textarea>
            <?php if (isset($errors['description'])): ?>
              <div class="form-error">⚠ <?= e($errors['description']) ?></div>
            <?php else: ?>
              <div class="form-help">Min 20 characters. Be factual and objective.</div>
            <?php endif; ?>
          </div>

        </div>
      </div>

      <!-- Preview card (live) -->
      <div id="live-preview-section" style="margin-top:var(--space-xl);display:none;">
        <div class="section-header__eyebrow" style="margin-bottom:var(--space-md);">Card Preview</div>
        <div style="max-width:380px;">
          <div class="controversy-card" style="pointer-events:none;">
            <div class="controversy-card__image" id="preview-card-img-wrap">
              <img id="preview-card-img" src="" alt="Preview" style="width:100%;height:100%;object-fit:cover;">
              <div class="controversy-card__gradient"></div>
              <span class="controversy-card__badge controversy-card__badge--competition" id="preview-comp">Competition</span>
              <span class="controversy-card__minute" id="preview-min">—'</span>
            </div>
            <div class="controversy-card__body">
              <div class="controversy-card__match" id="preview-match">Match Name</div>
              <h3 class="controversy-card__title" id="preview-title">Incident Title</h3>
              <p class="controversy-card__desc" id="preview-desc">Your description will appear here...</p>
              <div class="vote-bar" style="margin-top:var(--space-md);">
                <div class="vote-bar__track">
                  <div class="vote-bar__fill" style="width:50%;"></div>
                </div>
                <div class="vote-bar__labels">
                  <span class="vote-bar__label vote-bar__label--correct">✓ Correct <span class="vote-bar__pct">—%</span></span>
                  <span class="vote-bar__label vote-bar__label--wrong"><span class="vote-bar__pct">—%</span> Wrong ✗</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div style="margin-top:var(--space-xl);padding-top:var(--space-lg);border-top:1px solid var(--border-subtle);display:flex;align-items:center;justify-content:space-between;gap:var(--space-md);flex-wrap:wrap;">
        <div>
          <p style="font-size:.875rem;color:var(--text-muted);margin:0;">
            ⏳ Your submission will be reviewed by a moderator before going live.
          </p>
        </div>
        <div style="display:flex;gap:var(--space-sm);">
          <a href="<?= SITE_URL ?>/index.php" class="btn btn--ghost">Cancel</a>
          <button type="submit" class="btn btn--primary" style="padding:12px 32px;font-size:1rem;" id="submit-btn">
            Submit for Review
          </button>
        </div>
      </div>

    </form>
  </div>
</main>

<script>
/* Live card preview — updates as user types */
const fields = {
  title:       { input: 'title',        output: 'preview-title',  fallback: 'Incident Title' },
  match:       { input: 'match_name',   output: 'preview-match',  fallback: 'Match Name' },
  competition: { input: 'competition',  output: 'preview-comp',   fallback: 'Competition' },
  description: { input: 'description',  output: 'preview-desc',   fallback: 'Your description...' },
};

function updatePreview() {
  let hasContent = false;

  Object.values(fields).forEach(({ input, output, fallback }) => {
    const val = document.getElementById(input)?.value?.trim();
    const el  = document.getElementById(output);
    if (el) {
      el.textContent = val || fallback;
      if (val) hasContent = true;
    }
  });

  // Minute display
  const minVal = document.getElementById('incident_min')?.value;
  document.getElementById('preview-min').textContent = minVal ? minVal + "'" : "—'";
  document.getElementById('min-display').textContent = minVal ? minVal + "'" : "—'";

  // Show preview section if any field has content
  document.getElementById('live-preview-section').style.display = hasContent ? 'block' : 'none';
}

/* Attach live preview to all fields */
['title','match_name','competition','incident_min','description'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', updatePreview);
  document.getElementById(id)?.addEventListener('change', updatePreview);
});

/* Image preview → also update card preview */
document.getElementById('image-input').addEventListener('change', function() {
  const file = this.files[0];
  if (!file || !file.type.startsWith('image/')) return;

  const reader = new FileReader();
  reader.onload = (e) => {
    document.getElementById('preview-img').src   = e.target.result;
    document.getElementById('upload-preview').classList.add('visible');

    const cardImg = document.getElementById('preview-card-img');
    cardImg.src = e.target.result;
    document.getElementById('live-preview-section').style.display = 'block';
  };
  reader.readAsDataURL(file);
});

document.getElementById('remove-preview').addEventListener('click', function() {
  document.getElementById('image-input').value = '';
  document.getElementById('upload-preview').classList.remove('visible');
  document.getElementById('preview-card-img').src = '';
});

/* Submit guard — prevent double submission */
document.getElementById('upload-form').addEventListener('submit', function(e) {
  const btn = document.getElementById('submit-btn');
  // Basic client-side required check
  const required = this.querySelectorAll('[required]');
  let valid = true;
  required.forEach(el => { if (!el.value.trim()) valid = false; });
  if (!valid) return; // let browser handle HTML5 validation

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px;vertical-align:middle;margin-right:6px;"></span>Submitting…';
});

/* Auto-fill competition if user typed one that matches */
document.getElementById('competition').addEventListener('change', updatePreview);

/* Trigger initial preview */
updatePreview();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
