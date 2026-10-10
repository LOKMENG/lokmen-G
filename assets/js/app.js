/* =====================================================================
   منصة الرخصة المهنية - سكربتات الواجهة العامة
   ===================================================================== */
(function () {
  'use strict';

  /* ---------------- الوضع الليلي/النهاري ---------------- */
  const themeKey = 'pl-theme';
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('[data-theme-icon]').forEach(function (el) {
      el.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    });
  }
  window.plToggleTheme = function () {
    const next = (document.documentElement.getAttribute('data-bs-theme') || 'light') === 'dark' ? 'light' : 'dark';
    localStorage.setItem(themeKey, next);
    applyTheme(next);
  };
  applyTheme(localStorage.getItem(themeKey) || document.documentElement.getAttribute('data-bs-theme') || 'light');

  /* ---------------- القائمة الجانبية على الجوال ---------------- */
  window.plToggleSidebar = function () {
    const sidebar = document.querySelector('.pl-sidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('show');
    let backdrop = document.querySelector('.pl-sidebar-backdrop');
    if (sidebar.classList.contains('show')) {
      if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'pl-sidebar-backdrop';
        backdrop.addEventListener('click', window.plToggleSidebar);
        document.body.appendChild(backdrop);
      }
    } else if (backdrop) {
      backdrop.remove();
    }
  };

  /* ---------------- التحقق قبل العمليات الحساسة ---------------- */
  document.addEventListener('click', function (event) {
    const target = event.target.closest('[data-confirm]');
    if (target && !window.confirm(target.getAttribute('data-confirm'))) {
      event.preventDefault();
      event.stopPropagation();
    }
  });

  /* ---------------- تحديد الكل في الجداول + حماية النماذج الجماعية ---------------- */
  // يجمع مربعات الاختيار التابعة لنموذج، سواء كانت داخله أو مرتبطة به عبر سمة form
  function plFormBoxes(form) {
    const boxes = Array.prototype.slice.call(form.querySelectorAll('input[type=checkbox][name$="[]"]'));
    if (form.id) {
      document.querySelectorAll('input[type=checkbox][form="' + form.id + '"]').forEach(function (box) {
        if (boxes.indexOf(box) === -1 && !box.disabled) boxes.push(box);
      });
    }
    return boxes;
  }

  document.addEventListener('change', function (event) {
    const master = event.target.closest('[data-check-all]');
    if (!master) return;
    const form = document.querySelector(master.getAttribute('data-check-all'));
    const boxes = form ? plFormBoxes(form) : document.querySelectorAll('input[type=checkbox][name$="[]"]');
    Array.prototype.forEach.call(boxes, function (box) {
      if (!box.disabled) box.checked = master.checked;
    });
  });

  document.addEventListener('submit', function (event) {
    const form = event.target;
    const boxes = plFormBoxes(form);
    if (boxes.length === 0) return;
    const checked = boxes.filter(function (box) { return box.checked; }).length;
    if (checked === 0) {
      event.preventDefault();
      event.stopImmediatePropagation();
      window.alert('يرجى تحديد صف واحد على الأقل.');
    }
  }, true);

  /* ---------------- منع الإرسال المزدوج للنماذج ---------------- */
  document.addEventListener('submit', function (event) {
    const form = event.target;
    if (form.dataset.submitted === '1') {
      event.preventDefault();
      return;
    }
    form.dataset.submitted = '1';
    const button = form.querySelector('[type="submit"]');
    if (button && !form.hasAttribute('data-no-spinner')) {
      const original = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm ms-2"></span>' + original;
      setTimeout(function () { form.dataset.submitted = '0'; button.disabled = false; button.innerHTML = original; }, 12000);
    }
  });

  /* ---------------- إظهار/إخفاء كلمة المرور ---------------- */
  document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-toggle-password]');
    if (!toggle) return;
    const input = document.querySelector(toggle.getAttribute('data-toggle-password'));
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    toggle.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  /* ---------------- نسخ إلى الحافظة ---------------- */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-copy]');
    if (!button) return;
    const value = button.getAttribute('data-copy');
    const done = function () {
      const original = button.innerHTML;
      button.innerHTML = '<i class="bi bi-check2"></i> تم النسخ';
      setTimeout(function () { button.innerHTML = original; }, 1600);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(value).then(done);
    } else {
      const temp = document.createElement('textarea');
      temp.value = value;
      document.body.appendChild(temp);
      temp.select();
      document.execCommand('copy');
      temp.remove();
      done();
    }
  });

  /* ---------------- رسوم Chart.js من سمات data-* ---------------- */
  window.plDrawChart = function (canvas, config) {
    if (typeof Chart === 'undefined' || !canvas) return null;
    const isDark = (document.documentElement.getAttribute('data-bs-theme') || 'light') === 'dark';
    Chart.defaults.font.family = "'Cairo', sans-serif";
    Chart.defaults.color = isDark ? '#cfe0d7' : '#5f7169';
    config.options = Object.assign({
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { labels: { boxWidth: 12, usePointStyle: true } } },
      scales: (config.type === 'doughnut' || config.type === 'pie') ? {} : {
        y: { beginAtZero: true, grid: { color: isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)' } },
        x: { grid: { display: false } }
      }
    }, config.options || {});
    return new Chart(canvas, config);
  };

  document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
    let payload;
    try {
      payload = JSON.parse(canvas.getAttribute('data-chart'));
    } catch (e) {
      return;
    }
    payload.data = payload.data || {};
    const palette = ['#0b6b3a', '#c9a227', '#0d6efd', '#dc3545', '#20c997', '#6f42c1', '#fd7e14', '#0dcaf0'];
    if (payload.data.datasets) {
      payload.data.datasets = payload.data.datasets.map(function (dataset, index) {
        const type = payload.type || 'line';
        const color = dataset.color || palette[index % palette.length];
        if (type === 'doughnut' || type === 'pie') {
          dataset.backgroundColor = dataset.backgroundColor || palette;
          dataset.borderWidth = dataset.borderWidth || 2;
        } else if (type === 'bar') {
          dataset.backgroundColor = dataset.backgroundColor || color;
          dataset.borderRadius = 6;
        } else {
          dataset.borderColor = dataset.borderColor || color;
          dataset.backgroundColor = dataset.backgroundColor || (color + '22');
          dataset.tension = 0.35;
          dataset.fill = true;
          dataset.borderWidth = 2;
          dataset.pointRadius = 3;
        }
        return dataset;
      });
    }
    window.plDrawChart(canvas, payload);
  });

  /* ---------------- إخفاء التنبيهات تلقائياً ---------------- */
  setTimeout(function () {
    document.querySelectorAll('.alert[data-auto-dismiss]').forEach(function (alert) {
      alert.classList.add('fade');
      setTimeout(function () { alert.remove(); }, 400);
    });
  }, 5000);

  /* ---------------- تفعيل التلميحات ---------------- */
  if (typeof bootstrap !== 'undefined') {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      new bootstrap.Tooltip(el);
    });
  }
})();
