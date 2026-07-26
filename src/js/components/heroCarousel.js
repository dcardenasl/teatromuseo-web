const parseSlides = (root) => {
  try {
    const slides = JSON.parse(root.dataset.slides || '[]');
    return Array.isArray(slides) ? slides : [];
  } catch {
    return [];
  }
};

const initHeroCarousel = (root) => {
  const slides = parseSlides(root);
  if (!slides.length) return;

  const image = root.querySelector('[data-hero-image]');
  const link = root.querySelector('[data-hero-link]');
  const captionTitles = Array.from(root.querySelectorAll('[data-hero-caption-title]'));
  const captionSubtitles = Array.from(root.querySelectorAll('[data-hero-caption-subtitle]'));
  const captionCtas = Array.from(root.querySelectorAll('[data-hero-caption-cta]'));
  const prev = root.querySelector('[data-hero-prev]');
  const next = root.querySelector('[data-hero-next]');
  const dots = Array.from(root.querySelectorAll('[data-hero-dot]'));
  const autoplayEnabled = root.dataset.autoplay !== '0';
  const slideDuration = Math.max(1000, Number(root.dataset.interval || 6000));
  const hoverTarget = image || root;
  const overlay = root.querySelector('[data-hero-overlay]');
  const captionCard = root.querySelector('[data-hero-caption-card]');
  const transitionClassNames = {
    fade: 'hero-carousel-image--fade',
    slide: 'hero-carousel-image--slide',
    zoom: 'hero-carousel-image--zoom',
  };
  const transitionClassList = Object.values(transitionClassNames);
  let hasRendered = false;

  // The button is the touch target (min 24x24 for a11y); the visual pill is a
  // nested span so it can stay small while the button's hit area stays large.
  const dotVisuals = dots.map((dot) => dot.querySelector('[data-hero-dot-visual]') || dot);

  const dotFills = dots.map((dot, dotIndex) => {
    let fill = dot.querySelector('[data-hero-dot-fill]');
    if (!fill) {
      fill = document.createElement('span');
      fill.setAttribute('data-hero-dot-fill', '');
      fill.className = 'block h-full w-full bg-slate-900';
      dotVisuals[dotIndex].appendChild(fill);
    }
    return fill;
  });

  let current = 0;
  let timer = null;
  let progressTimer = null;
  let startedAt = 0;
  let remainingMs = slideDuration;
  let paused = false;

  const clearProgress = () => {
    dotFills.forEach((fill) => {
      fill.style.transform = 'scaleX(0)';
    });
  };

  const setActiveDot = () => {
    dots.forEach((dot, dotIndex) => {
      const active = dotIndex === current;
      const visual = dotVisuals[dotIndex];
      visual.classList.toggle('bg-slate-100', active);
      visual.classList.toggle('bg-slate-200', !active);
      visual.style.width = active ? '1rem' : '0.5rem';
      visual.style.height = '0.5rem';
      dot.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  };

  const updateProgress = () => {
    const activeFill = dotFills[current];
    if (!activeFill || !startedAt) return;

    const elapsed = Math.min(slideDuration, Math.max(0, Date.now() - startedAt));
    const ratio = slideDuration > 0 ? elapsed / slideDuration : 1;
    activeFill.style.transform = `scaleX(${Math.max(0, Math.min(1, ratio))})`;
  };

  const stopProgress = () => {
    if (!autoplayEnabled || paused) return;
    const elapsed = Math.max(0, Date.now() - startedAt);
    remainingMs = Math.max(0, slideDuration - elapsed);
    window.clearTimeout(timer);
    timer = null;
    if (progressTimer) {
      window.clearInterval(progressTimer);
      progressTimer = null;
    }
    updateProgress();
    paused = true;
  };

  const scheduleNext = (delayMs = slideDuration) => {
    if (slides.length < 2 || !autoplayEnabled) return;

    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
      current = (current + 1) % slides.length;
      remainingMs = slideDuration;
      paused = false;
      render();
      clearProgress();
      startedAt = Date.now();
      updateProgress();
      scheduleNext(slideDuration);
    }, delayMs);
    startedAt = Date.now();
    remainingMs = delayMs;
  };

  const resumeProgress = () => {
    if (!autoplayEnabled || !paused || !slides.length) return;
    paused = false;
    startedAt = Date.now() - (slideDuration - remainingMs);
    if (progressTimer) {
      window.clearInterval(progressTimer);
    }
    progressTimer = window.setInterval(updateProgress, 50);
    updateProgress();
    scheduleNext(remainingMs);
  };

  const stop = () => {
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }
    if (progressTimer) {
      window.clearInterval(progressTimer);
      progressTimer = null;
    }
    paused = false;
  };

  const start = () => {
    if (slides.length < 2 || !autoplayEnabled) {
      clearProgress();
      setActiveDot();
      return;
    }

    stop();
    paused = false;
    remainingMs = slideDuration;
    clearProgress();
    startedAt = Date.now();
    progressTimer = window.setInterval(updateProgress, 50);
    updateProgress();
    scheduleNext(slideDuration);
  };

  const render = () => {
    const slide = slides[current];
    if (!slide) return;

    if (image) {
      const imageUrl = slide.image?.url || slide.image?.external_url || '';
      const shouldAnimate = hasRendered && image.getAttribute('src') !== imageUrl;

      // A carousel reuses one <img> node. Any responsive candidates rendered
      // for the first slide would otherwise keep winning source selection after
      // src changes, leaving the browser on the first image forever.
      image.removeAttribute('srcset');
      image.removeAttribute('sizes');
      image.src = imageUrl;
      image.alt = slide.image_alt_text || slide.heading || '';

      transitionClassList.forEach((className) => image.classList.remove(className));
      const transitionClass = transitionClassNames[root.dataset.transition];
      if (shouldAnimate && transitionClass) {
        // Restart the keyframe when a user changes slides repeatedly.
        void image.offsetWidth;
        image.classList.add(transitionClass);
      }
    }
    if (link) {
      link.href = slide.cta_url || '#';
      link.setAttribute('aria-label', slide.heading || '');
    }
    captionTitles.forEach((node) => {
      node.textContent = slide.heading || '';
      node.hidden = !slide.heading;
    });
    captionSubtitles.forEach((node) => {
      node.textContent = slide.subtitle || '';
      node.hidden = !slide.subtitle;
    });
    captionCtas.forEach((node) => {
      node.textContent = slide.cta_label || '';
      node.hidden = !slide.cta_label;
    });

    if (overlay) {
      if (slide.overlay_color) {
        overlay.style.background = slide.overlay_color;
      } else {
        const overlayOpacity = root.dataset.overlayPct || '0';
        overlay.style.background = `linear-gradient(to bottom, rgba(15, 23, 42, ${overlayOpacity / 100}) 0%, rgba(15, 23, 42, 0) 42%, rgba(15, 23, 42, ${overlayOpacity / 100}) 100%)`;
      }
    }
    if (captionCard || captionTitles.length) {
      // slide.text_color is authored for the OVERLAY caption, which always sits on a
      // dark `bg-slate-950/65` card — white is safe there. The BELOW caption instead
      // sits directly on the plain page background, so it must never reuse that
      // overlay-tuned color: it always uses the same dark tone as the rest of the
      // page's below-hero copy, regardless of what the slide configured.
      const captionPosition = root.dataset.captionPosition || 'below';
      const isOverlayCaption = captionPosition.startsWith('overlay');
      const resolvedTextColor = isOverlayCaption
        ? (slide.text_color || '#ffffff')
        : 'rgb(15, 23, 42)';
      // Set color on the heading itself too, not just the wrapping card: the global
      // `h1, h2, h3, h4, h5, h6` base rule targets <h2> directly, which always wins
      // over a color merely inherited from the parent.
      if (captionCard) captionCard.style.color = resolvedTextColor;
      captionTitles.forEach((node) => {
        node.style.color = resolvedTextColor;
      });
    }

    setActiveDot();
    hasRendered = true;
  };

  const goToSlide = (index) => {
    current = index;
    remainingMs = slideDuration;
    paused = false;
    render();
    start();
  };

  if (prev) {
    prev.addEventListener('click', () => {
      goToSlide((current - 1 + slides.length) % slides.length);
    });
  }

  if (next) {
    next.addEventListener('click', () => {
      goToSlide((current + 1) % slides.length);
    });
  }

  dots.forEach((dot, dotIndex) => {
    dot.addEventListener('click', () => {
      goToSlide(dotIndex);
    });
  });

  if (hoverTarget) {
    hoverTarget.addEventListener('mouseenter', stopProgress, { passive: true });
    hoverTarget.addEventListener('mouseleave', resumeProgress, { passive: true });
  }

  start();
  render();
  clearProgress();
  updateProgress();
};

export const initHeroCarousels = () => {
  document.querySelectorAll('[data-hero-carousel]').forEach(initHeroCarousel);
};
