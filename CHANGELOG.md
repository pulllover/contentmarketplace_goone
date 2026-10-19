# Go1 content marketplace plugin (contentmarketplace_goone) changelog

# Version 2.0.0

Baseline: Totara core 20.0.0, plugin version 2026011500.
Release: version 2.0.0, plugin version 2026090400.
Compatible Totara versions: Totara 20.0.0 and later.

## Platform

- **Go1 API v3.** All calls go to `gateway.go1.com` with the `Api-Version: 2025-01-01` header. The plugin does not use the v2 account, configuration and collection-item endpoints. New endpoints:
  - User accounts: search, get, create, update and login.
  - Enrollments.
  - Webhooks.
  - Learning-object list with scroll, for large collections.
- **New collection names.** The old names "all", "subscribed" and "collection" are now "free", "subscribe" and "custom". An upgrade step changes the stored content access setting to the new names.
- **OAuth changes.**
  - Authorisation asks for more scopes: `lo.read lo.write enrollment.read portal.read portal.write user.read user.write user.login webhook.read webhook.write`.
  - If Go1 rejects the refresh token, the token refresh uses the client credentials.
  - If a scope is missing, the plugin shows a special error. The error tells the user to speak to Go1 support.

## New functionality

- **Content curation page** (`curate.php`).
  - The page shows the Go1 Content Hub in an iframe. It uses a one-time token.
  - The plugin finds the Go1 account for the Totara user. It looks in the mapping table first, then it compares the email. If there is no account, the plugin makes one.
  - The plugin gives the account the Content administrator role.
  - The "Sync content" button syncs the custom collection through AJAX. Only the scheduled task syncs the Free and Subscribed collections.
- **Content sync** (new `sync_learning_objects` sync action).
  - The core marketplace scheduled task `\totara_contentmarketplace\task\sync_task` runs the sync. The "Sync content" button also runs it.
  - The sync gets the published learning objects from the selected collections. A region relevance filter is optional. The sync then makes the courses automatically.
  - You can configure the course category, the course type (single activity or multi activity) and the shortname strategy.
  - Each course gets these items:
    - The Go1 image.
    - A description with an overview, learning outcomes, topics, skills, and duration.
    - A SCORM module.
    - Completion criteria.
- **Retired content process.**
  - The sync finds learning objects in the retired state. Then it does actions on the courses that use them.
  - Actions for retired content (configurable): a banner on the course image ("Leaving on ..."), a notice in the summary, a move to a category, and hide.
  - Actions for removed content: move and hide.
  - The scope is the sync category only or the full site.
  - The plugin records the actions for each learning object. Thus each action occurs one time only.
- **Webhook** (`payload.php`).
  - The webhook subscribes to `enrollment.complete`.
  - The plugin examines the Go1 signature and the timestamp. The timestamp must be in the permitted tolerance.
  - The plugin uses different methods to find the Totara user for the Go1 user.
  - The plugin records the completion of the related SCORM activities.
  - If an error occurs, the plugin writes it to a new log table. It does not send an HTTP error.
  - The admin tab shows the webhook. It finds differences in the webhook configuration. It can create or update the webhook.
- **Add an activity to a course.** New `create_marketplace_activity` workflow and `activitycreate.php` page with a confirmation step. The install and the upgrade enable this workflow.
- **Manual course creation setting.**
  - If manual creation is disabled, the "Add courses from Go1" workflow opens the curation page.
  - The default is disabled for new sites and upgraded sites.
  - Manual creation uses the same completion defaults as the sync.

## Changed functionality

- **Settings page.** It is now an admin page with these tabs: General, Content sync, Retired content, Content access and Webhook. The General tab shows the Client ID.
- **Setup wizard.**
  - The account step is removed.
  - Content access is a multi-select list of collections. The default is "custom".
  - After setup, the wizard opens the marketplaces list, not the explorer.
- **Explorer.**
  - The filters use the v3 facets. "Tags" is now "Topics".
  - The provider and language filters use the new facet structure.
  - The availability filter shows free, subscribe and custom, as set in the content access settings.
  - You can select one learning object only. The multi-select function is removed.
- **"Explore marketplace" workflow.** It opens the curation page, not the explorer.
- **Course creation.**
  - It uses the container course helper.
  - It applies the default audience visibility.
  - For SCORM completion, it selects both Passed and Completed.
- **Learning object model.** It reads the v3 fields (`core.title`, `core.image.value`, `relevance.language`).
- **REST client.** It can send PATCH requests. You can configure the timeouts.

## Removed functionality

- The account and plan display: `account.php`, the account template and the `account_plan_*` config values.
- The standalone content settings page.
- Curation of Go1 collections through the API. The collection class now throws an exception. The exception tells users to use the Go1 Content Hub in Totara.
- Pay-per-seat sync with the Go1 configuration.
- Behat features for course creation, filters and OAuth, and the v2 fixture set. New PHPUnit tests replace them. These tests are for the API, OAuth, search, helper, sync, webhook, embed, upgrade and manual course creation.

## New capability

- `contentmarketplace/goone:curatecontent` (system context, write). The Manager archetype has this capability. A user must have this capability for these items:
  - The curation page.
  - The "Manage available content" button (manual course creation).

## Database

- New tables:
  - `marketplace_goone_user_map`: the Totara user and the related Go1 user. One row for each Totara user.
  - `marketplace_goone_webhook_logs`.
- `marketplace_goone_learning_object`: new columns `retired_time`, `retired_actioned`, `removed_time` and `removed_actioned`.

## New config keys

`create_course_manual`, `create_courses`, `course_category`, `course_type`, `course_shortname`, `sync_collections`, `content_regions`, `retired_content_process`, `retired_content_location`, `retired_content_retired_actions`, `retired_content_removed_actions`, `retired_content_actions_move_category`, `retired_content_dateformat`, `webhook_secret_key`, `last_synced_new_content`.
