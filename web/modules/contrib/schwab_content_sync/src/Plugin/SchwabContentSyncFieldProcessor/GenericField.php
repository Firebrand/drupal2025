<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncFieldProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\schwab_content_sync\SchwabContentSyncFieldProcessorPluginBase;

/**
 * Plugin implementation generic field processor plugin.
 *
 * @SchwabContentSyncFieldProcessor(
 *   id = "generic",
 *   field_type=""
 * )
 */
class GenericField extends SchwabContentSyncFieldProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    return $field->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    $entity->set($fieldName, $value);
  }

}
