<?php

namespace Drupal\single_content_sync\Utility;

use Drupal\file\FileInterface;

/**
 * Creates an interface for command helper.
 */
interface CommandHelperInterface {

  /**
   * Append flags to a message.
   *
   * @param string $message
   *   The message to append flags to.
   * @param array $options
   *   The options array with flags to append.
   */
  public function createMessageWithFlags(string $message, array $options = []): string;

  /**
   * Import content from a zip file.
   *
   * @param string $file_path
   *   The path to the zip file to import.
   */
  public function commandZipImport(string $file_path): void;

  /**
   * Get the entities to export.
   *
   * @param string $entity_type
   *   The entity type to export, e.g. 'node'.
   * @param string $bundle
   *   The specific bundle for nodes or taxonomy terms.
   * @param bool $all_allowed_content
   *   Will export all entity types if set to TRUE.
   * @param string|null $entity_ids_to_export
   *   A comma separated string of entity ids or uuids to export, e.g. "1,2,5".
   *
   * @return array
   *   Returns an array of entities to export.
   */
  public function getEntitiesToExport(string $entity_type = 'node', string $bundle = '', bool $all_allowed_content = FALSE, string $entity_ids_to_export = NULL): array;

  /**
   * Get selected entities.
   *
   * Get an array of entities to export based on an entity type and
   * an array of entity ids.
   *
   * @param string $entity_type
   *   The entity type to export, e.g. 'node'.
   * @param string $ids_to_export
   *   A comma separated string of entity type ids to export, e.g. "1,2,5".
   *
   * @return array
   *   An array of entities.
   */
  public function getSelectedEntities(string $entity_type, string $ids_to_export): array;

  /**
   * Move a file to directory.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file to move.
   * @param string $output_dir
   *   A relative path to move the file to.
   * @param string $file_target
   *   The current location of the file.
   *
   * @return string
   *   The directory to which the file was moved.
   */
  public function moveFile(FileInterface $file, string $output_dir, string $file_target): string;

  /**
   * Gets the real output directory based on a relative output path.
   *
   * @param string $output_path
   *   The relative ouput path.
   *
   * @return string
   *   A string with the real output directory.
   */
  public function getRealDirectory(string $output_path): string;

}
