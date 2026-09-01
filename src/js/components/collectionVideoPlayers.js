const ALLOWED_EMBED_HOSTS = new Set([
  'www.youtube-nocookie.com',
  'player.vimeo.com',
]);

export const isAllowedEmbedUrl = (value, baseUrl = globalThis.location?.href || 'https://teatromuseo.cl/') => {
  try {
    const url = new globalThis.URL(value, baseUrl);

    return url.protocol === 'https:' && ALLOWED_EMBED_HOSTS.has(url.hostname);
  } catch {
    return false;
  }
};

const createModal = (closeLabel, playerLabel) => {
  const modal = document.createElement('div');
  const titleId = `collection-video-title-${Date.now()}`;
  const shell = document.createElement('div');
  const header = document.createElement('div');
  const title = document.createElement('h2');
  const closeButton = document.createElement('button');
  const closeIcon = document.createElement('span');
  const container = document.createElement('div');

  modal.className = 'fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/95 p-4';
  modal.dataset.videoModal = '';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-hidden', 'true');
  modal.setAttribute('aria-labelledby', titleId);

  shell.className = 'relative w-full max-w-5xl';
  header.className = 'mb-3 flex items-center justify-between gap-4 text-white';
  title.id = titleId;
  title.dataset.videoModalTitle = '';
  title.className = 'min-w-0 truncate text-base font-bold';
  closeButton.type = 'button';
  closeButton.dataset.videoModalClose = '';
  closeButton.className = 'shrink-0 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-4 focus-visible:ring-white/40';
  closeButton.setAttribute('aria-label', closeLabel);
  closeIcon.className = 'block h-5 w-5 text-center text-xl leading-5';
  closeIcon.setAttribute('aria-hidden', 'true');
  closeIcon.textContent = '×';
  container.dataset.videoModalContainer = '';
  container.className = 'aspect-video overflow-hidden rounded-xl bg-black shadow-2xl';
  container.setAttribute('aria-label', playerLabel);

  closeButton.append(closeIcon);
  header.append(title, closeButton);
  shell.append(header, container);
  modal.append(shell);
  document.body.append(modal);

  return modal;
};

export const initCollectionVideoPlayers = () => {
  if (document.documentElement.dataset.collectionVideoPlayersInitialized === '1') return;

  document.documentElement.dataset.collectionVideoPlayersInitialized = '1';

  let modal = null;
  let lastFocused = null;
  let bodyLockOwned = false;

  const focusableElements = () => {
    if (!modal) return [];

    return Array.from(modal.querySelectorAll(
      'button, iframe, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])',
    ));
  };

  const close = () => {
    if (!modal) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    modal.querySelector('[data-video-modal-container]')?.replaceChildren();
    if (bodyLockOwned) document.body.classList.remove('overflow-hidden');
    bodyLockOwned = false;
    if (lastFocused?.isConnected) lastFocused.focus();
    lastFocused = null;
  };

  const open = (trigger, root) => {
    const embedUrl = trigger.dataset.videoEmbedUrl || '';
    if (!isAllowedEmbedUrl(embedUrl)) return;

    modal ??= createModal(
      root.dataset.videoCloseLabel || 'Close',
      root.dataset.videoPlayerLabel || 'Video player',
    );

    const title = modal.querySelector('[data-video-modal-title]');
    const container = modal.querySelector('[data-video-modal-container]');
    const closeButton = modal.querySelector('[data-video-modal-close]');
    if (!title || !container || !closeButton) return;

    lastFocused = trigger;
    title.textContent = trigger.dataset.videoTitle || '';

    const iframe = document.createElement('iframe');
    iframe.src = embedUrl;
    iframe.className = 'h-full w-full border-0';
    iframe.title = trigger.dataset.videoTitle || 'Video';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.loading = 'eager';
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    container.replaceChildren(iframe);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    if (!document.body.classList.contains('overflow-hidden')) {
      document.body.classList.add('overflow-hidden');
      bodyLockOwned = true;
    }
    closeButton.focus();
  };

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!target || typeof target.closest !== 'function') return;

    const trigger = target.closest('[data-video-trigger]');
    if (trigger) {
      const root = trigger.closest('[data-video-listing]');
      if (root) open(trigger, root);
    }
    if (modal && target === modal) close();
    if (modal && target.closest('[data-video-modal-close]')) close();
  });

  document.addEventListener('keydown', (event) => {
    if (modal?.getAttribute('aria-hidden') === 'true') return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();

      return;
    }
    if (event.key !== 'Tab' || !modal) return;

    const focusable = focusableElements();
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (!first || !last) {
      event.preventDefault();

      return;
    }

    if (!modal.contains(document.activeElement)) {
      event.preventDefault();
      first.focus();

      return;
    }

    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  document.addEventListener('focusin', (event) => {
    if (modal?.getAttribute('aria-hidden') === 'true' || !modal) return;
    if (modal.contains(event.target)) return;

    focusableElements()[0]?.focus();
  });
};
