/* Nate Hall Insurance — small vanilla JS helpers. No dependencies. */
(function () {
  'use strict';

  /* ------------------------------------------------------------------
     TESTIMONIALS
     The carousel stays hidden until TESTIMONIALS_ENABLED is true AND
     there is at least one entry below.

     How to add real reviews:
       1. Only use real words from real clients, with their permission
          (a text or email saying "yes, you can use this" is enough).
       2. Copy their words as written. Light spelling fixes are fine;
          don't add claims they didn't make.
       3. Use first name + last initial and town, unless they say otherwise.
       4. Add one object per review, then set TESTIMONIALS_ENABLED = true:
          { quote: 'Their words here.', name: 'Jane D.', place: 'Hartford, SD' }
     ------------------------------------------------------------------ */
  var TESTIMONIALS_ENABLED = false;
  var TESTIMONIALS = [
    // { quote: '', name: '', place: '' },
  ];

  /* ---------- Sticky header shadow ---------- */
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Tabs (WAI-ARIA tabs pattern, automatic activation) ---------- */
  var tabs = Array.prototype.slice.call(document.querySelectorAll('[role="tab"]'));

  function activateTab(tab, setFocus) {
    tabs.forEach(function (t) {
      var selected = t === tab;
      t.setAttribute('aria-selected', String(selected));
      t.tabIndex = selected ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !selected;
    });
    if (setFocus) tab.focus();
  }

  tabs.forEach(function (tab, i) {
    tab.addEventListener('click', function () { activateTab(tab, false); });
    tab.addEventListener('keydown', function (e) {
      var next = null;
      if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
      else if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
      else if (e.key === 'Home') next = tabs[0];
      else if (e.key === 'End') next = tabs[tabs.length - 1];
      if (next) { e.preventDefault(); activateTab(next, true); }
    });
  });

  // "Specializing In" tiles open the Coverage tab and jump to the matching card.
  function openCoverageCard(id, smooth) {
    var card = document.getElementById(id);
    var tab = document.getElementById('tab-coverage');
    if (!card || !tab) return false;
    activateTab(tab, false);
    card.setAttribute('tabindex', '-1');
    card.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'center' });
    card.focus({ preventScroll: true });
    return true;
  }

  document.querySelectorAll('[data-open-tab]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var id = link.getAttribute('href').slice(1);
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      if (openCoverageCard(id, !reduce)) {
        e.preventDefault();
        history.replaceState(null, '', '#' + id);
      }
    });
  });

  if (/^#coverage-/.test(location.hash)) openCoverageCard(location.hash.slice(1), false);

  // Links like the retirement callout can pre-check a product on the contact form.
  document.querySelectorAll('[data-interest]').forEach(function (link) {
    link.addEventListener('click', function () {
      var box = document.querySelector('.contact-form input[name="interests[]"][value="' + link.getAttribute('data-interest') + '"]');
      if (box) box.checked = true;
    });
  });

  /* ---------- FAQ accordion ---------- */
  document.querySelectorAll('.acc-trigger').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!open));
      document.getElementById(btn.getAttribute('aria-controls')).hidden = open;
    });
  });

  /* ---------- Testimonials carousel ---------- */
  (function initTestimonials() {
    var section = document.getElementById('testimonials');
    if (!section || !TESTIMONIALS_ENABLED || TESTIMONIALS.length === 0) return;

    var track = section.querySelector('.carousel-track');
    var status = section.querySelector('[data-carousel-status]');
    var prev = section.querySelector('[data-carousel-prev]');
    var next = section.querySelector('[data-carousel-next]');
    var index = 0;

    function render() {
      var t = TESTIMONIALS[index];
      var fig = document.createElement('figure');
      fig.className = 'testimonial';
      fig.setAttribute('role', 'group');
      fig.setAttribute('aria-roledescription', 'slide');
      fig.setAttribute('aria-label', (index + 1) + ' of ' + TESTIMONIALS.length);
      var q = document.createElement('blockquote');
      q.textContent = '“' + t.quote + '”';
      var cap = document.createElement('figcaption');
      cap.textContent = '— ' + t.name + (t.place ? ', ' + t.place : '');
      fig.appendChild(q);
      fig.appendChild(cap);
      track.replaceChildren(fig);
      status.textContent = (index + 1) + ' / ' + TESTIMONIALS.length;
    }

    function go(step) {
      index = (index + step + TESTIMONIALS.length) % TESTIMONIALS.length;
      render();
    }

    prev.addEventListener('click', function () { go(-1); });
    next.addEventListener('click', function () { go(1); });
    if (TESTIMONIALS.length < 2) { prev.hidden = true; next.hidden = true; status.hidden = true; }

    section.hidden = false;
    render();
  })();

  /* ---------- Contact form (emailed by contact.php, submitted with fetch) ---------- */
  var form = document.querySelector('.contact-form');
  if (form) {
    var errorBox = form.querySelector('[data-form-error]');
    var success = document.querySelector('[data-form-success]');
    var submitBtn = form.querySelector('[type="submit"]');

    function showError(msg, field) {
      errorBox.textContent = msg;
      errorBox.hidden = false;
      if (field) field.focus();
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      errorBox.hidden = true;

      var invalid = null;
      form.querySelectorAll('input, textarea').forEach(function (el) {
        if (el.name === 'bot-field' || el.type === 'hidden' || el.type === 'checkbox' || el.type === 'radio') return;
        var ok = el.checkValidity() && !(el.required && !el.value.trim());
        el.setAttribute('aria-invalid', String(!ok));
        if (!ok && !invalid) invalid = el;
      });
      if (invalid) {
        var label = form.querySelector('label[for="' + invalid.id + '"]');
        var name = label ? label.textContent.replace('*', '').trim() : 'this field';
        showError('Please check ' + name + '.', invalid);
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending…';

      fetch(form.getAttribute('action'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
        body: new URLSearchParams(new FormData(form)).toString()
      })
        .then(function (res) {
          // Only show "Thank you" when contact.php confirms the email was sent.
          return res.json().catch(function () { return {}; }).then(function (data) {
            if (!res.ok || !data.ok) throw new Error(data.message || 'HTTP ' + res.status);
          });
        })
        .then(function () {
          form.hidden = true;
          success.hidden = false;
          success.focus();
        })
        .catch(function (err) {
          // Show contact.php's own message (e.g. "Please enter a valid email address.") when there is one.
          var msg = err && err.message && !/^HTTP |JSON|fetch/i.test(err.message)
            ? err.message : 'Sorry, something went wrong sending your request.';
          showError(msg + ' If it keeps happening, please call me at 605-321-5367.');
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send My Request';
        });
    });
  }
})();
