/**
 * @file
 * Hero banner parallax — image scrolls at half speed.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.konsolifin2026HeroParallax = {
    attach(context) {
      once('hero-parallax', '.node__hero-banner--has-image', context).forEach((banner) => {
        const bg = banner.querySelector('.node__hero-bg');
        if (!bg) return;

        // Skip on reduced-motion preference.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        let ticking = false;

        const update = () => {
          const rect = banner.getBoundingClientRect();
          const bannerHeight = banner.offsetHeight;

          // Only apply when the banner is in or near the viewport.
          if (rect.bottom < 0 || rect.top > window.innerHeight) {
            ticking = false;
            return;
          }

          // scrollProgress: 0 when banner top is at viewport top, increases as you scroll down.
          // Translate the bg upward at half the scroll rate.
          const scrolled = -rect.top;
          const translate = Math.round(scrolled * 0.5);

          // Clamp so the image doesn't shift too far.
          const maxShift = bannerHeight * 0.3;
          const clamped = Math.max(0, Math.min(translate, maxShift));

          bg.style.transform = 'translate3d(0, ' + clamped + 'px, 0)';
          ticking = false;
        };

        const onScroll = () => {
          if (!ticking) {
            requestAnimationFrame(update);
            ticking = true;
          }
        };

        window.addEventListener('scroll', onScroll, { passive: true });
        update();
      });
    },
  };
})(Drupal, once);
