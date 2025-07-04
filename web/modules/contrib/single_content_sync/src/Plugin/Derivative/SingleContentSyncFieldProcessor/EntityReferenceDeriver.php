<?php

namespace Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Retrieves field processor plugin definitions for entity reference fields.
 *
 * @see \Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor\EntityReference
 */
class EntityReferenceDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $simpleFieldTypes = [
      'entity_reference',
      'dynamic_entity_reference',
    ];

    foreach ($simpleFieldTypes as $fieldType) {
      $this->derivatives[$fieldType] = $base_plugin_definition;
      $this->derivatives[$fieldType]['id'] = $base_plugin_definition['id'] . ':' . $fieldType;
      $this->derivatives[$fieldType]['label'] = new TranslatableMarkup('Entity reference field processor for @fieldType', ['@fieldType' => $fieldType]);
      $this->derivatives[$fieldType]['field_type'] = $fieldType;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
