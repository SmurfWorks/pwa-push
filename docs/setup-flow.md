# Set up PWA Push

Herd serves this folder at <http://pwa-push.test>. Do these four steps in order. The server does not know why a reminder exists. The app decides the time and the words, and this server delivers them.

```mermaid
flowchart TD
  install["1. Install the server"] --> pushApp["2. Register a push app"]
  pushApp --> device["3. Register a device"]
  device --> notification["4. Register a notification"]
  notification --> due{"Has that phone reached the time?"}
  due -->|Not yet| wait["Check again next minute"]
  wait --> due
  due -->|Yes, still inside the grace| send["Send the push"]
  due -->|Later than the grace| skip["Skip it"]
```

## 1. Install the server

`push:install` writes a VAPID key pair and an admin token into `.env` when they are missing. The public key is shared by every app. The admin token is only for registering apps.

```mermaid
flowchart TD
  start([Install the server]) --> env{".env exists?"}
  env -->|No| copy["Copy .env.example to .env"]
  copy --> install
  env -->|Yes| install["php artisan push:install"]
  install --> vapid{"VAPID keys already in .env?"}
  vapid -->|No| keys["Write a VAPID key pair and print the public key"]
  vapid -->|Yes| token
  keys --> token{"PUSH_ADMIN_TOKEN already in .env?"}
  token -->|No| admin["Write an admin token and print it once"]
  token -->|Yes| sqlite
  admin --> sqlite{"database/database.sqlite exists?"}
  sqlite -->|No| touch["Create that empty file"]
  sqlite -->|Yes| migrate
  touch --> migrate["php artisan migrate"]
  migrate --> host{"Where is it running?"}
  host -->|This Mac| work["php artisan schedule:work"]
  host -->|A server| cron["Cron runs php artisan schedule:run every minute"]
```

On this Mac, leave `schedule:work` running. It sends anything that is due, once a minute. On a server:

```bash
* * * * * cd /path/to/pwa-push && php artisan schedule:run >> /dev/null 2>&1
```

## 2. Register a push app

Give every origin the app is actually served from, including `http://localhost` while you are developing. `http` is only for localhost, `127.0.0.1`, `::1`, and hosts ending in `.test`. The printed token goes into that app. It is not shown again.

```mermaid
flowchart TD
  start([Register a push app]) --> how{"Artisan or HTTP?"}
  how -->|Artisan| cli["php artisan push-apps:create Name --origin=https://example.com"]
  how -->|HTTP| http["POST /api/apps with Authorization: Bearer admin token"]
  cli --> origins{"At least one origin, and each one allowed?"}
  http --> origins
  origins -->|No| fixOrigin["Use the sites the app is served from"]
  fixOrigin --> how
  origins -->|Yes| name{"That name is already registered?"}
  name -->|Yes| taken["Pick another name, or rotate that app's token"]
  name -->|No| saved["App id and token are printed once"]
  saved --> store["Put the token in that app"]
```

Artisan:

```bash
php artisan push-apps:create "Dosage Helper" \
  --origin=https://example.com \
  --origin=http://localhost:5173
```

HTTP, with the admin token:

```bash
curl -X POST http://pwa-push.test/api/apps \
  -H "Authorization: Bearer $PUSH_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Worknote","origins":["https://worknote.example","http://localhost:5174"]}'
```

`php artisan push-apps:list` lists apps. `php artisan push-apps:rotate "Dosage Helper"` replaces a token. A token only lets that app register its own phones and its own notifications.

## 3. Register a device

The page asks for notification permission, subscribes with the public key, then registers the phone. Store the returned device id on the phone. The same call again updates the keys and the timezone, and returns the same id.

```mermaid
flowchart TD
  start([Register a device]) --> publicKey["GET /api/vapid-public-key"]
  publicKey --> permission{"Notification permission granted?"}
  permission -->|No| ask["The page asks the user to allow notifications"]
  ask --> permission
  permission -->|Yes| subscribe["pushManager.subscribe with that public key"]
  subscribe --> post["POST /api/devices with the app token"]
  post --> body["Body: subscription.toJSON and timezone, such as America/Los_Angeles"]
  body --> owner{"This endpoint already belongs to another app?"}
  owner -->|Yes| conflict["409. The phone stays with that other app"]
  owner -->|No| saved["Response is the device id"]
  saved --> keep["Store the id on the phone"]
  keep --> later["A later POST with the same endpoint updates keys and timezone"]
```

```http
POST /api/devices
Authorization: Bearer {app token}

{
  "timezone": "America/Los_Angeles",
  "subscription": {
    "endpoint": "https://...",
    "keys": { "p256dh": "...", "auth": "..." }
  }
}
```

Leaving `timezone` out keeps the one already stored. `DELETE /api/devices/{id}` removes the phone.

## 4. Register a notification

The key is yours (`expiry`, `time-09:00`, `2026-10-12T17:00`). Putting the same key again replaces it. If the time or the text changed, it will send again. If nothing changed, a notification that already went out stays sent.

`deliver_at` can be a moment that already includes a timezone, such as `2026-10-12T16:00:00Z`, or a clock time with none, such as `2026-10-12T09:00:00`. A clock time is read in the phone's timezone, so 9:00 is sent when that phone reaches 9:00. When the phone's timezone changes, a waiting clock time moves with it. A time that already includes a timezone stays at that moment.

```mermaid
flowchart TD
  start([Register a notification]) --> put["PUT /api/devices/{id}/notifications/{key} with the app token"]
  put --> zoned{"deliver_at includes a timezone?"}
  zoned -->|Yes| moment["Send at that moment"]
  zoned -->|No, a clock time| phoneZone{"The phone has a timezone?"}
  phoneZone -->|No| needZone["Register a timezone, or send a time that includes one"]
  phoneZone -->|Yes| exists{"That clock time exists on that day?"}
  exists -->|Skipped when the clocks go forward| refused["The booking is refused"]
  exists -->|Yes| local["Read 9:00 in the phone's timezone"]
  exists -->|The hour happens twice| earlier["Use the earlier one"]
  moment --> address
  local --> address
  earlier --> address{"url and icon are on an origin of this app?"}
  address -->|No| badAddress["Use an address on a site registered for this app"]
  address -->|Yes| changed{"Time or text changed since last time?"}
  changed -->|No, and it already went out| stays["It stays sent"]
  changed -->|Yes, or it is new| pending["It is pending"]
  pending --> tick["Each minute, alerts:send looks for due notifications"]
  tick --> grace{"Due, and still inside grace_minutes?"}
  grace -->|Not yet| tick
  grace -->|Yes| push["The push is sent"]
  grace -->|Past the grace, default 30 minutes| skipped["It is skipped"]
  push --> worker["The app's service worker calls showNotification"]
```

```http
PUT /api/devices/{id}/notifications/{key}
Authorization: Bearer {app token}

{
  "deliver_at": "2026-10-12T09:00:00",
  "title": "Dosage Helper",
  "body": "Your bolus could be wearing off.",
  "url": "https://example.com/",
  "icon": "https://example.com/icons/icon-192.png",
  "tag": "expiry",
  "grace_minutes": 30
}
```

`url` and `icon` have to be on an origin you registered for that app. `grace_minutes` defaults to 30.

`DELETE /api/devices/{id}/notifications/{key}` cancels one. `DELETE /api/devices/{id}/notifications` cancels all of them for that phone. When the user logs a dose or saves a note, the app cancels or replaces the pending notification. This server cannot see that happen until the app tells it.
