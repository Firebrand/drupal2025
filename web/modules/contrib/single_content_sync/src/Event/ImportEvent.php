<?php

namespace Drupal\single_content_sync\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * The event dispatched before the entity is imported.
 */
class ImportEvent extends Event {

  /**
   * The entity being imported. Could be either a new or existing entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected ContentEntityInterface $entity;

  /**
   * The content to import.
   *
   * @var array
   */
  protected array $content;

  /**
   * Constructs a new ImportEvent object.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported. Could be either a new or existing entity.
   * @param array $content
   *   The content to import.
   */
  public function __construct(ContentEntityInterface $entity, array $content) {
    $this->entity = $entity;
    $this->content = $content;
  }

  /**
   * Gets the entity being imported.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity being imported.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Replaces the entity being imported.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being imported.
   *
   * @return $this
   */
  public function setEntity(ContentEntityInterface $entity): ImportEvent {
    $this->entity = $entity;
    return $this;
  }

  /**
   * Gets the content to import.
   *
   * @return array
   *   The content to import. The array keys are:
   *   - 'entity_type': The entity type.
   *   - 'bundle': The entity bundle.
   *   - 'uuid': The entity UUID.
   *   - 'base_fields': The entity base fields.
   *   - 'custom_fields': The entity custom fields.
   */
  public function getContent(): array {
    return $this->content;
  }

  /**
   * Sets the content to import.
   *
   * @param array $content
   *   The content to import. The same array keys should be preserved as
   *   returned by getContent().
   *
   * @return $this
   */
  public function setContent(array $content): self {
    $this->content = $content;
    return $this;
  }

}
