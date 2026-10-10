/**
 * منصة الرخصة المهنية — طبقة التفاعل «سيلادون»
 * ---------------------------------------------------------------------------
 * مستوحاة من قالب Celadon (templatemo.com/tm-633-celadon) بأسلوب كلايمورفيزم:
 * ظهور تدريجي عند التمرير، عدّادات أرقام متحركة، مسار شهادات قابل للسحب
 * بفيزياء قصور ذاتي، مراقبة ظهور الأقسام، زر العودة للأعلى، وتحسينات
 * لوحة الاختبار (تنبيه آخر دقيقتين).

 * مكتوبة بـ JavaScript خالص بلا أي مكتبات، وتُعطّل نفسها تلقائياً عند تفعيل
 * تفضيل «تقليل الحركة» في النظام.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------------------
     1) الظهور التدريجي عند التمرير
     --------------------------------------------------------------------- */
  function initReveal() {
    var nodes = document.querySelectorAll('.rv');
    if (!nodes.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
      nodes.forEach(function (node) { node.classList.add('rv-in'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('rv-in');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    nodes.forEach(function (node) { observer.observe(node); });
  }

  /* ---------------------------------------------------------------------
     2) عدّادات الأرقام المتحركة
        <span data-count-to="1250" data-count-suffix="+">0</span>
        الرقم النهائي مكتوب داخل العنصر، فإن تعطّلت الجافاسكربت بقي صحيحاً.
     --------------------------------------------------------------------- */
  function initCounters() {
    var nodes = document.querySelectorAll('[data-count-to]');
    if (!nodes.length) return;

    function render(node, value) {
      var suffix = node.getAttribute('data-count-suffix') || '';
      var prefix = node.getAttribute('data-count-prefix') || '';
      var formatted = Math.round(value).toLocaleString('ar-EG');
      node.textContent = prefix + formatted + suffix;
    }

    function animate(node) {
      var target = parseFloat(node.getAttribute('data-count-to')) || 0;
      var duration = parseInt(node.getAttribute('data-count-duration') || '1100', 10);
      var start = null;
      function step(timestamp) {
        if (start === null) start = timestamp;
        var progress = Math.min((timestamp - start) / duration, 1);
        // تسارع ناعم (ease-out cubic)
        var eased = 1 - Math.pow(1 - progress, 3);
        render(node, target * eased);
        if (progress < 1) window.requestAnimationFrame(step);
      }
      window.requestAnimationFrame(step);
    }

    nodes.forEach(function (node) {
      if (reduceMotion || !('IntersectionObserver' in window)) return;
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animate(entry.target);
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.4 });
      observer.observe(node);
    });
  }

  /* ---------------------------------------------------------------------
     3) مسار الشهادات: سحب وإفلات بفيزياء قصور ذاتي
        البنية: [data-rail] > .rail-track ، وأزرار [data-rail-prev] / [data-rail-next]
     --------------------------------------------------------------------- */
  function initRail() {
    document.querySelectorAll('[data-rail]').forEach(function (rail) {
      var track = rail.querySelector('[data-rail-track]');
      if (!track) return;

      var prev = document.querySelector('[data-rail-prev]');
      var next = document.querySelector('[data-rail-next]');
      var dragging = false;
      var startX = 0;
      var startScroll = 0;
      var velocity = 0;
      var lastX = 0;
      var lastTime = 0;
      var rafId = null;

      function cardStep() {
        var card = track.querySelector(':scope > *');
        if (!card) return 280;
        var gap = parseFloat(getComputedStyle(track).columnGap || '16') || 16;
        return card.getBoundingClientRect().width + gap;
      }

      function updateButtons() {
        var max = track.scrollWidth - track.clientWidth - 2;
        // في RTL يكون scrollLeft سالباً في المتصفحات الحديثة، لذا نتحقق من الاتجاهين
        var offset = Math.abs(track.scrollLeft);
        if (prev) prev.disabled = offset <= 2;
        if (next) next.disabled = offset >= max;
      }

      if (prev) {
        prev.addEventListener('click', function () {
          track.scrollBy({ left: cardStep(), behavior: reduceMotion ? 'auto' : 'smooth' });
        });
      }
      if (next) {
        next.addEventListener('click', function () {
          track.scrollBy({ left: -cardStep(), behavior: reduceMotion ? 'auto' : 'smooth' });
        });
      }

      if (!reduceMotion) {
        track.addEventListener('pointerdown', function (event) {
          if (event.pointerType === 'mouse' && event.button !== 0) return;
          dragging = true;
          velocity = 0;
          startX = event.clientX;
          startScroll = track.scrollLeft;
          lastX = event.clientX;
          lastTime = performance.now();
          track.classList.add('dragging');
          track.setPointerCapture(event.pointerId);
          if (rafId) window.cancelAnimationFrame(rafId);
        });

        track.addEventListener('pointermove', function (event) {
          if (!dragging) return;
          var delta = event.clientX - startX;
          track.scrollLeft = startScroll - delta;
          var now = performance.now();
          var elapsed = now - lastTime;
          if (elapsed > 0) {
            velocity = (event.clientX - lastX) / elapsed;
            lastX = event.clientX;
            lastTime = now;
          }
          event.preventDefault();
        });

        function endDrag(event) {
          if (!dragging) return;
          dragging = false;
          track.classList.remove('dragging');
          if (event && event.pointerId !== undefined && track.hasPointerCapture(event.pointerId)) {
            track.releasePointerCapture(event.pointerId);
          }
          // قصور ذاتي: يتابع المسار الحركة ثم يتوقف تدريجياً
          var momentum = velocity * 26;
          if (Math.abs(momentum) < 4) {
            updateButtons();
            return;
          }
          var friction = 0.93;
          function glide() {
            momentum *= friction;
            track.scrollLeft -= momentum;
            if (Math.abs(momentum) > 1.2) {
              rafId = window.requestAnimationFrame(glide);
            } else {
              updateButtons();
            }
          }
          rafId = window.requestAnimationFrame(glide);
        }

        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);
        track.addEventListener('pointerleave', endDrag);
        track.addEventListener('dragstart', function (event) { event.preventDefault(); });
      }

      track.addEventListener('scroll', updateButtons, { passive: true });
      window.addEventListener('resize', updateButtons);
      updateButtons();
    });
  }

  /* ---------------------------------------------------------------------
     4) الشريط العلوي المتلاصق + زر العودة للأعلى
     --------------------------------------------------------------------- */
  function initScrollUi() {
    var top = document.querySelector('.pl-navbar');
    var upButton = document.getElementById('clUp');

    function onScroll() {
      var offset = window.scrollY || document.documentElement.scrollTop;
      if (top) top.classList.toggle('stuck', offset > 12);
      if (upButton) upButton.classList.toggle('show', offset > 520);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (upButton) {
      upButton.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      });
    }
  }

  /* ---------------------------------------------------------------------
     5) فتح أقسام المزايا بالنقر (شريحة تتوسّع مثل قالب سيلادون)
        البنية: [data-accordion-group] > [data-accordion-item]
     --------------------------------------------------------------------- */
  function initSlabAccordion() {
    document.querySelectorAll('[data-slab-group]').forEach(function (group) {
      var items = group.querySelectorAll('[data-slab-item]');
      items.forEach(function (item) {
        var head = item.querySelector('[data-slab-head]') || item;
        function toggle() {
          var willOpen = !item.classList.contains('open');
          items.forEach(function (other) { other.classList.remove('open'); });
          if (willOpen) item.classList.add('open');
        }
        head.addEventListener('click', toggle);
        head.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggle();
          }
        });
      });
    });
  }

  /* ---------------------------------------------------------------------
     6) لوحة الاختبار: تنبيه آخر دقيقتين + منع فقدان الإجابات
     --------------------------------------------------------------------- */
  function initExamExtras() {
    // شاشة الاختبار ترسم المؤقّت عبر assets/js/exam.js ([data-timer]);
    // هنا نضيف تجهيزات الشكل فقط: تلوين المؤقّت قرب النهاية، ومنع فقدان الإجابات.
    var timer = document.querySelector('[data-timer]');
    if (timer) {
      var styles = document.createElement('style');
      styles.textContent = '.pl-timer.warning{background:linear-gradient(180deg,#FFE1A8 0%,#E8B457 100%)!important;'
        + 'color:#4A3410!important}.pl-timer.danger{background:linear-gradient(180deg,#F5A492 0%,#D2604A 100%)!important;'
        + 'color:#40150E!important}';
      document.head.appendChild(styles);
    }

    // حماية الإجابات من ضغط Backspace بالخطأ خارج الحقول
    document.addEventListener('keydown', function (event) {
      var tag = (event.target && event.target.tagName || '').toLowerCase();
      if (event.key === 'Backspace' && ['input', 'textarea', 'select'].indexOf(tag) === -1) {
        event.preventDefault();
      }
    });
  }

  /* ---------------------------------------------------------------------
     7) نسخ النصوص (مفاتيح، روابط، أكواد)
     --------------------------------------------------------------------- */
  function initCopy() {
    document.querySelectorAll('[data-cl-copy]').forEach(function (node) {
      node.addEventListener('click', function () {
        var text = node.getAttribute('data-cl-copy') || node.textContent.trim();
        if (!text) return;
        var done = function () {
          var original = node.getAttribute('data-cl-original') || node.innerHTML;
          if (!node.getAttribute('data-cl-original')) node.setAttribute('data-cl-original', original);
          if (window.plToast) { window.plToast('تم النسخ'); return; }
          node.textContent = 'تم النسخ';
          setTimeout(function () { node.innerHTML = original; }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done).catch(done);
        } else {
          done();
        }
      });
    });
  }

  /* ---------------------------------------------------------------------
     8) تشغيل عند الجهوزية
     --------------------------------------------------------------------- */
  function boot() {
    initReveal();
    initCounters();
    initRail();
    initScrollUi();
    initSlabAccordion();
    initExamExtras();
    initCopy();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // واجهات عامة متاحة لبقية السكربتات
  window.plCeladon = {
    reveal: initReveal,
    counters: initCounters,
    rail: initRail,
    reducedMotion: reduceMotion
  };
})();
