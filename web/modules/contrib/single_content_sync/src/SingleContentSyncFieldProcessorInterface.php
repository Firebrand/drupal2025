<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Interface for single_content_sync_field_processor plugins.
 */
interface SingleContentSyncFieldProcessorInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The translated title.
   */
  public function label(): string;

  /**
   * Exports the field values to a serializable format.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to export.
   *
   * @return array
   *   The exported field value.
   */
  public function exportFieldValue(FieldItemListInterface $field): array;

  /**
   * Imports the field value to the entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to import the field value to.
   * @param string $fieldName
   *   The name of the field to import the value to.
   * @param array $value
   *   The value to import.
   */
  public function importFieldValue(
    FieldableEntityInterface $entity,
    string $fieldName,
    array $value
  ): void;

}
