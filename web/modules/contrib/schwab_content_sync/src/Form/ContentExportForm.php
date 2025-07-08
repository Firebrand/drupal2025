<?php

namespace Drupal\schwab_content_sync\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\schwab_content_sync\ContentExporterInterface;
use Drupal\schwab_content_sync\ContentFileGeneratorInterface;
use Drupal\schwab_content_sync\ContentSyncHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Defines a form to export content.
 *
 * @package Drupal\schwab_content_sync\Form
 */
class ContentExportForm extends FormBase {

  /**
   * The content exporter service.
   *
   * @var \Drupal\schwab_content_sync\ContentExporterInterface
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
   * @var \Drupal\schwab_content_sync\ContentFileGeneratorInterface
   */
  protected ContentFileGeneratorInterface $fileGenerator;

  /**
   * The content sync helper.
   *
   * @var \Drupal\schwab_content_sync\ContentSyncHelperInterface
   */
  protected ContentSyncHelperInterface $contentSyncHelper;

  /**
   * ContentExportForm constructor.
   *
   * @param \Drupal\schwab_content_sync\ContentExporterInterface $content_exporter
   *   The content exporter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\schwab_content_sync\ContentFileGeneratorInterface $file_generator
   *   The content file generator.
   * @param \Drupal\schwab_content_sync\ContentSyncHelperInterface $content_sync_helper
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
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('schwab_content_sync.exporter'),
      $container->get('entity_type.manager'),
      $container->get('schwab_content_sync.file_generator'),
      $container->get('schwab_content_sync.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'schwab_content_sync_export_form';
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
    $form['deploy_message'] = [
      '#type' => 'markup',
      '#markup' => $this->t('By using the generated file you can import content on deploy'),
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $parameters = $this->getRouteMatch()->getParameters();
    $entity = $this->contentSyncHelper->getDefaultLanguageEntity($parameters);
    
    if (!$entity instanceof FieldableEntityInterface) {
      $this->messenger()->addError($this->t('Unable to export entity.'));
      return;
    }
    
    $file_name = $this->contentSyncHelper->generateContentFileName($entity);

    // Stream a zip with assets.
    $response = new StreamedResponse(function () use ($entity) {
      $file = $this->fileGenerator->generateZipFile($entity, FALSE);
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
      }

      fclose($fp);
      // Delete the temporary file.
      $file->delete();
    }, 200, [
      'Content-disposition' => 'attachment; filename="' . $file_name . '.zip"',
      'Content-Type' => 'application/zip',
    ]);
    $form_state->setResponse($response);
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