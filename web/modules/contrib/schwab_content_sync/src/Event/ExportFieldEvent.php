<?php

namespace Drupal\schwab_content_sync\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * The event dispatched before the entity field is exported.
 */
class ExportFieldEvent extends Event {

  /**
   * The field item list being exported.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   */
  protected FieldItemListInterface $field;

  /**
   * The field value to export.
   *
   * @var array<int, array<string, mixed>>
   */
  protected array $fieldValue;

  /**
   * Constructs a new ExportFieldEvent object.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $field
   *   The field item list being exported.
   * @param array<int, array<string, mixed>> $field_value
   *   The field value from the field processor.
   */
  public function __construct(FieldItemListInterface $field, array $field_value) {
    $this->field = $field;
    $this->fieldValue = $field_value;
  }

  /**
   * Gets the field item list being exported.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface>
   *   The field item list object.
   */
  public function getField(): FieldItemListInterface {
    return $this->field;
  }

  /**
   * Gets the field value to export.
   *
   * @return array<int, array<string, mixed>>
   *   The field value to export.
   */
  public function getFieldValue(): array {
    return $this->fieldValue;
  }

  /**
   * Sets the field value to export.
   *
   * @param array<int, array<string, mixed>> $field_value
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