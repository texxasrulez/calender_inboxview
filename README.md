# calender_inboxview (Roundcube plugin)

> Show upcoming calendar events in the Mail (mailbox) view.

## What it does
- Adds a panel in the Mailbox view that lists upcoming events for the next _N_ days.
- Adds a toggle in **Settings → Preferences → Mailbox View → Upcoming Events (Mailbox View)**:
  - **Display Upcoming Events** (on/off)
  - **Days to show ahead**

## Requirements
- Roundcube 1.5+ (tested against PHP 8+ constructs)
- The official **calendar** plugin enabled (Kolab calendar or texxasrulez calendar).

## Installation
1. Copy the folder `calender_inboxview` into `plugins/` on your Roundcube server.
2. Add `calender_inboxview` to the `$config['plugins']` array in `config/config.inc.php`.
3. Optionally copy `config.inc.php.dist` to `config.inc.php` and adjust settings.
4. In Roundcube, go to **Settings → Preferences → Mailbox View** and enable **Display Upcoming Events**.

## How it fetches events
By default, the plugin tries to call the Calendar plugin's JSON event list endpoint using the current user session.
Different calendar plugin versions expose slightly different action names. This plugin tries a few common ones:
- `_action=event_list`
- `_action=load_events`
- `_action=list`

It also attempts both Unix timestamps and ISO strings for the `start`/`end` parameters. If your calendar responds
on a different action name, let me know — it's trivial to add another try.

If nothing matches, the panel gracefully shows **No upcoming events**. You can also switch the provider to `"dummy"`
in `config.inc.php` to validate the UI without touching calendars.

## Skinning
- Basic styles for `classic` and `elastic` skins are included. If your custom skin is named differently,
  the plugin will fall back to the `classic` stylesheet.

## Localization
The `localization/` folder contains labels with keys like:
- `ci_prefs_section`, `ci_display_upcoming`, `ci_days_ahead`, `ci_panel_title`, `ci_no_events`

Add more locales by copying `en_US.inc.php` and translating the strings.

## Notes
- The panel mounts just under the folder list in most skins. If your layout is exotic,
  adjust the `findMountPoint()` selector list in `js/calender_inboxview.js`.

## Troubleshooting
- Enable Roundcube logs and keep an eye out for HTTP 404/500 from `?_task=calendar` calls.
- If your calendar plugin requires different parameter names, ping me the exact endpoint and I'll adapt
  the provider quickly (or you can adjust `try_calendar_http()` in `calender_inboxview.php`).

