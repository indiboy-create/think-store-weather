(function () {
  'use strict';

  function el(id) {
    return document.getElementById(id);
  }

  function initNav() {
    var toggle = el('wx-nav-toggle');
    var nav = el('wx-nav');

    if (!toggle || !nav) {
      return;
    }

    var icon = toggle.querySelector('i');

    function close() {
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', '打开导航');

      if (icon) {
        icon.className = 'layui-icon layui-icon-spread-left';
      }
    }

    function open() {
      nav.classList.add('is-open');
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', '关闭导航');

      if (icon) {
        icon.className = 'layui-icon layui-icon-close';
      }
    }

    toggle.addEventListener('click', function (event) {
      event.stopPropagation();

      if (nav.classList.contains('is-open')) {
        close();
      } else {
        open();
      }
    });

    nav.addEventListener('click', function (event) {
      if (event.target instanceof Element && event.target.closest('a')) {
        close();
      }
    });

    document.addEventListener('click', function (event) {
      if (!nav.classList.contains('is-open')) {
        return;
      }

      if (nav.contains(event.target) || toggle.contains(event.target)) {
        return;
      }

      close();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        close();
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 820) {
        close();
      }
    });
  }

  function initAnchorNav() {
    var links = document.querySelectorAll('.wx-nav a');

    links.forEach(function (link) {
      link.addEventListener('click', function () {
        if ((this.getAttribute('href') || '').charAt(0) !== '#') {
          return;
        }

        links.forEach(function (item) {
          var parent = item.closest('.layui-nav-item');

          if (parent) {
            parent.classList.remove('layui-this');
          }
        });

        var current = this.closest('.layui-nav-item');

        if (current) {
          current.classList.add('layui-this');
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initAnchorNav();
  });
})();