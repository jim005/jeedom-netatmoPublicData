# Solutions alternatives pour le problème MAJOR_VERSION

## Contexte
Certaines installations Jeedom rencontrent l'erreur : `Undefined class constant 'MAJOR_VERSION'`
Cette erreur provient de `vendor/guzzlehttp/guzzle/src/Utils.php:117`

## Solution 1 : Composer Patches (✅ RECOMMANDÉ - DÉJÀ IMPLÉMENTÉ)

Le patch est automatiquement appliqué via composer-patches.

**Commandes à exécuter :**
```bash
composer install
# ou pour réappliquer les patches
composer update --lock
```

**Avantages :**
- Patch appliqué automatiquement à chaque installation
- Solution propre et maintenable
- Documenté dans composer.json

**Inconvénients :**
- Nécessite le package cweagans/composer-patches

---

## Solution 2 : Définir User-Agent au niveau du Client Guzzle

Modifier directement dans le code du plugin pour définir le User-Agent.

**Dans `core/class/netatmoPublicData.class.php` ligne 114 :**

```php
// AVANT
$client = new GuzzleHttp\Client();

// APRÈS
$client = new GuzzleHttp\Client([
    'headers' => [
        'User-Agent' => 'GuzzleHttp/7 Jeedom/4'
    ]
]);
```

**Faire de même ligne 157 :**
```php
$client = new GuzzleHttp\Client([
    'headers' => [
        'User-Agent' => 'GuzzleHttp/7 Jeedom/4'
    ]
]);
```

**Avantages :**
- Pas de modification du vendor
- Solution ciblée
- Fonctionne même si Guzzle est mis à jour

**Inconvénients :**
- Nécessite de modifier le code à 2 endroits
- Le User-Agent est défini en dur

---

## Solution 3 : Script post-install automatique

Créer un script qui applique le patch après composer install.

**Créer `scripts/post-install.php` :**

```php
<?php
$utilsFile = __DIR__ . '/../vendor/guzzlehttp/guzzle/src/Utils.php';

if (file_exists($utilsFile)) {
    $content = file_get_contents($utilsFile);

    // Vérifier si le patch n'est pas déjà appliqué
    if (strpos($content, 'GuzzleHttp/7 Jeedom/4') === false) {
        $content = str_replace(
            "return sprintf('GuzzleHttp/%d', ClientInterface::MAJOR_VERSION);",
            "// return sprintf('GuzzleHttp/%d', ClientInterface::MAJOR_VERSION);\n        return 'GuzzleHttp/7 Jeedom/4'; //  https://community.jeedom.com/t/erreur-sur-la-fonction-cron15-du-plugin-undefined-class-constant-major-version/146119?u=jim005",
            $content
        );
        file_put_contents($utilsFile, $content);
        echo "✓ Patch Guzzle User-Agent appliqué\n";
    } else {
        echo "✓ Patch Guzzle User-Agent déjà présent\n";
    }
}
?>
```

**Ajouter dans composer.json :**
```json
{
    "scripts": {
        "post-install-cmd": "php scripts/post-install.php",
        "post-update-cmd": "php scripts/post-install.php"
    }
}
```

**Avantages :**
- Automatique
- Pas de dépendance externe

**Inconvénients :**
- Plus fragile que composer-patches
- Nécessite de créer le script

---

## Solution 4 : Forcer une version spécifique de Guzzle

Forcer l'utilisation d'une version de Guzzle qui fonctionne correctement.

**Dans composer.json :**
```json
{
    "require": {
        "league/oauth2-client": "^2.7",
        "guzzlehttp/guzzle": "7.5.0"
    }
}
```

**Avantages :**
- Garantit une version stable

**Inconvénients :**
- Bloque les mises à jour de sécurité de Guzzle
- Ne résout pas le problème de fond

---

## Solution 5 : Créer un Wrapper pour Guzzle

Créer une classe qui encapsule la création du client Guzzle.

**Créer `core/class/GuzzleClientFactory.php` :**

```php
<?php

class GuzzleClientFactory
{
    public static function create(array $options = []): \GuzzleHttp\Client
    {
        // Ajouter le User-Agent par défaut
        if (!isset($options['headers'])) {
            $options['headers'] = [];
        }

        if (!isset($options['headers']['User-Agent'])) {
            $options['headers']['User-Agent'] = 'GuzzleHttp/7 Jeedom/4';
        }

        return new \GuzzleHttp\Client($options);
    }
}
```

**Puis utiliser :**
```php
$client = GuzzleClientFactory::create();
```

**Avantages :**
- Solution élégante et réutilisable
- Centralise la configuration
- Pas de modification du vendor

**Inconvénients :**
- Nécessite de changer tous les `new GuzzleHttp\Client()`

---

## Recommandation finale

**Utiliser la Solution 1 (Composer Patches)** car :
- ✅ Déjà implémentée
- ✅ Automatique et maintenable
- ✅ Standard dans l'écosystème PHP/Composer
- ✅ Documentée

**Alternative simple :** Solution 2 si composer-patches pose problème sur certaines installations Jeedom.
