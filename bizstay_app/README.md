# bizstay_app

BizStay PG management - manager + tenant app. One app, two roles.

## Run it

```bash
php artisan serve --host=0.0.0.0 --port=8000
cd bizstay_app
flutter pub get
flutter run
```

API URL: `--dart-define=BIZSTAY_API_URL=...`, else login gear-icon
override, else default `https://bizstay.onrender.com/api` (emulator).
Real phone on same Wi-Fi: `http://<mac-lan-ip>:8000/api`.

## Sign in

- Manager: email + password, e.g. `admin@mail.com` / `password`.
- Tenant: phone + password, e.g. `9810000001` / `password`.

## Screens

- Manager: Today (occupancy, dues, P&L), Rooms (beds + PG/hotel
  tariffs), Residents (search, KYC, dues), Billing (queues + Collect
  sheet with UTR), Ops (complaints + SLA, leads, log-a-complaint).
- Tenant: My stay (bed, dues, UPI card, history), Bills (GST split),
  Help (raise + track requests).

## Release checklist

- Serve API over HTTPS; remove LAN-only cleartext flags
  (`usesCleartextTraffic`, `NSAllowsArbitraryLoads`).
- Build: `flutter build apk --dart-define=BIZSTAY_API_URL=https://.../api`
