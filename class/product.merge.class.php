<?php
/*
 * Copyright (C) 2014 Oscim       <oscim@users.sourceforge.net>
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
 *  \file
 *  \ingroup
 *  \brief      This file is an example for a CRUD class file (Create/Read/Update/Delete)
 *
 */

require_once(DOL_DOCUMENT_ROOT . "/product/class/product.class.php");

class ProductFusion extends Product
{


	public $product;
	public $db;

	/**
	 * @param $product
	 */
	public function __construct(Product $product, $db)
	{
		$this->product = $product;
		$this->db = $db;
	}


	/**
	 *  Delete a product from database (if not used)
	 * @param id          Product id
	 * @return        int            < 0 if KO, >= 0 if OK
	 */
	function merge($id_delete)
	{

		global $conf, $user, $langs;
		$error = 0;

		if ($user->rights->produit->supprimer) {
			$prod_use = 0;

			$this->db->begin();

			if ($prod_use == 0) {


				$elements = $this->childtables;


				foreach ($elements as $table) {
					$sql = "UPDATE " . MAIN_DB_PREFIX . $table;
					$sql .= " SET  fk_product = '" . $this->product->id . "' WHERE fk_product = " . $id_delete;
					$result = $this->db->query($sql);
					if ($result < 0) {
						$error++;
					}
				}
				// Récupération des éléments de la base de données
				$sql = "SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE COLUMN_NAME = 'fk_product'";

				$elements = $this->db->query($sql);

				if ($elements) {
					// Parcours des éléments trouvés et exécution des requêtes UPDATE
					while ($row = $this->db->fetch_object($elements)) {
						$table_name = $row->TABLE_NAME;

						$update_sql = "UPDATE " . $table_name;
						$update_sql .= " SET fk_product = '" . $this->product->id . "' WHERE fk_product = " . $id_delete;

						$result = $this->db->query($update_sql);
						if ($result < 0) {
							$error++;
						}
					}
				} else {
					// Gestion d'erreur si la requête SELECT échoue
					// ... code pour gérer l'erreur ...
				}


				/**
				 * Stock product
				 * Deport sur les nouveau produit, puis suppression rows
				 */

				if (!$error) {
					$sql = "SELECT reel, fk_entrepot FROM  " . MAIN_DB_PREFIX . "product_stock";
					$sql .= "  WHERE fk_product = " . $id_delete;

					$result = $this->db->query($sql);
					$num = $this->db->num_rows($result);
					$tmp_sqr = 0;
					$i = 0;
					require_once(DOL_DOCUMENT_ROOT . "/product/stock/class/mouvementstock.class.php");
					$mouvP = new MouvementStock($this->db);

					while ($i < $num) {
						$obj = $this->db->fetch_object($result);
						$i++;

						if ($obj->reel > 0)
							$result = $mouvP->reception($user, $this->product->id, $obj->fk_entrepot, $obj->reel, 0, $langs->trans("ProductsMerge"));
						else
							$result = $mouvP->livraison($user, $this->product->id, $obj->fk_entrepot, $obj->reel, 0, $langs->trans("ProductsMerge"));

						if ($result < 0) {
							$error++;
						}
					}
					$sqlz = "DELETE from " . MAIN_DB_PREFIX . "product_stock";
					$sqlz .= " WHERE fk_product = " . $id_delete;
					$resultz = $this->db->query($sqlz);
				}

				/**
				 * Composed products
				 */

				if (!$error) {

					$sql = "SELECT fk_product_fils FROM  " . MAIN_DB_PREFIX . "product_association";
					$sql .= "  WHERE fk_product_pere = " . $id_delete;

					$result = $this->db->query($sql);
					$num = $this->db->num_rows($result);
					$i = 0;
					while ($i < $num) {
						$obj = $this->db->fetch_object($result);
						$i++;

						if ($obj->fk_product_fils == $this->product->id)
							$error++;
					}

					$sql = "UPDATE " . MAIN_DB_PREFIX . "product_association";
					$sql .= " SET fk_product_pere ='" . $this->product->id . "'  WHERE fk_product_pere = " . $id_delete;

					dol_syslog("Products::Merge sql=" . $sql, LOG_DEBUG);
					if ($this->db->query($sql)) {
					} else {
						$error++;

						$this->error = $this->db->lasterror();
						dol_syslog("Products::Merge erreur -2 " . $this->error, LOG_ERR);
					}
				}


				/**
				 * variant products
				 */
				if (!$error) {

					$sql = "UPDATE " . MAIN_DB_PREFIX . "product_attribute_combination";
					$sql .= " SET fk_product_child ='" . $this->product->id . "'  WHERE fk_product_child = " . $id_delete;

					dol_syslog("Products::Merge sql=" . $sql, LOG_DEBUG);
					if ($this->db->query($sql)) {
					} else {
						$error++;

						$this->error = $this->db->lasterror();
						dol_syslog("Products::Merge erreur -2 " . $this->error, LOG_ERR);
					}


					$sql = "UPDATE " . MAIN_DB_PREFIX . "product_attribute_combination";
					$sql .= " SET fk_product_parent ='" . $this->product->id . "'  WHERE fk_product_parent = " . $id_delete;
					dol_syslog("Products::Merge sql=" . $sql, LOG_DEBUG);
					if ($this->db->query($sql)) {
					} else {
						$error++;

						$this->error = $this->db->lasterror();
						dol_syslog("Products::Merge erreur -2 " . $this->error, LOG_ERR);
					}
				}

				if (!$error) {

					$sql = "SELECT fk_product_pere FROM  " . MAIN_DB_PREFIX . "product_association";
					$sql .= "  WHERE fk_product_fils = " . $id_delete;

					$result = $this->db->query($sql);
					$num = $this->db->num_rows($result);
					$i = 0;
					while ($i < $num) {
						$obj = $this->db->fetch_object($result);
						$i++;

						if ($obj->fk_product_pere == $this->product->id)
							$error++;
					}

					$sql = "UPDATE " . MAIN_DB_PREFIX . "product_association";
					$sql .= " SET fk_product_fils ='" . $this->product->id . "'  WHERE fk_product_fils = " . $id_delete;
					dol_syslog("Products::Merge sql=" . $sql, LOG_DEBUG);
					if ($this->db->query($sql)) {

					} else {
						$error++;

						dol_syslog("Products::Merge erreur -2 " . $this->error, LOG_ERR);
					}


				}

				if (!$error) {
					// Appel des triggers
					include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
					$interface = new Interfaces($this->db);
					$result = $interface->run_triggers('PRODUCT_MERGE', $this, $user, $langs, $conf);
					if ($result < 0) {
						$error++;
						$this->errors = $interface->errors;
					}
					// Fin appel triggers
				}


				if (!$error) {

					$productDelete = new Product($this->db);
					$productDelete->fetch($id_delete);
					$resultz = $productDelete->delete($user, $id_delete);

					if (!$resultz) {
						print $productDelete->error;
						$error++;
					}
				}

				if ($error) {
					$this->db->rollback();
					setEventMessages($langs->trans($this->db->lasterror), null, 'errors');

					return -$error;
				} else {
					setEventMessages($langs->trans('ProductMergeSuccess'), null, 'mesgs');
					$this->db->commit();
					return 1;
				}
			} else {
				$this->error .= "FailedToMergeProduct. Already used.\n";
				return -1;
			}
		} else {
			return -1;
		}
	}

	/**
	 * Retrieves an array of table names containing the 'fk_product' column.
	 *
	 * @param DoliDb $db The database handler.
	 *
	 * @return array An array of table names with the 'fk_product' column.
	 */
	public static function getTablesWithFkProduct($db)
	{
		$tables = array();

		$sql = "SELECT
                TABLE_NAME
            FROM
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE
                COLUMN_NAME = 'fk_product'";
		$resql = $db->query($sql);

		if ($resql) {
			while ($record = $db->fetch_object($resql)) {
				$tables[] = $record->TABLE_NAME;
			}
		}

		return $tables;
	}


}
