# Fonctionnalités WooSync (ecommerceng)

Ce document décrit les fonctionnalités du module WooSync pour Dolibarr, permettant la synchronisation bidirectionnelle avec WooCommerce.

---

## Synchronisation des données

### Produits

- **Synchronisation bidirectionnelle** des produits entre Dolibarr et WooCommerce
- **Synchronisation AJAX page par page** pour éviter les timeouts PHP sur les gros volumes
- Support des **produits simples** et **produits variables** (variations)
- Synchronisation des **images produits** (téléchargement et envoi)
- Synchronisation des **catégories produits** avec hiérarchie
- Mapping des **classes de TVA** WooCommerce vers Dolibarr
- Synchronisation des **attributs produits** vers champs complémentaires
- Support du **multi-langues** via plugin WPML
- Gestion du **prix régulier** et **prix promotionnel** avec dates
- Synchronisation des **dimensions** (poids, hauteur, largeur, profondeur)
- Option de gestion du **statut de vente** des produits
- Gestion de l'**état du stock (backorders) par produit** via champ complémentaire avec liste déroulante (En stock, Victime de son succès retour bientôt, En réapprovisionnement) et paramétrage global par défaut

### Stocks

- Synchronisation des stocks **Dolibarr → WooCommerce** ou **WooCommerce → Dolibarr**
- Support **multi-entrepôts** (plugin "Stock Locations for WooCommerce" v3.0.2+)
- Support du plugin **"WooCommerce Multi Locations Inventory Management"**
- Synchronisation du **stock virtuel** (stock réel + stock commandé)
- Association **entrepôt/zone d'expédition** selon méthode de livraison
- Mise à jour automatique via **mouvements de stock** Dolibarr
- Option pour **ne pas synchroniser le stock** sur certains produits (champ complémentaire)

### Tiers et contacts

- Synchronisation des **clients WooCommerce** vers tiers Dolibarr
- **Synchronisation AJAX page par page** pour eviter les timeouts PHP sur les gros volumes (barre de progression, log, annulation, retry reseau) — disponible pour tiers, produits et commandes
- Option **"Synchroniser uniquement les elements deja lies"** pour resynchroniser les éléments existants sans filtre de date (tiers, produits, commandes)
- **Detection des éléments distants manquants** lors de la synchro avec warnings enrichis (ref Dolibarr et ID)
- Détection des doublons par **email**, **nom + code postal**, ou **nom**
- Distinction **entreprise/particulier** automatique
- Synchronisation des **contacts de facturation** et **livraison**
- Support des **méta-données client** vers champs complémentaires
- Enregistrement du **code du rôle WooCommerce** dans le champ complémentaire du tiers (code brut au lieu de la traduction)
- Filtrage par **rôles utilisateurs** WooCommerce supportés
- Gestion d'un **tiers anonyme** pour commandes invités
- Fusion automatique lors de changement de société

### Commandes

- Import des **commandes WooCommerce** vers Dolibarr
- **Synchronisation AJAX page par page** pour éviter les timeouts PHP sur les gros volumes
- Mapping des **statuts de commande** configurable
- Synchronisation du **statut** de Dolibarr vers WooCommerce
- Support des **codes promo** et **cartes cadeaux**
- Récupération des **notes de commande** (publique/privée)
- Génération automatique du **PDF de commande**
- Ajout automatique d'un **commercial** en contact
- Support des produits **bundles** (plugin "WPC Product Bundles")

### Factures

- Création automatique de **factures** depuis les commandes synchronisées
- Support des **factures d'acompte**
- Création de **factures d'avoir** pour remboursements
- **Envoi automatique par email** avec modèle configurable
- Génération du **PDF dans la langue** de la commande (WPML)
- Création de **factures fournisseur** pour frais de paiement (Stripe, etc.)
- Classement automatique en **payée** selon mode de paiement

### Expéditions

- Synchronisation des **expéditions validées** vers WooCommerce
- Envoi du **PDF d'expédition** vers la commande WooCommerce
- Mise à jour du **statut de commande** à l'expédition

---

## WebHooks (Crochets Web)

### Traitement asynchrone

- Réception des **webhooks WooCommerce** en temps réel
- File d'attente des événements dans `llx_ecommerce_pending_webhooks`
- Traitement par **tâche cron** toutes les 15 minutes
- Validation de la **signature HMAC-SHA256**

### Événements supportés

| Événement | Action |
|-----------|--------|
| `product.created` | Création du produit dans Dolibarr |
| `product.updated` | Mise à jour du produit dans Dolibarr |
| `product.deleted` | Suppression du lien produit |
| `product.restored` | Restauration du lien produit |
| `order.created` | Création de la commande dans Dolibarr |
| `order.updated` | Mise à jour de la commande dans Dolibarr |

### Gestion des erreurs

- Statuts : **En attente**, **Traité**, **Erreur**, **Avertissement**
- **Archivage automatique** des webhooks traités (après 7 jours par défaut)
- **Alerte email** en cas d'erreur ou dépassement de volumétrie
- Interface de **retraitement** des webhooks en erreur
- Bouton de **déblocage du verrou** de synchronisation des webhooks en cas d'arrêt anormal de la tâche cron (suppression de la constante `ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION`)

---

## Tâches Cron

| Tâche | Classe/Méthode | Fréquence | Description |
|-------|----------------|-----------|-------------|
| **Traitement WebHooks** | `eCommercePendingWebHook::cronProcessPendingWebHooks` | 15 min | Traite les webhooks en attente |
| **Vérification WebHooks** | `eCommercePendingWebHook::cronCheckWebHooksStatus` | 15 min | Vérifie le statut de livraison des webhooks |
| **Volumétrie WebHooks** | `eCommercePendingWebHook::cronCheckWebHooksVolumetry` | 15 min | Surveille le volume de webhooks (désactivé par défaut) |
| **Sync Stocks** | `eCommerceUtils::cronSynchronizeStocksToSite` | 15 min | Synchronise les stocks vers WooCommerce |

---

## Triggers (Déclencheurs)

### Création et modification

| Événement | Condition | Action |
|-----------|-----------|--------|
| `COMPANY_MODIFY` | `realtime_dtoe[thirdparty]` activé | Synchronise le tiers vers WooCommerce |
| `CONTACT_MODIFY` | `realtime_dtoe[contact]` activé | Synchronise le contact vers WooCommerce |
| `PRODUCT_MODIFY` | Produit dans catégorie du site + `realtime_dtoe[product]` | Synchronise le produit vers WooCommerce |
| `PRODUCT_SET_MULTILANGS` | WPML activé | Synchronise les traductions produit |
| `CATEGORY_LINK` | Catégorie liée à un produit | Déclenche la synchro produit |
| `ORDER_VALIDATE` | Commande liée à un site | Met à jour le statut sur WooCommerce |
| `ORDER_CLOSE` | Commande liée à un site | Met à jour le statut sur WooCommerce |
| `ORDER_CANCEL` | Commande liée à un site | Met à jour le statut sur WooCommerce |
| `BILL_MODIFY` | `realtime_dtoe[order]` activé | Synchronise la facture |
| `SHIPPING_VALIDATE` | Expédition liée à une commande sync | Envoie PDF et met à jour statut |

### Suppression

| Événement | Action |
|-----------|--------|
| `COMPANY_DELETE` | Supprime les liens `ecommerce_societe` et `ecommerce_socpeople` |
| `CONTACT_DELETE` | Supprime le lien `ecommerce_socpeople` |
| `CATEGORY_DELETE` | Supprime le lien `ecommerce_category`, met à jour les références parent |
| `ORDER_DELETE` | Supprime le lien `ecommerce_commande` |
| `BILL_DELETE` | Supprime les liens `ecommerce_facture` et `ecommerce_commande` associés |

---

## Widgets

### Widget WebHooks

- **Classe** : `box_ecommerce_webhooks.php`
- **Affichage** : Tableau de bord Dolibarr
- **Contenu** : Compteurs par statut (En attente, Traité, Erreur, Avertissement)
- **Liens** : Accès direct à la liste filtrée par statut
- **Permission** : Droit de lecture ecommerceng

---

## Configuration

### Onglet Paramètres

- **Nom du site** et adresse URL
- **Type d'authentification** (OAuth1, clés API)
- **Credentials** (Consumer Key/Secret)
- **Version de l'API** WooCommerce
- **Timeout** de connexion
- **Vérification SSL** (désactivable pour certificats auto-signés)
- **Inclusion des données POST** dans la signature OAuth

### Onglet Tiers

- **Catégorie racine** pour les clients synchronisés
- **Rôles utilisateurs** WooCommerce supportés
- **Mapping champs complémentaires** tiers
- **Tiers anonyme** pour commandes invités
- Option **ne pas mettre à jour** les tiers existants
- Recherche par **nom + code postal** (activable/désactivable)

### Onglet Produits

- **Catégorie racine** des produits à synchroniser
- **Niveau de prix** Dolibarr à utiliser
- **Type de prix** WooCommerce (régulier ou promo)
- **Direction de synchronisation** de la référence
- **Statuts de publication** synchronisés
- **Support WPML** pour multi-langues
- **Mapping attributs** → champs complémentaires
- **Comptes comptables** par défaut
- **Unités de poids/dimensions**
- **Gestion des ventes** de produits

### Onglet Stocks

- **Direction de synchronisation** : Dolibarr→Site, Site→Dolibarr, ou Aucune
- **Entrepôts** à synchroniser (sélection multiple)
- **Support plugin multi-entrepôts** WooCommerce
- **Association entrepôt/zone d'expédition**
- **Stock virtuel** (inclure commandes en cours)

### Onglet Commandes/Factures

- **Création commande** : Oui/Non
- **Création facture** : Standard, Acompte, ou Non
- **Mapping statuts** commande WooCommerce ↔ Dolibarr
- **Modes de paiement** : association gateway → mode Dolibarr
- **Compte bancaire** par mode de paiement
- **Modèle d'email** facture (par langue si WPML)
- **Condition de règlement** par défaut
- **Entrepôt** pour décrémentation stock
- **Frais annexes** : produit ou service dédié
- **Note commande** : privée ou publique
- **Méta-données** produit dans description ligne
- **Facture fournisseur** pour frais de paiement

---

## Constantes globales

### Synchronisation

| Constante | Défaut | Description |
|-----------|--------|-------------|
| `ECOMMERCENG_MAXSIZE_MULTICALL` | 400 | Taille max par appel API batch |
| `ECOMMERCENG_MAXRECORD_PERSYNC` | 2000 | Nombre max d'enregistrements par synchro |
| `ECOMMERCENG_NO_COUNT_UPDATE` | 1 | Désactive le comptage (optimisation) |
| `ECOMMERCENG_DEBUG` | 0 | Active les logs détaillés des requêtes API |
| `ECOMMERCENG_ENABLE_LOG_IN_NOTE` | 0 | Stocke les réponses API dans les notes |

### WooCommerce

| Constante | Description |
|-----------|-------------|
| `ECOMMERCENG_WOOCOMMERCE_NO_VERIFY_SSL` | Désactive la vérification SSL |
| `ECOMMERCENG_WOOCOMMERCE_ORDER_STATUS_LVL_CHECK` | Vérifie le niveau de statut |
| `ECOMMERCENG_WOOCOMMERCE_VAT_NUMBER_META_NAME` | Nom du champ TVA intracommunautaire |
| `ECOMMERCENG_WOOCOMMERCE_GET_EMAIL_ON_COMPANY` | Récupère l'email sur le tiers entreprise |
| `ECOMMERCENG_WOOCOMMERCE_DEFAULT_LANG_OTHER_COUNTRY` | Langue par défaut si pays différent |

### Mode personnalisé

| Constante | Description |
|-----------|-------------|
| `ECOMMERCENG_SYNCHRO_PRODUCT_MODE_CUSTOM_1` | Mode personnalisé : la ref produit est dans le champ "name" WooCommerce, le libellé dans la meta "product_short_description". Désactive le support WPML et des variations. |

### Interface

| Constante | Défaut | Description |
|-----------|--------|-------------|
| `ECOMMERCENG_DISABLE_AJAX_SYNC` | 0 | Desactive la synchronisation AJAX et utilise le submit classique |

### Traitement données

| Constante | Description |
|-----------|-------------|
| `ECOMMERCENG_UPPERCASE_LASTNAME` | Force le nom en majuscules |
| `ECOMMERCENG_PRODUCT_IN_PURCHASE_WHEN_CREATED` | Produit en achat à la création |
| `ECOMMERCENG_PRODUCT_REF_MANDATORY` | Référence produit obligatoire |
| `ECOMMERCENG_DISABLED_PRODUCT_SYNCHRO_STOD` | Désactive synchro produit Site→Dolibarr |
| `ECOMMERCE_DONT_UNSET_CATEGORIE_OF_PRODUCT_WHEN_DELINK` | Garde les catégories au délien |
| `ECOMMERCE_DELTA_FIX_TOTAL_AMOUNTS` | Tolérance arrondi montants (défaut: 0.1) |
| `ECOMMERCE_KEEP_NEGATIVE_PRICE_LINES_WHEN_CREATE_INVOICE` | Garde les lignes négatives |

### WebHooks

| Constante | Description |
|-----------|-------------|
| `ECOMMERCE_PROCESSING_WEBHOOK_LOGS_BEFORE_X_DAYS` | Jours avant archivage (défaut: 7) |
| `ECOMMERCE_NOTIFY_EMAIL_ERRORS_CHECK_WEBHOOKS_STATUS` | Emails alerte erreurs webhooks |
| `ECOMMERCE_NOTIFY_EMAIL_ERRORS_CHECK_WEBHOOKS_VOLUMETRY` | Emails alerte volumétrie |

---

## Permissions

| Code | Libellé | Type |
|------|---------|------|
| `ecommerceng->read` | Voir le statut de synchronisation | Lecture |
| `ecommerceng->write` | Synchroniser les données | Écriture |
| `ecommerceng->site` | Configurer les sites | Administration |

---

## Dictionnaires

| Table | Description | Condition |
|-------|-------------|-----------|
| `c_ecommerceng_tax_class` | Classes de TVA WooCommerce | Site WooCommerce actif |
| `c_ecommerceng_tax_rate` | Taux de TVA WooCommerce | Site WooCommerce actif |
| `c_ecommerceng_attribute` | Attributs produits WooCommerce | Site WooCommerce actif |
| `c_ecommerceng_lang` | Correspondance langues Dolibarr/WooCommerce | Multi-langues activé |

---

## Champs complémentaires créés

### Sur les produits

- **ecommerceng_stockable_product** : Gestion du stock sur le site (Oui/Non)

- **ecommerceng_wc_backorders_{site_id}_{entity}** : État du stock / backorders pour le site (En stock / Victime de son succès / En réapprovisionnement)

### Sur les commandes (par site)

- **ecommerceng_status_site_{id}** : Statut de la commande sur le site

---

## Scripts utilitaires

| Script | Usage |
|--------|-------|
| `syncronize_to_dolibarr.php` | `php script.php user site_id (categories\|products\|thirdparties\|orders) id_from id_to [force]` |
| `syncronize_products_to_ecommerce.php` | `php script.php user` - Synchro produits vers WooCommerce |
| `activate_sharing_product_images.php` | Active le partage des images produits |
| `check_movement_stock_dolibarr.php` | Vérifie les mouvements de stock |
| `set_payment_for_all_unpaid_invoices_linked_to_ecommerce.php` | Marque les factures comme payées |
| `fix_invoices_lines_with_update_orders_lines_if_exist.php` | Corrige les lignes factures/commandes |

---

## Dépendances

### Modules Dolibarr requis

- **Sociétés** (modSociete)
- **Produits** (modProduct)
- **Catégories** (modCategorie)
- **Services Web** (modWebServices)

### Bibliothèques externes

- **GuzzleHTTP** ^6.3 - Client HTTP avec retry automatique
- **OAuth1 Subscriber** - Authentification OAuth1 personnalisée

---

## Compatibilité

| Composant | Version |
|-----------|---------|
| Dolibarr | 14.0 à 21.x |
| PHP | 7.0 à 8.2 |
| WooCommerce | API REST v3 |

### Plugins WooCommerce supportés

- **WPML** - Multi-langues
- **Stock Locations for WooCommerce** - Multi-entrepôts
- **WooCommerce Multi Locations Inventory Management** - Gestion inventaire
- **WPC Product Bundles** - Produits composés
- **PW WooCommerce Gift Cards Pro** - Cartes cadeaux
- **WooCommerce Cost of Goods** - Prix d'achat
- **F4 Shipping Phone and E-Mail** - Téléphone/email livraison
- **Advanced Coupons for WooCommerce Free** - Coupons avancés
