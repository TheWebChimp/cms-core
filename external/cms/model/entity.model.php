<?php

	/**
	 * Entity Class
	 *
	 * Entity
	 *
	 * @version  1.0
	 * @author   biohzrdmx <github.com/biohzrdmx>
	 */
	class Entity extends CROOD {

		public $id;
		public $author;
		public $parent;
		public $title;
		public $excerpt;
		public $password;
		public $content;
		public $type;
		public $status;
		public $slug;
		public $position;
		public $views;
		public $comments;
		public $mime_type;
		public $published;
		public $created;
		public $modified;

		/**
		 * Initialization callback
		 * @return nothing
		 */
		function init($args = false) {

			$now = date('Y-m-d H:i:s');

			$this->table = 					'entity';
			$this->table_fields = 			array('id', 'author', 'parent', 'title', 'excerpt', 'password', 'content', 'type', 'status', 'slug', 'position', 'views', 'comments', 'mime_type', 'published', 'created', 'modified');
			$this->update_fields = 			array('author', 'parent', 'title', 'excerpt', 'password', 'content', 'type', 'status', 'slug', 'position', 'views', 'comments', 'mime_type', 'published', 'modified');
			$this->singular_class_name = 	'Entity';
			$this->plural_class_name = 		'Entities';


			#metaModel
			$this->meta_id = 				'id_entity';
			$this->meta_table = 			'entity_meta';

			if (! $this->id ) {

				$this->id = '';
				$this->author = 0;
				$this->parent = '';
				$this->title = '';
				$this->excerpt = '';
				$this->password = '';
				$this->content = '';
				$this->type = '';
				$this->status = '';
				$this->slug = '';
				$this->position = '';
				$this->views = '';
				$this->comments = '';
				$this->mime_type = '';
				$this->published = '';
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

		protected function buildPath() {
			global $site;
			$ret = '';
			if ($this->parent == 0) {
				$type = $site->cms->getEntityType($this->type);
				$ret = $type->routing->slug;
			} else {
				$parent = Entities::getById($this->parent);
				$ret = $parent->buildPath() . "/{$parent->slug}";
			}
			return $ret;
		}

		function getPermalink($echo = false) {
			global $site;
			$path = $this->buildPath();
			$ret = $site->urlTo("{$path}/{$this->slug}");
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		function getTerms($taxonomy = '') {
			global $site;
			$dbh = $site->getDatabase();
			$ret = false;
			try {
				$taxonomy_s = $taxonomy ? $dbh->quote($taxonomy) : '';
				$conditions = $taxonomy ? " AND taxonomy = $taxonomy_s" : '';
				$sql = "SELECT t.* FROM term t, term_entity te WHERE t.id = te.id_term AND te.id_entity = :id_entity {$conditions}";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id_entity', $this->id);
				$stmt->setFetchMode(PDO::FETCH_CLASS, 'Term');
				$stmt->execute();
				$rows = $stmt->fetchAll();
				if ($rows) {
					$ret = $rows;
				}
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
			return $ret;
		}

		function clearTerms() {
			global $site;
			$dbh = $site->getDatabase();
			try {
				$sql = "DELETE FROM term_entity WHERE id_entity = :id_entity";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id_entity', $this->id);
				$stmt->execute();
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
		}

		function updateTerms($terms) {
			global $site;
			$dbh = $site->getDatabase();
			try {
				$sql = "INSERT INTO term_entity (id_term, id_entity, position) VALUES (:id_term, :id_entity, 0)";
				$stmt = $dbh->prepare($sql);
				if ($terms) {
					foreach ($terms as $term) {
						$stmt->bindValue(':id_term', $term);
						$stmt->bindValue(':id_entity', $this->id);
						$stmt->execute();
						Terms::updateEntityCount($term);
					}
				}
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
		}

		function hasThumbnail() {
			global $site;
			$ret = false;
			if ( $site->cms->featureSupport($this->type, 'thumbnail') ) {
				$ret = !!$this->getMeta('thumbnail');
			}
			return $ret;
		}

		function getThumbnail($size = 'thumbnail', $type = 'url', $echo = false, $attrs = []) {
			global $site;
			$ret = false;
			if ( $site->cms->featureSupport($this->type, 'thumbnail') ) {
				$attachment_id = $this->getMeta('thumbnail');
				$ret = $site->cms->getImage($attachment_id, $size, $type, $echo, $attrs);
			}
			return $ret;
		}
	}

	# ==============================================================================================

	/**
	 * Entities Class
	 *
	 * Entities
	 *
	 * @version 1.0
	 * @author  biohzrdmx <github.com/biohzrdmx>
	 */
	class Entities extends NORM {

		protected static $table = 					'entity';
		protected static $table_fields = 			array('id', 'author', 'parent', 'title', 'excerpt', 'password', 'content', 'type', 'status', 'slug', 'position', 'views', 'comments', 'mime_type', 'published', 'created', 'modified');
		protected static $singular_class_name = 	'Entity';
		protected static $plural_class_name = 		'Entities';

		public function updateCommentCount($id_entity) {
			global $site;
			$dbh = $site->getDatabase();
			try {
				$sql = "UPDATE entity SET comments = ( SELECT COUNT(id_entity) AS total FROM comment WHERE id_entity = :id_entity ) WHERE id = :id_entity";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id_entity', $id_entity);
				$stmt->execute();
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
		}
	}
?>