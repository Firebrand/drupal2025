<?php

namespace Drupal\single_content_sync\Commands;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentFileGeneratorInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\ContentSyncHelperInterface;
use Drupal\single_content_sync\Utility\CommandHelperInterface;
use Drush\Commands\DrushCommands;

/**
 * Defines the commands for exporting and importing content with drush.
 */
class ContentSyncCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * The content exporter service.
   *
   * @var \Drupal\single_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $contentExporter;

  /**
   * The content importer service.
   *
   * @var \Drupal\single_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $contentImporter;

  /**
   * The content file generator service.
   *
   * @var \Drupal\single_content_sync\ContentFileGeneratorInterface
   */
  protected ContentFileGeneratorInterface $fileGenerator;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The content sync helper service.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The command helper.
   *
   * @var \Drupal\single_content_sync\Utility\CommandHelperInterface
   */
  protected CommandHelperInterface $commandHelper;

  /**
   * Constructor of ContentSyncCommands.
   *
   * @param \Drupal\single_content_sync\ContentExporterInterface $content_exporter
   *   The content exporter service.
   * @param \Drupal\single_content_sync\ContentImporterInterface $content_importer
   *   The content importer service.
   * @param \Drupal\single_content_sync\ContentFileGeneratorInterface $file_generator
   *   The content file generator service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   * @param \Drupal\single_content_sync\Utility\CommandHelperInterface $command_helper
   *   The command helper.
   */
  public function __construct(
    ContentExporterInterface $content_exporter,
    ContentImporterInterface $content_importer,
    ContentFileGeneratorInterface $file_generator,
    MessengerInterface $messenger,
    ContentSyncHelperInterface $content_sync_helper,
    CommandHelperInterface $command_helper
  ) {
    parent::__construct();
    $this->contentExporter = $content_exporter;
    $this->contentImporter = $content_importer;
    $this->fileGenerator = $file_generator;
    $this->messenger = $messenger;
    $this->contentSyncHelper = $content_sync_helper;
    $this->commandHelper = $command_helper;
  }

  /**
   * Export all content of a given entity type.
   *
   * By default, running the content:export command as is
   * ('drush content:export'), all entities of type 'node'
   * will be exported, if allowed in the site's configuration,
   * and placed in the default export directory.
   *
   * @param string $entityType
   *   The entity type to export, e.g. 'node'. default is 'node'.
   * @param string $outputPath
   *   A string with the path to the directory the content should be
   *   exported to. Relative to Drupal root. If nothing will be passed content
   *   will be exported to default export directory.
   * @param array $options
   *   The options prefixed with -- to customize the execution of the command.
   *
   * @command content:export
   *
   * @option $translate Whether to include translations in the export.
   * @option $assets Whether to include assets in the export.
   * @option $all-content Will export all entity types.
   * @option $dry-run Will run the command in 'dry-run mode' and will not export anything.
   * @option $entities
   *    A comma separated string of entity id's or uuids to be exported.
   *    Combine with param $entityType in order to target the correct entities.
   *    if $all-content is used, it will take priority over this option.
   * @option $bundle
   *    A specific bundle for nodes or taxonomy.
   *
   * @usage content:export node /relative/output/path --entities="1,4,17" --translate --assets --all-content --dry-run --bundle="bundle_name"
   */
  public function exportEntitiesCommand(string $entityType = 'node', string $outputPath = '', array $options = [
    'translate' => FALSE,
    'assets' => FALSE,
    'all-content' => FALSE,
    'dry-run' => FALSE,
    'entities' => NULL,
    'bundle' => '',
  ]): void {
    [
      'translate' => $include_translations,
      'assets' => $include_assets,
      'all-content' => $all_allowed_content,
      'dry-run' => $is_dry_run,
      'entities' => $entity_ids_to_export,
      'bundle' => $bundle,
    ] = $options;
    $output_dir = $this->commandHelper->getRealDirectory($outputPath);

    // Create message to inform user how they are running the command.
    $message = $this->commandHelper->createMessageWithFlags("\nExecuting drush content:export {$entityType} {$outputPath}", $options);
    $this->output->write($message);
    $is_dry_run && $this->output->writeln($this->t("This is a dry run. No content will be exported.\n"));

    // Get the entities that will be exported.
    $entities = $this->commandHelper->getEntitiesToExport($entityType, $bundle, $all_allowed_content, $entity_ids_to_export);

    if (!$entities) {
      $this->messenger->addWarning($this->t('Nothing to export. Please check if your content is allowed to be exported in the configuration page of the module.'));
      return;
    }

    if ($is_dry_run) {
      // Generate the correct file name for the dry run.
      $file_name = count($entities) === 1 && !$include_assets
        ? "{$this->contentSyncHelper->generateContentFileName(reset($entities))}.yml"
        : sprintf('content-bulk-export-%s.zip', date('d_m_Y-H_i'));

      // Get the correct output path.
      $zip_path = "{$output_dir}/{$file_name}";
      $this->messenger->addStatus($this->t('Successfully exported the content. You can find the exported file at the following location: @path', [
        '@path' => $zip_path,
      ]));
      return;
    }

    // Generate YAML file if there is only 1 content to export without assets,
    // else, generate Zip file.
    $file = count($entities) === 1 && !$include_assets
      ? $this->fileGenerator->generateYamlFile(reset($entities), $include_translations)
      : $this->fileGenerator->generateBulkZipFile($entities, $include_translations, $include_assets);

    $this->contentSyncHelper->prepareFilesDirectory($output_dir);
    $file_target = $this->commandHelper->moveFile($file, $output_dir, explode('://', $file->getFileUri(), 2)[1]);

    $this->messenger->addStatus($this->t('Successfully exported the content. You can find the exported file at the following location: @path', [
      '@path' => $file_target,
    ]));
  }

  /**
   * Import content from a file at the given path.
   *
   * @param string $path
   *   The path to the file to import, relative to the docroot folder.
   *
   * @command content:import
   *
   * @usage content:import /path/to/file.zip
   */
  public function importEntitiesCommand(string $path): void {
    $message = $this->commandHelper->createMessageWithFlags("\nExecuting drush content:import {$path}");
    $this->output->write($message);

    $file_path = $this->commandHelper->getRealDirectory($path);
    $file_info = pathinfo($file_path);

    try {
      if (file_exists($file_path)) {
        $file_info['extension'] === 'zip' ? $this->commandHelper->commandZipImport($file_path) : $this->contentImporter->importFromFile($file_path);
        $this->messenger->addStatus($this->t('Successfully imported the content.'));
      }
      else {
        throw new \Exception("The file {$file_path} does not exist.");
      }
    }
    catch (\Exception $e) {
      $this->messenger->addError($e->getMessage());
    }
  }

}
