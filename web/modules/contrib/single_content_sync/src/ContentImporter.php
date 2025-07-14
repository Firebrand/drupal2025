<?php

namespace Drupal\single_content_sync;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\file\FileInterface;
use Drupal\single_content_sync\Event\ImportEvent;
use Drupal\single_content_sync\Event\ImportFieldEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Creates a helper service to import content.
 */
class ContentImporter implements ContentImporterInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The content sync helper.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The field processor plugin manager.
   *
   * @var \Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginManagerInterface
   */
  protected SingleContentSyncFieldProcessorPluginManagerInterface $fieldProcessorPluginManager;

  /**
   * The entity base fields processor plugin manager.
   *
   * @var \Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginManagerInterface
   */
  protected SingleContentSyncBaseFieldsProcessorPluginManagerInterface $entityBaseFieldsProcessorPluginManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * ContentExporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginManagerInterface $field_processor_plugin_manager
   *   The field processor plugin manager.
   * @param \Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginManagerInterface $entity_base_fields_processor_plugin_manager
   *   The entity base field processor plugin manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *  The stream wrapper manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    ModuleHandlerInterface $module_handler,
    FileSystemInterface $file_system,
    ContentSyncHelperInterface $content_sync_helper,
    TimeInterface $time,
    SingleContentSyncFieldProcessorPluginManagerInterface $field_processor_plugin_manager,
    SingleContentSyncBaseFieldsProcessorPluginManagerInterface $entity_base_fields_processor_plugin_manager,
    EventDispatcherInterface $event_dispatcher,
    StreamWrapperManagerInterface $stream_wrapper_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->contentSyncHelper = $content_sync_helper;
    $this->time = $time;
    $this->fieldProcessorPluginManager = $field_processor_plugin_manager;
    $this->entityBaseFieldsProcessorPluginManager = $entity_base_fields_processor_plugin_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function doImport(array $content): EntityInterface {
    $storage = $this->entityTypeManager->getStorage($content['entity_type']);
    $definition = $this->entityTypeManager->getDefinition($content['entity_type']);

    // Check if there is an existing entity with the identical uuid.
    $entity = $this->entityRepository->loadEntityByUuid($content['entity_type'], $content['uuid']);

    // If not, create a new instance of the entity.
    if (!$entity) {
      $values = [
        'uuid' => $content['uuid'],
      ];
      if ($bundle_key = $definition->getKey('bundle')) {
        $values[$bundle_key] = $content['bundle'];
      }

      $entity = $storage->create($values);
    }

    switch ($content['entity_type']) {
      case 'node':
        if ($entity instanceof RevisionLogInterface) {
          $entity->setNewRevision();
          $entity->setRevisionCreationTime($this->time->getCurrentTime());

          if (isset($content['base_fields']['revision_uid'])) {
            $entity->setRevisionUserId($content['base_fields']['revision_uid']);
          }

          if (isset($content['base_fields']['revision_log_message'])) {
            $entity->setRevisionLogMessage($content['base_fields']['revision_log_message']);
          }
        }
        break;

      case 'taxonomy_term':
        if ($content['base_fields']['parent']) {
          $entity->set('parent', $this->doImport($content['base_fields']['parent']));
        }
        break;

      case 'block_content':
        if (isset($content['base_fields']['enforce_new_revision']) && $entity instanceof RevisionableInterface) {
          $entity->setNewRevision();
        }
        break;
    }

    $importEvent = new ImportEvent($entity, $content);
    $this->eventDispatcher->dispatch($importEvent);
    $entity = $importEvent->getEntity();
    $content = $importEvent->getContent();

    // Import values from base fields.
    $this->importBaseValues($entity, $content['base_fields']);

    // Alter importing entity by using hook_content_import_entity_alter().
    $this->moduleHandler->alterDeprecated(
      'Deprecated as of single_content_sync 1.4.0; subscribe to \Drupal\single_content_sync\Event\ImportEvent instead. For implementing support of new entity types, implement SingleContentSyncBaseFieldsProcessor plugin.',
      'content_import_entity',
      $content,
      $entity
    );

    // Import values from custom fields.
    $this->importCustomValues($entity, $content['custom_fields']);
    $this->createOrUpdate($entity);

    // Import menu link when entity is created.
    if (isset($content['base_fields']['menu_link'])) {
      $this->doImport($content['base_fields']['menu_link']);
    }

    // Sync translations of the entity.
    if (isset($content['translations']) && $entity instanceof TranslatableInterface) {
      foreach ($content['translations'] as $langcode => $translation_content) {
        $translated_entity = !$entity->hasTranslation($langcode) ? $entity->addTranslation($langcode) : $entity->getTranslation($langcode);

        $this->importBaseValues($translated_entity, $translation_content['base_fields']);
        $this->importCustomValues($translated_entity, $translation_content['custom_fields']);

        $translated_entity->set('content_translation_source', $entity->language()->getId());
        $translated_entity->save();
      }
    }

    return $entity;
  }

  /**
   * Create a new entity or update existing one in the proper way.
   *
   * The entity that we are importing can be already created during the import
   * if that entity existed as a reference. We need to update this entity to
   * use the same id and enforce update instead of insert.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to create or update.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createOrUpdate(EntityInterface &$entity): void {
    $definition = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
    $existing_entity = $this->entityRepository->loadEntityByUuid($entity->getEntityTypeId(), $entity->uuid());

    if ($existing_entity) {
      $entity->{$definition->getKey('id')} = $existing_entity->id();
      $entity->enforceIsNew(FALSE);
    }

    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function importCustomValues(FieldableEntityInterface $entity, array $fields): void {
    foreach ($fields as $field_name => $field_value) {
      $this->setFieldValue($entity, $field_name, $field_value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importBaseValues(FieldableEntityInterface $entity, array $fields): void {
    $entityProcessor = $this->entityBaseFieldsProcessorPluginManager
      ->getEntityPluginInstance($entity->getEntityTypeId());

    $values = $entityProcessor->mapBaseFieldsValues($fields, $entity);

    // Set moderation state if it is supported for multiple entities.
    if (isset($fields['moderation_state'])) {
      $values['moderation_state'] = $fields['moderation_state'];
    }

    // Handle url alias if entity type supports it.
    if ($entity->hasField('path')) {
      $values['path'] = [
        'alias' => $fields['url'] ?? NULL,
        'pathauto' => 0,
      ];
    }

    // It's possible to export a single translation of the entity. In this case,
    // we need to load the translation of the entity to import the values.
    if (isset($values['langcode']) && $entity instanceof TranslatableInterface && $entity->hasTranslation($values['langcode'])) {
      $entity = $entity->getTranslation($values['langcode']);
    }

    foreach ($values as $field_name => $value) {
      $entity->set($field_name, $value);
    }

    // This might be useful if you want to save entity already after all base
    // fields are set.
    $entityProcessor->afterBaseValuesImport($fields, $entity);
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldValue(FieldableEntityInterface $entity, string $field_name, $field_value): void {
    if (!$entity->hasField($field_name)) {
      return;
    }

    // Clear value.
    if (is_null($field_value)) {
      $entity->set($field_name, $field_value);
      return;
    }

    // Get the field processor instance and call it to set the field value.
    $fieldProcessor = $this
      ->fieldProcessorPluginManager
      ->getFieldPluginInstance(
        $entity->getEntityTypeId(),
        $entity->bundle(),
        $field_name
      );

    // If field type is not supported, it will simply set value as it is.
    $fieldProcessor->importFieldValue($entity, $field_name, $field_value);

    // But you can alter it by implementing an event subscriber.
    $event = new ImportFieldEvent($entity, $field_name, $field_value);
    $this->eventDispatcher->dispatch($event);

    // Alter setting a field value during the import by using
    // hook_content_import_field_value().
    $this->moduleHandler->alterDeprecated('Deprecated as of single_content_sync 1.4.0; use ImportFieldEvent to update fields value before setting into the entity, implement SingleContentSyncFieldProcessor plugin instead to provide support for new field types.', 'content_import_field_value', $entity, $field_name, $field_value);
  }

  /**
   * {@inheritdoc}
   */
  public function importFromFile(string $file_real_path): EntityInterface {
    if (!file_exists($file_real_path)) {
      throw new \Exception('The requested file does not exist.');
    }

    $file_content = file_get_contents($file_real_path);

    if (!$file_content) {
      throw new \Exception('The requested file could not be downloaded.');
    }

    $content = $this->contentSyncHelper->validateYamlFileContent($file_content);

    return $this->doImport($content);
  }

  /**
   * Validate zip file before we run batch.
   *
   * @param string $path
   *   The local file path of the extracted zip file.
   *
   * @return bool
   *   TRUE if the valid YML file is found.
   */
  protected function isZipFileValid(string $path): bool {
    $info = pathinfo($path);

    if (is_file($path) && $info['extension'] === 'yml') {
      // Extra directory found, let's skip the operation and trigger
      // an error later.
      [, $directory] = explode('://', $info['dirname']);

      // If there are more than 3 parts, then there is an extra folder.
      // e.g. import/zip/uuid is correct one.
      if (count(explode('/', $directory)) > 3) {
        return FALSE;
      }

      // File name can't start with dot.
      if (strpos($info['filename'], '.') === 0) {
        return FALSE;
      }

      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function importFromZip(string $file_real_path): void {
    // Extract zip files to the unique local directory.
    $zip = $this->contentSyncHelper->createZipInstance($file_real_path);
    $import_directory = $this->contentSyncHelper->createImportDirectory();
    $zip->extract($import_directory);

    $content_file_path = NULL;
    $batch = [
      'title' => $this->t('Importing entities'),
      'operations' => [],
      'file' => '\Drupal\single_content_sync\ContentBatchImporter',
      'finished' => '\Drupal\single_content_sync\ContentBatchImporter::batchImportFinishedCallback',
    ];

    // Always import assets first, even if they're at the end of ZIP archive.
    foreach ($zip->listContents() as $zip_file) {
      $original_file_path = "{$import_directory}/{$zip_file}";

      // Ensure only files from assets folder are imported.
      if (strpos($zip_file, 'assets') === 0 && is_file($original_file_path)) {
        $batch['operations'][] = [
          '\Drupal\single_content_sync\ContentBatchImporter::batchImportAssets',
          [$original_file_path, $zip_file],
        ];
      }
    }

    foreach ($zip->listContents() as $zip_file) {
      $original_file_path = "{$import_directory}/{$zip_file}";

      if ($this->isZipFileValid($original_file_path)) {
        $content_file_path = $original_file_path;
        $batch['operations'][] = [
          '\Drupal\single_content_sync\ContentBatchImporter::batchImportFile',
          [$original_file_path],
        ];
      }
    }

    if (!$batch['operations']) {
      throw new \Exception(' Please check the structure of the zip file and ensure you do not have an extra parent directory.');
    }

    $batch['operations'][] = [
      '\Drupal\single_content_sync\ContentBatchImporter::cleanImportDirectory',
      [$import_directory],
    ];

    if (is_null($content_file_path)) {
      throw new \Exception('The content file in YAML format could not be found.');
    }

    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function importAssets(string $extracted_file_path, string $zip_file_path): void {
    // Validate schema dynamically based on folder structure.
    // For example assets/{schema}/file.png.
    if (preg_match('/^assets\/([^\/]*)\//', $zip_file_path, $matches)) {
      // Check if subfolder name represents a file schema.
      if ($this->streamWrapperManager->isValidScheme($matches[1])) {
        $destination = str_replace("assets/{$matches[1]}/", "{$matches[1]}://", $zip_file_path);
      }
    }

    // Fallback to public schema destination.
    if (!isset($destination)) {
      $destination = str_replace('assets/', 'public://', $zip_file_path);
    }

    $directory = $this->fileSystem->dirname($destination);
    $this->contentSyncHelper->prepareFilesDirectory($directory);
    $this->fileSystem->move($extracted_file_path, $destination, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * {@inheritdoc}
   */
  public function createStubEntity(array $entity): EntityInterface {
    $stub_entity_values = [
      'uuid' => $entity['uuid'],
    ];
    $definition = $this->entityTypeManager->getDefinition($entity['entity_type']);
    if ($bundle_key = $definition->getKey('bundle')) {
      $stub_entity_values[$bundle_key] = $entity['bundle'];
    }
    $stub_entity = $this->entityTypeManager->getStorage($entity['entity_type'])->create($stub_entity_values);
    $this->importBaseValues($stub_entity, $entity['base_fields']);
    $stub_entity->save();

    return $stub_entity;
  }

  /**
   * {@inheritdoc}
   */
  public function isFullEntity(array $entity): bool {
    return isset($entity['uuid'])
      && isset($entity['entity_type'])
      && isset($entity['base_fields'])
      && isset($entity['custom_fields']);
  }

}
