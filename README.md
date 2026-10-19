# Go1 content marketplace plugin for Totara

`contentmarketplace_goone` connects the [Go1](https://www.go1.com) content library to Totara Learn. It uses the Totara content marketplace framework. With this plugin, a site administrator can do these tasks:

- Connect a Go1 portal to Totara.
- Curate content in the Go1 Content Hub, which shows in Totara.
- Sync the selected learning objects into Totara as courses.
- Receive completion data from Go1.

This repository contains the uplifted version of the plugin. The plugin uses the Go1 API v3 and Totara 20. It replaces the `contentmarketplace_goone` plugin in Totara core.

## Compatibility

The plugin is compatible with Totara 20 only. It is not compatible with earlier Totara versions.

## Repository contents

The repository is the plugin directory. Install it at this location:

```
server/totara/contentmarketplace/contentmarketplaces/goone
```

Main parts:

| Path | Function |
|------|----------|
| `classes/api.php`, `classes/rest_client.php`, `classes/oauth*.php` | Go1 API v3 client, OAuth authorisation and token refresh. |
| `classes/contentmarketplace.php`, `classes/plugininfo.php` | Registration of the plugin in the Totara content marketplace framework. |
| `classes/sync_action/sync_learning_objects.php` | Content sync. Gets learning objects from the selected collections and makes courses. |
| `classes/helper.php` | Course creation, completion defaults and retired content process. |
| `classes/webhook.php`, `payload.php` | Webhook subscription. Endpoint that receives `enrollment.complete` events. |
| `classes/embed.php`, `curate.php` | Go1 Content Hub in Totara, for content curation. |
| `classes/workflow/`, `coursecreate.php`, `activitycreate.php` | "Add course" and "add activity" workflows. |
| `classes/controllers/`, `tabs/`, `settings.php`, `setup.php` | Admin settings page with tabs, and setup wizard. |
| `classes/learning_object/`, `classes/model/`, `classes/entity/` | Learning object model, ORM entities and repositories. |
| `db/` | Capabilities, caches, hooks, install schema and upgrade steps. |
| `amd/`, `templates/`, `less/`, `pix/` | Frontend assets and Mustache templates. |
| `lang/en/` | Language strings. |
| `tests/` | PHPUnit tests for the API client, OAuth, search, sync, webhook, embed, upgrade and manual course creation. |
| `CHANGELOG.md` | Release notes. |

## Features

- **Go1 API v3.** The plugin uses API version `2025-01-01`.
- **Content curation.** The Go1 Content Hub shows in Totara. The plugin finds the Go1 account for the Totara user. If there is no account, the plugin makes one. The plugin gives the account the Content administrator role.
- **Content sync.** The plugin gets the published learning objects from the selected collections (free, subscribed, custom). It makes a course for each learning object. Each course has an image, a description, a SCORM module and completion criteria. The core marketplace scheduled task runs the sync. To sync the custom collection immediately, use the "Sync content" button.
- **Retired content process.** Go1 can retire or remove a learning object. The plugin can then do these actions on the related courses: add a banner, add a notice, move the course to a category, or hide the course.
- **Completion webhook.** Go1 sends `enrollment.complete` notifications to the plugin. The plugin examines each notification. Then it records the completion of the related SCORM activity in Totara.
- **"Add course" and "add activity" workflows.** You can make a course from a Go1 learning object manually. You can also add a learning object as an activity to a course.
- **Settings tabs.** The settings page has these tabs: General, Content sync, Retired content, Content access and Webhook.

For the full list of changes from the core plugin, refer to [CHANGELOG.md](CHANGELOG.md).

## Manual installation

1. In your Totara 20 site, remove or replace the core plugin directory:

   ```
   server/totara/contentmarketplace/contentmarketplaces/goone
   ```

2. Clone this repository into that location. Use the branch for your Totara version:

   ```bash
   cd /path/to/totara/server/totara/contentmarketplace/contentmarketplaces
   git clone -b TOTARA_20_STABLE <repository-url> goone
   ```

3. Do the Totara upgrade. Use Site administration or the CLI:

   ```bash
   php server/admin/cli/upgrade.php
   ```

4. Purge the caches.

You can upgrade from the core plugin. The upgrade step changes the stored content access setting to the new collection names. It also enables the "add activity" workflow. After the upgrade, click "Set up" again. This authorises the integration with the additional scopes.

## Configuration

1. Go to **Site administration > Content Marketplace > Manage content marketplaces**. Set up Go1. The setup wizard shows the steps for OAuth authorisation and content access.
2. On the **Content sync** tab, select these items:
   - The collections to sync.
   - The target course category.
   - The course type.
   - The shortname strategy.
3. On the **Webhook** tab, create the webhook. Go1 then sends completions to Totara.
4. On the **Retired content** tab, you can set the actions for courses that use retired or removed Go1 content. This step is optional.

To curate content, a user must have the `contentmarketplace/goone:curatecontent` capability. By default, the Manager archetype has this capability.

## Go1 requirements

The OAuth client asks Go1 for these scopes:

```
lo.read lo.write enrollment.read portal.read portal.write user.read user.write user.login webhook.read webhook.write
```

If Go1 does not give a scope, the plugin shows an error. The error tells you to speak to Go1 support. After Go1 support adds the missing scope, click "Set up" again. This authorises the integration with all the scopes.

## Licence

GNU General Public License v3 or later. Refer to the file headers for the copyright notices.
