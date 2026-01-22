# FilePond Uploader 2.2.0

**Feature-Update** - Vollständige Integration von MetaInfo-Feldern & UI Overhaul.

---

## Was ist neu in 2.2.0

### ✨ Features
- **Dynamische Metadaten:** Automatische Erkennung und Anzeige aller `med_*` Felder aus dem MetaInfo-AddOn.
- **Multilinguale Tabs:** Neue, Tab-basierte Benutzeroberfläche für mehrsprachige Metadatenfelder (Framework-unabhängig).
- **Blacklist-Konfiguration:** Neue Einstellung `excluded_metadata_fields`, um spezifische Metadaten-Felder im Dialog auszublenden.

### 🎨 UI & UX Improvements
- **Dark Mode Support:** Vollständige Anpassung des Metadaten-Modals an den REDAXO Dark Mode.
- **Verbesserte Icons:** Optimierte Darstellung von Datei-Symbolen (PDF, Office, etc.) im Modal.
- **Performance:** Leichtgewichtiges, Framework-unabhängiges Tab-System ersetzt Bootstrap-Abhängigkeit im Modal.

### 🐛 Bugfixes
- **UI-Interaktion:** Problem behoben, bei dem die Sprach-Umschaltung einen Doppelklick erforderte.
- **Darstellung:** Fixes für abgeschnittene Icons und Layout-Probleme.

### 📦 Dependencies
- **FilePond Update:** Core Library auf Version 4.32.11 aktualisiert für bessere Stabilität und Performance.
- **Plugin Updates:** Alle FilePond-Plugins auf den neuesten Stand gebracht.

---

# FilePond Uploader 2.1.0

**Feature-Update** - Konfigurierbare Pflichtfelder für Metadaten.

---

## Was ist neu in 2.1.0

### ✨ Features
- **Konfigurierbare Pflichtfelder:** Neue Einstellung `required_metadata_fields`, um festzulegen, welche Metadaten-Felder (z.B. user, med_alt, med_copyright) beim Upload zwingend ausgefüllt werden müssen.
- **Rückwärtskompatibilität:** Die bestehende Einstellung "Titel als Pflichtfeld" funktioniert weiterhin wie gewohnt.

---

# FilePond Uploader 2.0.6

**Feature-Update** - AI Alt-Text Generierung & Verbesserungen.

---

## Was ist neu in 2.0.6

### ✨ Features
- **AI Alt-Text Generierung:** Automatische Generierung von Alt-Texten mit Google Gemini oder Cloudflare Workers AI.
- **Statistik optional:** Die Barrierefreiheits-Statistik im Alt-Text-Checker kann nun in den Einstellungen deaktiviert werden.

### 🐛 Bugfixes & Verbesserungen
- **Widget Initialisierung:** Verbesserte Zuverlässigkeit beim Laden des Widgets (PJAX/Reload Fixes).
- **Einstellungen:** Fix für doppelte Felder und Speicherprobleme.
- **Sprachdateien:** Korrektes Escaping von Sonderzeichen und HTML-Links in den Einstellungen.

---

# FilePond Uploader 2.0.5

**Feature-Update** - Konfigurierbare Token-Limits für AI-Generierung.

---

## Was ist neu in 2.0.5

### ✨ Features
- **Konfigurierbare Token-Limits:** Das Limit für `maxOutputTokens` kann nun in den Einstellungen konfiguriert werden (Standard: 2048). Dies verhindert abgeschnittene Texte bei ausführlichen Bildbeschreibungen.
- **Provider-Unabhängigkeit:** Die Einstellung gilt sowohl für Google Gemini als auch für Cloudflare Workers AI.

---

# FilePond Uploader 2.0.3

**Bugfix-Release** - Diese Version behebt Probleme mit dem Alt-Text-Checker und verbessert die Benutzerfreundlichkeit.

---

## Was ist neu in 2.0.3

### 🐛 Bugfixes Alt-Text-Checker
- **Kategorie-Filter repariert:** Der Kategorie-Filter funktioniert jetzt korrekt und leitet nicht mehr zur falschen Seite weiter
- **rex_media_category_select Integration:** Verwendet jetzt das standardmäßige REDAXO Kategorie-Select wie in anderen Mediapool-Seiten
- **Formular-Submission korrigiert:** Das Filter-Formular submited jetzt zur richtigen Seite (`mediapool/alt_checker`)
- **Automatische Filterung:** Kategorie-Auswahl triggert automatisch eine neue Suche

### 🔧 Technische Verbesserungen
- **Stabilität erhöht:** Robuste Fehlerbehandlung bei Kategorie-Berechtigungen
- **Performance:** Optimierte Datenbank-Abfragen für Kategorie-Filter
- **Code-Qualität:** Bereinigung und Verbesserung der Code-Struktur

---

## Upgrade-Anleitung

1. **Backup machen** deiner REDAXO-Installation
2. **Addon aktualisieren** über den REDAXO-Installer
3. **Alt-Text-Checker testen** - der Kategorie-Filter sollte jetzt funktionieren

---

## Vorherige Versionen

### FilePond Uploader 2.0.1

**Das größte Update seit der Erstveröffentlichung** - Diese Version bringt KI-gestützte Alt-Text-Generierung, einen neuen Alt-Text-Checker für Barrierefreiheit und viele weitere Features.

#### Neue Features

##### 🤖 KI Alt-Text Generator (brandneu!)
- **Automatische Alt-Text-Generierung** mit Google Gemini oder Cloudflare Workers AI
- **Zwei KI-Provider zur Auswahl:**
  - **Google Gemini** (Gemini 2.0 Flash, 2.5 Flash Preview, 1.5 Pro) - kostenloser API-Zugang
  - **Cloudflare Workers AI** (LLaVA) - komplett kostenlos
- **Mehrsprachig:** Generiert Alt-Texte in der gewählten Sprache (DE, EN, IT, etc.)
- **Intelligente Skip-Logik:** Bereits gefüllte Felder werden übersprungen
- **Batch-Generierung:** "Für alle generieren" Button für alle leeren Felder
- **Token-Anzeige:** Zeigt verbrauchte Tokens nach jeder Generierung
- **Rate-Limit-Handling:** Benutzerfreundliche Meldungen bei API-Limits

##### ♿ Alt-Text-Checker für Barrierefreiheit (brandneu!)
- **Übersicht aller Bilder** im Medienpool mit Alt-Text-Status
- **Filter:** Alle / Ohne Alt-Text / Mit Alt-Text
- **Inline-Editing:** Alt-Texte direkt bearbeiten
- **Mehrsprachig:** Alle Sprachen auf einen Blick bearbeiten
- **Bulk-Speichern:** Alle Änderungen mit einem Klick speichern
- **Ignorieren-Funktion:** Dekorative Bilder als "ignoriert" markieren

#### Was ist neu in 2.0.1
- **Bugfixes und kleine Verbesserungen**
- **Stabilitätsverbesserungen** für den KI-Generator
- **Performance-Optimierungen** im Alt-Text-Checker

---

## Danksagung

Ein großes Dankeschön an alle Tester und die REDAXO-Community für das Feedback!

---

## Links

- **Homepage:** https://github.com/KLXM/filepond_uploader/
- **Dokumentation:** [README.md](README.md)
- **Support:** GitHub Issues