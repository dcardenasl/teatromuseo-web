const initVideoPlayer = (player) => {
  const overlay = player.querySelector('[data-poster-overlay]');
  if (!overlay) return; // No poster: iframe/video already rendered server-side.

  overlay.addEventListener('click', () => {
    const embedUrl = player.getAttribute('data-embed-url') || '';
    const isIframe = player.getAttribute('data-is-iframe') === '1';
    const nativeUrl = player.getAttribute('data-native-url') || '';
    const mute = player.getAttribute('data-mute') === '1';
    const loop = player.getAttribute('data-loop') === '1';

    let content = '';

    if (isIframe) {
      content = `<iframe src="${embedUrl}" class="w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
    } else {
      content = `<video src="${nativeUrl}" class="w-full h-full object-contain" controls autoplay ${mute ? 'muted' : ''} ${loop ? 'loop' : ''}></video>`;
    }

    // Animate overlay out, then inject video
    overlay.style.opacity = '0';
    setTimeout(() => {
      overlay.remove();
      player.innerHTML = content;
    }, 300);
  });
};

export const initVideoPlayers = () => {
  document.querySelectorAll('[data-video-player]').forEach(initVideoPlayer);
};
