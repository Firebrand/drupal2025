<?php

namespace Drupal\Tests\single_content_sync\Kernel\FieldProcessor;

use CommerceGuys\Addressing\Zone\Zone;

/**
 * @coversDefaultClass \Drupal\single_content_sync\Plugin\SingleContentSyncFieldProcessor\AddressZone
 */
class AddressZoneTest extends FieldProcessorTestBase {

  /**
   * {@inheritdoc}
   */
  public static function importFieldValueDataProvider(): array {
    return [
      'address_zone' => [
        [
          'type' => 'address_zone',
          'settings' => [
            'available_countries' => [],
          ],
        ],
        [
          0 => [
            'id' => 'field_zone',
            'label' => 'zone',
            'territories' => [
              0 => [
                'country_code' => 'UA',
                'administrative_area' => '07',
                'locality' => NULL,
                'dependent_locality' => NULL,
                'included_postal_codes' => NULL,
                'excluded_postal_codes' => NULL,
              ],
            ],
          ],
        ],
        [
          0 => [
            'id' => 'field_zone',
            'label' => 'zone',
            'territories' => [
              0 => [
                'country_code' => 'UA',
                'administrative_area' => '07',
                'locality' => NULL,
                'dependent_locality' => NULL,
                'included_postal_codes' => NULL,
                'excluded_postal_codes' => NULL,
              ],
            ],
          ],
        ],
        ['address'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function exportFieldValueDataProvider(): array {
    return [
      'address_zone' => [
        [
          'type' => 'address_zone',
          'settings' => [
            'available_countries' => [],
          ],
        ],
        new Zone([
          'id' => 'field_zone',
          'label' => 'zone',
          'territories' => [
            [
              'country_code' => 'UA',
              'administrative_area' => '07',
              'locality' => NULL,
              'dependent_locality' => NULL,
              'included_postal_codes' => NULL,
              'excluded_postal_codes' => NULL,
            ],
          ],
        ]),
        [
          0 => [
            'id' => 'field_zone',
            'label' => 'zone',
            'territories' => [
              0 => [
                'country_code' => 'UA',
                'administrative_area' => '07',
                'locality' => NULL,
                'dependent_locality' => NULL,
                'included_postal_codes' => NULL,
                'excluded_postal_codes' => NULL,
              ],
            ],
          ],
        ],
        ['address'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function assertImportedValueEquals(mixed $expectedFieldValue, mixed $actualImportedFieldValue): void {
    foreach ($expectedFieldValue as $delta => $expectedValue) {
      /** @var \CommerceGuys\Addressing\Zone\Zone $zone */
      $zone = $actualImportedFieldValue[$delta]['value'];
      $this->assertInstanceOf(Zone::class, $zone);
      $this->assertEquals($expectedValue['id'], $zone->getId());
      $this->assertEquals($expectedValue['label'], $zone->getLabel());

      $expectedTerritories = $expectedValue['territories'];
      $importedTerritories = $zone->getTerritories();

      $this->assertCount(count($expectedTerritories), $importedTerritories);
      $this->assertEquals(array_keys($expectedTerritories), array_keys($importedTerritories));

      foreach ($expectedTerritories as $territoryDelta => $expectedTerritory) {
        $importedTerritory = $importedTerritories[$territoryDelta];
        $this->assertEquals($expectedTerritory['country_code'], $importedTerritory->getCountryCode());
        $this->assertEquals($expectedTerritory['administrative_area'], $importedTerritory->getAdministrativeArea());
        $this->assertEquals($expectedTerritory['locality'], $importedTerritory->getLocality());
        $this->assertEquals($expectedTerritory['dependent_locality'], $importedTerritory->getDependentLocality());
        $this->assertEquals($expectedTerritory['included_postal_codes'], $importedTerritory->getIncludedPostalCodes());
        $this->assertEquals($expectedTerritory['excluded_postal_codes'], $importedTerritory->getExcludedPostalCodes());
      }
    }
  }

}
