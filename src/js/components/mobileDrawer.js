const setDrawerOpen = (toggleBtn, drawer, iconPath, open) => {
  if (!toggleBtn || !drawer) return;

  drawer.classList.toggle('opacity-0', !open);
  drawer.classList.toggle('pointer-events-none', !open);
  drawer.classList.toggle('translate-y-4', !open);
  drawer.classList.toggle('opacity-100', open);
  drawer.classList.toggle('pointer-events-auto', open);
  drawer.classList.toggle('translate-y-0', open);

  toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  if (iconPath) {
    iconPath.setAttribute('d', open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16');
  }

  document.body.style.overflow = open ? 'hidden' : '';
};

export const initMobileDrawer = () => {
  const toggleBtn = document.querySelector('[data-mobile-menu-toggle]');
  const drawer = document.querySelector('[data-mobile-drawer]');
  const iconPath = document.querySelector('[data-mobile-menu-icon]');

  if (!toggleBtn || !drawer) return;

  let isOpen = false;

  toggleBtn.addEventListener('click', () => {
    isOpen = !isOpen;
    setDrawerOpen(toggleBtn, drawer, iconPath, isOpen);
  });

  const submenuToggles = drawer.querySelectorAll('[data-submenu-toggle]');
  submenuToggles.forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const targetId = toggle.getAttribute('data-target');
      if (!targetId) return;

      const submenu = document.getElementById(targetId);
      const svg = toggle.querySelector('svg');
      if (!submenu) return;

      const willOpen = submenu.classList.contains('hidden');
      submenu.classList.toggle('hidden', !willOpen);
      toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      if (svg) {
        svg.classList.toggle('rotate-180', willOpen);
      }
    });
  });

  drawer.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', () => {
      if (!isOpen) return;
      isOpen = false;
      setDrawerOpen(toggleBtn, drawer, iconPath, false);
    });
  });
};
