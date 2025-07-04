<?php

namespace Drupal\Tests\single_content_sync\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\single_content_sync\ContentImporterInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Tests the content importer.
 *
 * @group single_content_sync
 * @coversDefaultClass \Drupal\single_content_sync\ContentImporter
 */
class ContentImporterTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'single_content_sync',
    'field',
    'file',
    'node',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['node', 'system']);

    $this->createContentType(['type' => 'article']);
  }

  /**
   * @covers ::__construct
   */
  public function testConstructor() {
    $this->assertInstanceOf(ContentImporterInterface::class, $this->getImporter());
  }

  /**
   * Basic node import test.
   *
   * @covers ::doImport
   */
  public function testDoImportNode() {
    $nodeUuid = $this->container->get('uuid')->generate();

    $node = $this
      ->getImporter()
      ->doImport($this->getTestNodeData($nodeUuid, 'testDoImportNode() node', TRUE));

    $this->assertTestNodeCreated($node, $nodeUuid, TRUE);
  }

  /**
   * Node import test with author.
   *
   * @covers ::doImport
   */
  public function testDoImportNodeWithAuthor() {
    $nodeUuid = $this->container->get('uuid')->generate();
    $user = $this->createUser();

    $node = $this
      ->getImporter()
      ->doImport(array_merge_recursive(
        $this->getTestNodeData($nodeUuid, 'testDoImportNodeAuthor() node', TRUE),
        ['base_fields' => ['author' => $user->getEmail()]],
      ));

    $this->assertTestNodeCreated($node, $nodeUuid, TRUE);
    $this->assertEquals($user->id(), $node->getOwnerId());
  }

  /**
   * Test import taxonomy terms.
   *
   * The following entity create cases are tested:
   * - term with parent set to 0 (this is a valid case, the exported sets the
   *   parent to 0 when the term has no parent rather than omitting the key);
   * - term with parent set to another term which in turn has another parent.
   *
   * In addition, we're testing update of the parent term to make sure
   * that the importer does not create duplicate terms.
   *
   * @covers ::doImport
   */
  public function testDoImportTaxonomyTerm() {
    $termWithZeroParentUuid = $this->container->get('uuid')->generate();
    $termWithZeroParent = $this
      ->getImporter()
      ->doImport($this->getTestTermData($termWithZeroParentUuid, 'testDoImportTaxonomyTerm() term with zero parent'));
    $this->assertTestTermCreated($termWithZeroParent, $termWithZeroParentUuid);
    $this->assertNull($termWithZeroParent->parent->entity);

    $grandparentTermUuid = $this->container->get('uuid')->generate();
    $parentTermUuid = $this->container->get('uuid')->generate();
    $childTermUuid = $this->container->get('uuid')->generate();

    $grandparentTermData = $this->getTestTermData($grandparentTermUuid, 'testDoImportTaxonomyTerm() grandparent term');
    $parentTermData = $this->getTestTermData($parentTermUuid, 'testDoImportTaxonomyTerm() parent term');
    $childTermData = $this->getTestTermData($childTermUuid, 'testDoImportTaxonomyTerm() child term');

    $parentTermData['base_fields']['parent'] = $grandparentTermData;
    $childTermData['base_fields']['parent'] = $parentTermData;

    // Check the parent term was created.
    $childTerm = $this
      ->getImporter()
      ->doImport($childTermData);
    $this->assertTestTermCreated($childTerm, $childTermUuid);

    // Update the parent term by re-importing it, and check the child term
    // is still there.
    $parentTerm = $this
      ->getImporter()
      ->doImport($parentTermData);
    $this->assertTestTermCreated($parentTerm, $parentTermUuid);

    $this->assertEquals($parentTerm->id(), $childTerm->parent->target_id);
  }

  /**
   * @covers ::createStubEntity
   */
  public function testCreateStubEntity() {
    // Node entity type has bundles.
    $nodeUuid = $this->container->get('uuid')->generate();
    $node = $this
      ->getImporter()
      ->createStubEntity(
        $this->getTestNodeData($nodeUuid, 'testCreateStubEntity() node')
      );

    // Note we are not asserting base fields here since it's done in
    // importBaseValues() and is covered by respective test.
    $this->assertTestNodeCreated($node, $nodeUuid);

    // User does not have bundles; we are still passing the invalid bundle
    // key here to make sure it's ignored.
    $userUuid = $this->container->get('uuid')->generate();
    $user = $this
      ->getImporter()
      ->createStubEntity([
        'entity_type' => 'user',
        'uuid' => $userUuid,
        'bundle' => 'invalid',
        'base_fields' => [
          'mail' => 'somebody@once.told.me',
          'init' => 'somebody@once.told.me',
          'name' => 'testCreateStubEntity() user',
          'created' => 123456789,
          'status' => TRUE,
          'timezone' => 'UTC',
        ],
      ]);

    $this->assertInstanceOf(UserInterface::class, $user);
    $this->assertFalse($user->isNew());
    $this->assertEquals($userUuid, $user->uuid());
    $this->assertEquals('user', $user->bundle());
  }

  /**
   * Tests import of base fields.
   *
   * @covers ::importBaseValues
   */
  public function testImportBaseValues() {
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
      ]);

    $nodeUuid = $this->container->get('uuid')->generate();
    $this
      ->getImporter()
      ->importBaseValues($node, $this->getTestNodeData($nodeUuid, 'testImportBaseValues() node', TRUE)['base_fields']);

    $this->assertTestNodeCreated($node, expectSaved: FALSE);
  }

  /**
   * Tests settings entity fields from exported data.
   *
   * @covers ::setFieldValue
   */
  public function testSetFieldValue() {
    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->create([
        'type' => 'article',
        'body' => [
          'value' => 'test node body',
          'format' => 'plain_text',
        ],
      ]);

    $importer = $this->getImporter();

    // Try setting a non-existent field and make sure the object property
    // was not set.
    $this->assertObjectNotHasProperty('non_existent_field', $node);
    $importer->setFieldValue($node, 'non_existent_field', 'some value');
    $this->assertObjectNotHasProperty('non_existent_field', $node);

    // Clear the body field and make sure it's empty.
    $this->assertNotEmpty($node->body->value);
    $importer->setFieldValue($node, 'body', NULL);
    $this->assertEmpty($node->body->value);

    // Finally, set the body field and make sure it's set.
    $importer->setFieldValue($node, 'body', [
      [
        'value' => 'test node body',
        'format' => 'plain_text',
      ],
    ]);
    $this->assertEquals('test node body', $node->body->value);
    $this->assertEquals('plain_text', $node->body->format);
  }

  /**
   * Retrieves the content importer service from container.
   */
  protected function getImporter(): ContentImporterInterface {
    return $this->container->get('single_content_sync.importer');
  }

  /**
   * Generates test node data array.
   *
   * @param string $nodeUuid
   *   The test node UUID.
   * @param string $title
   *   The test node title.
   * @param bool $includeCustomFields
   *   Whether to include custom fields.
   *
   * @return array
   *   The test node data array.
   */
  protected function getTestNodeData(
    string $nodeUuid,
    string $title,
    bool $includeCustomFields = FALSE
  ): array {
    $data = [
      'entity_type' => 'node',
      'uuid' => $nodeUuid,
      'bundle' => 'article',
      'base_fields' => [
        'title' => $title,
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
        'created' => 123456789,
        'status' => NodeInterface::PUBLISHED,
      ],
    ];

    if ($includeCustomFields) {
      $data['custom_fields'] = [
        'body' => [
          0 => [
            'value' => 'test node body',
            'format' => 'plain_text',
          ],
        ],
      ];
    }

    return $data;
  }

  /**
   * Asserts that the test node was created and has correct data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The test node.
   * @param string|null $expectedUuid
   *   The expected node UUID. If not set, the UUID is not checked.
   * @param bool $assertCustomFields
   *   Whether to assert custom fields.
   * @param bool|null $expectSaved
   *   Whether to expect the node to be saved.
   * @param string|null $expectedBundle
   *   The expected bundle. If set to NULL, the bundle is not checked.
   */
  protected function assertTestNodeCreated(
    EntityInterface $node,
    ?string $expectedUuid = NULL,
    bool $assertCustomFields = FALSE,
    ?bool $expectSaved = TRUE,
    ?string $expectedBundle = 'article',
  ): void {
    $this->assertInstanceOf(NodeInterface::class, $node, 'Asserting entity is a node');

    if ($expectSaved !== NULL) {
      if ($expectSaved) {
        $this->assertTrue(!$node->isNew(), 'Asserting node is saved');
      }
      else {
        $this->assertTrue($node->isNew(), 'Asserting node is new');
      }
    }

    if ($expectedUuid !== NULL) {
      $this->assertEquals($expectedUuid, $node->uuid(), 'Asserting UUID');
    }

    if ($expectedBundle !== NULL) {
      $this->assertEquals($expectedBundle, $node->bundle(), 'Asserting bundle');
    }

    if ($assertCustomFields) {
      $this->assertEquals('test node body', $node->body->value, 'Asserting body value');
      $this->assertEquals('plain_text', $node->body->format, 'Asserting body format');
    }
  }

  /**
   * Generates test taxonomy term data array.
   *
   * @param string $termUuid
   *   The test term UUID.
   * @param string $title
   *   The test term title.
   *
   * @return array
   *   The test term data array.
   */
  protected function getTestTermData(string $termUuid, string $title): array {
    return [
      'entity_type' => 'taxonomy_term',
      'uuid' => $termUuid,
      'bundle' => 'tags',
      'base_fields' => [
        'name' => $title,
        'weight' => 0,
        'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
        'description' => NULL,
        'parent' => 0,
      ],
      'custom_fields' => [],
    ];
  }

  /**
   * Asserts that the test term was created and has correct data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $term
   *   The test term.
   * @param string $termUuid
   *   The expected term UUID.
   */
  protected function assertTestTermCreated(
    EntityInterface $term,
    string $termUuid
  ) {
    $this->assertInstanceOf(TermInterface::class, $term);
    $this->assertFalse($term->isNew());
    $this->assertEquals($termUuid, $term->uuid());
    $this->assertEquals('tags', $term->bundle());
  }

}
