<?php
/* Copyright (C) 2025		Lionel Vessiller		<lvessiller@open-dsi.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */


/**
 * @var Conf $conf
 * @var Translate $langs
 * @var User $user
 *
 * @var DolibarrModules $modClass
 *
 */

if (empty($user) || !is_object($user)) {
	print "Error, template page can't be called as URL";
	exit(1);
} elseif (empty($modClass) || !is_object($modClass)) {
	print "Error, template page can't be called as URL";
	exit(1);
}

if (!function_exists('dolPrintHTML')) {
    function dolPrintHTML($s)
    {
        // Original DLB version. dol_htmlwithnojs() is long .
        // return dol_escape_htmltag(dol_htmlwithnojs(dol_string_onlythesehtmltags(dol_htmlentitiesbr($s), 1, 1, 1)), 1, 1, 'common', 0, 1);
        return dol_escape_htmltag(dol_string_onlythesehtmltags(dol_htmlentitiesbr($s), 1, 1, 1), 1, 1, 'common', 0, 1);
    }
}

$editorCommercialEmail = 'info@opendsi.fr';
$editorCommercialTel = '+33 4 82 53 94 76';
$editorName = $modClass->editor_name;
$editorSupportCreateTicketUrl = 'https://support.opendsi.fr/create_ticket.php';
$editorStoreLink = '<a href="https://www.dolistore.com/index.php?search_query=opendsi" target="_blank">Dolistore</a>';

$supportMessage = "/*****"."<br>";
$supportMessage .= " * Module : ".$langs->trans('Module'.$modClass->numero.'Name')."<br>";
$supportMessage .= " * Module version : ".(!empty($modClass->getVersion()) ? $modClass->getVersion() : 'NC')."<br>";
$supportMessage .= " * Dolibarr version : ".DOL_VERSION."<br>";
$supportMessage .= " * Dolibarr version installation initiale : ".getDolGlobalString('MAIN_VERSION_LAST_INSTALL')."<br>";
$supportMessage .= " * Option colonne sélection à gauche : ".getDolGlobalInt('MAIN_CHECKBOX_LEFT_COLUMN', 0)."<br>";
$supportMessage .= " * Version PHP : ".PHP_VERSION."<br>";
$supportMessage .= " *****/"."<br>";
$supportMessage .= "Description de votre problème :"."<br>";

?>

<!-- BEGIN TEMPLATE -->
<div class="about-tab">

    <?php if (version_compare(DOL_VERSION, '14.0.0') >= 0): ?>
    <script>
        $().ready(() => {
            $('.clipboardCPValue').hide()
        })
    </script>
    <?php endif; ?>

	<div class="about-header">
		<img class="opendsi-logo" src="../img/object_opendsi_big.png" alt="<?php echo $editorName; ?>">
		<div class="right">
			<img class="preferred-partner-logo" src="../img/dolibarr_preferred_partner.png" alt="Preferred Partner">
		</div>
	</div>
	<div class="about-body">
		<h2>Module développé par <?php echo $editorName; ?></h2>
		<p>Ce module a été conçu par <?php echo $editorName; ?>, société experte en intégration et développement de solutions sur mesure autour de <b>Dolibarr ERP/CRM</b>.</p>
		<p>Notre engagement : proposer des outils robustes, évolutifs et conformes aux exigences des professionnels.</p>
		<p class="editor-preferred-partner"><?php echo $editorName; ?> est membre du programme officiel <b>Dolibarr Preferred Partner</b>, gage de qualité et d’expertise.</p>

		<h2>Support technique</h2>
		<p>Pour toute demande d’assistance ou retour concernant ce module, merci de soumettre une demande via notre plateforme dédiée :</p>
		<div class="center">
			<form id="ticket" method="POST" target="_blank" action="<?php echo $editorSupportCreateTicketUrl; ?>">
				<input name="message" type="hidden" value="<?php echo $supportMessage; ?>" />
				<input name="email" type="hidden" value="<?php echo $user->email; ?>" />
				<div class="center">
					<button class="support" type="submit">Accéder au support</button>
					<div class="btn-copy-clipboard"><?php print showValueWithClipboardCPButton(dolPrintHTML($supportMessage), 0, 'Copier les informations techniques du module'); ?></div>
				</div>
			</form>
		</div>

		<br />
		<div class="center">

		</div>

		<h2>Contact commercial</h2>
		<p>Pour toute information complémentaire, projet personnalisé ou besoin d’accompagnement :</p>
		<ul>
			<li><a href="<?php echo "mailto:".$editorCommercialEmail; ?>"><?php echo $editorCommercialEmail; ?></a></li>
			<li><?php echo $editorCommercialTel; ?></li>
		</ul>
	</div>
	<div class="about-footer">
		<h2>Découvrez nos autres modules</h2>
		<p>Retrouvez l’ensemble de nos solutions sur le <?php echo $editorStoreLink; ?>, la place de marché officielle de modules Dolibarr.</p>
	</div>
</div>
<!-- END TEMPLATE -->
