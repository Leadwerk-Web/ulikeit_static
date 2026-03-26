/* ============================================================
   U-like-it Version 3 — Script
   Mode entdecken. Stadt beleben.
   ============================================================ */

(function () {
  'use strict';

  /* ---------- Scroll Reveal (Intersection Observer) ---------- */
  const revealElements = document.querySelectorAll('.reveal');

  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            revealObserver.unobserve(entry.target);
          }
        });
      },
      {
        rootMargin: '-60px 0px',
        threshold: 0.1,
      }
    );

    revealElements.forEach(function (el) {
      revealObserver.observe(el);
    });
  } else {
    revealElements.forEach(function (el) {
      el.classList.add('visible');
    });
  }

  /* ---------- Hero Typewriter (Aufbau + Wechsel, nur zweiter Teil) ---------- */
  (function () {
    var textEl = document.getElementById('hero-typewriter-text');
    var cursorEl = document.getElementById('hero-typewriter-cursor');
    if (!textEl) return;

    var phrases = [
      'Deal für Deal.',
      'Dein Deal um die Ecke.',
      'Schnapp\'s dir.'
    ];
    var typeDelay = 90;
    var pauseAfterType = 2200;
    var pauseBeforeNext = 800;
    var phraseIndex = 0;
    var typewriterTimer = null;

    function showCursor(show) {
      if (cursorEl) cursorEl.style.opacity = show ? '1' : '0';
    }

    function typePhrase() {
      var phrase = phrases[phraseIndex];
      var i = 0;
      textEl.textContent = '';
      showCursor(true);

      function typeNext() {
        if (i < phrase.length) {
          textEl.textContent += phrase.charAt(i);
          i += 1;
          typewriterTimer = setTimeout(typeNext, typeDelay);
        } else {
          showCursor(false);
          phraseIndex = (phraseIndex + 1) % phrases.length;
          typewriterTimer = setTimeout(function () {
            typewriterTimer = setTimeout(typePhrase, pauseBeforeNext);
          }, pauseAfterType);
        }
      }
      typeNext();
    }

    typewriterTimer = setTimeout(typePhrase, 400);
  })();

  /* ---------- Header Scroll Behavior ---------- */
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
      requestAnimationFrame(function () {
        handleHeaderScroll();
        headerTicking = false;
      });
      headerTicking = true;
    }
  });

  handleHeaderScroll();

  /* ---------- Mobile Navigation ---------- */
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

  /* ---------- Tab Switching (How it works) ---------- */
  var tabBtns = document.querySelectorAll('.tab-btn');
  var tabPanels = document.querySelectorAll('.tab-panel');

  tabBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('aria-controls');

      tabBtns.forEach(function (b) {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });

      tabPanels.forEach(function (p) {
        p.classList.remove('active');
      });

      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');

      var targetPanel = document.getElementById(targetId);
      if (targetPanel) {
        targetPanel.classList.add('active');
      }
    });
  });

  /* ---------- Parallax Backgrounds ---------- */
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
        requestAnimationFrame(function () {
          updateParallax();
          parallaxTicking = false;
        });
        parallaxTicking = true;
      }
    });
    window.addEventListener('resize', updateParallax);
    updateParallax();
  }

  /* ---------- Scroll Progress Bar ---------- */
  var scrollProgress = document.getElementById('scroll-progress');
  if (scrollProgress) {
    function updateScrollProgress() {
      var scrollTop = window.scrollY;
      var docHeight = document.documentElement.scrollHeight - window.innerHeight;
      var progress = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
      scrollProgress.style.width = progress + '%';
      scrollProgress.classList.toggle('is-scrolled', scrollTop > 0);
    }
    window.addEventListener('scroll', function () {
      requestAnimationFrame(updateScrollProgress);
    });
    updateScrollProgress();
  }

  /* ---------- Hero Scroll Effect (Scale / Parallax beim Scrollen) ---------- */
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
    window.addEventListener('scroll', function () {
      requestAnimationFrame(updateHeroScroll);
    });
    updateHeroScroll();
  }

  /* ---------- Custom Cursor ---------- */
  var isTouchDevice =
    'ontouchstart' in window || navigator.maxTouchPoints > 0;

  if (!isTouchDevice) {
    var cursor = document.getElementById('custom-cursor');
    var cursorShadow = document.getElementById('custom-cursor-shadow');

    if (cursor && cursorShadow) {
      var mouseX = 0;
      var mouseY = 0;
      var shadowX = 0;
      var shadowY = 0;
      var idleTimer = null;

      document.body.classList.add('cursor-active');

      document.addEventListener('mousemove', function (e) {
        mouseX = e.clientX;
        mouseY = e.clientY;

        cursor.style.left = mouseX + 'px';
        cursor.style.top = mouseY + 'px';

        document.body.classList.remove('cursor-idle');

        clearTimeout(idleTimer);
        idleTimer = setTimeout(function () {
          document.body.classList.add('cursor-idle');
        }, 120);
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

  /* ---------- Smooth Scroll for Anchor Links ---------- */
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      var targetId = this.getAttribute('href');
      if (targetId === '#') return;

      var target = document.querySelector(targetId);
      if (target) {
        e.preventDefault();
        var headerHeight = header ? header.offsetHeight : 0;
        var targetPosition =
          target.getBoundingClientRect().top + window.scrollY - headerHeight - 20;

        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth',
        });
      }
    });
  });

  /* ---------- So funktioniert die App — Slider + Bildwechsel ---------- */
  var appStepsSection = document.getElementById('app-steps');
  if (appStepsSection) {
    var appSlides = appStepsSection.querySelectorAll('.app-step-slide');
    var appDots = appStepsSection.querySelectorAll('.app-steps-dot');
    var appPrev = document.getElementById('app-steps-prev');
    var appNext = document.getElementById('app-steps-next');
    var appImg1 = document.getElementById('app-step-img-1');
    var appCurrentIndex = 0;
    var appTotal = appSlides.length;

    function setAppStep(index) {
      appCurrentIndex = (index + appTotal) % appTotal;
      appSlides.forEach(function (slide, i) {
        slide.classList.toggle('active', i === appCurrentIndex);
      });
      appDots.forEach(function (dot, i) {
        dot.classList.toggle('active', i === appCurrentIndex);
        dot.setAttribute('aria-selected', i === appCurrentIndex);
      });
      var activeSlide = appSlides[appCurrentIndex];
      if (activeSlide && appImg1) {
        var src = activeSlide.getAttribute('data-img-top');
        if (src) appImg1.src = src;
      }
    }

    if (appPrev) appPrev.addEventListener('click', function () { setAppStep(appCurrentIndex - 1); });
    if (appNext) appNext.addEventListener('click', function () { setAppStep(appCurrentIndex + 1); });
    appDots.forEach(function (dot, i) {
      dot.addEventListener('click', function () { setAppStep(i); });
    });
    setAppStep(0);

    var appBadge = document.getElementById('app-steps-badge');
    if (appBadge) {
      var badgeParallaxStrength = 0.015;
      function onAppStepsMouseMove(e) {
        var rect = appStepsSection.getBoundingClientRect();
        var centerX = rect.left + rect.width / 2;
        var centerY = rect.top + rect.height / 2;
        var deltaX = e.clientX - centerX;
        var deltaY = e.clientY - centerY;
        var moveX = -deltaX * badgeParallaxStrength;
        var moveY = -deltaY * badgeParallaxStrength;
        appBadge.style.transform = 'translate(' + Math.round(moveX) + 'px, ' + Math.round(moveY) + 'px)';
      }
      appStepsSection.addEventListener('mousemove', onAppStepsMouseMove);
      appStepsSection.addEventListener('mouseleave', function () {
        appBadge.style.transform = 'translate(0, 0)';
      });
    }
  }

})();
