<?php

namespace Drupal\single_content_sync\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines single_content_sync_base_fields_processor annotation object.
 *
 * @Annotation
 */
class SingleContentSyncBaseFieldsProcessor extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public string $id;

  /**
   * The human-readable name of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $title;

  /**
   * The entity type machine name that this plugin supports.
   *
   * @var string
   */
  public string $entity_type;

}
