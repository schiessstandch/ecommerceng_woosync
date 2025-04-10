<?php
/* Copyright (C) 2007-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2019      Open-DSI             <support@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	    \file       htdocs/custom/ecommerceng/admin/about.php
 *		\ingroup    ecommerceng
 *		\brief      Page about of ecommerceng module
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../main.inc.php")) $res=@include '../../../main.inc.php';		// to work if your module directory is into a subdir of root htdocs directory
if (! $res) die("Include of main fails");
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/ecommerceng/lib/eCommerce.lib.php');
dol_include_once('/ecommerceng/lib/opendsi_common.lib.php');
dol_include_once('/ecommerceng/core/modules/modECommerceNg.class.php');

$langs->loadLangs(array("admin", "orders", "companies", "bills", "accountancy", "banks", "oauth", "ecommerce@ecommerceng", "opendsi@ecommerceng"));

if (!$user->admin) accessforbidden();









$object = new eCommerceSite($db);
if (!($id > 0)) {
	$sites = $object->listSites();
	if (!empty($sites)) {
		$id = array_values($sites)[0]['id'];
	}
	$action = '';
}
if ($id > 0) {
	$result = $object->fetch($id);
	if ($result < 0) {
		accessforbidden($object->errorsToString());
	} elseif ($result == 0) {
		$langs->load('errors');
		accessforbidden($langs->trans('ErrorRecordNotFound'));
	}
} else {
	accessforbidden($langs->trans('ErrorRecordNotFound'));
}

/*if (empty($conf->facture->enabled) && empty($conf->facture->enabled)) {
	accessforbidden($langs->trans('ModuleDisabled'));
} */






/**
 * View
 */

$wikihelp='EN:ECommerceNg_En|FR:ECommerceNg_Fr|ES:ECommerceNg_Es';
llxHeader('', $langs->trans("ECommerceSetup"), $wikihelp);

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("ECommerceSetup"),$linkback,'title_setup');
print "<br>\n";


$head=ecommercengConfigSitePrepareHead($object);

print dol_get_fiche_head($head, 'changelog', $langs->trans("Module107100Name"), 0, 'opendsi@ecommerceng');

$changelog = opendsi_common_getChangeLog('ecommerceng');

print '<div class="moduledesclong">'."\n";
print (!empty($changelog) ? $changelog : $langs->trans("NotAvailable"));
print '<div>'."\n";

print dol_get_fiche_end();


llxFooter();

$db->close();
