<?php

namespace Acquia\Blt\Tests\Blt;

use Acquia\Blt\Tests\BltProjectTestBase;

/**
 * Class AcsfTest.
 *
 * Verifies that acsf support has been initialized.
 */
class AcsfTest extends BltProjectTestBase {

  /**
   * Tests recipes:acsf:init command.
   */
  public function testAcsfInit() {
    $this->blt("recipes:acsf:init");
    $this->assertFileExists($this->sandboxInstance . '/docroot/modules/contrib/acsf');
    $this->assertFileExists($this->sandboxInstance . '/factory-hooks');
  }

}
