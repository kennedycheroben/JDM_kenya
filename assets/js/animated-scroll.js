gsap.registerPlugin(ScrollTrigger);

/* ========== LENIS SMOOTH SCROLL ========== */
const isDashboard = document.body.classList.contains('dashboard-wrapper');
const asWrapper = isDashboard ? null : document.getElementById('smooth-wrapper');
const asContent = isDashboard ? null : document.getElementById('smooth-content');

let asLenis;

if (asWrapper && asContent) {
  asLenis = new Lenis({
    wrapper: asWrapper,
    content: asContent,
    duration: 1.2,
    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    orientation: 'vertical',
    smoothWheel: true,
    wheelMultiplier: 1,
    touchMultiplier: 2,
    infinite: false,
  });

  asLenis.on('scroll', ScrollTrigger.update);

  ScrollTrigger.scrollerProxy(asWrapper, {
    scrollTop(value) {
      if (arguments.length) {
        asLenis.scrollTo(value, { immediate: true });
      }
      return asLenis.scroll;
    },
    getBoundingClientRect() {
      return {
        top: 0,
        left: 0,
        width: window.innerWidth,
        height: window.innerHeight,
      };
    },
    pinType: 'transform',
  });

  gsap.ticker.add((time) => {
    asLenis.raf(time * 1000);
  });

  gsap.ticker.lagSmoothing(0);
}

/* ========== CUSTOM CURSOR ========== */
const asCursor = document.querySelector('#cursor');
const asCursorBlur = document.querySelector('#cursor-blur');

if (asCursor && asCursorBlur && window.innerWidth > 768) {
  document.addEventListener('mousemove', (e) => {
    asCursor.style.left = e.clientX + 'px';
    asCursor.style.top = e.clientY + 'px';
    asCursorBlur.style.left = e.clientX - 150 + 'px';
    asCursorBlur.style.top = e.clientY - 150 + 'px';
  });

  document.querySelectorAll('a, .btn, .as-btn-primary, .as-btn-secondary, .as-about-play-btn, button').forEach((el) => {
    el.addEventListener('mouseenter', () => {
      asCursor.style.transform = 'scale(2.5)';
      asCursor.style.backgroundColor = '#fff';
    });
    el.addEventListener('mouseleave', () => {
      asCursor.style.transform = 'scale(1)';
      asCursor.style.backgroundColor = '#4274D9';
    });
  });
}

/* ========== HERO ANIMATIONS ========== */
const asHeroTL = gsap.timeline({ defaults: { ease: 'power4.out' } });

asHeroTL
  .from('.as-hero-title .line .word', {
    y: 120,
    rotation: 5,
    opacity: 0,
    stagger: 0.12,
    duration: 1.2,
    scroller: asWrapper || window,
  })
  .from('.as-hero-subtitle', {
    y: 40,
    opacity: 0,
    duration: 0.8,
    scroller: asWrapper || window,
  }, '-=0.6')
  .from('.as-hero-cta a', {
    y: 30,
    opacity: 0,
    stagger: 0.15,
    duration: 0.6,
    scroller: asWrapper || window,
  }, '-=0.4')
  .from('.as-hero-image-wrapper img', {
    scale: 1.4,
    opacity: 0,
    duration: 1.5,
    ease: 'power3.out',
    scroller: asWrapper || window,
  }, '-=1.2')
  .from('.as-hero-accent', {
    opacity: 0,
    scale: 0.8,
    duration: 0.8,
    scroller: asWrapper || window,
  }, '-=0.8');

/* ========== SCROLL TRIGGER ANIMATIONS ========== */

/* Section headers */
document.querySelectorAll('.as-section-header').forEach((header) => {
  gsap.from(header, {
    scrollTrigger: {
      trigger: header,
      start: 'top 85%',
      scroller: asWrapper || window,
    },
    y: 60,
    duration: 1,
    ease: 'power3.out',
  });
});

/* Grid cards */
document.querySelectorAll('.as-grid-2, .as-grid-4').forEach((grid) => {
  gsap.from(grid.children, {
    scrollTrigger: {
      trigger: grid,
      start: 'top 80%',
      scroller: asWrapper || window,
    },
    y: 80,
    stagger: 0.12,
    duration: 0.9,
    ease: 'power3.out',
  });
});

/* About content */
const asAboutContent = document.querySelector('.as-about-content');
const asAboutImage = document.querySelector('.as-about-image-wrapper');

if (asAboutContent) {
  gsap.from(asAboutContent, {
    scrollTrigger: {
      trigger: asAboutContent.closest('.as-about-section') || asAboutContent,
      start: 'top 80%',
      scroller: asWrapper || window,
    },
    x: -80,
    duration: 1.2,
    ease: 'power3.out',
  });
}

if (asAboutImage) {
  gsap.from(asAboutImage, {
    scrollTrigger: {
      trigger: asAboutImage.closest('.as-about-section') || asAboutImage,
      start: 'top 80%',
      scroller: asWrapper || window,
    },
    x: 80,
    duration: 1.2,
    ease: 'power3.out',
  });
}

/* Stats */
document.querySelectorAll('.as-stat').forEach((stat) => {
  gsap.from(stat, {
    scrollTrigger: {
      trigger: stat.closest('.as-stats') || stat,
      start: 'top 85%',
      scroller: asWrapper || window,
    },
    y: 40,
    stagger: 0.15,
    duration: 0.8,
    ease: 'power2.out',
  });
});

/* ========== ANIMATED COUNTER ========== */
document.querySelectorAll('.as-stat-number').forEach((counter) => {
  const target = parseInt(counter.getAttribute('data-target'), 10) || 0;
  const countProxy = { value: 0 };

  gsap.to(countProxy, {
    value: target,
    duration: 2,
    ease: 'power2.out',
    scrollTrigger: {
      trigger: counter,
      start: 'top 90%',
      scroller: asWrapper || window,
      once: true,
    },
    onUpdate: () => {
      counter.textContent = Math.round(countProxy.value);
    },
    onComplete: () => {
      counter.textContent = target;
    },
  });
});

/* Service cards (legacy single-card support) */
document.querySelectorAll('.as-service-card:not(.as-grid-4 .as-service-card)').forEach((card, i) => {
  gsap.from(card, {
    scrollTrigger: {
      trigger: card.closest('.as-grid-4') || card,
      start: 'top 80%',
      scroller: asWrapper || window,
    },
    y: 60,
    stagger: 0.1,
    duration: 0.8,
    ease: 'power3.out',
  });
});

/* Contact section */
const asContactInner = document.querySelector('.as-contact-inner');
if (asContactInner) {
  gsap.from(asContactInner, {
    scrollTrigger: {
      trigger: asContactInner.closest('.as-contact-section') || asContactInner,
      start: 'top 85%',
      scroller: asWrapper || window,
    },
    y: 60,
    duration: 1,
    ease: 'power3.out',
  });
}

/* ========== PARALLAX ON SCROLL ========== */
document.querySelectorAll('.as-parallax-img').forEach((img) => {
  const parent = img.closest('[class*="as-hero"], [class*="as-about"]');
  if (!parent) return;

  gsap.to(img, {
    scrollTrigger: {
      trigger: parent,
      start: 'top top',
      end: 'bottom top',
      scrub: 1.5,
      scroller: asWrapper || window,
    },
    y: 80,
    ease: 'none',
  });
});

/* Project card image parallax */
document.querySelectorAll('.as-card-image img').forEach((img) => {
  gsap.to(img, {
    scrollTrigger: {
      trigger: img.closest('.as-card'),
      start: 'top bottom',
      end: 'bottom top',
      scrub: 1,
      scroller: asWrapper || window,
    },
    y: 40,
    ease: 'none',
  });
});
