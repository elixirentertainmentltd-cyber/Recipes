const header = document.querySelector('.site-header');
const progress = document.querySelector('.scroll-progress');
const toggle = document.querySelector('.menu-toggle');
const nav = document.querySelector('#site-nav');
function onScroll() {
  if (!progress) return;
  const height = document.documentElement.scrollHeight - window.innerHeight;
  progress.style.setProperty('--scroll-progress', height > 0 ? Math.min(100, Math.max(0, window.scrollY / height * 100)).toFixed(2) : '0');
}
onScroll();
window.addEventListener('scroll', onScroll, { passive: true });
window.addEventListener('resize', onScroll);
if (toggle && nav) {
  document.documentElement.classList.add('js-nav');
  toggle.hidden = false;
  const mobile = window.matchMedia('(max-width: 800px)');
  function setOpen(open, focusToggle = false) {
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open && mobile.matches);
    if (focusToggle) toggle.focus();
  }
  toggle.addEventListener('click', () => setOpen(toggle.getAttribute('aria-expanded') !== 'true'));
  nav.addEventListener('click', (event) => { if (event.target.closest('a')) setOpen(false); });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && nav.classList.contains('open')) setOpen(false, true);
  });
  document.addEventListener('click', (event) => { if (!header.contains(event.target)) setOpen(false); });
  header.addEventListener('focusout', (event) => { if (!header.contains(event.relatedTarget)) setOpen(false); });
  mobile.addEventListener('change', () => setOpen(false));
}
const printButton = document.querySelector('.print-recipe');
if (printButton) {
  printButton.hidden = false;
  printButton.addEventListener('click', () => window.print());
}
