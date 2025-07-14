<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\FieldConfigInterface;
use Drupal\single_content_sync\Event\ExportEvent;
use Drupal\single_content_sync\Event\ExportFieldEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Define a service to export content.
 */
class ContentExporter implements ContentExporterInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Whether to extract translations.
   *
   * @var bool
   */
  protected bool $extractTranslationsMode = FALSE;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * Local cache variable to store the reference info of entities.
   *
   * @var array
   */
  private array $entityReferenceCache = [];

  /**
   * Local cache variable to store the exported output of entities.
   *
   * @var array
   */
  private array $entityOutputCache = [];

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The content sync helper.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The content sync field processor plugin manager.
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
   * ContentExporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginManagerInterface $field_processor_plugin_manager
   *   The field processor plugin manager.
   * @param \Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginManagerInterface $entity_base_fields_processor_plugin_manager
   *   The entity base fields processor plugin manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    MessengerInterface $messenger,
    LanguageManagerInterface $language_manager,
    ContentSyncHelperInterface $content_sync_helper,
    EntityRepositoryInterface $entity_repository,
    SingleContentSyncFieldProcessorPluginManagerInterface $field_processor_plugin_manager,
    SingleContentSyncBaseFieldsProcessorPluginManagerInterface $entity_base_fields_processor_plugin_manager,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
    $this->languageManager = $language_manager;
    $this->contentSyncHelper = $content_sync_helper;
    $this->entityRepository = $entity_repository;
    $this->fieldProcessorPluginManager = $field_processor_plugin_manager;
    $this->entityBaseFieldsProcessorPluginManager = $entity_base_fields_processor_plugin_manager;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * Generates a cache key based on the entity's entity type id and uuid.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object for which to generate a cache key.
   *
   * @return string
   *   A string representing the entity's cache key.
   */
  protected function generateCacheKey(FieldableEntityInterface $entity): string {
    $hasTranslations = $this->extractTranslationsMode ? 'has_trans' : 'no_trans';

    return implode('-', [
      $entity->getEntityTypeId(),
      $entity->uuid(),
      $hasTranslations,
    ]);
  }

  /**
   * Verifies whether a given entity is present in the entityOutputCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be verified in the cache.
   *
   * @return bool
   *   If the entity is present in the entityOutputCache will return TRUE,
   *   else will return FALSE.
   */
  protected function isOutputCached(FieldableEntityInterface $entity): bool {
    return array_key_exists($this->generateCacheKey($entity), $this->entityOutputCache);
  }

  /**
   * Verifies whether a given entity is present in the entityReferenceCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be verified in the cache.
   *
   * @return bool
   *   If the entity is present in the entityReferenceCache will return TRUE,
   *   else will return FALSE.
   */
  public function isReferenceCached(FieldableEntityInterface $entity): bool {
    return array_key_exists($this->generateCacheKey($entity), $this->entityReferenceCache);
  }

  /**
   * Adds a given entity to the entityOutputCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be added to the entityOuputCache.
   * @param array $output
   *   The exported content output.
   */
  protected function addEntityToOutputCache(FieldableEntityInterface $entity, array $output): void {
    $id = $this->generateCacheKey($entity);
    $this->entityOutputCache[$id] = $output;
  }

  /**
   * Adds a given entity to the entityReferenceCache.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to be added to the entityReferenceCache.
   */
  protected function addEntityToReferenceCache(FieldableEntityInterface $entity): void {
    $id = $this->generateCacheKey($entity);
    $this->entityReferenceCache[$id] = $id;
  }

  /**
   * {@inheritdoc}
   */
  public function doExportToArray(FieldableEntityInterface $entity): array {
    // Add the entity to the entityReferenceCache array.
    $this->addEntityToReferenceCache($entity);

    // If the output was already cached, return the cached output.
    // Continue the method if not.
    if ($this->isOutputCached($entity)) {
      return $this->entityOutputCache[$this->generateCacheKey($entity)];
    }

    $output = [
      'uuid' => $entity->uuid(),
      'entity_type' => $entity->getEntityTypeId(),
      'bundle' => $entity->bundle(),
      'base_fields' => $this->exportBaseValues($entity),
      'custom_fields' => $this->exportCustomValues($entity),
    ];

    $exportEvent = new ExportEvent($entity, $output);
    $this->eventDispatcher->dispatch($exportEvent);
    $output = $exportEvent->getContent();

    // Alter value by using hook_content_export_entity_alter().
    $this->moduleHandler->alterDeprecated(
      'Deprecated as of single_content_sync 1.4.0; subscribe to \Drupal\single_content_sync\Event\ExportEvent instead. For implementing support of new entity types, implement SingleContentSyncBaseFieldsProcessor plugin.',
      'content_export_entity',
      $output['base_fields'],
      $entity,
    );

    // Display a message when we don't support base fields export for specific
    // entity type.
    if (!$output['base_fields']) {
      $this->messenger->addWarning($this->t('Base fields of "@entity_type" is not exportable out-of-the-box. Check README for a workaround.', [
        '@entity_type' => $output['entity_type'],
      ]));
    }

    // Extract translations.
    if ($this->extractTranslationsMode && $entity->isTranslatable()) {
      $translations = $entity->getTranslationLanguages();

      if (count($translations)) {
        foreach ($translations as $language) {
          // Skip the default loaded translation.
          if ($entity->language()->getId() === $language->getId()) {
            continue;
          }

          $translated_entity = $entity->getTranslation($language->getId());

          $output['translations'][$language->getId()]['base_fields'] = $this->exportBaseValues($translated_entity);
          $output['translations'][$language->getId()]['custom_fields'] = $this->exportCustomValues($translated_entity, TRUE);

          $exportEvent = new ExportEvent($translated_entity, $output['translations'][$language->getId()], TRUE);
          $this->eventDispatcher->dispatch($exportEvent);
          $output['translations'][$language->getId()] = $exportEvent->getContent();
        }
      }
    }

    // Add the output to the entityOutputCache array.
    $this->addEntityToOutputCache($entity, $output);

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function doExportToYml(FieldableEntityInterface $entity, $extract_translations = FALSE): string {
    // Remember the extract translation option to use it later.
    $this->extractTranslationsMode = (bool) $extract_translations;

    // Export content to array first.
    $output['site_uuid'] = $this->contentSyncHelper->getSiteUuid();
    $output += $this->doExportToArray($entity);

    return Yaml::encode($output);
  }

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    $entityProcessor = $this->entityBaseFieldsProcessorPluginManager
      ->getEntityPluginInstance($entity->getEntityTypeId());

    $base_fields = $entityProcessor->exportBaseValues($entity);

    // Support moderation state for multiple entity types.
    if ($entity->hasField('moderation_state') && !$entity->get('moderation_state')->isEmpty()) {
      $base_fields['moderation_state'] = $entity->get('moderation_state')->value;
    }

    // Support path field for multiple entity types.
    if ($entity->hasField('path')) {
      $base_fields['url'] = $entity->get('path')->alias;
    }

    return $base_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function exportCustomValues(FieldableEntityInterface $entity, bool $check_translated_fields_only = FALSE): array {
    $fields = $check_translated_fields_only ? $entity->getTranslatableFields() : $entity->getFields();
    $values = [];

    foreach ($fields as $field) {
      if ($field->getFieldDefinition() instanceof FieldConfigInterface) {
        $values[$field->getName()] = !$field->isEmpty() ? $this->getFieldValue($field) : NULL;
      }
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldValue(FieldItemListInterface $field) {
    $fieldProcessor = $this
      ->fieldProcessorPluginManager
      ->getFieldPluginInstance(
        $field->getEntity()->getEntityTypeId(),
        $field->getEntity()->bundle(),
        $field->getName()
      );

    // If field type is not supported, it will simply get the value as it is.
    $value = $fieldProcessor->exportFieldValue($field);

    // Trigger event to alter the field export.
    $event = new ExportFieldEvent($field, $value);
    $this->eventDispatcher->dispatch($event);
    $value = $event->getFieldValue();

    // Alter value by using hook_content_export_field_value_alter().
    $this->moduleHandler->alterDeprecated('Deprecated as of single_content_sync 1.4.0; implement SingleContentSyncFieldProcessor plugin instead to provide support for new field types or subscribe to ExportFieldEvent event in your event subscriber.', 'content_export_field_value', $value, $field);

    return $value;
  }

}
