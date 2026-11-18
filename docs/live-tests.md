# Live tests with real accounts

The live tests check the plugin and its extensions with your real accounts of the services: the connection, the
directory listing, the import, the synchronization, the export (public and private), the delivery to visitors and the
permissions. They replace the usual manual tests after a change.

There are two kinds of live tests, both use the same file `.env.live`:

| | PHPUnit (`composer test-live`) | Playwright (`composer test-live-e2e`) |
|---|---|---|
| Runs in | the WordPress of the unit tests | your development website |
| Tests | the plugin via its abilities and classes | the backend in the browser like a user |
| Needs | `composer test-install-live` once | the plugin [EFML Dev Credentials](#efml-dev-credentials) on the website |

Only services whose credentials are complete in `.env.live` are tested, all others are skipped. Without any complete
credentials the tests show a hint and do nothing.

## .env.live

Copy `.env.live.example` in the directory of the plugin to `.env.live` and enter the credentials of the services you
want to test. The file is listed in `.gitignore` and is never part of the release. Keep it outside of the repository
if you like and set its path in the environment variable `EFML_LIVE_ENV`.

- The keys are the [constants](constants.md) of the plugin and its extensions (`EFML_<SERVICE>_<FIELD>`), plus some
  settings of the tests (`EFML_LIVE_…`).
- Values from the environment win over the values of the file.
- Keys ending with `_FILE` read their value from a file, e.g.
  `EFML_GOOGLE_CLOUD_STORAGE_AUTHENTICATION_JSON_FILE=/path/to/service-account.json`.
- Dropbox and Google Drive use OAuth: connect them once on a website and copy the refresh token into `.env.live`
  (`EFML_LIVE_DROPBOX_REFRESH_TOKEN`, `EFML_LIVE_GOOGLE_DRIVE_REFRESH_TOKEN`). The page "Tools > EFML Dev Credentials"
  of the plugin below shows them.

The tests upload files with names starting with `efml-live-` and delete them afterward, also on the external sources.
Use test directories, test buckets or test accounts.

## PHPUnit

```bash
composer test-install-live   # once: the WordPress of the tests incl. the extensions from GitHub
composer test-live           # run the live tests
```

`composer test-install-live` works like `composer test-install` and additionally installs the extensions into the
WordPress of the tests. Settings in `.env.live` (or in the environment):

| Setting | Meaning |
|---|---|
| `EFML_ADDONS_SOURCE` | `github` (default of `composer test-install-live`), `none` or a directory with your working copies of the extensions side by side, which are linked |
| `EFML_ADDONS_REF` | branch, tag or commit on GitHub (default `master`) |
| `EFML_ADDON_<AWS\|GCS\|DRIVE\|WEBDAV>_SOURCE` / `_REF` | the same for a single extension, e.g. the path of your working copy of the AWS extension |

`composer test-install-addons` installs only the extensions again (e.g. after a change of these settings). Linked
working copies need their `vendor` directory (`composer install`).

What is tested for each service:

- the connection and the directory listing of a saved external source,
- the import of the first file and its delivery to visitors,
- the synchronization of the directory, if it contains at most `EFML_LIVE_MAX_SYNC_FILES` files (default 20) and no
  subdirectories,
- the export of an uploaded image incl. its sizes, its delivery to visitors and its deletion on the external source,
- the same as private file (for services with private files),
- the export of an upload of an editor without the capability for the service, who could not import files of it,
- the URLs in `EFML_LIVE_URLS` via "Add URL".

If the external source of a service allows only private files (e.g. a bucket with "Block public access"), list the
service in `EFML_LIVE_PRIVATE_ONLY` (e.g. `aws-s3,google-cloud-storage`): its public export is skipped, the other
tests export privately.

For local test servers (e.g. MinIO or WebDAV in a container) set `EFML_LIVE_ALLOW_LOCAL_HOSTS=1` and, for self-signed
certificates, `EFML_LIVE_IGNORE_SSL=1`.

## Playwright

The Playwright live tests use your development website in the browser like you do: they open the directory listing of
each service, import a file via the dialog, upload images via "Add Media File" with the export enabled (public,
private and as editor), check the files as visitor and add the URLs of `EFML_LIVE_URLS`.

### Set up

1. Install the plugin **EFML Dev Credentials** (see below) on your development website. It reads the same `.env.live`
   and provides the credentials of the services there.
2. Copy `.env.live.example` to `.env.live` (if not done for the PHPUnit tests) and set in the section "Playwright live
   tests" (remove the `#` at the start of the lines):

   | Setting | Meaning |
   |---|---|
   | `EFML_LIVE_SITE_URL` | **required**: the address of your development website, e.g. `https://dev.example.com`, with `http://` or `https://`, without `/wp-admin`. Replace the example value `https://efml.local`. |
   | `EFML_LIVE_SITE_ADMIN_USER`, `EFML_LIVE_SITE_ADMIN_PASSWORD` | **required**: login and password of an administrator |
   | `EFML_LIVE_SITE_EDITOR_USER`, `EFML_LIVE_SITE_EDITOR_PASSWORD` | optional: an editor for the export of his uploads (best without the capabilities for the services) |
   | `EFML_LIVE_BROWSER_CHANNEL` | optional: `chrome` for your installed Google Chrome, otherwise the browser of Playwright |

3. Install the dependencies and the browser once: `npm install` and `npx playwright install chromium` (not needed
   with `EFML_LIVE_BROWSER_CHANNEL=chrome`). On a Linux server without desktop, `npx playwright install --with-deps
   chromium` installs the required system libraries, too.
4. Run the tests:

```bash
composer test-live-e2e
```

The tests run on the computer where you start them: the address in `EFML_LIVE_SITE_URL` must be reachable from there
(a real domain or an entry in the hosts file of this computer). The users must be able to log in via `wp-login.php`
without two-factor authentication or captcha.

### What happens

Before the tests, Playwright checks the configuration, checks that the website is reachable and logs in once with each
user. The sessions are saved automatically in `tests/Live/Playwright/.auth/admin.json` and `editor.json` (you never
create them yourself; the directory is ignored by Git). If the website is not configured, empty sessions are saved and
all tests are skipped with the reason.

The tests skip services, which are disabled or incomplete in EFML Dev Credentials or whose extension is not active.
The list in the console shows skipped tests without reason; the report shows the reason of each test, also the error
message and a screenshot of failed tests: it is in `playwright-report/live` (`npx playwright show-report
playwright-report/live`).

The language of the backend does not matter, the tests find buttons and fields independent of it. If a dialog shows an
error, the test fails at once with the text of the dialog. A complete run takes some minutes; Composer does not stop it
after 300 seconds.

### Typical errors

| Message | Solution |
|---|---|
| `These values are missing in …/.env.live: …` | Set the named values and remove the `#` at the start of their lines. |
| `The name "…" could not be resolved` | The address is not known on this computer: use the real domain or add it to the hosts file. Did you keep the example value `https://efml.local`? |
| `Nothing answers at "…"` | The web server is not reachable: check the address and the port. |
| `… shows no login form of WordPress` | The URL contains `/wp-admin`, or a plugin hides or protects the login page. |
| `The login of "…" failed: …` | Check login and password. Two-factor authentication or a captcha must be disabled for this user. |
| `ENOENT … .auth/admin.json` | Comes from older versions of these tests: update the tests. |
| `The process … exceeded the timeout of 300 seconds` | Comes from older versions of these tests: update `composer.json`. |

## EFML Dev Credentials

A small plugin for your development websites in its own repository: it reads `.env.live` and defines the credentials
of all complete services as constants, before the plugin is loaded. So every service is ready on each development
website without entering credentials.

- **Admin bar "EFML Dev"**: shows the number of used services and switches the credentials overall or per service.
  Disable a service to use the settings of the website for it (constants force the global mode and lock saved
  external sources of other servers).
- **Tools > EFML Dev Credentials**: the state of all services and the refresh tokens of the actual connections to
  Dropbox and Google Drive to copy into `.env.live`.
- Restores the connections to Dropbox and Google Drive from the refresh tokens of `.env.live`.
- Enables all abilities of the plugin (filter `efml_abilities_mode`).
- Endpoints for the Playwright tests: `efml-dev/v1/status` and `efml-dev/v1/prepare` (administrators only).
- Works only on websites with the environment type `local` or `development` (`WP_ENVIRONMENT_TYPE`).

Installation (as mu-plugin, so it is loaded before all plugins):

```bash
git clone <repository> ~/repos/efml-dev-credentials
~/repos/efml-dev-credentials/install.sh /path/to/wordpress
```

`.env.live` is found in the directory of the plugin "External Files in Media Library" (its working copy), in the
directory above WordPress or at the path of the constant `EFML_LIVE_ENV` in `wp-config.php`.
