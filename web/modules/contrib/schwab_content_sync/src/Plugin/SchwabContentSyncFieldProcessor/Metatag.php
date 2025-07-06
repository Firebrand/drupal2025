<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncFieldProcessor;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\schwab_content_sync\SchwabContentSyncFieldProcessorPluginBase;

/**
 * Plugin implementation for metatag field processor plugin.
 *
 * @SchwabContentSyncFieldProcessor(
 *   id = "metatag",
 *   label = @Translation("Metatag field processor"),
 *   field_type = "metatag",
 * )
 */
class Metatag extends SchwabContentSyncFieldProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $field_value = $field->getString();

    // This check is from the MetatagEntities migration process.
    // Serialized arrays from Metatag v1.
    if (substr($field_value, 0, 2) === 'a:') {
      $data = @unserialize($field_value, ['allowed_classes' => FALSE]);
    }
    // Encoded JSON from Metatag v2.
    elseif (substr($field_value, 0, 2) === '{"') {
      // @todo Handle non-array responses.
      $data = Json::decode($field_value);
    }

    return $data ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    $data = @serialize($value)
      ?? Json::encode($value)
      ?? '';

    $entity->set($fieldName, [['value' => $data]]);
  }

}
