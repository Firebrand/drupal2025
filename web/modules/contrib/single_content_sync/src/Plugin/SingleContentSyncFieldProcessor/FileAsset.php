<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\FileInterface;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of file / media / image fields processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "file_asset",
 *   deriver = "Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor\FileAssetDeriver",
 * )
 */
class FileAsset extends SingleContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

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
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs new FileAsset plugin instance.
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContentExporterInterface $exporter,
    ContentImporterInterface $importer,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $value = [];

    foreach ($field as $item) {
      // The file could not be loaded. Check other files in the field.
      if (!$item->entity instanceof FileInterface) {
        continue;
      }

      $file_item = [];

      if (isset($item->alt)) {
        $file_item['alt'] = $item->alt;
      }

      if (isset($item->title)) {
        $file_item['title'] = $item->title;
      }

      if (isset($item->description)) {
        $file_item['description'] = $item->description;
      }

      $file_item['target_entity'] = $this->exporter->doExportToArray($item->entity);

      $value[] = $file_item;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    $file_storage = $this->entityTypeManager->getStorage('file');
    $values = [];

    foreach ($value as $file_item) {
      // Support new way.
      if (isset($file_item['target_entity'])) {
        $file = $this->importer->doImport($file_item['target_entity']);
      }
      elseif (!isset($file_item['uri'])) {
        // Invalid input.
        continue;
      }
      else {
        // Provide backward compatibility.
        $files = $file_storage->loadByProperties([
          'uri' => $file_item['uri'],
        ]);

        if (count($files)) {
          /** @var \Drupal\file\FileInterface $file */
          $file = reset($files);
        }
        else {
          $file = $file_storage->create([
            'uid' => 1,
            'status' => FileInterface::STATUS_PERMANENT,
            'uri' => $file_item['uri'],
          ]);
        }

        $this->importer->importBaseValues($file, $file_item);
      }

      $file_value = [
        'target_id' => $file->id(),
      ];

      if (isset($file_item['alt'])) {
        $file_value['alt'] = $file_item['alt'];
      }

      if (isset($file_item['title'])) {
        $file_value['title'] = $file_item['title'];
      }

      if (isset($file_item['description'])) {
        $file_value['description'] = $file_item['description'];
      }

      $values[] = $file_value;
    }

    $entity->set($fieldName, $values);
  }

}
