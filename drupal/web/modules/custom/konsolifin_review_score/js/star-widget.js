/**
 * @file
 * Interactive star widget behavior for the konsolifin_review_score module.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Clamps a value between min and max.
   */
  function clamp(min, max, value) {
    return Math.min(max, Math.max(min, value));
  }

  /**
   * Converts a position fraction (0–1) to a score (0–400).
   *
   * @param {number} fraction - Position fraction in [0, 1].
   * @returns {number} Score clamped to 0–400.
   */
  function positionToScore(fraction) {
    return clamp(0, 400, Math.round(fraction * 400));
  }

  /**
   * Adjusts the current score based on a keyboard key.
   *
   * @param {number} currentScore - Current score value (0–400).
   * @param {string} key - The key name from the keyboard event.
   * @returns {number} Adjusted score clamped to 0–400.
   */
  function adjustScore(currentScore, key) {
    var step = 16;
    switch (key) {
      case 'ArrowRight':
      case 'ArrowUp':
        return clamp(0, 400, currentScore + step);
      case 'ArrowLeft':
      case 'ArrowDown':
        return clamp(0, 400, currentScore - step);
      case 'Home':
        return 0;
      case 'End':
        return 400;
      default:
        return currentScore;
    }
  }

  /**
   * Updates the visual state of star elements based on a score.
   *
   * @param {HTMLElement} container - The star container element.
   * @param {number|null} score - The score value (0–400) or null for empty.
   */
  function updateStarVisuals(container, score) {
    var stars = container.querySelectorAll('.review-score-star');
    var fillFraction = (score !== null && score !== '') ? score / 400 : 0;

    for (var i = 0; i < stars.length; i++) {
      var starFill = Math.min(1, Math.max(0, fillFraction * 5 - i));

      // Remove existing state classes.
      stars[i].classList.remove('star--full', 'star--empty', 'star--partial');
      stars[i].removeAttribute('style');

      if (starFill >= 1.0) {
        stars[i].classList.add('star--full');
      }
      else if (starFill <= 0.0) {
        stars[i].classList.add('star--empty');
      }
      else {
        var pct = starFill * 100;
        stars[i].classList.add('star--partial');
        stars[i].style.background = 'linear-gradient(90deg, #f5a623 ' + pct + '%, #ccc ' + pct + '%)';
      }
    }
  }

  /**
   * Drupal behavior for the interactive star widget.
   */
  Drupal.behaviors.starWidget = {
    attach: function (context) {
      var widgets = once('star-widget', '.review-score-widget', context);

      widgets.forEach(function (container) {
        // Find the hidden input (sibling of the container).
        var wrapper = container.parentNode;
        var hiddenInput = wrapper.querySelector('input[type="hidden"]');
        var resetButton = wrapper.querySelector('.review-score-reset');

        // Track the committed score value.
        var committedScore = hiddenInput.value !== '' ? parseInt(hiddenInput.value, 10) : null;

        // Hover: update visuals based on mouse position.
        container.addEventListener('mousemove', function (e) {
          var rect = container.getBoundingClientRect();
          var fraction = (e.clientX - rect.left) / rect.width;
          fraction = Math.min(1, Math.max(0, fraction));
          var hoverScore = positionToScore(fraction);
          updateStarVisuals(container, hoverScore);
        });

        // Click: commit the score.
        container.addEventListener('click', function (e) {
          var rect = container.getBoundingClientRect();
          var fraction = (e.clientX - rect.left) / rect.width;
          fraction = Math.min(1, Math.max(0, fraction));
          var score = positionToScore(fraction);
          committedScore = score;
          hiddenInput.value = score;
          container.setAttribute('aria-valuenow', String(score));
          updateStarVisuals(container, score);
        });

        // Mouse leave: revert to committed score.
        container.addEventListener('mouseleave', function () {
          updateStarVisuals(container, committedScore);
        });

        // Keyboard: adjust score with arrow keys.
        container.addEventListener('keydown', function (e) {
          var handledKeys = ['ArrowRight', 'ArrowUp', 'ArrowLeft', 'ArrowDown', 'Home', 'End'];
          if (handledKeys.indexOf(e.key) === -1) {
            return;
          }
          e.preventDefault();

          var current = committedScore !== null ? committedScore : 0;
          var newScore = adjustScore(current, e.key);
          committedScore = newScore;
          hiddenInput.value = newScore;
          container.setAttribute('aria-valuenow', String(newScore));
          updateStarVisuals(container, newScore);
        });

        // Reset: clear the value.
        if (resetButton) {
          resetButton.addEventListener('click', function (e) {
            e.preventDefault();
            committedScore = null;
            hiddenInput.value = '';
            container.setAttribute('aria-valuenow', '');
            updateStarVisuals(container, null);
          });
        }
      });
    }
  };

  // Export pure functions for testing.
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { positionToScore: positionToScore, adjustScore: adjustScore };
  }

})(Drupal, once);
