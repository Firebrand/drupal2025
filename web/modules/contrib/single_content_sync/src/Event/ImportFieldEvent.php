<?php

namespace Drupal\single_content_sync\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * The event dispatched before the entity field value is set.
 */
class ImportFieldEvent extends Event {

  /**
   * The entity being imported. Could be either a new or existing entity.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  /**
   * The field value to set into the field.
   *
   * @var array
   */
  protected array $fieldValue;

  /**
   * The field name to set the value into.
   *
   * @var string
   */
  protected string $fieldName;

  /**
   * Constructs a new ImportFieldEvent object.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity being imported. Could be either a new or existing entity.
   * @param string $field_name
   *   The field name to set the value into.
   * @param array $field_value
   *   The field value.
   */
  public function __construct(FieldableEntityInterface $entity, string $field_name, array $field_value) {
    $this->entity = $entity;
    $this->fieldName = $field_name;
    $this->fieldValue = $field_value;
  }

  /**
   * Gets the entity being imported.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The entity being imported.
   */
  public function getEntity(): FieldableEntityInterface {
    return $this->entity;
  }

  /**
   * Gets the field value to import.
   *
   * @return array
   *   The field value to import.
   */
  public function getFieldValue(): array {
    return $this->fieldValue;
  }

  /**
   * Gets the field name.
   *
   * @return string
   *   The field name.
   */
  public function getFieldName(): string {
    return $this->fieldName;
  }

}
