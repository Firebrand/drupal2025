<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncFieldProcessor;

use Drupal\Core\Entity\EntityInterface;
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
 * Plugin implementation for layout section field processor plugin.
 *
 * @SchwabContentSyncFieldProcessor(
 *   id = "link",
 *   label = @Translation("Link field processor"),
 *   field_type = "link",
 * )
 */
class Link extends SchwabContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

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
   * Constructs new Link plugin instance.
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
    /** @var array<int, array<string, mixed>> $values */
    $values = $field->getValue();
    
    if (!is_array($values)) {
      return [];
    }
    
    foreach ($values as $delta => $value) {
      if (!is_array($value) || empty($value['uri']) || !is_string($value['uri'])) {
        continue;
      }

      if (preg_match('/^entity:(.+)\/(\d+)$/', $value['uri'], $matches)) {
        $entity_type_id = $matches[1];
        $entity_id = $matches[2];

        $entity = $this->entityTypeManager->getStorage($entity_type_id)
          ->load($entity_id);

        if (!$entity instanceof EntityInterface) {
          continue;
        }

        // Check if entity is FieldableEntityInterface for export operations
        if ($entity instanceof FieldableEntityInterface) {
          // Check if exporter is ContentExporter to use isReferenceCached method
          if ($this->exporter instanceof ContentExporter && !$this->exporter->isReferenceCached($entity)) {
            $values[$delta]['linked_entity'] = $this->exporter->doExportToArray($entity);
          }
          else {
            $values[$delta]['linked_entity'] = [
              'uuid' => $entity->uuid(),
              'entity_type' => $entity->getEntityTypeId(),
              'base_fields' => $this->exporter->exportBaseValues($entity),
              'bundle' => $entity->bundle(),
            ];
          }
        }
        else {
          // For non-fieldable entities, just store basic info
          $values[$delta]['linked_entity'] = [
            'uuid' => $entity->uuid(),
            'entity_type' => $entity->getEntityTypeId(),
            'bundle' => $entity->bundle(),
          ];
        }
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int, array<string, mixed>> $value
   *   The field values.
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    foreach ($value as $delta => $item) {
      if (!is_array($item) || !isset($item['linked_entity']) || !is_array($item['linked_entity'])) {
        continue;
      }

      $linked_entity = $item['linked_entity'];

      // If the entity was fully exported we do the full import.
      if ($this->importer->isFullEntity($linked_entity)) {
        $referenced_entity = $this->importer->doImport($linked_entity);
      }
      else {
        if (isset($linked_entity['entity_type']) && isset($linked_entity['uuid'])) {
          $referenced_entity = $this
            ->entityRepository
            ->loadEntityByUuid($linked_entity['entity_type'], $linked_entity['uuid']);

          // Create a stub entity without custom field values.
          if (!$referenced_entity) {
            $referenced_entity = $this->importer->createStubEntity($linked_entity);
          }
        }
        else {
          continue;
        }
      }

      if ($referenced_entity instanceof EntityInterface && isset($item['uri']) && is_string($item['uri'])) {
        $value[$delta]['uri'] = preg_replace_callback('/^entity:.+\/(\d+)$/', function () use ($referenced_entity) {
          return "entity:{$referenced_entity->getEntityTypeId()}/{$referenced_entity->id()}";
        }, $item['uri']);
      }
    }

    $entity->set($fieldName, $value);
  }

}