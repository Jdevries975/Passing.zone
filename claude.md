# Passing.zone — Projektkontext

## System
- WordPress, gehostet auf Shared Hosting bei all-inkl.com
- Cloudflare, normalerweise mit "Under Attack Mode"
- language for both frontend and backend is English, GB

## Theme & Plugins
- **Theme:** GeneratePress Premium (Child Theme)
- **Page Builder:** Beaver Builder Pro mit Themer
- **BB Add-ons:** Powerpack Pro, Ultimate Add-ons Pro
- **Custom Fields:** ACF Pro
- **WP Rocket**
- **Borlabs Cookie**
- **Gravity Forms** for registration, login all forms and also pattern upload
- **Ajax Search Pro**, **Matomo** (ohne Cookies), **Wordfence**, eigenes Plugin **calendar-julike** (FullCalendar lokal)

## Sicherheit (Stand 2026-09-25)

### Merken
1. **Neuer externer Dienst** (YouTube, Google Maps, Tracking …) wird nicht angezeigt → die CSP blockiert ihn.
   Host in der CSP in `.htaccess` freigeben (zum Testen vorübergehend `Content-Security-Policy-Report-Only`).
2. **Nach Plugin-Updates** (WP Rocket, Wordfence, ShortPixel schreiben die `.htaccess` neu) prüfen, ob
   `# BEGIN jdev Security` noch in der Server-`.htaccess` steht. Die eigenen Blöcke sind schon einmal verloren gegangen.
   Vor jedem `.htaccess`-Upload die Server-Datei sichern (lokal `backup/`, nicht eingecheckt).

### Wo was geregelt ist
- **`.htaccess`, Block `jdev Security`** (vor `# BEGIN WordPress`): Security-Header, CSP (scharf, nur für nicht
  eingeloggte Besucher; mit `'unsafe-inline'` wegen WP-Rocket-Cache), Sperren für readme/license/wp-includes,
  `?author=N`, `?rest_route=/wp/v2/users`, xmlrpc, alte Ninja-Forms-Uploads; SVGs ohne Script.
  CSP-Freigaben: Google Calendar API (Kalender), reCAPTCHA (google.com/gstatic.com), Microsoft Clarity. Keine Google Fonts.
- **Cloudflare** (nicht in der `.htaccess`!): HTTPS-Weiterleitung, HSTS (12 Monate), TLS ≥ 1.2, KI-Crawler
  (AI Crawl Control, Suchmaschinen erlaubt), Custom Rule „jdev Bots & IPs“ (Bot-User-Agents + IP-Bereiche,
  Lars' Videoserver 91.99.57.23 ausgenommen). `facebookexternalhit` ist gesperrt → keine Link-Vorschauen auf Facebook/WhatsApp.
- **`functions.php`:** SVG-Upload nur ab Rolle Autor, wird bereinigt (auch das Gravity-Forms-Pattern-Bild);
  REST `/wp/v2/users` nur für Redakteur:innen; Google-`@font-face`/Preconnect von Ajax Search Pro werden aus dem
  HTML entfernt (`jdev_strip_google_fonts`).
- **`style.css`:** Ajax Search Pro nutzt die Seitenschrift (ASP ignoriert „inherit“ für Instanz 2).
- **`uploads/.htaccess`:** kein PHP im Uploads-Ordner.

### Entscheidungen
- Autorenseiten bleiben mit den bisherigen Slugs: Logins sind `vorname.nachname` und aus den sichtbaren Namen
  ohnehin ableitbar, andere Slugs brächten keinen Schutz. Nur Admin-Konten (und Konten mit E-Mail als Login)
  bekommen per „Edit Author Slug“ einen eigenen Slug. Schutz stattdessen über Wordfence (2FA, Login-Limits).