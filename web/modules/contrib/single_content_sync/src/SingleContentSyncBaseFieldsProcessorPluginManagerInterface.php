<?php

namespace Drupal\single_content_sync;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * SingleContentSyncBaseFieldsProcessor plugin manager interface.
 */
interface SingleContentSyncBaseFieldsProcessorPluginManagerInterface extends PluginManagerInterface {

  /**
   * Gets the base field processor for a given entity type.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @return \Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorInterface|null
   *   The base field processor plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getEntityPluginInstance(string $entityType): SingleContentSyncBaseFieldsProcessorInterface;

}
