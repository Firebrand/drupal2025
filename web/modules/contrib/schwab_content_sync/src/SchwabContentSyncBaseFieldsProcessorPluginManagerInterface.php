<?php

namespace Drupal\schwab_content_sync;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * SchwabContentSyncBaseFieldsProcessor plugin manager interface.
 */
interface SchwabContentSyncBaseFieldsProcessorPluginManagerInterface extends PluginManagerInterface {

  /**
   * Gets the base field processor for a given entity type.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @return \Drupal\schwab_content_sync\SchwabContentSyncBaseFieldsProcessorInterface|null
   *   The base field processor plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the instance cannot be created, such as if the ID is invalid.
   */
  public function getEntityPluginInstance(string $entityType): SchwabContentSyncBaseFieldsProcessorInterface;

}
