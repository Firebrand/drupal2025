<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\link\Plugin\Field\FieldType\LinkItem;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\schwab_content_sync\ContentExporter;
use Drupal\schwab_content_sync\ContentExporterInterface;
use Drupal\schwab_content_sync\ContentImporterInterface;
use Drupal\schwab_content_sync\SchwabContentSyncBaseFieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for node base fields processor plugin.
 *
 * @SchwabContentSyncBaseFieldsProcessor(
 *   id = "menu_link_content",
 *   label = @Translation("Menu link content processor"),
 *   entity_type = "menu_link_content",
 * )
 */
class MenuLinkContent extends SchwabContentSyncBaseFieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The content exporter.
   *
   * @var \Drupal\schwab_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $exporter;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The content importer.
   *
   * @var \Drupal\schwab_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $importer;

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\schwab_content_sync\ContentExporterInterface $exporter
   *   The content exporter service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\schwab_content_sync\ContentImporterInterface $importer
   *   The content importer service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContentExporterInterface $exporter, EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, ContentImporterInterface $importer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->entityRepository = $entity_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->importer = $importer;
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
      $container->get('entity.repository'),
      $container->get('entity_type.manager'),
      $container->get('schwab_content_sync.importer')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    assert($entity instanceof MenuLinkContentInterface);

    /** @var array<string, mixed> $base_fields */
    $base_fields = [
      'title' => $entity->getTitle(),
      'enabled' => $entity->isPublished(),
      'expanded' => $entity->isExpanded(),
      'langcode' => $entity->language()->getId(),
      'menu_name' => $entity->get('menu_name')->value,
      'description' => $entity->getDescription(),
      'link' => $entity->get('link')->getValue(),
      'weight' => $entity->getWeight(),
      'parent' => '',
    ];

    // Export parent menu link.
    if ($entity->getParentId()) {
      $parent_id_parts = explode(':', $entity->getParentId(), 2);
      if (count($parent_id_parts) === 2) {
        [, $parent_uuid] = $parent_id_parts;
        $parent = $this->entityRepository->loadEntityByUuid($entity->getEntityTypeId(), $parent_uuid);

        if ($parent instanceof MenuLinkContentInterface) {
          $base_fields['parent'] = $this->exporter->doExportToArray($parent);
        }
      }
    }

    // Export linked entity.
    foreach ($entity->get('link') as $index => $item) {
      if (!$item instanceof LinkItem || !$item->getUrl()->isRouted()) {
        continue;
      }

      if (preg_match('/^entity\.(.+)\.canonical$/', $item->getUrl()->getRouteName(), $matches)) {
        $linked_entity_type_id = $matches[1];
        $linked_entity_id = $item->getUrl()->getRouteParameters()[$linked_entity_type_id] ?? NULL;

        if (!$linked_entity_id) {
          continue;
        }

        $linked_entity = $this->entityTypeManager->getStorage($linked_entity_type_id)
          ->load($linked_entity_id);

        if ($linked_entity instanceof FieldableEntityInterface) {
          // Check if exporter is ContentExporter to use isReferenceCached method
          if ($this->exporter instanceof ContentExporter && !$this->exporter->isReferenceCached($linked_entity)) {
            $base_fields['link'][$index]['entity'] = $this->exporter->doExportToArray($linked_entity);
          }
          else {
            $base_fields['link'][$index]['entity'] = [
              'uuid' => $linked_entity->uuid(),
              'entity_type' => $linked_entity->getEntityTypeId(),
              'base_fields' => $this->exporter->exportBaseValues($linked_entity),
              'bundle' => $linked_entity->bundle(),
            ];
          }

          $base_fields['link'][$index]['uri'] = "entity:{$linked_entity_type_id}/{$linked_entity->uuid()}";
        }
      }
    }

    return $base_fields;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $values
   * @return array<string, mixed>
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    /** @var array<string, mixed> $baseFields */
    $baseFields = [
      'title' => $values['title'],
      'enabled' => $values['enabled'],
      'expanded' => $values['expanded'],
      'langcode' => $values['langcode'],
      'menu_name' => $values['menu_name'],
      'description' => $values['description'],
      'weight' => $values['weight'],
      'link' => $values['link'],
      'parent' => '',
    ];

    // Import parent menu link first.
    if (!empty($values['parent']) && is_array($values['parent'])) {
      $parent = $this->importer->doImport($values['parent']);
      if ($parent instanceof EntityInterface) {
        $baseFields['parent'] = implode(':', ['menu_link_content', $parent->uuid()]);
      }
    }

    // Import linked entity.
    if (is_array($baseFields['link'])) {
      foreach ($baseFields['link'] as &$item) {
        if (!is_array($item) || !isset($item['entity']) || !is_array($item['entity'])) {
          continue;
        }

        // If the entity was fully exported we do the full import.
        if ($this->importer->isFullEntity($item['entity'])) {
          $this->importer->doImport($item['entity']);
        }

        if (isset($item['entity']['entity_type']) && isset($item['entity']['uuid'])) {
          $linked_entity = $this->entityRepository->loadEntityByUuid($item['entity']['entity_type'], $item['entity']['uuid']);

          if (!$linked_entity) {
            $linked_entity = $this->importer->createStubEntity($item['entity']);
          }

          if ($linked_entity instanceof EntityInterface) {
            $item['uri'] = "entity:{$linked_entity->getEntityTypeId()}/{$linked_entity->id()}";
          }
        }
      }
    }

    return $baseFields;
  }

}