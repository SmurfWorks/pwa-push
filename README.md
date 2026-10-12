# PWA Push

A small server that sends a notification to a phone when one of your installed web apps asks it to. The server does not know why the reminder exists. The app decides the time and the words, and this server delivers them.

Each app keeps its own data. What comes here is the phone's push subscription, and a notification such as "show this title and text at this time."

## Run it on this Mac

Herd serves this folder at <http://pwa-push.test>.

```bash
php artisan push:install
php artisan migrate
php artisan schedule:work
```

`push:install` writes a VAPID key pair and an admin token into `.env` when they are missing. The public key is shared by every app. The admin token is only for registering apps. `schedule:work` sends anything that is due, once a minute, while that terminal stays open.

On a server, cron replaces `schedule:work`:

```bash
* * * * * cd /path/to/pwa-push && php artisan schedule:run >> /dev/null 2>&1
```

SQLite is enough. The database file is `database/database.sqlite`.

## Register an app

```bash
php artisan push-apps:create "Dosage Helper" \
  --origin=https://example.com \
  --origin=http://localhost:5173

php artisan push-apps:list
php artisan push-apps:rotate "Dosage Helper"
```

Give every origin the app is actually served from, including `http://localhost` while you are developing. The printed token goes into that app. It is not shown again. A token only lets that app register its own phones and its own notifications.

The same call over HTTP, with the admin token:

```bash
curl -X POST http://pwa-push.test/api/apps \
  -H "Authorization: Bearer $PUSH_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Worknote","origins":["https://worknote.example","http://localhost:5174"]}'
```

`GET /api/apps` lists apps. `PATCH /api/apps/{id}` changes the name or origins. `POST /api/apps/{id}/token` replaces the token. `DELETE /api/apps/{id}` removes the app, its phones, and its notifications.

## What an app calls

The public key, which the app passes to `pushManager.subscribe`:

```http
GET /api/vapid-public-key
```

Register this phone. Send the subscription object from `subscription.toJSON()`. The same call again updates the keys and returns the same id.

```http
POST /api/devices
Authorization: Bearer {app token}

{
  "subscription": {
    "endpoint": "https://...",
    "keys": { "p256dh": "...", "auth": "..." }
  }
}
```

Book a notification. The key is yours (`expiry`, `time-09:00`, `2026-10-12T17:00`). Putting the same key again replaces it. If the time or the text changed, it will send again. If nothing changed, a notification that already went out stays sent.

```http
PUT /api/devices/{id}/notifications/{key}
Authorization: Bearer {app token}

{
  "deliver_at": "2026-10-12T16:00:00Z",
  "title": "Dosage Helper",
  "body": "Your bolus could be wearing off.",
  "url": "https://example.com/",
  "icon": "https://example.com/icons/icon-192.png",
  "tag": "expiry",
  "grace_minutes": 30
}
```

`url` and `icon` have to be on an origin you registered for that app. `grace_minutes` defaults to 30: a notification that is still waiting after that many minutes is skipped, which matches Dosage Helper skipping a reminder missed by more than 30 minutes.

`DELETE /api/devices/{id}/notifications/{key}` cancels one. `DELETE /api/devices/{id}/notifications` cancels all of them for that phone. `DELETE /api/devices/{id}` removes the phone.

When the user logs a dose or saves a note, the app cancels or replaces the pending notification. This server cannot see that happen until the app tells it.

## What the service worker should do

Each installed app still shows its own notifications. On a push, it has to call `showNotification`. iPhone drops permission if a push arrives and nothing is shown.

```js
self.addEventListener('push', (event) => {
  const data = event.data?.json() ?? {}
  event.waitUntil(self.registration.showNotification(data.title || 'Reminder', {
    body: data.body || '',
    icon: data.icon,
    tag: data.tag,
    data: { url: data.url },
  }))
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const url = event.notification.data?.url
  if (url) event.waitUntil(self.clients.openWindow(url))
})
```

The page subscribes with the public key from `GET /api/vapid-public-key`, then `POST`s the subscription. Store the returned device id on the phone, and `PUT` a notification whenever the reminder time changes.

## Tests

```bash
php artisan test
```
