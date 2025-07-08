<?php

namespace Drupal\schwab_content_sync\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\schwab_content_sync\ContentFileGeneratorInterface;
use Drupal\schwab_content_sync\ContentSyncHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * This action is used to export multiple contents in a bulk operation.
 *
 * @Action(
 *  id = "content_bulk_export",
 *  label = @Translation("Export content"),
 *  type = "node",
 * )
 */
class ContentBulkExport extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The Content sync helper.
   *
   * @var \Drupal\schwab_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The custom file generator to export content.
   *
   * @var \Drupal\schwab_content_sync\ContentFileGeneratorInterface
   */
  protected ContentFileGeneratorInterface $fileGenerator;

  /**
   * Constructs a ContentBulkExport object.
   *
   * @param array<string, mixed> $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\schwab_content_sync\ContentFileGeneratorInterface $file_generator
   *   The custom file generator to export content.
   * @param \Drupal\schwab_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContentFileGeneratorInterface $file_generator, ContentSyncHelperInterface $content_sync_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->fileGenerator = $file_generator;
    $this->contentSyncHelper = $content_sync_helper;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $configuration
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('schwab_content_sync.file_generator'),
      $container->get('schwab_content_sync.helper'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $object
   *   The object to execute the action on.
   */
  public function execute($object = NULL): void {
    // Moved the logic to ::executeMultiple();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<int, \Drupal\Core\Entity\EntityInterface> $entities
   *   An array of entities.
   */
  public function executeMultiple(array $entities): void {
    $extract_translations = $this->configuration['translation'];
    $extract_assets = $this->configuration['assets'];
    $file = $this->fileGenerator->generateBulkZipFile($entities, $extract_translations, $extract_assets);

    $response = new StreamedResponse(static function () use ($file) {
      $file_uri = $file->getFileUri();
      
      if (!is_string($file_uri)) {
        return;
      }
      
      $fp = fopen($file_uri, 'rb');
      
      if ($fp === false) {
        return;
      }

      while (!feof($fp)) {
        // Read a chunk of the file and send it to the client.
        echo fread($fp, 8192);

        // Flush the buffer to ensure the data is sent to the client.
        flush();
      }

      // Close the file once done.
      fclose($fp);

      // Delete temp zip file permanently.
      $file->delete();
    }, 200, [
      'Content-disposition' => 'attachment; filename="' . $file->getFilename() . '"',
      'Content-Type' => 'application/zip',
    ]);

    $response->send();
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $object
   *   The object to check access for.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   * @param bool $return_as_object
   *   Whether to return an AccessResultInterface object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface|bool
   *   The access result.
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if ($account === NULL) {
      $account = \Drupal::currentUser();
    }
    
    $result = AccessResult::allowedIfHasPermission($account, 'export single content');

    if ($object instanceof EntityInterface && !$this->contentSyncHelper->access($object)) {
      $result = AccessResult::forbidden()->addCacheTags(['config:schwab_content_sync.settings']);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, bool>
   *   The default configuration.
   */
  public function defaultConfiguration(): array {
    return [
      'assets' => TRUE,
      'translation' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array<string, mixed>
   *   The form structure.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['assets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include all assets'),
      '#description' => $this->t('Whether to export all file assets such as images, documents, videos and etc.'),
      '#default_value' => $this->configuration['assets'],
    ];

    $form['translation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include all translations'),
      '#description' => $this->t('Whether to export available translations of the content.'),
      '#default_value' => $this->configuration['translation'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['assets'] = $form_state->getValue('assets');
    $this->configuration['translation'] = $form_state->getValue('translation');
  }

}