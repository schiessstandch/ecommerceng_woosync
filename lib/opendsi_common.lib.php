<?php
/* Copyright (C) 2005-2013  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2017       Open-DSI                <support@open-dsi.fr>
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
 *	\file       htdocs/module/lib/opendsi_common.lib.php
 * 	\ingroup	module
 *	\brief      Common functions opendsi for the module
 */

/**
 * Gives the changelog. First check ChangeLog-la_LA.md then ChangeLog.md
 *
 * @param	string	  $moduleName			    Name of module
 *
 * @return  string                              Content of ChangeLog
 */
function opendsi_common_getChangeLog($moduleName)
{
    global $langs;
    $langs->load("admin");

    include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
    include_once DOL_DOCUMENT_ROOT . '/core/lib/geturl.lib.php';

    $filefound = false;

    $modulePath = dol_buildpath('/'.strtolower($moduleName), 0);

    // Define path to file README.md.
    // First check README-la_LA.md then README.md
    $pathoffile = $modulePath . '/ChangeLog-' . $langs->defaultlang . '.md';
    if (dol_is_file($pathoffile)) {
        $filefound = true;
    }
    if (!$filefound) {
        $pathoffile = $modulePath . '/ChangeLog.md';
        if (dol_is_file($pathoffile)) {
            $filefound = true;
        }
    }

    $content = '';

    if ($filefound)     // Mostly for external modules
    {
        $moduleUrlPath = dol_buildpath('/'.strtolower($moduleName), 1);
        $content = file_get_contents($pathoffile);

        if ((float)DOL_VERSION >= 6.0) {
            @include_once DOL_DOCUMENT_ROOT . '/core/lib/parsemd.lib.php';
            $content = dolMd2Html($content, 'parsedown', array('doc/' => $moduleUrlPath . '/doc/'));
        } else {
            $content = opendsi_common_dolMd2Html('codenaf', $content, 'parsedown', array('doc/' => $moduleUrlPath . '/doc/'));
        }

    }

    return $content;
}

/**
 * Function to parse MD content into HTML
 *
 * @param	string	  $moduleName			Name of module
 * @param	string	  $content			    MD content
 * @param   string    $parser               'parsedown' or 'nl2br'
 * @param   string    $replaceimagepath     Replace path to image with another path. Exemple: ('doc/'=>'xxx/aaa/')
 *
 * @return	string                          Parsed content
 */
function opendsi_common_dolMd2Html($moduleName, $content, $parser='parsedown',$replaceimagepath=null)
{
    if (is_array($replaceimagepath)) {
        foreach ($replaceimagepath as $key => $val) {
            $keytoreplace = '](' . $key;
            $valafter = '](' . $val;
            $content = preg_replace('/' . preg_quote($keytoreplace, '/') . '/m', $valafter, $content);
        }
    }

    if ($parser == 'parsedown') {
        dol_include_once('/' . strtolower($moduleName) . '/includes/parsedown/Parsedown.php');
        $Parsedown = new Parsedown();
        $content = $Parsedown->text($content);
    } else {
        $content = nl2br($content);
    }

    return $content;
}

/**
 * Generate natural SQL search string for a criteria (this criteria can be tested on one or several fields)
 *
 * @param 	string|string[]	$fields 	String or array of strings, filled with the name of all fields in the SQL query we must check (combined with a OR). Example: array("p.field1","p.field2")
 * @param 	string[]		$nullfields	Array of strings, filled with the name of the field in the SQL query we must check if the searched fields is NULL (when mode = 4). Example: array("p.field1"=>"p.field3")
 * @param 	string 			$value 		The value to look for.
 *                          		    If param $mode is 0, can contains several keywords separated with a space or |
 *                                         like "keyword1 keyword2" = We want record field like keyword1 AND field like keyword2
 *                                         or like "keyword1|keyword2" = We want record field like keyword1 OR field like keyword2
 *                             			If param $mode is 1, can contains an operator <, > or = like "<10" or ">=100.5 < 1000"
 *                             			If param $mode is 2, can contains a list of int id separated by comma like "1,3,4"
 *                             			If param $mode is 3, can contains a list of string separated by comma like "a,b,c"
 *                             			If param $mode is 4, can contains a datetime or a date and an operator <, > or = of string like "<=YYYY-MM-DD HH:mm:ss" or "<=YYYY-MM-DD HH:mm" or "=YYYY-MM-DD" or ">YYYY" ( support &, | and () )
 * @param	integer			$mode		0=value is list of keyword strings, 1=value is a numeric test (Example ">5.5 <10"), 2=value is a list of id separated with comma (Example '1,3,4')
 * @param	integer			$nofirstand	1=Do not output the first 'AND'
 * @return 	string 			$res 		The statement to append to the SQL query
 */
function opendsi_natural_search($fields, $value, $mode=0, $nofirstand=0, $nullfields=array())
{
    global $db;
    if ($mode == 4) {
        if (!is_array($fields)) $fields = array($fields);

        $criterias = array();
        if (preg_match_all('/\s*(&|\|)?\s*(\()?\s*([<>=]+)?\s*([0-9]{4})(?:\s*-\s*([0-9]{2}))?(?:\s*-\s*([0-9]{2}))?(?:\s+([0-9]{2})?(?:\s*\:\s*([0-9]{2}))?(?:\s*\:\s*([0-9]{2}))?)?\s*(\))?\s*/', $value, $matches, PREG_SET_ORDER)) {
            require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
            foreach ($matches as $match) {
                $operatorSQL = !empty($match[1]) && $match[1] == '&' ? ' AND ' : ' OR ';
                $openingParenthesis = !empty($match[2]) ? $match[2] : '';
                $operator = !empty($match[3]) ? $match[3] : '=';
                $end_limit = $operator == '<=' || $operator == '>';
                $date = $match[4];                                                                                                                          // Year
                $date .= '-' . (!empty($match[5]) ? $match[5] : ($end_limit ? '12' : '00'));                                                                // Month
                $date .= '-' . (!empty($match[6]) ? $match[6] : ($end_limit ? (!empty($match[5]) ? dol_get_last_day($match[4], $match[5]) : '31') : '00')); // Day
                $date .= ' ' . (!empty($match[7]) ? $match[7] : ($end_limit ? '23' : '00'));                                                                // Hour
                $date .= ':' . (!empty($match[8]) ? $match[8] : ($end_limit ? '59' : '00'));                                                                // Minute
                $date .= ':' . (!empty($match[9]) ? $match[9] : ($end_limit ? '59' : '00'));                                                                // second
                $date = "'" . $db->escape($date) . "'";
                $closingParenthesis = !empty($match[10]) ? $match[10] : '';

                $not_complete = empty($match[9]) || empty($match[8]) || empty($match[7]) || empty($match[6]) || empty($match[5]);
                if ($operator == '=' && $not_complete) {
                    $criterias[] = array($operatorSQL, $openingParenthesis.'(', '>=', $date, $closingParenthesis);

                    $end_limit = true;
                    $date = $match[4];                                                                                                                          // Year
                    $date .= '-' . (!empty($match[5]) ? $match[5] : ($end_limit ? '12' : '00'));                                                                // Month
                    $date .= '-' . (!empty($match[6]) ? $match[6] : ($end_limit ? (!empty($match[5]) ? dol_get_last_day($match[4], $match[5]) : '31') : '00')); // Day
                    $date .= ' ' . (!empty($match[7]) ? $match[7] : ($end_limit ? '23' : '00'));                                                                // Hour
                    $date .= ':' . (!empty($match[8]) ? $match[8] : ($end_limit ? '59' : '00'));                                                                // Minute
                    $date .= ':' . (!empty($match[9]) ? $match[9] : ($end_limit ? '59' : '00'));                                                                // second
                    $date = "'" . $db->escape($date) . "'";
                    $operatorSQL = ' AND ';
                    $openingParenthesis = '';
                    $operator = '<=';
                    $closingParenthesis .= ')';
                }

                $criterias[] = array($operatorSQL, $openingParenthesis, $operator, $date, $closingParenthesis);
            }
        }

        $to_print = array();
        foreach ($fields as $field) {
            $ifnull = isset($nullfields[$field]) ? $nullfields[$field] : '';
            $statementSQL = '';
            foreach ($criterias as $criteria) {
                $statementSQL .= $criteria[0] . $criteria[1] . (!empty($ifnull) ? $db->ifsql($field . ' IS NULL', $ifnull, $field) : $field) . ' ' . $criteria[2] . ' ' . $criteria[3] . $criteria[4];
            }
            $statementSQL = preg_replace('/^( (?:AND|OR) )/', '', $statementSQL);
            if (!empty($statementSQL)) $to_print[] = $statementSQL;
        }

        return (!empty($to_print) ? ($nofirstand ? "" : " AND ") . "((" . implode(') OR (', $to_print) . "))" : '');
    } else {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/functions.lib.php';
        return natural_search($fields, $value, $mode, $nofirstand);
    }
}

/**
 *  Return the handle of the object of the specified element
 *
 * @param	DoliDB	    $db		        Database handler
 * @param	string	    $element_type	Type of the element
 * @param	int		    $element_id	    Id of the element
 * @return 	object|int                  <0 if KO otherwise object handler
 */
function opendsi_get_object($db, $element_type, $element_id)
{
	global $conf;

	$element_prop = getElementProperties($element_type);
	if (is_array($element_prop) && $conf->{$element_prop['module']}->enabled) {
		if (dol_include_once('/' . $element_prop['classpath'] . '/' . $element_prop['classfile'] . '.class.php')) {
			if (class_exists($element_prop['classname'], false)) {
				$objecttmp = new $element_prop['classname']($db);
				$ret = $objecttmp->fetch($element_id);
				if ($ret >= 0) {
					return $objecttmp;
				}
			}
		}
	}

	// Parse element/subelement (ex: project_task)
	$element = $subelement = $element_type;
	if (preg_match('/^([^_]+)_([^_]+)/i', $element_type, $regs)) {
		$element = $regs [1];
		$subelement = $regs [2];
	}

	$classpath = $element;
	if ($element_type == 'order' || $element_type == 'commande') {
		$classpath = $subelement = 'commande';
	} else if ($element_type == 'propal') {
		$classpath = 'comm/propal';
		$subelement = 'propal';
	} else if ($element_type == 'facture') {
		$classpath = 'compta/facture';
		$subelement = 'facture';
	} else if ($element_type == 'contract') {
		$classpath = $subelement = 'contrat';
	} else if ($element_type == 'shipping') {
		$classpath = $subelement = 'expedition';
	} else if ($element_type == 'deplacement') {
		$classpath = 'compta/deplacement';
		$subelement = 'deplacement';
	} else if ($element_type == 'order_supplier') {
		$classpath = 'fourn';
		$subelement = 'fournisseur.commande';
	} else if ($element_type == 'invoice_supplier') {
		$classpath = 'fourn';
		$subelement = 'fournisseur.facture';
	} else if ($element_type == 'chargesociales') {
		$classpath = 'compta/sociales';
	} else if ($element_type == 'tva') {
		$classpath = 'compta/tva';
	} else if ($element_type == 'salary') {
		$classpath = 'salaries';
	} else if ($element_type == 'payment_various') {
		$classpath = 'compta/bank';
		$subelement = 'paymentvarious';
	} else if ($element_type == 'bank_account') {
		$classpath = 'compta/bank';
	} else if ($element_type == 'stock') {
		$classpath = 'product/stock';
		$subelement = 'entrepot';
	}

	$result = dol_include_once('/' . $classpath . '/class/' . $subelement . '.class.php');
	if (!$result) {
		return -1;
	}

	if ($element_type == 'order_supplier') {
		$classname = 'CommandeFournisseur';
	} else if ($element_type == 'invoice_supplier') {
		$classname = 'FactureFournisseur';
	} else if ($element_type == 'payment_various') {
		$classname = 'PaymentVarious';
	} else if ($element_type == 'stock') {
		$classname = 'Entrepot';
	} else $classname = ucfirst($subelement);

	if (!class_exists($classname, false)) {
		return -1;
	}

	$srcobject = new $classname($db);
	$result = $srcobject->fetch($element_id);
	if ($result < 0) {
		return -1;
	}

	return $srcobject;
}

/**
 *  Return info of the documents path of the element
 *
 * @param	DoliDB	    $db		        Database handler
 * @param	User	    $user		    User handler
 * @param	string	    $element_type	Type of the element
 * @param	int		    $element_id	    Id of the element
 * @return 	array						Return info of the documents path of the element array('module_part' => '', 'file_dir' => '')
 */
function opendsi_get_object_documents_path_info($db, User $user, $element_type, $element_id)
{
	global $conf, $hookmanager;

	$module_part = '';
	$module_sub_dir = '';
	$file_dir = '';

	if (opendsi_check_user_access_to_object($user, $element_type, $element_id)) {
		$object = opendsi_get_object($db, $element_type, $element_id);
		if (is_object($object) && $object->id > 0) {
			// Add custom element by hook
			if (!is_object($hookmanager)) {
				include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
				$hookmanager = new HookManager($db);
			}
			$hookmanager2 = clone $hookmanager; // Génère des erreurs de resultat disparaissant si appelé dans une autre hooks donc on copie la hook
			$hookmanager2->initHooks(array('opendsidao'));
			$parameters = array('module_part' => &$module_part, 'file_dir' => &$file_dir, 'module_sub_dir' => &$module_sub_dir);
			$reshook = $hookmanager2->executeHooks('opendsiGetObjectDocumentsPathInfo', $parameters); // Note that $action and $object may have been
			if (empty($reshook)) {
				$module_part = $element_type;
				$module_sub_dir = dol_sanitizeFileName($object->ref);

				if ($element_type == 'order_supplier') {
					$module_part = 'commande_fournisseur';
				} elseif ($element_type == 'invoice_supplier') {
					$module_part = 'facture_fournisseur';
					$module_sub_dir = get_exdir($object->id, 2, 0, 0, $object, 'invoice_supplier') . $module_sub_dir;
				} elseif ($element_type == 'contrat') {
					$module_part = 'contract';
				} elseif ($element_type == 'fichinter') {
					$module_part = 'ficheinter';
				} elseif ($element_type == 'project_task') {
					require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
					$projectstatic = new Project($db);
					$projectstatic->fetch($object->fk_project);
					$module_sub_dir = dol_sanitizeFileName($projectstatic->ref) . '/' . $module_sub_dir;
                } elseif ($element_type == 'product') {
                    if (!empty($conf->product->enabled) || !empty($conf->service->enabled)) {
                        if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
                            $module_sub_dir = substr(substr("000" . $object->id, -2), 1, 1) . '/' . substr(substr("000" . $object->id, -2), 0, 1) . '/' . $object->id . "/photos";
                        } else {
                            $module_sub_dir = get_exdir(0, 0, 0, 1, $object, 'product');
                        }
                    }
                } elseif ($element_type == 'action') {
					// Note: This 'elseif' block had been deleted, and it caused a bug in agenda display.
					// It may be linked to DLB version.
					// If this bug happens anew, contact kkhelifa or tnegre by open-dsi.
                    $module_sub_dir = $object->id;
					$module_part	= 'actions';
                } elseif ($element_type == 'salary') {
                    $module_part = 'salaries';
                    $module_sub_dir = dol_sanitizeFileName($object->id);
                } elseif ($element_type == 'payment_various') {
                    $module_part = 'banque';
                    $module_sub_dir = dol_sanitizeFileName($object->id);
                } elseif ($element_type == 'bank_account') {
                    $module_part = 'bank';
                } elseif ($element_type == 'webhost') {
                    $module_sub_dir = "/webhost/" . dol_sanitizeFileName($object->id);
                } elseif ($element_type == 'webhost_webinstance') {
                    $module_part = 'webhost';
                    $module_sub_dir = "/webinstance/" . dol_sanitizeFileName($object->id);
                } elseif ($element_type == 'webhost_webmodule') {
                    $module_part = 'webhost';
                    $module_sub_dir = "/webmodule/" . dol_sanitizeFileName($object->id);
                } elseif ($element_type == 'usergroup') {
					if (DOL_VERSION > 14) {
						$module_part = 'user';
					}
                }


				require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
				$check_access = dol_check_secure_access_document($module_part, $module_sub_dir, $conf->entity, $user, $object->ref);
				if ($check_access['accessallowed']) {
					$file_dir = $check_access['original_file'];
				} else {
					$module_part = '';
				}
			}
		}
	}

	return array('module_part' => $module_part, 'file_dir' => $file_dir, 'module_sub_dir' => $module_sub_dir);
}

/**
 *  Return if the user is authorized to access to the specified element
 *
 * @param	User	    $user		    User handler
 * @param	string	    $element_type	Type of the element
 * @param	int		    $element_id	    Id of the element
 * @return 	bool
 */
function opendsi_check_user_access_to_object(User $user, $element_type, $element_id)
{
	global $db, $hookmanager;

	// Add custom element by hook
	if (!is_object($hookmanager)) {
		include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
		$hookmanager = new HookManager($db);
	}
	$hookmanager2 = clone $hookmanager; // Génère des erreurs de resultat disparaissant si appelé dans une autre hooks donc on copie la hook
	$hookmanager2->initHooks(array('opendsidao'));
	$parameters = array();
	$reshook = $hookmanager2->executeHooks('opendsiCheckUserAccessToObject', $parameters); // Note that $action and $object may have been
	if (empty($reshook)) {
		$features = $element_type;
		$objectid = $element_id;
		$tableandshare = '';
		$feature2 = '';
		$dbt_keyfield = 'fk_soc';
		$dbt_select = 'rowid';
		$objcanvas = null;
        $v14p = version_compare(DOL_VERSION, '4.0.0', '>=');

		if ($element_type == 'invoice_supplier') {
			$features = 'fournisseur';
			$tableandshare = 'facture_fourn';
			$feature2 = 'facture';
		} elseif ($element_type == 'order_supplier') {
			$features = 'fournisseur';
			$tableandshare = 'commande_fournisseur';
			$feature2 = 'commande';
		} elseif ($element_type == 'fichinter') {
			$features = 'ficheinter';
			$tableandshare = 'fichinter';
		} elseif ($element_type == 'project') {
			$features = 'projet';
			$tableandshare = 'projet&project';
		} elseif ($element_type == 'project_task') {
            require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
            $object = new Task($db);
            $object->fetch($element_id);
            $objectid = $object->fk_project;
            $features = 'projet';
            $tableandshare = 'projet&project';
		} elseif ($element_type == 'expensereport') {
			$objectid = 0;
			$tableandshare = 'expensereport';
        } elseif ($element_type == 'action') {
            $features = 'agenda';
            $tableandshare = 'actioncomm&societe';
            $feature2 = 'myactions|allactions';
            $dbt_select = 'id';
        } elseif ($element_type == 'product') {
            $features = 'produit|service';
            $tableandshare = 'product&product';
        } elseif ($element_type == 'tva') {
            $features = 'tax';
            $tableandshare = 'tva';
            $feature2 = 'charges';
        } elseif ($element_type == 'salary') {
            $features = 'salaries';
            $tableandshare = 'salary';
        } elseif ($element_type == 'payment_various') {
            $features = 'banque';
            $objectid = '';
        } elseif ($element_type == 'bank_account') {
            $features = 'banque';
            $tableandshare = 'bank_account';
        } elseif ($element_type == 'webhost_webinstance') {
            return empty($user->rights->webhost->instance->read) ? 0 : 1;
        } elseif ($element_type == 'webhost_webmodule') {
            return 1;
        }

		return opendsi_restrictedArea($user, $features, $objectid, $tableandshare, $feature2, $dbt_keyfield, $dbt_select, $objcanvas);
	}

	// $reshook: =-1 not authorized, =1 authorized
	return $reshook > 0 ? 1 : 0;
}

/**
 *	Check permissions of a user to show a page and an object. Check read permission.
 * 	If GETPOST('action','aZ09') defined, we also check write and delete permission.
 *
 *	@param	User	$user      	  	User to check
 *	@param  string	$features	    Features to check (it must be module name. Examples: 'societe', 'contact', 'produit&service', 'produit|service', ...)
 *	@param  int		$objectid      	Object ID if we want to check a particular record (optional) is linked to a owned thirdparty (optional).
 *	@param  string	$tableandshare  'TableName&SharedElement' with Tablename is table where object is stored. SharedElement is an optional key to define where to check entity for multicompany modume. Param not used if objectid is null (optional).
 *	@param  string	$feature2		Feature to check, second level of permission (optional). Can be a 'or' check with 'level1|level2'.
 *  @param  string	$dbt_keyfield   Field name for socid foreign key if not fk_soc. Not used if objectid is null (optional)
 *  @param  string	$dbt_select     Field name for select if not rowid. Not used if objectid is null (optional)
 *  @param	Canvas	$objcanvas		Object canvas
 * 	@return	int						Always 1, die process if not allowed
 *  @see dol_check_secure_access_document
 */
function opendsi_restrictedArea($user, $features, $objectid=0, $tableandshare='', $feature2='', $dbt_keyfield='fk_soc', $dbt_select='rowid', $objcanvas=null)
{
	global $db, $conf;

    $v14p = version_compare(DOL_VERSION, '4.0.0', '>=');

    if ($v14p) {
        $result = restrictedArea($user, $features, $objectid, $tableandshare, $feature2, $dbt_keyfield, $dbt_select, 0, 1);
        return $result;
    }

    //dol_syslog("functions.lib:restrictedArea $feature, $objectid, $dbtablename,$feature2,$dbt_socfield,$dbt_select");
	//print "user_id=".$user->id.", features=".$features.", feature2=".$feature2.", objectid=".$objectid;
	//print ", dbtablename=".$dbtablename.", dbt_socfield=".$dbt_keyfield.", dbt_select=".$dbt_select;
	//print ", perm: ".$features."->".$feature2."=".($user->rights->$features->$feature2->lire)."<br>";

	// If we use canvas, we try to use function that overlod restrictarea if provided with canvas
	if (is_object($objcanvas))
	{
		if (method_exists($objcanvas->control,'restrictedArea')) return $objcanvas->control->restrictedArea($user,$features,$objectid,$tableandshare,$feature2,$dbt_keyfield,$dbt_select);
	}

	if ($dbt_select != 'rowid' && $dbt_select != 'id') $objectid = "'".$objectid."'";

	// Features/modules to check
	$featuresarray = array($features);
	if (preg_match('/&/', $features)) $featuresarray = explode("&", $features);
	elseif (preg_match('/\|/', $features)) $featuresarray = explode("|", $features);

	// More subfeatures to check
	if (! empty($feature2)) $feature2 = explode("|", $feature2);

	// More parameters
	$params = explode('&', $tableandshare);
	$dbtablename=(! empty($params[0]) ? $params[0] : '');
	$sharedelement=(! empty($params[1]) ? $params[1] : $dbtablename);

	$listofmodules=explode(',',$conf->global->MAIN_MODULES_FOR_EXTERNAL);

	// Check read permission from module
	$readok=1; $nbko=0;
	foreach ($featuresarray as $feature)	// first we check nb of test ko
	{
		$featureforlistofmodule=$feature;
		if ($featureforlistofmodule == 'produit') $featureforlistofmodule='product';
		if (! empty($user->socid) && ! empty($conf->global->MAIN_MODULES_FOR_EXTERNAL) && ! in_array($featureforlistofmodule,$listofmodules))	// If limits on modules for external users, module must be into list of modules for external users
		{
			$readok=0; $nbko++;
			continue;
		}

		if ($feature == 'societe')
		{
			if (! $user->rights->societe->lire && ! $user->rights->fournisseur->lire) { $readok=0; $nbko++; }
		}
		elseif ($feature == 'contact')
		{
			if (! $user->rights->societe->contact->lire) { $readok=0; $nbko++; }
		}
		elseif ($feature == 'produit|service')
		{
			if (! $user->rights->produit->lire && ! $user->rights->service->lire) { $readok=0; $nbko++; }
		}
		elseif ($feature == 'prelevement')
		{
			if (! $user->rights->prelevement->bons->lire) { $readok=0; $nbko++; }
		}
		elseif ($feature == 'cheque')
		{
			if (! $user->rights->banque->cheque) { $readok=0; $nbko++; }
		}
		elseif ($feature == 'projet')
		{
			if (! $user->rights->projet->lire && ! $user->rights->projet->all->lire) { $readok=0; $nbko++; }
		}
		elseif (! empty($feature2))	// This should be used for future changes
		{
			$tmpreadok=1;
			foreach($feature2 as $subfeature)
			{
				if (! empty($subfeature) && empty($user->rights->$feature->$subfeature->lire) && empty($user->rights->$feature->$subfeature->read)) { $tmpreadok=0; }
				elseif (empty($subfeature) && empty($user->rights->$feature->lire) && empty($user->rights->$feature->read)) { $tmpreadok=0; }
				else { $tmpreadok=1; break; } // Break is to bypass second test if the first is ok
			}
			if (! $tmpreadok)	// We found a test on feature that is ko
			{
				$readok=0;	// All tests are ko (we manage here the and, the or will be managed later using $nbko).
				$nbko++;
			}
		}
		elseif (! empty($feature) && ($feature!='user' && $feature!='usergroup'))		// This is for old permissions
		{
			if (empty($user->rights->$feature->lire)
				&& empty($user->rights->$feature->read)
				&& empty($user->rights->$feature->run)) { $readok=0; $nbko++; }
		}
	}

	// If a or and at least one ok
	if (preg_match('/\|/', $features) && $nbko < count($featuresarray)) $readok=1;

	if (! $readok) return 0;
	//print "Read access is ok";

	// Check write permission from module
	$createok=1; $nbko=0;
	if (GETPOST('action','aZ09')  == 'create')
	{
		foreach ($featuresarray as $feature)
		{
			if ($feature == 'contact')
			{
				if (! $user->rights->societe->contact->creer) { $createok=0; $nbko++; }
			}
			elseif ($feature == 'produit|service')
			{
				if (! $user->rights->produit->creer && ! $user->rights->service->creer) { $createok=0; $nbko++; }
			}
			elseif ($feature == 'prelevement')
			{
				if (! $user->rights->prelevement->bons->creer) { $createok=0; $nbko++; }
			}
			elseif ($feature == 'commande_fournisseur')
			{
				if (! $user->rights->fournisseur->commande->creer) { $createok=0; $nbko++; }
			}
			elseif ($feature == 'banque')
			{
				if (! $user->rights->banque->modifier) { $createok=0; $nbko++; }
			}
			elseif ($feature == 'cheque')
			{
				if (! $user->rights->banque->cheque) { $createok=0; $nbko++; }
			}
			elseif (! empty($feature2))	// This should be used
			{
				foreach($feature2 as $subfeature)
				{
					if (empty($user->rights->$feature->$subfeature->creer)
						&& empty($user->rights->$feature->$subfeature->write)
						&& empty($user->rights->$feature->$subfeature->create)) { $createok=0; $nbko++; }
					else { $createok=1; break; } // Break to bypass second test if the first is ok
				}
			}
			elseif (! empty($feature))		// This is for old permissions ('creer' or 'write')
			{
				//print '<br>feature='.$feature.' creer='.$user->rights->$feature->creer.' write='.$user->rights->$feature->write;
				if (empty($user->rights->$feature->creer)
					&& empty($user->rights->$feature->write)
					&& empty($user->rights->$feature->create)) { $createok=0; $nbko++; }
			}
		}

		// If a or and at least one ok
		if (preg_match('/\|/', $features) && $nbko < count($featuresarray)) $createok=1;

		if (! $createok) return 0;
		//print "Write access is ok";
	}

	// Check create user permission
	$createuserok=1;
	if (GETPOST('action','aZ09') == 'confirm_create_user' && GETPOST("confirm") == 'yes')
	{
		if (! $user->rights->user->user->creer) $createuserok=0;

		if (! $createuserok) return 0;
		//print "Create user access is ok";
	}

	// Check delete permission from module
	$deleteok=1; $nbko=0;
	if ((GETPOST('action','aZ09')  == 'confirm_delete' && GETPOST("confirm") == 'yes') || GETPOST('action','aZ09')  == 'delete')
	{
		foreach ($featuresarray as $feature)
		{
			if ($feature == 'contact')
			{
				if (! $user->rights->societe->contact->supprimer) $deleteok=0;
			}
			elseif ($feature == 'produit|service')
			{
				if (! $user->rights->produit->supprimer && ! $user->rights->service->supprimer) $deleteok=0;
			}
			elseif ($feature == 'commande_fournisseur')
			{
				if (! $user->rights->fournisseur->commande->supprimer) $deleteok=0;
			}
			elseif ($feature == 'banque')
			{
				if (! $user->rights->banque->modifier) $deleteok=0;
			}
			elseif ($feature == 'cheque')
			{
				if (! $user->rights->banque->cheque) $deleteok=0;
			}
			elseif ($feature == 'ecm')
			{
				if (! $user->rights->ecm->upload) $deleteok=0;
			}
			elseif ($feature == 'ftp')
			{
				if (! $user->rights->ftp->write) $deleteok=0;
			}elseif ($feature == 'salaries')
			{
				if (! $user->rights->salaries->delete) $deleteok=0;
			}
			elseif ($feature == 'salaries')
			{
				if (! $user->rights->salaries->delete) $deleteok=0;
			}
			elseif (! empty($feature2))	// This should be used for future changes
			{
				foreach($feature2 as $subfeature)
				{
					if (empty($user->rights->$feature->$subfeature->supprimer) && empty($user->rights->$feature->$subfeature->delete)) $deleteok=0;
					else { $deleteok=1; break; } // For bypass the second test if the first is ok
				}
			}
			elseif (! empty($feature))		// This is for old permissions
			{
				//print '<br>feature='.$feature.' creer='.$user->rights->$feature->supprimer.' write='.$user->rights->$feature->delete;
				if (empty($user->rights->$feature->supprimer)
					&& empty($user->rights->$feature->delete)
					&& empty($user->rights->$feature->run)) $deleteok=0;
			}
		}

		// If a or and at least one ok
		if (preg_match('/\|/', $features) && $nbko < count($featuresarray)) $deleteok=1;

		if (! $deleteok) return 0;
		//print "Delete access is ok";
	}

	// If we have a particular object to check permissions on, we check this object
	// is linked to a company allowed to $user.
	if (! empty($objectid) && $objectid > 0)
	{
		require_once DOL_DOCUMENT_ROOT . '/core/lib/security.lib.php';
		$ok = checkUserAccessToObject($user, $featuresarray, $objectid, $tableandshare, $feature2, $dbt_keyfield, $dbt_select);
		return $ok ? 1 : 0;
	}

	return 1;
}
