// Feature: konsolifin-review-score, Property 5: Keyboard score adjustment
// **Validates: Requirements 4.7**

/**
 * Property test for adjustScore: for any currentScore in [0, 400] and any
 * key action, the result matches clamp(0, 400, currentScore ± 16) for arrows,
 * 0 for Home, 400 for End, and currentScore for unrecognized keys.
 */

// Mock Drupal and once globals before requiring star-widget.js.
global.Drupal = { behaviors: {} };
global.once = function () { return []; };

const { adjustScore } = require('../../js/star-widget');

/**
 * Reference implementation of clamp.
 */
function clamp(min, max, value) {
  return Math.min(max, Math.max(min, value));
}

const STEP = 16;
const INCREMENT_KEYS = ['ArrowRight', 'ArrowUp'];
const DECREMENT_KEYS = ['ArrowLeft', 'ArrowDown'];
const ALL_ARROW_KEYS = [...INCREMENT_KEYS, ...DECREMENT_KEYS];

describe('Property 5: Keyboard score adjustment', () => {
  // --- Boundary tests ---

  test('boundary: score 0 + ArrowDown stays 0', () => {
    expect(adjustScore(0, 'ArrowDown')).toBe(0);
  });

  test('boundary: score 0 + ArrowLeft stays 0', () => {
    expect(adjustScore(0, 'ArrowLeft')).toBe(0);
  });

  test('boundary: score 400 + ArrowUp stays 400', () => {
    expect(adjustScore(400, 'ArrowUp')).toBe(400);
  });

  test('boundary: score 400 + ArrowRight stays 400', () => {
    expect(adjustScore(400, 'ArrowRight')).toBe(400);
  });

  test('Home always returns 0', () => {
    expect(adjustScore(200, 'Home')).toBe(0);
    expect(adjustScore(0, 'Home')).toBe(0);
    expect(adjustScore(400, 'Home')).toBe(0);
  });

  test('End always returns 400', () => {
    expect(adjustScore(200, 'End')).toBe(400);
    expect(adjustScore(0, 'End')).toBe(400);
    expect(adjustScore(400, 'End')).toBe(400);
  });

  test('unrecognized key returns currentScore unchanged', () => {
    expect(adjustScore(150, 'Enter')).toBe(150);
    expect(adjustScore(0, 'Tab')).toBe(0);
    expect(adjustScore(400, 'Escape')).toBe(400);
  });

  // --- Property-based: 120+ random (currentScore, keyAction) pairs ---

  test('120 random increment key presses produce clamp(0, 400, score + 16)', () => {
    for (let i = 0; i < 120; i++) {
      const score = Math.floor(Math.random() * 401); // 0–400
      const key = INCREMENT_KEYS[Math.floor(Math.random() * INCREMENT_KEYS.length)];
      const expected = clamp(0, 400, score + STEP);
      expect(adjustScore(score, key)).toBe(expected);
    }
  });

  test('120 random decrement key presses produce clamp(0, 400, score - 16)', () => {
    for (let i = 0; i < 120; i++) {
      const score = Math.floor(Math.random() * 401); // 0–400
      const key = DECREMENT_KEYS[Math.floor(Math.random() * DECREMENT_KEYS.length)];
      const expected = clamp(0, 400, score - STEP);
      expect(adjustScore(score, key)).toBe(expected);
    }
  });

  test('120 random Home key presses always return 0', () => {
    for (let i = 0; i < 120; i++) {
      const score = Math.floor(Math.random() * 401);
      expect(adjustScore(score, 'Home')).toBe(0);
    }
  });

  test('120 random End key presses always return 400', () => {
    for (let i = 0; i < 120; i++) {
      const score = Math.floor(Math.random() * 401);
      expect(adjustScore(score, 'End')).toBe(400);
    }
  });
});
