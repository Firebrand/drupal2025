<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncFieldProcessor;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\schwab_content_sync\ContentExporter;
use Drupal\schwab_content_sync\ContentExporterInterface;
use Drupal\schwab_content_sync\ContentImporterInterface;
use Drupal\schwab_content_sync\SchwabContentSyncFieldProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the entity reference field processor plugin.
 *
 * @SchwabContentSyncFieldProcessor(
 *   id = "entity_reference",
 *   deriver = "Drupal\schwab_content_sync\Plugin\Derivative\SchwabContentSyncFieldProcessor\EntityReferenceDeriver",
 * )
 */
class EntityReference extends SchwabContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The content exporter service.
   *
   * @var \Drupal\schwab_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $exporter;

  /**
   * The content importer service.
   *
   * @var \Drupal\schwab_content_sync\ContentImporterInterface
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
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\schwab_content_sync\ContentExporterInterface $exporter
   *   The content exporter service.
   * @param \Drupal\schwab_content_sync\ContentImporterInterface $importer
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
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('schwab_content_sync.exporter'),
      $container->get('schwab_content_sync.importer'),
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $field
   *   The field item list.
   *
   * @return array<int, array<string, mixed>>
   *   The exported field values.
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    /** @var array<int, array<string, mixed>> $value */
    $value = [];
    /** @var array<string, array<int>> $ids_by_entity_type */
    $ids_by_entity_type = [];
    
    $plugin_definition = $this->getPluginDefinition();
    $field_type = is_array($plugin_definition) && isset($plugin_definition['field_type']) 
      ? $plugin_definition['field_type'] 
      : NULL;
    
    if ($field_type === 'entity_reference') {
      $fieldDefinition = $field->getFieldDefinition();
      $field_values = $field->getValue();
      if (is_array($field_values)) {
        $ids_by_entity_type[$fieldDefinition->getSetting('target_type')] = array_column($field_values, 'target_id');
      }
    }
    else {
      $field_values = $field->getValue();
      if (is_array($field_values)) {
        foreach ($field_values as $item) {
          if (is_array($item) && isset($item['target_type']) && isset($item['target_id'])) {
            $ids_by_entity_type[$item['target_type']][] = $item['target_id'];
          }
        }
      }
    }

    foreach ($ids_by_entity_type as $entity_type => $ids) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      $entities = $storage->loadMultiple($ids);
      foreach ($entities as $child_entity) {
        if ($child_entity instanceof FieldableEntityInterface) {
          // Check if exporter is ContentExporter to use isReferenceCached method
          if ($this->exporter instanceof ContentExporter && !$this->exporter->isReferenceCached($child_entity)) {
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
   *
   * @param array<int, array<string, mixed>> $value
   *   The field values to import.
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    /** @var array<int, mixed> $values */
    $values = [];

    foreach ($value as $childEntity) {
      if (!is_array($childEntity)) {
        continue;
      }
      
      // Import config relation just by setting target id.
      if (isset($childEntity['type']) && $childEntity['type'] === 'config' && isset($childEntity['value'])) {
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

      if (isset($childEntity['entity_type']) && isset($childEntity['uuid'])) {
        $referencedEntity = $this
          ->entityRepository
          ->loadEntityByUuid($childEntity['entity_type'], $childEntity['uuid']);

        // Create a stub entity without custom field values.
        if (!$referencedEntity) {
          $referencedEntity = $this->importer->createStubEntity($childEntity);
        }

        $values[] = $referencedEntity;
      }
    }

    $entity->set($fieldName, $values);
  }

}