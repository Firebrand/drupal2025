<?php

namespace Drupal\schwab_content_sync\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\single_content_sync\Form\ContentExportForm;

/**
 * Customized export form for Paragraph Library Items.
 */
class SchwabContentSyncExportForm extends ContentExportForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'schwab_content_sync_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the parent form
    $form = parent::buildForm($form, $form_state);
    
    // Remove the YAML output field
    unset($form['output']);
    
    // Add library item information
    $entity = $this->contentSyncHelper->getDefaultLanguageEntity($this->getRouteMatch()->getParameters());
    
    $form['info'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Export Information'),
    ];
    
    $form['info']['title'] = [
      '#type' => 'item',
      '#title' => $this->t('Library Item'),
      '#markup' => '<strong>' . $entity->label() . '</strong>',
    ];
    
    $form['info']['paragraphs_count'] = [
      '#type' => 'item',
      '#title' => $this->t('Number of Paragraphs'),
      '#markup' => count($entity->get('paragraphs')),
    ];
    
    // Customize translation checkbox
    $form['translation']['#title'] = $this->t('Include translations of paragraphs?');
    $form['translation']['#description'] = $this->t('Export all available translations of the paragraphs in this library item.');
    
    // Remove AJAX since we don't have the output field
    unset($form['translation']['#ajax']);
    
    // Simplify actions - only keep ZIP download
    unset($form['actions']['download_file']);
    $form['actions']['download_zip']['#value'] = $this->t('Download Library Item');
    
    // Add description
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t('Export this Paragraph Library item with all associated images and files. The exported file can be imported on another site.') . '</p>',
      '#weight' => -10,
    ];
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Only handle ZIP download
    $extract_translations = $form_state->getValue('translation', FALSE);
    $entity = $this->contentSyncHelper->getDefaultLanguageEntity($this->getRouteMatch()->getParameters());
    $file_name = $this->contentSyncHelper->generateContentFileName($entity);

    $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function() use ($entity, $extract_translations) {
      $file = $this->fileGenerator->generateZipFile($entity, $extract_translations);
      $fp = fopen($file->getFileUri(), 'rb');

      while (!feof($fp)) {
        echo fread($fp, 8192);
        flush();
      }

      fclose($fp);
      $file->delete();
    }, 200, [
      'Content-disposition' => 'attachment; filename="' . $file_name . '.zip"',
      'Content-Type' => 'application/zip',
    ]);

    $form_state->setResponse($response);
  }

}