<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for layout section field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "layout_section",
 *   label = @Translation("Layout section field processor"),
 *   field_type = "layout_section",
 * )
 */
class LayoutSection extends SingleContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The inline block usage service.
   *
   * @var \Drupal\layout_builder\InlineBlockUsageInterface|null
   */
  protected ?InlineBlockUsageInterface $inlineBlockUsage;

  /**
   * Constructs new EntityReference plugin instance.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\layout_builder\InlineBlockUsageInterface|null $inline_block_usage
   *   The optional inline block usage service. The dependency is optional
   *   to allow plugin to instantiate if the Layout Builder module is not
   *   enabled.
   */
  public function __construct(
    array $configuration,
   $plugin_id,
   $plugin_definition,
    ContentExporterInterface $exporter,
    ContentImporterInterface $importer,
    EntityTypeManagerInterface $entityTypeManager,
    ModuleHandlerInterface $moduleHandler,
    ?InlineBlockUsageInterface $inline_block_usage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
    $this->inlineBlockUsage = $inline_block_usage;
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
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('inline_block.usage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $block_storage = $this->entityTypeManager->getStorage('block_content');
    $block_list = [];
    $sections = [];

    foreach ($field->getValue() as $section_array) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = $section_array['section'];
      $sections[] = $section->toArray();
      $components = $section->getComponents();
      foreach ($components as $component) {
        if ($component->getPlugin() instanceof InlineBlock) {
          $configuration = $component->toArray()['configuration'];
          $block = NULL;

          if (isset($configuration['block_serialized'])) {
            $block = unserialize($configuration['block_serialized'], [
              'allowed_classes' => [BlockContent::class],
            ]);
          }
          elseif (isset($configuration['block_revision_id'])) {
            $block = $block_storage->loadRevision($configuration['block_revision_id']);
          }

          if ($block) {
            $block_list[] = $this->exporter->doExportToArray($block);
          }
        }
      }
    }

    return [
      'sections' => $sections,
      'blocks' => $block_list,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    if (!$this->moduleHandler->moduleExists('layout_builder')) {
      throw new \Exception('The layout could not be imported due to the layout_builder module was disabled.');
    }
    if (!$this->inlineBlockUsage) {
      // This should never happen if the module is enabled, but we've declared
      // the property as nullable so there's a safety check here.
      throw new \Exception('The inline_block.usage service is not available.');
    }

    $imported_blocks = [];
    $block_list = $value['blocks'] ?? [];

    // Prepare entity to have id in the database to be used for inline block
    // usages.
    if ($block_list) {
      $this->importer->createOrUpdate($entity);
    }

    foreach ($block_list as $block) {
      /** @var \Drupal\block_content\BlockContentInterface $new_block */
      $new_block = $this->importer->doImport($block);

      if (!$this->inlineBlockUsage->getUsage($new_block->id())) {
        $this->inlineBlockUsage->addUsage($new_block->id(), $entity);
      }

      $old_revision_id = $block['base_fields']['block_revision_id'];
      $imported_blocks[$old_revision_id] = $new_block->getRevisionId();
    }

    // Get unserialized version of each section.
    /** @var \Drupal\layout_builder\Section[] $sections */
    // Get unserialized version of each section, supporting both old and new formats.
    if (isset($value['sections']) && is_string($value['sections'])) {
      // Backward compatibility: decode and unserialize old base64 format.
      $base64_sections = base64_decode($value['sections']);
      /** @var \Drupal\layout_builder\Section[] $sections */
      $sections = array_map(function (string $section) {
        return unserialize($section, [
          'allowed_classes' => [Section::class, SectionComponent::class],
        ]);
      }, explode('|', $base64_sections));
    } elseif (isset($value['sections']) && is_array($value['sections'])) {
      // New format: Convert stored section arrays back into Section objects.
      /** @var \Drupal\layout_builder\Section[] $sections */
      $sections = array_map(function (array $section_array) {
        return Section::fromArray($section_array);
      }, $value['sections']);
    } else {
      // If sections are missing or invalid, throw an exception.
      throw new \Exception('Invalid or missing section data format.');
    }   

    foreach ($sections as $section) {
      $section_components = $section->getComponents();
      foreach ($section_components as $component) {
        if ($component->getPlugin() instanceof InlineBlock) {
          $configuration = $component->toArray()['configuration'];
          if (isset($configuration['block_revision_id']) && isset($imported_blocks[$configuration['block_revision_id']])) {
            // Replace the old revision id with a new revision id.
            $configuration['block_revision_id'] = $imported_blocks[$configuration['block_revision_id']];
            $component->setConfiguration($configuration);
          }
        }
      }
    }

    $entity->set($fieldName, $sections);
  }

}
