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

### Third-Party Integration
- **3rdparty/Netatmo-API-PHP/** - Netatmo API PHP library
  - Clients, Constants, Exceptions, Handlers
  - Netatmo objects and API wrappers

### Dependencies (vendor/)
- **guzzlehttp/guzzle** - HTTP client
- **league/oauth2-client** - OAuth 2.0 authentication
- **psr/http-*** - PSR HTTP standards

### Internationalization
- **core/i18n/** - Translations (de_DE, en_US, es_ES, fr_FR, id_ID, ja_JP, pt_PT, ru_RU, tr)

### Documentation
- **docs/** - Plugin documentation (en_US, fr_FR)
- **README.md** - Main readme
- **notesDev.md** - Development notes

### Configuration
- **plugin_info/info.json** - Plugin metadata
- **plugin_info/configuration.php** - Configuration UI
- **composer.json** - PHP dependencies

## Development Notes
- Uses OAuth 2.0 for Netatmo API authentication
- Integrates with Jeedom's core framework
- Supports multiple languages
- Uses Materialize CSS for documentation

