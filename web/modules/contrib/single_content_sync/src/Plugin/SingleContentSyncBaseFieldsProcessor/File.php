<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\file\FileInterface;
use Drupal\focal_point\FocalPointManagerInterface;
use Drupal\single_content_sync\ContentSyncHelperInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for file base fields processor plugin.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "file",
 *   label = @Translation("File base fields processor"),
 *   entity_type = "file",
 * )
 */
class File extends SingleContentSyncBaseFieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module private temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $privateTempStore;

  /**
   * The content sync helper service.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The focal_point manager.
   *
   * @var \Drupal\focal_point\FocalPointManagerInterface
   */
  protected FocalPointManagerInterface $focalPointManager;

  /**
   * Constructs new File plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStore $private_temp_store
   *   The module private temporary storage.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\focal_point\FocalPointManagerInterface|null $focal_point_manager
   *   The focal_point manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    PrivateTempStore $private_temp_store,
    ContentSyncHelperInterface $content_sync_helper,
    FileSystemInterface $file_system,
    ConfigFactoryInterface $config_factory,
    FocalPointManagerInterface $focal_point_manager = NULL
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStore = $private_temp_store;
    $this->contentSyncHelper = $content_sync_helper;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;

    if ($focal_point_manager) {
      $this->focalPointManager = $focal_point_manager;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('single_content_sync.store'),
      $container->get('single_content_sync.helper'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('focal_point.manager', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    assert($entity instanceof FileInterface);

    $file_item = [
      'name' => $entity->getFilename(),
      'uri' => $entity->getFileUri(),
      'url' => $entity->createFileUrl(FALSE),
      'status' => $entity->get('status')->value,
      'created' => $entity->getCreatedTime(),
      'changed' => $entity->getChangedTime(),
      'mimetype' => $entity->getMimeType(),
    ];

    // Export focal point.
    if (isset($this->focalPointManager) && $this->configFactory->get('focal_point.settings')) {
      $crop_type = $this->configFactory->get('focal_point.settings')->get('crop_type');
      $crop = $this->focalPointManager->getCropEntity($entity, $crop_type);
      if (!$crop->isNew() && !$crop->get('x')->isEmpty() && !$crop->get('y')->isEmpty()) {
        $file_item['crop'] = [
          'width' => $field->width ?? 0,
          'height' => $field->height ?? 0,
        ];
        $file_item['crop'] += $this->focalPointManager->absoluteToRelative(
          $crop->get('x')->value,
          $crop->get('y')->value,
          $file_item['crop']['width'],
          $file_item['crop']['height'],
        );
      }
    }

    $assets = $this->privateTempStore->get('export.assets') ?? [];
    if (!in_array($file_item['uri'], $assets, TRUE)) {
      $assets[] = $file_item['uri'];

      // Let's store all exported assets in the private storage.
      // This will be used during exporting all assets to the zip later on.
      $this->privateTempStore->set('export.assets', $assets);
    }

    return $file_item;
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    // Try to get and save a file by absolute url if file could not
    // be found after assets import.
    if (!file_exists($values['uri'])) {
      $data = file_get_contents($values['url']);

      if ($data) {
        // Save external file to the proper destination.
        $directory = $this->fileSystem->dirname($values['uri']);
        $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $this->fileSystem->saveData($data, $values['uri']);
      }
    }

    return [
      'uid' => 1,
      'uri' => $values['uri'],
      'status' => $values['status'] ?? FileInterface::STATUS_PERMANENT,
      'filename' => $values['name'] ?? NULL,
      'filemime' => $values['mimetype'] ?? NULL,
      'created' => $values['created'] ?? NULL,
      'changed' => $values['changed'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function afterBaseValuesImport(array $values, FieldableEntityInterface $entity): void {
    // Import focal point metadata.
    if (isset($values['crop']) && isset($this->focalPointManager) && $entity instanceof FileInterface) {
      // To save crop we need to ensure that file has id.
      if ($entity->isNew()) {
        $entity->save();
      }

      $crop_type = $this->configFactory->get('focal_point.settings')->get('crop_type');
      $crop = $this->focalPointManager->getCropEntity($entity, $crop_type);

      $this->focalPointManager->saveCropEntity(
        $values['crop']['x'],
        $values['crop']['y'],
        $values['crop']['width'],
        $values['crop']['height'],
        $crop
      );
    }
  }

}
