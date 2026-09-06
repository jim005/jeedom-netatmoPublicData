# Compatibilité PHP 7.4 → 8.3 et « Class netatmoPublicData does not exist »

Cible de compatibilité retenue : **Jeedom 4.6 minimum**, **PHP 7.4 à 8.3**.

Ces deux contraintes sont cohérentes avec le core : le `composer.json` de Jeedom 4.6.2 déclare
`"php": ">=7.4"` et épingle `config.platform.php = "7.4"`. Un Jeedom 4.6 peut donc tourner sous
PHP 7.4 comme sous PHP 8.3, et le plugin doit couvrir toute la plage.

Le socle Jeedom est désormais appliqué : `plugin_info/info.json` déclare `"require": "4.6"`. Jeedom
lit ce champ dans `plugin::setIsEnable()` et refuse l'activation du plugin sur un core plus ancien,
avec un message explicite. Les installations en Jeedom 4.2 à 4.5 ne sont pas désactivées
rétroactivement, mais ne pourront plus réactiver le plugin après cette mise à jour.

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

### Confirmation par les logs d'installation d'un utilisateur

Les logs remontés le 5 septembre 2026 (Jeedom, PHP 7.4.33, Composer 2.10.3) valident la chaîne
étape par étape :

```
+ cd /var/www/html/.../plugins/netatmoPublicData
+ rm -rf vendor
+ sudo composer install --no-ansi --no-dev --no-interaction ...
Verifying lock file contents can be installed on current platform.
Your lock file does not contain a compatible set of packages. Please run composer update.
Problem 1
- symfony/deprecation-contracts is locked to version v3.7.1 ...
- symfony/deprecation-contracts v3.7.1 requires php >=8.1 -> your php version (7.4.33) does not satisfy that requirement.
Problem 2
- guzzlehttp/psr7 2.13.0 requires symfony/deprecation-contracts ^2.5 || ^3.0 ...
+ php .../jeecli.php plugin dependancy_end netatmoPublicData
PHP Warning: require_once(.../core/class/../../vendor/autoload.php): failed to open stream:
             No such file or directory in .../core/class/netatmoPublicData.class.php on line 25
```

Le `chown` qui suit l'installation liste `3rdparty CLAUDE.md LICENSE README.md composer.json
composer.lock core desktop docs notesDev.md plugin_info` — sans `vendor`, qui n'a effectivement pas
été recréé.

Cette panne a été reproduite à l'identique en forçant la plateforme à PHP 7.4.33 : mêmes Problem 1
et Problem 2, mêmes paquets. Avec le `composer.lock` corrigé, dans les mêmes conditions,
l'installation des dix paquets est vérifiée sans erreur.

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

## 7. Garde-fou d'intégrité des dépendances

`vendor/` reste versionné alors que Jeedom le supprime et le reconstruit. Les deux arbres doivent
donc rester synchronisés : toute mise à jour de `composer.lock` — les montées Dependabot en
particulier — doit s'accompagner d'une régénération de `vendor/`. C'est précisément ce qui avait
dérivé, et Dependabot ne touche jamais au `vendor/`.

Le workflow `.github/workflows/composer-integrity.yml` ferme cette porte. Il se déclenche sur toute
PR ou tout push touchant `composer.json`, `composer.lock` ou `vendor/`.

**`compatibilite`** — matrice PHP 7.4 et 8.3, les deux bornes annoncées dans `plugin_info/info.json`.
Sur chaque version : `composer validate` (qui contrôle aussi la fraîcheur du lock vis-à-vis de
`composer.json`), puis `composer install --dry-run`, qui échoue si le lock verrouille un paquet
incompatible avec ce PHP. C'est exactement le contrôle qui aurait arrêté la panne : sous PHP 7.4,
`symfony/deprecation-contracts v3.7.1` exige `php >= 8.1`. S'ajoute un `php -l` sur les sources du
plugin, sur les deux versions.

**`vendor-aligne`** — `.github/scripts/check-vendor-lock.php` compare `vendor/composer/installed.json`
à `composer.lock`, paquet par paquet. La comparaison porte sur les noms et versions, pas sur le
contenu des fichiers : selon que Composer installe depuis une archive dist ou depuis les sources,
les arbres diffèrent légitimement, et un diff octet-à-octet produirait de faux positifs.

Ce contrôle a été éprouvé sur l'état réel de `beta` avant correctif. Il remonte les sept écarts,
dont celui à l'origine de la panne :

```
guzzlehttp/guzzle                  vendor/ : 7.7.0    lock : 7.15.2
symfony/deprecation-contracts      vendor/ : v2.5.2   lock : v3.7.1
symfony/polyfill-php80             absent de vendor/ (lock : v1.37.0)
...
✗ 7 écart(s) détecté(s).
```

**`synchroniser`** — déclenchement manuel (`workflow_dispatch`), au choix en mode `install`
(matérialise `vendor/` depuis le lock existant) ou `update` (résout à nouveau). Régénère et commite
`vendor/` et `composer.lock` ensemble. Le déclenchement est manuel à dessein : les workflows lancés
par Dependabot reçoivent un jeton en lecture seule et ne peuvent pas pousser de commit.

### Effet attendu au premier lancement de `synchroniser`

Le `vendor/` actuellement versionné a été régénéré depuis des clones **source**, faute d'accès aux
archives dist au moment du correctif. Il embarque donc les suites de tests, les docs et les
répertoires `.github/` des dépendances — que les paquets excluent pourtant en dist via
`export-ignore`. Un `composer install` normal produira un arbre sensiblement plus léger que les
4,3 Mo actuels.

C'est un gain — le paquet livré aux Jeedom des utilisateurs s'allège d'autant — mais cela produira un
diff volumineux. Le contrôle `vendor-aligne` n'en est pas affecté, puisqu'il ne compare que les
versions.

## 8. Observations annexes

Un répertoire `3rdparty` est présent sur les installations des utilisateurs alors qu'il est absent du
dépôt et que `.gitmodules` est vide : reliquat d'une version antérieure, que `CLAUDE.md` documente
encore. Et `CLAUDE.md`, `notesDev.md` comme ce rapport sont livrés en production chez les
utilisateurs alors qu'ils sont internes — un `.gitattributes` avec `export-ignore` les écarterait du
paquet.
