/**
 * @file
 * Aligns sidebar to the summary info box on node pages.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.konsolifin2026SidebarAlign = {
    attach(context) {
      once('sidebar-align', '.region-sidebar', context).forEach((sidebar) => {
        const summaryInfo = document.querySelector('.node__summary-info');
        const heroContent = document.querySelector('.node__hero-content');
        if (!summaryInfo && !heroContent) return;

        const align = () => {
          // Only on desktop where sidebar is a grid column.
          if (window.innerWidth < 1024) {
            sidebar.style.marginTop = '';
            return;
          }

          // Find the target element to align with.
          const target = summaryInfo || heroContent;
          const sidebarParent = sidebar.closest('.layout-content-wrapper');
          if (!sidebarParent) return;

          const parentTop = sidebarParent.getBoundingClientRect().top + window.scrollY;
          const targetTop = target.getBoundingClientRect().top + window.scrollY;
          const offset = Math.max(0, targetTop - parentTop);

          sidebar.style.marginTop = offset + 'px';
        };

        align();

        const ro = new ResizeObserver(align);
        ro.observe(document.body);
      });
    },
  };
})(Drupal, once);
