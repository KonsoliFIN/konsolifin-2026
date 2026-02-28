// Feature: konsolifin-review-score, Property 4: Click position to score mapping
// **Validates: Requirements 4.2, 4.3**

/**
 * Property test for positionToScore: for any fraction f in [0, 1],
 * positionToScore(f) === clamp(0, 400, Math.round(f * 400)).
 */

// Mock Drupal and once globals before requiring star-widget.js.
global.Drupal = { behaviors: {} };
global.once = function () { return []; };

const { positionToScore } = require('../../js/star-widget');

/**
 * Reference implementation of clamp.
 */
function clamp(min, max, value) {
  return Math.min(max, Math.max(min, value));
}

describe('Property 4: Click position to score mapping', () => {
  test('boundary: fraction 0 maps to score 0', () => {
    expect(positionToScore(0)).toBe(0);
  });

  test('boundary: fraction 0.5 maps to score 200', () => {
    expect(positionToScore(0.5)).toBe(200);
  });

  test('boundary: fraction 1.0 maps to score 400', () => {
    expect(positionToScore(1.0)).toBe(400);
  });

  test('edge case: negative fraction clamps to 0', () => {
    expect(positionToScore(-0.1)).toBe(0);
  });

  test('edge case: fraction > 1 clamps to 400', () => {
    expect(positionToScore(1.5)).toBe(400);
  });

  test('120 random fractions in [0, 1] map correctly', () => {
    for (let i = 0; i < 120; i++) {
      const f = Math.random();
      const expected = clamp(0, 400, Math.round(f * 400));
      const actual = positionToScore(f);
      expect(actual).toBe(expected);
    }
  });
});
