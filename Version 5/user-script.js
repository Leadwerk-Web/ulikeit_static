/* ============================================================
   U-like-it â€” User Subpage Script
   Reuses patterns from main script.js
   ============================================================ */

(function () {
  'use strict';

  /* ---------- Scroll Reveal (Intersection Observer) ---------- */
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

    revealElements.forEach(function (el) {
      revealObserver.observe(el);
    });
  } else {
    revealElements.forEach(function (el) {
      el.classList.add('visible');
    });
  }

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

  function unlockBodyScroll() {
    var restoreScroll = function () {
      window.scrollTo(0, navScrollPosition);
    };
    if (document.activeElement && typeof document.activeElement.blur === 'function') {
      document.activeElement.blur();
    }
    document.body.classList.remove('nav-open');
    document.documentElement.classList.remove('nav-open');
    restoreScroll();
    window.requestAnimationFrame(restoreScroll);
  }

  function openMainNav() {
    if (!navToggle || !mainNav) return;
    mainNav.classList.add('is-open');
    navToggle.classList.add('is-open');
    navToggle.setAttribute('aria-expanded', 'true');
    navToggle.setAttribute('aria-label', 'Menue schliessen');
    closeSubmenus();
    lockBodyScroll();
    mainNav.scrollTop = 0;
  }

  function closeMainNav() {
    if (!navToggle || !mainNav) return;
    mainNav.classList.remove('is-open');
    navToggle.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Menue oeffnen');
    closeSubmenus();
    unlockBodyScroll();
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
      link.addEventListener('click', closeMainNav);
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
    window.addEventListener('scroll', function () {
      requestAnimationFrame(updateHeroScroll);
    });
    updateHeroScroll();
  }

  /* ---------- Custom Cursor ---------- */
  var isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

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

  /* ---------- App Showcase (Tabs + Swipe) ---------- */
  var showcase = document.getElementById('app-showcase');
  if (showcase) {
    var tabs = showcase.querySelectorAll('.app-showcase-tab');
    var imgs = showcase.querySelectorAll('.app-showcase-img');
    var infos = showcase.querySelectorAll('.app-showcase-slide-info');
    var dots = showcase.querySelectorAll('.app-showcase-dot');
    var screen = document.getElementById('app-showcase-screen');
    var currentSlide = 0;
    var totalSlides = imgs.length;
    var autoTimer = null;

    function setSlide(index) {
      currentSlide = ((index % totalSlides) + totalSlides) % totalSlides;
      tabs.forEach(function (t, i) {
        t.classList.toggle('active', i === currentSlide);
        t.setAttribute('aria-selected', i === currentSlide);
      });
      imgs.forEach(function (img, i) {
        img.classList.toggle('active', i === currentSlide);
      });
      infos.forEach(function (info, i) {
        info.classList.toggle('active', i === currentSlide);
      });
      dots.forEach(function (dot, i) {
        dot.classList.toggle('active', i === currentSlide);
      });
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

    var swipeArea = document.getElementById('app-showcase-screen');
    if (swipeArea) {
      var startX = 0;
      var dragging = false;

      swipeArea.addEventListener('touchstart', function (e) {
        startX = e.changedTouches[0].clientX;
      }, { passive: true });

      swipeArea.addEventListener('touchend', function (e) {
        var diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) {
          setSlide(currentSlide + (diff > 0 ? 1 : -1));
          resetAuto();
        }
      }, { passive: true });

      swipeArea.addEventListener('mousedown', function (e) {
        startX = e.clientX;
        dragging = true;
        e.preventDefault();
      });

      document.addEventListener('mouseup', function (e) {
        if (!dragging) return;
        dragging = false;
        var diff = startX - e.clientX;
        if (Math.abs(diff) > 40) {
          setSlide(currentSlide + (diff > 0 ? 1 : -1));
          resetAuto();
        }
      });
    }

    function startAuto() {
      autoTimer = setInterval(function () {
        setSlide(currentSlide + 1);
      }, 4500);
    }

    function resetAuto() {
      clearInterval(autoTimer);
      startAuto();
    }

    startAuto();
  }

  /* ---------- Location Modal ---------- */
  var locOverlay = document.getElementById('loc-modal-overlay');
  var locClose = document.getElementById('loc-modal-close');
  var locCityEl = document.getElementById('loc-modal-city');
  var locInput = document.getElementById('location-city-input');
  var locTags = document.querySelectorAll('.user-location-tag[data-city]');

  function openLocModal(city) {
    if (!locOverlay) return;
    locCityEl.textContent = city || 'deiner Stadt';
    locOverlay.classList.add('is-open');
    locOverlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeLocModal() {
    if (!locOverlay) return;
    locOverlay.classList.remove('is-open');
    locOverlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  if (locInput) {
    locInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var val = locInput.value.trim();
        if (val) openLocModal(val);
      }
    });
  }

  locTags.forEach(function (tag) {
    tag.addEventListener('click', function () {
      var city = tag.getAttribute('data-city');
      if (city) {
        openLocModal(city);
      } else {
        if (locInput) locInput.focus();
      }
    });
  });

  if (locClose) {
    locClose.addEventListener('click', closeLocModal);
  }

  if (locOverlay) {
    locOverlay.addEventListener('click', function (e) {
      if (e.target === locOverlay) closeLocModal();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && locOverlay.classList.contains('is-open')) {
        closeLocModal();
      }
    });
  }

  /* ---------- Smooth Scroll for Same-Page Anchor Links ---------- */
  function getSamePageAnchorTarget(anchor) {
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

    var currentPath = window.location.pathname.replace(/\/+$/, '') || '/';
    var targetPath = url.pathname.replace(/\/+$/, '') || '/';
    if (currentPath != targetPath) {
      return null;
    }

    return document.querySelector(url.hash);
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

  document.querySelectorAll('a[href*="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      var target = getSamePageAnchorTarget(this);
      if (!target) return;

      e.preventDefault();
      closeMainNav();

      var headerHeight = header ? header.offsetHeight : 0;
      var targetPosition = Math.max(0, getAnchorDocumentTop(target) - headerHeight - 20);

      window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
      });

      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState(null, '', this.getAttribute('href'));
      }
    });
  });

})();

