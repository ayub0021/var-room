/* ============================================================
   VAR ROOM — Global JavaScript
   main.js
   ============================================================ */

'use strict';

/* ── DOM ready helper ────────────────────────────────────────── */
const ready = (fn) => {
  if (document.readyState !== 'loading') fn();
  else document.addEventListener('DOMContentLoaded', fn);
};

/* ── Utility: select elements ────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

/* ── Utility: show/hide spinner on a button ──────────────────── */
function btnLoading(btn, isLoading) {
  if (isLoading) {
    btn.dataset.origText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px;"></span>';
    btn.disabled = true;
  } else {
    btn.innerHTML = btn.dataset.origText || btn.innerHTML;
    btn.disabled = false;
  }
}

/* ── Flash: auto-dismiss after 5 s ───────────────────────────── */
function initFlash() {
  $$('.flash').forEach(el => {
    setTimeout(() => el.remove(), 5000);
  });
}

/* ── Mobile Nav ──────────────────────────────────────────────── */
function initMobileNav() {
  const burger  = $('#nav-hamburger');
  const mobileNav = $('#nav-mobile');
  if (!burger || !mobileNav) return;

  burger.addEventListener('click', () => {
    const open = mobileNav.classList.toggle('open');
    burger.classList.toggle('open', open);
    burger.setAttribute('aria-expanded', open);
  });

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.nav') && mobileNav.classList.contains('open')) {
      mobileNav.classList.remove('open');
      burger.classList.remove('open');
    }
  });
}

/* ── Filter Chips ────────────────────────────────────────────── */
function initFilterChips() {
  $$('.filter-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const group = chip.closest('.filter-bar');
      $$('.filter-chip', group).forEach(c => c.classList.remove('active'));
      chip.classList.add('active');

      const filter = chip.dataset.filter;
      triggerFilter(filter);
    });
  });
}

function triggerFilter(value) {
  const url = new URL(window.location.href);
  if (value && value !== 'all') {
    url.searchParams.set('competition', value);
  } else {
    url.searchParams.delete('competition');
  }
  url.searchParams.delete('page'); // reset pagination
  window.location.href = url.toString();
}

/* ── Image Upload Preview ────────────────────────────────────── */
function initUploadPreview() {
  const zone    = $('.upload-zone');
  const input   = $('input[type="file"]', zone || document);
  const preview = $('.upload-preview');

  if (!input || !preview) return;

  function showPreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = $('img', preview) || (() => {
        const i = document.createElement('img'); preview.appendChild(i); return i;
      })();
      img.src = e.target.result;
      preview.classList.add('visible');
    };
    reader.readAsDataURL(file);
  }

  input.addEventListener('change', () => showPreview(input.files[0]));

  // Drag & drop
  if (zone) {
    zone.addEventListener('dragover', e => {
      e.preventDefault(); zone.classList.add('dragover');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('dragover');
      const file = e.dataTransfer.files[0];
      if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        showPreview(file);
      }
    });
  }

  // Remove preview
  const removeBtn = $('.upload-preview__remove');
  if (removeBtn) {
    removeBtn.addEventListener('click', () => {
      input.value = '';
      preview.classList.remove('visible');
    });
  }
}

/* ── Vote System (AJAX) ──────────────────────────────────────── */
function initVoting() {
  $$('.vote-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const reviewId = btn.dataset.reviewId;
      const voteType = btn.dataset.voteType;
      if (!reviewId || !voteType) return;

      const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

      btnLoading(btn, true);

      try {
        const res = await fetch('/var-room/ajax/vote.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ review_id: reviewId, vote_type: voteType, csrf_token: csrf }),
        });

        const data = await res.json();
        if (data.success) {
          updateVoteUI(reviewId, data);
        } else {
          showToast(data.error || 'Vote failed', 'error');
        }
      } catch {
        showToast('Network error. Try again.', 'error');
      } finally {
        btnLoading(btn, false);
      }
    });
  });
}

function updateVoteUI(reviewId, data) {
  // Update percentages & bars
  const correctPct = data.correct_pct;
  const wrongPct   = data.wrong_pct;
  const total      = data.total;

  $$(`[data-review-id="${reviewId}"]`).forEach(btn => {
    btn.classList.toggle('voted', btn.dataset.voteType === data.user_vote);
  });

  // Update vote bar
  const bar = $(`[data-vote-bar="${reviewId}"]`);
  if (bar) {
    const fill = $('.vote-bar__fill', bar);
    const cLabel = $('.vote-bar__label--correct .vote-bar__pct', bar);
    const wLabel = $('.vote-bar__label--wrong .vote-bar__pct', bar);
    if (fill)   fill.style.width = correctPct + '%';
    if (cLabel) cLabel.textContent = correctPct + '%';
    if (wLabel) wLabel.textContent = wrongPct + '%';
  }

  // Update total vote count
  const totalEl = $(`[data-vote-total="${reviewId}"]`);
  if (totalEl) totalEl.textContent = total.toLocaleString();

  showToast('Vote recorded!', 'success');
}

/* ── Comment System (AJAX) ───────────────────────────────────── */
function initComments() {
  const form = $('#comment-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const textarea  = $('textarea', form);
    const submitBtn = $('button[type="submit"]', form);
    const body = textarea.value.trim();

    if (!body) { showToast('Comment cannot be empty', 'error'); return; }
    if (body.length > 1000) { showToast('Comment too long (max 1000 chars)', 'error'); return; }

    const reviewId = form.dataset.reviewId;
    const csrf     = document.querySelector('meta[name="csrf-token"]')?.content;

    btnLoading(submitBtn, true);

    try {
      const res = await fetch('/var-room/ajax/comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ review_id: reviewId, body, csrf_token: csrf }),
      });

      const data = await res.json();
      if (data.success) {
        textarea.value = '';
        prependComment(data.comment);
        updateCommentCount(reviewId, 1);
        showToast('Comment posted!', 'success');
      } else {
        showToast(data.error || 'Could not post comment', 'error');
      }
    } catch {
      showToast('Network error. Try again.', 'error');
    } finally {
      btnLoading(submitBtn, false);
    }
  });
}

function prependComment(comment) {
  const list = $('#comment-list');
  if (!list) return;

  const emptyState = $('.empty-state', list);
  if (emptyState) emptyState.remove();

  const div = document.createElement('div');
  div.className = 'comment fade-in';
  div.innerHTML = `
    <div class="comment__avatar">
      <img src="/var-room/uploads/avatars/${escapeHtml(comment.avatar)}"
           alt="${escapeHtml(comment.username)}"
           onerror="this.src='/var-room/assets/images/default_avatar.png'">
    </div>
    <div class="comment__body">
      <div class="comment__header">
        <span class="comment__name">${escapeHtml(comment.username)}</span>
        <span class="comment__time">just now</span>
      </div>
      <p class="comment__text">${escapeHtml(comment.body)}</p>
    </div>`;
  list.prepend(div);
}

function updateCommentCount(reviewId, delta) {
  const el = $(`[data-comment-count="${reviewId}"]`);
  if (el) el.textContent = parseInt(el.textContent || '0') + delta;
}

/* ── Toast Notification ──────────────────────────────────────── */
function showToast(message, type = 'info', duration = 3500) {
  let container = $('#toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    Object.assign(container.style, {
      position: 'fixed', bottom: '24px', right: '24px',
      zIndex: '9999', display: 'flex', flexDirection: 'column',
      gap: '8px', alignItems: 'flex-end',
    });
    document.body.appendChild(container);
  }

  const colors = {
    success: 'var(--neon-green)',
    error:   'var(--neon-red)',
    info:    'var(--neon-blue)',
  };

  const toast = document.createElement('div');
  Object.assign(toast.style, {
    padding: '12px 18px',
    borderRadius: '8px',
    background: 'var(--bg-card)',
    border: `1px solid ${colors[type] || colors.info}`,
    borderLeft: `3px solid ${colors[type] || colors.info}`,
    color: colors[type] || colors.info,
    fontFamily: 'var(--font-condensed)',
    fontSize: '0.9rem',
    fontWeight: '600',
    letterSpacing: '0.03em',
    boxShadow: 'var(--shadow-elevated)',
    maxWidth: '320px',
    animation: 'fadeIn 0.25s ease-out both',
    cursor: 'pointer',
  });
  toast.textContent = message;
  toast.addEventListener('click', () => toast.remove());

  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = 'opacity 0.3s, transform 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

/* ── Escape HTML ─────────────────────────────────────────────── */
function escapeHtml(str) {
  const map = { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'": '&#39;' };
  return String(str).replace(/[&<>"']/g, c => map[c]);
}

/* ── Animate vote bars on page load ──────────────────────────── */
function animateVoteBars() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const fill = entry.target;
        const target = fill.dataset.width || '0';
        setTimeout(() => { fill.style.width = target + '%'; }, 100);
        observer.unobserve(fill);
      }
    });
  }, { threshold: 0.3 });

  $$('.vote-bar__fill[data-width]').forEach(fill => {
    fill.style.width = '0%';
    observer.observe(fill);
  });
}

/* ── Stagger-fade card grid ──────────────────────────────────── */
function animateCards() {
  const cards = $$('.controversy-card, .card');
  cards.forEach((card, i) => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(16px)';
    card.style.transition = `opacity 0.4s ease, transform 0.4s ease`;
    card.style.transitionDelay = `${i * 0.06}s`;
    setTimeout(() => {
      card.style.opacity = '1';
      card.style.transform = 'translateY(0)';
    }, 50);
  });
}

/* ── Ticker: duplicate items for seamless loop ───────────────── */
function initTicker() {
  const track = $('.ticker__items');
  if (!track) return;
  // Clone items for infinite loop
  const clone = track.cloneNode(true);
  track.parentElement.appendChild(clone);
}

/* ── Confirm dialogs ─────────────────────────────────────────── */
function initConfirm() {
  $$('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      const msg = el.dataset.confirm || 'Are you sure?';
      if (!confirm(msg)) e.preventDefault();
    });
  });
}

/* ── Character counter for textareas ─────────────────────────── */
function initCharCounters() {
  $$('textarea[maxlength]').forEach(ta => {
    const max = parseInt(ta.getAttribute('maxlength'));
    const counter = document.createElement('div');
    counter.style.cssText =
      'font-size:0.75rem;color:var(--text-muted);text-align:right;margin-top:4px;font-family:var(--font-mono);';
    counter.textContent = `0 / ${max}`;
    ta.insertAdjacentElement('afterend', counter);

    ta.addEventListener('input', () => {
      const len = ta.value.length;
      counter.textContent = `${len} / ${max}`;
      counter.style.color = len > max * 0.9
        ? 'var(--neon-red)' : 'var(--text-muted)';
    });
  });
}

/* ── Entry point ─────────────────────────────────────────────── */
ready(() => {
  initFlash();
  initMobileNav();
  initFilterChips();
  initUploadPreview();
  initVoting();
  initComments();
  animateVoteBars();
  animateCards();
  initTicker();
  initConfirm();
  initCharCounters();
});
