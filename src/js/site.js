/**
 * Public site entry point.
 *
 * Source of truth for all site JavaScript. The committed artifact
 * public/assets/js/site.js is generated from here with esbuild:
 *   npm run build:js
 */
import { initMobileDrawer } from './components/mobileDrawer.js';
import { initHeroCarousels } from './components/heroCarousel.js';
import { initCardsSliders } from './components/cardsSlider.js';
import { initMetricsCounters } from './components/metricsCounter.js';
import { initVideoPlayers } from './components/videoPlayer.js';
import { initCollectionFilters } from './components/collectionFilters.js';
import { initShareButtons } from './components/shareButtons.js';
import { initCollectionVideoPlayers } from './components/collectionVideoPlayers.js';
import { initImageFallbacks } from './components/imageFallbacks.js';
import { initPublicForms } from './components/publicForms.js';

const boot = () => {
  initMobileDrawer();
  initHeroCarousels();
  initCardsSliders();
  initMetricsCounters();
  initVideoPlayers();
  initCollectionFilters();
  initShareButtons();
  initCollectionVideoPlayers();
  initImageFallbacks();
  initPublicForms();
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
