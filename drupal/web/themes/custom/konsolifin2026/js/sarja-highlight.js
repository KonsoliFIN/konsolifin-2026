/**
 * @file
 * Sarja highlight region enhancements.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.konsolifin2026SarjaHighlight = {
    attach(context) {
      // Horizontal scroll indicators for mobile sarja items.
      once('sarja-scroll', '.sarja-highlight__items', context).forEach((container) => {
        const region = container.closest('.region-sarja-highlight');
        if (!region) return;

        const updateScrollIndicator = () => {
          const { scrollLeft, scrollWidth, clientWidth } = container;
          const canScrollLeft = scrollLeft > 0;
          const canScrollRight = scrollLeft + clientWidth < scrollWidth - 1;
          region.classList.toggle('sarja--can-scroll-left', canScrollLeft);
          region.classList.toggle('sarja--can-scroll-right', canScrollRight);
        };

        container.addEventListener('scroll', updateScrollIndicator, { passive: true });
        updateScrollIndicator();

        // Re-check on resize.
        const ro = new ResizeObserver(updateScrollIndicator);
        ro.observe(container);
      });
    },
  };
})(Drupal, once);
