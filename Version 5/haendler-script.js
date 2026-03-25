/* ============================================================
   U-like-it — Händler Subpage Script
   Identisches Pattern wie user-script.js
   ============================================================ */

(function () {
  'use strict';

  /* ---------- Scroll Reveal ---------- */
  var revealElements = document.querySelectorAll('.reveal');

  if ('IntersectionObserver' in window) {
    var revealObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
          }
        });
      },
      { rootMargin: '-60px 0px', threshold: 0.1 }
    );
    revealElements.forEach(function (el) { revealObserver.observe(el); });
  } else {
    revealElements.forEach(function (el) { el.classList.add('visible'); });
  }

  /* ---------- Header Scroll ---------- */
  var header = document.getElementById('site-header');
  var scrollThreshold = 60;

  function handleHeaderScroll() {
    if (window.scrollY > scrollThreshold) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  }

  var headerTicking = false;
  window.addEventListener('scroll', function () {
    if (!headerTicking) {
      requestAnimationFrame(function () { handleHeaderScroll(); headerTicking = false; });
      headerTicking = true;
    }
  });
  handleHeaderScroll();

  /* ---------- Mobile Nav ---------- */
  var navToggle = document.getElementById('nav-toggle');
  var mainNav = document.getElementById('main-nav');

  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      var isOpen = mainNav.classList.toggle('is-open');
      navToggle.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', String(isOpen));
      navToggle.setAttribute('aria-label', isOpen ? 'Menü schließen' : 'Menü öffnen');
      document.body.classList.toggle('nav-open', isOpen);
    });

    mainNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        mainNav.classList.remove('is-open');
        navToggle.classList.remove('is-open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Menü öffnen');
        document.body.classList.remove('nav-open');
      });
    });
  }

  /* ---------- Parallax ---------- */
  var parallaxElements = document.querySelectorAll('[data-parallax]');

  function updateParallax() {
    var scrollY = window.scrollY;
    var viewportHeight = window.innerHeight;
    var viewportCenter = scrollY + viewportHeight / 2;

    parallaxElements.forEach(function (el) {
      var rate = parseFloat(el.getAttribute('data-parallax')) || 0.2;
      var rect = el.getBoundingClientRect();
      var elCenter = scrollY + rect.top + rect.height / 2;
      var offset = (viewportCenter - elCenter) * rate;
      el.style.transform = 'translateY(' + offset + 'px)';
    });
  }

  if (parallaxElements.length > 0) {
    var parallaxTicking = false;
    window.addEventListener('scroll', function () {
      if (!parallaxTicking) {
        requestAnimationFrame(function () { updateParallax(); parallaxTicking = false; });
        parallaxTicking = true;
      }
    });
    window.addEventListener('resize', updateParallax);
    updateParallax();
  }

  /* ---------- Scroll Progress ---------- */
  var scrollProgress = document.getElementById('scroll-progress');
  if (scrollProgress) {
    function updateScrollProgress() {
      var scrollTop = window.scrollY;
      var docHeight = document.documentElement.scrollHeight - window.innerHeight;
      var progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
      scrollProgress.style.width = progress + '%';
      scrollProgress.classList.toggle('is-scrolled', scrollTop > 0);
    }
    window.addEventListener('scroll', function () { requestAnimationFrame(updateScrollProgress); });
    updateScrollProgress();
  }

  /* ---------- Hero Scroll Effect ---------- */
  var heroInner = document.getElementById('hero-inner');
  if (heroInner) {
    function updateHeroScroll() {
      var scrollY = window.scrollY;
      var vh = window.innerHeight;
      var maxScroll = vh * 0.5;
      var t = Math.min(scrollY / maxScroll, 1);
      var scale = 1 - t * 0.06;
      var y = scrollY * 0.15;
      heroInner.style.transform = 'translateY(' + y + 'px) scale(' + scale + ')';
      heroInner.style.opacity = String(1 - t * 0.12);
    }
    window.addEventListener('scroll', function () { requestAnimationFrame(updateHeroScroll); });
    updateHeroScroll();
  }

  /* ---------- Custom Cursor ---------- */
  var isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

  if (!isTouchDevice) {
    var cursor = document.getElementById('custom-cursor');
    var cursorShadow = document.getElementById('custom-cursor-shadow');

    if (cursor && cursorShadow) {
      var mouseX = 0, mouseY = 0, shadowX = 0, shadowY = 0, idleTimer = null;
      document.body.classList.add('cursor-active');

      document.addEventListener('mousemove', function (e) {
        mouseX = e.clientX;
        mouseY = e.clientY;
        cursor.style.left = mouseX + 'px';
        cursor.style.top = mouseY + 'px';
        document.body.classList.remove('cursor-idle');
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function () { document.body.classList.add('cursor-idle'); }, 120);
      });

      function animateShadow() {
        shadowX += (mouseX - shadowX) * 0.12;
        shadowY += (mouseY - shadowY) * 0.12;
        cursorShadow.style.left = shadowX + 'px';
        cursorShadow.style.top = shadowY + 'px';
        requestAnimationFrame(animateShadow);
      }
      animateShadow();
    }
  }

  /* ---------- Dashboard Showcase (Tabs + Swipe) ---------- */
  var showcase = document.getElementById('haendler-showcase');
  if (showcase) {
    var tabs = showcase.querySelectorAll('.app-showcase-tab');
    var imgs = showcase.querySelectorAll('.app-showcase-img');
    var infos = showcase.querySelectorAll('.app-showcase-slide-info');
    var dots = showcase.querySelectorAll('.app-showcase-dot');
    var currentSlide = 0;
    var totalSlides = imgs.length;
    var autoTimer = null;

    function setSlide(index) {
      currentSlide = ((index % totalSlides) + totalSlides) % totalSlides;
      tabs.forEach(function (t, i) {
        t.classList.toggle('active', i === currentSlide);
        t.setAttribute('aria-selected', i === currentSlide);
      });
      imgs.forEach(function (img, i) { img.classList.toggle('active', i === currentSlide); });
      infos.forEach(function (info, i) { info.classList.toggle('active', i === currentSlide); });
      dots.forEach(function (dot, i) { dot.classList.toggle('active', i === currentSlide); });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        setSlide(parseInt(tab.getAttribute('data-slide'), 10));
        resetAuto();
      });
    });

    dots.forEach(function (dot) {
      dot.addEventListener('click', function () {
        setSlide(parseInt(dot.getAttribute('data-dot'), 10));
        resetAuto();
      });
    });

    var swipeArea = document.getElementById('haendler-showcase-screen');
    if (swipeArea) {
      var startX = 0, dragging = false;

      swipeArea.addEventListener('touchstart', function (e) {
        startX = e.changedTouches[0].clientX;
      }, { passive: true });

      swipeArea.addEventListener('touchend', function (e) {
        var diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) { setSlide(currentSlide + (diff > 0 ? 1 : -1)); resetAuto(); }
      }, { passive: true });

      swipeArea.addEventListener('mousedown', function (e) { startX = e.clientX; dragging = true; e.preventDefault(); });
      document.addEventListener('mouseup', function (e) {
        if (!dragging) return;
        dragging = false;
        var diff = startX - e.clientX;
        if (Math.abs(diff) > 40) { setSlide(currentSlide + (diff > 0 ? 1 : -1)); resetAuto(); }
      });
    }

    function startAuto() { autoTimer = setInterval(function () { setSlide(currentSlide + 1); }, 5000); }
    function resetAuto() { clearInterval(autoTimer); startAuto(); }
    startAuto();
  }

  /* ---------- Form Feedback ---------- */
  var form = document.getElementById('haendler-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('.haendler-form-submit');
      if (btn) {
        var originalText = btn.textContent;
        btn.textContent = 'Anfrage gesendet ✓';
        btn.disabled = true;
        btn.style.opacity = '0.7';
        setTimeout(function () {
          btn.textContent = originalText;
          btn.disabled = false;
          btn.style.opacity = '';
          form.reset();
        }, 3000);
      }
    });
  }

  /* ---------- Smooth Scroll ---------- */
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      var targetId = this.getAttribute('href');
      if (targetId === '#') return;
      var target = document.querySelector(targetId);
      if (target) {
        e.preventDefault();
        var headerHeight = header ? header.offsetHeight : 0;
        var targetPosition = target.getBoundingClientRect().top + window.scrollY - headerHeight - 20;
        window.scrollTo({ top: targetPosition, behavior: 'smooth' });
      }
    });
  });

})();
