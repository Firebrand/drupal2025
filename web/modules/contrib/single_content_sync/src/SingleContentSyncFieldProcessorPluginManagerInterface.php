<?php

namespace Drupal\single_content_sync;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * SingleContentSyncFieldProcessor plugin manager interface.
 */
interface SingleContentSyncFieldProcessorPluginManagerInterface extends PluginManagerInterface {

  /**
   * Gets the field processor plugin definition for a given field.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   *
   * @return array|null
   *   The plugin definition or NULL if none found.
   */
  public function findFieldPluginDefinition(
    string $entityType,
    string $bundle,
    string $fieldName
  ): ?array;

  /**
   * Gets the field processor for a given field type.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   *
   * @return \Drupal\single_content_sync\SingleContentSyncFieldProcessorInterface
   *   The field processor plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getFieldPluginInstance(
    string $entityType,
    string $bundle,
    string $fieldName
  ): SingleContentSyncFieldProcessorInterface;

}
