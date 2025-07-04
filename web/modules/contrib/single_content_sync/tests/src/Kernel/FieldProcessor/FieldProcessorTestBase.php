<?php

namespace Drupal\Tests\single_content_sync\Kernel\FieldProcessor;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * The base class for field processor plugins tests.
 *
 * @coversDefaultClass \Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase
 */
abstract class FieldProcessorTestBase extends KernelTestBase {

  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'single_content_sync',
    'field',
    'file',
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

  }

  /**
   * Returns an array of export test data.
   *
   * Each array row is a single test case; rows are keyed by a human-readable
   * label.
   *
   * Each row is an array with the following elements: field storage definition,
   * field value, expected export output.
   *
   * @return array
   *   An array of test data.
   */
  abstract public static function exportFieldValueDataProvider(): array;

  /**
   * @covers ::exportFieldValue
   * @dataProvider exportFieldValueDataProvider
   */
  public function testExportFieldValue(
    array $fieldStorageDefinition,
    mixed $fieldValue,
    array $expectedExportOutput,
    array $extraModules = []
  ): void {
    if ($extraModules) {
      $this->enableModules($extraModules);
    }

    $this->prepareNodeTypeWithField($fieldStorageDefinition);

    $node = Node::create([
      'type' => 'article',
      'title' => 'Test node',
      'field_test_field' => $fieldValue,
    ]);

    $node->save();

    // Force reload the node from database to make sure we're dealing with a
    // real field data and not something we've just put in there.
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());

    $this->assertExportedValueEquals(
      $expectedExportOutput,
      $this->getFieldProcessor()->exportFieldValue($node->get('field_test_field')),
    );
  }

  /**
   * Returns an array of import test data.
   *
   * Each array row is a single test case; rows are keyed by a human-readable
   * label.
   *
   * Each row is an array with the following elements: field storage definition,
   * data to import, expected field value.
   *
   * @return array
   *   An array of test data.
   */
  abstract public static function importFieldValueDataProvider(): array;

  /**
   * @covers ::importFieldValue
   * @dataProvider importFieldValueDataProvider
   */
  public function testImportFieldValue(
    array $fieldStorageDefinition,
    mixed $dataToImport,
    mixed $expectedFieldValue = NULL,
    array $extraModules = []
  ): void {
    if ($expectedFieldValue === NULL) {
      $expectedFieldValue = $dataToImport;
    }

    if ($extraModules) {
      $this->enableModules($extraModules);
    }

    $this->prepareNodeTypeWithField($fieldStorageDefinition);

    $node = Node::create([
      'type' => 'article',
      'title' => 'Test node',
    ]);

    $this->getFieldProcessor()->importFieldValue($node, 'field_test_field', $dataToImport);
    $node->save();

    // Force reload the node from database; otherwise we'll get just same
    // values as we've set above.
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());

    $this->assertImportedValueEquals(
      $expectedFieldValue,
      $node->get('field_test_field')->getValue(),
    );
  }

  /**
   * Returns the field processor plugin instance to test.
   */
  protected function getFieldProcessor(): SingleContentSyncFieldProcessorInterface {
    return $this
      ->container
      ->get('plugin.manager.single_content_sync_field_processor')
      ->getFieldPluginInstance('node', 'article', 'field_test_field');
  }

  /**
   * Creates node type, field storage and field config for the test.
   *
   * @param array $fieldStorageDefinition
   *   The field storage definition.
   */
  protected function prepareNodeTypeWithField(array $fieldStorageDefinition): void {
    NodeType::create([
      'type' => 'article',
      'name' => 'article',
    ])->save();

    $fieldStorageConfig = FieldStorageConfig::create($fieldStorageDefinition + [
      'field_name' => 'field_test_field',
      'entity_type' => 'node',
    ]);
    $fieldStorageConfig->save();
    FieldConfig::create([
      'field_storage' => $fieldStorageConfig,
      'bundle' => 'article',
    ])->save();
  }

  /**
   * Asserts equality of exported value.
   *
   * This method could be overridden in child classes to provide custom
   * comparison logic.
   *
   * @param mixed $expectedExportOutput
   *   The expected export output.
   * @param array $actualExportOutput
   *   The actual export output.
   */
  protected function assertExportedValueEquals(mixed $expectedExportOutput, array $actualExportOutput) {
    $this->assertEquals($expectedExportOutput, $actualExportOutput);
  }

  /**
   * Asserts equality of imported value.
   *
   * This method could be overridden in child classes to provide custom
   * comparison logic.
   *
   * @param mixed $expectedFieldValue
   *   The expected field value.
   * @param mixed $actualImportedFieldValue
   *   The actual imported field value.
   */
  protected function assertImportedValueEquals(mixed $expectedFieldValue, mixed $actualImportedFieldValue): void {
    $this->assertEquals($expectedFieldValue, $actualImportedFieldValue);
  }

}
