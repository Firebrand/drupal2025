<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * SingleContentSyncBaseFieldsProcessor plugin manager.
 */
class SingleContentSyncBaseFieldsProcessorPluginManager extends DefaultPluginManager implements SingleContentSyncBaseFieldsProcessorPluginManagerInterface {

  /**
   * An array of base fields plugin definitions, keyed by entity type.
   *
   * Used for performance optimization to avoid repeated calls to
   * getDefinitions().
   *
   * @var array|null
   */
  protected ?array $entityTypePluginDefinitions = NULL;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs SingleContentSyncBaseFieldsProcessorPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct(
      'Plugin/SingleContentSyncBaseFieldsProcessor',
      $namespaces,
      $moduleHandler,
      'Drupal\single_content_sync\SingleContentSyncBaseFieldsProcessorInterface',
      'Drupal\single_content_sync\Annotation\SingleContentSyncBaseFieldsProcessor'
    );
    $this->alterInfo('single_content_sync_base_fields_processor_info');
    $this->setCacheBackend($cacheBackend, 'single_content_sync_base_fields_processor_plugins');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): SingleContentSyncBaseFieldsProcessorInterface {
    $instance = parent::createInstance($plugin_id, $configuration);

    // Throw an exception if the plugin does not implement the interface.
    if (!$instance instanceof SingleContentSyncBaseFieldsProcessorInterface) {
      throw new \InvalidArgumentException(
        sprintf(
          'The plugin %s does not implement the SingleContentSyncBaseFieldsProcessorInterface.',
          $plugin_id
        )
      );
    }

    return $instance;
  }

  /**
   * Gets the base fields processor plugin definition for a given entity type.
   *
   * @param string $entityType
   *   The entity type.
   *
   * @return array|null
   *   The plugin definition or NULL if none found.
   */
  protected function findEntityPluginDefinition(string $entityType): ?array {
    // Throw an InvalidArgumentException if the entity type does not exist.
    if (!$this->entityTypeManager->getDefinition($entityType, FALSE)) {
      throw new \InvalidArgumentException(sprintf('The entity type "%s" does not exist.', $entityType));
    }

    return $this->getEntityTypesPlugins()[$entityType] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityPluginInstance(string $entityType): SingleContentSyncBaseFieldsProcessorInterface {
    $pluginDefinition = $this->findEntityPluginDefinition($entityType);

    return $pluginDefinition
      ? $this->createInstance($pluginDefinition['id'])
      : $this->createInstance('generic');
  }

  /**
   * Returns a list of base fields plugin definitions, keyed by entity type.
   *
   * @return array
   *   The entity type plugin definitions.
   */
  protected function getEntityTypesPlugins(): array {
    if (!isset($this->entityTypePluginDefinitions)) {
      $this->entityTypePluginDefinitions = [];
      foreach ($this->getDefinitions() as $definition) {
        if (isset($this->entityTypePluginDefinitions[$definition['entity_type']])) {
          throw new \LogicException(sprintf('The entity type "%s" is already defined by the "%s" plugin.', $definition['entity_type'], $this->entityTypePluginDefinitions[$definition['entity_type']]['id']));
        }
        $this->entityTypePluginDefinitions[$definition['entity_type']] = $definition;
      }
    }

    return $this->entityTypePluginDefinitions;
  }

}
