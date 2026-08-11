const handleImageError = (image) => {
  if (image.dataset.imageFallback === 'hide') {
    image.classList.add('image-load-hidden');
    return;
  }

  image.classList.add('image-load-failed');
  image.alt = image.dataset.imageFallbackAlt || image.alt;
};

export const initImageFallbacks = () => {
  document.querySelectorAll('img[data-image-fallback]').forEach((image) => {
    image.addEventListener('error', () => handleImageError(image));
  });
};
