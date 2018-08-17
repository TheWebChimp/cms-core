<?php

	/**
	 * Term Class
	 *
	 * Term
	 *
	 * @version  1.0
	 * @author   biohzrdmx <github.com/biohzrdmx>
	 */
	class Term extends CROOD {

		public $id;
		public $parent;
		public $name;
		public $slug;
		public $taxonomy;
		public $description;
		public $position;
		public $entities;
		public $status;
		public $created;
		public $modified;

		/**
		 * Initialization callback
		 * @return nothing
		 */
		function init($args = false) {

			$now = date('Y-m-d H:i:s');

			$this->table = 					'term';
			$this->table_fields = 			array('id', 'parent', 'name', 'slug', 'taxonomy', 'description', 'position', 'entities', 'status', 'created', 'modified');
			$this->update_fields = 			array('parent', 'name', 'slug', 'taxonomy', 'description', 'position', 'entities', 'status', 'modified');
			$this->singular_class_name = 	'Term';
			$this->plural_class_name = 		'Terms';


			#metaModel
			$this->meta_id = 				'id_term';
			$this->meta_table = 			'term_meta';

			if (! $this->id ) {

				$this->id = '';
				$this->parent = '';
				$this->name = '';
				$this->slug = '';
				$this->taxonomy = '';
				$this->description = '';
				$this->position = '';
				$this->entities = '';
				$this->status = '';
				$this->created = $now;
				$this->modified = $now;
				$this->metas = new stdClass();
			}

			else {

				$args = $this->preInit($args);

				# Enter your logic here
				# ----------------------------------------------------------------------------------



				# ----------------------------------------------------------------------------------

				$args = $this->postInit($args);
			}
		}

		function getPermalink($echo = false) {
			global $site;
			$taxonomy = $site->cms->getTaxonomy($this->taxonomy);
			$path = $taxonomy->routing->slug;
			$ret = $site->urlTo("{$path}/{$this->slug}");
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		function getEntities($entity = '') {
			global $site;
			$dbh = $site->getDatabase();
			$ret = false;
			// try {
			// 	$taxonomy_s = $taxonomy ? $dbh->quote($taxonomy) : '';
			// 	$conditions = $taxonomy ? " AND taxonomy = $taxonomy_s" : '';
			// 	$sql = "SELECT t.* FROM term t, term_entity te WHERE t.id = te.id_term AND te.id_entity = :id_entity {$conditions}";
			// 	$stmt = $dbh->prepare($sql);
			// 	$stmt->bindValue(':id_entity', $this->id);
			// 	$stmt->setFetchMode(PDO::FETCH_CLASS, 'Term');
			// 	$stmt->execute();
			// 	$rows = $stmt->fetchAll();
			// 	if ($rows) {
			// 		$ret = $rows;
			// 	}
			// } catch (PDOException $e) {
			// 	error_log( $e->getMessage() );
			// }
			return $ret;
		}
	}

	# ==============================================================================================

	/**
	 * Terms Class
	 *
	 * Terms
	 *
	 * @version 1.0
	 * @author  biohzrdmx <github.com/biohzrdmx>
	 */
	class Terms extends NORM {

		protected static $table = 					'term';
		protected static $table_fields = 			array('id', 'parent', 'name', 'slug', 'taxonomy', 'description', 'position', 'entities', 'status', 'created', 'modified');
		protected static $singular_class_name = 	'Term';
		protected static $plural_class_name = 		'Terms';

		public function updateEntityCount($id_term) {
			global $site;
			$dbh = $site->getDatabase();
			try {
				$sql = "UPDATE term SET entities = ( SELECT COUNT(id_entity) AS total FROM term_entity WHERE id_term = :id_term ) WHERE id = :id_term";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id_term', $id_term);
				$stmt->execute();
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
		}
	}
?>