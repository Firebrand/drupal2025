<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;

/**
 * Plugin implementation for user base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "user",
 *   label = @Translation("User base fields processor"),
 *   entity_type = "user",
 * )
 */
class User extends SingleContentSyncBaseFieldsProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    return [
      'mail' => $entity->getEmail(),
      'init' => $entity->getInitialEmail(),
      'name' => $entity->getAccountName(),
      'created' => $entity->getCreatedTime(),
      'status' => $entity->isActive(),
      'timezone' => $entity->getTimeZone(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    return [
      'mail' => $values['mail'],
      'init' => $values['init'],
      'name' => $values['name'],
      'created' => $values['created'],
      'status' => $values['status'],
      'timezone' => $values['timezone'],
    ];
  }

}
