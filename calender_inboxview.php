<?php
/**
 * calender_inboxview
 * Show upcoming calendar events in the Mail (mailbox) view.
 *
 */

declare(strict_types=1);

class calender_inboxview extends rcube_plugin
{
    public $task = 'mail|settings|calendar';
    private rcube $rc;

    public function init(): void
    {
        // Load localization and register settings hooks regardless of task
        $this->add_texts('localization/', true);
        $this->add_hook('preferences_list', [$this, 'prefs_list']);
        $this->add_hook('preferences_save', [$this, 'prefs_save']);

        $this->rc = rcmail::get_instance();

        $this->load_config();

        // Load our translations (expects localization/<lang>.inc with $labels array)
        $this->add_texts('localization/');
        $this->add_label('ci_no_events', 'ci_panel_title', 'ci_demo_sync', 'ci_demo_standup', 'ci_demo_room', 'ci_prefs_section', 'ci_display_upcoming', 'ci_days_ahead', 'ci_compact_mode');

        
        $this->add_label('ci_prefs_section','ci_display_upcoming','ci_days_ahead','ci_compact_mode','ci_show_all_day_time','ci_max_events','ci_debug_level','ci_force_noon_anchor');
		// Settings UI block
        $this->add_hook('preferences_list', [$this, 'prefs_list']);
        $this->add_hook('preferences_save', [$this, 'prefs_save']);

        // Data endpoint
        $this->register_action('plugin.calender_inboxview.fetch', [$this, 'action_fetch']);

        // Only inject assets on Mail task
        if ($this->rc->task === 'mail') {
            $this->include_script('js/calender_inboxview.js');

            $skin = (string)$this->rc->config->get('skin', 'classic');
            if (file_exists($this->home . "/skins/$skin/calender_inboxview.css")) {
                $this->include_stylesheet("skins/$skin/calender_inboxview.css");
            } 
        else if ($this->rc->task === 'calendar') {
            $this->include_script('js/ci_calendar_bridge.js');
        }
		else {
                $this->include_stylesheet("skins/classic/calender_inboxview.css");
            }

            // Expose prefs/env to JS
            $show   = (bool)$this->rc->config->get('ci_display', false);
            $days   = (int)$this->rc->config->get('ci_days_ahead', 7);
            $title  = (string)$this->gettext('ci_panel_title');

            $this->rc->output->set_env('ci_display', $show);
            $this->rc->output->set_env('ci_days_ahead', $days);
            $this->rc->output->set_env('ci_panel_title', $title);
            $this->rc->output->set_env('ci_compact', (bool)$this->rc->config->get('ci_compact', false));
            $this->rc->output->set_env('ci_show_all_day_time', (bool)$this->rc->config->get('ci_show_all_day_time', false));
            $this->rc->output->set_env('ci_max_events', (int)$this->rc->config->get('ci_max_events', 8));
            $this->rc->output->set_env('ci_debug_level', (string)$this->rc->config->get('ci_debug_level', 'basic'));
            $this->rc->output->set_env('ci_force_noon_anchor', (bool)$this->rc->config->get('ci_force_noon_anchor', true));
			$this->dbg('init', ['ci_display' => $show, 'ci_days_ahead' => $days, 'title' => $title, 'skin' => $skin]);
        }
    }

    public function prefs_list(array $args): array
    {
        // Only for the Mailbox preferences section
        if (($args['section'] ?? '') !== 'mailbox') {
            return $args;
        }

        $block_id = 'ci_calendar';
        $args['blocks'][$block_id]['name'] = $this->gettext('ci_prefs_section') ?: 'Calendar InboxView';

        $display = (bool) $this->rc->config->get('ci_display', true);
        $days    = (int)  $this->rc->config->get('ci_days_ahead', 7);
        $compact = (bool) $this->rc->config->get('ci_compact', false);
        $show_all_day_time = (bool) $this->rc->config->get('ci_show_all_day_time', false);
        $max_events = (int) $this->rc->config->get('ci_max_events', 8);
        $debug_level = (string) $this->rc->config->get('ci_debug_level', 'basic');
        $force_noon = (bool) $this->rc->config->get('ci_force_noon_anchor', true);

        $args['blocks'][$block_id]['options']['ci_display'] = [
            'title'   => rcube::Q($this->gettext('ci_display_upcoming') ?: 'Show Upcoming Events'),
            'content' => sprintf('<input type="checkbox" name="_ci_display" value="1"%s>', $display ? ' checked="checked"' : '')
        ];

        $args['blocks'][$block_id]['options']['ci_days_ahead'] = [
            'title'   => rcube::Q($this->gettext('ci_days_ahead') ?: 'Days Ahead'),
            'content' => sprintf('<input type="number" min="1" max="30" step="1" name="_ci_days_ahead" value="%d" class="input" />', $days)
        ];

        $args['blocks'][$block_id]['options']['ci_compact'] = [
            'title'   => rcube::Q($this->gettext('ci_compact_mode') ?: 'Compact Mode'),
            'content' => sprintf('<input type="checkbox" name="_ci_compact" value="1"%s>', $compact ? ' checked="checked"' : '')
        ];

        $args['blocks'][$block_id]['options']['ci_show_all_day_time'] = [
            'title'   => rcube::Q($this->gettext('ci_show_all_day_time') ?: 'Show All Day Time'),
            'content' => sprintf('<input type="checkbox" name="_ci_show_all_day_time" value="1"%s>', $show_all_day_time ? ' checked="checked"' : '')
        ];

        $args['blocks'][$block_id]['options']['ci_max_events'] = [
            'title'   => rcube::Q($this->gettext('ci_max_events') ?: 'Max Events'),
            'content' => sprintf('<input type="number" min="1" max="20" step="1" name="_ci_max_events" value="%d" class="input" />', $max_events)
        ];

        $select = '<select name="_ci_debug_level" class="input">'
                . '<option value="none"' . ($debug_level==='none' ? ' selected="selected"' : '') . '>none</option>'
                . '<option value="basic"' . ($debug_level==='basic' ? ' selected="selected"' : '') . '>basic</option>'
                . '<option value="verbose"' . ($debug_level==='verbose' ? ' selected="selected"' : '') . '>verbose</option>'
                . '</select>';
        $args['blocks'][$block_id]['options']['ci_debug_level'] = [
            'title'   => rcube::Q($this->gettext('ci_debug_level') ?: 'Debug Level'),
            'content' => $select
        ];

        $args['blocks'][$block_id]['options']['ci_force_noon_anchor'] = [
            'title'   => rcube::Q($this->gettext('ci_force_noon_anchor') ?: 'Force Noon Anchor'),
            'content' => sprintf('<input type="checkbox" name="_ci_force_noon_anchor" value="1"%s>', $force_noon ? ' checked="checked"' : '')
        ];

        return $args;
    }



    
    
    public function prefs_save(array $args): array
    {
        if (($args['section'] ?? '') !== 'mailbox') {
            return $args;
        }

        $args['prefs']['ci_display'] = (bool) rcube_utils::get_input_value('_ci_display', rcube_utils::INPUT_POST);

        $days = (int) rcube_utils::get_input_value('_ci_days_ahead', rcube_utils::INPUT_POST);
        if ($days < 1) $days = 7; if ($days > 30) $days = 30;
        $args['prefs']['ci_days_ahead'] = $days;

        $args['prefs']['ci_compact'] = (bool) rcube_utils::get_input_value('_ci_compact', rcube_utils::INPUT_POST);
        $args['prefs']['ci_show_all_day_time'] = (bool) rcube_utils::get_input_value('_ci_show_all_day_time', rcube_utils::INPUT_POST);

        $maxe = (int) rcube_utils::get_input_value('_ci_max_events', rcube_utils::INPUT_POST);
        if ($maxe < 1) $maxe = 8; if ($maxe > 20) $maxe = 20;
        $args['prefs']['ci_max_events'] = $maxe;

        $lvl = (string) rcube_utils::get_input_value('_ci_debug_level', rcube_utils::INPUT_POST);
        if (!in_array($lvl, ['none','basic','verbose'], true)) $lvl = 'basic';
        $args['prefs']['ci_debug_level'] = $lvl;

        $args['prefs']['ci_force_noon_anchor'] = (bool) rcube_utils::get_input_value('_ci_force_noon_anchor', rcube_utils::INPUT_POST);

        return $args;
    }

    /* -------------------------- Data fetch endpoint ------------------------- */

    public function action_fetch(): void
    {
        $this->dbg('fetch_action_called', ['task' => $this->rc->task]);

        if ($this->rc->task !== 'mail') {
            $this->dbg('wrong_task_abort', ['task' => $this->rc->task]);
            $this->return_events([]);
            return;
        }

        if (!(bool)$this->rc->config->get('ci_display', false)) {
            $this->dbg('display_pref_off');
            $this->return_events([]);
            return;
        }

        $days = (int) rcube_utils::get_input_value('days', rcube_utils::INPUT_GPC);
        if ($days < 1) $days = (int)$this->rc->config->get('ci_days_ahead', 7);
        if ($days < 1) $days = 7;

        $tz = $this->rc->config->get('timezone', 'UTC');
        try {
            $now   = new DateTime('now', new DateTimeZone($tz));
            $end   = (clone $now)->modify('+' . $days . ' days');
        } catch (Exception $e) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $end = (clone $now)->modify('+' . $days . ' days');
        }

        $provider = (string)$this->rc->config->get('ci_provider', 'calendar_api');
        $this->dbg('provider_selected', ['provider' => $provider, 'range' => [$now->format(DateTime::ATOM), $end->format(DateTime::ATOM)]]);

        $events = [];
        switch ($provider) {
            case 'dummy':
                $events = $this->dummy_events($now, $end);
                break;

            case 'calendar_api':
                $events = $this->try_calendar_api($now, $end) ?? [];
                if (!$events) {
                    $this->dbg('api_failed_fallback_http');
                    $events = $this->try_calendar_http($now, $end) ?? [];
                }
                break;

            case 'calendar_http':
            default:
                $events = $this->try_calendar_http($now, $end) ?? [];
                break;
        }

        usort($events, function ($a, $b) { return strcmp($a['start'], $b['start']); });
        $this->dbg('events_returning', ['count' => count($events)]);
        $this->return_events($events, $now);
    }

    private function return_events(array $events, ?DateTime $now = null): void
    {
        $payload = ['events' => $events, 'now' => $now ? $now->format(DateTime::ATOM) : null];
        $this->rc->output->command('plugin.ci_events', $payload);
        $this->rc->output->send();
    }

    /* -------------------------- Calendar providers -------------------------- */

    private function try_calendar_api(DateTime $start, DateTime $end): ?array
    {
        $this->dbg('try_calendar_api_enter');
        try {
            $loader = $this->rc->plugins;
            $cal = $loader->get_plugin('calendar');
            if (!$cal) {
                try { $loader->load_plugin('calendar', true, true); } catch (Throwable $t) { $this->dbg('calendar_load_plugin_error', ['err' => $t->getMessage()]); }
                $cal = $loader->get_plugin('calendar');
            }
            if (!is_object($cal)) { $this->dbg('calendar_plugin_not_found'); return null; }

            $callables = [];
            if (method_exists($cal, 'list_events')) $callables[] = [$cal, 'list_events'];
            if (method_exists($cal, 'get_driver')) {
                try {
                    $drv = $cal->get_driver();
                    if (is_object($drv) && method_exists($drv, 'list_events')) $callables[] = [$drv, 'list_events'];
                } catch (Throwable $t) { $this->dbg('calendar_get_driver_err', ['err' => $t->getMessage()]); }
            }
            if (property_exists($cal, 'driver') && is_object($cal->driver) && method_exists($cal->driver, 'list_events'))
                $callables[] = [$cal->driver, 'list_events'];

            $this->dbg('calendar_api_candidates', ['count' => count($callables)]);
            if (!$callables) return null;

            $attempts = [
                [$start, $end],
                [$start->format('U'), $end->format('U')],
                [$start->format(DateTime::ATOM), $end->format(DateTime::ATOM)],
            ];

            foreach ($callables as $idx => $call) {
                foreach ($attempts as $aidx => $args) {
                    try {
                        $this->dbg('calendar_api_call', ['call' => $idx, 'attempt' => $aidx]);
                        $raw = @call_user_func_array($call, $args);
                        if (is_array($raw)) {
                            $evs = $this->normalize_calendar_events($raw);
                            if ($evs !== null) { $this->dbg('calendar_api_success', ['events' => count($evs)]); return $evs; }
                        }
                    } catch (Throwable $t) { $this->dbg('calendar_api_call_error', ['err' => $t->getMessage()]); }
                }
            }
        } catch (Throwable $t) { $this->dbg('calendar_api_top_error', ['err' => $t->getMessage()]); }
        return null;
    }
    
    private function try_calendar_http(DateTime $start, DateTime $end): ?array
    {
        $task   = (string)$this->rc->config->get('ci_calendar_task', 'calendar');
        $base   = $this->rc->url(['_task' => $task], true, true);

        // If admin supplies an explicit URL template, use it directly.
        // Example: '?_task=calendar&_action=event_list&_token={token}&start={start}&end={end}'
        $tpl = $this->rc->config->get('ci_http_url_template');
        $disable = (bool)$this->rc->config->get('ci_disable_probes', false);

        $token = method_exists($this->rc, 'get_request_token') ? $this->rc->get_request_token() : null;

        if (is_string($tpl) && $tpl !== '') {
            $url = $tpl;
            $repl = [
                '{start}' => $start->format('U'),
                '{end}'   => $end->format('U'),
                '{start_iso}' => $start->format('Y-m-d\TH:i:s'),
                '{end_iso}'   => $end->format('Y-m-d\TH:i:s'),
                '{token}' => (string)$token,
            ];
            $url = strtr($url, $repl);
            // If template is relative, anchor safely (avoid duplicating ?_task)
            if (strpos($url, 'http') !== 0) {
                $root = preg_replace('/\?.*$/', '', $base);
                if (strlen($url) && $url[0] === '&') {
                    $url = $base . $url;
                } elseif (strlen($url) && $url[0] === '?') {
                    $url = $root . $url;
                } else {
                    $url = rtrim($root, '/') . '/' . ltrim($url, '/');
                }
            }
            $this->dbg('http_try_template', ['url' => $url]);
            $res = $this->http_get_and_parse($url);
            return $res;
        }

        $actions = $this->rc->config->get('ci_http_actions', ['list','events','event_list','load','load_events','get_events','fetch','index']);
        if (!is_array($actions) || empty($actions)) $actions = ['list','events','event_list','load','load_events','get_events','fetch','index'];
        $this->dbg('http_probe_actions', ['actions' => $actions]);

        $tries = [];
        foreach ($actions as $a) $tries[] = ['_action' => (string)$a];

        $params_a = ['start' => $start->format('U'), 'end' => $end->format('U')];
        $params_b = ['start' => $start->format('Y-m-d\TH:i:s'), 'end' => $end->format('Y-m-d\TH:i:s')];

        $sources = $this->rc->config->get('ci_sources');
        if (is_array($sources) && $sources) {
            $params_a['source'] = implode(',', $sources);
            $params_b['source'] = implode(',', $sources);
        }
        if ($token) { $params_a['_token'] = $token; $params_b['_token'] = $token; }

        foreach ($tries as $act) {
            foreach ([$params_a, $params_b] as $params) {
                $url = $base . '&' . http_build_query($act + $params);
                $this->dbg('http_try', ['url' => $url]);
                $events = $this->http_get_and_parse($url);
                if (is_array($events)) return $events;
            }
        }

        return null;
    }

    private function http_get_and_parse(string $url): ?array
    {
        // Prefer rcube_http_request, fallback to rcube_net_http, else raw stream with cookies
        $client = null; $mode = null;
        if (class_exists('rcube_http_request')) { $client = new rcube_http_request(); $client->set_method('GET'); $client->set_timeout(7); $mode='http_request'; }
        elseif (class_exists('rcube_net_http'))  { $client = new rcube_net_http();   $mode='net_http'; }

        try {
            $body = null; $code = 0;
            if ($mode === 'http_request') {
                $client->set_url($url);
                $res = $client->send();
                $code = $res ? $res->get_status() : 0;
                $body = $res ? (string)$res->get_body() : '';
            } elseif ($mode === 'net_http') {
                $cookie = self::cookie_header();
                if ($cookie) $client->add_header('Cookie', $cookie);
                $resp = $client->get($url);
                $code = $client->get_status();
                $body = (string)$resp;
            } else {
                $cookie = self::cookie_header();
                $opts = ['http' => ['method' => 'GET', 'header' => $cookie ? "Cookie: $cookie\r\n" : ""]];
                $ctx = stream_context_create($opts);
                $body = @file_get_contents($url, false, $ctx);
                $code = $body !== false ? 200 : 0;
            }

            $this->dbg('http_resp', ['status' => $code, 'len' => strlen((string)$body)]);
            if ($code === 200 && $body) {
                $json = json_decode($body, true);
                if (is_array($json)) {
                    $events = $this->normalize_calendar_events($json);
                    if ($events !== null) { $this->dbg('http_success', ['events' => count($events)]); return $events; }
                    $this->dbg('http_json_unexpected_shape');
                } else {
                    $this->dbg('http_json_decode_fail', ['err' => json_last_error_msg(), 'peek' => substr($body, 0, 200)]);
                }
            }
        } catch (Throwable $t) {
            $this->dbg('http_try_error', ['err' => $t->getMessage()]);
        }

        return null;
    }

    private static function cookie_header(): string
    {
        if (empty($_COOKIE)) return '';
        $pairs = [];
        foreach ($_COOKIE as $k => $v) {
            $pairs[] = $k . '=' . rawurlencode((string)$v);
        }
        return implode('; ', $pairs);
    }

    /* ----------------------------- Normalization ---------------------------- */

    private function normalize_calendar_events($json): ?array
    {
        $out = [];

        if (is_array($json) && isset($json['events']) && is_array($json['events'])) {
            foreach ($json['events'] as $e) {
                $ev = $this->normalize_one_event($e);
                if ($ev) $out[] = $ev;
            }
            return $out;
        }

        if (is_array($json)) {
            $is_list = array_keys($json) === range(0, count($json) - 1);
            if ($is_list) {
                foreach ($json as $e) {
                    if (is_array($e)) {
                        $ev = $this->normalize_one_event($e);
                        if ($ev) $out[] = $ev;
                    }
                }
                return $out;
            }
        }

        return null;
    }

    private function normalize_one_event(array $e): ?array
    {
        $title = $e['title'] ?? $e['summary'] ?? $e['name'] ?? null;
        $start = $e['start'] ?? $e['dtstart'] ?? ($e['dates']['start'] ?? null);
        $end   = $e['end']   ?? $e['dtend']   ?? ($e['dates']['end'] ?? null);

        if (!$title || !$start) return null;

        $start_iso = $this->value_to_iso8601($start);
        $end_iso   = $this->value_to_iso8601($end ?: $start);

        return [
            'id'       => (string)($e['id'] ?? $e['uid'] ?? $start_iso . ':' . $title),
            'title'    => (string)$title,
            'start'    => $start_iso,
            'end'      => $end_iso,
            'location' => (string)($e['location'] ?? ''),
            'allDay'   => (bool)($e['allDay'] ?? $e['allday'] ?? false),
            'color'    => (string)($e['color'] ?? $e['bgColor'] ?? $e['backgroundColor'] ?? ''),
            'src'      => (string)($e['calendar'] ?? $e['source'] ?? $e['calendar_id'] ?? $e['cid'] ?? ''),
        ];
    }

    private function get_user_timezone(): DateTimeZone
    {
        $tzid = $this->rc->config->get('timezone', 'UTC');
        if (!$tzid || $tzid === 'auto') {
            $tzid = @date_default_timezone_get() ?: 'UTC';
        }
        try { return new DateTimeZone($tzid); }
        catch (Exception $e) { return new DateTimeZone('UTC'); }
    }

	private function value_to_iso8601($val): string
    {
        $tz = $this->get_user_timezone();
        try {
            // DateTime already
            if ($val instanceof DateTimeInterface) {
                return (new DateTimeImmutable($val->format('c')))->setTimezone($tz)->format(DateTimeInterface::ATOM);
            }

            // Array forms
            if (is_array($val)) {
                if (isset($val['unixtime']) && is_numeric($val['unixtime'])) {
                    return (new DateTimeImmutable('@' . (int)$val['unixtime']))->setTimezone($tz)->format(DateTimeInterface::ATOM);
                }
                if (isset($val['date'])) {
                    $s = trim((string)$val['date']);
                    // date-only => set to LOCAL NOON
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                        return (new DateTimeImmutable($s . ' 12:00:00', $tz))->format(DateTimeInterface::ATOM);
                    }
                    // UTC midnight patterns => bump to LOCAL NOON of that date
                    if (preg_match('/^(\d{4}-\d{2}-\d{2})T00:00:00(?:\.000)?(?:Z|\+00:00)$/', $s, $m)) {
                        return (new DateTimeImmutable($m[1] . ' 12:00:00', $tz))->format(DateTimeInterface::ATOM);
                    }
                    $has_tz = (bool)preg_match('/(Z|[+\-]\d{2}(:?\d{2})?)$/i', $s) || (bool)preg_match('/[+\-]\d{2}:\d{2}$/', $s);
                    $dt = $has_tz ? new DateTimeImmutable($s) : new DateTimeImmutable($s, $tz);
                    return $dt->setTimezone($tz)->format(DateTimeInterface::ATOM);
                }
            }

            // Numeric
            if (is_numeric($val)) {
                return (new DateTimeImmutable('@' . (int)$val))->setTimezone($tz)->format(DateTimeInterface::ATOM);
            }

            // String forms
            $s = trim((string)$val);
            if ($s === '') {
                return (new DateTimeImmutable('now', $tz))->format(DateTimeInterface::ATOM);
            }

            // Compact ICS-like
            if (preg_match('/^\d{8}T\d{6}Z?$/', $s)) {
                $dt = DateTime::createFromFormat('Ymd\THis\Z', $s, new DateTimeZone('UTC'));
                if (!$dt) $dt = DateTime::createFromFormat('Ymd\THis', $s, $tz);
                if ($dt instanceof DateTimeInterface) {
                    return (new DateTimeImmutable($dt->format('c')))->setTimezone($tz)->format(DateTimeInterface::ATOM);
                }
            }

            // date-only => LOCAL NOON
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                return (new DateTimeImmutable($s . ' 12:00:00', $tz))->format(DateTimeInterface::ATOM);
            }

            // UTC midnight => bump to LOCAL NOON
            if (preg_match('/^(\d{4}-\d{2}-\d{2})T00:00:00(?:\.000)?(?:Z|\+00:00)$/', $s, $m)) {
                return (new DateTimeImmutable($m[1] . ' 12:00:00', $tz))->format(DateTimeInterface::ATOM);
            }

            // General case
            $has_tz = (bool)preg_match('/(Z|[+\-]\d{2}(:?\d{2})?)$/i', $s) || (bool)preg_match('/[+\-]\d{2}:\d{2}$/', $s);
            $dt = $has_tz ? new DateTimeImmutable($s) : new DateTimeImmutable($s, $tz);
            return $dt->setTimezone($tz)->format(DateTimeInterface::ATOM);
        } catch (Exception $e) {
            return (new DateTimeImmutable('now', $tz))->format(DateTimeInterface::ATOM);
        }
    }


    private function dummy_events(DateTime $start, DateTime $end): array
    {
        $s1 = (clone $start)->modify('+2 hours');
        $e1 = (clone $s1)->modify('+1 hour');
        $s2 = (clone $start)->modify('+1 day 9:00');
        $e2 = (clone $s2)->modify('+30 minutes');

        return [
            ['id' => 'demo-1', 'title' => $this->gettext('ci_demo_sync'),   'start' => $s1->format(DateTime::ATOM), 'end' => $e1->format(DateTime::ATOM), 'location' => '—', 'allDay' => false],
            ['id' => 'demo-2', 'title' => $this->gettext('ci_demo_standup'), 'start' => $s2->format(DateTime::ATOM), 'end' => $e2->format(DateTime::ATOM), 'location' => $this->gettext('ci_demo_room'), 'allDay' => false],
        ];
    }

    /* ------------------------------- Debugging ------------------------------ */

    private function dbg(string $event, array $ctx = [], string $level = 'basic'): void
    {
        $legacy = (bool) $this->rc->config->get('ci_debug', false);
        $lvl = (string) $this->rc->config->get('ci_debug_level', $legacy ? 'basic' : 'basic');
        if ($lvl === 'none' && !$legacy) return;
        if ($lvl === 'basic' && $level === 'verbose' && !$legacy) return;
        rcube::write_log('ci', '[' . $event . '] ' . json_encode($ctx));
    }
}
