const createModal = (closeLabel, playerLabel) => {
  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/95 p-4';
  modal.dataset.videoModal = '';
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-hidden', 'true');
  modal.innerHTML = `
    <div class="relative w-full max-w-5xl">
      <div class="mb-3 flex items-center justify-between gap-4 text-white">
        <h2 data-video-modal-title class="min-w-0 truncate text-base font-bold"></h2>
        <button type="button" data-video-modal-close class="shrink-0 rounded-full bg-white/10 p-3 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-4 focus-visible:ring-white/40" aria-label="${closeLabel}">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
      </div>
      <div data-video-modal-container class="aspect-video overflow-hidden rounded-xl bg-black shadow-2xl">
        <span class="sr-only">${playerLabel}</span>
      </div>
    </div>`;
  document.body.appendChild(modal);
  return modal;
};

export const initCollectionVideoPlayers = () => {
  let modal = null;
  let lastFocused = null;

  const close = () => {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    modal.querySelector('[data-video-modal-container]')?.replaceChildren();
    document.body.classList.remove('overflow-hidden');
    lastFocused?.focus();
    lastFocused = null;
  };

  const open = (trigger, root) => {
    const embedUrl = trigger.dataset.videoEmbedUrl || '';
    if (!embedUrl) return;
    modal ??= createModal(root.dataset.videoCloseLabel || 'Close', root.dataset.videoPlayerLabel || 'Video player');
    const title = modal.querySelector('[data-video-modal-title]');
    const container = modal.querySelector('[data-video-modal-container]');
    const closeButton = modal.querySelector('[data-video-modal-close]');
    lastFocused = trigger;
    title.textContent = trigger.dataset.videoTitle || '';
    const iframe = document.createElement('iframe');
    iframe.src = embedUrl;
    iframe.className = 'h-full w-full border-0';
    iframe.title = trigger.dataset.videoTitle || 'Video';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    container.replaceChildren(iframe);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
    closeButton.focus();
  };

  document.addEventListener('click', (event) => {
    const trigger = event.target.closest?.('[data-video-trigger]');
    if (trigger) {
      const listing = trigger.closest('[data-ajax-listing]');
      const root = listing?.querySelector('[data-video-listing]');
      if (root) open(trigger, root);
    }
    if (modal && event.target === modal) close();
    if (modal && event.target.closest?.('[data-video-modal-close]')) close();
  });
  document.addEventListener('keydown', (event) => {
    if (modal?.getAttribute('aria-hidden') !== 'true' && event.key === 'Escape') close();
  });
};
