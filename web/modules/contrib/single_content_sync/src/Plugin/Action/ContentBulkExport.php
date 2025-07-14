<?php

namespace Drupal\single_content_sync\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\single_content_sync\ContentFileGeneratorInterface;
use Drupal\single_content_sync\ContentSyncHelperInterface;
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
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * The custom file generator to export content.
   *
   * @var \Drupal\single_content_sync\ContentFileGeneratorInterface
   */
  protected ContentFileGeneratorInterface $fileGenerator;

  /**
   * Constructs a ContentBulkExport object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\single_content_sync\ContentFileGeneratorInterface $file_generator
   *   The custom file generator to export content.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContentFileGeneratorInterface $file_generator, ContentSyncHelperInterface $content_sync_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->fileGenerator = $file_generator;
    $this->contentSyncHelper = $content_sync_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('single_content_sync.file_generator'),
      $container->get('single_content_sync.helper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    // Moved the logic to ::executeMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $extract_translations = $this->configuration['translation'];
    $extract_assets = $this->configuration['assets'];
    $file = $this->fileGenerator->generateBulkZipFile($entities, $extract_translations, $extract_assets);

    $response = new StreamedResponse(static function() use ($file) {
      $fp = fopen($file->getFileUri(), 'rb');

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
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIfHasPermission($account, 'export single content');

    if (!$this->contentSyncHelper->access($object)) {
      $result = AccessResult::forbidden()->addCacheTags(['config:single_content_sync.settings']);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'assets' => TRUE,
      'translation' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['assets'] = $form_state->getValue('assets');
    $this->configuration['translation'] = $form_state->getValue('translation');
  }

}
