<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the text field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "text_field",
 *   deriver = "Drupal\single_content_sync\Plugin\Derivative\SingleContentSyncFieldProcessor\TextFieldDeriver",
 * )
 */
class TextField extends SingleContentSyncFieldProcessorPluginBase implements ContainerFactoryPluginInterface {

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
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\TempStore\PrivateTempStore $private_temp_store
   *    The module private temporary storage.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContentExporterInterface $exporter,
    ContentImporterInterface $importer,
    EntityRepositoryInterface $entity_repository,
    PrivateTempStore $private_temp_store
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
    $this->entityRepository = $entity_repository;
    $this->privateTempStore = $private_temp_store;
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
      $container->get('entity.repository'),
      $container->get('single_content_sync.store'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    $value = $field->getValue();

    foreach ($value as &$item) {
      $text = $item['value'];

      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      $embed_entities = [];

      foreach ($xpath->query('//drupal-media[@data-entity-type="media" and normalize-space(@data-entity-uuid)!=""]') as $node) {
        /** @var \DOMElement $node */
        $uuid = $node->getAttribute('data-entity-uuid');
        $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
        assert($media === NULL || $media instanceof MediaInterface);

        if ($media) {
          $embed_entities[] = $this->exporter->doExportToArray($media);
        }
      }

      foreach ($xpath->query('//a[normalize-space(@href)!="" and normalize-space(@data-entity-type)!="" and normalize-space(@data-entity-uuid)!=""]') as $element) {
        /** @var \DOMElement $element */
        $entity_type_id = $element->getAttribute('data-entity-type');
        $uuid = $element->getAttribute('data-entity-uuid');
        $linked_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);

        // Skip the process if the link is broken and entity could not be found.
        if (!$linked_entity instanceof FieldableEntityInterface) {
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

      foreach ($xpath->query('//img[@data-entity-type="file" and normalize-space(@data-entity-uuid)!=""]') as $node) {
        /** @var \DOMElement $node */
        $uuid = $node->getAttribute('data-entity-uuid');
        $file = $this->entityRepository->loadEntityByUuid('file', $uuid);
        assert($file === NULL || $file instanceof FileInterface);

        // File entity does not need a stub entity, so we do a full export.
        if ($file) {
          $embed_entities[] = $this->exporter->doExportToArray($file);
        }
      }

      $item['embed_entities'] = $embed_entities;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    foreach ($value as $delta => $item) {
      $embed_entities = $item['embed_entities'] ?? [];

      if (array_key_exists('embed_entities', $item)) {
        unset($value[$delta]['embed_entities']);
      }

      foreach ($embed_entities as $embed_entity) {
        if ($this->importer->isFullEntity($embed_entity)) {
          $this->importer->doImport($embed_entity);
        }
        else {
          $referenced_entity = $this
            ->entityRepository
            ->loadEntityByUuid($embed_entity['entity_type'], $embed_entity['uuid']);

          // Create a stub entity without custom field values.
          if (!$referenced_entity) {
            $this->importer->createStubEntity($embed_entity);
          }
        }
      }

      $text = $item['value'];
      $dom = Html::load($text);
      $xpath = new \DOMXPath($dom);
      $needs_update = FALSE;

      foreach ($xpath->query('//a[normalize-space(@href)!="" and normalize-space(@data-entity-type)!="" and normalize-space(@data-entity-uuid)!=""]') as $element) {
        /** @var \DOMElement $element */
        $entity_type_id = $element->getAttribute('data-entity-type');
        $uuid = $element->getAttribute('data-entity-uuid');
        $linked_entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);
        assert($linked_entity === NULL || $linked_entity instanceof FieldableEntityInterface);

        if ($linked_entity) {
          $needs_update = TRUE;
          $element->setAttribute('href', $linked_entity->toUrl('canonical', [
            'alias' => TRUE,
            'path_processing' => FALSE,
          ])->toString());
        }
      }

      if ($needs_update) {
        $value[$delta]['value'] = Html::serialize($dom);
      }
    }

    $entity->set($fieldName, $value);
  }

}
