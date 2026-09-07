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
