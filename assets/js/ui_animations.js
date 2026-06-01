const body = document.body;
const darkToggle = document.getElementById('darkToggle');
const savedTheme = localStorage.getItem('jdm-theme');
const header = document.querySelector('#header');
const navmenu = document.querySelector('#navmenu');
const mobileNavToggleBtn = document.querySelector('.mobile-nav-toggle');
const scrollTop = document.querySelector('.scroll-top');
const preloader = document.querySelector('#preloader');

function toggleScrolled() {
  if (!header) return;
  window.scrollY > 100 ? body.classList.add('scrolled') : body.classList.remove('scrolled');
}

if (mobileNavToggleBtn && navmenu) {
  mobileNavToggleBtn.addEventListener('click', () => {
    navmenu.classList.toggle('mobile-nav-active');
    mobileNavToggleBtn.classList.toggle('bi-list');
    mobileNavToggleBtn.classList.toggle('bi-x');
  });
}

document.querySelectorAll('#navmenu a').forEach(navLink => {
  navLink.addEventListener('click', () => {
    if (navmenu && navmenu.classList.contains('mobile-nav-active')) {
      navmenu.classList.remove('mobile-nav-active');
      if (mobileNavToggleBtn) {
        mobileNavToggleBtn.classList.add('bi-list');
        mobileNavToggleBtn.classList.remove('bi-x');
      }
    }
  });
});

if (preloader) {
  window.addEventListener('load', () => {
    preloader.remove();
  });
}

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
