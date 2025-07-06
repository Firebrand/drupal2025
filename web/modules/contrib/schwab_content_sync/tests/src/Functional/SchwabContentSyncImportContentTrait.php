<?php

namespace Drupal\Tests\schwab_content_sync\Functional;

/**
 * Define a trait with useful methods to use in tests.
 */
trait SchwabContentSyncImportContentTrait {

  /**
   * Imports a file.
   *
   * @param string $file_name
   *   The name of the file in the asset folder.
   */
  protected function importFile($file_name): void {
    $file_path = \Drupal::service('extension.list.module')->getPath('schwab_content_sync') . '/tests/assets/' . $file_name . '.yml';
    \Drupal::service('schwab_content_sync.importer')->importFromFile($file_path);
  }

}
