<?php

namespace Drupal\schwab_content_sync\Plugin\SchwabContentSyncFieldProcessor;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\schwab_content_sync\ContentExporterInterface;
use Drupal\schwab_content_sync\ContentImporterInterface;
use Drupal\schwab_content_sync\SchwabContentSyncFieldProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the text field processor plugin.
 *
 * @SchwabContentSyncFieldProcessor(
 *   id = "text_field",
 *   deriver = "Drupal\schwab_content_sync\Plugin\Derivative\SchwabContentSyncFieldProcessor\TextFieldDeriver",
 * )
 */
class TextField extends SchwabContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The content exporter service.
   *
   * @var \Drupal\schwab_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $exporter;

  /**
   * The content importer service.
   *
   * @var \Drupal\schwab_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $importer;

  /**
   * The entity repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The module private temporary storage.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected PrivateTempStore $privateTempStore;

  /**
   * Constructs new EntityReference plugin instance.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\schwab_content_sync\ContentExporterInterface $exporter
   *   The content exporter service.
   * @param \Drupal\schwab_content_sync\ContentImporterInterface $importer
   *   The content importer service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\TempStore\PrivateTempStore $private_temp_store
   *   The module private temporary storage.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContentExporterInterface $exporter,
    ContentImporterInterface $importer,
    EntityRepositoryInterface $entity_repository,
    PrivateTempStore $private_temp_store,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
    $this->entityRepository = $entity_repository;
    $this->privateTempStore = $private_temp_store;
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
      $container->get('schwab_content_sync.importer'),
      $container->get('entity.repository'),
      $container->get('schwab_content_sync.store'),
    );
  }

  /**
   * {@inheritdoc}
   * 
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $field
   *   The field item list.
   *
   * @return array<int, array<string, mixed>>
   *   The exported field values.
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    /** @var array<int, array<string, mixed>> $value */
    $value = $field->getValue();

    foreach ($value as &$item) {
      if (!isset($item['value']) || !is_string($item['value'])) {
        continue;
      }
      
      $text = $item['value'];

      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      /** @var array<int, array<string, mixed>> $embed_entities */
      $embed_entities = [];

      $mediaNodes = $xpath->query('//drupal-media[@data-entity-type="media" and normalize-space(@data-entity-uuid)!=""]');
      if ($mediaNodes !== false) {
        foreach ($mediaNodes as $node) {
          /** @var \DOMElement $node */
          $uuid = $node->getAttribute('data-entity-uuid');
          $media = $this->entityRepository->loadEntityByUuid('media', $uuid);

          if ($media instanceof MediaInterface) {
            $embed_entities[] = $this->exporter->doExportToArray($media);
          }
        }
      }

      $linkNodes = $xpath->query('//a[normalize-space(@href)!="" and normalize-space(@data-entity-type)!="" and normalize-space(@data-entity-uuid)!=""]');
      if ($linkNodes !== false) {
        foreach ($linkNodes as $element) {
          /** @var \DOMElement $element */
          $entity_type_id = $element->getAttribute('data-entity-type');
          $uuid = $element->getAttribute('data-entity-uuid');
          $linked_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);

          // Skip the process if the link is broken and entity could not be found.
          if (!($linked_entity instanceof FieldableEntityInterface)) {
            continue;
          }

          if (!$this->exporter->isReferenceCached($linked_entity)) {
            $embed_entities[] = $this->exporter->doExportToArray($linked_entity);
          }
          else {
            $embed_entities[] = [
              'uuid' => $linked_entity->uuid(),
              'entity_type' => $linked_entity->getEntityTypeId(),
              'base_fields' => $this->exporter->exportBaseValues($linked_entity),
              'bundle' => $linked_entity->bundle(),
            ];
          }
        }
      }

      $fileNodes = $xpath->query('//img[@data-entity-type="file" and normalize-space(@data-entity-uuid)!=""]');
      if ($fileNodes !== false) {
        foreach ($fileNodes as $node) {
          /** @var \DOMElement $node */
          $uuid = $node->getAttribute('data-entity-uuid');
          $file = $this->entityRepository->loadEntityByUuid('file', $uuid);

          // File entity does not need a stub entity, so we do a full export.
          if ($file instanceof FileInterface) {
            $embed_entities[] = $this->exporter->doExportToArray($file);
          }
        }
      }

      $item['embed_entities'] = $embed_entities;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   * 
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to import the field value into.
   * @param string $fieldName
   *   The field name.
   * @param array<int, array<string, mixed>> $value
   *   The field values to import.
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    foreach ($value as $delta => $item) {
      /** @var array<int, array<string, mixed>> $embed_entities */
      $embed_entities = $item['embed_entities'] ?? [];

      if (array_key_exists('embed_entities', $item)) {
        unset($value[$delta]['embed_entities']);
      }

      foreach ($embed_entities as $embed_entity) {
        if ($this->importer->isFullEntity($embed_entity)) {
          $this->importer->doImport($embed_entity);
        }
        else {
          if (isset($embed_entity['entity_type']) && isset($embed_entity['uuid']) 
              && is_string($embed_entity['entity_type']) && is_string($embed_entity['uuid'])) {
            $referenced_entity = $this
              ->entityRepository
              ->loadEntityByUuid($embed_entity['entity_type'], $embed_entity['uuid']);

            // Create a stub entity without custom field values.
            if (!$referenced_entity) {
              $this->importer->createStubEntity($embed_entity);
            }
          }
        }
      }

      if (!isset($item['value']) || !is_string($item['value'])) {
        continue;
      }

      $text = $item['value'];
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      $needs_update = FALSE;

      $linkNodes = $xpath->query('//a[normalize-space(@href)!="" and normalize-space(@data-entity-type)!="" and normalize-space(@data-entity-uuid)!=""]');
      if ($linkNodes !== false) {
        foreach ($linkNodes as $element) {
          /** @var \DOMElement $element */
          $entity_type_id = $element->getAttribute('data-entity-type');
          $uuid = $element->getAttribute('data-entity-uuid');
          $linked_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);

          if ($linked_entity instanceof FieldableEntityInterface) {
            $needs_update = TRUE;
            $element->setAttribute('href', $linked_entity->toUrl('canonical', [
              'alias' => TRUE,
              'path_processing' => FALSE,
            ])->toString());
          }
        }
      }

      if ($needs_update) {
        $value[$delta]['value'] = Html::serialize($dom);
      }
    }

    $entity->set($fieldName, $value);
  }

}