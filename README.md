# Passing.zone
Eine Sammlung von Ideen, Fragen, To Dos

## Shortcodes (functions.php)

### `[pz_related_patterns]`
Zeigt unter einem Pattern ähnliche Patterns als Karten – gleiche Optik wie „Newest Patterns“ auf der Startseite:
Bild (oben abgerundet), Titel, Datum, Schwierigkeit und Anzahl Jongleur:innen. Kein Hover-Effekt.

- Eingebunden im Beaver-Themer-Layout „Single Pattern“.
- Auswahl: zufällige Patterns mit gleichem Pattern-Typ oder Tag; reicht das nicht, wird mit anderen zufälligen Patterns aufgefüllt.
- Attribute: `count` (Standard `3`), `heading` (Standard `More patterns to try:`, leer = keine Überschrift).
  Beispiel: `[pz_related_patterns count="6" heading="Similar patterns"]`
- CSS: `style.css`, Klassen `.pz-related-*`.
- Ersetzt das Plugin „Related Posts Thumbnails“ (gelöscht am 2026-11-09).

## Hosting, Cloudflare und SSL

Die Domain `passing.zone` zeigt per DNS auf Cloudflare (Proxy aktiv). Cloudflare holt die Seiten beim Webspace
von all-inkl.com ab. Es gibt also zwei Verbindungen, die beide verschlüsselt sein müssen:

```
Besucher ──HTTPS──▶ Cloudflare ──HTTPS──▶ all-inkl (Webspace /passing.zone/)
          Cloudflare-Zertifikat   Cloudflare-Origin-Zertifikat
```

**Aktuelle Einstellungen (Stand 2026-11-23):**

| Wo | Einstellung |
|---|---|
| Cloudflare → SSL/TLS | Modus **Full (strict)** |
| Cloudflare | HTTP → HTTPS, ohne `www`, HSTS 12 Monate |
| KAS → SSL-Schutz → passing.zone | **Cloudflare-Origin-Zertifikat** (gültig 15 Jahre, bis ca. 2041), SSL aktiv, SSL erzwingen: ja, HSTS: nein |
| `.htaccess` | keine eigene HTTPS-/www-Umleitung |

**Warum kein Let's Encrypt bei all-inkl?** all-inkl prüft vor dem Ausstellen, ob die Domain auf den eigenen Server
zeigt. Sie zeigt aber auf Cloudflare, daher scheitert Ausstellung und Verlängerung. Das Origin-Zertifikat stellt
Cloudflare selbst aus; es wird nur von Cloudflare akzeptiert, nicht von Browsern – das reicht, weil Besucher nie
direkt mit all-inkl sprechen.

### Störung am 2026-11-23: „Umleitungsfehler“

- **Symptom:** Browser meldet „Umleitungsfehler“ / „too many redirects“, auf allen Seiten.
- **Ursache:** Das Let's-Encrypt-Zertifikat bei all-inkl war weg (Verlängerung hinter Cloudflare unmöglich),
  der SSL-Schutz im KAS stand auf „deaktiviert“. all-inkl leitete deshalb jede HTTPS-Anfrage auf `http://` um,
  Cloudflare wieder auf `https://` – Endlosschleife.
- **Lösung:** Cloudflare-Origin-Zertifikat erzeugt (Cloudflare → SSL/TLS → Origin Server → Create Certificate,
  RSA, `passing.zone` + `*.passing.zone`, 15 Jahre) und im KAS unter SSL-Schutz eingetragen
  (KEY = Private Key, CRT = Origin Certificate, CSR und Brückenzertifikate leer). Cloudflare auf Full (strict),
  Cache geleert („Purge Everything“).

**Schnelltest**, ob die Kette stimmt:

```sh
curl -sI https://passing.zone/ | grep -iE "^(HTTP|location)"   # erwartet: 200
curl -sI http://passing.zone/  | grep -iE "^(HTTP|location)"   # erwartet: 301 → https://passing.zone/
```

Kommt bei `https://` ein `301` mit `location: http://…`, ist das Zertifikat bei all-inkl wieder weg.
