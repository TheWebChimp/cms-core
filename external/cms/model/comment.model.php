<?php

	/**
	 * Comment Class
	 *
	 * Comment
	 *
	 * @version  1.0
	 * @author   biohzrdmx <github.com/biohzrdmx>
	 */
	class Comment extends CROOD {

		public $id;
		public $id_user;
		public $id_entity;
		public $parent;
		public $source;
		public $content;
		public $karma;
		public $approved;
		public $type;
		public $mime_type;
		public $created;
		public $modified;

		/**
		 * Initialization callback
		 * @return nothing
		 */
		function init($args = false) {

			$now = date('Y-m-d H:i:s');

			$this->table = 					'comment';
			$this->table_fields = 			array('id', 'id_user', 'id_entity', 'parent', 'source', 'content', 'karma', 'approved', 'type', 'mime_type', 'created', 'modified');
			$this->update_fields = 			array('id_user', 'id_entity', 'parent', 'source', 'content', 'karma', 'approved', 'type', 'mime_type', 'modified');
			$this->singular_class_name = 	'Comment';
			$this->plural_class_name = 		'Comments';


			#metaModel
			$this->meta_id = 				'id_comment';
			$this->meta_table = 			'comment_meta';

			if (! $this->id ) {

				$this->id = '';
				$this->id_user = 0;
				$this->id_entity = 0;
				$this->parent = '';
				$this->source = '';
				$this->content = '';
				$this->karma = '';
				$this->approved = '';
				$this->type = '';
				$this->mime_type = '';
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
	}

	# ==============================================================================================

	/**
	 * Comments Class
	 *
	 * Comments
	 *
	 * @version 1.0
	 * @author  biohzrdmx <github.com/biohzrdmx>
	 */
	class Comments extends NORM {

		protected static $table = 					'comment';
		protected static $table_fields = 			array('id', 'id_user', 'id_entity', 'parent', 'source', 'content', 'karma', 'approved', 'type', 'mime_type', 'created', 'modified');
		protected static $singular_class_name = 	'Comment';
		protected static $plural_class_name = 		'Comments';
	}
?>