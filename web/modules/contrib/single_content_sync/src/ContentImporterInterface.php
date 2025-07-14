<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Creates an interface for content importer.
 */
interface ContentImporterInterface {

  /**
   * Import content.
   *
   * @param array $content
   *   Content to import.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Imported entity.
   */
  public function doImport(array $content): EntityInterface;

  /**
   * Set field value.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Entity to be imported.
   * @param string $field_name
   *   Field name.
   * @param mixed $field_value
   *   Field value.
   */
  public function setFieldValue(FieldableEntityInterface $entity, string $field_name, $field_value): void;

  /**
   * Import content from the YAML file.
   *
   * @param string $file_real_path
   *   The real path to the local file.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Imported entity.
   */
  public function importFromFile(string $file_real_path): EntityInterface;

  /**
   * Import content from the Zip file.
   *
   * @param string $file_real_path
   *   The real path to the local file.
   */
  public function importFromZip(string $file_real_path): void;

  /**
   * Import assets from a Zip file.
   *
   * @param string $extracted_file_path
   *   The original file path where assets are imported.
   * @param string $zip_file_path
   *   The contents from the zip file which should be imported.
   */
  public function importAssets(string $extracted_file_path, string $zip_file_path): void;

  /**
   * Handle import of values for custom fields.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity instance to import fields in.
   * @param array $fields
   *   The custom fields with values.
   */
  public function importCustomValues(FieldableEntityInterface $entity, array $fields): void;

  /**
   * Handle import of values for base fields.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity instance to import fields in.
   * @param array $fields
   *   The base fields with values.
   */
  public function importBaseValues(FieldableEntityInterface $entity, array $fields): void;

  /**
   * Create a stub entity (import only base fields).
   *
   * @param array $entity
   *   The exported stub entity (does not contain custom_fields).
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The stub entity object.
   */
  public function createStubEntity(array $entity): EntityInterface;

  /**
   * Validates whether an entity array is a full entity array or not.
   *
   * @param array $entity
   *   The entity array to be validated.
   *
   * @return bool
   *   If the entity is a full entity array will return TRUE,
   *   else will return FALSE.
   */
  public function isFullEntity(array $entity): bool;

}
