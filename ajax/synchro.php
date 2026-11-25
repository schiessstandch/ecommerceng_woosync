<?php
/* Copyright (C) 2024       Open-Dsi		<support@open-dsi.fr>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX', '1');

$res = 0;
if (!$res && file_exists("../../main.inc.php")) $res = @include '../../main.inc.php';
if (!$res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
dol_include_once('/ecommerceng/lib/compatibility.lib.php');
dol_include_once("/ecommerceng/class/business/eCommerceSynchro.class.php");
dol_include_once("/ecommerceng/class/data/eCommerceSite.class.php");
dol_include_once("/ecommerceng/lib/eCommerce.lib.php");

$langs->load("ecommerce@ecommerceng");

// Increase time limit for sync operations
$ExecTimeLimit = 600; // 10 min per page
$err = error_reporting();
error_reporting(0);
@set_time_limit($ExecTimeLimit);
error_reporting($err);

header('Content-Type: application/json; charset=UTF-8');

// Allow PHP to detect client disconnection (AJAX abort) without dying mid-transaction
ignore_user_abort(true);

$action = GETPOST('action', 'aZ09');
$element_type = GETPOST('element', 'aZ09');
$id = GETPOST('id', 'int');

// Validate element type
if (!in_array($element_type, ['societe', 'product', 'commande'])) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'message' => 'Invalid element type']);
	exit;
}

// Check rights
if (empty(ecommercengUserHasRight($user, 'ecommerceng', 'write'))) {
	http_response_code(403);
	echo json_encode(['status' => 'error', 'message' => 'Permission denied']);
	exit;
}

// Check site ID
if (empty($id)) {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'message' => 'Missing site ID']);
	exit;
}

// Load site
$site = new eCommerceSite($db);
$site->fetch($id);
if ($site->entity != $conf->entity) {
	http_response_code(403);
	echo json_encode(['status' => 'error', 'message' => 'Access denied']);
	exit;
}

// Check webhook processing
if (!empty(ecommercengGetGlobalString('ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION'))) {
	http_response_code(409);
	echo json_encode(['status' => 'error', 'message' => $langs->transnoentitiesnoconv('ECommerceProcessingWebHooksSynchronisationSince') . ' : ' . ecommercengGetGlobalString('ECOMMERCE_PROCESSING_WEBHOOK_SYNCHRONIZATION')]);
	exit;
}

if ($action == 'batch') {
	// Clear output buffers so flush() in sync loops reaches the network layer
	// This is required for connection_aborted() to detect client disconnect (AJAX cancel)
	while (ob_get_level()) ob_end_flush();

	$page = GETPOST('page', 'int');
	$to_nb = GETPOST('to_nb', 'int');
	$already_processed = GETPOST('already_processed', 'int');

	if ($page < 1) $page = 1;

	// Resolve and freeze the date filter so it stays identical across every page of a
	// sync run. On the first page the effective dates are derived from the form fields,
	// or else from the last-sync cursor. The client echoes them back on the following
	// pages (frozen_from_date/frozen_to_date) so only the page number advances while the
	// filter remains constant — otherwise the sliding cursor plus the page number would
	// double-advance and skip records.
	$cursor_const = array(
		'societe'  => 'ECOMMERCE_LAST_SYNC_DATE_CUSTOMER_',
		'product'  => 'ECOMMERCE_LAST_SYNC_DATE_PRODUCT_',
		'commande' => 'ECOMMERCE_LAST_SYNC_DATE_ORDER_',
	);

	if (GETPOSTISSET('frozen_from_date') || GETPOSTISSET('frozen_to_date')) {
		// Following pages: reuse the exact filter frozen by the first page (0 = no bound)
		$ts = GETPOST('frozen_from_date', 'int');
		$fromDate = $ts > 0 ? $ts : null;
		$ts = GETPOST('frozen_to_date', 'int');
		$toDate = $ts > 0 ? $ts : null;
	} else {
		// First page: resolve the effective filter
		$from_date_str = GETPOST('from_date', 'aZ09');
		$to_date_str = GETPOST('to_date', 'aZ09');
		$fromDate = !empty($from_date_str) ? dol_stringtotime($from_date_str) : null;
		$toDate = !empty($to_date_str) ? dol_stringtotime($to_date_str) : null;
		if ($fromDate === null && isset($cursor_const[$element_type])) {
			$const = $cursor_const[$element_type] . $id;
			if (!empty($conf->global->$const)) $fromDate = (int) $conf->global->$const;
		}
	}

	// Element-specific remote IDs parameter
	$remote_ids_str = '';
	if ($element_type == 'societe') $remote_ids_str = GETPOST('company_remote_ids', 'alpha');
	elseif ($element_type == 'product') $remote_ids_str = GETPOST('product_remote_ids', 'alpha');
	elseif ($element_type == 'commande') $remote_ids_str = GETPOST('order_remote_ids', 'alpha');

	$remote_ids = array();
	if (!empty($remote_ids_str)) {
		$remote_ids = array_filter(array_map('trim', explode(',', $remote_ids_str)), 'strlen');
	}

	// Adjust to_nb for already processed records
	$effective_to_nb = 0;
	if (!empty($to_nb)) {
		$remaining = $to_nb - $already_processed;
		if ($remaining <= 0) {
			echo json_encode([
				'status' => 'ok',
				'total' => -1,
				'page' => $page,
				'fetched' => 0,
				'processed' => 0,
				'success' => 0,
				'errors' => [],
				'warnings' => [],
				'has_more' => false,
			]);
			exit;
		}
		$effective_to_nb = $remaining;
	}

	try {
		$synchro = new eCommerceSynchro($db, $site, $fromDate, $toDate, $effective_to_nb);
		$synchro->connect();
		if (count($synchro->errors)) {
			echo json_encode(['status' => 'error', 'message' => dol_html_entity_decode($synchro->errorsToString(), ENT_COMPAT | ENT_HTML5)]);
			exit;
		}

		// Dispatch to the right sync method
		$only_linked = GETPOST('only_linked', 'int');
		if ($element_type == 'societe') {
			$force_update = GETPOST('force_update', 'int');
			$result = $synchro->synchSociete($remote_ids, $effective_to_nb, !empty($force_update), $page, !empty($only_linked));
		} elseif ($element_type == 'product') {
			$force_update = GETPOST('force_update', 'int');
			$result = $synchro->synchProduct($remote_ids, $effective_to_nb, !empty($force_update), $page, !empty($only_linked));
		} elseif ($element_type == 'commande') {
			$dont_synchronize_products = GETPOST('dont_synchronize_products', 'int');
			$result = $synchro->synchCommande($remote_ids, $effective_to_nb, !empty($dont_synchronize_products), $page, !empty($only_linked));
		}

		if (is_array($result)) {
			// Check if to_nb limit reached
			if (!empty($to_nb) && ($already_processed + $result['fetched']) >= $to_nb) {
				$result['has_more'] = false;
			}

			echo json_encode([
				'status' => 'ok',
				'total' => $result['total'],
				'page' => $page,
				'fetched' => $result['fetched'],
				'processed' => $result['success'] + count($result['errors']),
				'success' => $result['success'],
				'errors' => array_map(function($e) { return dol_html_entity_decode($e, ENT_COMPAT | ENT_HTML5); }, $result['errors']),
				'warnings' => array_map(function($w) { return dol_html_entity_decode($w, ENT_COMPAT | ENT_HTML5); }, $result['warnings']),
				'has_more' => $result['has_more'],
				// Effective date filter, echoed back by the client to freeze it across pages
				'from_date' => $fromDate !== null ? (int) $fromDate : 0,
				'to_date' => $toDate !== null ? (int) $toDate : 0,
			]);
		} else {
			echo json_encode([
				'status' => 'error',
				'message' => dol_html_entity_decode($synchro->errorsToString(), ENT_COMPAT | ENT_HTML5),
			]);
		}
	} catch (Exception $e) {
		echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
	}
} elseif ($action == 'finish') {
	$total_success = GETPOST('total_success', 'int');
	$total_errors = GETPOST('total_errors', 'int');

	echo json_encode([
		'status' => 'ok',
		'message' => $langs->transnoentitiesnoconv('ECommerceSynchroAjaxFinished', $total_success, $total_errors),
	]);
} else {
	http_response_code(400);
	echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}

$db->close();
