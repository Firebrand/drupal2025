<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;

/**
 * Plugin implementation for block_content base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "block_content",
 *   label = @Translation("Block Content base fields processor"),
 *   entity_type = "block_content",
 * )
 */
class BlockContent extends SingleContentSyncBaseFieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    return [
      'info' => $entity->label(),
      'reusable' => $entity->isReusable(),
      'langcode' => $entity->language()->getId(),
      'block_revision_id' => $entity->getRevisionId(),
      'enforce_new_revision' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    return [
      'langcode' => $values['langcode'],
      'info' => $values['info'],
      'reusable' => $values['reusable'],
    ];
  }

}
