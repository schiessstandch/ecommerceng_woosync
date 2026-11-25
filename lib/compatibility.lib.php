<?php
/* Copyright (C) 2026 Open-DSI <support@open-dsi.fr>
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
 * or see http://www.gnu.org/
 */

/**
 * \file        lib/compatibility.lib.php
 * \ingroup     ecommerceng
 * \brief       Lightweight cross-version compatibility helpers.
 *              This file must stay free of heavy includes so it can be
 *              loaded cheaply from hot paths (triggers, module descriptor).
 */

if (!function_exists('ecommercengIsModEnabled')) {
	/**
	 * Check whether a module is enabled, compatible with Dolibarr < v15.
	 *
	 * isModEnabled() was introduced in Dolibarr v15. On older versions we
	 * fall back to reading $conf->{module}->enabled.
	 *
	 * @param   string  $module     Module name (default 'ecommerceng')
	 * @return  bool                True if the module is enabled
	 */
	function ecommercengIsModEnabled($module = 'ecommerceng')
	{
		if (function_exists('isModEnabled')) {
			return isModEnabled($module);
		}
		global $conf;
		return !empty($conf->$module->enabled);
	}
}

if (!function_exists('ecommercengGetGlobalString')) {
	/**
	 * Read a global constant as a string, compatible with Dolibarr < v15.
	 *
	 * getDolGlobalString() was introduced in Dolibarr v15. This wrapper reads
	 * $conf->global->{key} directly (guarded against undefined property to avoid
	 * PHP 8.x warnings) so the default value works on every version.
	 *
	 * @param   string  $key        Constant name
	 * @param   string  $default    Value returned when the constant is not set
	 * @return  string              Constant value or default
	 */
	function ecommercengGetGlobalString($key, $default = '')
	{
		global $conf;
		return isset($conf->global->$key) ? (string) $conf->global->$key : $default;
	}
}

if (!function_exists('ecommercengGetGlobalInt')) {
	/**
	 * Read a global constant as an int, compatible with Dolibarr < v15.
	 *
	 * getDolGlobalInt()'s default parameter is not available on older Dolibarr
	 * versions, so this wrapper handles the default itself (reads $conf->global
	 * directly, guarded), guaranteeing the default works on every version.
	 *
	 * @param   string  $key        Constant name
	 * @param   int     $default    Value returned when the constant is not set/empty
	 * @return  int                 Constant value (cast to int) or default
	 */
	function ecommercengGetGlobalInt($key, $default = 0)
	{
		global $conf;
		return (isset($conf->global->$key) && $conf->global->$key !== '') ? (int) $conf->global->$key : $default;
	}
}

if (!function_exists('ecommercengUserHasRight')) {
	/**
	 * Check a user permission, compatible with Dolibarr < v16.
	 *
	 * User::hasRight() was introduced in Dolibarr v16. On older versions we
	 * fall back to reading $user->rights->{module}->{perm1}[->{perm2}].
	 *
	 * @param   User    $user       User object
	 * @param   string  $module     Module name (e.g. 'ecommerceng')
	 * @param   string  $perm1      Permission level 1 (e.g. 'read', 'write', 'site')
	 * @param   string  $perm2      Permission level 2 (optional)
	 * @return  bool                True if the permission is granted
	 */
	function ecommercengUserHasRight($user, $module, $perm1, $perm2 = '')
	{
		if (is_object($user) && method_exists($user, 'hasRight')) {
			return (bool) $user->hasRight($module, $perm1, $perm2);
		}
		if (empty($user) || empty($user->rights) || empty($user->rights->$module)) {
			return false;
		}
		$rights = $user->rights->$module;
		if ($perm2 !== '') {
			return !empty($rights->$perm1) && !empty($rights->$perm1->$perm2);
		}
		return !empty($rights->$perm1);
	}
}
