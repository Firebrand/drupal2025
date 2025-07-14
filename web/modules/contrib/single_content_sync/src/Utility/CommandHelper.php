<?php

namespace Drupal\single_content_sync\Utility;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\ContentSyncHelperInterface;

/**
 * Provides functionality to be used by CLI tools.
 */
class CommandHelper implements CommandHelperInterface {

  /**
   * The content importer service.
   *
   * @var \Drupal\single_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $contentImporter;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The content sync helper service.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The app root.
   *
   * @var string
   */
  protected string $root;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * Constructor of ContentSyncCommands.
   *
   * @param \Drupal\single_content_sync\ContentImporterInterface $content_importer
   *   The content importer service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param string $root
   *   The app root.
   */
  public function __construct(
    ContentImporterInterface $content_importer,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    ContentSyncHelperInterface $content_sync_helper,
    FileSystemInterface $file_system,
    EntityRepositoryInterface $entity_repository,
    string $root
  ) {
    $this->contentImporter = $content_importer;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->contentSyncHelper = $content_sync_helper;
    $this->fileSystem = $file_system;
    $this->entityRepository = $entity_repository;
    $this->root = $root;
  }

  /**
   * {@inheritDoc}
   */
  public function createMessageWithFlags(string $message, array $options = []): string {
    $include_translations = $options['translate'] ?? FALSE;
    $include_assets = $options['assets'] ?? FALSE;
    $all_allowed_content = $options['all-content'] ?? FALSE;
    $is_dry_run = $options['dry-run'] ?? FALSE;
    $entity_ids_to_export = $options['entities'] ?? NULL;

    $flags = $include_translations ? ' --translate' : '';
    $flags .= $include_assets ? ' --assets' : '';
    $flags .= $all_allowed_content ? ' --all-content' : '';
    $flags .= $is_dry_run ? ' --dry-run' : '';
    $flags .= $entity_ids_to_export ? " --entities=\"{$entity_ids_to_export}\"" : '';

    return "{$message}{$flags}\n\n";
  }

  /**
   * {@inheritDoc}
   */
  public function commandZipImport(string $file_path): void {
    $this->contentImporter->importFromZip($file_path);
    drush_backend_batch_process();
  }

  /**
   * {@inheritdoc}
   */
  public function getEntitiesToExport(string $entity_type = 'node', string $bundle = '', bool $all_allowed_content = FALSE, string $entity_ids_to_export = NULL): array {
    $allowed_entity_types = $this->configFactory->get('single_content_sync.settings')->get('allowed_entity_types');

    if ($entity_ids_to_export) {
      return isset($allowed_entity_types[$entity_type])
        ? $this->getSelectedEntities($entity_type, $entity_ids_to_export)
        : [];
    }

    // Check if specific entity type is allowed.
    if (!$all_allowed_content) {
      if (isset($allowed_entity_types[$entity_type])) {
        $allowed_bundles = $allowed_entity_types[$entity_type];
        $allowed_entity_types = [$entity_type => $allowed_bundles];

        // Check if specific bundle of specific entity type is allowed.
        if ($bundle) {
          $is_bundle_allowed = !$allowed_bundles || in_array($bundle, $allowed_bundles, TRUE);
          $allowed_entity_types = $is_bundle_allowed
            ? [$entity_type => [$bundle]]
            : [];
        }

      }
      else {
        // Specific entity type is not allowed, nothing to export.
        $allowed_entity_types = [];
      }
    }

    $entities = [];
    foreach ($allowed_entity_types as $entity_type_id => $bundles) {
      $properties = [];

      // Do not export inline blocks (it's exported as part of LB field).
      if ($entity_type_id === 'block_content') {
        $properties['reusable'] = TRUE;
      }

      // Filter content by specific bundles.
      if ($bundles) {
        $definition = $this->entityTypeManager->getDefinition($entity_type_id);
        if (!$definition->hasKey('bundle')) {
          throw new \Exception("The entity type {$entity_type_id} does not have bundles");
        }

        $properties[$definition->getKey('bundle')] = $bundles;
      }

      $entities = array_merge(
        $entities,
        $this->entityTypeManager->getStorage($entity_type_id)->loadByProperties($properties)
      );
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getSelectedEntities(string $entity_type, string $ids_to_export): array {
    $entity_ids = explode(',', $ids_to_export);
    $entities = [];

    $storage = $this->entityTypeManager->getStorage($entity_type);
    foreach ($entity_ids as $id) {
      $entity = is_numeric($id)
        ? $storage->load($id)
        : $this->entityRepository->loadEntityByUuid($entity_type, $id);

      if (!$entity) {
        throw new \Exception("The export couldn't be completed because the --entities contain invalid id: {$id}");
      }
      $entities[] = $entity;
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function moveFile(FileInterface $file, string $output_dir, string $file_target): string {
    if (!$output_dir) {
      return $file_target;
    }

    $target_base_name = basename($file_target);
    $moved_file_path = "{$output_dir}/{$target_base_name}";

    return $this->fileSystem->move($file->getFileUri(), $moved_file_path);
  }

  /**
   * {@inheritdoc}
   */
  public function getRealDirectory(string $output_path): string {
    $grandparent_path = $this->root;
    if (!$output_path) {
      return $grandparent_path . '/scs-export';
    }

    if (substr($output_path, 0, strlen('./')) === './') {
      $output_path = substr($output_path, 2);
    }

    $relative_dir = rtrim($output_path, '/');
    $parent_count = substr_count($relative_dir, '../');
    $grandparent_path = !!$parent_count ? dirname($grandparent_path, $parent_count) : $grandparent_path;
    $trimmed_relative_dir = ltrim(str_replace('../', '', $relative_dir), '/');
    $output_dir = "{$grandparent_path}/{$trimmed_relative_dir}";

    return $output_dir;
  }

}
