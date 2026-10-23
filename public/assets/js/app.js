/**
 * Integrity Autocars — small dependency-free progressive enhancements.
 * Everything must still work with JS disabled (plain forms + full page loads).
 */
(function () {
  'use strict';

  // ------------------------------------------------------------- theme toggle
  // Light/dark switch: toggles the `dark` class on <html> and persists the
  // choice ("light"|"dark") to localStorage. Initial class is applied before
  // paint by theme_boot_script() (see helpers.php), so there is no flash.
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var dark = document.documentElement.classList.toggle('dark');
      try {
        localStorage.setItem('iac_theme', dark ? 'dark' : 'light');
      } catch (e) { /* private mode etc. — theme just won't persist */ }
    });
  });

  // ------------------------------------------------------------- language menu
  // The <details class="lang-menu"> dropdown works without JS; this only adds
  // closing on outside click or Escape (progressive enhancement).
  document.querySelectorAll('details.lang-menu').forEach(function (menu) {
    document.addEventListener('click', function (ev) {
      if (menu.open && !menu.contains(ev.target)) {
        menu.open = false;
      }
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && menu.open) {
        menu.open = false;
      }
    });
  });

  // ---------------------------------------------------------------- mobile nav
  var toggle = document.getElementById('nav-toggle');
  var nav = document.getElementById('mobile-nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('hidden') === false;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.innerHTML = open
        ? '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>'
        : '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
    });
  }

  // -------------------------------------------------- vehicle gallery thumbnails
  document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
    var main = gallery.querySelector('[data-gallery-main]');
    gallery.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        if (!main) return;
        main.src = thumb.getAttribute('data-gallery-thumb');
        main.alt = thumb.getAttribute('data-gallery-alt') || main.alt;
        gallery.querySelectorAll('[data-gallery-thumb]').forEach(function (t) {
          t.classList.remove('border-brand');
          t.classList.add('border-transparent');
        });
        thumb.classList.remove('border-transparent');
        thumb.classList.add('border-brand');
      });
    });
  });

  // ----------------------------------------------------- password show/hide
  document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-toggle-password'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      var eye = btn.querySelector('[data-icon-eye]');
      var eyeOff = btn.querySelector('[data-icon-eye-off]');
      if (eye) eye.classList.toggle('hidden', show);
      if (eyeOff) eyeOff.classList.toggle('hidden', !show);
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  // ------------------------------------------------------ confirmation dialogs
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // ------------------------------------------- auto-submit filter selects
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
      if (el.form) el.form.submit();
    });
  });

  // --------------------------------------------------------------- analytics
  // Same semantics as the previous SPA: every page load = 1 visit; a hashed
  // visitor id (never the raw UUID) counts once per day as a unique.
  try {
    var vid = localStorage.getItem('iac_visitor_id');
    if (!vid) {
      vid = crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
      localStorage.setItem('iac_visitor_id', vid);
    }
    var body = new URLSearchParams({ visitorId: vid });
    fetch('/api/analytics', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      keepalive: true,
    }).catch(function () {});
  } catch (e) {
    /* private mode — ignore */
  }

  // --------------------------------------------------- copy-to-clipboard buttons
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var target = document.querySelector(btn.getAttribute('data-copy'));
      if (!target) return;
      var text = target.value || target.textContent || '';
      var done = function () {
        var old = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = old; }, 1500);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () {});
      } else {
        target.focus();
        target.select();
        document.execCommand('copy');
        done();
      }
    });
  });
})();
