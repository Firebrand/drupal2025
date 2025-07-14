# Single Content Sync

A simple way to export/import a node content with all entity references.

## Export content

### Which entity references can be exported?

All entity types can be exported (core, contrib, custom).

You can extend or alter existing logic per entity type. See how to do this in the sections below.

### Can I extend/alter exporting of an entity type?

You can implement a custom event subscriber to subscribe to ExportEvent::class e.g.

```php
public static function getSubscribedEvents(): array {
  return [
    ExportEvent::class => ['onExport'],
  ];
}
```

Another option is to implement a custom plugin `SingleContentSyncBaseFieldsProcessor` where you can isolate your entity export/import logic into a single plugin.

Check out a few examples of existing plugins at `src/Plugin/SingleContentSyncBaseFieldsProcessor`.

### Can I extend/alter exporting of a field type?

You can implement a custom event subscriber to subscribe to ExportFieldEvent::class e.g.

```php
public static function getSubscribedEvents(): array {
  return [
    ExportFieldEvent::class => ['onFieldExport'],
  ];
}
```

Another option is to implement a custom plugin `SingleContentSyncFieldProcessor` where you can isolate your field export/import logic into a single plugin.

Check out a few examples of existing plugins at `src/Plugin/SingleContentSyncFieldProcessor`.

## Import content

### Can I alter importing of an entity?

Similar to exporting, there's an `ImportEvent` which
could be subscribed to alter the imported entity before it's saved.

For fields the event is `ImportFieldEvent`.

### Can I import my content on deploy?

Yes! Please use the importer service and hook_update_N or similar to do it.

```php
function example_update_11001() {
  $file_path = \Drupal::service('extension.list.module')
    ->getPath('example') . '/assets/homepage.yml';

  \Drupal::service('single_content_sync.importer')
    ->importFromFile($file_path);
}
```

If you would like to import content from a generated zip file,
use the following code:

```php
function example_update_11001() {
  $file_path = \Drupal::service('extension.list.module')
    ->getPath('example') . '/assets/homepage.zip';

  \Drupal::service('single_content_sync.importer')
    ->importFromZip($file_path);
}
```

Find more details here:

https://www.drupal.org/docs/extending-drupal/contributed-modules/contributed-module-documentation/single-content-sync/importing-content#s-importing-content-on-deploy.

## Drush commands

You can use Drush commands to export and import your content.

### Export

To export content you can use `drush content:export`. By default, the command will export all entities of type `Node` at the following location: `DRUPAL_ROOT/scs-export`.
You can customize the execution of the command by passing it some parameters and options.
The first parameter will change the entity types being exported (e.g. `taxonomy_term`, `block_content`, etc.).
The second parameter will specify an output path from DRUPAL_ROOT.
For example: `drush content:export block_content ./export-folder` will export all entities of type `block_content` in the `DRUPAL_ROOT/export-folder` directory (if the export-folder directory does not exist, a new one will be created).

The following options can also be passed to the command:

-   `--translate` if used, the export will also contain the translated content
-   `--assets` if used, the export will also contain all necessary assets
-   `--all-content` if used, the export will contain all entities of all entity types
-   `--dry-run` if used, the terminal will show an example output without performing the export
-   `--bundle` if used, all entities of specific type and bundle will be exported. if `--all-content` is used, it will take priority over this option.
-   `--entities` if used, only the entities passed (using entity id or uuid) will be in the export. Usage: `drush content:export --entities="1,4,7"`. if `--all-content` is used, it will take priority over this option.

### Import

To import content you can use `drush content:import`. The import command requires a `path` parameter to import content from.
The `path` parameter is a relative path to the DRUPAL_ROOT folder.
For example: `drush content:import export-folder/content-bulk-export.zip` will import the contents of a zip folder in the following location `DRUPAL_ROOT/export-folder/content-bulk-export.zip`.

## Documentation

Check out the guide to see the module’s overview and the guidelines for using it

https://www.drupal.org/docs/contributed-modules/single-content-sync
