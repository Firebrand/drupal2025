<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\schwab_content_sync\SchwabContentSyncBaseFieldsProcessorPluginBase;

/**
 * Plugin implementation for user base fields processor plugin.
 *
 * @SchwabContentSyncBaseFieldsProcessor(
 *   id = "user",
 *   label = @Translation("User base fields processor"),
 *   entity_type = "user",
 * )
 */
class User extends SchwabContentSyncBaseFieldsProcessorPluginBase {

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
