<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncBaseFieldsProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\file\FileInterface;
use Drupal\schwab_content_sync\ContentSyncHelperInterface;
use Drupal\schwab_content_sync\SchwabContentSyncBaseFieldsProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

// Check if focal_point module classes exist
if (interface_exists('Drupal\focal_point\FocalPointManagerInterface')) {
  class_alias('Drupal\focal_point\FocalPointManagerInterface', 'FocalPointManagerInterface');
}

/**
 * Plugin implementation for file base fields processor plugin.
 *
 * @SchwabContentSyncBaseFieldsProcessor(
 *   id = "file",
 *   label = @Translation("File base fields processor"),
 *   entity_type = "file",
 * )
 */
class File extends SchwabContentSyncBaseFieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

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
   * @var \Drupal\schwab_content_sync\ContentSyncHelperInterface
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
   * @var object|null
   */
  protected ?object $focalPointManager = NULL;

  /**
   * The image factory.
   *
   * @var \Drupal\Core\Image\ImageFactory
   */
  protected $imageFactory;

  /**
   * Constructs new File plugin instance.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\TempStore\PrivateTempStore $private_temp_store
   *   The module private temporary storage.
   * @param \Drupal\schwab_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param object|null $focal_point_manager
   *   The focal_point manager.
   * @param \Drupal\Core\Image\ImageFactory|null $image_factory
   *   The image factory.
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
    ?object $focal_point_manager = NULL,
    $image_factory = NULL,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->privateTempStore = $private_temp_store;
    $this->contentSyncHelper = $content_sync_helper;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
    $this->imageFactory = $image_factory;

    if ($focal_point_manager) {
      $this->focalPointManager = $focal_point_manager;
    }
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
      $container->get('entity_type.manager'),
      $container->get('schwab_content_sync.store'),
      $container->get('schwab_content_sync.helper'),
      $container->get('file_system'),
      $container->get('config.factory'),
      $container->get('focal_point.manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
      $container->get('image.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    assert($entity instanceof FileInterface);

    /** @var array<string, mixed> $file_item */
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
    if ($this->focalPointManager !== NULL) {
      $focalPointSettings = $this->configFactory->get('focal_point.settings');
      if ($focalPointSettings !== NULL) {
        $crop_type = $focalPointSettings->get('crop_type');
        
        // Use dynamic method calls to avoid PHPStan errors
        if (method_exists($this->focalPointManager, 'getCropEntity')) {
          /** @var object $crop */
          $crop = $this->focalPointManager->getCropEntity($entity, $crop_type);
          
          if (is_object($crop) && 
              method_exists($crop, 'isNew') && 
              method_exists($crop, 'get') && 
              !$crop->isNew()) {
            
            $xField = $crop->get('x');
            $yField = $crop->get('y');
            
            if (is_object($xField) && method_exists($xField, 'isEmpty') && !$xField->isEmpty() &&
                is_object($yField) && method_exists($yField, 'isEmpty') && !$yField->isEmpty()) {
              
              // Get image dimensions from the file entity
              $width = 0;
              $height = 0;
              
              if ($this->imageFactory !== NULL) {
                $image = $this->imageFactory->get($entity->getFileUri());
                if ($image->isValid()) {
                  $width = $image->getWidth();
                  $height = $image->getHeight();
                }
              }
              
              $file_item['crop'] = [
                'width' => $width,
                'height' => $height,
              ];
              
              if (method_exists($this->focalPointManager, 'absoluteToRelative') &&
                  property_exists($xField, 'value') && property_exists($yField, 'value')) {
                /** @var array<string, mixed> $relative */
                $relative = $this->focalPointManager->absoluteToRelative(
                  $xField->value,
                  $yField->value,
                  $file_item['crop']['width'],
                  $file_item['crop']['height'],
                );
                $file_item['crop'] = array_merge($file_item['crop'], $relative);
              }
            }
          }
        }
      }
    }

    $assets = $this->privateTempStore->get('export.assets');
    if (!is_array($assets)) {
      $assets = [];
    }
    
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
   *
   * @param array<string, mixed> $values
   * @return array<string, mixed>
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    // Try to get and save a file by absolute url if file could not
    // be found after assets import.
    if (isset($values['uri']) && is_string($values['uri']) && !file_exists($values['uri'])) {
      if (isset($values['url']) && is_string($values['url'])) {
        $data = file_get_contents($values['url']);

        if ($data) {
          // Save external file to the proper destination.
          $directory = $this->fileSystem->dirname($values['uri']);
          $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
          $this->fileSystem->saveData($data, $values['uri']);
        }
      }
    }

    return [
      'uid' => 1,
      'uri' => $values['uri'] ?? '',
      'status' => $values['status'] ?? FileInterface::STATUS_PERMANENT,
      'filename' => $values['name'] ?? NULL,
      'filemime' => $values['mimetype'] ?? NULL,
      'created' => $values['created'] ?? NULL,
      'changed' => $values['changed'] ?? NULL,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $values
   */
  public function afterBaseValuesImport(array $values, FieldableEntityInterface $entity): void {
    // Import focal point metadata.
    if (isset($values['crop']) && $this->focalPointManager !== NULL && $entity instanceof FileInterface) {
      // To save crop we need to ensure that file has id.
      if ($entity->isNew()) {
        $entity->save();
      }

      $crop_type = $this->configFactory->get('focal_point.settings')->get('crop_type');
      
      // Use dynamic method calls to avoid PHPStan errors
      if (method_exists($this->focalPointManager, 'getCropEntity')) {
        $crop = $this->focalPointManager->getCropEntity($entity, $crop_type);
        
        if (method_exists($this->focalPointManager, 'saveCropEntity')) {
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
  }

}