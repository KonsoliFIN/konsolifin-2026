<?php

declare(strict_types=1);

// Feature: test-fixture-migration, Property 8: Placeholder Image Size Constraint

namespace Drupal\Tests\migrate_konsolifin_testdata\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Property test: placeholder image size constraint.
 *
 * For any image file in the module's `data/images/` directory, the file size
 * SHALL be less than 10KB.
 *
 * **Validates: Requirements 6.6**
 *
 * @group migrate_konsolifin_testdata
 */
class PlaceholderImageSizeTest extends TestCase {

  /**
   * Maximum allowed file size in bytes (10KB).
   */
  private const MAX_SIZE_BYTES = 10240;

  /**
   * The module's data/images directory path.
   */
  private static function imagesDir(): string {
    return dirname(__DIR__, 3) . '/data/images';
  }

  /**
   * Data provider that discovers all image files in data/images/.
   */
  public static function imageFilesProvider(): \Generator {
    $imagesDir = self::imagesDir();

    if (!is_dir($imagesDir)) {
      self::fail('The data/images/ directory does not exist: ' . $imagesDir);
    }

    $files = glob($imagesDir . '/*.{png,jpg,jpeg,gif,webp,svg}', GLOB_BRACE);

    if (empty($files)) {
      self::fail('No image files found in data/images/ directory.');
    }

    foreach ($files as $filePath) {
      $filename = basename($filePath);
      yield $filename => [$filePath, $filename];
    }
  }

  /**
   * Tests that each image file is less than 10KB.
   *
   * **Validates: Requirements 6.6**
   */
  #[DataProvider('imageFilesProvider')]
  public function testImageFileSizeIsUnder10Kb(string $filePath, string $filename): void {
    $this->assertFileExists($filePath, sprintf(
      'Image file "%s" does not exist.',
      $filename,
    ));

    $fileSize = filesize($filePath);
    $this->assertIsInt($fileSize, sprintf(
      'Could not determine file size of "%s".',
      $filename,
    ));

    $this->assertLessThan(self::MAX_SIZE_BYTES, $fileSize, sprintf(
      'Image file "%s" is %d bytes, which exceeds the 10KB (%d bytes) limit.',
      $filename,
      $fileSize,
      self::MAX_SIZE_BYTES,
    ));
  }

}
