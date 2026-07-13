/**
 * Accounting System — global UI
 * Theme switch · fixed sidebar · collapse · API helpers
 */
(function () {
  'use strict';

  var THEME_KEY = 'as_theme';
  var base = typeof window.APP_BASE === 'string' ? window.APP_BASE : '';

  window.Apex = {
    base: base,
    url: function (path) {
      if (!path) path = '/';
      if (path.charAt(0) !== '/') path = '/' + path;
      return base + path;
    },
    api: function (path, options) {
      options = options || {};
      options.credentials = 'same-origin';
      options.headers = Object.assign(
        { Accept: 'application/json' },
        options.headers || {}
      );
      return fetch(Apex.url(path), options).then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok && !data.message) {
            data.message = 'Request failed (' + res.status + ')';
          }
          data._status = res.status;
          return data;
        }).catch(function () {
          return { success: false, message: 'Invalid JSON response', _status: res.status };
        });
      });
    },
    toast: function (message, type) {
      type = type || 'info';
      var root = document.getElementById('toast-root');
      if (!root) {
        root = document.createElement('div');
        root.id = 'toast-root';
        document.body.appendChild(root);
      }
      var el = document.createElement('div');
      el.className = 'toast toast-' + type;
      el.textContent = message;
      root.appendChild(el);
      setTimeout(function () {
        el.style.opacity = '0';
        el.style.transition = 'opacity .3s';
        setTimeout(function () { el.remove(); }, 300);
      }, 3200);
    },
    money: function (n) {
      var x = Number(n) || 0;
      return x.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
    qs: function (sel, ctx) {
      return (ctx || document).querySelector(sel);
    },
    qsa: function (sel, ctx) {
      return Array.prototype.slice.call((ctx || document).querySelectorAll(sel));
    },
    getTheme: function () {
      return document.documentElement.getAttribute('data-theme') || 'light';
    },
    setTheme: function (theme) {
      theme = theme === 'dark' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', theme);
      document.body && document.body.setAttribute('data-theme', theme);
      try {
        localStorage.setItem(THEME_KEY, theme);
      } catch (e) {}
      var sw = document.getElementById('theme-switch');
      if (sw) sw.checked = theme === 'dark';
      var label = document.getElementById('theme-switch-label');
      if (label) label.textContent = theme === 'dark' ? 'Dark' : 'Light';
    },
    toggleTheme: function () {
      Apex.setTheme(Apex.getTheme() === 'dark' ? 'light' : 'dark');
    },
  };

  // Theme
  function initTheme() {
    var theme = 'light';
    try {
      theme = localStorage.getItem(THEME_KEY) || 'light';
    } catch (e) {}
    Apex.setTheme(theme);

    var sw = document.getElementById('theme-switch');
    if (sw) {
      sw.checked = Apex.getTheme() === 'dark';
      // change + click both (some browsers flaky with label)
      sw.addEventListener('change', function () {
        Apex.setTheme(sw.checked ? 'dark' : 'light');
      });
      sw.addEventListener('click', function (e) {
        // ensure toggle even if change doesn't fire
        setTimeout(function () {
          Apex.setTheme(sw.checked ? 'dark' : 'light');
        }, 0);
      });
    }

    var wrap = document.getElementById('theme-switch-wrap');
    if (wrap) {
      wrap.addEventListener('click', function (e) {
        if (e.target === sw) return;
        e.preventDefault();
        Apex.toggleTheme();
      });
    }
  }

  // Sidebar fixed + hide/show
  function initSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    var openBtn = document.getElementById('btn-sidebar-open');
    var closeBtn = document.getElementById('btn-sidebar-close');

    function openMobile() {
      if (!sidebar) return;
      sidebar.classList.add('open');
      if (overlay) overlay.classList.add('show');
      document.body.classList.add('sidebar-mobile-open');
    }
    function closeMobile() {
      if (!sidebar) return;
      sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('show');
      document.body.classList.remove('sidebar-mobile-open');
    }

    if (openBtn) openBtn.addEventListener('click', openMobile);
    if (closeBtn) closeBtn.addEventListener('click', closeMobile);
    if (overlay) overlay.addEventListener('click', closeMobile);

    window.addEventListener('resize', function () {
      if (window.matchMedia('(min-width: 1024px)').matches) closeMobile();
    });
  }

  // Nested nav click (hover also via CSS)
  function initNav() {
    Apex.qsa('[data-nav-group]').forEach(function (group) {
      var parent = group.querySelector('[data-nav-parent]');
      if (!parent) return;
      parent.addEventListener('click', function (e) {
        e.preventDefault();
        var open = group.classList.contains('is-open');
        Apex.qsa('[data-nav-group]').forEach(function (g) {
          if (g !== group) {
            g.classList.remove('is-open');
            var p = g.querySelector('[data-nav-parent]');
            if (p) p.setAttribute('aria-expanded', 'false');
          }
        });
        group.classList.toggle('is-open', !open);
        parent.setAttribute('aria-expanded', !open ? 'true' : 'false');
      });
    });
  }

  // Hierarchy toggle on COA
  function initHierarchyToggle() {
    var btn = document.getElementById('btn-toggle-hierarchy');
    var panel = document.getElementById('coa-hierarchy-panel');
    if (!btn || !panel) return;
    btn.addEventListener('click', function () {
      var hidden = panel.classList.toggle('hidden');
      btn.textContent = hidden ? 'Show Hierarchy' : 'Hide Hierarchy';
    });
  }

  /**
   * Enter key → next field (like Tab)
   * Works on all forms: inputs, selects, textareas
   * Last field: if submit button exists, focus/click it; else stay
   * Shift+Enter on textarea still inserts newline
   */
  function initEnterToNextField() {
    function isFocusableField(el) {
      if (!el || el.disabled || el.readOnly) return false;
      if (el.tabIndex < 0) return false;
      if (el.type === 'hidden' || el.type === 'file' || el.type === 'submit' || el.type === 'button' || el.type === 'reset' || el.type === 'checkbox' || el.type === 'radio') {
        return false;
      }
      var tag = (el.tagName || '').toUpperCase();
      return tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA';
    }

    function getFormFields(form) {
      var nodes = form.querySelectorAll('input, select, textarea');
      var list = [];
      for (var i = 0; i < nodes.length; i++) {
        if (isFocusableField(nodes[i])) list.push(nodes[i]);
      }
      return list;
    }

    function focusNext(form, current) {
      var fields = getFormFields(form);
      var idx = fields.indexOf(current);
      if (idx === -1) return false;

      // Prefer next enabled field
      for (var i = idx + 1; i < fields.length; i++) {
        try {
          fields[i].focus();
          if (typeof fields[i].select === 'function' && fields[i].tagName === 'INPUT' && fields[i].type !== 'date' && fields[i].type !== 'number') {
            fields[i].select();
          }
          return true;
        } catch (e) {}
      }

      // No next field → try submit button
      var submit =
        form.querySelector('button[type="submit"]:not([disabled])') ||
        form.querySelector('input[type="submit"]:not([disabled])') ||
        form.querySelector('.btn-primary:not([disabled])') ||
        form.querySelector('.btn-save-dark:not([disabled])');
      if (submit) {
        submit.focus();
        // Do not auto-submit — user presses Enter again to save
        return true;
      }
      return false;
    }

    document.addEventListener(
      'keydown',
      function (e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) return;

        var el = e.target;
        if (!el || !el.closest) return;

        // Allow native submit if already on a submit button
        var tag = (el.tagName || '').toUpperCase();
        if (tag === 'BUTTON' || (tag === 'INPUT' && (el.type === 'submit' || el.type === 'button'))) {
          return;
        }

        // Textarea: Enter = newline, Ctrl/Cmd+Enter = next field
        if (tag === 'TEXTAREA') {
          if (!(e.ctrlKey || e.metaKey)) return;
        }

        var form = el.closest('form');
        if (!form) return;
        if (!isFocusableField(el) && tag !== 'TEXTAREA') return;

        // Move to next field instead of submitting mid-form
        e.preventDefault();
        focusNext(form, el);
      },
      true
    );
  }

  function boot() {
    initTheme();
    initSidebar();
    initNav();
    initHierarchyToggle();
    initEnterToNextField();
  }

  document.addEventListener('DOMContentLoaded', boot);

  // Also run immediately if DOM already ready
  if (document.readyState !== 'loading') {
    boot();
  }
})();
