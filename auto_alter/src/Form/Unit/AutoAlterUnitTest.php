<?php

namespace Drupal\Tests\auto_alter\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests the auto_alter module functions.
 *
 * @group auto_alter
 */
class AutoAlterUnitTest extends UnitTestCase {

  /**
   * The path to the test image.
   *
   * @var string
   */
  protected $testImagePath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->testImagePath = __DIR__ . '/testimage.jpg';
    
    // Include the module file using a relative path
    require_once dirname(__DIR__, 2) . '/auto_alter.module';
  }

  /**
   * Tests the encodeImageToDataURL function.
   */
  public function testEncodeImageToDataURL() {
    // Test with valid image
    $result = encodeImageToDataURL($this->testImagePath);
    $this->assertStringStartsWith('data:image/jpeg;base64,', $result);
    $this->assertTrue(base64_decode(substr($result, 23)) !== false);

    // Test with non-existent image
    $result = encodeImageToDataURL('/path/to/nonexistent/image.jpg');
    $this->assertEquals('Error: File not found.', $result);

    // Test with invalid file
    $result = encodeImageToDataURL(__FILE__); // Using the test file itself as an invalid image
    $this->assertStringStartsWith('data:text/x-php;base64,', $result);
  }

} 