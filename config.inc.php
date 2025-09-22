<?php
// calender_inboxview config

// Enable debug logging to logs/ci
$config['ci_debug'] = false;

// Provider mode:
// - 'calendar_api' (default): attempt to call the Calendar plugin/driver in-process (best for Kolab/CalDAV forks).
// - 'calendar_http': try JSON endpoints on ?_task=calendar (works across many versions).
// - 'dummy': render sample events for UI testing.
$config['ci_provider'] = 'calendar_api';

// Calendar Template (use with 'calendar_http')
$config['ci_http_url_template'] = '&_action=load_events&_token={token}&start={start}&end={end}';

// Whether to show the panel by default for new users (they can toggle in Settings → Preferences → Mailbox View)
$config['ci_display'] = false;

// How many days ahead to display
$config['ci_days_ahead'] = 7;

// HTTP probe settings (only used if provider is 'calendar_http' or 'calendar_api' fallback)
$config['ci_calendar_task'] = 'calendar';
$config['ci_http_actions']  = ['list','events','event_list','load','load_events','get_events','fetch','index'];

// Optionally narrow to specific calendar "sources" (IDs as strings)
$config['ci_sources'] = ['personal'];

// Disable HTTP action probing entirely (use only template if provided)
$config['ci_disable_probes'] = true;

// Compact mode (one-line titles)
$config['ci_compact'] = false;
