<?php

namespace Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor;

use CommerceGuys\Addressing\Zone\Zone;
use CommerceGuys\Addressing\Zone\ZoneTerritory;
use Drupal\address\Plugin\Field\FieldType\ZoneItemList;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\single_content_sync\SingleContentSyncFieldProcessorPluginBase;

/**
 * Plugin implementation for metatag field processor plugin.
 *
 * @SingleContentSyncFieldProcessor(
 *   id = "address_zone",
 *   label = @Translation("Address Zone field processor"),
 *   field_type = "address_zone",
 * )
 */
class AddressZone extends SingleContentSyncFieldProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function exportFieldValue(FieldItemListInterface $field): array {
    assert($field instanceof ZoneItemList);

    $values = [];
    foreach ($field->getValue() as $delta => $item) {
      if (!isset($item['value'])) {
        continue;
      }

      /** @var \CommerceGuys\Addressing\Zone\Zone $zone */
      $zone = $item['value'];

      $values[$delta] = [
        'id' => $zone->getId(),
        'label' => $zone->getLabel(),
        // Serialize ZoneTerritory objects to arrays.
        'territories' => array_map(function (ZoneTerritory $territory) {
          return [
            'country_code' => $territory->getCountryCode(),
            'administrative_area' => $territory->getAdministrativeArea(),
            'locality' => $territory->getLocality(),
            'dependent_locality' => $territory->getDependentLocality(),
            'included_postal_codes' => $territory->getIncludedPostalCodes(),
            'excluded_postal_codes' => $territory->getExcludedPostalCodes(),
          ];
        }, $zone->getTerritories()),
      ];
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function importFieldValue(FieldableEntityInterface $entity, string $fieldName, array $value): void {
    // @see \Drupal\address\Plugin\Field\FieldType\ZoneItem::setValue()
    if (!empty($value)) {
      $entity->set($fieldName, array_map(function (array $zone) {
        return new Zone($zone);
      }, $value));
    }
  }

}
