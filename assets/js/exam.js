/* =====================================================================
   محرك واجهة الاختبار: المؤقت، التنقل، حفظ الإجابات عبر AJAX
   ===================================================================== */
(function () {
  'use strict';

  const config = window.PL_EXAM || {};
  if (!config.attemptId) return;

  const state = {
    current: config.startIndex || 0,
    total: 0,
    answered: new Set(),
    flagged: new Set(),
    secondsLeft: config.remainingSeconds || 0,
    submitted: false
  };

  const $ = function (selector) { return document.querySelector(selector); };
  const $$ = function (selector) { return Array.prototype.slice.call(document.querySelectorAll(selector)); };

  /* ---------------- التنقل بين الأسئلة ---------------- */
  function showQuestion(index) {
    const slides = $$('[data-question-index]');
    if (!slides.length) return;
    state.current = Math.max(0, Math.min(index, slides.length - 1));
    slides.forEach(function (slide, i) {
      slide.classList.toggle('d-none', i !== state.current);
    });
    $$('[data-nav-index]').forEach(function (button) {
      button.classList.toggle('current', parseInt(button.getAttribute('data-nav-index'), 10) === state.current);
    });
    const counter = $('[data-question-counter]');
    if (counter) counter.textContent = (state.current + 1) + ' / ' + slides.length;
    const prev = $('[data-exam-prev]');
    const next = $('[data-exam-next]');
    if (prev) prev.disabled = state.current === 0;
    if (next) next.disabled = state.current === slides.length - 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  window.plShowQuestion = showQuestion;

  /* ---------------- تحديث حالة التقدم ---------------- */
  function refreshProgress() {
    const answered = state.answered.size;
    const total = state.total;
    const bar = $('[data-progress-bar]');
    if (bar) bar.style.width = (total ? (answered / total) * 100 : 0) + '%';
    const answeredLabel = $('[data-answered-count]');
    const remainingLabel = $('[data-remaining-count]');
    if (answeredLabel) answeredLabel.textContent = answered;
    if (remainingLabel) remainingLabel.textContent = total - answered;
  }

  /* ---------------- حفظ الإجابة ---------------- */
  function saveAnswer(questionId, answer, flagged) {
    const body = { attempt_id: config.attemptId, question_id: questionId, _token: config.csrfToken };
    if (answer !== undefined) body.answer = answer;
    if (flagged !== undefined) body.flagged = flagged ? 1 : 0;

    return fetch(config.saveUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    }).then(function (response) { return response.json(); }).then(function (data) {
      const indicator = $('[data-save-indicator]');
      if (indicator) {
        indicator.innerHTML = data.ok
          ? '<i class="bi bi-cloud-check text-success"></i> تم الحفظ'
          : '<i class="bi bi-exclamation-triangle text-danger"></i> ' + (data.message || 'تعذّر الحفظ');
        setTimeout(function () { indicator.innerHTML = ''; }, 2500);
      }
      if (data.expired) {
        state.submitted = true;
        window.location.href = config.resultUrl;
      }
      return data;
    }).catch(function () {
      const indicator = $('[data-save-indicator]');
      if (indicator) indicator.innerHTML = '<i class="bi bi-wifi-off text-danger"></i> تحقق من الاتصال';
      return { ok: false };
    });
  }

  /* ---------------- اختيار الإجابة ---------------- */
  document.addEventListener('change', function (event) {
    const input = event.target.closest('input[data-answer]');
    if (!input) return;
    const questionId = parseInt(input.getAttribute('data-question-id'), 10);
    const wrapper = input.closest('.pl-options');
    if (wrapper) {
      wrapper.querySelectorAll('.pl-option').forEach(function (option) { option.classList.remove('selected'); });
      const label = input.closest('.pl-option');
      if (label) label.classList.add('selected');
    }
    state.answered.add(questionId);
    const navButton = document.querySelector('[data-nav-index][data-question-id="' + questionId + '"]');
    if (navButton) navButton.classList.add('answered');
    refreshProgress();
    saveAnswer(questionId, input.value, undefined);
  });

  /* ---------------- تعليم السؤال للمراجعة ---------------- */
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-flag-question]');
    if (!button) return;
    const questionId = parseInt(button.getAttribute('data-flag-question'), 10);
    saveAnswer(questionId, undefined, !state.flagged.has(questionId)).then(function (data) {
      if (!data.ok) return;
      const isFlagged = !!data.flagged;
      if (isFlagged) { state.flagged.add(questionId); } else { state.flagged.delete(questionId); }
      button.classList.toggle('active', isFlagged);
      button.innerHTML = isFlagged
        ? '<i class="bi bi-flag-fill text-warning"></i> مُعلَّم للمراجعة'
        : '<i class="bi bi-flag"></i> علّم للمراجعة';
      const navButton = document.querySelector('[data-nav-index][data-question-id="' + questionId + '"]');
      if (navButton) navButton.classList.toggle('flagged', isFlagged);
      const flaggedLabel = $('[data-flagged-count]');
      if (flaggedLabel) flaggedLabel.textContent = state.flagged.size;
    });
  });

  /* ---------------- أزرار التنقل ---------------- */
  const prev = $('[data-exam-prev]');
  const next = $('[data-exam-next]');
  if (prev) prev.addEventListener('click', function () { showQuestion(state.current - 1); });
  if (next) next.addEventListener('click', function () { showQuestion(state.current + 1); });
  $$('[data-nav-index]').forEach(function (button) {
    button.addEventListener('click', function () {
      showQuestion(parseInt(button.getAttribute('data-nav-index'), 10));
    });
  });
  document.addEventListener('keydown', function (event) {
    if (event.target.matches('input, textarea, select')) return;
    if (event.key === 'ArrowLeft') showQuestion(state.current + 1);
    if (event.key === 'ArrowRight') showQuestion(state.current - 1);
  });

  /* ---------------- المؤقت ---------------- */
  function renderTimer() {
    const timer = $('[data-timer]');
    if (!timer || !config.hasTimer) return;
    const seconds = Math.max(0, state.secondsLeft);
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    const pad = function (value) { return value < 10 ? '0' + value : '' + value; };
    timer.querySelector('[data-timer-value]').textContent = (hours > 0 ? pad(hours) + ':' : '') + pad(minutes) + ':' + pad(secs);
    timer.classList.toggle('warning', seconds <= 300 && seconds > 60);
    timer.classList.toggle('danger', seconds <= 60);
  }

  if (config.hasTimer) {
    setInterval(function () {
      if (state.submitted) return;
      state.secondsLeft = state.secondsLeft - 1;
      renderTimer();
      if (state.secondsLeft <= 0) {
        state.submitted = true;
        const notice = $('[data-time-notice]');
        if (notice) notice.classList.remove('d-none');
        document.getElementById('pl-exam-form').submit();
      }
    }, 1000);
    renderTimer();
  }

  /* ---------------- إنهاء الاختبار ---------------- */
  const finishForm = document.getElementById('pl-exam-form');
  document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-finish-exam]');
    if (!button) return;
    event.preventDefault();
    const unanswered = state.total - state.answered.size;
    const message = unanswered > 0
      ? 'لديك ' + unanswered + ' سؤالاً بدون إجابة. هل تريد إنهاء الاختبار وعرض النتيجة؟'
      : 'هل تريد إنهاء الاختبار وعرض النتيجة؟';
    if (!window.confirm(message)) return;
    state.submitted = true;
    if (finishForm) finishForm.submit();
  });

  /* ---------------- تهيئة الحالة ---------------- */
  const slides = $$('[data-question-index]');
  state.total = slides.length;
  $$('input[data-answer]:checked').forEach(function (input) {
    state.answered.add(parseInt(input.getAttribute('data-question-id'), 10));
  });
  $$('.pl-nav-btn.answered').forEach(function (button) {
    const id = parseInt(button.getAttribute('data-question-id'), 10);
    if (!isNaN(id)) state.answered.add(id);
  });
  $$('[data-flag-question].active').forEach(function (button) {
    state.flagged.add(parseInt(button.getAttribute('data-flag-question'), 10));
  });
  refreshProgress();
  showQuestion(state.current);

  /* ---------------- تحذير عند مغادرة الصفحة ---------------- */
  window.addEventListener('beforeunload', function (event) {
    if (!state.submitted && config.hasTimer) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
})();
