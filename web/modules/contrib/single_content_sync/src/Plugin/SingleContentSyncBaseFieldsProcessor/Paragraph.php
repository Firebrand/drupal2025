<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;

/**
 * Plugin implementation for paragraph base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "paragraph",
 *   label = @Translation("Paragraph base fields processor"),
 *   entity_type = "paragraph",
 * )
 */
class Paragraph extends SingleContentSyncBaseFieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    return [
      'status' => $entity->isPublished(),
      'langcode' => $entity->language()->getId(),
      'created' => $entity->getCreatedTime(),
      'behavior_settings' => $entity->getAllBehaviorSettings(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    $baseFields = [
      'langcode' => $values['langcode'],
      'created' => $values['created'],
      'status' => $values['status'],
    ];

    if (!empty($values['behavior_settings'])) {
      $baseFields['behavior_settings'] = serialize($values['behavior_settings']);
    }

    return $baseFields;
  }

}
