<?php

namespace Drupal\schwab_content_sync\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Override the import route
    if ($route = $collection->get('single_content_sync.import')) {
      $route->setDefault('_form', '\Drupal\schwab_content_sync\Form\SchwabContentSyncImportForm');
      $route->setDefault('_title', 'Import Paragraph Library Items');
    }

    // Override the export route for paragraph library items
    if ($route = $collection->get('entity.paragraphs_library_item.single_content:export')) {
      $route->setDefault('_form', '\Drupal\schwab_content_sync\Form\SchwabContentSyncExportForm');
      $route->setDefault('_title', 'Export Paragraph Library Item');
    }
  }

}