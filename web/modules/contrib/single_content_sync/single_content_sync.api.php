<?php

/**
 * @file
 * Single Content Sync API documentation.
 */

/**
 * @addtogroup hooks
 * @{
 */

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Perform alterations on the exporting field value.
 *
 * @param array|string|bool $value
 *   The original value after exporting to modify/extend.
 * @param \Drupal\Core\Field\FieldItemListInterface $field
 *   The field item list.
 *
 * @deprecated in single_content_sync:1.4.0 and is removed from single_content_sync:2.0.0.
 *   Define a custom plugin to export value of the custom field type or
 *   implement the event subscriber to alter the existing value.
 *
 * @see https://www.drupal.org/project/single_content_sync/issues/3336402
 * @see \Drupal\single_content_sync\Annotation\SingleContentSyncFieldProcessor
 */
function hook_content_export_field_value_alter(&$value, FieldItemListInterface $field) {
  switch ($field->getFieldDefinition()->getType()) {
    case 'my_custom_field_type':
      $value[] = [
        'foo' => $field->foo,
        'bar' => $field->bar,
        'external' => TRUE,
      ];
      break;
  }
}

/**
 * Perform alterations on the exporting entity.
 *
 * @param array $base_fields
 *   The original value of base fields during the import.
 * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
 *   The entity to be exported.
 *
 * @deprecated in single_content_sync:1.4.0 and is removed from single_content_sync:2.0.0.
 *   Define a custom plugin to export value of the custom entity or
 *   implement the event subscriber to alter the existing entity.
 *
 * @see https://www.drupal.org/project/single_content_sync/issues/3336402
 * @see \Drupal\single_content_sync\ContentExporter::doExportToArray()
 * @see \Drupal\single_content_sync\Event\ExportEvent
 */
function hook_content_export_entity_alter(array &$base_fields, FieldableEntityInterface $entity) {
  switch ($entity->getEntityTypeId()) {
    case 'my_custom_entity':
      $base_fields['langcode'] = $entity->language()->getId();
      break;
  }
}

/**
 * Perform alterations on the importing entity.
 *
 * @param array $content
 *   The content array of entity to be imported.
 * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
 *   The entity to be imported which is just created or updated.
 *
 * @deprecated in single_content_sync:1.4.0 and is removed from single_content_sync:2.0.0.
 *   Define a custom plugin to handle custom entity import or implement
 *   the event subscriber to alter import of the existing entity.
 *
 * @see https://www.drupal.org/project/single_content_sync/issues/3336402
 * @see \Drupal\single_content_sync\ContentImporter::doImport()
 * @see \Drupal\single_content_sync\Event\ImportEvent
 */
function hook_content_import_entity_alter(array $content, FieldableEntityInterface &$entity = NULL) {
  switch ($content['entity_type']) {
    case 'my_custom_entity_type':
      $storage = \Drupal::entityTypeManager()->getStorage('my_custom_entity_type');
      $entity = $storage->create([
        'foo' => $content['foo'],
        'boo' => $content['boo'],
      ]);
      break;
  }
}

/**
 * Perform alterations on the importing field value.
 *
 * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
 *   The entity to be imported which is just created or updated.
 * @param string $field_name
 *   The name of the field where imported value should be set.
 * @param string|array|bool $field_value
 *   The raw field value of the field to be imported.
 *
 * @deprecated in single_content_sync:1.4.0 and is removed from single_content_sync:2.0.0.
 *   Define a custom plugin to import value of the custom field type or
 *   implement the event subscriber to alter import of the existing value.
 *
 * @see https://www.drupal.org/project/single_content_sync/issues/3336402
 * @see \Drupal\single_content_sync\Annotation\SingleContentSyncFieldProcessor
 */
function hook_content_import_field_value_alter(FieldableEntityInterface $entity, $field_name, $field_value) {
  $field_definition = $entity->getFieldDefinition($field_name);

  switch ($field_definition->getType()) {
    case 'my_custom_field_type':
      $entity->set($field_name, $field_value);
      break;
  }
}

/**
 * @} End of "addtogroup hooks".
 */
