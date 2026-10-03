const mobileMenu = document.querySelector('.marketing-mobile-menu');

if (mobileMenu) {
  mobileMenu.addEventListener('click', (event) => {
    if (event.target.closest('a')) mobileMenu.open = false;
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && mobileMenu.open) {
      mobileMenu.open = false;
      mobileMenu.querySelector('summary').focus();
    }
  });

  document.addEventListener('click', (event) => {
    if (!mobileMenu.contains(event.target)) mobileMenu.open = false;
  });
}

document.getElementById('registration-errors')?.focus();

document.addEventListener('click', (event) => {
  const target = event.target.closest('[data-analytics-event]');
  const eventName = target?.dataset.analyticsEvent?.trim();

  if (!eventName || typeof window.plausible !== 'function') return;

  window.plausible(eventName, {
    props: {
      placement: target.dataset.analyticsPlacement || 'unspecified',
    },
  });
});

document.addEventListener('submit', (event) => {
  const form = event.target.closest('form[data-analytics-event]');
  const eventName = form?.dataset.analyticsEvent?.trim();

  if (!eventName || typeof window.plausible !== 'function') return;

  window.plausible(eventName, {
    props: {
      placement: form.dataset.analyticsPlacement || 'unspecified',
    },
  });
});
