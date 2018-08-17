<?php

	/**
	 * User Class
	 *
	 * User
	 *
	 * @version  1.0
	 * @author   biohzrdmx <github.com/biohzrdmx>
	 */
	class User extends CROOD {

		public $id;
		public $login;
		public $email;
		public $nicename;
		public $password;
		public $status;
		public $type;
		public $created;
		public $modified;

		/**
		 * Initialization callback
		 * @return nothing
		 */
		function init($args = false) {

			$now = date('Y-m-d H:i:s');

			$this->table = 					'user';
			$this->table_fields = 			array('id', 'login', 'email', 'nicename', 'password', 'status', 'type', 'created', 'modified');
			$this->update_fields = 			array('login', 'email', 'nicename', 'password', 'status', 'type', 'modified');
			$this->singular_class_name = 	'User';
			$this->plural_class_name = 		'Users';


			#metaModel
			$this->meta_id = 				'id_user';
			$this->meta_table = 			'user_meta';

			if (! $this->id ) {

				$this->id = '';
				$this->login = '';
				$this->email = '';
				$this->nicename = '';
				$this->password = '';
				$this->status = '';
				$this->type = '';
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

		function save() {
			# Sanitization
			if ( empty($this->login) ) {
				return false;
			}
			$this->modified = date('Y-m-d H:i:s');
			$this->nicename = $this->nicename ? $this->nicename : $this->email;
			$this->login = $this->login ? $this->login : $this->email;
			if( substr($this->password, 0, 4) != '$2a$' ) {
				$hasher = new PasswordHash(8, FALSE);
				$this->password = $hasher->HashPassword($this->password);
			}
			return parent::save();
		}

		function getPermalink($echo = false) {
			global $site;
			$path = $site->cms->getOption('author_slug', 'author');
			$ret = $site->urlTo("{$path}/{$this->login}");
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}
	}

	# ==============================================================================================

	/**
	 * Users Class
	 *
	 * Users
	 *
	 * @version 1.0
	 * @author  biohzrdmx <github.com/biohzrdmx>
	 */
	class Users extends NORM {

		protected static $table = 					'user';
		protected static $table_fields = 			array('id', 'login', 'email', 'nicename', 'password', 'status', 'type', 'created', 'modified');
		protected static $singular_class_name = 	'User';
		protected static $plural_class_name = 		'Users';
	}
?>