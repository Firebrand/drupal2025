<?php

namespace Drupal\schwab_content_sync;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Archiver\ArchiverInterface;
use Drupal\Core\Archiver\ArchiverManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Creates a helper service with useful methods during content sync.
 */
class ContentSyncHelper implements ContentSyncHelperInterface {

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The file system.
   *
   * @var \Drupal\file\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The archiver manager.
   *
   * @var \Drupal\Core\Archiver\ArchiverManager
   */
  protected ArchiverManager $archiverManager;

  /**
   * The uuid service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuid;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * ContentSyncHelper constructor.
   *
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The uuid generator.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\file\FileRepositoryInterface $file_repository
   *   The file repository.
   * @param \Drupal\Core\Archiver\ArchiverManager $archiver_manager
   *   The archive manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   */
  public function __construct(UuidInterface $uuid, FileSystemInterface $file_system, FileRepositoryInterface $file_repository, ArchiverManager $archiver_manager, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->uuid = $uuid;
    $this->fileSystem = $file_system;
    $this->fileRepository = $file_repository;
    $this->archiverManager = $archiver_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->entityRepository = $entity_repository;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFilesDirectory(string &$directory): void {
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }

  /**
   * {@inheritdoc}
   */
  public function saveFileContentTemporary(string $content, string $destination): FileInterface {
    $file = $this->fileRepository->writeData($content, $destination);
    $file->setTemporary();
    $file->save();

    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function createImportDirectory(): string {
    $schema = $this->getImportDirectorySchema();
    $uuid = $this->uuid->generate();

    $import_directory = "{$schema}://import/zip/{$uuid}";
    $this->prepareFilesDirectory($import_directory);

    return $import_directory;
  }

  /**
   * {@inheritdoc}
   */
  public function createZipInstance(string $file_real_path, int $flags = 0): ArchiverInterface {
    $instance = $this->archiverManager->getInstance([
      'filepath' => $file_real_path,
      'flags' => $flags,
    ]);
    
    if (!$instance instanceof ArchiverInterface) {
      throw new \RuntimeException('Failed to create archiver instance.');
    }
    
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function generateContentFileName(EntityInterface $entity): string {
    return implode('-', [
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->uuid(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   */
  public function validateYamlFileContent(string $content): array {
    $decoded = Yaml::decode($content);
    // Validate YAML format structure.
    if (!is_array($decoded)) {
      throw new \Exception('YAML is not valid.');
    }

    /** @var array<string, mixed> $decoded */

    // Validate required YAML properties.
    if (!isset($decoded['uuid']) || !isset($decoded['entity_type']) || !isset($decoded['bundle']) || !isset($decoded['base_fields']) || !isset($decoded['custom_fields'])) {
      throw new \Exception('The content of the YAML file is not valid. Make sure there are uuid, entity_type, base_fields, and custom_fields properties.');
    }

    // Validate Site UUID value to prevent import on different site.
    if ($this->siteUuidCheckEnabled()
      && !empty($decoded['site_uuid'])
      && $this->getSiteUuid() !== $decoded['site_uuid']) {
      throw new \Exception('Content source site has another UUID than current one (destination). Make sure that content has been exported from the same instance of the site or disable Site UUID check.');
    }

    // Validate that entity type and bundle exists.
    if (!is_string($decoded['entity_type'])) {
      throw new \Exception('Entity type must be a string.');
    }
    
    if (!is_string($decoded['bundle'])) {
      throw new \Exception('Bundle must be a string.');
    }
    
    try {
      $definition = $this->entityTypeManager->getDefinition($decoded['entity_type']);
    }
    catch (\Exception $e) {
      throw new \Exception(sprintf('The content of the YAML file is not valid. Make sure that entity type "%s" of the imported content does exist on your site.', $decoded['entity_type']));
    }

    // Validate that bundle exists.
    $available_bundles = $this->entityTypeBundleInfo->getBundleInfo($decoded['entity_type']);
    if (empty($available_bundles[$decoded['bundle']])) {
      throw new \Exception(sprintf('The content of the YAML file is not valid. Make sure that bundle "%s" of the imported content with entity type "%s" does exist on your site.', $decoded['bundle'], $decoded['entity_type']));
    }

    return $decoded;
  }

  /**
   * {@inheritdoc}
   */
  public function getFileRealPathById(int $fid): string {
    $storage = $this->entityTypeManager->getStorage('file');

    /** @var \Drupal\file\FileInterface|null $file */
    $file = $storage->load($fid);

    if (!$file instanceof FileInterface) {
      throw new \Exception('The requested file could not be found.');
    }

    $file_uri = $file->getFileUri();
    if (!is_string($file_uri)) {
      throw new \Exception('File URI is not valid.');
    }

    $real_path = $this->fileSystem->realpath($file_uri);
    if ($real_path === FALSE) {
      throw new \Exception('Could not determine real path for file.');
    }

    return $real_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLanguageEntity(ParameterBag $parameters): EntityInterface {
    $iterator = $parameters->getIterator();
    $current = $iterator->current();
    
    if (!$current instanceof EntityInterface) {
      throw new \InvalidArgumentException('No valid entity found in parameters.');
    }
    
    $entity_uuid = $current->uuid();
    if (!is_string($entity_uuid)) {
      throw new \RuntimeException('Entity UUID is not valid.');
    }
    
    $entity_type_id = $current->getEntityTypeId();
    
    $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $entity_uuid);
    
    if (!$entity instanceof EntityInterface) {
      throw new \RuntimeException('Entity could not be loaded.');
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity): bool {
    $allowed_entity_types = $this->configFactory->get('schwab_content_sync.settings')->get('allowed_entity_types');
    
    if (!is_array($allowed_entity_types)) {
      return FALSE;
    }
    
    $entity_type_id = $entity->getEntityTypeId();

    return array_key_exists($entity_type_id, $allowed_entity_types) &&
      (is_array($allowed_entity_types[$entity_type_id]) && 
       ($allowed_entity_types[$entity_type_id] === [] || in_array($entity->bundle(), $allowed_entity_types[$entity_type_id], TRUE))) &&
      $entity->getEntityType()->hasLinkTemplate('single-content:export') &&
      $entity->access('single-content:export');
  }

  /**
   * {@inheritdoc}
   */
  public function siteUuidCheckEnabled(): bool {
    return !empty($this->configFactory->get('schwab_content_sync.settings')->get('site_uuid_check'));
  }

  /**
   * {@inheritdoc}
   */
  public function getSiteUuid(): string {
    $uuid = $this->configFactory->get('system.site')->get('uuid');
    
    if (!is_string($uuid)) {
      throw new \RuntimeException('Site UUID is not configured.');
    }
    
    return $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public function getExportDirectorySchema(): string {
    $settings = $this->configFactory->get('schwab_content_sync.settings');
    $schema = $settings->get('export_directory_schema');
    
    if (!is_string($schema) || empty($schema)) {
      return 'temporary';
    }
    
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getImportDirectorySchema(): string {
    $settings = $this->configFactory->get('schwab_content_sync.settings');
    $schema = $settings->get('import_directory_schema');
    
    if (!is_string($schema) || empty($schema)) {
      return 'temporary';
    }
    
    return $schema;
  }

}