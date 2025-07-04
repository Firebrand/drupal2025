<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncBaseFieldsProcessor;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorPluginBase;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation for generic fieldable entity.
 *
 * @SingleContentSyncBaseFieldsProcessor(
 *   id = "generic",
 *   label = @Translation("Generic entity fields processor"),
 *   entity_type = ""
 * )
 */
class GenericContentEntity extends SingleContentSyncBaseFieldsProcessorPluginBase implements ContainerFactoryPluginInterface {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('single_content_sync.exporter'),
      $container->get('single_content_sync.importer'),
    );
  }

  /**
   * Constructs new GenericContentEntity plugin instance.
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ContentExporterInterface $exporter,
    ContentImporterInterface $importer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->exporter = $exporter;
    $this->importer = $importer;
  }

  /**
   * {@inheritdoc}
   */
  public function exportBaseValues(FieldableEntityInterface $entity): array {
    $entity_keys = array_filter($entity->getEntityType()->getKeys());
    $entity_keys_flip = array_flip($entity_keys);

    $skip_keys = [
      'uuid',
      'bundle',
      'id',
    ];

    $base_fields = [];

    foreach ($entity->getFieldDefinitions() as $field) {
      if ($field instanceof BaseFieldDefinition) {
        $field_name = $field->getName();

        if (isset($entity_keys_flip[$field_name]) && in_array($entity_keys_flip[$field_name], $skip_keys)) {
          continue;
        }

        $base_fields[$field->getName()] = $this->exporter->getFieldValue($entity->get($field->getName()));
      }
    }

    return $base_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues(array $values, FieldableEntityInterface $entity): array {
    foreach ($values as $field_name => $value) {
      $this->importer->setFieldValue($entity, $field_name, $value);
    }

    return [];
  }

}
