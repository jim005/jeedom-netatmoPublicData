# Compatibilité PHP 7.4 → 8.3

Analyse du plugin **netatmoPublicData** (v2.1) sur la plage PHP 7.4 à 8.3.

Périmètre : `core/`, `desktop/php/`, `plugin_info/`, `vendor/`, `composer.json` / `composer.lock`.

## Méthode

- `php -l` sur les 5 fichiers PHP du plugin : aucune erreur de syntaxe.
- `PHPCompatibility` (PHP_CodeSniffer), `testVersion 7.4-8.3`, sur le code plugin **et** sur le `vendor/` versionné : **0 signalement**.
- Recherche des constructions supprimées ou dépréciées : `each()`, `create_function()`, interpolation `${var}`, offsets `$s{0}`, propriétés dynamiques (8.2), `utf8_encode/decode`, `strftime`, `FILTER_SANITIZE_STRING` → **aucune occurrence**.
- Vérification empirique du comportement runtime des appels sensibles (coercition de types, accès tableau) sur PHP 8.
- Résolution Composer simulée avec une plateforme PHP 7.4.

L'analyse statique est donc propre. Les problèmes réels sont **sémantiques** : PHP 8.0 a transformé en `TypeError` fatale plusieurs coercitions qui n'étaient qu'un warning en 7.4. Ce sont ces points qui cassent le plugin, pas la syntaxe.

## Synthèse

| # | Emplacement | Impact | Version concernée |
|---|---|---|---|
| 1 | `core/class/netatmoPublicData.class.php:147` | Fatale | PHP 8.0+ |
| 2 | `desktop/php/netatmoPublicData.php:107` | Fatale | PHP 8.0+ |
| 3 | `core/class/netatmoPublicData.class.php:461, 491, 533` | Fatale | PHP 8.0+ |
| 4 | `composer.lock` (`symfony/deprecation-contracts` v3.7.1) | Bloque `composer install` | PHP 7.4 |
| 5 | `composer.lock` ↔ `vendor/` | Divergence de versions | Toutes |
| 6 | `core/class/netatmoPublicData.class.php:155` | Fatale (bug préexistant) | Toutes |
| 7 à 11 | divers | Warnings | PHP 8.0+ |

---

## 1. `TypeError` sur le calcul d'expiration du token — bloquant PHP 8

`core/class/netatmoPublicData.class.php:145-150`

```php
$npd_expires_at = config::byKey('npd_expires_at', 'netatmoPublicData');
log::add(..., round((time() - $npd_expires_at) / 60 * -1) ...);
if (is_null($npd_expires_at) || $npd_expires_at < time()) {
```

`config::byKey()` renvoie `''` (et non `null`) quand la clé n'existe pas. En PHP 8, `time() - ''` lève `TypeError: Unsupported operand types: int - string`. En PHP 7.4, c'était un simple `Warning: A non-numeric value encountered`.

Le cas est atteignable en conditions normales : le bouton **Débrancher** (`npdRemoveTokens()` dans `plugin_info/configuration.php:355`) supprime `npd_expires_at`, tout comme une installation neuve. Le prochain appel à `getNetatmoData()` — donc le cron 15 min — plante.

Second défaut au même endroit : `is_null($npd_expires_at)` n'est jamais vrai puisque la valeur par défaut est `''`. Le garde-fou ne protège rien.

Correctif :

```php
$npd_expires_at = (int) config::byKey('npd_expires_at', 'netatmoPublicData', 0);
log::add(..., round(($npd_expires_at - time()) / 60) ...);
if ($npd_expires_at <= 0 || $npd_expires_at < time()) {
```

## 2. `TypeError` sur `str_repeat()` — bloquant PHP 8

`desktop/php/netatmoPublicData.php:107`

```php
$options .= '<option ...>' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . ...;
```

Si `getConfiguration('parentNumber')` renvoie `''` (valeur par défaut de Jeedom quand la clé est absente), PHP 8 lève `TypeError: str_repeat(): Argument #2 ($times) must be of type int, string given`. La page de l'équipement devient inaccessible. En PHP 7.4 la valeur était silencieusement convertie en `0`.

Correctif : `str_repeat('&nbsp;&nbsp;', (int) $object->getConfiguration('parentNumber', 0))`.

## 3. `TypeError` sur `array_column()` — bloquant PHP 8

`core/class/netatmoPublicData.class.php:461`, `:491`, `:533`

```php
$netatmo_array_key = array_search($this->getLogicalId(), array_column($netatmo, '_id')); // ligne 448
...
$NAModule1_array_key = array_search($module_type, array_column($netatmo[$netatmo_array_key]['modules'], 'type'));
```

`$netatmo_array_key` vaut `false` si l'équipement Jeedom n'est plus présent dans la réponse Netatmo (station retirée des favoris entre deux synchronisations). `$netatmo[false]` est alors résolu en `$netatmo[0]`, dont la clé `modules` peut être absente → `array_column(null, 'type')` → `TypeError: Argument #1 ($array) must be of type array, null given`. En PHP 7.4 : warning + `null`, la boucle continuait.

Correctif : sortir tôt si `$netatmo_array_key === false`, et vérifier `is_array($netatmo[$netatmo_array_key]['modules'])` avant l'appel — le même garde-fou que celui déjà présent ligne 441.

## 4. `composer.lock` non installable sur PHP 7.4

`composer.lock` verrouille `symfony/deprecation-contracts v3.7.1`, qui déclare `"php": ">=8.1"` et utilise le type `mixed` (PHP 8.0+) dans `function.php` — fichier chargé via l'autoload `files`, donc à chaque requête.

Conséquence : `composer install` échoue sur un Jeedom en PHP 7.4 (Debian 10 / 11, Jeedom 4.2-4.3). Confirmé par une résolution simulée avec `platform.php = 7.4.33` : Composer rétrograde alors vers `v2.5.4`.

Ce n'est pas visible aujourd'hui parce que le `vendor/` versionné contient encore la v2.5.2 — voir le point suivant.

Correctif : déclarer explicitement la plateforme cible dans `composer.json`, ce qui force Composer à ne verrouiller que des versions compatibles.

```json
{
    "require": {
        "php": ">=7.4",
        "league/oauth2-client": "^2.7"
    },
    "config": {
        "platform": { "php": "7.4.33" }
    }
}
```

## 5. Divergence entre `composer.lock` et `vendor/`

Le répertoire `vendor/` est versionné (195 fichiers suivis par Git) et c'est lui qui est réellement chargé à l'exécution : `core/class/netatmoPublicData.class.php:25` inclut directement `vendor/autoload.php`.

| Paquet | `vendor/` (exécuté) | `composer.lock` |
|---|---|---|
| guzzlehttp/guzzle | 7.7.0 | 7.15.2 |
| guzzlehttp/psr7 | 2.5.0 | 2.13.0 |
| guzzlehttp/promises | 2.0.0 | 2.5.1 |
| psr/http-client | 1.0.2 | 1.0.3 |
| psr/http-factory | 1.0.2 | 1.1.0 |
| symfony/deprecation-contracts | v2.5.2 | v3.7.1 |
| symfony/polyfill-php80 | absent | v1.37.0 |

Les montées de version Dependabot (commits `e73764e`, `f0d9d16`, `67b50b0`) n'ont mis à jour que le `composer.lock` ; le `vendor/` n'a jamais été régénéré. Le lock est donc purement décoratif aujourd'hui, et les correctifs de sécurité qu'il embarque ne sont pas appliqués.

À retenir : régénérer `vendor/` depuis le lock actuel **casserait immédiatement PHP 7.4** (point 4). Il faut d'abord poser la contrainte de plateforme, refaire un `composer update`, puis committer `vendor/` et `composer.lock` ensemble.

Les deux arbres, l'ancien comme le nouveau, sont par ailleurs compatibles PHP 8.3 (Guzzle 7.x et league/oauth2-client 2.7 le sont).

## 6. Fonction imbriquée redéclarée — fatale, toutes versions

`core/class/netatmoPublicData.class.php:155`

`performRequestWithToken()` est déclarée dans le corps de `getNetatmoData()`. C'est une fonction globale : au second appel de `getNetatmoData()` dans la même requête, PHP émet `Fatal error: Cannot redeclare performRequestWithToken()`.

Le scénario est atteignable : si l'appel API échoue, `getNetatmoData()` renvoie `false`, `self::$_netatmoData` vaut `false`, et `updateValues()` (ligne 434, test `empty()`) rappelle `getNetatmoData()` → fatale.

Défaut connexe ligne 175 : la relance après un 403 n'est pas retournée (`performRequestWithToken($npd_access_token);` sans `return`), la fonction retombe donc sur `return false` et le nouveau token obtenu est perdu.

Correctif : transformer en méthode statique privée de la classe, et ajouter le `return` manquant.

## 7 à 11. Warnings PHP 8 (non bloquants)

Ces points étaient des `Notice` en PHP 7.4 et deviennent des `Warning` en PHP 8. Ils polluent les logs Jeedom et signalent chacun un défaut logique réel.

- **`class.php:239` / `:386`** — `$new_equipment` n'est initialisé que dans la branche « nouvel équipement » et n'est jamais réinitialisé entre deux itérations. Warning `Undefined variable` au premier passage, et surtout dimensionnement de widget appliqué à tort aux équipements existants dès qu'un nouveau a été créé plus tôt dans la boucle. À initialiser à `false` en début d'itération.
- **`configuration.php:240`** — `$netatmoAuthorizationUrl` n'est défini que dans la branche `else` de la ligne 105. Si l'accès externe Jeedom est invalide, warning + variable JS vide, et le bouton d'association ouvre une page blanche. À initialiser à `''` avant le bloc conditionnel.
- **`class.php:604`** — `self::$_moduleType[$netatmo_module['type']]` : la clé `NAMain` n'existe pas dans le tableau (seuls `NAModule1` à `NAModule4` y figurent), alors que le cas `pressure` appelle `sendErrorMessage()` avec ce type. Warning `Undefined array key`. À sécuriser avec `?? $netatmo_module['type']`.
- **`class.php:218`** — `foreach (self::$_netatmoData['devices'] as $device)` sans garde alors que `getNetatmoData()` peut renvoyer `false`. Deux warnings en cascade. La ligne 441 fait déjà ce contrôle ailleurs, à répliquer ici.
- **`class.php:133` / `:180`** — `$content_array['state']` et `$content_array['body']` sur un `json_decode()` qui peut renvoyer `null` si la réponse n'est pas du JSON.

## Conclusion

Le plugin **n'est pas compatible PHP 8.x en l'état** : trois `TypeError` fatales (points 1, 2, 3) attendent des conditions parfaitement ordinaires — installation neuve, débranchement de la liaison, station retirée des favoris. Aucune n'est détectée par l'analyse statique, toutes découlent du durcissement des coercitions de types en PHP 8.0.

Sur **PHP 7.4**, le code fonctionne aujourd'hui grâce au `vendor/` figé en version ancienne, mais le `composer.lock` est déjà hors compatibilité. Le premier `composer install` ou la première régénération du `vendor/` cassera l'installation.

Ordre de traitement suggéré :

1. Points 1, 2, 3 — casts et gardes explicites, corrections courtes et sans risque.
2. Point 6 — sortir la fonction imbriquée, corriger le `return` manquant.
3. Points 4 et 5 — poser `"php": ">=7.4"` et la contrainte `config.platform`, régénérer `vendor/` et le committer avec le lock.
4. Points 7 à 11 — nettoyage des warnings.

Il faut aussi choisir : si le support de Jeedom 4.2 (PHP 7.4) est abandonné, `plugin_info/info.json` doit passer `require` à `4.4` et `composer.json` déclarer `"php": ">=8.1"`. Sinon le socle 7.4 doit être verrouillé côté Composer.

Note annexe, hors périmètre : `CLAUDE.md` documente un répertoire `3rdparty/Netatmo-API-PHP/` qui n'existe plus dans le dépôt, et `.gitmodules` est vide. Documentation à mettre à jour.
