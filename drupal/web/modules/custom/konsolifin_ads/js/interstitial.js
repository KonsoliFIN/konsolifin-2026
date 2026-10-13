/**
 * @file
 * Interstitial video modal behavior.
 */
(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.konsolifinInterstitial = {
    attach: function (context, settings) {
      const elements = once('konsolifin-interstitial', '#konsolifin-interstitial-modal', context);
      if (!elements.length) {
        return;
      }

      const modal = elements[0];
      const closeBtn = document.getElementById('konsolifin-interstitial-close');
      const unmuteBtn = document.getElementById('konsolifin-interstitial-unmute');
      const iframe = modal.querySelector('iframe');
      let isMuted = true;

      // Check if we already showed it within the interval
      const cookieName = 'konsolifin_interstitial_seen';

      function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
      }

      function setCookie(name, value, hours) {
        let expires = '';
        if (hours) {
          const date = new Date();
          date.setTime(date.getTime() + (hours * 60 * 60 * 1000));
          expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (value || '') + expires + '; path=/';
      }

      function closeModal() {
        modal.style.display = 'none';
        // Stop iframe video completely
        if (iframe) {
          iframe.src = '';
        }
      }

      if (!getCookie(cookieName)) {
        // Show modal
        modal.style.display = 'flex';

        // Load iframe source now that the modal is visible to trigger autoplay reliably
        if (iframe && iframe.dataset.src) {
          iframe.src = iframe.dataset.src;
        }

        // Unmute / Mute toggle event
        if (unmuteBtn) {
          unmuteBtn.addEventListener('click', function () {
            if (!iframe || !iframe.contentWindow) {
              return;
            }

            const icon = unmuteBtn.querySelector('.icon');
            const label = unmuteBtn.querySelector('.label');

            if (isMuted) {
              iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'unMute' }), '*');
              iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'setVolume', args: [100] }), '*');
              if (icon) icon.textContent = '🔊';
              if (label) label.textContent = 'Mykistä (Mute)';
              isMuted = false;
            } else {
              iframe.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'mute' }), '*');
              if (icon) icon.textContent = '🔇';
              if (label) label.textContent = 'Kytke äänet (Unmute)';
              isMuted = true;
            }
          });
        }

        // Close modal events
        if (closeBtn) {
          closeBtn.addEventListener('click', closeModal);
        }

        // Close when clicking outside content (on overlay backdrop)
        modal.addEventListener('click', function (e) {
          if (e.target === modal) {
            closeModal();
          }
        });

        // Close on ESC key
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && modal.style.display !== 'none') {
            closeModal();
          }
        });

        // Set cookie so it won't show again for configured interval
        const intervalHours = settings.konsolifin_ads && settings.konsolifin_ads.interstitial_interval !== undefined
          ? parseInt(settings.konsolifin_ads.interstitial_interval, 10)
          : 24;
        setCookie(cookieName, '1', intervalHours);
      }
    }
  };
})(Drupal, once);
