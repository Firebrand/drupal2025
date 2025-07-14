<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * SingleContentSyncFieldProcessor plugin manager.
 */
class SingleContentSyncFieldProcessorPluginManager extends DefaultPluginManager implements SingleContentSyncFieldProcessorPluginManagerInterface {

  /**
   * An array of field type plugin definitions, keyed by field type.
   *
   * Used for performance optimization to avoid repeated calls to
   * getDefinitions().
   *
   * @var array|null
   */
  protected ?array $fieldTypePluginDefinitions = NULL;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructs SingleContentSyncFieldProcessorPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
    EntityFieldManagerInterface $entity_field_manager
  ) {
    parent::__construct(
      'Plugin/SingleContentSyncFieldProcessor',
      $namespaces,
      $moduleHandler,
      'Drupal\single_content_sync\SingleContentSyncFieldProcessorInterface',
      'Drupal\single_content_sync\Annotation\SingleContentSyncFieldProcessor'
    );
    $this->alterInfo('single_content_sync_field_processor_info');
    $this->setCacheBackend($cacheBackend, 'single_content_sync_field_processor_plugins');
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): SingleContentSyncFieldProcessorInterface {
    $instance = parent::createInstance($plugin_id, $configuration);

    // Throw an exception if the plugin does not implement the interface.
    if (!$instance instanceof SingleContentSyncFieldProcessorInterface) {
      throw new \InvalidArgumentException(
        sprintf(
          'The plugin %s does not implement the SingleContentSyncFieldProcessorInterface.',
          $plugin_id
        )
      );
    }

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function findFieldPluginDefinition(
    string $entityType,
    string $bundle,
    string $fieldName
  ): ?array {
    $fieldType = $this->getEntityFieldType($entityType, $bundle, $fieldName);

    // @todo Dispatch an event to allow other modules to alter the selected
    //   plugin.
    return $this->getFieldTypesPlugins()[$fieldType] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldPluginInstance(
    string $entityType,
    string $bundle,
    string $fieldName
  ): SingleContentSyncFieldProcessorInterface {
    $pluginDefinition = $this->findFieldPluginDefinition($entityType, $bundle, $fieldName);

    return $pluginDefinition
      ? $this->createInstance($pluginDefinition['id'])
      : $this->createInstance('generic');
  }

  /**
   * Gets the field type for a given field.
   *
   * @param string $entityType
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $fieldName
   *   The field name.
   *
   * @return string
   *   The field type.
   */
  protected function getEntityFieldType(
    string $entityType,
    string $bundle,
    string $fieldName
  ): string {
    $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle);

    // Throw an InvalidArgumentException if the field does not exist.
    if (!isset($fieldDefinitions[$fieldName])) {
      throw new \InvalidArgumentException(sprintf('The field "%s" does not exist on the "%s" entity type.', $fieldName, $entityType));
    }

    return $fieldDefinitions[$fieldName]->getType();
  }

  /**
   * Returns a list of field type plugin definitions, keyed by field type.
   *
   * @return array
   *   The field type plugin definitions.
   */
  protected function getFieldTypesPlugins(): array {
    if (!isset($this->fieldTypePluginDefinitions)) {
      $this->fieldTypePluginDefinitions = [];
      foreach ($this->getDefinitions() as $definition) {
        if (isset($this->fieldTypePluginDefinitions[$definition['field_type']])) {
          throw new \LogicException(sprintf('The field type "%s" is already defined by the "%s" plugin.', $definition['field_type'], $this->fieldTypePluginDefinitions[$definition['field_type']]['id']));
        }
        $this->fieldTypePluginDefinitions[$definition['field_type']] = $definition;
      }
    }

    return $this->fieldTypePluginDefinitions;
  }

}
