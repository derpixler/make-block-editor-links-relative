# WordPress.org Plugin Assets

Dieser Ordner enthält die Grafiken für das WordPress.org Plugin-Verzeichnis.
Die Dateien liegen im SVN im Top-Level-Ordner `assets/` (Geschwister von
`trunk/` und `tags/`) — **nicht** im Plugin-ZIP.

## Fertige Dateien (bereits aus `hero.jpg` / `icon.jpg` erzeugt)

| Datei | Maße | Quelle |
|---|---|---|
| `icon-256x256.jpg` | 256 × 256 px | `icon.jpg` (quadratisch gecroppt) |
| `icon-128x128.jpg` | 128 × 128 px | `icon.jpg` (HiDPI) |
| `banner-772x250.jpg` | 772 × 250 px | `hero.jpg` (auf 3.088:1 gecroppt) |
| `banner-1544x500.jpg` | 1544 × 500 px | `hero.jpg` (HiDPI) |

## Quellen (Originale, bleiben erhalten)

- `hero.jpg` — 1408 × 768 (1,83:1) · Quelle für Banner + GitHub-README-Hero
- `icon.jpg` — 558 × 535 · Quelle für das Icon

## Noch offen

- `screenshot-1.png` / `.jpg` (1280 × 720, 16:9) — Screenshot aus dem Plugin
  (Editor/Frontend), max. 10 Stück. Müssen manuell erstellt werden.

## Regeln (WordPress.org)

- Formate PNG/JPEG (Icon zusätzlich als `icon.svg` möglich).
- Banner/Icon dürfen den Plugin-Namen enthalten — Text nicht in den Randbereich
  legen (Banner wird auf 772×250 zugeschnitten, Icon rund beschnitten).
- Screenshots zeigen Funktionalität, keine Marketing-Folie.

> Hinweis: Der Banner-Crop von `hero.jpg` (1,83:1 → 3,088:1) schneidet oben/unten
> ~40 % ab. Falls Text/Logo abgeschnitten wird: eigenes breites Banner (772×250)
> erstellen und `banner-772x250.jpg` ersetzen.

> Das `readme.txt` (WordPress.org-Format) liegt im Repo-Root.
