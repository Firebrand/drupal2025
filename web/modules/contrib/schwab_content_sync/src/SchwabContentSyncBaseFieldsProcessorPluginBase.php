<?php

namespace Drupal\schwab_content_sync;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Base class for schwab_content_sync_base_fields_processor plugins.
 */
abstract class SchwabContentSyncBaseFieldsProcessorPluginBase extends PluginBase implements SchwabContentSyncBaseFieldsProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function afterBaseValuesImport(array $values, FieldableEntityInterface $entity): void {
    // Nothing to do by default, so it's an optional method.
  }

}
