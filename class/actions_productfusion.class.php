<?php
require_once 'product.merge.class.php';

class ActionsProductFusion
{
	/**
	 * Add more action buttons and handle the product merge action.
	 *
	 * @param array        $parameters   Parameters passed to the method.
	 * @param Product      $object       The relevant product object.
	 * @param string       $action       The current action.
	 * @param HookManager  $hookmanager  The hook manager.
	 * @return int
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $db, $langs, $conf;

		$form = new Form($db);
		$formconfirm = '';

		// If the action is 'merge', display a select box for the origin product and a confirmation form
		if ($action === 'merge') {
			$formquestion = [
				[
					'name' => 'prod_origin',
					'label' => $langs->trans('MergeOriginProduct'),
					'type' => 'other',
					'value' => $form->select_produits('', 'prod_origin', '', 0, 0, -1, 2, '', 0, [], 0, '1', 1, '', 0, '', [], 1)
				]
			];
			$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . "?id=" . $object->id, $langs->trans("MergeProducts"), $langs->trans("ConfirmMergeProducts"), "confirm_merge", $formquestion, 'no', 1, 250);
			$formconfirm .= $this->replaceAutocompletation();
		}
		// Handle the merge confirmation action
		elseif ($action === 'confirm_merge' && GETPOST('confirm') === 'yes') {
			$result = 0;
			$productToMerge = new Product($db);
			$productToMerge->fetch(GETPOST('id'));
			$productFusion = new ProductFusion($productToMerge, $db);
			$result = $productFusion->merge(GETPOST('prod_origin'));
			$printResult = $result ? "ok" : "not ok";
		}

		print $formconfirm;
		print '<a class="butActionDelete" href="card.php?action=merge&id=' . $object->id . '" title="' . dol_escape_htmltag($langs->trans("MergeProduct")) . '">' . $langs->trans('Merge') . '</a>' . "\n";

		return 0;
	}

	/**
	 * Replace the autocompletion style on the form confirmation.
	 *
	 * @return string
	 */
	public function replaceAutocompletation()
	{
		return "<style>.ui-autocomplete {
    position: absolute;
    z-index: 10000;
    overflow: auto;
    max-height: 200px;
}</style>";
	}
}


