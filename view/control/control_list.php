<?php
/* Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
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

/**
 *   	\file      view/control/control_list.php
 *		\ingroup    dolismq
 *		\brief      List page for control
 */

// Load DoliSMQ environment
if (file_exists('../dolismq.main.inc.php')) {
	require_once __DIR__ . '/../dolismq.main.inc.php';
} elseif (file_exists('../../dolismq.main.inc.php')) {
	require_once __DIR__ . '/../../dolismq.main.inc.php';
} else {
	die('Include of dolismq main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcategory.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contract.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ticket.lib.php';

// load dolismq libraries
require_once __DIR__ . '/../../lib/dolismq_sheet.lib.php';

require_once __DIR__.'/../../class/control.class.php';
require_once __DIR__.'/../../core/boxes/dolismqwidget1.php';
require_once __DIR__ . '/../../class/sheet.class.php';
require_once __DIR__ . '/../../class/control.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['other', 'bills', 'projects', 'orders', 'companies', 'product', 'productbatch', 'task', 'contracts']);

$action      = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction  = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files  = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm     = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel      = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect    = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'controllist'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss   = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')
$fromtype    = GETPOST('fromtype', 'alpha'); // element type
$fromid      = GETPOST('fromid', 'int'); //element id

// Load variable for pagination
$limit     = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page      = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) { $page = 0; }     // If $page is not defined, or '' or -1 or if we click on clear filters
$offset   = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize objects
// Technical objets
$object         = new Control($db);
$box            = new dolismqwidget1($db);
$categorystatic = new Categorie($db);
$sheet          = new Sheet($db);
$extrafields    = new ExtraFields($db);
$controlstatic  = new Control($db);

// View objects
$form = new Form($db);

$hookmanager->initHooks(array('controllist')); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
//$extrafields->fetch_name_optionals_label($object->table_element_line);

if (!empty($conf->categorie->enabled)) {
	$search_category_array = GETPOST("search_category_control_list", "array");
}

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// Default sort order (if not yet defined by previous GETPOST)
if (!$sortfield) { reset($object->fields); $sortfield="t.".key($object->fields); }   // Set here default search field. By default 1st field in definition. Reset is required to avoid key() to return null.
if (!$sortorder) $sortorder = "ASC";

$linkableElements = get_sheet_linkable_objects();

$objectPosition = 10;
foreach($linkableElements as $linkableElementType => $linkableElement) {
	$className  = $linkableElement['className'];

	if (!empty($fromtype) && $fromtype == $linkableElement['link_name']) {
		$objectLinked = new $className($db);
		$objectLinked->fetch($fromid);
	}

	$arrayfields['t.'.$linkableElement['post_name']] = [
		'type' => 'integer:'. $className .':' . $linkableElement['class_path'], 'label' => $linkableElement['langs'], 'enabled' => '1', 'position' => $objectPosition, 'notnull' => 0, 'visible' => 5, 'checked' => 1
	];

	$object->fields[$linkableElement['post_name']] = $arrayfields['t.'.$linkableElement['post_name']];
	$elementElementFields[$linkableElement['post_name']] = $linkableElement['link_name'];
	$linkNameElementCorrespondance[$linkableElement['link_name']] = $linkableElement;
	$objectPosition++;
}

// Initialize array of search criterias
$searchAll = GETPOST('search_all', 'alphanohtml') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_'.$key, 'alpha') !== '') $search[$key] = GETPOST('search_'.$key, 'alpha');
}

if(!empty($fromtype)) {
	$search_key = array_search($fromtype, $elementElementFields);
	$search[$search_key] = $fromid;
	switch ($fromtype) {
		case 'fk_sheet':
			$search['fk_sheet'] = $fromid;
			break;
		case 'user':
			$search['fk_user_controller'] = $fromid;
			break;
	}
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val) {
	if ($val['searchall']) $fieldstosearchall['t.'.$key] = $val['label'];
}

// Definition of array of fields for columns
$arrayfields = array();
foreach ($object->fields as $key => $val) {
	// If $val['visible']==0, then we never show the field
	if (!empty($val['visible'])) {
		$visible = (int) dol_eval($val['visible'], 1);
		$arrayfields['t.'.$key] = array(
			'label'=>$val['label'],
			'checked'=>(($visible < 0) ? 0 : 1),
			'enabled'=>($visible != 3 && dol_eval($val['enabled'], 1)),
			'position'=>$val['position'],
			'help'=>$val['help'],
            'css' => $val['css']
		);
	}
}

$arrayfields['t.status']['checked'] = 0;

// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_array_fields.tpl.php';

$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields    = dol_sort_array($arrayfields, 'position');

$permissiontoread   = $user->rights->dolismq->control->read;
$permissiontoadd    = $user->rights->dolismq->control->write;
$permissiontodelete = $user->rights->dolismq->control->delete;

// Security check
saturne_check_access($permissiontoread, $object);

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) { $action = 'list'; $massaction = ''; }
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction = ''; }

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
			$_POST[$key] = '';
		}
		$toselect = '';
		$search_array_options = array();
		$search_category_array = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha'))
	{
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'Control';
	$objectlabel = 'Control';
	$uploaddir = $conf->dolismq->dir_output;

	if (!$error && ($massaction == 'delete' || ($action == 'delete' && $confirm == 'yes')) && $permissiontodelete) {
		$db->begin();

		$objecttmp = new $objectclass($db);
		$nbok = 0;
		$TMsg = array();
		foreach ($toselect as $toselectid) {
			$result = $objecttmp->fetch($toselectid);
			if ($result > 0) {
				$result = $objecttmp->delete($user);

				if (empty($result)) { // if delete returns 0, there is at least one object linked
					$TMsg = array_merge($objecttmp->errors, $TMsg);
				} elseif ($result < 0) { // if delete returns is < 0, there is an error, we break and rollback later
					setEventMessages($objecttmp->error, $objecttmp->errors, 'errors');
					$error++;
					break;
				} else {
					$nbok++;
				}
			} else {
				setEventMessages($objecttmp->error, $objecttmp->errors, 'errors');
				$error++;
				break;
			}
		}

		if (empty($error)) {
			// Message for elements well deleted
			if ($nbok > 1) {
				setEventMessages($langs->trans("RecordsDeleted", $nbok), null, 'mesgs');
			} elseif ($nbok > 0) {
				setEventMessages($langs->trans("RecordDeleted", $nbok), null, 'mesgs');
			}

			// Message for elements which can't be deleted
			if (!empty($TMsg)) {
				sort($TMsg);
				setEventMessages('', array_unique($TMsg), 'warnings');
			}

			$db->commit();
		} else {
			$db->rollback();
		}

		//var_dump($listofobjectthirdparties);exit;
	}

//	include DOL_DOCUMENT_ROOT . '/core/actions_massactions.inc.php';
}

/*
 * View
 */

$now      = dol_now();
$help_url = '';
$title    = $langs->trans("ControlList");

saturne_header(0,'', $title, $help_url);
if (!empty($fromtype)) {
	print saturne_get_fiche_head($objectLinked, 'control', $langs->trans("Control"));

	$linkback = '<a href="'.DOL_URL_ROOT.'/'.$fromtype.'/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';

	saturne_banner_tab($objectLinked, 'ref', '', 0);
}

if ($fromid) {
	print '<div class="underbanner clearboth"></div>';
	print '<div class="fichehalfleft">';
	print '<br>';
	$controls = $controlstatic->fetchAll();

	if (is_array($controls) && !empty($controls)) {
		foreach ($controls as $control) {
			$control->fetchObjectLinked('','', $control->id, 'dolismq_' . $control->element);
			if (!empty($control->linkedObjectsIds)) {
				if (array_key_exists($fromtype, $control->linkedObjectsIds)) {
					$linkedObjectsIds = array_values($control->linkedObjectsIds[$fromtype]);
					if (in_array($fromid, $linkedObjectsIds)) {
						$categories = $categorystatic->getListForItem($control->id, $control->element);
						if (is_array($categories) && !empty($categories)) {
							foreach ($categories as $category) {
								$nbBox[$category['label']] = 1;
							}
						}
					}
				}
			}
		}

        if (is_array($nbBox) || is_object($nbBox)) {
            $box->loadBox();
            for ($i = 0; $i < count($nbBox); $i++) {
                $box->showBox($i,$i);
            }
        }
	}
	print '</div>';
}

$newcardbutton = dolGetButtonTitle($langs->trans('NewControl'), '', 'fa fa-plus-circle', dol_buildpath('/dolismq/view/control/control_card.php', 1).'?action=create', '', $permissiontoadd);

include_once '../../core/tpl/dolismq_control_list.tpl.php';

// End of page
llxFooter();
$db->close();
