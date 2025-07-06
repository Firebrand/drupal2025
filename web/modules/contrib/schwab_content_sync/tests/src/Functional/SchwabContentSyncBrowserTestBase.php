<?php

namespace Drupal\Tests\schwab_content_sync\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Browser test base class for Single content sync functional tests.
 *
 * @group schwab_content_sync
 */
abstract class SchwabContentSyncBrowserTestBase extends BrowserTestBase {

  use SchwabContentSyncImportContentTrait;

  /**
   * {@inheritdoc}
   */
  protected $profile = 'minimal';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stable9';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schwab_content_sync', 'node', 'path'];

  /**
   * A user with permissions to view and create content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->createContentType([
        'type' => 'page',
        'name' => 'Basic page',
      ]);
      $this->createContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    $this->adminUser = $this->createUser([
      'access content',
      'access administration pages',
      'create page content',
      'edit any page content',
      'delete any page content',
      'create article content',
      'edit any article content',
      'delete any article content',
      'access content overview',
      'import single content',
      'export single content',
      'export node content',
    ]);
    $this->drupalLogin($this->adminUser);

  }

}
