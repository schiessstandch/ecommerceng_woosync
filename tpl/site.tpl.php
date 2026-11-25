<?php

/**
 * @var eCommerceSynchro    $synchro
 */

dol_include_once('/ecommerceng/lib/compatibility.lib.php');

llxHeader();

if (is_object($site)) {
	dol_include_once("/ecommerceng/lib/eCommerce.lib.php");
	$linkback = '<a href="index.php">' . $langs->trans("BackToListOfSites") . '</a>';

	//print_fiche_titre($langs->trans('ECommerceSiteSynchro') . ' ' . $site->name, $linkback, 'eCommerceTitle@ecommerceng');

	print load_fiche_titre($langs->trans("ECommerceSiteSynchro"), $linkback, 'title_setup');

	print '<br>';

	$head = array();
	$head[1][0] = '?id=' . $site->id;
	$head[1][1] = $langs->trans("Direction") . ' : Ecommerce -> Dolibarr';
	$head[1][2] = 'ecommerce2dolibarr';

	// dol_fiche_head($head, 'ecommerce2dolibarr', '');

	print dol_get_fiche_head($head, 'ecommerce2dolibarr', $langs->trans("Module107100Name"), 0, 'opendsi@ecommerceng');

	$disabled = $synchRights != true && empty(ecommercengGetGlobalString('ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION'));

	print '<form name="form_count" id="form_count" action="' . $_SERVER['PHP_SELF'] . '?id=' . $site->id . '" method="post">';
	print '<input type="hidden" name="token" value="' . ecommercengNewToken() . '">';
	//print '<input type="hidden" name="id" value="'.$site->id.'">';
	if (GETPOST('test_with_no_categ_count', 'alpha')) print '<input type="hidden" name="test_with_no_categ_count" value="' . GETPOST('test_with_no_categ_count', 'alpha') . '">';
	if (GETPOST('test_with_no_product_count', 'alpha')) print '<input type="hidden" name="test_with_no_product_count" value="' . GETPOST('test_with_no_product_count', 'alpha') . '">';
	if (GETPOST('test_with_no_thirdparty_count', 'alpha')) print '<input type="hidden" name="test_with_no_thirdparty_count" value="' . GETPOST('test_with_no_thirdparty_count', 'alpha') . '">';
	if (GETPOST('test_with_no_order_count', 'alpha')) print '<input type="hidden" name="test_with_no_order_count" value="' . GETPOST('test_with_no_order_count', 'alpha') . '">';
	if (GETPOST('test_with_no_invoice_count', 'alpha')) print '<input type="hidden" name="test_with_no_invoice_count" value="' . GETPOST('test_with_no_invoice_count', 'alpha') . '">';
	print '<input type="hidden" name="id" value="' . $site->id . '">';

	print '<table class="centpercent nobordernopadding">';

	print '<tr>';
	print '<td>';
	if (!empty(ecommercengGetGlobalString('ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION'))) {
		print $langs->trans("ECommerceProcessingWebHooksSynchronisationSince") . ' : <strong>' . ecommercengGetGlobalString('ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION') . '</strong><br>';
	}
	print $langs->trans("ECommerceLastCompleteSync", $site->name) . ' : ';
	if ($site->last_update) print '<strong>' . dol_print_date($site->last_update, 'dayhoursec') . '</strong>';
	else print '<strong>' . $langs->trans("ECommerceNoUpdateSite") . '</strong>';
	print '</td><td rowspan="'.(!empty($site->fk_anonymous_thirdparty) ? '4' : '3').'" class="center">';
	if (empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) print '<input type="submit" class="button marginrightonly" name="refresh" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&action=refresh&token='.ecommercengNewToken().'" value="' . $langs->trans('RefreshCount') . '">';
	if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_SYNCHRONIZE_ALL_BUTTON'))) print '<input type="submit" class="button' . ($disabled ? ' buttonRefused' : '') . '" name="submit_synchro_all" href="' . ($disabled ? '#' : $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&action=submit_synchro_all&submit_synchro_all=1&token='.ecommercengNewToken()) . '" value="' . $langs->trans('SyncAll') . '">';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	print $langs->trans("RestrictSyncForRecordBetween") . ' ';
	print '<input type="text" name="from_date" value="' . dol_escape_htmltag($from_date) . '" placeholder="YYYYMMDDHHMMSS">';
	print ' ' . $langs->trans("to") . ' ';
	print '<input type="text" name="to_date" value="' . dol_escape_htmltag($to_date) . '" placeholder="YYYYMMDDHHMMSS">';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	print $langs->trans("RestrictSyncToCompanyRemoteID") . '<br><textarea row="3" name="company_remote_ids" class="centpercent">' . $company_remote_ids . '</textarea>';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	print $langs->trans("RestrictSyncToProductRemoteID") . '<br><textarea row="3" name="product_remote_ids" class="centpercent">' . $product_remote_ids . '</textarea>';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	print $langs->trans("RestrictSyncToOrderRemoteID") . '<br><textarea row="3" name="order_remote_ids" class="centpercent">' . $order_remote_ids . '</textarea>';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	print $langs->trans("DontSyncProductWhenSyncOrders") . ' : <input type="checkbox" name="dont_synchronize_products" value="1"' . (empty($dont_synchronize_products) ? '' : ' checked') . ' />';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	print $langs->trans("ECommerceForceUpdate") . ' : <input type="checkbox" name="force_update" value="1"' . (empty($force_update) ? '' : ' checked') . ' />';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	$only_linked = GETPOST('only_linked', 'aZ09');
	print $langs->trans("ECommerceSynchOnlyLinked") . ' : <input type="checkbox" name="only_linked" id="only_linked" value="1"' . (empty($only_linked) ? '' : ' checked') . ' />';
	print '</td>';
	print '</tr>';

	print '<tr>';
	print '<td>';
	print $langs->trans("RestrictNbInSync") . ' ';
	print '<input type="text" name="to_nb" placeholder="0" value="' . dol_escape_htmltag($to_nb) . '">';
	print '</td>';
	print '</tr>';

	if (!empty($site->fk_anonymous_thirdparty)) {
		print '<tr>';
		print '<td>';
		print $langs->trans("SynchUnkownCustomersOnThirdParty") . ' : ';
		$nonloggedthirdparty = new Societe($db);
		$result = $nonloggedthirdparty->fetch($site->fk_anonymous_thirdparty);
		print $nonloggedthirdparty->getNomUrl(1);
		print '</td>';
		print '</tr>';
	}

	print '</table>';
	print '<br>' . "\n";
	?>
	<div class="div-table-responsive-no-min">
		<table class="noborder" width="100%">
			<tr class="liste_titre">
				<td><?php print $langs->trans('ECommerceObjectToUpdate') ?></td>
				<td><?php print $langs->trans('NbInDolibarr') ?></td>
				<td><?php print $langs->trans('NbInDolibarrLinkedToE') ?></td>
				<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?><td><?php print $langs->trans('ECommerceCountToUpdate'); ?></td><?php } ?>
				<?php if ($synchRights == true): ?><td>&nbsp;</td><?php endif; ?>
			</tr>

			<tr class="oddeven">
				<td><?php print $langs->trans('ECommerceCategoriesProducts') ?></td>
				<td><?php print $nbCategoriesInDolibarr; ?> *</td>
				<td><?php print $nbCategoriesInDolibarrLinkedToE;
					?> *
					<?php
					if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
						print '<div class="debugtools inline-block">(<a class="submit_reset_data_links" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=categories_links&token='.ecommercengNewToken().'">' . $langs->trans("ClearLinks") . '</a>';
						print ' - <a class="submit_reset_data_all" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=categories_all&token='.ecommercengNewToken().'">' . $langs->trans("ClearData") . '</a>';
						print ')</div>';
					}
					?>
				</td>
				<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?><td><?php print $nbCategoriesToUpdate; ?></td><?php } ?>
				<?php if ($synchRights == true) { ?>
					<td>
						<?php //if ($nbCategoriesToUpdate > 0 || !empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?>
							<input type="submit" name="submit_synchro_category" id="submit_synchro_category"
								   class="button"
								   value="<?php print $langs->trans('ECommerceSynchronizeCategoryProduct') ?>">
						<?php //} ?>
					</td>
				<?php } ?>
			</tr>
			<tr class="oddeven">
				<td><?php print $langs->trans('ProductsOrServices') ?>
					<?php
					if (isset($synchro)) {
						$last_update_date = $synchro->getProductLastUpdateDate();
						print ' ( ' . $langs->trans('ECommerceLastUpdate') . ' : ' . (empty($last_update_date) ? 'N/A' : dol_print_date($last_update_date, 'dayhour')) . ' )';
						if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
							print '<div class="debugtools inline-block">&nbsp;(<a class="submit_reset_last_update_date" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_last_update_date=product&token=' . ecommercengNewToken() . '">' . $langs->trans("ClearLastUpdateDate") . '</a>)</div>';
						}
					}
					?>
                </td>
				<td><?php print $nbProductInDolibarr; ?> **</td>
				<td><?php print $nbProductInDolibarrLinkedToE; ?> **
					<?php
					if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
						print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=products_links&token='.ecommercengNewToken().'">' . $langs->trans("ClearLinks") . '</a>';
						print ' - <a class="submit_reset_data_all" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=products_all&token='.ecommercengNewToken().'">' . $langs->trans("ClearData") . '</a>';
						print ')</div>';
					}
					?>
				</td>
				<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?><td><?php print $nbProductToUpdate; ?></td><?php } ?>
				<?php if ($synchRights == true): ?>
					<td>
						<?php
						/*if ($nbProductToUpdate > 0 && $nbCategoriesToUpdate > 0 && empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) {
							?>
							<input type="submit" name="submit_synchro_product" id="submit_synchro_product"
								   class="button" disabled="disabled"
								   value="<?php print $langs->trans('ECommerceSynchronizeProduct') . ' (' . $langs->trans("SyncCategFirst") . ")"; ?>">
							<?php
						} elseif ($nbProductToUpdate > 0 || !empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) {*/ ?>
							<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_DISABLE_AJAX_SYNC')) && !empty($conf->use_javascript_ajax)) { ?>
								<input type="button" name="submit_synchro_product_ajax" id="submit_synchro_product_ajax"
									   class="button" value="<?php print $langs->trans('ECommerceSynchronizeNewProduct') ?>">
							<?php } else { ?>
								<input type="submit" name="submit_synchro_product" id="submit_synchro_product"
									   class="button" value="<?php print $langs->trans('ECommerceSynchronizeNewProduct') ?>">
							<?php } ?>
						<?php //} ?>
					</td>
				<?php endif; ?>
			</tr>
			<tr class="oddeven">
				<td><?php print $langs->trans('ECommerceSociete') ?> (<?php print $langs->trans('ECommerceOnlyNew') ?>
					)
					<?php
					if (isset($synchro)) {
						$last_update_date = $synchro->getSocieteLastUpdateDate();
						print ' ( ' . $langs->trans('ECommerceLastUpdate') . ' : ' . (empty($last_update_date) ? 'N/A' : dol_print_date($last_update_date, 'dayhour')) . ' )';
						if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
							print '<div class="debugtools inline-block">&nbsp;(<a class="submit_reset_last_update_date" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_last_update_date=thirdparty&token=' . ecommercengNewToken() . '">' . $langs->trans("ClearLastUpdateDate") . '</a>)</div>';
						}
					}
					?>
                </td>
				<td><?php print $nbSocieteInDolibarr; ?> ***</td>
				<td><?php print $nbSocieteInDolibarrLinkedToE; ?> ***
					<?php
					if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
						print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=thirdparties_links&token='.ecommercengNewToken().'">' . $langs->trans("ClearLinks") . '</a>';
						print ' - <a class="submit_reset_data_all" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=thirdparties_all&token='.ecommercengNewToken().'">' . $langs->trans("ClearData") . '</a>';
						print ')</div>';
					}
					?>
				</td>
				<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?><td><?php print $nbSocieteToUpdate; ?> ****</td><?php } ?>
				<?php if ($synchRights == true): ?>
					<td>
						<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_DISABLE_AJAX_SYNC')) && !empty($conf->use_javascript_ajax)) { ?>
							<input type="button" name="submit_synchro_societe_ajax" id="submit_synchro_societe_ajax"
								   class="button" value="<?php print $langs->trans('ECommerceSynchronizeNewSociete') ?>">
						<?php } else { ?>
							<input type="submit" name="submit_synchro_societe" id="submit_synchro_societe"
								   class="button" value="<?php print $langs->trans('ECommerceSynchronizeNewSociete') ?>">
						<?php } ?>
					</td>
				<?php endif; ?>
			</tr>
			<tr class="oddeven">
				<td><?php print $langs->trans('ECommerceCommande') ?>
					<?php
					if (isset($synchro)) {
						$last_update_date = $synchro->getCommandeLastUpdateDate();
						print ' ( ' . $langs->trans('ECommerceLastUpdate') . ' : ' . (empty($last_update_date) ? 'N/A' : dol_print_date($last_update_date, 'dayhour')) . ' )';
						if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
							print '<div class="debugtools inline-block">&nbsp;(<a class="submit_reset_last_update_date" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_last_update_date=order&token=' . ecommercengNewToken() . '">' . $langs->trans("ClearLastUpdateDate") . '</a>)</div>';
						}
					}
					?>
                </td>
				<?php
				if (empty($conf->commande->enabled)) {
					?>
					<td colspan="3"><?php print $langs->trans("ModuleCustomerOrderDisabled"); ?></td>
					<?php
				} else {
					?>
					<td><?php print $nbCommandeInDolibarr; ?></td>
					<td><?php print $nbCommandeInDolibarrLinkedToE; ?>
						<?php
						if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
							print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=orders_links&token='.ecommercengNewToken().'">' . $langs->trans("ClearLinks") . '</a>';
							print ' - <a class="submit_reset_data_all" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=orders_all&token='.ecommercengNewToken().'">' . $langs->trans("ClearData") . '</a>';
							print ')</div>';
						}
						?>
					</td>
					<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?><td><?php print $nbCommandeToUpdate; ?></td><?php } ?>
					<?php if ($synchRights == true): ?>
						<td>
							<?php
							/*if ($nbCommandeToUpdate > 0 && $nbSocieteToUpdate > 0 && empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?>
								<input type="submit" name="submit_synchro_commande" id="submit_synchro_commande"
									   class="button" disabled="disabled"
									   value="<?php print $langs->trans('ECommerceSynchronizeCommande') . ' (' . $langs->trans("SyncSocieteFirst") . ')'; ?>">
							<?php } elseif ($nbCommandeToUpdate > 0 || !empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) {*/ ?>
								<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_DISABLE_AJAX_SYNC')) && !empty($conf->use_javascript_ajax)) { ?>
									<input type="button" name="submit_synchro_commande_ajax" id="submit_synchro_commande_ajax"
										   class="button" value="<?php print $langs->trans('ECommerceSynchronizeNewCommande') ?>">
								<?php } else { ?>
									<input type="submit" name="submit_synchro_commande" id="submit_synchro_commande"
										   class="button"
										   value="<?php print $langs->trans('ECommerceSynchronizeNewCommande') ?>">
								<?php } ?>
							<?php //} ?>
						</td>
					<?php endif;
				}
				?>
			</tr>
			<?php
			/*
			if (!empty($conf->facture->enabled)) {
				?>
				<tr class="oddeven">
					<td><?php print $langs->trans('ECommerceFacture') ?></td>
					<td><?php print $nbFactureInDolibarr; ?></td>
					<td><?php print $nbFactureInDolibarrLinkedToE; ?>
						<?php
						if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
							print '<div class="debugtools inline-block"> (<a class="submit_reset_data_links" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=invoices_links&token='.ecommercengNewToken().'">' . $langs->trans("ClearLinks") . '</a>';
							print ' - <a class="submit_reset_data_all" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&reset_data=invoices_all&token='.ecommercengNewToken().'">' . $langs->trans("ClearData") . '</a>';
							print ')</div>';
						}
						?>
					</td>
					<td>
						<?php
						print $nbFactureToUpdate;
						?>
					</td>
					<?php if ($synchRights == true): ?>
						<td>
							<?php if ($nbFactureToUpdate > 0 && $nbSocieteToUpdate > 0 && empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?>
								<input type="submit" name="submit_synchro_facture" id="submit_synchro_facture"
									   class="button" disabled="disabled"
									   value="<?php print $langs->trans('ECommerceSynchronizeFacture') . ' (' . $langs->trans("SyncSocieteFirst") . ')'; ?>">
							<?php } elseif ($nbFactureToUpdate > 0 && $nbCommandeToUpdate > 0 && empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?>
								<input type="submit" name="submit_synchro_facture" id="submit_synchro_facture"
									   class="button" disabled="disabled"
									   value="<?php print $langs->trans('ECommerceSynchronizeFacture') . ' (' . $langs->trans("SyncCommandeFirst") . ')'; ?>">
							<?php } elseif ($nbFactureToUpdate > 0 || !empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) { ?>
								<input type="submit" name="submit_synchro_facture" id="submit_synchro_facture"
									   class="button"
									   value="<?php print $langs->trans('ECommerceSynchronizeFacture') ?>">
							<?php } ?>
						</td>
					<?php endif; ?>
				</tr>
			<?php }*/ ?>
		</table>
	</div>

	<?php if (empty(ecommercengGetGlobalString('ECOMMERCENG_DISABLE_AJAX_SYNC')) && !empty($conf->use_javascript_ajax)) { ?>
	<!-- AJAX sync shared progress zone -->
	<div id="synchro_progress" class="hideobject">
		<div class="marginbottomonly">
			<span id="synchro_status">
				<span class="fas fa-sync fa-spin"></span>
				<span id="synchro_status_text"></span>
			</span>
			<span id="synchro_counter" class="opacitymedium marginleftonly"></span>
			<input type="button" id="synchro_cancel" class="button hideobject marginleftonly" value="<?php print $langs->trans('Cancel'); ?>">
		</div>
		<div class="progress progress-striped sm spaced" id="synchro_progress_bar" title="0%">
			<div class="progress-bar progress-bar-success" role="progressbar"
				 style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
		</div>
		<div id="synchro_log" class="small"></div>
	</div>
	<?php } ?>

	<?php
	$categorytmpprod = new Categorie($db);
	$categorytmpprod->fetch($site->fk_cat_product);
	$tagnameprod = $categorytmpprod->label;
	$categorytmp = new Categorie($db);
	$categorytmp->fetch($site->fk_cat_societe);
	$tagname = $categorytmp->label;
	print '<span class="opacitymedium">';
	print '* ' . $langs->trans("OnlyProductCategIn", $tagnameprod) . '<br>';
	print '** ' . $langs->trans("OnlyProductsIn", $tagnameprod, $tagnameprod) . '<br>';
	//print '*** '.$langs->trans("OnlyThirdPartyWithTags", $tagname).'<br>';
	print '*** ' . $langs->trans("OnlyThirdPartyIn", $tagname) . '<br>';
	print '**** ' . $langs->trans("WithMagentoThirdIsModifiedIfAddressModified") . '<br>';
	print '</span>';

	dol_fiche_end();

	print '<br>';

	$head = array();
	$head[1][0] = '?id=' . $site->id;
	$head[1][1] = $langs->trans("Direction") . ' : Dolibarr -> Ecommerce';
	$head[1][2] = 'dolibarr2ecommerce';

	dol_fiche_head($head, 'dolibarr2ecommerce', '');

	print $langs->trans("SyncIsAutomaticInRealTime", $site->name) . "\n";

	print '<form name="form_count" id="form_count" action="' . $_SERVER['PHP_SELF'] . '?id=' . $site->id . '" method="post">';
	print '<input type="hidden" name="token" value="' . ecommercengNewToken() . '">';
	//print '<input type="hidden" name="id" value="'.$site->id.'">';

	print '<table class="centpercent nobordernopadding">';

	print '<tr><td>';
	if (!empty(ecommercengGetGlobalString('ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION'))) {
		print $langs->trans("ECommerceProcessingWebHooksSynchronisationSince") . ' : <strong>' . ecommercengGetGlobalString('ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION') . '</strong><br>';
	}
	print $langs->trans("RestrictNbInSync") . ' ';
	print '<input type="text" name="dtoe_to_nb" placeholder="0" value="' . dol_escape_htmltag($dtoe_to_nb) . '">';
	print '</td><td>';
	if (empty(ecommercengGetGlobalString('ECOMMERCENG_NO_COUNT_UPDATE'))) print '<input type="submit" class="button marginrightonly" name="refresh" href="' . $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&action=refresh&token='.ecommercengNewToken().'" value="' . $langs->trans('RefreshCount') . '">';
	$disabled = $synchRights != true;
//	if ($synchRights != true || (($nbCategoriesToUpdateDToE > 0 || $nbProductToUpdateDToE > 0) && !($nbCategoriesToUpdate > 0 || $nbProductToUpdate > 0))) $disabled = false;
	print '<input type="submit" class="button' . ($disabled ? ' buttonRefused' : '') . '" name="submit_dtoe_synchro_all" href="' . ($disabled ? '#' : $_SERVER["PHP_SELF"] . '?id=' . $site->id . '&action=submit_dtoe_synchro_all&token='.ecommercengNewToken().'') . '" value="' . $langs->trans('SyncAll') . '">';
	print '</td></tr>';

	print '</table>';

	print '<br>' . "\n";
	?>
	<div class="div-table-responsive">
		<table class="noborder" width="100%">
			<tr class="liste_titre">
				<td><?php print $langs->trans('ECommerceObjectToUpdate') ?></td>
				<td><?php print $langs->trans('NbInDolibarr') ?></td>
				<td><?php print $langs->trans('NbInDolibarrLinkedToE') ?></td>
				<td><?php print $langs->trans('ECommerceCountToUpdateDtoE'); ?></td>
				<?php if ($synchRights == true) { ?>
					<td>&nbsp;</td>
				<?php } ?>
			</tr>
            <tr class="oddeven">
				<td><?php print $langs->trans('ECommerceCategoriesProducts') ?></td>
				<td><?php print $nbCategoriesInDolibarr ?> *</td>
				<td><?php print $nbCategoriesInDolibarrLinkedToE ?> *</td>
				<td><?php print $nbCategoriesToUpdateDToE ?></td>
				<?php if ($synchRights == true) { ?>
					<td>
						<?php if ($nbCategoriesToUpdateDToE > 0) { ?>
							<input type="submit" name="submit_dtoe_synchro_category" id="submit_dtoe_synchro_category"
								   class="button"<?php print ($nbCategoriesToUpdate != '?' && $nbCategoriesToUpdate > 0 ? ' disabled="disabled"' : '') ?>
								   value="<?php print ($nbCategoriesToUpdate != '?' && $nbCategoriesToUpdate > 0 ? $langs->trans('ECommerceSynchronizeCategoryProduct') . ' (' . $langs->trans("SyncCategFirst") . ")" : $langs->trans('ECommerceSynchronizeCategoryProduct')) ?>">
						<?php } ?>
					</td>
				<?php } ?>
			</tr>
			<tr class="oddeven">
				<td><?php print $langs->trans('ProductsOrServices') ?></td>
				<td><?php print $nbProductInDolibarr ?> **</td>
				<td><?php print $nbProductInDolibarrLinkedToE ?> **</td>
				<td><?php print $nbProductToUpdateDToE ?> ***</td>
				<?php if ($synchRights == true) { ?>
					<td>
						<?php if ($nbProductToUpdateDToE > 0) { ?>
							<input type="submit" name="submit_dtoe_synchro_product" id="submit_dtoe_synchro_product"
								   class="button"<?php print ($nbCategoriesToUpdateDToE != '?' && $nbCategoriesToUpdateDToE > 0 ? ' disabled="disabled"' : '') ?>
								   value="<?php print ($nbCategoriesToUpdateDToE != '?' && $nbCategoriesToUpdateDToE > 0 ? $langs->trans('ECommerceSynchronizeProduct') . ' (' . ($nbProductToUpdate != '?' && $nbProductToUpdate > 0 ? $langs->trans("SyncProductFirst") : $langs->trans("SyncCategFirst")) . ")" : $langs->trans('ECommerceSynchronizeProduct')) ?>">
						<?php } ?>
					</td>
				<?php } ?>
			</tr>
		</table>
	</div>
	<?php
	print '<span class="opacitymedium">';
	print '* ' . $langs->trans("OnlyProductCategIn", $tagnameprod) . '<br>';
	print '** ' . $langs->trans("OnlyProductsIn", $tagnameprod, $tagnameprod) . '<br>';
	print '*** ' . $langs->trans("ECommerceNGUpdateElementDateMoreRecent") . '<br>';
	print '</span>';

	dol_fiche_end();

	print '</form>';


	if (!empty(ecommercengGetGlobalString('ECOMMERCENG_SHOW_DEBUG_TOOLS'))) {
		print '<br><br>';

		$head = array();
		$head[1][0] = '?id=' . $site->id;
		$head[1][1] = $langs->trans("DangerZone");
		$head[1][2] = 'dangerzone';

		dol_fiche_head($head, 'dangerzone', '');

		print '<div class="nodebugtools inline-block">';
		print '<a id="showtools">' . $langs->trans("ShowDebugTools") . '</a>';
		print '</div>';
		print '<div class="debugtools">';
		print '<a class="submit_reset_data_links" href="' . $_SERVER['PHP_SELF'] . '?id=' . $site->id . '&to_date=' . $to_date . '&reset_data=links&token='.ecommercengNewToken().'">' . $langs->trans('ECommerceResetLink') . '</a>';
		print '<br><br>';
		print '<a class="submit_reset_data_all" href="' . $_SERVER['PHP_SELF'] . '?id=' . $site->id . '&to_date=' . $to_date . '&reset_data=all&token='.ecommercengNewToken().'">' . $langs->trans('ECommerceReset') . '</a>';
		dol_fiche_end();
		print '</div>';

		print "
		<!-- Include jQuery confirm -->
		<script type=\"text/javascript\">
        jQuery(document).ready(function() {
		$('.submit_reset_data_links').on('click', function () {
		    return confirm('" . dol_escape_js($langs->transnoentitiesnoconv("AreYouSureYouWantToDeleteLinks")) . "');
		});
        $('.submit_reset_data_all').on('click', function () {
		    return confirm('" . dol_escape_js($langs->transnoentitiesnoconv("AreYouSureYouWantToDeleteAll")) . "');
		});
		$('.submit_reset_last_update_date').on('click', function () {
		    return confirm('" . dol_escape_js($langs->transnoentitiesnoconv("AreYouSureYouWantToDeleteLastUpdateDate")) . "');
		});
		$('#showtools').on('click', function () {
		    jQuery('.nodebugtools').toggle();
		    jQuery('.debugtools').toggle();
        });
        ";
		if (empty($_SESSION["showdebugtool"])) {
			print " jQuery('.nodebugtools').show();\n";
			print " jQuery('.debugtools').hide();\n";
		} else {
			print " jQuery('.nodebugtools').hide();\n";
			print " jQuery('.debugtools').show();\n";
		}
		print "});";
		print "</script>";
	}


	if ($site->type == 1) {
		print '<br>';
		$soapwsdlcacheon = ini_get('soap.wsdl_cache_enabled');
		$soapwsdlcachedir = ini_get('soap.wsdl_cache_dir');
		if ($soapwsdlcacheon) {
			print '<div class="opacitymedium">' . $langs->trans("WarningSoapCacheIsOn", $soapwsdlcachedir) . '</div><br>';
		} else {
			print '<div class="opacitymedium">' . $langs->trans("SoapCacheIsOff", $soapwsdlcachedir) . '</div><br>';
		}
	}
	print '<br>';
} else {
	print load_fiche_titre($langs->trans("ECommerceSiteSynchro"), $linkback, 'eCommerceTitle@ecommerceng');
	$errors[] = $langs->trans('ECommerceSiteError');
}

setEventMessages(null, $errors, 'errors');
setEventMessages(null, $warnings, 'warnings');
if (GETPOST('reset_data')) setEventMessages(null, $success, 'warnings');
else setEventMessages(null, $success);

if (is_object($site) && empty(ecommercengGetGlobalString('ECOMMERCENG_DISABLE_AJAX_SYNC')) && !empty($conf->use_javascript_ajax)) {
?>
<link rel="stylesheet" type="text/css" href="<?php print dol_buildpath('/ecommerceng/css/synchro.css', 1) ?>">
<script type="text/javascript" src="<?php print dol_buildpath('/ecommerceng/js/synchro_ajax.js', 1) ?>"></script>
<script type="text/javascript">
var __synchroConfig = {
	siteId: <?php print $site->id ?>,
	ajaxUrl: '<?php print dol_buildpath('/ecommerceng/ajax/synchro.php', 1) ?>',
	collectOptions: function(form) {
		var opts = {};
		form.find('input, textarea, select').each(function() {
			var el = jQuery(this);
			var name = el.attr('name');
			if (!name) return;
			if (el.is(':checkbox')) {
				opts[name] = el.is(':checked') ? 1 : 0;
			} else {
				opts[name] = el.val() || '';
			}
		});
		return opts;
	},
	langs: {
		processing: '<?php print dol_escape_js($langs->transnoentitiesnoconv("ECommerceSynchroAjaxProcessing")) ?>',
		finished: '<?php print dol_escape_js($langs->transnoentitiesnoconv("ECommerceSynchroAjaxFinished")) ?>',
		aborted: '<?php print dol_escape_js($langs->transnoentitiesnoconv("ECommerceSynchroAjaxAborted")) ?>',
		networkError: '<?php print dol_escape_js($langs->transnoentitiesnoconv("ECommerceSynchroAjaxNetworkError")) ?>',
		sessionExpired: '<?php print dol_escape_js($langs->transnoentitiesnoconv("ECommerceSynchroAjaxSessionExpired")) ?>'
	}
};
var synchroSociete = createSynchroAjaxInstance('societe', __synchroConfig);
var synchroProduct = createSynchroAjaxInstance('product', __synchroConfig);
var synchroCommande = createSynchroAjaxInstance('commande', __synchroConfig);
jQuery(document).ready(function() {
	var _activeSynchro = null;
	jQuery('#submit_synchro_societe_ajax').on('click', function(e) {
		e.preventDefault();
		_activeSynchro = synchroSociete;
		synchroSociete.start();
	});
	jQuery('#submit_synchro_product_ajax').on('click', function(e) {
		e.preventDefault();
		_activeSynchro = synchroProduct;
		synchroProduct.start();
	});
	jQuery('#submit_synchro_commande_ajax').on('click', function(e) {
		e.preventDefault();
		_activeSynchro = synchroCommande;
		synchroCommande.start();
	});
	jQuery('#synchro_cancel').on('click', function(e) {
		e.preventDefault();
		if (_activeSynchro) _activeSynchro.abort();
	});
	// Disable incompatible fields when only_linked is checked
	jQuery('#only_linked').on('change', function() {
		var checked = jQuery(this).is(':checked');
		jQuery('input[name="from_date"], input[name="to_date"], textarea[name="company_remote_ids"], textarea[name="product_remote_ids"], textarea[name="order_remote_ids"]')
			.prop('disabled', checked)
			.css('opacity', checked ? 0.5 : 1);
		var toNbField = jQuery('input[name="to_nb"]');
		if (checked) {
			toNbField.data('old_value', toNbField.val());
			toNbField.val('0').prop('disabled', true).css('opacity', 0.5);
		} else {
			toNbField.val(toNbField.data('old_value') || '').prop('disabled', false).css('opacity', 1);
		}
	}).trigger('change');
});
</script>
<?php
}

llxFooter();
