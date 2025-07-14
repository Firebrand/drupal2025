<?php

namespace Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Retrieves field processor plugin definitions for text field types.
 *
 * @see \Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor\TextField
 */
class TextFieldDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $textFieldTypes = [
      'text_long',
      'text_with_summary',
    ];

    foreach ($textFieldTypes as $fieldType) {
      $this->derivatives[$fieldType] = $base_plugin_definition;
      $this->derivatives[$fieldType]['id'] = $base_plugin_definition['id'] . ':' . $fieldType;
      $this->derivatives[$fieldType]['label'] = new TranslatableMarkup('Text field processor for @fieldType', ['@fieldType' => $fieldType]);
      $this->derivatives[$fieldType]['field_type'] = $fieldType;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
