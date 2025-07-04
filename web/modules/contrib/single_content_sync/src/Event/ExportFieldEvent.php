<?php

namespace Drupal\single_content_sync\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * The event dispatched before the entity field is exported.
 */
class ExportFieldEvent extends Event {

  /**
   * The field item list being exported.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface
   */
  protected FieldItemListInterface $field;

  /**
   * The field value to export.
   *
   * @var array
   */
  protected array $fieldValue;

  /**
   * Constructs a new ExportFieldEvent object.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field item list being exported.
   * @param array $field_value
   *   The field value from the field processor.
   */
  public function __construct(FieldItemListInterface $field, array $field_value) {
    $this->field = $field;
    $this->fieldValue = $field_value;
  }

  /**
   * Gets the field item list being exported.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   The field item list object.
   */
  public function getEntity(): FieldItemListInterface {
    return $this->field;
  }

  /**
   * Gets the field value to export.
   *
   * @return array
   *   The field value to export.
   */
  public function getFieldValue(): array {
    return $this->fieldValue;
  }

  /**
   * Sets the field value to export.
   *
   * @param array $field_value
   *   The field value to export. The same array keys should be preserved as
   *   returned by getFieldValue().
   *
   * @return $this
   */
  public function setFieldValue(array $field_value): self {
    $this->fieldValue = $field_value;
    return $this;
  }

}
