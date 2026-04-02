/**
 * @file
 * Mobile navigation toggle for KonsoliFIN 2026.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.konsolifin2026Navigation = {
    attach(context) {
      once('mobile-nav', '.menu-toggle', context).forEach((toggle) => {
        const nav = document.querySelector('.primary-nav');
        const overlay = document.querySelector('.primary-nav__overlay');
        if (!nav) return;

        const open = () => {
          toggle.setAttribute('aria-expanded', 'true');
          nav.setAttribute('data-open', 'true');
          if (overlay) overlay.setAttribute('data-visible', 'true');
          document.body.style.overflow = 'hidden';
          // Focus first link in nav.
          const firstLink = nav.querySelector('a');
          if (firstLink) firstLink.focus();
        };

        const close = () => {
          toggle.setAttribute('aria-expanded', 'false');
          nav.setAttribute('data-open', 'false');
          if (overlay) overlay.setAttribute('data-visible', 'false');
          document.body.style.overflow = '';
          toggle.focus();
        };

        toggle.addEventListener('click', () => {
          const isOpen = toggle.getAttribute('aria-expanded') === 'true';
          isOpen ? close() : open();
        });

        if (overlay) {
          overlay.addEventListener('click', close);
        }

        // Close on Escape key.
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            close();
          }
        });

        // Close on resize to desktop.
        const mq = window.matchMedia('(min-width: 1024px)');
        mq.addEventListener('change', (e) => {
          if (e.matches) close();
        });
      });
    },
  };
})(Drupal, once);
