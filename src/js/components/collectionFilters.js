/**
 * Progressive AJAX filtering component for collections (portfolio, news, etc.)
 */
export const initCollectionFilters = () => {
  const container = document.querySelector('[data-ajax-listing]');
  if (!container) return;

  const updateContent = async (url) => {
    const grid = container.querySelector('[data-listing-grid]');
    const pagination = container.querySelector('[data-listing-pagination]');
    const countEl = container.querySelector('[data-listing-count]');
    
    // Reduce opacity of grid for loading state feedback
    if (grid) {
      grid.style.transition = 'opacity 0.2s ease-in-out';
      grid.style.opacity = '0.4';
    }
    
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error('Fetch request failed');
      const html = await response.text();
      
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      
      const newGrid = doc.querySelector('[data-listing-grid]');
      const newPagination = doc.querySelector('[data-listing-pagination]');
      const newCount = doc.querySelector('[data-listing-count]');
      
      // Swap grid cards content and layout classes
      if (newGrid && grid) {
        grid.innerHTML = newGrid.innerHTML;
        grid.className = newGrid.className;
      }
      
      // Swap pagination navigation
      if (pagination) {
        if (newPagination) {
          pagination.innerHTML = newPagination.innerHTML;
          pagination.style.display = '';
        } else {
          pagination.style.display = 'none';
        }
      }
      
      // Swap elements count label
      if (newCount && countEl) {
        countEl.innerHTML = newCount.innerHTML;
      }
      
      // Sincronizar el estado activo de píldoras y caja de búsqueda
      const pillsContainer = container.querySelector('form');
      const newPillsContainer = doc.querySelector('form');
      if (pillsContainer && newPillsContainer) {
        const pillsSelects = pillsContainer.querySelectorAll('[data-listing-pills]');
        const newPillsSelects = newPillsContainer.querySelectorAll('[data-listing-pills]');
        pillsSelects.forEach((el, index) => {
          if (newPillsSelects[index]) {
            el.innerHTML = newPillsSelects[index].innerHTML;
          }
        });
        
        const input = pillsContainer.querySelector('input[type="search"]');
        const newInput = newPillsContainer.querySelector('input[type="search"]');
        if (input && newInput) {
          input.value = newInput.value;
        }
      }
      
      // Smooth scroll to top of collection container
      const topOffset = container.getBoundingClientRect().top + window.scrollY - 80;
      window.scrollTo({ top: topOffset, behavior: 'smooth' });
      
      // Update history state
      history.pushState(null, '', url);
    } catch (err) {
      console.error('AJAX filtering failed, fallback redirect:', err);
      window.location.href = url;
    } finally {
      if (grid) {
        grid.style.opacity = '1';
      }
    }
  };

  // Intercept click on filter links
  container.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (!link) return;
    
    if (link.closest('[data-listing-pills]') || link.closest('[data-listing-pagination]')) {
      e.preventDefault();
      const url = link.getAttribute('href');
      if (url && url !== '#') {
        updateContent(url);
      }
    }
  });

  // Intercept form searches
  const form = container.querySelector('form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      
      const formData = new FormData(form);
      const params = new URLSearchParams();
      
      for (const [key, value] of formData.entries()) {
        if (value.trim()) {
          params.append(key, value.trim());
        }
      }
      
      const action = form.getAttribute('action') || window.location.pathname;
      const queryString = params.toString();
      const url = action + (queryString ? '?' + queryString : '');
      
      updateContent(url);
    });
  }

  // Handle browser back/forward navigation
  window.addEventListener('popstate', () => {
    updateContent(window.location.href);
  });
};
