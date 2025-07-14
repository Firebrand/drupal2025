<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity reference field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "entity_reference",
 *   deriver = "Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor\EntityReferenceDeriver",
 * )
 */
class EntityReference extends SingleContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The content exporter service.
   *
   * @var \Drupal\single_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $exporter;

  /**
   * The content importer service.
   *
   * @var \Drupal\single_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $importer;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs new EntityReference plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\single_content_sync\ContentExporterInterface $exporter
   *   The content exporter service.
   * @param \Drupal\single_content_sync\ContentImporterInterface $importer
   *   The content importer service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContentExporterInterface $exporter,
    ContentImporterInterface $importer,
    EntityRepositoryInterface $entity_repository,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('single_content_sync.importer'),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $value = [];
    $ids_by_entity_type = [];
    if ($this->getPluginDefinition()['field_type'] === 'entity_reference') {
      $fieldDefinition = $field->getFieldDefinition();
      $ids_by_entity_type[$fieldDefinition->getSetting('target_type')] = array_column($field->getValue(), 'target_id');
    }
    else {
      foreach ($field->getValue() as $item) {
        $ids_by_entity_type[$item['target_type']][] = $item['target_id'];
      }
    }

    foreach ($ids_by_entity_type as $entity_type => $ids) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $entities = $storage->loadMultiple($ids);
      foreach ($entities as $child_entity) {
        if ($child_entity instanceof FieldableEntityInterface) {
          // Avoid exporting the same entity multiple times.
          if (!$this->exporter->isReferenceCached($child_entity)) {
            // Export content entity relation.
            $value[] = $this->exporter->doExportToArray($child_entity);
          }
          else {
            $value[] = [
              'uuid' => $child_entity->uuid(),
              'entity_type' => $child_entity->getEntityTypeId(),
              'base_fields' => $this->exporter->exportBaseValues($child_entity),
              'bundle' => $child_entity->bundle(),
            ];
          }
        }
        // Support basic export of config entity relation.
        elseif ($child_entity instanceof ConfigEntityInterface) {
          $value[] = [
            'type' => 'config',
            'dependency_name' => $child_entity->getConfigDependencyName(),
            'value' => $child_entity->id(),
          ];
        }
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    $values = [];

    foreach ($value as $childEntity) {
      // Import config relation just by setting target id.
      if (isset($childEntity['type']) && $childEntity['type'] === 'config') {
        $values[] = [
          'target_id' => $childEntity['value'],
        ];
        continue;
      }

      // If the entity was fully exported we do the full import.
      if ($this->importer->isFullEntity($childEntity)) {
        $values[] = $this->importer->doImport($childEntity);
        continue;
      }

      $referencedEntity = $this
        ->entityRepository
        ->loadEntityByUuid($childEntity['entity_type'], $childEntity['uuid']);

      // Create a stub entity without custom field values.
      if (!$referencedEntity) {
        $referencedEntity = $this->importer->createStubEntity($childEntity);
      }

      $values[] = $referencedEntity;
    }

    $entity->set($fieldName, $values);
  }

}
