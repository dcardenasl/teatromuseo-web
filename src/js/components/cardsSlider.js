const initCardsSlider = (slider) => {
  const container = slider.querySelector('.slides-container');
  if (!container) return;

  const slides = container.children;
  const totalSlides = slides.length;
  if (totalSlides <= 1) return;

  let currentIdx = 0;
  let timer = null;

  const autoplay = slider.getAttribute('data-autoplay') === '1';
  const interval = parseInt(slider.getAttribute('data-interval') || '5000', 10);
  const visibleCount = Math.max(1, parseInt(slider.getAttribute('data-visible-count') || '1', 10));
  const maxIndex = Math.max(0, totalSlides - visibleCount);

  const updateSlider = (newIndex) => {
    if (newIndex > maxIndex) {
      currentIdx = 0;
    } else if (newIndex < 0) {
      currentIdx = maxIndex;
    } else {
      currentIdx = newIndex;
    }
    container.style.transform = `translateX(-${currentIdx * (100 / visibleCount)}%)`;

    const dots = slider.querySelectorAll('[data-slider-dots] button');
    dots.forEach((dot, idx) => {
      if (idx === currentIdx) {
        dot.classList.add('bg-violet-600', 'w-6');
        dot.classList.remove('bg-slate-300');
      } else {
        dot.classList.remove('bg-violet-600', 'w-6');
        dot.classList.add('bg-slate-300');
      }
    });
  };

  const startAutoplay = () => {
    if (!autoplay) return;
    timer = setInterval(() => {
      updateSlider(currentIdx + 1);
    }, interval);
  };

  const resetAutoplay = () => {
    if (timer) clearInterval(timer);
    startAutoplay();
  };

  slider.querySelector('[data-slider-prev]')?.addEventListener('click', () => {
    resetAutoplay();
    updateSlider(currentIdx - 1);
  });

  slider.querySelector('[data-slider-next]')?.addEventListener('click', () => {
    resetAutoplay();
    updateSlider(currentIdx + 1);
  });

  slider.querySelectorAll('[data-dot]').forEach((dot) => {
    dot.addEventListener('click', () => {
      resetAutoplay();
      const idx = parseInt(dot.getAttribute('data-dot'), 10);
      updateSlider(idx);
    });
  });

  startAutoplay();
};

export const initCardsSliders = () => {
  document.querySelectorAll('[data-cards-slider]').forEach(initCardsSlider);
};
