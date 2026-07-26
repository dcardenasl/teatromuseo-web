/**
 * Copy-link button for the article share bar. Social links are plain
 * anchors and need no JS; only the "copy link" action does.
 */
export const initShareButtons = () => {
  document.querySelectorAll('[data-share-buttons]').forEach((container) => {
    const copyButton = container.querySelector('[data-share-copy]');
    const copyLabelEl = container.querySelector('[data-share-copy-label]');
    if (!copyButton || !copyLabelEl) return;

    const url = container.dataset.shareUrl || window.location.href;
    const copyLabel = container.dataset.copyLabel || copyLabelEl.textContent;
    const copiedLabel = container.dataset.copiedLabel || copyLabel;

    let resetTimer;

    copyButton.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(url);
      } catch {
        window.prompt(copyLabel, url);
        return;
      }

      copyLabelEl.textContent = copiedLabel;
      clearTimeout(resetTimer);
      resetTimer = setTimeout(() => {
        copyLabelEl.textContent = copyLabel;
      }, 2000);
    });
  });
};
