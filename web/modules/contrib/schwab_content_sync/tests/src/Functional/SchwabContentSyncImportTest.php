<?php

namespace Drupal\Tests\schwab_content_sync\Functional;

/**
 * Test different import functionalities.
 *
 * @group schwab_content_sync
 */
class SchwabContentSyncImportTest extends SchwabContentSyncBrowserTestBase {

  /**
   * Test import of a very simple basic page.
   */
  public function testSingleImport() {
    // First check if there's no content.
    $this->drupalGet('admin/content');
    $this->assertSession()->pageTextContains('There are no content items yet.');

    // We import a very simple basic page.
    $this->importFile('basic-page');

    // We check if the node is created.
    $this->drupalGet('admin/content');
    $this->assertSession()->pageTextContains('Llama');
  }

}
