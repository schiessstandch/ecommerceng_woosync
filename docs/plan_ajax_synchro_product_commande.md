# Plan : Synchro AJAX paginée pour Produits et Commandes

## Contexte

La synchro des tiers (societe) a été convertie en AJAX paginé page par page pour éviter les timeouts PHP. Il faut appliquer le même pattern aux produits et commandes.

## Fichiers à modifier

| Fichier | Rôle |
|---------|------|
| `js/synchro_ajax.js` | Généraliser le JS (actuellement hardcodé societe) |
| `class/data/eCommerceRemoteAccess.class.php` | Proxy — passer `$page` |
| `class/data/woocommerce/eCommerceRemoteAccessWoocommerce.class.php` | Ajouter `$page` + chunking à `convertRemoteObjectIntoDolibarrProduct()` et `convertRemoteObjectIntoDolibarrCommande()` |
| `class/business/eCommerceSynchro.class.php` | Ajouter `$page` à `synchProduct()`, `synchronizeProducts()`, `synchCommande()`, `synchronizeOrders()` |
| `tpl/site.tpl.php` | Boutons AJAX + progress UI pour produits/commandes |
| `langs/en_US/ecommerce.lang` | Clés de traduction AJAX produits/commandes |
| `langs/fr_FR/ecommerce.lang` | Idem FR |

## Fichiers à créer

| Fichier | Rôle |
|---------|------|
| `ajax/synchro_product.php` | Endpoint AJAX produits (copie de synchro_societe.php adaptée) |
| `ajax/synchro_commande.php` | Endpoint AJAX commandes (copie de synchro_societe.php adaptée) |

## Étapes d'implémentation

### Étape 1 : Généraliser `synchro_ajax.js`

Transformer le singleton `EcommerceSynchroAjax` en constructeur `EcommerceSynchroAjaxInstance(elementType, config)` :
- Tous les sélecteurs jQuery deviennent dynamiques : `#synchro_` + elementType + `_progress`, etc.
- `startSociete()` → `start()` générique
- La collecte des options du formulaire passe par `config.collectOptions(form)` (callback)
- Les POST data incluent `this.options` directement (chaque entité envoie ses propres champs)
- Wrapper backward-compat : `var EcommerceSynchroAjax = synchroSociete`

### Étape 2 : Remote data layer — Produits

**`convertRemoteObjectIntoDolibarrProduct()`** — ajouter `$page = 0` :
- Chunking sur `$include_ids` (IDs parents uniques), pas sur `$remoteObject` brut
- Quand `$page > 0` : mapper page AJAX → chunk index, garder les `$include_variation_ids` du chunk
- Retour structuré : `['products' => [...], 'total' => N, 'fetched' => N, 'has_more' => bool]`
- Quand `$page == 0` : comportement inchangé (retourne tableau simple)

**Attention produits-spécifique** : les IDs peuvent être `product_id|variation_id`. Le chunking doit regrouper par parent pour que les variations soient complètes dans chaque chunk.

**`eCommerceRemoteAccess.class.php`** : passer `$page` dans le proxy.

### Étape 3 : Business layer — Produits

**`synchProduct()`** : ajouter `$page = 0`, passer à `synchronizeProducts()`

**`synchronizeProducts()`** : ajouter `$page = 0`
- Quand `$page > 0` : itérer sur `$products_data['products']`, collecter erreurs/succès, retourner `['total', 'fetched', 'success', 'errors', 'warnings', 'has_more']`
- Quand `$page == 0` : comportement inchangé

### Étape 4 : Endpoint AJAX — `ajax/synchro_product.php`

Même structure que `synchro_societe.php`. Différences :
- Paramètres : `product_remote_ids`, `force_update`, `to_nb` (pas de `only_linked`)
- Appel : `$synchro->synchProduct($remote_ids, $effective_to_nb, !empty($force_update), $page)`
- `dol_html_entity_decode()` sur les messages d'erreur (comme fait pour societe)

### Étape 5 : Remote data layer — Commandes

**`convertRemoteObjectIntoDolibarrCommande()`** — ajouter `$page = 0` :
- Même pattern que societe (pas de variations, plus simple)
- Chunking des `$remoteObject`, mapping page → chunk
- Retour structuré : `['orders' => [...], 'total' => N, 'fetched' => N, 'has_more' => bool]`

**`eCommerceRemoteAccess.class.php`** : passer `$page` dans le proxy.

### Étape 6 : Business layer — Commandes

**`synchCommande()`** : ajouter `$page = 0`, passer à `synchronizeOrders()`

**`synchronizeOrders()`** : ajouter `$page = 0`
- Quand `$page > 0` : même pattern que produits, appelle `synchronizeOrder()` par élément
- Quand `$page == 0` : inchangé

### Étape 7 : Endpoint AJAX — `ajax/synchro_commande.php`

Différences vs societe :
- Paramètres : `order_remote_ids`, `dont_synchronize_products`, `to_nb` (pas de `only_linked`)
- Appel : `$synchro->synchCommande($remote_ids, $effective_to_nb, !empty($dont_synchronize_products), $page)`

### Étape 8 : Template `site.tpl.php`

- **Boutons** : pattern conditionnel `ECOMMERCENG_DISABLE_AJAX_SYNC` + `$conf->use_javascript_ajax` pour produits et commandes
- **Progress UI** : ajouter divs `synchro_product_*` et `synchro_commande_*` (même structure HTML que societe)
- **JS init** : 3 instances `createSynchroAjaxInstance('societe'|'product'|'commande', {...})` avec les bindings click/cancel

### Étape 9 : Fichiers de langue

Ajouter les clés AJAX spécifiques produits/commandes (`ECommerceSynchroAjaxProcessingProduct`, `ECommerceSynchroAjaxProcessingCommande`, etc.)

## Points d'attention

1. **Variations produits** : chunker sur les IDs parents, pas sur le `$remoteObject` brut. Inclure toutes les variations du parent dans le même chunk.
2. **Backward-compat** : `$page = 0` par défaut → les appelants existants (cron, webhooks, site.php classique) ne changent pas.
3. **`$toNb` en mode page** : géré côté endpoint AJAX via `already_processed`, pas dans la couche remote.
4. **Encodage** : utiliser `transnoentitiesnoconv()` et `dol_html_entity_decode()` dans les endpoints AJAX (comme fait pour societe).

## Vérification

- Tester synchro produits AJAX : lancer, vérifier progression page par page, annuler
- Tester synchro commandes AJAX : idem
- Tester synchro societe : vérifier que la refacto JS n'a rien cassé
- Tester fallback classique (submit) avec `ECOMMERCENG_DISABLE_AJAX_SYNC=1`
- Tester avec `to_nb` limité
- Tester avec des remote_ids spécifiques (produits avec variations)