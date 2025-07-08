<?php

namespace Drupal\schwab_content_sync\Form;

use Drupal\Component\Utility\Environment;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\schwab_content_sync\ContentBatchImporter;
use Drupal\schwab_content_sync\ContentImporterInterface;
use Drupal\schwab_content_sync\ContentSyncHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to import a content.
 *
 * @package Drupal\schwab_content_sync\Form
 */
class ContentImportForm extends FormBase {

  /**
   * The content importer service.
   *
   * @var \Drupal\schwab_content_sync\ContentImporterInterface
   */
  protected ContentImporterInterface $contentImporter;

  /**
   * The content sync helper.
   *
   * @var \Drupal\schwab_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * ContentImportForm constructor.
   *
   * @param \Drupal\schwab_content_sync\ContentImporterInterface $content_importer
   *   The content importer service.
   * @param \Drupal\schwab_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   */
  public function __construct(ContentImporterInterface $content_importer, ContentSyncHelperInterface $content_sync_helper) {
    $this->contentImporter = $content_importer;
    $this->contentSyncHelper = $content_sync_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('schwab_content_sync.importer'),
      $container->get('schwab_content_sync.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'schwab_content_sync_import_form';
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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var array<string, mixed> $validators */
    $validators = [];

    // Still support older drupal version than 10.2 with old file extension
    // validator approach.
    if (floatval(\Drupal::VERSION) < 10.2) {
      $validators['file_validate_extensions'] = ['zip yml'];
      // @phpstan-ignore-next-line
      $max_upload_size = format_size(Environment::getUploadMaxSize());
    }
    else {
      $validators['FileExtension'] = ['zip yml'];
      $max_upload_size = ByteSizeMarkup::create(Environment::getUploadMaxSize());
    }

    $schema = $this->contentSyncHelper->getImportDirectorySchema();

    $form['upload_fid'] = [
      '#type' => 'managed_file',
      '#upload_location' => "{$schema}://import/zip",
      '#upload_validators' => $validators,
      '#title' => $this->t('Upload a file with content to import'),
      '#description' => $this->t(
        'Upload a Zip or YAML file with the previously exported content. Maximum file size: @size.',
        ['@size' => $max_upload_size]
      ),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
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
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $upload_file = $form_state->getValue('upload_fid');

    if (!$upload_file) {
      $form_state->setErrorByName('upload_fid', $this->t('Please upload a file to import your content.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $upload_fid = $form_state->getValue('upload_fid');
    
    if (!is_array($upload_fid) || empty($upload_fid)) {
      $this->messenger()->addError($this->t('No file was uploaded.'));
      return;
    }
    
    $fid = reset($upload_fid);
    
    if (!is_int($fid) && !is_string($fid)) {
      $this->messenger()->addError($this->t('Invalid file ID.'));
      return;
    }
    
    $file_real_path = $this->contentSyncHelper->getFileRealPathById((int) $fid);
    $file_info = pathinfo($file_real_path);
    $entity = NULL;

    try {
      if (isset($file_info['extension']) && $file_info['extension'] === 'zip') {
        $this->contentImporter->importFromZip($file_real_path);
      }
      elseif (isset($file_info['extension'])) {
        $entity = $this->contentImporter->importFromFile($file_real_path);
      }
      else {
        throw new \Exception($this->t('Unable to determine file extension.')->render());
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }

    // Clean up the temporary uploaded file.
    ContentBatchImporter::cleanUploadedFile($fid);

    if ($entity) {
      $this->messenger()->addStatus($this->t('The content has been synced @link', [
        '@link' => $entity->toLink()->toString(),
      ]));
    }
  }

}