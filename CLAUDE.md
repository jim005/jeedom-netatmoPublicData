# NetatmoPublicData Plugin - Claude Context

## Overview 
This is a Jeedom plugin for accessing Netatmo Public Weather Data API.

## Project Structure

### Core Components
- **core/class/netatmoPublicData.class.php** - Main plugin class
- **core/ajax/netatmoPublicData.ajax.php** - AJAX handlers
- **core/php/AuthorizationCodeGrant.php** - OAuth authentication handler

### Frontend
- **desktop/php/netatmoPublicData.php** - Desktop UI
- **desktop/js/netatmoPublicData.js** - JavaScript logic
- **desktop/css/netatmoPublicData.css** - Styling

### Dependencies
- **None.** HTTP and OAuth 2.0 calls use native curl (`netatmoPublicData::httpRequest()`,
  `requestOAuthTokens()`, `getOAuthAuthorizationUrl()`).
- Do NOT reintroduce Composer / vendor/: Jeedom runs every plugin in the same PHP process, and a
  bundled library collides with the copy shipped by another plugin (e.g. Guzzle 6 from
  SmartLife/Tuya). See COMPATIBILITE-PHP.md §8.

### Internationalization
- **core/i18n/** - Translations (de_DE, en_US, es_ES, fr_FR, id_ID, ja_JP, pt_PT, ru_RU, tr)

### Documentation
- **docs/** - Plugin documentation (en_US, fr_FR)
- **README.md** - Main readme
- **notesDev.md** - Development notes

### Configuration
- **plugin_info/info.json** - Plugin metadata
- **plugin_info/configuration.php** - Configuration UI

## Development Notes
- Uses OAuth 2.0 for Netatmo API authentication (native curl, no library)
- Integrates with Jeedom's core framework
- Supports multiple languages
- Uses Materialize CSS for documentation

