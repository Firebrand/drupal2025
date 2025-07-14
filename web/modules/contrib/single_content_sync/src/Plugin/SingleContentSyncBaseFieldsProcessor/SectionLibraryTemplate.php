<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implementation for section_library_template base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "section_library_template",
 *   label = @Translation("SectionLibraryTemplate base fields processor"),
 *   entity_type = "section_library_template",
 * )
 */
class SectionLibraryTemplate extends SingleContentSyncBaseFieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The content exporter.
   *
   * @var \Drupal\single_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $exporter;

  /**
   * The content importer.
   *
   * @var \Drupal\single_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $importer;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContentExporterInterface $exporter, ContentImporterInterface $importer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('single_content_sync.exporter'),
      $container->get('single_content_sync.importer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    $image = !empty($entity->get('image')->target_id)
      ? $this->exporter->getFieldValue($entity->get('image'))
      : NULL;

    $owner = $entity->getOwner();
    $layout_section = $this->exporter->getFieldValue($entity->get('layout_section'));
    $source_entity = !empty($entity->get('entity_id')->entity)
      ? $this->exporter->getFieldValue($entity->get('entity_id'))
      : NULL;

    return [
      'label' => $entity->label(),
      'type' => $this->exporter->getFieldValue($entity->get('type')),
      'source_entity' => $source_entity ? reset($source_entity) : NULL,
      'entity_type' => $this->exporter->getFieldValue($entity->get('entity_type')),
      'image' => $image,
      'owner' => $owner ? $owner->getEmail() : NULL,
      'langcode' => $entity->language()->getId(),
      'created' => $entity->getCreatedTime(),
      'layout_section' => $layout_section,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    if (isset($values['owner']) && ($account = user_load_by_mail($values['owner']))) {
      $entity->setOwner($account);
    }

    $this->importer->setFieldValue($entity, 'layout_section', $values['layout_section']);
    $this->importer->setFieldValue($entity, 'image', $values['image']);

    $source_entity = NULL;
    if (!empty($values['source_entity'])) {
      $source_entity = $this->importer->doImport($values['source_entity']);
    }

    return [
      'label' => $values['label'],
      'type' => $values['type'],
      'entity_id' => $source_entity?->id(),
      'entity_type' => $values['entity_type'],
      'langcode' => $values['langcode'],
      'created' => $values['created'],
    ];
  }

}
