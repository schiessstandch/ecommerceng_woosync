<?php
/* Copyright (C) 2019      Open-DSI             <support@open-dsi.fr>
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
 * \file    htdocs/ecommerceng/class/data/eCommerceRemoteShippingZoneMethods.class.php
 * \ingroup ecommerceng
 * \brief
 */
dol_include_once('/ecommerceng/class/data/eCommerceRemoteShippingZones.class.php');

/**
 * Class eCommerceRemoteShippingZoneMethods
 *
 * Put here description of your class
 */
class eCommerceRemoteShippingZoneMethods
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;
	/**
	 * @var string Error
	 */
	public $error = '';
	/**
	 * @var array Errors
	 */
	public $errors = array();

	public $table_element = 'ecommerceng_remote_shipping_zone_methods';

	/**
	 * Constructor
	 *
	 * @param        DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 *  Set all remote shipping zone methods of a site
	 *
	 * @param   int         $site_id                		Site ID
	 * @param   array       $remote_shipping_zone_methods	List of infos of each remote shipping zone methods
	 * @return  int                                 		>0 if OK, <0 if KO
	 * @throws  Exception
	 */
	public function set($site_id, $remote_shipping_zone_methods)
	{
		global $conf, $langs;
		dol_syslog(__METHOD__ . " site_id=$site_id, remote_shipping_zone_methods=" . json_encode($remote_shipping_zone_methods));

		$errors = 0;
		$this->errors = array();

		// Clean values
		$site_id = max(0, (int) $site_id);
		$remote_shipping_zone_methods = is_array($remote_shipping_zone_methods) ? $remote_shipping_zone_methods : array();

		// Check values
		if ($site_id == 0) {
			$this->errors[] = $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("ECommerceSite"));
			return -1;
		}

		$this->db->begin();

		// Set shipping zones
		$eCommerceRemoteShippingZones = new eCommerceRemoteShippingZones($this->db);
		$result = $eCommerceRemoteShippingZones->set($site_id, $remote_shipping_zone_methods);
		if ($result < 0) {
			$this->error = $eCommerceRemoteShippingZones->error;
			$this->errors = $eCommerceRemoteShippingZones->errors;
			return -1;
		}

		foreach ($remote_shipping_zone_methods as $remote_shipping_zone_infos) {
			if ($remote_shipping_zone_infos['methods']) {
				foreach ($remote_shipping_zone_infos['methods'] as $infos) {
					// Search shipping zone method
					$sql = 'SELECT rowid';
					$sql .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element;
					$sql .= ' WHERE site_id = ' . $site_id;
					$sql .= ' AND entity = ' . $conf->entity;
					$sql .= " AND remote_zone_id = " . ((int) $remote_shipping_zone_infos['remote_id']);
					$sql .= " AND remote_instance_id = " . ((int) $infos['remote_instance_id']);
					$sql .= " AND remote_method_id = '" . $this->db->escape($infos['remote_method_id']) . "'";

					$resql = $this->db->query($sql);
					if (!$resql) {
						dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
						$this->errors[] = $this->db->lasterror();
						$errors++;
						break;
					}

					$line_id = 0;
					if ($obj = $this->db->fetch_object($resql)) {
						$line_id = $obj->rowid;
					}

					$this->db->free($resql);

					if ($line_id > 0) {
						// Update values
						$sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element . ' SET';
						$sql .= " remote_title = '" . $this->db->escape($infos['remote_title']) . "'";
						$sql .= ", remote_order = " . ((int) $infos['remote_order']);
						$sql .= ", remote_enabled = " . (!empty($infos['remote_enabled']) ? 1 : 0);
						$sql .= ", remote_method_title = '" . $this->db->escape($infos['remote_method_title']) . "'";
						$sql .= ", remote_method_description = '" . $this->db->escape($infos['remote_method_description']) . "'";
						$sql .= ", warehouse_id = " . ($infos['warehouse_id'] > 0 ? $infos['warehouse_id'] : 'NULL');
						$sql .= ', old_entry = ' . (!empty($infos['old_entry']) ? 1 : 'NULL');
						$sql .= ' WHERE rowid = ' . $line_id;

						$resql = $this->db->query($sql);
					} else {
						// Insert values
						$sql = 'INSERT INTO ' . MAIN_DB_PREFIX . $this->table_element . '(site_id, remote_zone_id, remote_instance_id';
						$sql .= ', remote_title, remote_order, remote_enabled, remote_method_id, remote_method_title, remote_method_description';
						$sql .= ', warehouse_id, old_entry, entity) VALUES (';
						$sql .= $site_id;
						$sql .= ", " . ((int) $remote_shipping_zone_infos['remote_id']);
						$sql .= ", " . ((int) $infos['remote_instance_id']);
						$sql .= ", '" . $this->db->escape($infos['remote_title']) . "'";
						$sql .= ", " . ((int) $infos['remote_order']);
						$sql .= ", " . (!empty($infos['remote_enabled']) ? 1 : 0);
						$sql .= ", '" . $this->db->escape($infos['remote_method_id']) . "'";
						$sql .= ", '" . $this->db->escape($infos['remote_method_title']) . "'";
						$sql .= ", '" . $this->db->escape($infos['remote_method_description']) . "'";
						$sql .= ', ' . ($infos['warehouse_id'] > 0 ? $infos['warehouse_id'] : 'NULL');
						$sql .= ', ' . (!empty($infos['old_entry']) ? 1 : 'NULL');
						$sql .= ', ' . $conf->entity;
						$sql .= ')';
						$resql = $this->db->query($sql);
					}
					if (!$resql) {
						dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
						$this->errors[] = $this->db->lasterror();
						$errors++;
						break;
					}
				}
				if ($errors) {
					break;
				}
			}
		}

		if ($errors) {
			$this->db->rollback();
			return -1;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 *  Get a remote shipping zone method of a site by the remote shipping zone method instance id and remote shipping zone method id
	 *
	 * @param   int         $site_id                Site ID
	 * @param   integer     $remote_instance_id		Remote shipping zone method instance ID on site
	 * @param   string      $remote_method_id		Remote shipping zone method ID on site
	 * @return  array|int                           0 if not found, <0 if errors or array of infos
	 * @throws  Exception
	 */
	public function get($site_id, $remote_instance_id = 0, $remote_method_id = '')
	{
		global $conf;
		dol_syslog(__METHOD__ . " site_id=$site_id, remote_instance_id=$remote_instance_id, remote_method_id=$remote_method_id");

		$sql = 'SELECT remote_zone_id, remote_instance_id';
		$sql .= ', remote_title, remote_order, remote_enabled, remote_method_id, remote_method_title, remote_method_description';
		$sql .= ', warehouse_id, old_entry FROM ' . MAIN_DB_PREFIX . $this->table_element;
		$sql .= ' WHERE site_id = ' . $site_id . ' AND entity = ' . $conf->entity;
		$sql .= " AND remote_instance_id = " . ((int) $remote_instance_id);
		$sql .= " AND remote_method_id = '" . $this->db->escape($remote_method_id) . "'";
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($obj = $this->db->fetch_object($resql)) {
				return array(
					'remote_zone_id' => $obj->remote_zone_id,
					'remote_instance_id' => $obj->remote_instance_id,
					'remote_title' => $obj->remote_title,
					'remote_order' => $obj->remote_order,
					'remote_enabled' => !empty($obj->remote_enabled),
					'remote_method_id' => $obj->remote_method_id,
					'remote_method_title' => $obj->remote_method_title,
					'remote_method_description' => $obj->remote_method_description,
					'warehouse_id' => $obj->warehouse_id > 0 ? $obj->warehouse_id : 0,
					'old_entry' => !empty($obj->old_entry) ? 1 : 0,
				);
			}

			return 0;
		} else {
			dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
			$this->errors[] = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 *  Get all remote shipping zone methods of a site
	 *
	 * @param   int         $site_id    Site ID
	 * @return  array|int               List of all remote shipping zone methods infos
	 * @throws  Exception
	 */
	public function get_all($site_id)
	{
		global $conf;
		dol_syslog(__METHOD__ . " site_id=$site_id");

		// Get all shipping zones
		$eCommerceRemoteShippingZones = new eCommerceRemoteShippingZones($this->db);
		$remote_shipping_zones = $eCommerceRemoteShippingZones->get_all($site_id);
		if (!is_array($remote_shipping_zones) && $remote_shipping_zones < 0) {
			$this->error = $eCommerceRemoteShippingZones->error;
			$this->errors = $eCommerceRemoteShippingZones->errors;
			return -1;
		}

		foreach ($remote_shipping_zones as $key1 => $remote_shipping_zone_info) {
			$sql = 'SELECT remote_instance_id, remote_title, remote_order, remote_enabled, remote_method_id';
			$sql .= ', remote_method_title, remote_method_description, warehouse_id, old_entry';
			$sql .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element;
			$sql .= ' WHERE site_id = ' . ((int) $site_id);
			$sql .= ' AND remote_zone_id = ' . ((int) $remote_shipping_zone_info['remote_id']);
			$sql .= ' AND entity = ' . ((int) $conf->entity);
			$resql = $this->db->query($sql);
			if ($resql) {
				$shipping_methods = [];
				while ($obj = $this->db->fetch_object($resql)) {
					$key2 = $obj->remote_instance_id . '_' . $obj->remote_method_id;
					$shipping_methods[$key2] = array(
						'remote_zone_id' => ((int) $obj->remote_zone_id),
						'remote_instance_id' => ((int) $obj->remote_instance_id),
						'remote_title' => ((string) $obj->remote_title),
						'remote_order' => ((int) $obj->remote_order),
						'remote_enabled' => !empty($obj->remote_enabled),
						'remote_method_id' => ((string) $obj->remote_method_id),
						'remote_method_title' => ((string) $obj->remote_method_title),
						'remote_method_description' => ((string) $obj->remote_method_description),
						'warehouse_id' => ((int) $obj->warehouse_id),
						'old_entry' => !empty($obj->old_entry) ? 1 : 0,
					);
				}

				$shipping_methods = dol_sort_array($shipping_methods, 'remote_order', 'asc', 0, 0, 1);

				$remote_shipping_zones[$key1]['methods'] = $shipping_methods;
			} else {
				dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
				$this->errors[] = $this->db->lasterror();
				return -1;
			}
		}

		return $remote_shipping_zones;
	}

	/**
	 *  Delete all remote shipping zone methods of a site
	 *
	 * @param   int         $site_id    Site ID
	 * @return  int                     >0 if OK, <0 if KO
	 * @throws  Exception
	 */
	public function delete_all($site_id)
	{
		global $conf;
		dol_syslog(__METHOD__ . " site_id=$site_id");

		// Delete all line for the site
		$sql = 'DELETE FROM ' . MAIN_DB_PREFIX . $this->table_element . ' WHERE site_id = ' . $site_id . ' AND entity = ' . $conf->entity;
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__ . ' SQL: ' . $sql . '; Errors: ' . $this->db->lasterror(), LOG_ERR);
			$this->errors[] = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Method to output saved errors
	 *
	 * @param   string      $separator      Separator between each error
	 * @return	string		                String with errors
	 */
	public function errorsToString($separator = ', ')
	{
		return (is_array($this->errors) ? join($separator, $this->errors) : '');
	}
}