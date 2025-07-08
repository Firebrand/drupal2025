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
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $field
   *   The field item list.
   *
   * @return array<string, mixed>
   *   The exported field values.
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $field_value = $field->getString();

    /** @var array<string, mixed> $data */
    $data = [];

    // This check is from the MetatagEntities migration process.
    // Serialized arrays from Metatag v1.
    if (substr($field_value, 0, 2) === 'a:') {
      $unserialized = @unserialize($field_value, ['allowed_classes' => FALSE]);
      if (is_array($unserialized)) {
        $data = $unserialized;
      }
    }
    // Encoded JSON from Metatag v2.
    elseif (substr($field_value, 0, 2) === '{"') {
      try {
        $decoded = Json::decode($field_value);
        if (is_array($decoded)) {
          $data = $decoded;
        }
      }
      catch (\Exception $e) {
        // If JSON decode fails, return empty array
        $data = [];
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $value
   *   The field values to import.
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    // Try to serialize the value
    $serialized = @serialize($value);
    
    if ($serialized !== FALSE) {
      $data = $serialized;
    }
    else {
      // If serialization fails, try JSON encoding
      try {
        $data = Json::encode($value);
      }
      catch (\Exception $e) {
        // If both fail, use empty string
        $data = '';
      }
    }

    $entity->set($fieldName, [['value' => $data]]);
  }

}