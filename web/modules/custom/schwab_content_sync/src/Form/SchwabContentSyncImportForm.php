<?php

namespace Drupal\schwab_content_sync\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\single_content_sync\Form\ContentImportForm;

/**
 * Customized import form for Paragraph Library Items.
 */
class SchwabContentSyncImportForm extends ContentImportForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'schwab_content_sync_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the parent form
    $form = parent::buildForm($form, $form_state);
    
    // Remove the YAML editor field
    unset($form['content']);
    
    // Customize the file upload field
    $form['upload_fid']['#title'] = $this->t('Upload Paragraph Library Export');
    $form['upload_fid']['#description'] = $this->t(
      'Upload a ZIP file containing exported Paragraph Library items. Maximum file size: @size.',
      ['@size' => $form['upload_fid']['#description']->getArguments()['@size']]
    );
    
    // Make file upload required since we removed the YAML option
    $form['upload_fid']['#required'] = TRUE;
    
    // Remove the "OR" prefix from the original form
    if (isset($form['upload_fid']['#prefix'])) {
      unset($form['upload_fid']['#prefix']);
    }
    
    // Update submit button text
    $form['actions']['import']['#value'] = $this->t('Import Paragraph Library Items');
    
    // Add helpful description
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Import Paragraph Library items that were previously exported. All associated images and files will be imported automatically.') . '</p>',
      '#weight' => -10,
    ];
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Skip parent validation since we don't have the content field
    $upload_file = $form_state->getValue('upload_fid');
    
    if (!$upload_file) {
      $form_state->setErrorByName('upload_fid', $this->t('Please upload a file to import.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Only handle file upload since we removed the YAML option
    if ($upload_file = $form_state->getValue('upload_fid')) {
      $fid = reset($upload_file);
      $file_real_path = $this->contentSyncHelper->getFileRealPathById($fid);
      $file_info = pathinfo($file_real_path);

      try {
        if ($file_info['extension'] === 'zip') {
          $this->contentImporter->importFromZip($file_real_path);
          $this->messenger()->addStatus($this->t('Successfully imported Paragraph Library items.'));
        }
        else {
          throw new \Exception($this->t('Only ZIP files are supported for Paragraph Library import.'));
        }
      }
      catch (\Exception $e) {
        $this->messenger()->addError($e->getMessage());
      }

      // Clean up the temporary uploaded file
      \Drupal::service('single_content_sync.batch_importer')::cleanUploadedFile($fid);
    }
  }

}