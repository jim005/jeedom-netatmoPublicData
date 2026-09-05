# Compatibilité PHP 7.4 → 8.3 et « Class netatmoPublicData does not exist »

Cible de compatibilité retenue : **Jeedom 4.6 minimum**, **PHP 7.4 à 8.3**.

Ces deux contraintes sont cohérentes avec le core : le `composer.json` de Jeedom 4.6.2 déclare
`"php": ">=7.4"` et épingle `config.platform.php = "7.4"`. Un Jeedom 4.6 peut donc tourner sous
PHP 7.4 comme sous PHP 8.3, et le plugin doit couvrir toute la plage.

## 1. Cause racine de « Class netatmoPublicData does not exist »

Le message ne vient pas du plugin mais du core, et il masque la vraie panne. Chaîne complète,
vérifiée sur le code de Jeedom 4.6.2.

**Étape 1 — Jeedom supprime le `vendor/` livré avec le plugin.**
`plugin_info/packages.json` déclare une dépendance Composer sur le répertoire du plugin. À
l'installation des dépendances, `core/class/system.class.php:874` exécute :

```
cd plugins/netatmoPublicData; rm -rf vendor; composer install --no-dev --optimize-autoloader ...
```

Le `rm -rf vendor` est inconditionnel. Le `vendor/` versionné est détruit et reconstruit **depuis
`composer.lock`**.

**Étape 2 — le lock imposait PHP 8.1.**
Le `composer.lock` verrouillait `symfony/deprecation-contracts v3.7.1` (`"php": ">=8.1"`, et un type
`mixed` chargé par l'autoload `files`). Le `vendor/composer/platform_check.php` régénéré exigeait
alors :

| Origine | Garde générée |
|---|---|
| `vendor/` versionné (arbre figé, Guzzle 7.7.0) | `PHP_VERSION_ID >= 70205` |
| `composer.lock` avant correctif | `PHP_VERSION_ID >= 80100` |
| `composer.lock` après correctif | `PHP_VERSION_ID >= 70400` |

Sous PHP 7.4, l'installation échoue — ou aboutit à un `vendor/` dont l'autoload lève une
`RuntimeException` à chaque requête. Dans les deux cas `vendor/autoload.php` est inutilisable.

**Étape 3 — le `require_once` non gardé tuait la déclaration de la classe.**
`core/class/netatmoPublicData.class.php` faisait un `require_once` sec sur `vendor/autoload.php`.
L'échec survient **avant** l'instruction `class netatmoPublicData extends eqLogic`, qui n'est donc
jamais exécutée.

**Étape 4 — le core avale l'erreur.**
L'autoloader `jeedomAutoload()` (`core/php/core.inc.php:66-72`) enveloppe le chargement dans
`try { ... } catch (Throwable $e) { log::add('plugin', 'error', ...) }`. Or en PHP 8 un `require_once`
en échec lève une `Error` **rattrapable** (vérifié), et la `RuntimeException` de `platform_check.php`
l'est également. L'échec part donc dans le log `plugin` et l'exécution continue, classe non déclarée.

**Étape 5 — le message trompeur.**
Au rendu du dashboard, `cmd::widgetPossibility()` (`core/class/cmd.class.php:2958`) exécute
`new ReflectionClass($this->getEqType_name())`, d'où :

```
Class netatmoPublicData does not exist
```

Détail corroborant : PHP 8 formate ce message avec des guillemets (`Class "netatmoPublicData" does
not exist`), PHP 7.4 sans. La formulation rapportée par les utilisateurs est celle de PHP 7.4 —
exactement la population où l'installation des dépendances échouait.

### Correctifs appliqués

- `composer.json` déclare `"php": ">=7.4"` et épingle `config.platform.php = "7.4"`, sur le modèle du
  core. Composer ne peut plus verrouiller une dépendance hors plage.
- `composer.lock` et `vendor/` régénérés ensemble sous cette plateforme :
  `symfony/deprecation-contracts` redescend en `v2.5.4` (PHP 7.1+), Guzzle passe en 7.15.5,
  `league/oauth2-client` en 2.9.0. Garde d'autoload ramenée à `>= 7.4.0`.
- Le chargement du `vendor/` embarqué est précédé d'un `file_exists()` et, à défaut, d'un message
  d'erreur explicite dans le log du plugin. La classe est déclarée dans tous les cas : en cas de
  dépendances manquantes, l'utilisateur lit la vraie cause au lieu de « Class ... does not exist ».

## 2. Trois `TypeError` fatales en PHP 8

PHP 8.0 a transformé en erreur fatale des coercitions qui n'étaient qu'un avertissement en 7.4.
Aucune n'est détectée par l'analyse statique. Toutes sont atteignables en usage normal, et toutes
ont été reproduites avant correctif sur un banc d'essai avec core Jeedom simulé.

| Emplacement | Déclencheur | Avant | Après |
|---|---|---|---|
| `class.php` `getNetatmoData()` | `npd_expires_at` absent (installation neuve, bouton « Débrancher ») | `TypeError: Unsupported operand types: int - string` | sortie propre |
| `class.php` `updateValues()` | station sans clé `modules`, ou retirée des favoris | `TypeError: array_column(): Argument #1 must be of type array, null given` | sortie propre |
| `desktop/php/netatmoPublicData.php` | `parentNumber` non renseigné | `TypeError: str_repeat(): Argument #2 must be of type int, string given` | sortie propre |

Cause commune aux deux premières : `config::byKey()` et `getConfiguration()` renvoient `''`, jamais
`null`. Le garde `is_null($npd_expires_at)` en place ne pouvait donc jamais être vrai. Les correctifs
posent des casts et des gardes explicites.

## 3. Fonction imbriquée redéclarée

`performRequestWithToken()` était déclarée dans le corps de `getNetatmoData()`, donc dans l'espace
global : le second appel dans la même requête levait `Cannot redeclare performRequestWithToken()`,
sur toutes les versions de PHP. Le cas était atteignable dès le premier échec de l'API.

Elle devient une méthode statique privée. La relance après un 403 récupère au passage son `return`
manquant — le token renouvelé était obtenu puis jeté — et se limite à un seul essai.

## 4. User-Agent : sortie de `vendor/`

Le commit `75ef4d2` modifiait à la main `vendor/guzzlehttp/guzzle/src/Utils.php` pour forcer le
User-Agent et contourner « Undefined class constant MAJOR_VERSION ». Ce patch était condamné : le
`rm -rf vendor` de l'étape 1 l'efface à chaque installation des dépendances.

Le User-Agent est désormais fixé côté plugin, dans `netatmoPublicData::getHttpClient()`, appliqué aux
deux clients HTTP. Le comportement survit aux réinstallations et n'appelle plus
`Utils::defaultUserAgent()`.

## 5. Avertissements PHP 8 corrigés

- `$new_equipment` n'était jamais réinitialisé entre deux itérations : les équipements existants
  héritaient du dimensionnement de widget d'une station créée plus tôt dans la boucle.
- `$netatmoAuthorizationUrl` n'était défini que dans une branche conditionnelle, alors que le bloc
  `<script>` de la page de configuration le lit systématiquement.
- `self::$_moduleType['NAMain']` : clé inexistante, atteinte par la commande `pressure`.
- `foreach (self::$_netatmoData['devices'])` sans garde alors que `getNetatmoData()` peut renvoyer
  `false`.
- `$content_array['state']` sur un `json_decode()` pouvant renvoyer `null`.

## 6. Vérifications

- `php -l` sur les cinq fichiers PHP : aucune erreur.
- PHPCompatibility (`testVersion 7.4-8.3`) sur le code du plugin : 0 signalement.
- PHPCompatibility sur le nouveau `vendor/` : seuls des signalements sur `symfony/polyfill-php80`,
  faux positifs par construction — `bootstrap.php` sort immédiatement si `PHP_VERSION_ID >= 80000` et
  chaque fonction est gardée par `function_exists()` ; les stubs `Attribute` et `PhpToken` sont en
  `classmap`, donc jamais chargés sur PHP 7.4.
- Banc d'essai avec core Jeedom simulé, cinq scénarios de panne : tous fataux ou en avertissement
  avant correctif, tous propres après.
- `composer.lock` et `vendor/composer/installed.json` alignés paquet par paquet.

## 7. Point de vigilance restant

`vendor/` reste versionné alors que Jeedom le supprime et le reconstruit. Les deux arbres doivent
donc rester synchronisés : toute mise à jour de `composer.lock` — les montées Dependabot en
particulier — doit s'accompagner d'une régénération de `vendor/` dans le même commit. C'est
précisément ce qui avait dérivé (lock en Guzzle 7.15.2, `vendor/` en 7.7.0).

Deux options pour supprimer le risque à la source : un workflow GitHub Actions qui régénère et
commite `vendor/` sur les PR Dependabot, ou l'abandon du `vendor/` versionné maintenant que
`packages.json` couvre l'installation. Le dépôt n'a aujourd'hui aucun répertoire `.github/`.

Note annexe : `CLAUDE.md` documente un répertoire `3rdparty/Netatmo-API-PHP/` absent du dépôt, et
`.gitmodules` est vide.
