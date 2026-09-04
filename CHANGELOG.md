# Go1 content marketplace plugin (contentmarketplace_goone) changelog

# Version 2.0.0

Baseline: Totara core 20.0.0, plugin version 2026011500.
Release: version 2.0.0, plugin version 2026090400.
Supported Totara versions: Totara 20.0.0+

## Platform

- **Go1 API v3.** All calls now go to `gateway.go1.com` with the `Api-Version: 2025-01-01` header. The v2 account, configuration and collection-item endpoints are gone. New endpoints: user-accounts (search, get, create, update, login), enrollments, webhooks, and scrolled learning-object listing for large collections.
- **Collections renamed** from all / subscribed / collection to free / subscribe / custom. An upgrade step maps the stored content access setting.
- **OAuth changes.** Authorization requests a wider scope set (`lo.read lo.write enrollment.read portal.read portal.write user.read user.write user.login webhook.read webhook.write`). Token refresh falls back to client credentials when the refresh token is rejected. Missing scopes raise a dedicated exception with guidance to contact Go1 support.

## New functionality

- **Content curation page** (`curate.php`). Embeds the Go1 Content Hub in an iframe using a one-time token. The Totara user is matched to a Go1 account by the mapping table or by email, created if missing, and granted the Content administrator role. Includes a "Sync content" button that runs the custom-collection sync via AJAX (Free and Subscribed collections are synced by the scheduled task only).
- **Content sync** (new `sync_learning_objects` sync action, run by the core marketplace scheduled task `\totara_contentmarketplace\task\sync_task` or by the button). Pulls published learning objects from the selected collections, optionally filtered by region relevance, and creates courses automatically. Course category, single or multi activity course type, and shortname strategy are configurable. Courses receive the Go1 image, a generated description (overview, learning outcomes, topics, skills covered, duration), a SCORM module, and completion criteria.
- **Retired content processing.** The sync also detects learning objects in retired state and acts on courses that use them. Configurable actions on retirement: course image banner ("Leaving on ..."), summary notice, move to category, hide. Actions on removal: move, hide. Scope is either the sync category only or the whole site. Actions are tracked per learning object so each runs once.
- **Webhook** (`payload.php`). Subscribes to `enrollment.complete`, verifies the Go1 signature with a timestamp tolerance, resolves the Go1 user to a Totara user through several matching strategies, and marks the matching SCORM activities complete. Failures are logged to a new table instead of returning HTTP errors. The admin tab shows the webhook, detects configuration drift, and can create or update it.
- **Add activity to an existing course.** New `create_marketplace_activity` workflow and `activitycreate.php` page with a confirmation step. Enabled on install and on upgrade.
- **Manual course creation toggle.** The "Add courses from Go1" workflow redirects to the curation page unless manual creation is enabled. Default is disabled for new and upgraded sites. Manual creation applies the same completion defaults as sync.

## Amended functionality

- **Settings page** rebuilt as a tabbed admin page with General, Content sync, Retired content, Content access and Webhook tabs. The Client ID is displayed in General.
- **Setup wizard** dropped the account stage. Content access is a multi-select of collections, defaulting to custom. After setup the user lands on the marketplaces list rather than the explorer.
- **Explorer** filters moved to v3 facets: Tags became Topics, providers and languages read from the new facet shapes, and the availability filter offers free, subscribe and custom according to content access settings. Multi-select of learning objects was removed in favour of single selection.
- **Explore marketplace workflow** opens the curation page instead of the explorer.
- **Course creation** uses the container course helper, applies the audience visibility default, and ticks both Passed and Completed for SCORM completion.
- **Learning object model** reads the v3 field layout (`core.title`, `core.image.value`, `relevance.language`).
- **REST client** gained PATCH support and configurable timeouts.

## Retired functionality

- Account and plan display (`account.php`, account template, `account_plan_*` config values) and the standalone content settings page.
- Curating Go1 collections through the API. The collection class now throws and directs users to the embedded hub.
- Pay-per-seat sync with Go1 configuration.
- Behat features for course creation, filters and OAuth, along with the v2 fixture set. Replaced by PHPUnit suites covering API, OAuth, search, helper, sync, webhook, embed, upgrade and manual course creation.

## New permission

- `contentmarketplace/goone:curatecontent` (system context, write, granted to the Manager archetype). Required for the curation page, and for the display of the "Manage available content" button (manual course creation flow).

## Database

- New tables `marketplace_goone_user_map` (Totara user to Go1 user, unique per user) and `marketplace_goone_webhook_logs`.
- `marketplace_goone_learning_object`: added columns`retired_time`, `retired_actioned`, `removed_time` and `removed_actioned`.

## New config keys

`create_course_manual`, `create_courses`, `course_category`, `course_type`, `course_shortname`, `sync_collections`, `content_regions`, `retired_content_process`, `retired_content_location`, `retired_content_retired_actions`, `retired_content_removed_actions`, `retired_content_actions_move_category`, `retired_content_dateformat`, `webhook_secret_key`, `last_synced_new_content`.
