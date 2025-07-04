<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;

/**
 * Plugin implementation generic field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "generic",
 *   field_type=""
 * )
 */
class GenericField extends SingleContentSyncFieldProcessorPluginBase {

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
