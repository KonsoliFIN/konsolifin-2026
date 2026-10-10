(function (Drupal) {
  Drupal.behaviors.konsolifinInterstitial = {
    attach: function (context, settings) {
      const modal = document.getElementById('konsolifin-interstitial-modal');
      const closeBtn = document.getElementById('konsolifin-interstitial-close');

      if (!modal || !closeBtn) {
        return;
      }

      // Check if we already showed it today
      const cookieName = 'konsolifin_interstitial_seen';

      function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
      }

      function setCookie(name, value, days) {
        let expires = "";
        if (days) {
          const date = new Date();
          date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
          expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "")  + expires + "; path=/";
      }

      if (!getCookie(cookieName)) {
        // Show modal
        modal.style.display = 'flex';

        // Close modal event
        closeBtn.addEventListener('click', function() {
          modal.style.display = 'none';
          // Stop iframe video by removing its src
          const iframe = modal.querySelector('iframe');
          if (iframe) {
            iframe.src = iframe.src;
          }
        });

        // Set cookie so it won't show again for 1 day
        setCookie(cookieName, '1', 1);
      }
    }
  };
})(Drupal);
