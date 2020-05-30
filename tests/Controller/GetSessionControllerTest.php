<?php

namespace Drupal\commerce_pagseguro_v2\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the commerce_pagseguro_v2 module.
 */
class GetSessionControllerTest extends WebTestBase {


  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "commerce_pagseguro_v2 GetSessionController's controller functionality",
      'description' => 'Test Unit for module commerce_pagseguro_v2 and controller GetSessionController.',
      'group' => 'Other',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests commerce_pagseguro_v2 functionality.
   */
  public function testGetSessionController() {
    // Check that the basic functions of module commerce_pagseguro_v2.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
