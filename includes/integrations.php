<?php
require_once __DIR__ . '/functions.php';

function integ_cfg(?string $key = null) {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/integrations_config.php';
    if ($key === null) return $cfg;
    $v = $cfg;
    foreach (explode('.', $key) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return null;
        $v = $v[$k];
    }
    return $v;
}

function integ_http(string $method, string $url, array $headers = [], ?array $json = null, ?array $form = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];
    if ($json !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($json);
        $headers[] = 'Content-Type: application/json';
    } elseif ($form !== null) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($form);
    }
    $headers[] = 'Accept: application/json';
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err    = $raw === false ? curl_error($ch) : null;
    curl_close($ch);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    return ['status' => $status, 'body' => $decoded ?? $raw, 'error' => $err];
}

function integ_log(string $msg): void {
    error_log('[MediTrack integrations] ' . $msg);
}

class GCal {
    private const BASE = 'https://www.googleapis.com/calendar/v3/calendars/';

    public static function enabled(): bool {
        return integ_cfg('google.enabled') && integ_cfg('google.refresh_token');
    }

    private static function token(): ?string {
        $file = sys_get_temp_dir() . '/meditrack_gcal_token.json';
        if (is_file($file)) {
            $c = json_decode((string) file_get_contents($file), true);
            if (!empty($c['token']) && $c['exp'] > time() + 60) return $c['token'];
        }
        $r = integ_http('POST', 'https://oauth2.googleapis.com/token', [], null, [
            'client_id'     => integ_cfg('google.client_id'),
            'client_secret' => integ_cfg('google.client_secret'),
            'refresh_token' => integ_cfg('google.refresh_token'),
            'grant_type'    => 'refresh_token',
        ]);
        if ($r['status'] !== 200 || empty($r['body']['access_token'])) {
            integ_log('Google token error: ' . json_encode($r['body']));
            return null;
        }
        file_put_contents($file, json_encode(['token' => $r['body']['access_token'], 'exp' => time() + (int) $r['body']['expires_in']]));
        return $r['body']['access_token'];
    }

    private static function call(string $method, string $path, array $query = [], ?array $json = null): array {
        $t = self::token();
        if (!$t) return ['status' => 0, 'body' => null, 'error' => 'no token'];
        $url = self::BASE . rawurlencode(integ_cfg('google.calendar_id')) . $path;
        if ($query) $url .= '?' . http_build_query($query);
        return integ_http($method, $url, ['Authorization: Bearer ' . $t], $json);
    }

    private static function window(string $date, ?string $time): array {
        $tz = new DateTimeZone(integ_cfg('timezone'));
        if (!$time) {
            $end = (new DateTime($date, $tz))->modify('+1 day')->format('Y-m-d');
            return ['allDay' => true, 'start' => ['date' => $date], 'end' => ['date' => $end]];
        }
        $s = new DateTime("$date $time", $tz);
        $e = (clone $s)->modify('+' . (int) integ_cfg('google.duration_min') . ' minutes');
        return [
            'allDay' => false,
            'start'  => ['dateTime' => $s->format('c'), 'timeZone' => integ_cfg('timezone')],
            'end'    => ['dateTime' => $e->format('c'), 'timeZone' => integ_cfg('timezone')],
        ];
    }

    public static function conflicts(int $nurseId, string $date, ?string $time, ?int $excludeTaskId = null): array {
        if (!self::enabled() || !$time) return [];
        $w = self::window($date, $time);
        $r = self::call('GET', '/events', [
            'timeMin' => $w['start']['dateTime'], 'timeMax' => $w['end']['dateTime'],
            'singleEvents' => 'true',
            'privateExtendedProperty' => 'nurse_id=' . $nurseId,
        ]);
        if ($r['status'] !== 200) return [];
        $out = [];
        foreach ($r['body']['items'] ?? [] as $ev) {
            if (($ev['status'] ?? '') === 'cancelled') continue;
            if ($excludeTaskId && ($ev['extendedProperties']['private']['task_id'] ?? '') == $excludeTaskId) continue;
            $out[] = $ev['summary'] ?? '(busy)';
        }
        return $out;
    }

    public static function createEvent(array $t): ?string {
        if (!self::enabled()) return null;
        $w = self::window($t['task_date'], $t['due_time']);
        $colors = ['urgent' => '11', 'high' => '6', 'medium' => '9', 'low' => '8'];
        $body = [
            'summary'     => '[' . strtoupper($t['priority']) . '] ' . $t['task_title'],
            'description' => $t['task_description'] . "\n\nAssigned by " . $t['doctor_name'] . ' via MediTrack',
            'start' => $w['start'], 'end' => $w['end'],
            'colorId'   => $colors[$t['priority']] ?? '9',
            'attendees' => [['email' => $t['nurse_email'], 'displayName' => $t['nurse_name']]],
            'reminders' => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 30]]],
            'extendedProperties' => ['private' => ['task_id' => (string) $t['id'], 'nurse_id' => (string) $t['nurse_id']]],
        ];
        $r = self::call('POST', '/events', ['sendUpdates' => 'all'], $body);
        if ($r['status'] !== 200) { integ_log('GCal create failed: ' . json_encode($r['body'])); return null; }
        return $r['body']['id'] ?? null;
    }

    public static function markDone(string $eventId, string $title): bool {
        $r = self::call('PATCH', '/events/' . rawurlencode($eventId), ['sendUpdates' => 'none'], ['summary' => "✓ $title", 'colorId' => '8']);
        return $r['status'] === 200;
    }

    public static function deleteEvent(string $eventId): bool {
        $r = self::call('DELETE', '/events/' . rawurlencode($eventId), ['sendUpdates' => 'all']);
        return in_array($r['status'], [204, 404, 410], true);
    }
}

class Brevo {
    public static function enabled(): bool {
        return integ_cfg('brevo.enabled') && integ_cfg('brevo.api_key');
    }

    public static function send(string $type, ?int $taskId, ?int $userId, string $toEmail, string $toName, string $subject, string $bodyHtml): bool {
        if (!self::enabled()) return false;
        $r = integ_http('POST', 'https://api.brevo.com/v3/smtp/email', ['api-key: ' . integ_cfg('brevo.api_key')], [
            'sender'      => ['email' => integ_cfg('brevo.sender_email'), 'name' => integ_cfg('brevo.sender_name')],
            'to'          => [['email' => $toEmail, 'name' => $toName]],
            'subject'     => $subject,
            'htmlContent' => self::layout($subject, $bodyHtml),
            'tags'        => ['meditrack', $type],
        ]);
        $ok  = in_array($r['status'], [200, 201], true);
        $mid = $ok ? ($r['body']['messageId'] ?? null) : null;
        $err = $ok ? null : json_encode($r['body'] ?: $r['error']);
        try {
            getDB()->prepare("INSERT INTO notification_log (task_id,user_id,type,recipient,subject,message_id,status,error) VALUES (?,?,?,?,?,?,?,?)")
                   ->execute([$taskId, $userId, $type, $toEmail, $subject, $mid, $ok ? 'sent' : 'failed', $err]);
        } catch (Throwable $e) { integ_log('notification_log insert: ' . $e->getMessage()); }
        if (!$ok) integ_log('Brevo send failed: ' . $err);
        return $ok;
    }

    public static function refreshStatuses(): int {
        if (!self::enabled()) return 0;
        $db   = getDB();
        $rows = $db->query("SELECT id, message_id FROM notification_log
                            WHERE status='sent' AND message_id IS NOT NULL
                              AND created_at < (NOW() - INTERVAL 2 MINUTE)
                              AND created_at > (NOW() - INTERVAL 3 DAY) LIMIT 100")->fetchAll();
        $n = 0;
        foreach ($rows as $row) {
            $r = integ_http('GET', 'https://api.brevo.com/v3/smtp/statistics/events?limit=20&messageId=' . rawurlencode($row['message_id']),
                            ['api-key: ' . integ_cfg('brevo.api_key')]);
            if ($r['status'] !== 200) continue;
            $events = array_column($r['body']['events'] ?? [], 'event');
            if (!$events) continue;
            $status = null;
            if (array_intersect($events, ['hardBounces', 'softBounces', 'blocked', 'invalid', 'error', 'spam'])) $status = 'bounced';
            elseif (array_intersect($events, ['delivered', 'opened', 'clicks'])) $status = 'delivered';
            if ($status) {
                $db->prepare("UPDATE notification_log SET status=?, last_event=? WHERE id=?")->execute([$status, end($events), $row['id']]);
                $n++;
            }
        }
        return $n;
    }

    private static function layout(string $title, string $inner): string {
        return '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">'
             . '<div style="background:#0f766e;color:#fff;padding:14px 20px;font-weight:bold">MediTrack Hospital OS</div>'
             . '<div style="padding:20px;color:#111827;font-size:14px;line-height:1.6"><h3 style="margin:0 0 12px">' . htmlspecialchars($title) . '</h3>' . $inner . '</div>'
             . '<div style="background:#f9fafb;color:#6b7280;padding:10px 20px;font-size:12px">Automated message — please do not reply.</div></div>';
    }
}

class OpenFDA {
    public static function enabled(): bool { return (bool) integ_cfg('openfda.enabled'); }

    private static function clean($v, int $max = 700): string {
        $s = is_array($v) ? implode(' ', $v) : (string) $v;
        $s = trim(preg_replace('/\s+/', ' ', $s));
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . '…' : $s;
    }

    public static function lookup(string $name): array {
        $key = strtolower(trim($name));
        if ($key === '' || !self::enabled()) return ['found' => false, 'error' => 'Lookup unavailable.'];
        $db = getDB();

        $c = $db->prepare("SELECT payload, fetched_at FROM drug_cache WHERE drug_key=?");
        $c->execute([$key]);
        $cached = $c->fetch();
        $fresh  = $cached && strtotime($cached['fetched_at']) > time() - 86400 * (int) integ_cfg('openfda.cache_days');
        if ($fresh) return ['source' => 'cache'] + json_decode($cached['payload'], true);

        foreach (['openfda.generic_name', 'openfda.brand_name'] as $field) {
            $url = 'https://api.fda.gov/drug/label.json?limit=1&search=' . rawurlencode($field . ':"' . $key . '"');
            if ($k = integ_cfg('openfda.api_key')) $url .= '&api_key=' . rawurlencode($k);
            $r = integ_http('GET', $url);
            if ($r['status'] === 200 && !empty($r['body']['results'][0])) {
                $l = $r['body']['results'][0];
                $data = [
                    'found'        => true,
                    'query'        => $key,
                    'generic_name' => self::clean($l['openfda']['generic_name'] ?? $key, 120),
                    'brand_name'   => self::clean($l['openfda']['brand_name'] ?? '', 120),
                    'indications'  => self::clean($l['indications_and_usage'] ?? ''),
                    'dosage'       => self::clean($l['dosage_and_administration'] ?? ''),
                    'warnings'     => self::clean($l['warnings'] ?? ($l['warnings_and_cautions'] ?? ($l['boxed_warning'] ?? ''))),
                    'interactions' => self::clean($l['drug_interactions'] ?? '', 4000),
                ];
                $db->prepare("REPLACE INTO drug_cache (drug_key, payload) VALUES (?,?)")->execute([$key, json_encode($data)]);
                return ['source' => 'live'] + $data;
            }
            if ($r['status'] !== 404 && $r['status'] !== 200) {
                if ($cached) return ['source' => 'stale-cache'] + json_decode($cached['payload'], true);
                return ['found' => false, 'error' => 'openFDA is temporarily unavailable. Enter drug details manually.'];
            }
        }
        return ['found' => false, 'error' => 'No FDA label found for "' . $name . '". Try the generic name.'];
    }

    public static function interactions(string $drug, ?string $patientRef, string $date, ?int $excludeTaskId = null): array {
        $patientRef = trim((string) $patientRef);
        if ($drug === '' || $patientRef === '') return [];
        $st = getDB()->prepare("SELECT DISTINCT medication_name FROM tasks
                                WHERE patient_ref=? AND task_date=? AND medication_name IS NOT NULL
                                  AND status <> 'cancelled' AND id <> ? LIMIT 5");
        $st->execute([$patientRef, $date, (int) $excludeTaskId]);
        $others = array_filter(array_column($st->fetchAll(), 'medication_name'), fn($o) => strcasecmp($o, $drug) !== 0);

        $mine  = self::lookup($drug);
        $flags = [];
        foreach ($others as $o) {
            $theirs = self::lookup($o);
            $hit = fn(array $label, string $needle) => !empty($label['interactions'])
                && preg_match('/\b' . preg_quote($needle, '/') . '\b/i', $label['interactions']);
            if (($mine['found'] ?? false) && $hit($mine, $o) || ($theirs['found'] ?? false) && $hit($theirs, $drug)) {
                $flags[] = "Possible interaction: " . ucfirst($drug) . " and " . ucfirst($o) . " (both scheduled for patient $patientRef on $date). Verify before administering.";
            }
        }
        return $flags;
    }
}

function integ_load_task(int $taskId): ?array {
    $st = getDB()->prepare("SELECT t.*, n.name AS nurse_name, n.email AS nurse_email,
                                   d.name AS doctor_name, d.email AS doctor_email
                            FROM tasks t JOIN users n ON n.id=t.nurse_id JOIN users d ON d.id=t.doctor_id WHERE t.id=?");
    $st->execute([$taskId]);
    return $st->fetch() ?: null;
}

function integ_task_html(array $t): string {
    $h = fn($s) => htmlspecialchars((string) $s);
    return '<table style="border-collapse:collapse;font-size:14px">'
         . '<tr><td style="padding:3px 12px 3px 0;color:#6b7280">Task</td><td><b>' . $h($t['task_title']) . '</b></td></tr>'
         . '<tr><td style="padding:3px 12px 3px 0;color:#6b7280">Date</td><td>' . $h(date('l, F j, Y', strtotime($t['task_date']))) . ($t['due_time'] ? ' at ' . $h(date('g:i A', strtotime($t['due_time']))) : '') . '</td></tr>'
         . '<tr><td style="padding:3px 12px 3px 0;color:#6b7280">Priority</td><td>' . $h(ucfirst($t['priority'])) . '</td></tr>'
         . '<tr><td style="padding:3px 12px 3px 0;color:#6b7280;vertical-align:top">Details</td><td>' . nl2br($h($t['task_description'])) . '</td></tr></table>';
}

function integ_task_created(int $taskId, array $extra = []): array {
    $warnings = [];
    try {
        $db  = getDB();
        $med = trim($extra['medication_name'] ?? '');
        $ref = trim($extra['patient_ref'] ?? '');
        if ($med || $ref) {
            $db->prepare("UPDATE tasks SET patient_ref=?, medication_name=? WHERE id=?")->execute([$ref ?: null, $med ?: null, $taskId]);
        }

        $t = integ_load_task($taskId);
        if (!$t) return ['warnings' => []];

        if ($med) {
            $flags = OpenFDA::interactions($med, $ref, $t['task_date'], $taskId);
            if ($flags) {
                $warnings = array_merge($warnings, $flags);
                $db->prepare("UPDATE tasks SET drug_warning=? WHERE id=?")->execute([implode("\n", $flags), $taskId]);
            }
        }

        $clash = GCal::conflicts((int) $t['nurse_id'], $t['task_date'], $t['due_time'], $taskId);
        if ($clash) $warnings[] = $t['nurse_name'] . ' already has an overlapping calendar item: ' . implode(', ', $clash) . '.';
        if (GCal::enabled()) {
            $ev = GCal::createEvent($t);
            $db->prepare("UPDATE tasks SET calendar_event_id=?, calendar_sync_status=? WHERE id=?")
               ->execute([$ev, $ev ? 'synced' : 'failed', $taskId]);
            if (!$ev) $warnings[] = 'Task saved, but the Google Calendar event could not be created.';
        }

        $extraHtml = '';
        $ix = array_filter($warnings, fn($w) => str_contains($w, 'interaction'));
        if ($ix) $extraHtml = '<p style="color:#b45309"><b>Heads-up:</b><br>' . nl2br(htmlspecialchars(implode("\n", $ix))) . '</p>';
        Brevo::send('assigned', $taskId, (int) $t['nurse_id'], $t['nurse_email'], $t['nurse_name'],
            'New task assigned: ' . $t['task_title'],
            '<p>Hello ' . htmlspecialchars($t['nurse_name']) . ', ' . htmlspecialchars($t['doctor_name']) . ' assigned you a new task.</p>' . integ_task_html($t) . $extraHtml);
    } catch (Throwable $e) { integ_log('task_created: ' . $e->getMessage()); }
    return ['warnings' => $warnings];
}

function integ_task_status_changed(int $taskId, string $status): void {
    try {
        $t = integ_load_task($taskId);
        if (!$t) return;
        $db = getDB();
        if ($status === 'completed') {
            if ($t['calendar_event_id']) GCal::markDone($t['calendar_event_id'], $t['task_title']);
            Brevo::send('completed', $taskId, (int) $t['doctor_id'], $t['doctor_email'], $t['doctor_name'],
                'Task completed: ' . $t['task_title'],
                '<p>' . htmlspecialchars($t['nurse_name']) . ' marked this task as completed.</p>' . integ_task_html($t)
                . ($t['notes'] ? '<p><b>Nurse notes:</b><br>' . nl2br(htmlspecialchars($t['notes'])) . '</p>' : ''));
        } elseif ($status === 'cancelled') {
            if ($t['calendar_event_id']) GCal::deleteEvent($t['calendar_event_id']);
            $db->prepare("UPDATE tasks SET calendar_event_id=NULL, calendar_sync_status='none' WHERE id=?")->execute([$taskId]);
            Brevo::send('cancelled', $taskId, (int) $t['nurse_id'], $t['nurse_email'], $t['nurse_name'],
                'Task cancelled: ' . $t['task_title'], '<p>This task was cancelled by ' . htmlspecialchars($t['doctor_name']) . '.</p>' . integ_task_html($t));
        }
    } catch (Throwable $e) { integ_log('status_changed: ' . $e->getMessage()); }
}

function integ_task_deleting(int $taskId): void {
    try {
        $t = integ_load_task($taskId);
        if (!$t || in_array($t['status'], ['completed', 'cancelled'], true)) return;
        if ($t['calendar_event_id']) GCal::deleteEvent($t['calendar_event_id']);
        Brevo::send('cancelled', $taskId, (int) $t['nurse_id'], $t['nurse_email'], $t['nurse_name'],
            'Task removed: ' . $t['task_title'], '<p>This task was removed by ' . htmlspecialchars($t['doctor_name']) . '.</p>' . integ_task_html($t));
    } catch (Throwable $e) { integ_log('task_deleting: ' . $e->getMessage()); }
}

function integ_send_reminders(): int {
    $db = getDB();
    $tomorrow = (new DateTime('tomorrow', new DateTimeZone(integ_cfg('timezone'))))->format('Y-m-d');
    $st = $db->prepare("SELECT id FROM tasks WHERE task_date=? AND status IN ('pending','in_progress') AND reminder_sent_at IS NULL");
    $st->execute([$tomorrow]);
    $n = 0;
    foreach ($st->fetchAll() as $row) {
        $t = integ_load_task((int) $row['id']);
        if (!$t) continue;
        $ok = Brevo::send('reminder', (int) $t['id'], (int) $t['nurse_id'], $t['nurse_email'], $t['nurse_name'],
            'Reminder for tomorrow: ' . $t['task_title'],
            '<p>Hello ' . htmlspecialchars($t['nurse_name']) . ', this is a reminder of your task tomorrow.</p>' . integ_task_html($t));
        if ($ok) { $db->prepare("UPDATE tasks SET reminder_sent_at=NOW() WHERE id=?")->execute([$t['id']]); $n++; }
    }
    return $n;
}
