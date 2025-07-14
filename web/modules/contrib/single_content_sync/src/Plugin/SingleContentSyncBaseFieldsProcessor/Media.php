<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;

/**
 * Plugin implementation for media base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "media",
 *   label = @Translation("Media base fields processor"),
 *   entity_type = "media",
 * )
 */
class Media extends SingleContentSyncBaseFieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    return [
      'name' => $entity->getName(),
      'created' => $entity->getCreatedTime(),
      'status' => $entity->isPublished(),
      'langcode' => $entity->language()->getId(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    return [
      'langcode' => $values['langcode'],
      'name' => $values['name'],
      'status' => $values['status'],
      'created' => $values['created'],
    ];
  }

}
