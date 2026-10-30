/* =====================================================================
   Neon Carbon — سلوك الواجهة (مبني على سكربت قالب Tooplate 2165)
   مُعاد كتابته ليكون:
     • دفاعياً: لا يُسبب أي خطأ إذا كانت العناصر غير موجودة
     • متوافقاً مع RTL والعربية
     • آمناً عند تعطّل الجافاسكربت (المحتوى يبقى ظاهراً)
     • محترماً لتفضيل تقليل الحركة prefers-reduced-motion
   ===================================================================== */
(function () {
  'use strict';

  var root = document.documentElement;
  var body = document.body;
  if (!body || body.className.indexOf('theme-neon-carbon') === -1) { return; }

  // علامة تفعيل السكربت: تُفعّل الظهور التدريجي فقط عند وجود الجافاسكربت
  body.classList.add('nc-js');

  var reduceMotion = false;
  try {
    reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  } catch (e) { reduceMotion = false; }

  /* ── 1) الظهور التدريجي عند التمرير ───────────────────────────── */
  var revealEls = document.querySelectorAll('.nc-reveal');
  if (revealEls.length) {
    if (reduceMotion || !('IntersectionObserver' in window)) {
      Array.prototype.forEach.call(revealEls, function (el) { el.classList.add('nc-visible'); });
    } else {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('nc-visible');
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
      Array.prototype.forEach.call(revealEls, function (el) { observer.observe(el); });
      // أمان: إظهار كل العناصر بعد ٣ ثوان (للمعاينات داخل إطارات)
      setTimeout(function () {
        Array.prototype.forEach.call(revealEls, function (el) { el.classList.add('nc-visible'); });
      }, 3000);
    }
  }

  /* ── 2) تمييز رابط الشريط الجانبي النشط ───────────────────────── */
  var sidebarLinks = document.querySelectorAll('.pl-sidebar-nav .nav-link');
  if (sidebarLinks.length) {
    var currentPath = (location.pathname || '').replace(/\/+$/, '');
    Array.prototype.forEach.call(sidebarLinks, function (link) {
      try {
        var href = link.getAttribute('href') || '';
        if (!href) { return; }
        var linkPath = href.replace(/^https?:\/\/[^/]+/, '').replace(/\/+$/, '');
        if (linkPath && (currentPath === linkPath || currentPath.indexOf(linkPath + '/') === 0)) {
          link.classList.add('active');
        }
      } catch (e) { /* تجاهل */ }
    });
  }

  /* ── 3) قائمة الجوال (الهامبرغر) ──────────────────────────────── */
  var toggler = document.querySelector('.pl-navbar .navbar-toggler, .pl-topbar .navbar-toggler');
  if (toggler) {
    toggler.addEventListener('click', function () {
      var expanded = toggler.getAttribute('aria-expanded') === 'true';
      toggler.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    });
  }

  /* ── 4) تمرير سلس لروابط الأنكور ──────────────────────────────── */
  var anchors = document.querySelectorAll('a[href^="#"]:not([href="#"]):not([data-bs-toggle])');
  Array.prototype.forEach.call(anchors, function (link) {
    link.addEventListener('click', function (event) {
      var href = link.getAttribute('href') || '';
      if (href.length < 2) { return; }
      var target;
      try { target = document.querySelector(href); } catch (e) { target = null; }
      if (!target) { return; }
      event.preventDefault();
      target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    });
  });

  /* ── 5) عدّاد زمن الجلسة (إن وُجد عنصر data-nc-uptime) ─────────── */
  var uptimeEl = document.querySelector('[data-nc-uptime]');
  if (uptimeEl) {
    var startedAt = Date.now();
    var tick = function () {
      var elapsed = Math.floor((Date.now() - startedAt) / 1000);
      var h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
      var m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
      var s = String(elapsed % 60).padStart(2, '0');
      uptimeEl.textContent = h + ':' + m + ':' + s;
    };
    tick();
    setInterval(tick, 1000);
  }

  /* ── 6) وميض خفيف على المؤقّت عند الحالة الحرجة ───────────────── */
  var timer = document.querySelector('.pl-timer.danger');
  if (timer && !reduceMotion) {
    var on = true;
    setInterval(function () {
      timer.style.boxShadow = on ? '0 0 22px rgba(255, 59, 78, .55)' : '0 0 10px rgba(255, 59, 78, .25)';
      on = !on;
    }, 900);
  }
})();
