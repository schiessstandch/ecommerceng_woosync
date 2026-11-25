# Analyse de compatibilité Dolibarr V23 — Module ecommerceng (WooSync)

**Date d'analyse** : 2026-07-23  
**Version du module** : 14.0.61  
**Compatibilité déclarée** : Dolibarr V14 à V21  
**Cible** : Dolibarr V23 (sortie le 28 février 2026)

---

## Résumé exécutif

Le module ecommerceng nécessite **plusieurs corrections** pour être compatible avec Dolibarr V23. Un problème critique de signature de méthode peut provoquer des erreurs silencieuses lors de l'annulation de commandes. Plusieurs fonctions dépréciées sont utilisées dans les pages d'administration.

---

## Problème CRITIQUE

### `Commande::cancel()` — signature modifiée en V23

| Attribut | Valeur |
|---|---|
| **Fichier** | `class/business/eCommerceSynchro.class.php:4429` |
| **Priorité** | Critique |
| **Impact** | Annulation de commande silencieusement incorrecte |

**Situation actuelle :**
```php
$result = $order->cancel($warehouse_id);
```

**Requis en V23 :**
```php
$result = $order->cancel($user, $warehouse_id);
```

En V23, `$user` devient le premier paramètre obligatoire de `Commande::cancel()`. Sans cette correction, `$warehouse_id` est interprété comme `$user`, ce qui provoque un comportement incorrect sans erreur explicite.

---

## Problèmes ÉLEVÉS

### `load_fiche_titre()` dépréciée

| Attribut | Valeur |
|---|---|
| **Priorité** | Élevée |
| **Impact** | Rendu des en-têtes de page potentiellement cassé |

Cette fonction est dépréciée depuis Dolibarr V17. Elle est utilisée dans plus de 10 fichiers :

- `admin/setup.php` (lignes 316, 338, 452, 580)
- `admin/about.php` (ligne 31)
- `admin/thirdparty.php` (lignes 31, 48)
- `admin/product.php` (lignes 35, 51, 177)
- `admin/stock.php` (lignes 31, 49, 116)
- `admin/order.php` (lignes 33, 54, 111)
- `admin/changelog.php` (ligne 31)
- `admin/tpl/extrafields.tpl.php` (multiple)
- `tpl/index.tpl.php` (ligne 69)
- `tpl/site.tpl.php` (ligne 39)

**Remplacement attendu :** `print_fiche_titre()` ou intégration via `dol_get_fiche_head()`.

### Compatibilité maximale non déclarée

| Attribut | Valeur |
|---|---|
| **Fichier** | `.opendsi_info.json` |
| **Priorité** | Élevée |
| **Impact** | Blocage de l'installation sur Dolibarr V23 |

**Situation actuelle :**
```json
{
    "dlb_min_version": 14,
    "dlb_max_version": 21,
    "php_min_version": 7.0,
    "php_max_version": 8.2
}
```

**Correction :**
```json
{
    "dlb_min_version": 14,
    "dlb_max_version": 23,
    "php_min_version": 7.0,
    "php_max_version": 8.2
}
```

---

## Problèmes MOYENS

### `TRIGGER_PREFIX` manquant dans la classe trigger

| Attribut | Valeur |
|---|---|
| **Fichier** | `core/triggers/interface_90_modECommerceng_ECommerceng.class.php` |
| **Priorité** | Moyenne |
| **Impact** | Non-conformité au système de triggers V23 |

V23 introduit une propriété `$this->TRIGGER_PREFIX` obligatoire pour le namespacing des triggers. Elle est absente de la classe `InterfaceECommerceng`.

Le commentaire docblock (ligne 89) mentionne encore l'ancienne méthode `run_trigger()` (supprimée en V23) — à corriger même si la méthode effective `runTrigger()` est correcte.

**Correction :**
```php
public $TRIGGER_PREFIX = 'ECOMMERCENG';
```

### Propriétés dynamiques PHP 8.2+

| Attribut | Valeur |
|---|---|
| **Fichiers** | `core/modules/modECommerceNg.class.php:154`, `class/data/eCommerceSite.class.php` |
| **Priorité** | Moyenne |
| **Impact** | Avertissements en mode strict PHP 8.2 |

L'assignation de propriétés dynamiques sur `stdClass` sans attribut `#[AllowDynamicProperties]` est dépréciée en PHP 8.2 :

```php
$conf->ecommerceng = new stdClass();
$conf->ecommerceng->enabled = 0;
```

**Correction recommandée :** ajouter une vérification d'existence ou utiliser un objet typé.

---

## Problèmes FAIBLES (à planifier)

| Problème | Fichiers concernés | Statut en V23 |
|---|---|---|
| `$conf->ecommerceng->enabled` → `isModEnabled('ecommerceng')` | 22 occurrences | Fonctionne encore, déprécié |
| Vérifier l'extension du fichier icône `opendsi_big@ecommerceng` | `core/modules/modECommerceNg.class.php:78` | Requis si fichier PNG/SVG |
| Commentaire `run_trigger` obsolète dans le docblock trigger | `core/triggers/…:89` | Cosmétique |

---

## Points conformes — aucune action requise

| Point | Statut |
|---|---|
| `getDolGlobalString()` / `getDolGlobalInt()` | Correct (15 occurrences) |
| Gestion CSRF token (`ecommercengNewToken()`) | Déjà compatible V17+ |
| Méthode `runTrigger()` | Signature correcte |
| Hook `doActions()` | Signature correcte |
| jQuery | Pas de méthodes dépréciées |
| `$user->rights->` | Aucune occurrence — RAS |
| `$conf->global->` | Aucune occurrence — RAS |
| `$sumpayed` / `$sumdeposit` / `$sumcreditnote` (Facture) | Aucune occurrence — RAS |
| `img_pdf()` | Aucune occurrence — RAS |
| `timepicker.js` | Aucune occurrence — RAS |

---

## Plan d'action

| Priorité | Action | Fichier(s) | Effort estimé |
|---|---|---|---|
| Critique | Corriger `$order->cancel($user, $warehouse_id)` | `eCommerceSynchro.class.php:4429` | 5 min |
| Élevée | Mettre à jour `dlb_max_version` à `23` | `.opendsi_info.json` | 1 min |
| Élevée | Remplacer les appels `load_fiche_titre()` | 10+ fichiers admin | 2–3h |
| Moyenne | Ajouter `$this->TRIGGER_PREFIX` | trigger class | 10 min |
| Moyenne | Corriger propriétés dynamiques PHP 8.2 | 2 fichiers | 30 min |
| Faible | Migrer `$conf->xxx->enabled` → `isModEnabled()` | 22 occurrences | 1–2h |
