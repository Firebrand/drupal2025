<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin for the entity reference revisions field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "entity_reference_revisions",
 *   label = @Translation("Entity reference revisions field processor"),
 *   field_type = "entity_reference_revisions",
 * )
 */
class EntityReferenceRevisions extends EntityReference {

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $value = [];
    $ids = array_column($field->getValue(), 'target_id');

    // @todo Check if the target entity type is a paragraph.
    // @todo Otherwise, fall back to the parent implementation.
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraphs = $paragraph_storage->loadMultiple($ids);

    foreach ($paragraphs as $paragraph) {
      $value[] = $this->exporter->doExportToArray($paragraph);
    }

    return $value;
  }

}
