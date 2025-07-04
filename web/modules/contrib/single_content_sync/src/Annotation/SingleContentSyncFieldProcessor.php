<?php

namespace Drupal\single_content_sync\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines single_content_sync_field_processor annotation object.
 *
 * @Annotation
 */
class SingleContentSyncFieldProcessor extends Plugin {

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
   * The field type machine name that this plugin supports.
   *
   * @var string
   */
  public string $field_type;

}
