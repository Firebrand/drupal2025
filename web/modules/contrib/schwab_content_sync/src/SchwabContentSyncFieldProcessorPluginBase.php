<?php

namespace Drupal\schwab_content_sync;

use Drupal\Component\Plugin\PluginBase;

/**
 * Base class for schwab_content_sync_field_processor plugins.
 */
abstract class SchwabContentSyncFieldProcessorPluginBase extends PluginBase implements SchwabContentSyncFieldProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

}
