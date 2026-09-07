const header = document.querySelector('.site-header');
const progress = document.querySelector('.scroll-progress');
const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#site-nav');

function onScroll() {
  const y = window.scrollY;
  if (header) header.classList.toggle('scrolled', y > 10);
  if (progress) {
    const height = document.documentElement.scrollHeight - window.innerHeight;
    const value = height > 0 ? Math.min(100, Math.max(0, (y / height) * 100)) : 0;
    progress.style.setProperty('--scroll-progress', value.toFixed(2));
  }
}

onScroll();
window.addEventListener('scroll', onScroll, { passive: true });

if (toggle && nav) {
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!open));
    nav.classList.toggle('open', !open);
    document.body.classList.toggle('menu-open', !open);
  });
  nav.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
    toggle.setAttribute('aria-expanded', 'false');
    nav.classList.remove('open');
    document.body.classList.remove('menu-open');
  }));
}

const revealItems = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.08 });
  revealItems.forEach((item) => observer.observe(item));
} else {
  revealItems.forEach((item) => item.classList.add('visible'));
}
