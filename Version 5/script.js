/* ============================================================
   U-like-it Version 3 — Script
   App herunterladen. Schnäppchen sichern.
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
    var wrapEl = document.querySelector('.hero-typewriter-wrap');
    if (!textEl) return;

    var phrases = [
      'App herunterladen. Schnäppchen sichern.',
      'Lokale Angebote in deiner Nähe.',
      'Vor Ort einlösen. Direkt sparen.'
    ];
    var fallbackPhrases = [
      'App herunterladen. Schnäppchen sichern.',
      'Lokale Angebote in deiner Nähe.',
      'Vor Ort einlösen. Direkt sparen.'
    ];
    phrases = fallbackPhrases.slice();
    if (wrapEl && wrapEl.getAttribute('data-words')) {
      phrases = wrapEl.getAttribute('data-words').split('|').map(function (phrase) {
        return phrase.trim();
      }).filter(Boolean);
    }
    if (!phrases.length) {
      phrases = fallbackPhrases;
    }
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
    if (!header) return;
    if (header.hasAttribute('data-force-scrolled-header') || document.getElementById('download-page')) {
      header.classList.add('scrolled');
      return;
    }
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
  var submenuItems = mainNav ? mainNav.querySelectorAll('.nav-item-has-submenu') : [];
  var submenuCloseDelay = 180;
  var navScrollPosition = 0;

  function clearSubmenuClose(item) {
    if (!item || !item._submenuCloseTimer) return;
    window.clearTimeout(item._submenuCloseTimer);
    item._submenuCloseTimer = null;
  }

  function scheduleSubmenuClose(item) {
    if (!item) return;
    clearSubmenuClose(item);
    item._submenuCloseTimer = window.setTimeout(function () {
      setSubmenuState(item, false);
      item._submenuCloseTimer = null;
    }, submenuCloseDelay);
  }

  function setSubmenuState(item, isOpen) {
    if (!item) return;
    clearSubmenuClose(item);
    item.classList.toggle('is-open', isOpen);

    var toggle = item.querySelector('.nav-submenu-toggle');
    var submenu = item.querySelector('.nav-submenu');
    if (toggle) {
      toggle.setAttribute('aria-expanded', String(isOpen));
    }

    if (submenu) {
      if (window.innerWidth <= 1023) {
        submenu.style.display = isOpen ? 'grid' : 'none';
        submenu.style.maxHeight = '';
      } else {
        submenu.style.display = '';
        submenu.style.maxHeight = '';
      }
    }
  }

  function closeSubmenus(exceptItem) {
    submenuItems.forEach(function (item) {
      if (item === exceptItem) {
        clearSubmenuClose(item);
        return;
      }
      setSubmenuState(item, false);
    });
  }

  function lockBodyScroll() {
    if (document.body.classList.contains('nav-open')) return;
    navScrollPosition = window.scrollY || document.documentElement.scrollTop || 0;
    document.body.classList.add('nav-open');
    document.documentElement.classList.add('nav-open');
  }

  function unlockBodyScroll(restoreScrollPosition) {
    var shouldRestoreScroll = restoreScrollPosition !== false;
    var restoreScroll = function () {
      window.scrollTo(0, navScrollPosition);
    };
    if (document.activeElement && typeof document.activeElement.blur === 'function') {
      document.activeElement.blur();
    }
    document.body.classList.remove('nav-open');
    document.documentElement.classList.remove('nav-open');
    if (!shouldRestoreScroll) return;
    restoreScroll();
    window.requestAnimationFrame(restoreScroll);
  }

  function openMainNav() {
    if (!navToggle || !mainNav) return;
    mainNav.classList.add('is-open');
    navToggle.classList.add('is-open');
    navToggle.setAttribute('aria-expanded', 'true');
    navToggle.setAttribute('aria-label', 'Menü schließen');
    closeSubmenus();
    lockBodyScroll();
    mainNav.scrollTop = 0;
  }

  function closeMainNav(restoreScrollPosition) {
    if (!navToggle || !mainNav) return;
    mainNav.classList.remove('is-open');
    navToggle.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Menü öffnen');
    closeSubmenus();
    unlockBodyScroll(restoreScrollPosition);
  }

  if (navToggle && mainNav) {
    navToggle.addEventListener('click', function () {
      if (mainNav.classList.contains('is-open')) {
        closeMainNav();
      } else {
        openMainNav();
      }
    });

    mainNav.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (
          link.hasAttribute('data-home-download-swap-trigger') ||
          link.hasAttribute('data-app-hero-nav-trigger') ||
          (link.getAttribute('href') || '').indexOf('#') !== -1
        ) {
          return;
        }
        closeMainNav();
      });
    });

    submenuItems.forEach(function (item) {
      var toggle = item.querySelector('.nav-submenu-toggle');
      if (!toggle) return;

      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var isOpen = toggle.getAttribute('aria-expanded') === 'true';
        if (!isOpen) {
          closeSubmenus(item);
        }
        setSubmenuState(item, !isOpen);
      });

      item.addEventListener('mouseenter', function () {
        if (window.innerWidth > 1023) {
          closeSubmenus(item);
          setSubmenuState(item, true);
        }
      });

      item.addEventListener('mouseleave', function () {
        if (window.innerWidth > 1023) {
          scheduleSubmenuClose(item);
        }
      });

      item.addEventListener('focusin', function () {
        if (window.innerWidth > 1023) {
          closeSubmenus(item);
          setSubmenuState(item, true);
        }
      });

      item.addEventListener('focusout', function (e) {
        if (window.innerWidth > 1023 && !item.contains(e.relatedTarget)) {
          setSubmenuState(item, false);
        }
      });
    });

    document.addEventListener('click', function (e) {
      if (!mainNav.contains(e.target) && !navToggle.contains(e.target)) {
        closeSubmenus();
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 1023) {
        closeMainNav();
      }
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

  /* ---------- Smooth Scroll for Same-Page Anchor Links ---------- */
  function getHomeHeroSwapRoot() {
    return document.querySelector('.hero .hero-cta[data-home-download-swap-root]');
  }

  function createStoreBadgeLink(href, imageSrc, label, width, height) {
    var link = document.createElement('a');
    var image = document.createElement('img');

    link.href = href;
    link.className = 'store-badge';
    link.setAttribute('aria-label', label);

    image.src = imageSrc;
    image.alt = label;
    image.width = width;
    image.height = height;

    link.appendChild(image);
    return link;
  }

  function swapHomeHeroToStoreBadges(options) {
    var settings = options || {};
    var heroCta = getHomeHeroSwapRoot();
    if (!heroCta) return null;

    var existingBadge = heroCta.querySelector('.store-badge');
    if (existingBadge) {
      if (settings.focusFirstBadge) {
        existingBadge.focus();
      }
      return existingBadge;
    }

    var appleUrl = heroCta.getAttribute('data-home-store-apple-url');
    var appleBadge = heroCta.getAttribute('data-home-store-apple-badge');
    var googleUrl = heroCta.getAttribute('data-home-store-google-url');
    var googleBadge = heroCta.getAttribute('data-home-store-google-badge');

    if (!appleUrl || !appleBadge || !googleUrl || !googleBadge) {
      return null;
    }

    var appleLink = createStoreBadgeLink(
      appleUrl,
      appleBadge,
      'Im App Store herunterladen',
      155,
      46
    );
    var googleLink = createStoreBadgeLink(
      googleUrl,
      googleBadge,
      'Bei Google Play herunterladen',
      155,
      46
    );

    heroCta.classList.add('user-hero-cta');
    heroCta.setAttribute('data-home-download-swap-state', 'swapped');
    heroCta.textContent = '';
    heroCta.appendChild(appleLink);
    heroCta.appendChild(googleLink);

    if (settings.focusFirstBadge) {
      appleLink.focus();
    }

    return appleLink;
  }

  function getHashTarget(hash) {
    if (!hash || hash === '#') return null;

    var targetId = hash.charAt(0) === '#' ? hash.slice(1) : hash;
    try {
      targetId = decodeURIComponent(targetId);
    } catch (error) {
      return null;
    }

    if (!targetId) return null;

    return document.getElementById(targetId);
  }

  function normalizeAnchorPath(pathname) {
    var normalizedPath = pathname.replace(/\/+$/, '') || '/';
    normalizedPath = normalizedPath.replace(/\/index\.html$/i, '') || '/';
    return normalizedPath;
  }

  function getSamePageAnchorData(anchor) {
    var href = anchor.getAttribute('href');
    if (!href || href === '#') return null;

    var url;
    try {
      url = new URL(anchor.href, window.location.href);
    } catch (error) {
      return null;
    }

    if (!url.hash || url.origin !== window.location.origin) {
      return null;
    }

    var currentPath = normalizeAnchorPath(window.location.pathname);
    var targetPath = normalizeAnchorPath(url.pathname);
    if (currentPath != targetPath) {
      return null;
    }

    var target = getHashTarget(url.hash);
    if (!target) return null;

    return {
      target: target,
      href: href
    };
  }

  function getAnchorDocumentTop(target) {
    var top = 0;
    var node = target;

    while (node) {
      top += node.offsetTop || 0;
      node = node.offsetParent;
    }

    return top;
  }

  function scrollToAnchorTarget(target, behavior) {
    if (!target) return;

    var headerHeight = header ? header.offsetHeight : 0;
    var targetPosition = Math.max(0, getAnchorDocumentTop(target) - headerHeight - 20);

    if (behavior === 'instant') {
      var htmlScrollBehavior = document.documentElement.style.scrollBehavior;
      var bodyScrollBehavior = document.body.style.scrollBehavior;
      var applyInstantScroll = function () {
        window.scrollTo(0, targetPosition);
        document.documentElement.scrollTop = targetPosition;
        document.body.scrollTop = targetPosition;
      };

      document.documentElement.style.scrollBehavior = 'auto';
      document.body.style.scrollBehavior = 'auto';
      applyInstantScroll();
      window.setTimeout(applyInstantScroll, 0);
      window.setTimeout(applyInstantScroll, 90);
      window.setTimeout(applyInstantScroll, 220);

      window.setTimeout(function () {
        document.documentElement.style.scrollBehavior = htmlScrollBehavior;
        document.body.style.scrollBehavior = bodyScrollBehavior;
      }, 260);
      return;
    }

    window.scrollTo({
      top: targetPosition,
      behavior: behavior || 'smooth'
    });
  }

  function scrollToCurrentHashIfPresent() {
    var initialHash = window.location.hash;
    var target = getHashTarget(window.location.hash);
    if (!target) return;

    var applyHashScroll = function () {
      if (window.location.hash !== initialHash) return;
      scrollToAnchorTarget(target, 'instant');
    };

    window.setTimeout(applyHashScroll, 0);
    window.setTimeout(applyHashScroll, 120);
    window.setTimeout(applyHashScroll, 360);
    window.addEventListener('load', function () {
      window.setTimeout(applyHashScroll, 0);
      window.setTimeout(applyHashScroll, 160);
      window.setTimeout(applyHashScroll, 420);
      window.setTimeout(applyHashScroll, 900);
    }, { once: true });
  }

  function isElementInViewport(target, topOffset) {
    if (!target) return false;

    var rect = target.getBoundingClientRect();
    var offset = topOffset || 0;

    return rect.bottom > offset && rect.top < window.innerHeight;
  }

  function scrollHomeHeroIntoViewIfNeeded(behavior) {
    var heroSection = document.getElementById('hero');
    if (!heroSection) return;

    var headerHeight = header ? header.offsetHeight : 0;
    if (isElementInViewport(heroSection, headerHeight + 20)) {
      return;
    }

    var targetPosition = Math.max(0, getAnchorDocumentTop(heroSection) - headerHeight - 20);
    if (behavior === 'instant') {
      var htmlScrollBehavior = document.documentElement.style.scrollBehavior;
      var bodyScrollBehavior = document.body.style.scrollBehavior;
      var applyInstantScroll = function () {
        window.scrollTo(0, targetPosition);
        document.documentElement.scrollTop = targetPosition;
        document.body.scrollTop = targetPosition;
      };

      document.documentElement.style.scrollBehavior = 'auto';
      document.body.style.scrollBehavior = 'auto';
      applyInstantScroll();
      window.setTimeout(applyInstantScroll, 0);
      window.setTimeout(applyInstantScroll, 80);
      window.setTimeout(applyInstantScroll, 180);

      window.setTimeout(function () {
        document.documentElement.style.scrollBehavior = htmlScrollBehavior;
        document.body.style.scrollBehavior = bodyScrollBehavior;
      }, 220);
      return;
    }

    window.scrollTo({
      top: targetPosition,
      behavior: behavior || 'smooth'
    });
  }

  function scheduleHomeHeroScrollAfterNavClose() {
    window.setTimeout(function () {
      scrollHomeHeroIntoViewIfNeeded('instant');
    }, 20);
  }

  function buildHomeHeroSwapRequestUrl(anchor) {
    var targetUrl;
    try {
      targetUrl = new URL(anchor.href, window.location.href);
    } catch (error) {
      return anchor.href;
    }

    targetUrl.hash = '';
    targetUrl.searchParams.set('show-app-stores', '1');
    return targetUrl.toString();
  }

  function applyHomeHeroSwapRequestFromUrl() {
    var currentUrl;
    try {
      currentUrl = new URL(window.location.href);
    } catch (error) {
      return;
    }

    if (currentUrl.searchParams.get('show-app-stores') !== '1') {
      return;
    }

    window.requestAnimationFrame(function () {
      swapHomeHeroToStoreBadges();
      scrollHomeHeroIntoViewIfNeeded('instant');

      currentUrl.searchParams.delete('show-app-stores');
      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState(
          null,
          '',
          currentUrl.pathname + currentUrl.search + currentUrl.hash
        );
      }
    });
  }

  applyHomeHeroSwapRequestFromUrl();
  scrollToCurrentHashIfPresent();

  document.querySelectorAll('a[href*="#"], a[data-home-download-swap-trigger], a[data-app-hero-nav-trigger]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      if (this.hasAttribute('data-home-download-swap-trigger')) {
        var homeDownloadAnchorData = getSamePageAnchorData(this);
        var heroSwapRoot = getHomeHeroSwapRoot();
        if (!homeDownloadAnchorData && !heroSwapRoot) {
          return;
        }
        var focusFirstBadge = !!(heroSwapRoot && heroSwapRoot.contains(this));
        var shouldReturnToHero = !!(!focusFirstBadge && this.closest('.site-header'));
        var shouldWaitForNavClose = !!(shouldReturnToHero && mainNav && mainNav.classList.contains('is-open'));
        e.preventDefault();
        closeMainNav(false);
        swapHomeHeroToStoreBadges({ focusFirstBadge: focusFirstBadge });
        if (homeDownloadAnchorData) {
          scrollToAnchorTarget(homeDownloadAnchorData.target);
          if (window.history && typeof window.history.replaceState === 'function') {
            window.history.replaceState(null, '', homeDownloadAnchorData.href);
          }
          return;
        }
        if (shouldReturnToHero) {
          if (shouldWaitForNavClose) {
            scheduleHomeHeroScrollAfterNavClose();
          } else {
            scrollHomeHeroIntoViewIfNeeded();
          }
        }
        return;
      }

      if (this.hasAttribute('data-app-hero-nav-trigger')) {
        var appHeroAnchorData = getSamePageAnchorData(this);
        if (!appHeroAnchorData) return;
        e.preventDefault();
        closeMainNav(false);
        swapHomeHeroToStoreBadges();
        scrollToAnchorTarget(appHeroAnchorData.target);
        if (window.history && typeof window.history.replaceState === 'function') {
          window.history.replaceState(null, '', appHeroAnchorData.href);
        }
        return;
      }

      var anchorData = getSamePageAnchorData(this);
      if (!anchorData) return;

      e.preventDefault();
      closeMainNav(false);
      scrollToAnchorTarget(anchorData.target);

      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState(null, '', anchorData.href);
      }
    });
  });

  document.querySelectorAll('[data-download-slider]').forEach(function (slider) {
    var track = slider.querySelector('.download-slider-track');
    var slides = slider.querySelectorAll('.download-slider-slide');
    var prev = slider.querySelector('[data-download-slider-prev]');
    var next = slider.querySelector('[data-download-slider-next]');
    var current = 0;
    var timer = null;
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!track || slides.length < 2) return;

    function setDownloadSlide(index) {
      current = (index + slides.length) % slides.length;
      track.style.transform = 'translate3d(-' + (current * 100) + '%, 0, 0)';
    }

    function stopDownloadAuto() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    function startDownloadAuto() {
      if (reduceMotion) return;
      stopDownloadAuto();
      timer = setInterval(function () {
        setDownloadSlide(current + 1);
      }, 3400);
    }

    if (prev) {
      prev.addEventListener('click', function () {
        setDownloadSlide(current - 1);
        startDownloadAuto();
      });
    }

    if (next) {
      next.addEventListener('click', function () {
        setDownloadSlide(current + 1);
        startDownloadAuto();
      });
    }

    slider.addEventListener('mouseenter', stopDownloadAuto);
    slider.addEventListener('mouseleave', startDownloadAuto);
    slider.addEventListener('focusin', stopDownloadAuto);
    slider.addEventListener('focusout', startDownloadAuto);

    setDownloadSlide(0);
    startDownloadAuto();
  });

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
        appImg1.setAttribute('data-active-step', String(appCurrentIndex));
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
