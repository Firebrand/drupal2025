<?php

namespace Drupal\schwab_content_sync\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to configure Schwab Content Sync settings.
 *
 * @package Drupal\schwab_content_sync\Form
 */
class ContentSyncConfigForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $entityTypeBundleInfo;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected StreamWrapperManagerInterface $streamWrapperManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('stream_wrapper_manager')
    );
  }

  /**
   * ContentSyncConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity bundle info.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   *   The entity bundle info.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typed_config_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, StreamWrapperManagerInterface $stream_wrapper_manager) {
    // Still support older drupal version is one argument for the constructor.
    if (floatval(\Drupal::VERSION) < 10.2) {
      parent::__construct($config_factory);
    }
    else {
      parent::__construct($config_factory, $typed_config_manager);
    }

    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'schwab_content_sync_admin_settings';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, string>
   */
  protected function getEditableConfigNames(): array {
    return [
      'schwab_content_sync.settings',
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
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('schwab_content_sync.settings');

    $form['site_uuid_check'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site UUID check'),
      '#description' => $this->t('Enables checking for source/destination Site UUID value during the export. If imported content has been retrieved from another instance of the site, that does not match UUID value of the current site, it will not be imported.'),
      '#default_value' => $config->get('site_uuid_check'),
    ];

    $file_schemas = $this->streamWrapperManager->getNames(StreamWrapperInterface::WRITE);
    $form['file_schema'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Directory file schema'),
    ];
    $form['file_schema']['import_directory_schema'] = [
      '#type' => 'select',
      '#title' => $this->t('Import directory schema'),
      '#description' => $this->t('What file schema you would like to use during import process.'),
      '#options' => $file_schemas,
      '#required' => TRUE,
      '#default_value' => $config->get('import_directory_schema'),
    ];
    $form['file_schema']['export_directory_schema'] = [
      '#type' => 'select',
      '#title' => $this->t('Export directory schema'),
      '#description' => $this->t('What file schema you would like to use during export process.'),
      '#options' => $file_schemas,
      '#required' => TRUE,
      '#default_value' => $config->get('export_directory_schema'),
    ];

    $entity_types = $this->entityTypeManager->getDefinitions();
    /** @var array<string, mixed> $allowed_types */
    $allowed_types = [
      '#prefix' => '<h4>' . $this->t('Allowed content to export') . '</h4>',
      '#tree' => TRUE,
    ];
    
    $allowed_entity_types = $config->get('allowed_entity_types');
    if (!is_array($allowed_entity_types)) {
      $allowed_entity_types = [];
    }
    
    foreach ($entity_types as $entity_type) {
      if (!$entity_type->hasLinkTemplate('single-content:export')) {
        continue;
      }

      $entity_type_id = $entity_type->id();
      $allowed_types[$entity_type_id] = [
        '#type' => 'fieldset',
      ];
      $allowed_types[$entity_type_id]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $entity_type->getLabel(),
        '#default_value' => array_key_exists($entity_type_id, $allowed_entity_types),
      ];

      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      if ($bundles) {
        /** @var array<string, string> $bundles_as_options */
        $bundles_as_options = [];
        foreach ($bundles as $bundle_id => $bundle_info) {
          $bundles_as_options[$bundle_id] = $bundle_info['label'] ?? $bundle_id;
        }
        
        $default_bundles = $allowed_entity_types[$entity_type_id] ?? [];
        if (!is_array($default_bundles)) {
          $default_bundles = [];
        }
        
        $allowed_types[$entity_type_id]['bundles'] = [
          '#type' => 'checkboxes',
          '#title' => $entity_type->getBundleLabel(),
          '#options' => $bundles_as_options,
          '#default_value' => $default_bundles,
          '#description_display' => 'before',
          '#description' => $this->t('Leave empty to enable on all @plural_label.', [
            '@plural_label' => $entity_type->getPluralLabel(),
          ]),
          '#states' => [
            'visible' => [
              ':input[name="allowed_types[' . $entity_type_id . '][enabled]"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }
    $form['allowed_types'] = $allowed_types;

    return parent::buildForm($form, $form_state);
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
    $source = $form_state->getValue('allowed_types');
    /** @var array<string, array<string>> $allowed_types */
    $allowed_types = [];
    
    if (is_array($source)) {
      foreach ($source as $entity_type_id => $info) {
        if (is_array($info) && !empty($info['enabled'])) {
          $bundles = $info['bundles'] ?? [];
          if (is_array($bundles)) {
            $allowed_types[$entity_type_id] = array_keys(array_filter($bundles));
          } else {
            $allowed_types[$entity_type_id] = [];
          }
        }
      }
    }

    $this->configFactory->getEditable('schwab_content_sync.settings')
      ->set('allowed_entity_types', $allowed_types)
      ->set('site_uuid_check', $form_state->getValue('site_uuid_check'))
      ->set('import_directory_schema', $form_state->getValue('import_directory_schema'))
      ->set('export_directory_schema', $form_state->getValue('export_directory_schema'))
      ->save();

    parent::submitForm($form, $form_state);

    // Flush cache to update operation forms.
    drupal_flush_all_caches();
  }

}