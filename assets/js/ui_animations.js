const body = document.body;
const darkToggle = document.getElementById('darkToggle');
const savedTheme = localStorage.getItem('jdm-theme');
const header = document.querySelector('#header');
const scrollTop = document.querySelector('.scroll-top');
const preloader = document.querySelector('#preloader');

function toggleScrolled() {
  if (!header) return;
  window.scrollY > 100 ? body.classList.add('scrolled') : body.classList.remove('scrolled');
}

if (preloader) {
  window.addEventListener('load', () => {
    preloader.remove();
  });
}

window.addEventListener('load', () => {
  const menuButton = document.querySelector('.hamburger');
  const mobileMenu = document.querySelector('.mobile-nav');

  if (!menuButton || !mobileMenu) return;

  function toggleHamburgerMenu(e) {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    menuButton.classList.toggle('is-active');
    mobileMenu.classList.toggle('is-active');
    body.classList.toggle('mobile-menu-open');
  }

  if (window.PointerEvent) {
    menuButton.addEventListener('pointerup', (e) => {
      if (e.pointerType === 'touch' || e.pointerType === 'mouse') {
        e.preventDefault();
        toggleHamburgerMenu(e);
      }
    });
  } else {
    menuButton.addEventListener('click', toggleHamburgerMenu);
    menuButton.addEventListener('touchend', (e) => {
      e.preventDefault();
      toggleHamburgerMenu(e);
    });
  }

  mobileMenu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => {
      menuButton.classList.remove('is-active');
      mobileMenu.classList.remove('is-active');
      body.classList.remove('mobile-menu-open');
    });
  });
});

function toggleScrollTop() {
  if (!scrollTop) return;
  window.scrollY > 100 ? scrollTop.classList.add('active') : scrollTop.classList.remove('active');
}

if (scrollTop) {
  scrollTop.addEventListener('click', (e) => {
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

window.addEventListener('load', toggleScrolled);
window.addEventListener('scroll', toggleScrolled);
window.addEventListener('load', toggleScrollTop);
window.addEventListener('scroll', toggleScrollTop);

if (darkToggle) {
  if (savedTheme === 'dark') {
    body.classList.add('dark-mode');
    darkToggle.textContent = 'Light Mode';
  }
  darkToggle.addEventListener('click', () => {
    const isDark = body.classList.toggle('dark-mode');
    darkToggle.textContent = isDark ? 'Light Mode' : 'Dark Mode';
    localStorage.setItem('jdm-theme', isDark ? 'dark' : 'light');
  });
}

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const target = document.querySelector(this.hash);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
    }
  });
});
