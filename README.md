# Go1 content marketplace plugin for Totara

`contentmarketplace_goone` integrates the [Go1](https://www.go1.com) content library with Totara Learn through the Totara content marketplace framework. It lets site administrators connect a Go1 portal, curate content in the embedded Go1 Content Hub, sync the selected learning objects into Totara as courses, and receive completion data back from Go1.

This repository holds the uplifted version of the plugin, rebuilt against the Go1 API v3 and Totara 20. It replaces the `contentmarketplace_goone` plugin shipped with Totara core.

## Compatibility

The plugin requires Totara 20. It is not compatible with earlier Totara versions.

## What the repository contains

The repository is the plugin directory itself. It is installed at:

```
server/totara/contentmarketplace/contentmarketplaces/goone
```

Main parts:

| Path | Purpose |
|------|---------|
| `classes/api.php`, `classes/rest_client.php`, `classes/oauth*.php` | Go1 API v3 client, OAuth authorisation and token refresh. |
| `classes/contentmarketplace.php`, `classes/plugininfo.php` | Marketplace plugin registration with the Totara content marketplace framework. |
| `classes/sync_action/sync_learning_objects.php` | Content sync: pulls learning objects from selected collections and creates courses. |
| `classes/helper.php` | Course creation, completion defaults, retired content processing. |
| `classes/webhook.php`, `payload.php` | Webhook subscription management and the endpoint that receives `enrollment.complete` events. |
| `classes/embed.php`, `curate.php` | Embedded Go1 Content Hub for content curation. |
| `classes/workflow/`, `coursecreate.php`, `activitycreate.php` | Add course and add activity workflows. |
| `classes/controllers/`, `tabs/`, `settings.php`, `setup.php` | Tabbed admin settings page and setup wizard. |
| `classes/learning_object/`, `classes/model/`, `classes/entity/` | Learning object model, ORM entities and repositories. |
| `db/` | Capabilities, caches, hooks, install schema and upgrade steps. |
| `amd/`, `templates/`, `less/`, `pix/` | Frontend assets and Mustache templates. |
| `lang/en/` | Language strings. |
| `tests/` | PHPUnit suites covering the API client, OAuth, search, sync, webhook, embed, upgrade and manual course creation. |
| `CHANGELOG.md` | Release notes. |

## Features

- **Go1 API v3.** Supported API version `2025-01-01`.
- **Content curation.** The Go1 Content Hub is embedded in Totara. The Totara user is matched to or created as a Go1 account and given the Content administrator role.
- **Content sync.** Published learning objects from the chosen collections (free, subscribed, custom) are created as courses with image, generated description, SCORM module and completion criteria. Runs from the core marketplace scheduled task; custom library can also be synced manually using the Sync content button.
- **Retired content processing.** Courses built on retired or removed learning objects can be banner marked, annotated, moved to a category or hidden.
- **Completion webhook.** The plugin receives `enrollment.complete` notifications from Go1, these events are verified and mapped to SCORM activity completions in Totara.
- **Add course and add activity workflows.** Create a new course from a Go1 learning object manually, or add one as an activity to an existing course.
- **Tabbed settings.** General, Content sync, Retired content, Content access and Webhook tabs.

See [CHANGELOG.md](CHANGELOG.md) for the full list of changes against the core plugin.

## Manual installation

1. Remove or replace the core plugin directory in your Totara 20 site:

   ```
   server/totara/contentmarketplace/contentmarketplaces/goone
   ```

2. Clone this repository into that location, checking out the branch for your Totara version:

   ```bash
   cd /path/to/totara/server/totara/contentmarketplace/contentmarketplaces
   git clone -b TOTARA_20_STABLE <repository-url> goone
   ```

3. Run the Totara upgrade, either through Site administration or from the CLI:

   ```bash
   php server/admin/cli/upgrade.php
   ```

4. Purge caches.

Upgrading from the core plugin is supported. The upgrade step migrates the stored content access setting to the new collection names and enables the add activity workflow. After the plugin has been upgraded, click the "Set up" to authorise the integration with additional scopes.

## Configuration

1. Go to **Site administration > Content Marketplace > Manage content marketplaces** and set up Go1. The setup wizard walks through OAuth authorisation and content access.
2. On the **Content sync** tab choose the collections to sync, the target course category, the course type and the shortname strategy.
3. On the **Webhook** tab create the webhook so Go1 completions flow back into Totara.
4. Optionally configure the **Retired content** tab to define what happens to courses whose Go1 content is retired or removed.

Content curation requires the `contentmarketplace/goone:curatecontent` capability, granted to the Manager archetype by default.

## Requirements on the Go1 side

The OAuth client requests the following scopes in GO1:

```
lo.read lo.write enrollment.read portal.read portal.write user.read user.write user.login webhook.read webhook.write
```

If a scope is missing or not granted automatically, the plugin raises an error asking you to contact Go1 support. Once the support help to add the missing scope, click the Set up link again to authorise the integration with the full set of scopes.

## Licence

GNU General Public License v3 or later. See the file headers for copyright notices.
