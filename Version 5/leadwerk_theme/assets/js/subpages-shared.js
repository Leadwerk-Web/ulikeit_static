/* ============================================================
   U-like-it - Shared Subpage Script
   Handles /fuer-nutzer/ and /fuer-haendler/
   ============================================================ */

(function () {
  'use strict';

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

  var header = document.getElementById('site-header');
  var scrollThreshold = 60;

  function handleHeaderScroll() {
    if (!header) return;

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

  function initShowcase(showcaseId, screenId, autoDelay) {
    var showcase = document.getElementById(showcaseId);
    if (!showcase) return;

    var tabs = showcase.querySelectorAll('.app-showcase-tab');
    var imgs = showcase.querySelectorAll('.app-showcase-img');
    var infos = showcase.querySelectorAll('.app-showcase-slide-info');
    var dots = showcase.querySelectorAll('.app-showcase-dot');
    var swipeArea = document.getElementById(screenId);
    var currentSlide = 0;
    var totalSlides = imgs.length;
    var autoTimer = null;

    if (!totalSlides) return;

    function setSlide(index) {
      currentSlide = ((index % totalSlides) + totalSlides) % totalSlides;

      tabs.forEach(function (tab, i) {
        tab.classList.toggle('active', i === currentSlide);
        tab.setAttribute('aria-selected', i === currentSlide ? 'true' : 'false');
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

    function startAuto() {
      autoTimer = setInterval(function () {
        setSlide(currentSlide + 1);
      }, autoDelay);
    }

    function resetAuto() {
      clearInterval(autoTimer);
      startAuto();
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

    if (swipeArea) {
      var startX = 0;
      var dragging = false;

      swipeArea.addEventListener(
        'touchstart',
        function (e) {
          startX = e.changedTouches[0].clientX;
        },
        { passive: true }
      );

      swipeArea.addEventListener(
        'touchend',
        function (e) {
          var diff = startX - e.changedTouches[0].clientX;
          if (Math.abs(diff) > 40) {
            setSlide(currentSlide + (diff > 0 ? 1 : -1));
            resetAuto();
          }
        },
        { passive: true }
      );

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

    setSlide(0);
    startAuto();
  }

  initShowcase('app-showcase', 'app-showcase-screen', 4500);
  initShowcase('haendler-showcase', 'haendler-showcase-screen', 5000);

  var locOverlay = document.getElementById('loc-modal-overlay');
  var locClose = document.getElementById('loc-modal-close');
  var locCityEl = document.getElementById('loc-modal-city');
  var locInput = document.getElementById('location-city-input');
  var locTags = document.querySelectorAll('.user-location-tag[data-city]');

  function openLocModal(city) {
    if (!locOverlay || !locCityEl) return;

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
        var value = locInput.value.trim();
        if (value) {
          openLocModal(value);
        }
      }
    });
  }

  locTags.forEach(function (tag) {
    tag.addEventListener('click', function () {
      var city = tag.getAttribute('data-city');
      if (city) {
        openLocModal(city);
      } else if (locInput) {
        locInput.focus();
      }
    });
  });

  if (locClose) {
    locClose.addEventListener('click', closeLocModal);
  }

  if (locOverlay) {
    locOverlay.addEventListener('click', function (e) {
      if (e.target === locOverlay) {
        closeLocModal();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && locOverlay.classList.contains('is-open')) {
        closeLocModal();
      }
    });
  }

  function getHaendlerFieldLabel(field) {
    var label = field.querySelector('legend.wpforms-field-label, label.wpforms-field-label');
    return label ? label.textContent.replace(/\*/g, '').trim() : '';
  }

  function setHaendlerFieldLabel(field, text) {
    var label = field.querySelector('legend.wpforms-field-label, label.wpforms-field-label');
    if (!label || !text) return;

    var required = label.querySelector('.wpforms-required-label');
    label.textContent = text + (required ? ' ' : '');
    if (required) {
      label.appendChild(required);
    }
  }

  function normalizeHaendlerFieldKey(field) {
    var labelText = getHaendlerFieldLabel(field).toLowerCase();
    if (field.classList.contains('wpforms-field-name') || labelText.indexOf('name') !== -1) return 'name';
    if (labelText.indexOf('branche') !== -1) return 'branche';
    if (labelText.indexOf('telefon') !== -1) return 'telefonnummer';
    if (labelText.indexOf('ort') !== -1) return 'ort';
    return labelText;
  }

  function getVisibleHaendlerControls(field) {
    return Array.prototype.slice.call(field.querySelectorAll('input, select, textarea')).filter(function (control) {
      return control.type !== 'hidden'
        && control.getAttribute('aria-hidden') !== 'true'
        && control.getAttribute('tabindex') !== '-1';
    });
  }

  function markHiddenHaendlerField(field) {
    if (getVisibleHaendlerControls(field).length > 0) return false;
    field.setAttribute('data-leadwerk-hidden', 'true');
    return true;
  }

  function promoteHaendlerFieldDescription(field, key) {
    var description = field.querySelector('.wpforms-field-description');
    var text = description ? description.textContent.trim() : '';
    var controls = getVisibleHaendlerControls(field);

    if (!controls.length) return;

    if (key === 'name' && controls.length >= 2) {
      var parts = text ? text.split(/\s+/) : [];
      var firstPlaceholder = parts.shift() || 'Max';
      var lastPlaceholder = parts.join(' ') || 'Mustermann';

      if (!controls[0].getAttribute('placeholder')) {
        controls[0].setAttribute('placeholder', firstPlaceholder);
      }

      if (!controls[1].getAttribute('placeholder')) {
        controls[1].setAttribute('placeholder', lastPlaceholder);
      }

      controls[0].setAttribute('autocomplete', 'given-name');
      controls[1].setAttribute('autocomplete', 'family-name');
      field.classList.add('is-placeholder-split');
      if (text) {
        field.classList.add('wpforms-description-promoted');
      }
      return;
    }

    var control = controls[0];
    if (!control) return;

    if (key === 'telefonnummer') {
      control.setAttribute('inputmode', 'tel');
      control.setAttribute('autocomplete', 'tel');
      try {
        control.type = 'tel';
      } catch (error) {
        control.setAttribute('type', 'tel');
      }
    }

    if (key === 'ort') {
      control.setAttribute('autocomplete', 'address-level2');
    }

    if (control.tagName === 'SELECT') {
      var option = control.querySelector('option.placeholder, option[value=""]');
      var placeholderText = text || 'Bitte wählen';
      if (option && (!option.textContent.trim() || /select/i.test(option.textContent))) {
        option.textContent = placeholderText;
      }
      field.classList.add('wpforms-description-promoted');
      return;
    }

    if (text && !control.getAttribute('placeholder')) {
      control.setAttribute('placeholder', text);
      field.classList.add('wpforms-description-promoted');
    }
  }

  function reorderHaendlerFields(form) {
    if (!form) return;

    var submitContainer = form.querySelector('.wpforms-submit-container');
    if (!submitContainer) return;

    var fields = Array.prototype.slice.call(form.children).filter(function (node) {
      return node.classList && node.classList.contains('wpforms-field') && node.getAttribute('data-leadwerk-hidden') !== 'true';
    });

    var order = {
      name: 0,
      branche: 1,
      ort: 2,
      telefonnummer: 3
    };

    fields
      .map(function (field, index) {
        return {
          field: field,
          index: index,
          order: Object.prototype.hasOwnProperty.call(order, normalizeHaendlerFieldKey(field)) ? order[normalizeHaendlerFieldKey(field)] : 99 + index
        };
      })
      .sort(function (a, b) {
        return a.order - b.order;
      })
      .forEach(function (entry) {
        form.insertBefore(entry.field, submitContainer);
      });
  }

  function enhanceHaendlerWpformsContainer(container) {
    if (!container || container.getAttribute('data-leadwerk-enhanced') === 'true') return;

    var form = container.querySelector('.wpforms-form');
    if (!form) return;

    container.setAttribute('data-leadwerk-enhanced', 'true');

    var fields = Array.prototype.slice.call(form.querySelectorAll('.wpforms-field'));
    fields.forEach(function (field) {
      if (markHiddenHaendlerField(field)) {
        return;
      }

      var key = normalizeHaendlerFieldKey(field);
      if (key === 'telefonnummer') {
        setHaendlerFieldLabel(field, 'Telefonnummer');
      }

      promoteHaendlerFieldDescription(field, key);
    });

    reorderHaendlerFields(form);
    form.classList.add('leadwerk-wpforms-enhanced');
  }

  function enhanceHaendlerWpforms() {
    document.querySelectorAll('.haendler-form-embed .wpforms-container').forEach(function (container) {
      enhanceHaendlerWpformsContainer(container);
    });
  }

  enhanceHaendlerWpforms();

  if ('MutationObserver' in window) {
    var wpformsObserver = new MutationObserver(function () {
      enhanceHaendlerWpforms();
    });

    wpformsObserver.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  function getHaendlerHeroStoreSwapRoot() {
    return document.querySelector('.hero .haendler-hero-cta[data-haendler-hero-store-root]');
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

  function swapHaendlerHeroToStoreBadges(options) {
    var settings = options || {};
    var heroCta = getHaendlerHeroStoreSwapRoot();
    if (!heroCta) return null;

    var existingBadge = heroCta.querySelector('.store-badge');
    if (existingBadge) {
      if (settings.focusFirstBadge) {
        existingBadge.focus();
      }
      return existingBadge;
    }

    var appleUrl = heroCta.getAttribute('data-haendler-store-apple-url');
    var appleBadge = heroCta.getAttribute('data-haendler-store-apple-badge');
    var googleUrl = heroCta.getAttribute('data-haendler-store-google-url');
    var googleBadge = heroCta.getAttribute('data-haendler-store-google-badge');

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
    heroCta.setAttribute('data-haendler-hero-store-state', 'swapped');
    heroCta.textContent = '';
    heroCta.appendChild(appleLink);
    heroCta.appendChild(googleLink);

    if (settings.focusFirstBadge) {
      appleLink.focus();
    }

    return appleLink;
  }

  /* ---------- Smooth Scroll for Same-Page Anchor Links ---------- */
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

  function scrollHeroIntoViewIfNeeded(behavior) {
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

  function scheduleHeroScrollAfterNavClose() {
    window.setTimeout(function () {
      scrollHeroIntoViewIfNeeded('instant');
    }, 20);
  }

  scrollToCurrentHashIfPresent();

  document.querySelectorAll('a[href*="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      if (this.hasAttribute('data-app-hero-nav-trigger')) {
        var appHeroAnchorData = getSamePageAnchorData(this);
        if (!appHeroAnchorData) return;
        var shouldWaitForNavClose = !!(mainNav && mainNav.classList.contains('is-open'));
        e.preventDefault();
        closeMainNav(false);
        swapHaendlerHeroToStoreBadges();
        scrollToAnchorTarget(appHeroAnchorData.target);
        if (window.history && typeof window.history.replaceState === 'function') {
          window.history.replaceState(null, '', appHeroAnchorData.href);
        }
        return;
      }

      if (this.hasAttribute('data-haendler-hero-store-trigger')) {
        var haendlerStoreAnchorData = getSamePageAnchorData(this);
        if (!haendlerStoreAnchorData) return;
        e.preventDefault();
        closeMainNav(false);
        swapHaendlerHeroToStoreBadges();
        scrollToAnchorTarget(haendlerStoreAnchorData.target);
        if (window.history && typeof window.history.replaceState === 'function') {
          window.history.replaceState(null, '', haendlerStoreAnchorData.href);
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

})();

