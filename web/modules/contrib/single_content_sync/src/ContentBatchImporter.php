<?php

namespace Drupal\single_content_sync;

use Drupal\file\FileInterface;

/**
 * Define a batch importer.
 */
class ContentBatchImporter {

  /**
   * Get content importer service.
   *
   * @return \Drupal\single_content_sync\ContentImporterInterface
   *   The content importer.
   */
  public static function contentImporter(): ContentImporterInterface {
    return \Drupal::service('single_content_sync.importer');
  }

  /**
   * Import content operation.
   */
  public static function batchImportFile($original_file_path, &$context): void {
    try {
      $context['results'][] = self::contentImporter()->importFromFile($original_file_path);
    }
    catch (\Throwable $exception) {
      \Drupal::messenger()->addError($exception->getMessage());
    }
  }

  /**
   * Import assets operation.
   */
  public static function batchImportAssets(string $extracted_file_path, string $zip_file_path, &$context): void {
    self::contentImporter()->importAssets($extracted_file_path, $zip_file_path);
  }

  /**
   * Clean import directory after before finish batch.
   */
  public static function cleanImportDirectory(string $import_directory, &$context): void {
    \Drupal::service('file_system')->deleteRecursive($import_directory);
  }

  /**
   * Clean a temp file that was uploaded to import content from.
   *
   * @param int|string $file
   *   The file id or real file path.
   */
  public static function cleanUploadedFile(int|string $file): void {
    if (is_numeric($file)) {
      $file = \Drupal::entityTypeManager()->getStorage('file')->load($file);
    }
    elseif (file_exists($file)) {
      // Delete the file by its basename as it's always unique.
      $info = pathinfo($file);
      $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties([
        'filename' => $info['basename'],
      ]);
      $file = reset($files);
    }

    if ($file instanceof FileInterface) {
      try {
        $file->delete();
      }
      catch (\Exception $exception) {
        \Drupal::logger('single_content_sync')->error(sprintf('Could not delete temporary file due to error: %s', $exception->getMessage()));;
      }
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchImportFinishedCallback($success, $results, $operations): void {
    if ($success) {
      \Drupal::service('messenger')->addMessage(t('The import of content was processed successfully'));
    }
    else {
      \Drupal::service('messenger')->addError(t('The import process finished with an error.'));
    }
  }

}
