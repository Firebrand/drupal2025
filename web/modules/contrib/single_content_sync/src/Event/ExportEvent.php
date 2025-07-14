<?php

namespace Drupal\single_content_sync\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * The event dispatched when the entity is exported.
 */
class ExportEvent extends Event {

  /**
   * The entity being exported.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  /**
   * The content being exported.
   *
   * @var array
   */
  protected array $content;

  /**
   * The translation flag.
   *
   * @var bool
   */
  protected bool $isTranslation;

  /**
   * Constructs a new ExportEvent object.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being exported.
   * @param array $content
   *   The content being exported.
   * @param bool $is_translation
   *   The translation flag.
   */
  public function __construct(FieldableEntityInterface $entity, array $content, bool $is_translation = FALSE) {
    $this->entity = $entity;
    $this->content = $content;
    $this->isTranslation = $is_translation;
  }

  /**
   * Whether the export is translation.
   *
   * @return bool
   *   The translation flag.
   */
  public function isTranslation(): bool {
    return $this->isTranslation;
  }

  /**
   * Gets the entity being exported.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity being exported.
   */
  public function getEntity(): FieldableEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the content being exported.
   *
   * @return array
   *   The exported content. The array keys are:
   *   - 'entity_type': The entity type. Not available for translation export.
   *   - 'bundle': The entity bundle. Not available for translation export.
   *   - 'uuid': The entity UUID. Not available for translation export.
   *   - 'base_fields': The entity base fields.
   *   - 'custom_fields': The entity custom fields.
   */
  public function getContent(): array {
    return $this->content;
  }

  /**
   * Sets the exported content.
   *
   * @param array $content
   *   The exported content. The same array keys should be preserved as returned
   *   by getContent().
   *
   * @return $this
   */
  public function setContent(array $content): self {
    $this->content = $content;
    return $this;
  }

}
