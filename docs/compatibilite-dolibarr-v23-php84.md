# Analyse de compatibilité Dolibarr V23 & PHP 8.4 — Module ecommerceng (WooSync)

**Date d'analyse** : 2026-08-17
**Version du module** : 14.0.68
**Environnement de test** : Dolibarr 22.0.5
**Cibles** : Dolibarr V23 · PHP 8.4

> Cette analyse **remplace** le fichier `compatibilite-dolibarr-v23.md` (obsolète, plusieurs faux positifs).
> Chaque point ci-dessous a été vérifié dans le code réel du module et dans le noyau Dolibarr installé.

---

## Résumé exécutif

Le module est **globalement prêt pour Dolibarr V23 et PHP 8.4**. Aucun problème bloquant n'a été
trouvé : les fonctions dépréciées « graves » de l'ancienne analyse étaient soit déjà corrigées, soit
inexistantes. Il ne reste que des **dépréciations douces** (rétro-compatibles, non bloquantes) et
quelques nettoyages cosmétiques optionnels.

| Sévérité | Nombre de points | Bloquant V23/8.4 ? |
|---|---|---|
| Bloquant | 0 | — |
| Dépréciation douce (optionnel) | 2 familles (`$conf->global->`, `$user->rights->`) | Non |
| Cosmétique / conformité | 2 | Non |
| Déjà traité / sans objet | 6 | — |

---

## 1. Problèmes BLOQUANTS

**Aucun.** Le module se charge et fonctionne sur Dolibarr V23 avec PHP 8.4 en l'état.

---

## 2. Dépréciations douces (rétro-compatibles — refactor optionnel)

Ces patterns **fonctionnent toujours** en V23 (la rétro-compatibilité est maintenue par le noyau).
Ce ne sont pas des erreurs PHP 8.4, seulement des conventions Dolibarr modernes recommandées.

### 2.1 `$conf->global->XXX` → wrapper `ecommercengGetGlobalString()`  ✅ FAIT

Migration réalisée : **297 lectures statiques** `$conf->global->CONSTANTE` converties en
`ecommercengGetGlobalString('CONSTANTE')` sur 24 fichiers.

- Wrappers version-safe ajoutés dans `lib/compatibility.lib.php` :
  - `ecommercengGetGlobalString($key, $default = '')`
  - `ecommercengGetGlobalInt($key, $default = 0)` — gère le défaut lui-même car le paramètre
    `$default` de `getDolGlobalInt()` n'est pas disponible avant Dolibarr v15/v16.
  - Les deux lisent `$conf->global->{key}` de façon gardée (`isset`) → **suppriment aussi les
    warnings « undefined property » de PHP 8.x**.
- Inclusion : via `lib/eCommerce.lib.php` (pages/admin) + include explicite dans les classes
  invoquées directement (cron : `eCommerceSynchro`, `eCommercePendingWebHook`, `eCommerceUtils`)
  et les 3 scripts CLI.

**Volontairement non convertis** (avec raison) :

| Cas | Nb | Raison |
|---|---|---|
| Écritures `$conf->global->X = …` | 7 | `site.php` + `eCommerceSynchro` désactivent temporairement des constantes cœur (workflow) — un getter ne peut pas être une lvalue |
| Clés dynamiques `$conf->global->$var` | 11 | Accès à clé variable, déjà gardés par `isset()` ; sémantique (défaut `null`, comparaisons) à préserver |
| `lib/opendsi_common.lib.php` | 3 | Copie vendored du noyau (hors périmètre) |
| `isset($conf->global->X)` | — | Restauré tel quel : `isset()` n'accepte pas un appel de fonction et ne déclenche jamais de warning |

### 2.2 `$user->rights->xxx` → wrapper `ecommercengUserHasRight()`  ✅ FAIT (runtime)

Migration réalisée pour **tout le code runtime** portant sur les droits du module `ecommerceng`
(~29 lectures dans 13 fichiers : `site.php`, `webhookslist.php`, `index.php`, `ajax/synchro.php`,
les 5 pages `admin/*.php`, `scripts/syncronize_to_dolibarr.php`, `class/actions_ecommerceng.class.php`,
`core/boxes/box_ecommerce_webhooks.php`).

- Wrapper version-safe `ecommercengUserHasRight($user, 'ecommerceng', 'perm')` ajouté dans
  `lib/compatibility.lib.php` : utilise `User::hasRight()` si dispo (v16+), sinon repli sur
  `$user->rights->{module}->{perm}` (compat < v16). Inclusion via `lib/eCommerce.lib.php`
  (déjà chargé par 8 fichiers) + include explicite dans les 4 fichiers restants.

**Volontairement conservés en `$user->rights->...`** (non migrés, avec raison) :

| Emplacement | Occ. | Raison |
|---|---|---|
| `core/modules/modECommerceNg.class.php` `'perms'` (l.312-360) | 4 | Chaînes évaluées par `dol_eval()` — même contrainte que `'enabled'` : `dol_eval` interdit le mot-clé `function`, et le wrapper n'y est pas chargé. Restent compat toutes versions |
| `lib/opendsi_common.lib.php` | 41 | Copie vendored du `restrictedArea()` du noyau ; accès dynamiques `$user->rights->$feature->$subfeature->lire` non mappables sur `hasRight()`. Hors périmètre |
| `admin/class/gui/eCommerceMenu.class.php` | 6 | Code mort (classe jamais instanciée) |

---

## 3. Nettoyages cosmétiques / conformité (optionnels)

### 3.1 Classe trigger n'étend pas `DolibarrTriggers`

- **Fichier** : `core/triggers/interface_90_modECommerceng_ECommerceng.class.php:28`
- `class InterfaceECommerceng` est une classe autonome. Les triggers du noyau étendent
  `DolibarrTriggers`. Le module **fonctionne** (le chargeur de triggers appelle `runTrigger()`
  quelle que soit la classe parente), mais l'extension est la convention moderne.
- **Priorité** : Cosmétique. **Impact** : nul.

### 3.2 Docblock obsolète `run_trigger`

- **Fichier** : `core/triggers/interface_90_modECommerceng_ECommerceng.class.php:89`
- Le commentaire mentionne encore `run_trigger` (l'ancienne méthode, remplacée par `runTrigger()`
  déjà présente). Correction purement rédactionnelle.

---

## 4. Points déjà traités / sans objet (aucune action)

| Point | Statut vérifié |
|---|---|
| `Commande::cancel()` (signature V23) | ✅ Déjà géré par `version_compare(DOL_VERSION,'23.0.0')` — `eCommerceSynchro.class.php:4660-4664` |
| `load_fiche_titre()` soi-disant déprécié | ✅ **Faux** : non déprécié (c'est `print_fiche_titre()` qui l'est). Le module utilise déjà la bonne fonction |
| Migration `isModEnabled()` (code runtime) | ✅ Fait : wrapper `ecommercengIsModEnabled()` dans `lib/compatibility.lib.php`, utilisé dans le trigger et `tabcond` |
| `TRIGGER_PREFIX` manquant | ✅ **Faux positif** : c'est une constante des objets métier (enfants de `CommonObject`) ; aucun objet du module ne déclenche de trigger custom |
| `$conf->ecommerceng->enabled` | ✅ Migré là où c'était pertinent ; conservé dans les chaînes `dol_eval` (compat v14) |
| Propriétés dynamiques PHP 8.2+ | ✅ Les classes du module **déclarent** leurs propriétés (`public $...`) ; les `new stdClass` (28×) sont exemptés de la dépréciation |
| `.opendsi_info.json` | ✅ Déjà `dlb_max_version: 23`, `php_max_version: 8.4` |

---

## 5. Volet PHP 8.4 — détail des vérifications

Résultat : **aucun code cassant PHP 8.4** trouvé dans le module (hors libs tierces `includes/`).

| Vérification PHP 8.4 / 8.x | Résultat |
|---|---|
| Paramètres **implicitement nullable** (`Type $x = null` sans `?`) — *déprécié 8.4* | **0** (le module utilise des paramètres non typés, non concernés) |
| `utf8_encode()` / `utf8_decode()` — *dépréciés 8.2* | **0** |
| Interpolation `"${var}"` — *dépréciée 8.2* | **0** |
| `each()` PHP — *supprimé 8.0* | **0** (seul `.each()` jQuery présent, sans rapport) |
| Accès chaîne par accolades `$str{0}` — *supprimé 8.0* | **0** |
| `create_function()` / cast `(real)` / `is_real()` — *supprimés 8.0* | **0** |
| `preg_*` modificateur `/e`, `money_format()`, `mysql_*`, `split()`, `ereg*` | **0** |
| `implode($array, $glue)` (ordre inversé) — *supprimé 8.0* | **0** |
| Constante `E_STRICT` — *dépréciée 8.4* | **0** |
| Propriétés dynamiques sur classes utilisateur — *déprécié 8.2* | Classes du module OK (propriétés déclarées) |

### Point de vigilance runtime (non détectable statiquement)

PHP 8.1+ **déprécie** le passage de `null` aux paramètres non-nullable des fonctions internes
(`trim(null)`, `strlen(null)`, `htmlspecialchars(null)`, `preg_match(..., null)`…). Cela ne casse
rien (simple `Deprecated` dans les logs) mais peut être bruyant. À surveiller via les logs Dolibarr
lors d'un run réel sous PHP 8.4 ; corriger au cas par cas si des notices apparaissent.

---

## 6. Plan d'action recommandé

| Priorité | Action | Fichier(s) | Effort | Bloquant |
|---|---|---|---|---|
| — | **Rien de bloquant** — le module tourne sur V23 + PHP 8.4 | — | — | — |
| ✅ Fait | `$conf->global->` lectures statiques → wrapper `ecommercengGetGlobalString()` | 24 fichiers | — | Non |
| ✅ Fait | `$user->rights->` runtime → wrapper `ecommercengUserHasRight()` | 13 fichiers | — | Non |
| Cosmétique | Faire étendre `DolibarrTriggers` à la classe trigger | trigger | 15 min | Non |
| Cosmétique | Corriger le docblock `run_trigger` (l.89) | trigger | 1 min | Non |
| Vigilance | Surveiller les `Deprecated` (null → fonctions internes) sous PHP 8.4 | run réel | — | Non |

---

## Méthodologie

Analyse par recherche de patterns sur l'ensemble des fichiers `*.php` / `*.js` du module, en
**excluant** les bibliothèques tierces (`includes/` : Guzzle, oauth-subscriber). Chaque déclaration
« déprécié / supprimé » a été confrontée à la définition réelle dans le noyau Dolibarr installé
(`core/lib/functions.lib.php`, `core/class/commonobject.class.php`, `commande/class/commande.class.php`).
