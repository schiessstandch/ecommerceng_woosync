# Plan : Synchronisation des tiers en AJAX (WooCommerce -> Dolibarr)

## Objectif

Remplacer la synchronisation synchrone des tiers (submit form classique) par un traitement AJAX page par page, afin d'eviter les timeouts PHP sur les gros volumes, tout en conservant toutes les options actuelles.

---

## Analyse de l'existant

### Flux actuel (synchrone)

1. L'utilisateur clique sur "Synchroniser nouveaux Tiers" (`submit_synchro_societe`) dans `tpl/site.tpl.php`
2. Le formulaire POST recharge `site.php` qui execute `$synchro->synchSociete(...)` (ligne 167-169)
3. `synchSociete()` appelle `synchronizeCustomers()` qui :
   - Recupere **tous** les clients via l'API WooCommerce en paginant (`convertRemoteObjectIntoDolibarrSociete`)
   - Les traite un par un dans une boucle (`synchronizeCustomer`)
4. La page reste bloquee pendant toute la duree (timeout PHP a 3600s)
5. Les resultats/erreurs sont affiches au rechargement

### Contrainte API WooCommerce

L'endpoint `GET /customers` retourne **max 100 resultats par page** (`per_page` plafonne a 100, configurable via `ECOMMERCENG_MAXSIZE_MULTICALL`). Le header de reponse `X-WP-Total` indique le nombre total de clients correspondants.

La pagination actuelle dans `convertRemoteObjectIntoDolibarrSociete()` (ligne 570-594) boucle sur toutes les pages en une seule execution PHP -> c'est la source du timeout.

### Options actuelles a conserver

| Option | Source | Description |
|--------|--------|-------------|
| `from_date` | Champ texte (YYYYMMDDHHMMSS) | Date debut de la fenetre de synchro |
| `to_date` | Champ texte (YYYYMMDDHHMMSS) | Date fin de la fenetre de synchro |
| `company_remote_ids` | Textarea | IDs distants specifiques (virgule) |
| `force_update` | Checkbox | Forcer la MAJ meme si la date distante n'est pas plus recente |
| `to_nb` | Champ texte | Limiter le nombre d'enregistrements |
| `ECOMMERCENG_MAXRECORD_PERSYNC` | Config globale | Limite par defaut si `to_nb` vide |
| `ECOMMERCENG_MAXSIZE_MULTICALL` | Config globale | Taille de page API WooCommerce (max 100) |

### Nouvelle option ajoutee

| Option | Source | Description |
|--------|--------|-------------|
| `only_linked` | Checkbox | Synchroniser uniquement les tiers deja lies dans Dolibarr (table `llx_ecommerce_societe`) |

Cette option est generique et pourra etre reutilisee pour la synchro des produits (via `llx_ecommerce_product`) par la suite.

### Points d'ancrage existants

- `site.php` ligne 167 : verifie deja `GETPOST('submit_synchro_societe_ajax')`
- `site.php` lignes 294-298 : bloc AJAX prevu mais vide (`// Not used yet`)
- `synchronizeCustomer()` (ligne 2778) : traitement unitaire d'un client, reutilisable directement
- `convertRemoteObjectIntoDolibarrSociete()` (ligne 544) : accepte deja les filtres et la pagination

---

## Architecture proposee

### Principe : 1 appel AJAX = 1 page API WooCommerce

Chaque appel AJAX est autonome : il recupere une page de clients via l'API WooCommerce, les traite dans Dolibarr, et retourne le resultat. Pas de stockage intermediaire, pas de session.

```
[Navigateur]                              [Serveur ajax/synchro_societe.php]
    |                                              |
    |-- POST action=batch, page=1 ---------------->|
    |   {id, from_date, to_date,                   |
    |    company_remote_ids, force_update, to_nb}   |
    |                                              |-- Verif droits
    |                                              |-- Connect WooCommerce
    |                                              |-- GET /customers?page=1&per_page=100
    |                                              |-- Lit header X-WP-Total
    |                                              |-- synchronizeCustomer() x N
    |<-- JSON {total, page_count, processed,    ---|
    |          success, errors, has_more}           |
    |                                              |
    |-- Barre: page 1/N traite ------------------->|
    |                                              |
    |-- POST action=batch, page=2 ---------------->|
    |   ...                                        |
    |                                              |
    |-- (has_more=false) ------------------------->|
    |                                              |
    |-- POST action=finish ----------------------->|
    |   {id, total_success, total_errors}          |
    |                                              |-- MAJ site->last_update
    |<-- JSON {message} ---------------------------|
    |                                              |
    |-- Barre: 100% + Bilan --------------------->|
```

### Pourquoi c'est simple

- **Pas de phase "start"** : la premiere page retourne le `X-WP-Total`, le JS connait le total des la page 1
- **Pas de stockage temporaire** : chaque appel est independant (fetch API + traitement)
- **Pas de session** : les options sont renvoyees a chaque appel par le JS
- **Arret naturel** : quand l'API retourne moins de resultats que `per_page` -> `has_more=false`

---

## Fichiers a modifier / creer

### 1. `ajax/synchro_societe.php` (NOUVEAU)

Endpoint AJAX dedie.

#### `action=batch` - Traitement d'une page

```
Entree (POST) :
  - id                 : int    (site ID)
  - page               : int    (numero de page, commence a 1)
  - from_date          : string (YYYYMMDDHHMMSS, optionnel)
  - to_date            : string (YYYYMMDDHHMMSS, optionnel)
  - company_remote_ids : string (IDs separes par virgule, optionnel)
  - force_update       : int    (0|1)
  - only_linked        : int    (0|1, synchroniser uniquement les tiers deja lies)
  - to_nb              : int    (limite globale, 0 = sans limite)
  - already_processed  : int    (nb deja traite dans les pages precedentes)
  - token              : string (CSRF)

Traitement :
  1. Verifier droits ($user->rights->ecommerceng->write)
  2. Verifier pas de webhook en cours (ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION)
  3. Instancier eCommerceSynchro + connect()
  4. Appeler une nouvelle methode qui recupere 1 page API + traite les clients
  5. Retourner le resultat

Sortie (JSON) :
  {
    "status": "ok",
    "total": 350,              // depuis X-WP-Total (premiere page), ou -1 si indisponible
    "page": 1,
    "page_size": 100,
    "fetched": 100,            // nb recuperes dans cette page
    "processed": 100,          // nb traites
    "success": 97,
    "errors": ["Erreur client 789: email en doublon"],
    "warnings": ["Client 012: bypass, date non plus recente"],
    "has_more": true           // false quand fetched < page_size ou to_nb atteint
  }
```

#### `action=finish` - Finalisation

```
Entree (POST) :
  - id            : int (site ID)
  - total_success : int
  - total_errors  : int
  - token         : string (CSRF)

Traitement :
  1. Si total_errors == 0 : mettre a jour site->last_update avec toDate
  2. Sinon : mettre a jour site->last_update avec toDate - 1 (comme le code actuel)

Sortie (JSON) :
  {
    "status": "ok",
    "message": "Synchronisation terminee"
  }
```

### 2. `class/client/eCommerceClientApi.class.php` (MODIFICATION)

**Probleme identifie** : `sendToApi()` (ligne 99) retourne uniquement le body JSON decode. L'objet `$response` Guzzle qui contient les headers est local a la methode et perdu au `return`. Le header `X-WP-Total` n'est donc pas accessible actuellement.

**Modification** : ajouter un parametre par reference `&$response_headers` (meme pattern que `&$status_code` existant) :

```php
public function sendToApi($method, $url, $options = [], $without_prefix = false,
                          &$status_code = null, &$error_info = null, &$response_headers = null)
{
    // ... code existant inchange ...

    // Avant le return $data (ligne 155), ajouter :
    if (isset($response_headers)) {
        $response_headers = $response->getHeaders();
    }

    return $data;
}
```

Retro-compatible : le parametre est optionnel avec valeur par defaut `null`, les appelants existants ne sont pas impactes.

### 3. `class/business/eCommerceSynchro.class.php` (MODIFICATION)

Ajout du parametre `$page = 0` aux methodes existantes (passe en cascade) :

**`synchSociete()`** (ligne 1044) :

```php
public function synchSociete($remote_ids = array(), $toNb = 0, $force_update = false, $page = 0, $only_linked = false)
```

Quand `$only_linked = true` :
- Recupere les `remote_id` depuis `llx_ecommerce_societe` pour ce site (nouvelle methode `getAllRemoteIds()` dans `eCommerceSociete`, voir section 5)
- Ecrase `$remote_ids` avec ces IDs (les `company_remote_ids` manuels sont ignores)
- Ignore `$from_date` / `$to_date` (on veut resynchroniser les lies, pas filtrer par date)

**`synchronizeCustomers()`** (ligne 2694) :

```php
public function synchronizeCustomers($from_date = null, $to_date = null, $remote_ids = array(),
                                     $toNb = 0, $only_not_synchronized = false,
                                     $success_log = true, $force_update = false, $page = 0)
```

Pas de modification supplementaire : quand `$only_linked = true`, `synchSociete()` aura deja ecrase `$remote_ids` et mis les dates a null, le reste du flux est inchange.

- `$page = 0` (defaut) : comportement actuel inchange, retourne int (nb synchronises ou < 0 si erreur)
- `$page > 0` : passe `$page` a `convertRemoteObjectIntoDolibarrSociete()`, retourne un tableau enrichi :

```php
return [
    'total' => int,        // depuis X-WP-Total
    'fetched' => int,      // nb recuperes dans cette page
    'success' => int,      // nb traites avec succes
    'errors' => array,     // messages d'erreur
    'warnings' => array,   // messages de warning
    'has_more' => bool     // true si page complete (fetched == per_page)
];
```

### 4. `class/data/woocommerce/eCommerceRemoteAccessWoocommerce.class.php` (MODIFICATION)

Ajout du parametre `$page = 0` a la methode existante :

```php
public function convertRemoteObjectIntoDolibarrSociete($from_date = null, $to_date = null,
                                                        $remoteObject = array(), $toNb = 0,
                                                        $page = 0)
```

Modification minimale dans la boucle `do/while` existante (lignes 570-594) :

```php
$idxPage = ($page > 0) ? $page : 1;    // au lieu de: $idxPage = 1;
// ...
do {
    $filters['page'] = $idxPage++;

    $page_data = $this->client->sendToApi(..., $status_code, $error_info, $response_headers);
    // ... traitement existant inchange ...

    if ($page > 0) break;               // ajout : une seule iteration en mode page
} while (count($page_data) == $nb_max_by_request);
```

Quand `$page > 0`, retourne un tableau enrichi au lieu du simple tableau de clients :

```php
if ($page > 0) {
    return [
        'customers' => $companies,
        'total' => isset($response_headers['X-WP-Total'][0]) ? (int) $response_headers['X-WP-Total'][0] : -1,
        'has_more' => count($page_data) == $nb_max_by_request
    ];
}
return $companies;  // comportement actuel inchange
```

Les appelants existants (`$page = 0` par defaut) ne sont pas impactes.

### 5. `class/data/eCommerceSociete.class.php` (MODIFICATION)

Ajout d'une methode pour recuperer les `remote_id` lies a un site :

```php
/**
 * Recupere tous les remote_id lies a un site
 *
 * @param  int          $siteId     ID du site ecommerce
 * @return array|int                Tableau de remote_id ou -1 si erreur
 */
public function getAllRemoteIds($siteId)
```

Requete : `SELECT remote_id FROM llx_ecommerce_societe WHERE fk_site = $siteId`

Meme pattern que `getAllECommerceSocieteIds()` existant (ligne 420) mais retourne `remote_id` au lieu de `rowid`.

> **Note extensibilite** : la meme methode `getAllRemoteIds($siteId)` pourra etre ajoutee a `eCommerceProduct.class.php` pour la synchro produits par la suite.

### 6. `tpl/site.tpl.php` (MODIFICATION)

#### Checkbox "Uniquement les tiers lies" (dans la zone options du formulaire, apres `force_update`)

```php
print $langs->trans("ECommerceSynchOnlyLinked") . ' : ';
print '<input type="checkbox" name="only_linked" value="1"' . (empty($only_linked) ? '' : ' checked') . ' />';
```

Quand cochee, le JS passe `only_linked=1` a chaque appel AJAX. Les champs `from_date`, `to_date` et `company_remote_ids` sont visuellement desactives (grises) par le JS pour indiquer qu'ils sont ignores.

#### Bouton de synchro tiers (ligne 216-217)

Remplacer le `<input type="submit">` par un bouton AJAX via `dolGetButtonAction()` :

```php
<?php print dolGetButtonAction(
    $langs->trans('ECommerceSynchronizeNewSociete'),
    $langs->trans('ECommerceSynchronizeNewSociete'),
    'default',
    '#',
    'submit_synchro_societe_ajax',
    $user->rights->ecommerceng->write
); ?>
```

Conserver l'ancien bouton submit en fallback (noscript ou constante de config).

#### Zone de progression (apres le tableau, avant les notes `*`)

Composants natifs Dolibarr utilises :
- **Progress bar** : `.progress.progress-striped` (version striped)
- **Badges** : `.badge.badge-success` / `.badge.badge-danger` pour les compteurs dans le log
- **Icons** : Font Awesome (`fa-sync fa-spin` en cours, `fa-check` succes, `fa-times` erreur)
- **Bouton annuler** : `dolGetButtonAction()` avec `actionType = 'cancel'`
- **Bilan final** : `setEventMessages()` via AJAX (le JS injecte les messages dans la zone standard Dolibarr)

```html
<div id="synchro_societe_progress" style="display:none;">
    <div>
        <span id="synchro_societe_status">
            <span class="fas fa-sync fa-spin"></span>
            <span id="synchro_societe_status_text"></span>
        </span>
        <span id="synchro_societe_counter" class="opacitymedium"></span>
        <?php print dolGetButtonAction($langs->trans('Cancel'), $langs->trans('Cancel'),
            'cancel', '#', 'synchro_societe_cancel', 1,
            ['attr' => ['style' => 'display:none']]); ?>
    </div>
    <div class="progress progress-striped sm spaced" id="synchro_societe_progress_bar" title="0%">
        <div class="progress-bar progress-bar-success" role="progressbar"
             style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
    </div>
    <div id="synchro_societe_log"
         style="max-height:200px; overflow-y:auto; font-size:0.9em;"></div>
</div>
```

#### Inclusion du JS (en bas du template)

```php
<script type="text/javascript" src="<?php print dol_buildpath('/ecommerceng/js/synchro_ajax.js', 1) ?>"></script>
<script type="text/javascript">
EcommerceSynchroAjax.init({
    siteId: <?php print $site->id ?>,
    token: '<?php print ecommercengNewToken() ?>',
    ajaxUrl: '<?php print dol_buildpath('/ecommerceng/ajax/synchro_societe.php', 1) ?>',
    langs: {
        processing: '<?php print dol_escape_js($langs->trans("ECommerceSynchroAjaxProcessing")) ?>',
        finished: '<?php print dol_escape_js($langs->trans("ECommerceSynchroAjaxFinished")) ?>',
        aborted: '<?php print dol_escape_js($langs->trans("ECommerceSynchroAjaxAborted")) ?>',
        networkError: '<?php print dol_escape_js($langs->trans("ECommerceSynchroAjaxNetworkError")) ?>'
    }
});
</script>
```

### 7. `js/synchro_ajax.js` (NOUVEAU)

```javascript
var EcommerceSynchroAjax = {
    config: {},
    currentPage: 0,
    totalRemote: -1,       // Connu apres la page 1 via X-WP-Total
    totalProcessed: 0,
    totalSuccess: 0,
    totalErrors: 0,
    errors: [],
    aborted: false,

    init: function(config) { /* stocke config */ },

    /**
     * Point d'entree : collecte les options du formulaire et lance page 1
     */
    startSociete: function() {
        // Collecte from_date, to_date, company_remote_ids, force_update, to_nb
        // depuis les champs du formulaire form_count
        // Reset compteurs, affiche la barre de progression
        // Lance processPage(1)
    },

    /**
     * Traite une page : appel AJAX action=batch
     */
    processPage: function(page) {
        // Si aborted -> finish()
        // POST vers ajax/synchro_societe.php avec toutes les options + page
        // Au retour :
        //   - Met a jour totalRemote depuis response.total (page 1)
        //   - Cumule success/errors
        //   - Met a jour la barre de progression
        //   - Si has_more -> processPage(page + 1)
        //   - Sinon -> finish()
    },

    /**
     * Met a jour l'UI (barre, compteur, log)
     * - Barre : MAJ width + aria-valuenow + title
     * - Compteur : "Page X - 150/350"
     * - Log : une ligne par page avec badges Dolibarr natifs :
     *   '<span class="fas fa-check"></span> Page 2 : <span class="badge badge-success">100</span>
     *    succes, <span class="badge badge-danger">2</span> erreurs'
     */
    updateUI: function() { },

    /**
     * Finalisation : appel AJAX action=finish + bilan via setEventMessages
     * - Injecte les messages succes/erreurs dans la zone standard Dolibarr
     *   (#id-right .jnotify-container) pour un rendu natif
     * - Remplace l'icone fa-sync fa-spin par fa-check (succes) ou fa-exclamation-triangle (erreurs)
     */
    finish: function() { },

    /**
     * Annulation par l'utilisateur
     */
    abort: function() { this.aborted = true; }
};
```

### 8. Fichiers de traduction `langs/*/ecommerce.lang` (MODIFICATION)

```
ECommerceSynchOnlyLinked=Synchroniser uniquement les elements deja lies
ECommerceSynchroAjaxProcessing=Traitement en cours : page %s, %s client(s) traite(s)...
ECommerceSynchroAjaxPageResult=Page %s : %s succes, %s erreur(s)
ECommerceSynchroAjaxFinished=Synchronisation terminee : %s succes, %s erreur(s)
ECommerceSynchroAjaxAborted=Synchronisation annulee par l'utilisateur
ECommerceSynchroAjaxNetworkError=Erreur reseau, nouvelle tentative...
ECommerceSynchroAjaxSessionExpired=Session expiree, veuillez vous reconnecter
```

---

## Schema du flux

```
Utilisateur            JS (synchro_ajax.js)         PHP (ajax/synchro_societe.php)     WooCommerce API
    |                        |                                |                              |
    |-- Clic bouton -------->|                                |                              |
    |                        |                                |                              |
    |                        |-- POST batch, page=1 --------->|                              |
    |                        |   {id, options...}             |-- GET /customers?page=1 ---->|
    |                        |                                |<-- [100 clients] ------------|
    |                        |                                |    X-WP-Total: 350           |
    |                        |                                |-- synchronizeCustomer() x100 |
    |                        |<-- {total:350, success:98,  ---|                              |
    |                        |     has_more:true}             |                              |
    |<-- Barre: 100/350 -----|                                |                              |
    |                        |                                |                              |
    |                        |-- POST batch, page=2 --------->|                              |
    |                        |   {id, options...}             |-- GET /customers?page=2 ---->|
    |                        |                                |<-- [100 clients] ------------|
    |                        |                                |-- synchronizeCustomer() x100 |
    |                        |<-- {success:100, has_more:true} |                              |
    |<-- Barre: 200/350 -----|                                |                              |
    |                        |                                |                              |
    |                        |-- POST batch, page=3 --------->|                              |
    |                        |                                |-- GET /customers?page=3 ---->|
    |                        |                                |<-- [100 clients] ------------|
    |                        |<-- {success:99, has_more:true}  |                              |
    |<-- Barre: 300/350 -----|                                |                              |
    |                        |                                |                              |
    |                        |-- POST batch, page=4 --------->|                              |
    |                        |                                |-- GET /customers?page=4 ---->|
    |                        |                                |<-- [50 clients] -------------|
    |                        |<-- {success:49, has_more:false} |                              |
    |                        |                                |                              |
    |                        |-- POST finish ----------------->|                              |
    |                        |   {total_success:346,           |-- MAJ site->last_update     |
    |                        |    total_errors:4}              |                              |
    |                        |<-- {ok} -----------------------|                              |
    |                        |                                |                              |
    |<-- Barre: 100% --------|                                |                              |
    |    "346 OK, 4 erreurs" |                                |                              |
```

---

## Header X-WP-Total

L'API WooCommerce (basee sur WP REST API) retourne dans les headers de chaque reponse paginee :
- `X-WP-Total` : nombre total d'elements correspondant aux filtres
- `X-WP-TotalPages` : nombre total de pages

**Verifie** : `sendToApi()` dans `eCommerceClientApi.class.php` (ligne 99) retourne uniquement le body JSON decode. L'objet `$response` Guzzle est local et perdu. Le header `X-WP-Total` n'est **pas accessible** actuellement. La modification est decrite dans la section 2 ci-dessus (ajout du parametre `&$response_headers`).

---

## Gestion de `to_nb` (limite globale)

Le parametre `to_nb` limite le nombre total de clients a synchroniser. Avec la pagination :
- Le JS passe `to_nb` et `already_processed` a chaque appel
- Le PHP calcule : `remaining = to_nb - already_processed`
- Si `remaining < per_page` : ajuste `per_page` a `remaining` dans l'appel API
- `has_more = false` si `already_processed + fetched >= to_nb`

---

## Gestion des erreurs

- **Erreur sur un client** : log l'erreur, continue les autres clients de la page (pas d'arret)
- **Erreur API WooCommerce** : retourne l'erreur, le JS peut retry 1 fois puis log et arret
- **Erreur reseau (AJAX)** : retry automatique (1 tentative) puis arret avec message
- **Session expiree / 403** : arret et message "Session expiree, veuillez vous reconnecter"
- **Bouton Annuler** : flag `aborted = true`, arret apres la page courante, appel finish quand meme

---

## Retrocompatibilite

Le bouton form submit classique (`submit_synchro_societe`) reste present en fallback :
- Visible si JS desactive (`<noscript>`)
- Ou activable via une constante `ECOMMERCENG_DISABLE_AJAX_SYNC`
- Le code existant dans `site.php` lignes 167-169 continue de fonctionner tel quel

---

## Plan d'implementation (ordre)

### Etape 1 : Modification de `sendToApi()` (`eCommerceClientApi.class.php`)

1. Ajouter le parametre `&$response_headers = null` a la signature
2. Avant le `return $data`, stocker `$response->getHeaders()` si le parametre est passe
3. Pas d'impact sur les appelants existants (parametre optionnel)

### Etape 2 : Ajout des parametres `$page` et `$only_linked` aux methodes existantes

1. `eCommerceSociete::getAllRemoteIds($siteId)` : nouvelle methode (meme pattern que `getAllECommerceSocieteIds`)
2. `convertRemoteObjectIntoDolibarrSociete()` : ajout `$page = 0`, init `$idxPage` + `break` apres 1 iteration, retour enrichi si `$page > 0`
3. `synchronizeCustomers()` : ajout `$page = 0`, passe en cascade, retour enrichi si `$page > 0`
4. `synchSociete()` : ajout `$page = 0` et `$only_linked = false`, quand `$only_linked = true` ecrase `$remote_ids` et ignore les dates

### Etape 3 : Endpoint AJAX (`ajax/synchro_societe.php`)

1. Creer le fichier avec actions `batch` et `finish`
2. Verifications droits et webhook
3. Retour JSON

### Etape 4 : JavaScript (`js/synchro_ajax.js`)

1. Workflow : page 1 -> page N -> finish
2. Barre de progression + log
3. Bouton annuler

### Etape 5 : Template (`tpl/site.tpl.php`)

1. Checkbox `only_linked` + desactivation visuelle des champs incompatibles via JS
2. Bouton AJAX + fallback submit classique
3. Zone de progression HTML
4. Inclusion JS

### Etape 6 : Traductions

1. Cles FR et EN

### Etape 7 : Tests

1. 0 client a synchroniser
2. `company_remote_ids` specifiques
3. `force_update` active
4. `to_nb` avec limite inferieure a une page et superieure
5. `from_date` / `to_date`
6. `only_linked` active (verifie que les IDs viennent de la table de liaison)
7. `only_linked` + `force_update` combines
8. `only_linked` avec 0 tiers lies
9. Annulation en cours
10. Erreur reseau
11. Gros volume (>500 clients, plusieurs pages)

---

## Extensibilite

Le meme pattern (1 appel AJAX = 1 page API) est reutilisable pour :
- Produits (`submit_synchro_product_ajax`) - meme logique avec `/products`
- Commandes (`submit_synchro_commande_ajax`) - meme logique avec `/orders`

Factoriser `js/synchro_ajax.js` en consequence avec un objet configurable par type d'entite.