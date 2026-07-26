export const initMetricsCounters = () => {
  const counters = document.querySelectorAll('[data-stat-counter]');
  if (!counters.length) return;

  const observer = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        const el = entry.target;
        const target = parseInt(el.getAttribute('data-target-value') || '0', 10);
        const prefix = el.getAttribute('data-prefix') || '';
        const suffix = el.getAttribute('data-suffix') || '';

        if (target === 0) return;

        let count = 0;
        const duration = 1500; // ms
        const stepTime = Math.max(10, Math.floor(duration / target));

        const timer = setInterval(() => {
          count += Math.ceil(target / 50); // fast increment steps
          if (count >= target) {
            el.textContent = prefix + target + suffix;
            clearInterval(timer);
          } else {
            el.textContent = prefix + count + suffix;
          }
        }, stepTime);

        obs.unobserve(el);
      });
    },
    { threshold: 0.2 },
  );

  counters.forEach((counter) => observer.observe(counter));
};
