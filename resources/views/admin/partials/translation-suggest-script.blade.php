{{--
  Shared "Suggest translations (AI)" click handler for the modal-per-row
  translation panels (Announcement, Staff, Designation/Department —
  docs/modules/30-multilingual-content-plan.md Phase 5/7). One delegated
  listener per page (not one per row/panel) so @include-ing this partial
  once in an index page's @push('scripts') wires every row's button,
  including rows added later.

  A plain form POST+redirect would close the modal the admin is sitting in
  — they'd have to reopen Edit just to see what the AI filled in. Instead
  the button (class="js-suggest-translation", data-form="<hidden form id>")
  fetch()es that hidden form's own action/CSRF/locale with
  X-Requested-With so the controller returns JSON instead of redirecting,
  then fills the translation inputs inside the button's own <details> panel
  directly — no navigation, modal stays open. Only fills fields that are
  still empty (never overwrites text the admin already typed, matching the
  suggest job's own "never overwrite an existing translation" rule).
--}}
<script>
  (function () {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.js-suggest-translation');
      if (!btn) return;
      e.preventDefault();

      var form = document.getElementById(btn.dataset.form);
      var panel = btn.closest('details');
      if (!form || !panel) return;

      var originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> ' + @json(__('Translating…'));

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      })
        .then(function (res) {
          return res.json().catch(function () { return {}; }).then(function (data) {
            return { ok: res.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok) throw new Error((result.data && result.data.message) || 'Request failed');
          var fields = (result.data && result.data.fields) || {};
          Object.keys(fields).forEach(function (field) {
            var input = panel.querySelector('[name$="[' + field + ']"]');
            if (input && !input.value.trim()) input.value = fields[field] || '';
          });
          btn.innerHTML = '<i class="bi bi-check2"></i> ' + @json(__('Filled in below'));
        })
        .catch(function () {
          btn.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + @json(__('Failed — try again'));
        })
        .finally(function () {
          setTimeout(function () { btn.disabled = false; btn.innerHTML = originalHtml; }, 2000);
        });
    });
  })();
</script>
