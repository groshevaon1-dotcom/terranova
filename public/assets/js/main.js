/* ============================================================
   Terra Nova — поведение страницы.
   Без библиотек. Всё работает и без этого файла: страница
   остаётся читаемой, формы отправляются обычным POST.
   ============================================================ */
(function () {
  'use strict';

  /* ---------- Мобильное меню ---------- */
  var burger = document.getElementById('burger');
  var nav = document.querySelector('.nav');

  if (burger && nav) {
    burger.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.tagName === 'A') {
        nav.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* ---------- Cookie и аналитика ----------
     Аналитика не запускается, пока человек не согласился.
     Требование из юридической карты, раздел 9 CLAUDE.md.        */
  var COOKIE_KEY = 'tn-cookie-choice';
  var banner = document.getElementById('cookie');
  var choice = null;

  try { choice = localStorage.getItem(COOKIE_KEY); } catch (e) {}

  function loadAnalytics() {
    /* TODO: подставить номер счётчика Яндекс.Метрики в ANALYTICS_ID
       и раскомментировать загрузку. Без номера ничего не грузим. */
    var ANALYTICS_ID = null;
    if (!ANALYTICS_ID) return;

    (function (m, e, t, r, i, k, a) {
      m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
      m[i].l = 1 * new Date();
      k = e.createElement(t); a = e.getElementsByTagName(t)[0];
      k.async = 1; k.src = r; a.parentNode.insertBefore(k, a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js', 'ym');

    window.ym(ANALYTICS_ID, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true });
  }

  function remember(value) {
    try {
      localStorage.setItem(COOKIE_KEY, value);
      localStorage.setItem('tn-cookie-date', new Date().toISOString());
    } catch (e) {}
  }

  if (banner) {
    if (choice === 'all') {
      loadAnalytics();
    } else if (choice !== 'necessary') {
      banner.hidden = false;
      document.body.classList.add('has-sticky');
    }

    banner.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-cookie]');
      if (!btn) return;
      var value = btn.getAttribute('data-cookie');
      remember(value);
      banner.hidden = true;
      document.body.classList.remove('has-sticky');
      if (value === 'all') loadAnalytics();
    });
  }

  /* ---------- Форма: без согласия не отправляем ---------- */
  var form = document.querySelector('form.form');
  if (form) {
    form.addEventListener('submit', function (e) {
      var consent = form.querySelector('[name="consent_pd"]');
      var name = form.querySelector('[name="name"]');
      var phone = form.querySelector('[name="phone"]');
      var tg = form.querySelector('[name="telegram"]');
      var mail = form.querySelector('[name="email"]');
      var hint = document.getElementById('contact-hint');
      var problem = null;

      var hasContact = [phone, tg, mail].some(function (f) {
        return f && f.value.trim() !== '';
      });

      if (hint) hint.classList.remove('is-error');

      if (!name.value.trim()) problem = name;
      else if (!hasContact) {
        problem = phone;
        if (hint) hint.classList.add('is-error');
      }
      else if (consent && !consent.checked) problem = consent;

      if (problem) {
        e.preventDefault();
        problem.focus();
        var box = problem.closest('.field') || problem.closest('.consent');
        if (box) {
          box.style.outline = '2px solid var(--error)';
          box.style.outlineOffset = '6px';
          box.style.borderRadius = '12px';
          setTimeout(function () { box.style.outline = 'none'; }, 2400);
        }
      }
    });
  }

  /* ---------- Появление при прокрутке ---------- */
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var items = document.querySelectorAll('.rv');

  if (reduce || !('IntersectionObserver' in window)) {
    for (var i = 0; i < items.length; i++) items[i].classList.add('in');
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    for (var k = 0; k < items.length; k++) io.observe(items[k]);
  }
})();
