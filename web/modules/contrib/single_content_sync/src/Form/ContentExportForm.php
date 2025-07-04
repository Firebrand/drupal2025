<?php

namespace Drupal\single_content_sync\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\single_content_sync\ContentExporterInterface;
use Drupal\single_content_sync\ContentFileGeneratorInterface;
use Drupal\single_content_sync\ContentSyncHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Defines a form to export content.
 *
 * @package Drupal\single_content_sync\Form
 */
class ContentExportForm extends FormBase {

  /**
   * The content exporter service.
   *
   * @var \Drupal\single_content_sync\ContentExporterInterface
   */
  protected ContentExporterInterface $contentExporter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The content file generator.
   *
   * @var \Drupal\single_content_sync\ContentFileGeneratorInterface
   */
  protected ContentFileGeneratorInterface $fileGenerator;

  /**
   * The content sync helper.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * ContentExportForm constructor.
   *
   * @param \Drupal\single_content_sync\ContentExporterInterface $content_exporter
   *   The content exporter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\single_content_sync\ContentFileGeneratorInterface $file_generator
   *   The content file generator.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   */
  public function __construct(ContentExporterInterface $content_exporter, EntityTypeManagerInterface $entity_type_manager, ContentFileGeneratorInterface $file_generator, ContentSyncHelperInterface $content_sync_helper) {
    $this->contentExporter = $content_exporter;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileGenerator = $file_generator;
    $this->contentSyncHelper = $content_sync_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('single_content_sync.exporter'),
      $container->get('entity_type.manager'),
      $container->get('single_content_sync.file_generator'),
      $container->get('single_content_sync.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'single_content_sync_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $extract_translations = $form_state->getValue('translation', FALSE);
    $parameters = $this->getRouteMatch()->getParameters();
    $entity = $this->contentSyncHelper->getDefaultLanguageEntity($parameters);

    $export_in_yaml = $this->contentExporter->doExportToYml($entity, $extract_translations);

    $form['output'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Exported content'),
      '#attributes' => [
        'data-yaml-editor' => 'true',
      ],
      '#wrapper_attributes' => [
        'id' => 'exported-content',
      ],
      '#value' => $export_in_yaml,
      '#attached' => [
        'library' => [
          'single_content_sync/yaml_editor',
        ],
      ],
    ];

    $form['translation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include all translations?'),
      '#description' => $this->t('The exported content will be refreshed to preview it with translations.'),
      '#ajax' => [
        'callback' => '::refreshContent',
        'wrapper' => 'exported-content',
        'effect' => 'fade',
        'progress' => [
          'type' => 'fullscreen',
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['download_zip'] = [
      '#type' => 'submit',
      '#name' => 'download_zip',
      '#button_type' => 'primary',
      '#value' => $this->t('Download as a zip with all assets'),
    ];

    $form['actions']['download_file'] = [
      '#type' => 'submit',
      '#name' => 'download_file',
      '#value' => $this->t('Download as a file'),
    ];

    return $form;
  }

  /**
   * Ajax callback to refresh output field.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The refreshed form element.
   */
  public function refreshContent(array &$form, FormStateInterface $form_state): array {
    // Clean up warning messages when refreshing field.
    $this->messenger()->deleteByType('warning');

    return $form['output'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $button = $form_state->getTriggeringElement();
    $extract_translations = $form_state->getValue('translation', FALSE);
    $parameters = $this->getRouteMatch()->getParameters();
    $entity = $this->contentSyncHelper->getDefaultLanguageEntity($parameters);
    $file_name = $this->contentSyncHelper->generateContentFileName($entity);
    $exported_entity = $this->contentExporter->doExportToYml($entity, $extract_translations);

    // Stream a YML without assets.
    if ($button['#name'] === 'download_file') {
      $response = new StreamedResponse(static function() use ($exported_entity) {
        echo $exported_entity;
      }, 200, [
        'Content-disposition' => 'attachment; filename="' . $file_name . '.yml"',
        'Content-Type' => 'application/yaml',
      ]);
      $form_state->setResponse($response);
      return;
    }

    // Stream a zip with assets.
    if ($button['#name'] === 'download_zip') {
      $response = new StreamedResponse(function() use ($entity, $extract_translations) {
        $file = $this->fileGenerator->generateZipFile($entity, $extract_translations);
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
        'Content-disposition' => 'attachment; filename="' . $file_name . '.zip"',
        'Content-Type' => 'application/zip',
      ]);

      $form_state->setResponse($response);
    }
  }

  /**
   * Check if user has access to the export form.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(): AccessResultInterface {
    $parameters = $this->getRouteMatch()->getParameters();
    $entity = $parameters->getIterator()->current();

    if (is_string($entity)) {
      $entity = $parameters->get($entity);
    }

    if (!$entity instanceof EntityInterface) {
      return AccessResult::forbidden();
    }

    $hasAccess = $this->contentSyncHelper->access($entity);

    return $hasAccess ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
