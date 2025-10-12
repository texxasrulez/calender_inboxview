# calender_inboxview (Roundcube plugin)

[![Packagist Downloads](https://img.shields.io/packagist/dt/texxasrulez/calender_inboxview?style=plastic&logo=packagist&logoColor=white&label=Downloads&labelColor=blue&color=gold)](https://packagist.org/packages/texxasrulez/calender_inboxview)
[![Packagist Version](https://img.shields.io/packagist/v/texxasrulez/calender_inboxview?style=plastic&logo=packagist&logoColor=white&label=Version&labelColor=blue&color=limegreen)](https://packagist.org/packages/texxasrulez/calender_inboxview)
[![Github License](https://img.shields.io/github/license/texxasrulez/calender_inboxview?style=plastic&logo=github&label=License&labelColor=blue&color=coral)](https://github.com/texxasrulez/calender_inboxview/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/texxasrulez/calender_inboxview?style=plastic&logo=github&label=Stars&labelColor=blue&color=deepskyblue)](https://github.com/texxasrulez/calender_inboxview/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/texxasrulez/calender_inboxview?style=plastic&logo=github&label=Issues&labelColor=blue&color=aqua)](https://github.com/texxasrulez/calender_inboxview/issues)
[![GitHub Contributors](https://img.shields.io/github/contributors/texxasrulez/calender_inboxview?style=plastic&logo=github&logoColor=white&label=Contributors&labelColor=blue&color=orchid)](https://github.com/texxasrulez/calender_inboxview/graphs/contributors)
[![GitHub Forks](https://img.shields.io/github/forks/texxasrulez/calender_inboxview?style=plastic&logo=github&logoColor=white&label=Forks&labelColor=blue&color=darkorange)](https://github.com/texxasrulez/calender_inboxview/forks)
[![Donate Paypal](https://img.shields.io/badge/Paypal-Money_Please!-blue.svg?style=plastic&labelColor=blue&color=forestgreen&logo=paypal)](https://www.paypal.me/texxasrulez)

Show upcoming calendar events in the Mail (mailbox) view.

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

## Notes
- The panel mounts just under the folder list in most skins. If your layout is exotic,
  adjust the `findMountPoint()` selector list in `js/calender_inboxview.js`.

**Screenshot**

![Calendar Inbox View](images/calendar_inboxview_screenshot.png?raw=true "Calendar Inbox View Screenshot")
![Calendar Inbox SettingsView](images/calendar_inboxview_settings_screenshot.png?raw=true "Calendar Inbox View Settings Screenshot")
