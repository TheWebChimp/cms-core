<?php

	/**
	 * CMS Platform for Hummingbird Lite
	 * @author   biohzrdmx <http://biohzrdmx.me>
	 * @version  0.5
	 * @license  MIT
	 * @requires SimpleImage <https://github.com/claviska/SimpleImage>
	 *		   Parsedown <http://github.com/erusev/parsedown>
	 */

	include $site->baseDir('/external/cms/plugin.inc.php');
	include $site->baseDir('/external/cms/xhr.inc.php');

	if (class_exists('NORM') && class_exists('CROOD')) {
		include $site->baseDir('/external/cms/model/comment.model.php');
		include $site->baseDir('/external/cms/model/entity.model.php');
		include $site->baseDir('/external/cms/model/term.model.php');
		include $site->baseDir('/external/cms/model/user.model.php');
	}

	class CMS {

		public static $version = 0.5;

		private static $instance;

		protected $caching;
		protected $theme;
		protected $plugins;
		protected $entities;
		protected $taxonomies;
		protected $image_sizes;
		protected $shortcodes;
		protected $dirs;
		protected $root;
		protected $vars;

		protected $theme_config;

		public $base_dir;
		public $theme_dir;
		public $theme_uri;
		public $is_archive;
		public $is_paginated;
		public $search;
		public $pagination;
		public $slug;
		public $user;

		public static function getInstance() {
			if (null === static::$instance) {
				static::$instance = new static();
			}
			return static::$instance;
		}

		protected function __construct() {
			global $site;
			$this->caching = $this->getOption('caching', false);
			$this->theme = $this->getOption('theme', 'default');
			$this->plugins = $this->getOption('plugins', '[]');
			$this->entities = [];
			$this->taxonomies = [];
			$this->image_sizes = [];
			$this->shortcodes = [];
			$this->root = [];
			$this->vars = [];
			#
			$this->plugins = @json_decode($this->plugins);
			#
			$this->is_archive = false;
			$this->is_paginated = false;
			$this->search = '';
			$this->slug = '';
			#
			$this->pagination = new stdClass();
			$this->pagination->total = 0;
			$this->pagination->page = 0;
			$this->pagination->show = 15;
			#
			$this->dirs = [];
			#
			$site->getRouter()->addRoute('/*splat', 'CMS::routeRequest', true);
			$site->getRouter()->addRoute('/cms/utils/*splat', 'CMS::routeUtils', true);
			$site->getRouter()->addRoute('/cms/api/*splat', 'CMS::routeApi', true);
			#
			$this->setDir('content', '/content');
			$this->setDir('cache', '/cache');
			$this->setDir('plugins', '/plugins');
			$this->setDir('uploads', '/uploads');
			$this->setDir('themes', '/themes');
			$site->executeHook('cms.setDirs');
			# Load plugins
			if ($this->plugins) {
				$instances = [];
				foreach ($this->plugins as $plugin) {
					$instance = $this->loadPlugin($plugin);
					if ($instance) {
						$instances[$plugin] = $instance;
					}
				}
				$this->plugins = $instances;
			}
			$site->executeHook('cms.loadPlugins');
		}

		static function routeRequest($args) {
			global $site;
			$cms = $site->cms;
			$ret = false;
			$cms->base_dir = dirname(__FILE__);
			$installed = $cms->getOption('setup_installed', 0);
			if (! $installed ) {
				$site->redirectTo( $site->urlTo('/cms/utils/setup') );
				exit;
			}
			$cms->recoverSession();
			# Init plugins
			foreach ($cms->plugins as $plugin) {
				$plugin->init();
			}
			# Init router
			$site->executeHook('cms.initRouter', $args);
			# Load theme
			$content_dir = $site->cms->getDir('content');
			$themes_dir = $site->cms->getDir('themes');
			$cms->theme_uri = $site->baseUrl("{$content_dir}{$themes_dir}/{$cms->theme}");
			$cms->theme_dir = $site->baseDir("{$content_dir}{$themes_dir}/{$cms->theme}");
			$cms->theme_config = @json_decode( file_get_contents("{$cms->theme_dir}/theme.json") );
			$site->executeHook('cms.loadTheme', $cms->theme_uri, $cms->theme_dir, $cms->theme_config);
			# Register base entity types
			$config = @json_decode( file_get_contents("{$cms->base_dir}/content/default/entity/attachment.json") );
			$cms->registerEntity('attachment', $config);
			$config = @json_decode( file_get_contents("{$cms->base_dir}/content/default/entity/post.json") );
			$cms->registerEntity('post', $config);
			$config = @json_decode( file_get_contents("{$cms->base_dir}/content/default/entity/page.json") );
			$cms->registerEntity('page', $config);
			# Register base taxonomies
			$config = @json_decode( file_get_contents("{$cms->base_dir}/content/default/taxonomy/category.json") );
			$cms->registerTaxonomy('category', $config);
			# Register theme-defined taxonomies
			if ($cms->theme_config->entities) {
				foreach ($cms->theme_config->entities as $entity) {
					$config = @json_decode( file_get_contents("{$cms->theme_dir}/config/entity/{$entity}.json") );
					$cms->registerEntity($entity, $config);
				}
			}
			$site->executeHook('cms.registerTaxonomies');
			# Register theme-defined entity types
			if ($cms->theme_config->taxonomies) {
				foreach ($cms->theme_config->taxonomies as $taxonomy) {
					$config = @json_decode( file_get_contents("{$cms->theme_dir}/config/taxonomy/{$taxonomy}.json") );
					$cms->registerTaxonomy($taxonomy, $config);
				}
			}
			$site->executeHook('cms.registerEntities');
			#
			$cms->registerImageSize('thumbnail', 180, 180, 'thumbnail', true);
			$cms->registerImageSize('small', 480, null, 'long_side', true);
			$cms->registerImageSize('medium', 800, null, 'long_side', true);
			$cms->registerImageSize('large', 1024, null, 'long_side', true);
			$site->executeHook('cms.registerImageSizes');
			#
			$content = $cms->cacheRetrieve();
			if (!$content) {
				# Resolve non-cached content and cache it
				$ret = $cms->processRequest($args);
				$cms->cacheStore($ret);
			} else {
				# Echo cached content
				echo $content;
				$ret = true;
			}
			#
			return $ret;
		}

		static function routeUtils($args) {
			global $site;
			$cms = $site->cms;
			$dbh = $site->getDatabase();
			$ret = false;
			$cms->base_dir = dirname(__FILE__);
			if ($dbh) {
				$cms->recoverSession();
			}
			#
			$splat = get_item($args, 1);
			$splat = explode('/', $splat);
			if ( is_array($splat) ) {
				$function = array_shift($splat);
				$callable = dash_to_camel("utility-{$function}");
				if ( method_exists($cms, $callable) ){
					$ret = call_user_func_array([$cms, $callable], $splat);
				}
			}
			#
			return $ret;
		}

		static function routeApi($args) {
			global $site;
			$cms = $site->cms;
			$ret = false;
			$cms->base_dir = dirname(__FILE__);
			$installed = $cms->getOption('setup_installed', 0);
			if (! $installed ) {
				$site->redirectTo( $site->urlTo('/cms/utils/setup') );
				exit;
			}
			$cms->recoverSession();
			# Init plugins
			foreach ($cms->plugins as $plugin) {
				$plugin->init();
			}
			# Init API
			$site->executeHook('cms.initApi', $args);
			#
			$rest_api = $cms->getOption('rest_api', 1);
			if ($rest_api) {
				$splat = get_item($args, 1);
				$splat = explode('/', $splat);
				if ( is_array($splat) ) {
					$function = array_shift($splat);
					$callable = dash_to_camel("api-{$function}");
					if ( method_exists($cms, $callable) ){
						$ret = call_user_func([$cms, $callable], $splat);
					}
				}
			}
			#
			return $ret;
		}

		protected function apiComments($args) {
			global $site;
			$request = $site->getRequest();
			$response = $site->getResponse();
			$result = 'error';
			$data = [];
			$message = '';
			$ret = false;
			switch ($request->type) {
				case 'get':
					$id_entity = $request->get('id_entity', '');
					$parent = $request->get('parent', 0);
					$page = $request->get('page', 1);
					$show = $request->get('show', 25);
					$data['comments'] = $this->getComments($id_entity, $parent, $page, $show);
				break;
				case 'post':
					$comment_moderation = $this->getOption('comment_moderation', 1);
					#
					$csrf = $request->post('csrf', '');
					$redirect = $request->post('redirect', '');
					$id_entity = $request->post('id_entity', '');
					$id_user = $request->post('id_user', '');
					$parent = $request->post('parent', 0);
					$mime_type = $request->post('mime_type', 'text/markdown');
					$name = $request->post('name', $site->cms->user ? $site->cms->user->nicename : '');
					$email = $request->post('email', $site->cms->user ? $site->cms->user->email : '');
					$content = $request->post('content', '');
					#
					if ( $this->checkCsrfToken('comments.post', $csrf) ) {
						$res = $site->executeHook('cms.commentsValidate', $request->post());
						if ($res == false) {
							$plugin = true;
						} else {
							$plugin = $res['result'] == 'success';
						}
						if ($name && $email && $content && $plugin) {
							$source = ['name' => $name, 'email' => $email];
							$comment = new Comment;
							$comment->id_entity = $id_entity;
							$comment->id_user = $id_user;
							$comment->parent = $parent;
							$comment->content = htmlentities($content);
							$comment->source = $id_user ? '' : json_encode($source);
							$comment->approved = $comment_moderation ? 0 : 1;
							$comment->mime_type = $mime_type;
							$comment->save();
							if ($comment->id) {
								#
								Entities::updateCommentCount($id_entity);
								#
								$result = 'success';
								$data['comment'] = $comment;
								$message = 'MSG_PENDING_APPROVAL';
								if ($redirect) {
									$site->redirectTo("{$redirect}?msg={$message}");
								}
							} else {
								$message = 'ERR_DATABASE_ERROR';
							}
						} else {
							$message = 'ERR_REQUIRED_FIELDS';
						}
					} else {
						$message = 'ERR_INVALID_TOKEN';
					}
				break;
			}
			return $response->ajaxRespond($result, $data, $message);
		}

		protected function utilityPlaceholder($size) {
			$dimensions = explode('x', $size);
			$width = $dimensions[0] ?: 320;
			$height = $dimensions[1] ?: 240;
			header("Content-Type: image/png");
			$image = imagecreatetruecolor($width, $height);
			$color_fg = imagecolorallocate($image, 150, 150, 150);
			$color_bg = imagecolorallocate($image, 204, 204, 204);
			$font_file = "{$this->base_dir}/content/default/OpenSans-Regular.ttf";
			$font_size = $height / 8;
			$bbox = imagettfbbox($font_size, 0, $font_file, $size);
			$x = ($width - $bbox[2]) / 2;
			$y = ($height - $bbox[5]) / 2;
			imagealphablending($image, true);
			imagefill($image, 0, 0, $color_bg);
			imagettftext($image, $font_size, 0, $x, $y, $color_fg, $font_file, $size);
			imagepng($image);
			imagedestroy($image);
			return true;
		}

		protected function utilitySetup($page = 'index') {
			global $site;
			$request = $site->getRequest();
			$response = $site->getResponse();
			if (! $this->getOption('setup_disabled', 0) ) {
				$whitelist = ['index', 'config', 'install', 'done'];
				if ( in_array($page, $whitelist) ) {
					if ($page != 'done' && $this->getOption('setup_installed')) {
						$site->redirectTo('/cms/utils/setup/done');
					}
					if ($page != 'index' && !$site->getDatabase()) {
						$site->redirectTo('/cms/utils/setup');
					}
					switch ($request->type) {
						case 'get':
							# Avoid cache
							header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
							header("Cache-Control: post-check=0, pre-check=0", false);
							header("Pragma: no-cache");
							# Render page
							$site->setDir('pages', '/external/cms/content/setup');
							$site->setDir('partials', '/external/cms/content/setup');
							$site->render("page-{$page}");
						break;
						case 'post':
							$function = dash_to_camel("setup-{$page}");
							if ( method_exists($this, $function) ) {
								call_user_func([$this, $function], $request, $response);
							}
						break;
					}
				}
			} else {
				$site->redirectTo( $site->urlTo('/') );
			}
			return $response->respond();
		}

		protected function setupInstall($request, $response) {
			global $site;
			$dbh = $site->getDatabase();
			$ddl = file_get_contents( $site->baseDir('/external/cms/content/setup/setup.ddl') );
			try {
				$sql = $ddl;
				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$site->redirectTo( $site->urlTo('/cms/utils/setup/config') );
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
				$site->redirectTo( $site->urlTo('/cms/utils/setup/install?error='.$e->getCode()) );
			}
		}

		protected function setupConfig($request, $response) {
			global $site;
			$error = 0;
			$login = $request->post('login');
			$email = $request->post('email');
			$nicename = $request->post('nicename');
			$password = $request->post('password');
			$confirm = $request->post('confirm');
			if ($password == $confirm && $login && $email && $nicename) {
				$user = new User();
				$user->login = $login;
				$user->email = $email;
				$user->nicename = $nicename;
				$user->password = $password;
				$user->status = 'Active';
				$user->type = 'Administrator';
				$user->save();
				$capabilities = ['manage_all'];
				$user->updateMeta('capabilities', $capabilities);
			} else {
				$error = 1;
			}
			if ($error == 0) {
				$this->updateOption('setup_installed', 1);
				$this->updateOption('plugins', '["admin"]');
				$this->updateOption('caching', '0');
				$this->updateOption('rest_api', '1');
				$this->updateOption('comment_moderation', '1');
				$site->redirectTo( $site->urlTo('/cms/utils/setup/done') );
			} else {
				$site->redirectTo( $site->urlTo('/cms/utils/setup/config?error='.$error) );
			}
		}

		protected function setupDone($request, $response) {
			global $site;
			$this->updateOption('setup_disabled', 1);
			$site->redirectTo( $site->urlTo('/cms/admin') );
		}

		protected function processRequest($args) {
			global $site;
			$ret = false;
			# Cache root entities
			$root_entities = implode(',', $this->root);
			# Prepare regex data
			$str_search = $this->getOption('search_slug', 'search');
			$str_author = $this->getOption('author_slug', 'author');
			$str_paginated = $this->getOption('paginated_slug', 'page');
			$arr_entities = [];
			$slug_map = [];
			if ($this->entities) {
				$slug_map['entities'] = [];
				foreach ($this->entities as $name => $entity) {
					# Skip non-public, non-archive and root entities
					if ($entity->routing->ignore || $entity->routing->root || !$entity->flags->public || !$entity->flags->archive) continue;
					$arr_entities[] = $entity->routing->slug ?: $name;
					$slug_map['entities'][$entity->routing->slug] = $name;
				}
			}
			$str_entities = implode('|', $arr_entities);
			$arr_taxonomies = [];
			if ($this->taxonomies) {
				$slug_map['taxonomies'] = [];
				foreach ($this->taxonomies as $name => $taxonomy) {
					# Skip non-public and non-archive taxonomies
					if ($taxonomy->routing->ignore || !$taxonomy->flags->public || !$taxonomy->flags->archive) continue;
					$arr_taxonomies[] =$taxonomy->routing->slug ?: $name;
					$slug_map['taxonomies'][$taxonomy->routing->slug] = $name;
				}
			}
			$str_taxonomies = implode('|', $arr_taxonomies);
			# Build the regular expressions
			$regexes = [];
			#
			$regexes['search'] = "/^({$str_search})(?:\/((?:{$str_paginated}|\d|\/)+))?$/";
			#
			if ($str_author) {
				$regexes['author'] = "/^({$str_author})(?:\/([a-z0-9\-_\/]+))?$/";
			}
			if ($str_entities) {
				$regexes['entities'] = "/^({$str_entities})(?:\/([a-z0-9\-_\/]+))?$/";
			}
			if ($str_taxonomies) {
				$regexes['taxonomies'] = "/^($str_taxonomies)(?:\/([a-z0-9\-_\/]+))?$/";
			}
			# Resolve the route
			$request = get_item($args, 1);
			$this->is_archive = false;
			$this->is_paginated = preg_match("/^(.*){$str_paginated}\/(\d+)$/", $request, $paginated) === 1;
			$this->pagination->page = $paginated ? get_item($paginated, 2, 1) : 1;
			# If the request is empty OR paginated, but empty, treat it as a potential archive
			if ( $request == '' || ( $this->is_paginated && !$paginated[1] ) ) {
				# The root may be an archive, get the first public slug-less entity type that has 'archive' enabled from the root
				if ($this->root) {
					$entity = null;
					foreach ($this->root as $type) {
						$entity = $this->entities[$type];
						# Skip non-public, non-archive and root entities
						if ($entity->routing->ignore || !$entity->flags->public || !$entity->flags->archive || $entity->routing->slug) continue;
						$entity = $entity;
						break;
					}
					if ($entity) {
						$this->is_archive = true;
						$ret = $this->renderEntityArchive($type, $entity);
					}
				}
			} else {
				$ret = $this->regexTestRequest($request, $regexes, $slug_map);
			}
			# Override paginated if archive (archives are always paginated)
			$this->is_paginated |= $this->is_archive;
			#
			if (!$ret) {
				# Prepare rendering of the error page
				if ($this->theme_config->assets->styles) {
					foreach ($this->theme_config->assets->styles as $key => $value) {
						$value = str_replace('%theme_url%', $this->theme_uri, $value);
						$site->registerStyle($key, $value, true);
						$site->enqueueStyle($key, $value);
					}
				}
				if ($this->theme_config->assets->scripts) {
					foreach ($this->theme_config->assets->scripts as $key => $value) {
						$value = str_replace('%theme_url%', $this->theme_uri, $value);
						$site->registerScript($key, $value, true);
						$site->enqueueScript($key, $value);
					}
				}
				$site->setDir('partials', "/content/themes/{$this->theme}/templates");
				$site->setDir('pages', "/content/themes/{$this->theme}/templates");
				$site->setDir('images', "/content/themes/{$this->theme}/assets/images");
				$site->setDir('scripts', "/content/themes/{$this->theme}/assets/scripts");
				$site->setDir('styles', "/content/themes/{$this->theme}/assets/styles");
			}
			return $ret;
		}

		protected function regexTestRequest($request, $regexes, $slug_map) {
			$ret = false;
			$handled = false;
			$request_parts = explode('/', $request);
			if ($regexes) {
				# Run through each pattern to test the current request against it
				foreach ($regexes as $type => $pattern) {
					$res = preg_match($pattern, $request);
					if ( $res === 1 ) {
						if ($this->is_paginated) {
							# Paginated, pop the last two parts of the request
							array_pop($request_parts);
							array_pop($request_parts);
						}
						switch ($type) {
							case 'search':
								$ret = $this->renderSearchResults();
							break;
							case 'author':
								# Consume an extra token
								array_shift($request_parts);
								$author = array_shift($request_parts);
								$user = $author ? Users::getByLogin($author) : null;
								if ($user) {
									$this->is_archive = true;
									$ret = $this->renderAuthorArchive($author, $user);
								}
							break;
							case 'entities':
								$type = array_shift($request_parts);
								$type = get_item($slug_map['entities'], $type);
								$entity = get_item($this->entities, $type);
								if ($entity) {
									$this->is_archive = empty($request_parts);
									if ($this->is_archive) {
										$ret = $this->renderEntityArchive($type, $entity);
									} else {
										# Try to resolve to a single
										$ret = $this->renderSingle($request_parts);
									}
								}
							break;
							case 'taxonomies':
								$taxonomy = array_shift($request_parts);
								$taxonomy = get_item($slug_map['taxonomies'], $taxonomy);
								$taxonomy = get_item($this->taxonomies, $taxonomy);
								$term = array_shift($request_parts);
								$term = $term ? Terms::getBySlug($term) : null;
								if ($term) {
									$this->is_archive = true;
									$ret = $this->renderTaxonomyArchive($taxonomy, $term);
								}
							break;
						}
						$handled = true;
						break;
					}
				}
			}
			if (!$handled) {
				# Try to resolve to a single
				$ret = $this->renderSingle($request_parts);
			}
			return $ret;
		}

		protected function importEntities($data) {
			global $site;
			$dbh = $site->getDatabase();
			if ($data) {
				try {
					$sql = "INSERT INTO entity (id, author, parent, title, excerpt, password, content, type, status, slug, position, views, comments, mime_type, published, created, modified) VALUES (:id, :author, :parent, :title, :excerpt, :password, :content, :type, :status, :slug, :position, :views, :comments, :mime_type, :published, :created, :modified)";
					$stmt_entity = $dbh->prepare($sql);
					$sql = "INSERT INTO entity_meta (id, id_entity, name, value) VALUES (:id, :id_entity, :name, :value)";
					$stmt_meta = $dbh->prepare($sql);
					$dbh->query('START TRANSACTION');
					if ($data->entities) {
						foreach ($data->entities as $item) {
							$stmt_entity->bindValue(':id', $item->id);
							$stmt_entity->bindValue(':author', $item->author);
							$stmt_entity->bindValue(':parent', $item->parent);
							$stmt_entity->bindValue(':title', $item->title);
							$stmt_entity->bindValue(':excerpt', $item->excerpt);
							$stmt_entity->bindValue(':password', $item->password);
							$stmt_entity->bindValue(':content', $item->content);
							$stmt_entity->bindValue(':type', $item->type);
							$stmt_entity->bindValue(':status', $item->status);
							$stmt_entity->bindValue(':slug', $item->slug);
							$stmt_entity->bindValue(':position', $item->position);
							$stmt_entity->bindValue(':views', $item->views);
							$stmt_entity->bindValue(':comments', $item->comments);
							$stmt_entity->bindValue(':mime_type', $item->mime_type);
							$stmt_entity->bindValue(':published', $item->published);
							$stmt_entity->bindValue(':created', $item->created);
							$stmt_entity->bindValue(':modified', $item->modified);
							$stmt_entity->execute();
						}
					}
					if ($data->metas) {
						foreach ($data->metas as $item) {
							$stmt_meta->bindValue(':id', $item->id);
							$stmt_meta->bindValue(':id_entity', $item->id_entity);
							$stmt_meta->bindValue(':name', $item->name);
							$stmt_meta->bindValue(':value', $item->value);
							$stmt_meta->execute();
						}
					}
					$dbh->query('COMMIT');
				} catch (PDOException $e) {
					error_log( $e->getMessage() );
				}
			}
		}

		protected function importOptions($data) {
			global $site;
			$dbh = $site->getDatabase();
			if ($data) {
				try {
					$sql = "INSERT INTO option (id, name, value) VALUES (:id, :name, :value)";
					$stmt = $dbh->prepare($sql);
					$dbh->query('START TRANSACTION');
					if ($data->options) {
						foreach ($data->options as $item) {
							$stmt_meta->bindValue(':id', $item->id);
							$stmt_meta->bindValue(':name', $item->name);
							$stmt_meta->bindValue(':value', $item->value);
							$stmt_meta->execute();
						}
					}
					$dbh->query('COMMIT');
				} catch (PDOException $e) {
					error_log( $e->getMessage() );
				}
			}
		}

		protected function exportEntities() {
			global $site;
			$dbh = $site->getDatabase();
			$ret = null;
			try {
				# Fetch entities
				$sql = "SELECT id, author, parent, title, excerpt, password, content, type, status, slug, position, views, comments, mime_type, published, created, modified FROM entity ORDER BY id ASC";
				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$entities = $stmt->fetchAll();
				# Fetch metas
				$sql = "SELECT id, id_entity, name, value FROM entity_meta ORDER BY id ASC";
				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$metas = $stmt->fetchAll();
				# Pack them all
				$ret = [];
				$ret['entities'] = $entities;
				$ret['metas'] = $metas;
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
			return $ret;
		}

		protected function exportOptions() {
			global $site;
			$dbh = $site->getDatabase();
			$ret = null;
			try {
				# Fetch options
				$sql = "SELECT id, name, value FROM option ORDER BY id ASC";
				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$options = $stmt->fetchAll();
				# Pack them all
				$ret = [];
				$ret['options'] = $options;
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
			return $ret;
		}

		protected function encodeXML($data, &$xml_data = null) {
			$xml_data = $xml_data ?: new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><data></data>');
			foreach( $data as $key => $value ) {
				if( is_numeric($key) ){
					$key = "item{$key}";
				}
				if( is_array($value) || is_object($value) ) {
					$subnode = $xml_data->addChild($key);
					$this->encodeXML($value, $subnode);
				} else {
					//reject overly long 2 byte sequences, as well as characters above U+10000 and replace with ?
					$value = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
						'|[\x00-\x7F][\x80-\xBF]+'.
						'|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
						'|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
						'|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
						'?', $value );
					//reject overly long 3 byte sequences and UTF-16 surrogates and replace with ?
					$value = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
						'|\xED[\xA0-\xBF][\x80-\xBF]/S','?', $value );
					$xml_data->addChild($key, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
				}
			}
			return $xml_data;
		}

		public function import($src, $type, $format = 'json') {
			global $site;
			$data = null;
			switch ($format) {
				case 'json':
					$data = @json_decode( file_get_contents($src) );
				break;
			}
			if ($data) {
				switch ($type) {
					case 'entities':
						$this->importEntities($data);
					break;
					case 'options':
						$this->importOptions($data);
					break;
				}
			}
		}

		public function export($dest, $type, $format = 'json') {
			global $site;
			$data = null;
			switch ($type) {
				case 'entities':
					$data = $this->exportEntities();
				break;
				case 'options':
					$data = $this->exportOptions();
				break;
			}
			if ($data) {
				switch ($format) {
					case 'json':
						file_put_contents( $dest, @json_encode($data) );
					break;
					case 'xml':
						$xml_data = $this->encodeXML($data);
						file_put_contents( $dest, $xml_data->asXML() );
					break;
				}
			}
		}

		public function getOption($name, $default = '') {
			global $site;
			$dbh = $site->getDatabase();
			$ret = $default;
			if ($dbh) {
				try {
					$sql = "SELECT value FROM `option` WHERE name = :name";
					$stmt = $dbh->prepare($sql);
					$stmt->bindValue(':name', $name);
					$stmt->execute();
					$row = $stmt->fetch();
					$ret = $row ? $row->value : $default;
				} catch (PDOException $e) {
					error_log( $e->getMessage() );
				}
			}
			return $ret;
		}

		public function updateOption($name, $value) {
			global $site;
			$dbh = $site->getDatabase();
			try {
				$sql = "INSERT INTO `option` (id, name, value) VALUES (0, :name, :value) ON DUPLICATE KEY UPDATE value = :value";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':name', $name);
				$stmt->bindValue(':value', $value);
				$stmt->execute();
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
			return $this;
		}

		public function registerEntity($name, $config) {
			$this->entities[$name] = $config;
			if ($config->routing->root) {
				$this->root[] = $name;
			}
			return $this;
		}

		public function registerTaxonomy($name, $config) {
			$config->slug = $name;
			$this->taxonomies[$name] = $config;
			return $this;
		}

		public function registerImageSize($name, $width, $height, $strategy = 'fit', $enlarge = false) {
			$this->image_sizes[$name] = [
				'width' => $width == null ? null : abs( intval($width) ),
				'height' => $height == null ? null : abs( intval($height) ),
				'strategy' => $strategy,
				'enlarge' => $enlarge
			];
			return $this;
		}

		public function registerShortCode($name, $handler) {
			$this->shortcodes[$name] = $handler;
			return $this;
		}

		public function unregisterEntity($name) {
			unset( $this->entities[$name] );
			return $this;
		}

		public function unregisterTaxonomy($name) {
			unset( $this->taxonomies[$name] );
			return $this;
		}

		public function getEntityTypes() {
			return $this->entities;
		}

		public function getEntityType($name) {
			return get_item($this->entities, $name);
		}

		public function getTaxonomies() {
			return $this->taxonomies;
		}

		public function getTaxonomy($name) {
			return get_item($this->taxonomies, $name);
		}

		public function getComments($id_entity, $parent = 0, $page = 1, $show = 25) {
			global $site;
			$dbh = $site->getDatabase();
			$ret = false;
			$offset = ($page - 1) * $show;
			try {
				$sql = "SELECT * FROM comment WHERE id_entity = :id_entity AND approved = 1 AND parent = :parent ORDER BY created DESC LIMIT {$offset},{$show}";
				$stmt = $dbh->prepare($sql);
				$stmt->bindValue(':id_entity', $id_entity);
				$stmt->bindValue(':parent', $parent);
				$stmt->execute();
				$ret = $stmt->fetchAll();
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
			return $ret;
		}

		public function getAuthor($var) {
			$params = [];
			$params['pdoargs'] = ['fetch_metas'];
			$ret = is_object($var) ? Users::getById($var->author, $params) : Users::getById($var, $params);
			if ($ret) {
				$whitelist = ['id', 'login', 'email', 'nicename', 'status', 'type', 'created', 'metas'];
				$ret = $this->sanitizeObject($ret, $whitelist);
			}
			return $ret;
		}

		public function getEntity($id) {
			return is_numeric($id) ? Entities::getById($id) : Entities::getBySlug($id);
		}

		public function getImageSize($name) {
			return get_item($this->image_sizes, $name);
		}

		public function getPlugin($name) {
			return get_item($this->plugins, $name);
		}

		public function getDir($name, $echo = false) {
			$ret = get_item($this->dirs, $name);
			if ($echo) {
				echo $echo;
			}
			return $ret;
		}

		public function setDir($name, $path) {
			$this->dirs[$name] = $path;
			return $this;
		}

		public function checkLogin($login, $password) {
			global $site;
			$ret = false;
			$user = Users::getByLogin($login);
			if ($user) {
				$hasher = new PasswordHash(8, FALSE);
				$ret = $hasher->CheckPassword($password, $user->password) ? $user->id : false;
			}
			return $ret;
		}

		public function checkUserCookie($switch = false) {
			global $site;
			$request = $site->getRequest();
			$ret = false;
			$name = sprintf( $switch ? '%s-ext' : '%s-usr', $site->toAscii( $site->getGlobal('site_name') ) );
			$value = get_item($_COOKIE, $name);
			if ($value) {
				$salt = $site->getGlobal('token_salt');
				$parts = explode('|', $value);
				$data = get_item($parts, 0, null);
				$hash = get_item($parts, 1, null);
				$check = hash_hmac('sha256', $data, $salt);
				# Check that the hash is valid
				if ($hash && $hash == $check) {
					parse_str($data, $parts);
					$usr = get_item($parts, 'usr', 0);
					$exp = get_item($parts, 'exp', 0);
					# Check expiration time
					if (time() < $exp) {
						$ret = $usr;
					}
				}
			}
			return $ret;
		}

		public function setUserCookie($id_user, $switch = false) {
			global $site;
			$name = sprintf( $switch ? '%s-ext' : '%s-usr', $site->toAscii( $site->getGlobal('site_name') ) );
			$expire = strtotime('+2 weeks');
			$data = http_build_query(['usr' => $id_user, 'exp' => $expire]);
			$salt = $site->getGlobal('token_salt');
			$hash = hash_hmac('sha256', $data, $salt);
			$value = "{$data}|{$hash}";
			setcookie($name, $value, $expire, '/');
			return $this;
		}

		public function isUserSwitched() {
			return !!$this->checkUserCookie(true);
		}

		public function destroySession() {
			global $site;
			$name = sprintf( $this->isUserSwitched() ? '%s-ext' : '%s-usr', $site->toAscii( $site->getGlobal('site_name') ) );
			setcookie($name, '', strtotime('-1 year'), '/');
			unset( $_COOKIE[$name] );
			return $this;
		}

		public function recoverSession() {
			$user_id = $this->checkUserCookie(true) ?: $this->checkUserCookie();
			$params = [];
			$params['pdoargs'] = ['fetch_metas'];
			$user = Users::getById($user_id, $params);
			if ($user) {
				$whitelist = ['id', 'login', 'email', 'nicename', 'status', 'type', 'created', 'metas'];
				$this->user = $this->sanitizeObject($user, $whitelist);
			}
			return $this;
		}

		public function currentUserCan($capabilities) {
			$ret = false;
			if ($this->user) {
				if ( is_array($capabilities) ) {
					$ret = true;
					foreach ($capabilities as $capability) {
						if (! $this->currentUserCan($capability) ) {
							$ret = false;
							break;
						}
					}
				} else {
					$cur_capabilities = get_item($this->user->metas, 'capabilities', []);
					if ( in_array('manage_all', $cur_capabilities) || in_array($capability, $cur_capabilities) ) {
						$ret = true;
					}
				}
			}
			return $ret;
		}

		public function sidebar($name, $data = []) {
			global $site;
			$templates = ["sidebar-{$name}"];
			$template = $this->resolveTemplate($templates, 'sidebar', true);
			$path = $site->getDir('partials')."/_{$template}.php";
			if ( file_exists($path) ) {
				$site->partial($template, $data);
			}
			return $this;
		}

		public function comments($entity) {
			global $site;
			$templates = ["comments-{$entity->type}"];
			$template = $this->resolveTemplate($templates, 'comments', true);
			$data = [];
			$data['entity'] = $entity;
			$data['comments'] = $this->getComments($entity->id);
			$site->partial($template, $data);
			return $this;
		}

		public function featureSupport($var, $feature) {
			$type = $this->getEntityType($var);
			$ret = in_array($feature, $type->features);
			return $ret;
		}

		public function pluralize($number, $singular, $plural = '', $echo = false) {
			$plural = $plural ?: "{$singular}s";
			$ret = $number == 1 ? $singular : $plural;
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		public function sanitizeText($text, $echo = false) {
			$ret = htmlspecialchars($text);
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		public function sanitizeObject($object, $whitelist) {
			foreach ($object as $property => $value) {
				if (! in_array($property, $whitelist) ) {
					unset($object->$property);
				}
			}
			return $object;
		}

		public function filterText($text, $echo = false, $type = 'text/markdown') {
			$ret = $text;
			switch ($type) {
				case 'text/markdown':
					if ( class_exists('Parsedown') ) {
						$parsedown = new Parsedown();
						$ret = $parsedown->text($text);
					}
				break;
				case 'text/html':
					# Nothing to do here;
				break;
				case 'text/plain':
					$ret = nl2br($ret);
				break;
			}
			# Apply shortcodes
			if ($this->shortcodes) {
				$shortcodes = [];
				foreach ($this->shortcodes as $name => $handler) {
					$shortcodes[] = $name;
				}
				$shortcodes = implode('|', $shortcodes);
				$regex = '/{('.$shortcodes.')\s?([a-zA-Z0-9-_=" ]+)?}/';
				$ret = preg_replace_callback($regex, 'CMS::handleShortcode', $ret);
			}
			#
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		public function paginate($extra = [], $visible = 5) {
			global $site;
			$request = $site->getRequest();
			$total = $this->pagination->total;
			$page = $this->pagination->page;
			$show = $this->pagination->show;
			if ( $total > 0 ) {
				$pages = ceil($total / $show);
				$str_extra = $extra ? '?'.http_build_query($extra) : '';
				if ($pages > 1) {
					# Calculate boundaries
					if ($page < $visible) {
						$lower = 1;
						$upper = $visible;
					} else {
						$lower = $page - ($visible - ($page == $pages ? 1 : 2));
						$upper = $lower + ($visible - 1);
					}
					# Adjust
					$lower = $lower > 0 ? $lower : 1;
					$upper = $upper < $pages ? $upper : $pages;
					$trimmed = false;
					if ($lower > 1 && $upper < $pages) {
						$trimmed = 'both';
					} else if ($lower > 1) {
						# Left-trimmed, increment visible items on the right side
						$trimmed = 'left';
					} else if ($upper < $pages) {
						# Right-trimmed, increment visible items on the left side
						$trimmed = 'right';
					}
					# Adjust again
					$lower = $lower > 0 ? $lower : 1;
					$upper = $upper < $pages ? $upper : $pages;

					$url = $this->getCanonicalUrl();

					echo '<nav class="pagination"><ul>';
					# Go to first page
				 	if ($trimmed == 'left' || $trimmed == 'both') {
						echo '<li><a href="'.$url.'/page/1?show='.$show.$str_extra.'">&laquo;</a></li>';
					} else {
						echo '<li class="disabled"><a href="#">&laquo;</a></li>';
					}
					# Individual pages
					for ($p = $lower; $p <= $upper; $p++) {
						echo '<li class="'.($page == $p ? 'active' : '').'"><a href="'.$url.'/page/'.$p.$str_extra.'">'.$p.'</a></li>';
					}
					# Go to last page
					if ($trimmed == 'right' || $trimmed == 'both') {
						echo '<li><a href="'.$url.'/page/'.$pages.$str_extra.'">&raquo;</a></li>';
					} else {
						echo '<li class="disabled"><a href="#">&raquo;</a></li>';
					}
					echo '</ul></nav>';
				}
			}
		}

		public function queryEntities($args) {
			global $site;
			$dbh = $site->getDatabase();
			$ret = [];
			#
			$terms = get_item($args, 'terms');
			$search = get_item($args, 'search');
			$type = get_item($args, 'type');
			$status = get_item($args, 'status', 'Published');
			$author = get_item($args, 'author');
			$page = get_item($args, 'page', 1);
			$show = get_item($args, 'show', $this->pagination->show);
			$sort = get_item($args, 'sort', 'desc');
			$by = get_item($args, 'by', 'id');
			$include = get_item($args, 'include', []);
			$exclude = get_item($args, 'exclude', []);
			$debug = get_item($args, 'debug');
			#
			$whitelist_sort = ['asc', 'desc'];
			$whitelist_by = ['id', 'author', 'parent', 'title', 'excerpt', 'password', 'content', 'type', 'status', 'slug', 'position', 'views', 'comments', 'published', 'created', 'modified'];
			#
			$page = is_numeric($page) ? $page : 1;
			$show = is_numeric($show) ? $show : $this->pagination->show;
			$sort = in_array($sort, $whitelist_sort) ? $sort : 'desc';
			$by = in_array($by, $whitelist_by) ? $by : 'id';
			$offset = ($page - 1) * $show;
			$sort = strtoupper($sort);
			#
			$author_s = $author ? (is_array($author) ? $this->implodeSanitized(',', $author) : $dbh->quote($author)) : false;
			$type_s   = $type   ? (is_array($type)   ? $this->implodeSanitized(',', $type)   : $dbh->quote($type))   : false;
			$status_s = $status ? (is_array($status) ? $this->implodeSanitized(',', $status) : $dbh->quote($status)) : false;
			$search_s = $search ? $dbh->quote("%{$search}%") : false;
			#
			$conditions = 1;
			$conditions .= $author ? (is_array($author) ? " AND author IN ($author_s)" : " AND e.author = {$author_s}") : '';
			$conditions .= $type   ? (is_array($type) ? " AND type IN ($type_s)" : " AND e.type = {$type_s}") : '';
			$conditions .= $status ? (is_array($status) ? " AND status IN ($status_s)" : " AND e.status = {$status_s}") : '';
			$conditions .= $search ? " AND (e.title LIKE {$search_s} OR e.excerpt LIKE {$search_s} OR e.content LIKE {$search_s})" : '';
			#
			$include = $include ? (is_array($include) ? implode(', ', $include) : $include) : null;
			$exclude = $exclude ? (is_array($exclude) ? implode(', ', $exclude) : $exclude) : null;
			if ($include || $exclude) {
				$conditions .= $include ? " AND e.id IN ({$include})" : '';
				$conditions .= $exclude ? " AND e.id NOT IN ({$exclude})" : '';
			}
			#
			$tables = "entity e ";
			$order_limit = "ORDER BY e.{$by} {$sort} LIMIT {$offset}, {$show}";
			#
			if ($terms) {
				$terms_include = get_item($terms, 'include', []);
				$terms_exclude = get_item($terms, 'exclude', []);
				$terms_include = $terms_include ? (is_array($terms_include) ? implode(', ', $terms_include) : $terms_include) : null;
				$terms_exclude = $terms_exclude ? (is_array($terms_exclude) ? implode(', ', $terms_exclude) : $terms_exclude) : null;
				if ($terms_include || $terms_exclude) {
					$conditions .= $terms_include ? " AND te.id_term IN ({$terms_include})" : '';
					$conditions .= $terms_exclude ? " AND te.id_term NOT IN ({$terms_exclude})" : '';
					$conditions .= " AND e.id = te.id_entity";
					$tables .= ', term_entity te';
				}
			}
			#
			try {
				$sql = "SELECT e.* FROM {$tables} WHERE {$conditions} {$order_limit}";
				if ($debug) {
					echo $sql;
				}
				$stmt = $dbh->prepare($sql);
				$stmt->setFetchMode(PDO::FETCH_CLASS, 'Entity');
				$stmt->execute();
				$rows = $stmt->fetchAll();
				if ($rows) {
					$ret = $rows;
				}
				#
				$sql = "SELECT COUNT(e.id) AS total FROM {$tables} WHERE {$conditions}";
				$stmt = $dbh->prepare($sql);
				$stmt->execute();
				$row = $stmt->fetch();
				#
				$this->pagination->show = $show;
				$this->pagination->total = $row->total;
			} catch (PDOException $e) {
				error_log( $e->getMessage() );
			}
			return $ret;
		}

		public function listTerms($terms, $echo = false, $format = 'anchor') {
			$ret = [];
			$output = [];
			$formats = [];
			$formats['anchor'] = '<a href="%s">%s</a>';
			$formats['list'] = '<li><a href="%s">%s</a></li>';
			$formatter = $formats[$format];
			foreach ($terms as $term) {
				$name = $this->sanitizeText($term->name);
				$output[] = sprintf($formatter, $term->getPermalink(), $name);
				$ret[] = ['link' => $term->getPermalink(), 'title' => $name];
			}
			switch ($format) {
				case 'anchor':
					$output = implode(', ', $output);
				break;
				case 'list':
					$output = '<ul>'.implode("\n\t", $output).'</ul>';
				break;
			}
			if ($echo) {
				echo $output;
			}
			return $ret;
		}

		public function getCanonicalUrl($echo = false) {
			global $site;
			$request = $site->getRequest();
			$str_paginated = $this->getOption('paginated_slug', 'page');
			$parts = explode('?', $request->uri);
			$path = get_item($parts, 0);
			$params = get_item($parts, 1);
			$url = $site->urlTo( preg_replace("/\/{$str_paginated}\/\d+$/", '', $path) );
			if ($echo) {
				echo $url;
			}
			return $url;
		}

		public function uploadAttachment($file) {
			global $site;
			$ret = false;
			$path = $this->getUploadPath();
			$content_dir = $this->getDir('content');
			$uploads_dir = $this->getDir('uploads');
			if( $file && $file['tmp_name'] ) {
				# Get name parts
				$base_name = basename( $file['name'] );
				$name = substr( $base_name, 0, strrpos($base_name, '.') );
				$ext = substr( $base_name, strrpos($base_name, '.') + 1 );
				# Normalize JPEG extensions
				$ext = ($ext == 'jpeg') ? 'jpg' : $ext;
				#
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mime = finfo_file($finfo, $file['tmp_name']);
				finfo_close($finfo);
				if ($mime == 'text/x-php') {
					# Illegal type, abort
					return false;
				}
				#
				$path = $this->getUploadPath();
				if (! file_exists( $site->baseDir("{$content_dir}{$uploads_dir}/{$path}") ) ) {
					$year = date('Y');
					$month = date('m');
					@mkdir( $site->baseDir("{$content_dir}{$uploads_dir}/{$year}") );
					@mkdir( $site->baseDir("{$content_dir}{$uploads_dir}/{$year}/{$month}") );
				}
				# Generate a destination name
				$dest_name = $site->toAscii($name);
				$dest_path = $site->baseDir("{$content_dir}{$uploads_dir}/{$path}/{$dest_name}.{$ext}");
				# Check whether the name exists nor not
				if ( file_exists($dest_path) ) {
					$dest_name = $site->toAscii( $name . uniqid() );
					$dest_path = $site->baseDir("{$content_dir}{$uploads_dir}/{$path}/{$dest_name}.{$ext}");
				}
				# Get MIME type
				if ( $file['type'] ) {
					$mime = $file['type'];
				} else {
					switch ($ext) {
						case 'gif':
						case 'png':
							$mime = "image/{$ext}";
						case 'jpg':
							$mime = 'image/jpeg';
							break;
						case 'mpeg':
						case 'mp4':
						case 'ogg':
						case 'webm':
							$mime = "video/{$ext}";
							break;
						case 'pdf':
						case 'zip':
							$mime = "application/{$ext}";
							break;
						case 'csv':
						case 'xml':
							$mime = "text/{$ext}";
							break;
						default:
							$mime = 'application/octet-stream';
					}
				}
				# Move the uploaded file
				move_uploaded_file($file['tmp_name'], $dest_path);
				# Crunching
				if ( substr($mime, 0, 5) == 'image' ) {
					$this->crunchImage($dest_path);
				}
				# Create and save the attachment
				$attachment = new Entity();
				$attachment->slug = $dest_name;
				$attachment->title = $name;
				$attachment->content = "{$dest_name}.{$ext}";
				$attachment->mime_type = $mime;
				$attachment->type = 'attachment';
				$attachment->status = 'Published';
				$attachment->published = date('Y-m-d H:i:s');
				$attachment->save();
				$ret = $attachment;
				if ( substr($mime, 0, 5) == 'image' ) {
					$size = getimagesize($dest_path);
					$exif = @exif_read_data($dest_path);
					$info = [];
					$info['width'] = $size[0];
					$info['height'] = $size[1];
					$info['channels'] = get_item($size, 'channels');
					$info['bits'] = get_item($size, 'bits');
					$info['exif'] = $exif;
					$attachment->updateMeta('info', $info);
				}
			}
			return $ret;
		}

		public function crunchAttachment($id, $overwrite = false) {
			$path = $this->getImage($id, null, 'path');
			if ($path) {
				$this->crunchImage($path, $overwrite);
				#
				$attachment = Entities::getById($id);
				if ($attachment) {
					$size = getimagesize($path);
					$exif = @exif_read_data($path);
					$info = [];
					$info['width'] = $size[0];
					$info['height'] = $size[1];
					$info['channels'] = get_item($size, 'channels');
					$info['bits'] = get_item($size, 'bits');
					$info['exif'] = $exif;
					$attachment->updateMeta('info', $info);
				}
			}
			return $this;
		}

		public function getAttachment($id, $type = 'url', $echo = false) {
			global $site;
			$ret = false;
			$attachment = is_numeric($id) ? Entities::getById($id) : Entities::getBySlug($id);
			if ($attachment) {
				$content_dir = $this->getDir('content');
				$uploads_dir = $this->getDir('uploads');
				$path = $this->getUploadPath($attachment->created);
				$url = $site->urlTo("{$content_dir}{$uploads_dir}/{$path}/{$attachment->content}");
				$path = $site->baseDir("{$content_dir}{$uploads_dir}/{$path}/{$attachment->content}");
				switch ($type) {
					case 'url':
						$ret = $url;
					break;
					case 'path':
						$ret = $path;
					break;
					case 'object':
						$attachment->url = $url;
						// $attachment->path = $path;
						$ret = $attachment;
					break;
				}
			}
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		public function getImage($id, $size = 'thumbnail', $type = 'url', $echo = false, $attrs = []) {
			global $site;
			$ret = false;
			$attachment = is_numeric($id) ? Entities::getById($id) : Entities::getBySlug($id);
			if ($attachment) {
				$is_image = in_array($attachment->mime_type, ['image/png', 'image/gif', 'image/jpeg']);
				if ($is_image) {
					$content_dir = $this->getDir('content');
					$uploads_dir = $this->getDir('uploads');
					$path = $this->getUploadPath($attachment->created);
					$name = substr( $attachment->content, 0, strrpos($attachment->content, '.') );
					$ext = substr( $attachment->content, strrpos($attachment->content, '.') + 1 );
					#
					$urls = [];
					$paths = [];
					foreach ($this->image_sizes as $size_name => $config) {
						$urls[$size_name] = $site->urlTo("{$content_dir}{$uploads_dir}/{$path}/{$name}-{$size_name}.{$ext}");
						$paths[$size_name] = $site->baseDir("{$content_dir}{$uploads_dir}/{$path}/{$name}-{$size_name}.{$ext}");
					}
					#
					$url = $site->urlTo("{$content_dir}{$uploads_dir}/{$path}/{$attachment->content}");
					$path = $site->baseDir("{$content_dir}{$uploads_dir}/{$path}/{$attachment->content}");
					$image = new stdClass();
					$image->url = $url;
					// $image->path = $path;
					$image->sizes = $urls;
					#
					switch ($type) {
						case 'url':
							$ret = (!$size || $size == 'full') ? $url : $urls[$size];
						break;
						case 'path':
							$ret = (!$size || $size == 'full') ? $path : $paths[$size];
						break;
						case 'img':
							$url = (!$size || $size == 'full') ? $url : $urls[$size];
							$title = $this->sanitizeText($attachment->title);
							$attrs = $attrs ? $this->buildAttributes($attrs) : '';
							$ret = '<img src="'.$url.'" '.$attrs.'alt="'.$title.'">';
						break;
						case 'object':
							$ret = $image;
						break;
					}
				}
			}
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		public function getEndpoint($service, $echo = false) {
			global $site;
			$ret = false;
			$endpoint = '';
			switch ($service) {
				case 'comments':
					$endpoint = 'comments';
				break;
			}
			if ($endpoint) {
				$ret = $site->urlTo("/cms/api/{$endpoint}");
			}
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		public function getCsrfToken($action, $type = 'string', $echo = false, $exp = '+12 hours') {
			global $site;
			$ret = false;
			$auth = [$action, strtotime($exp)];
			$auth = implode('|', $auth);
			$salt = $site->getGlobal('token_salt');
			$hash = hash_hmac('sha256', $auth, $salt);
			$token = "{$auth}|{$hash}";
			switch ($type) {
				case 'string':
					$ret = $token;
				break;
				case 'input':
					$ret = '<input type="hidden" name="csrf" value="'.$token.'">';
				break;
			}
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		public function checkCsrfToken($action, $token) {
			global $site;
			$parts = explode('|', $token);
			$salt = $site->getGlobal('token_salt');
			$action_src = get_item($parts, 0);
			$auth = get_item($parts, 1);
			$hash = hash_hmac('sha256', "{$action_src}|{$auth}", $salt);
			$check = "{$action_src}|{$auth}|{$hash}";
			return ($token && $auth && $token == $check && time() < $auth && $action == $action_src);
		}

		public function cacheStore($content) {
			global $site;
			$caching = $site->cms->getOption('caching', 0);
			$cache_exp = $site->cms->getOption('cache_exp', 900);
			if ($caching && $content) {
				$hash = md5( $site->getRouter()->getCurrentUrl() );
				$cache_file = $site->baseDir("/content/cache/{$hash}.cache");
				file_put_contents($cache_file, gzencode($content));
			}
			return $this;
		}

		public function cacheRetrieve() {
			global $site;
			$ret = null;
			$caching = $site->cms->getOption('caching', 0);
			$cache_exp = $site->cms->getOption('cache_exp', 900);
			$hash = md5( $site->getRouter()->getCurrentUrl() );
			$cache_file = $site->baseDir("/content/cache/{$hash}.cache");
			if ($caching && file_exists($cache_file) && time() - filemtime($cache_file) < $cache_exp) {
				$ret = gzdecode( file_get_contents($cache_file) );
			}
			return $ret;
		}

		public function cacheDelete($path) {
			global $site;
			$hash = md5($path);
			$cache_file = $site->baseDir("/content/cache/{$hash}.cache");
			if ( file_exists($cache_file) ) {
				unlink($cache_file);
			}
			return $this;
		}

		public function cacheInfo($path) {
			global $site;
			$ret = false;
			$hash = md5($path);
			$cache_file = $site->baseDir("/content/cache/{$hash}.cache");
			if ( file_exists($cache_file) ) {
				$ret = new stdClass();
				$ret->size = filesize($cache_file);
				$ret->modified = filemtime($cache_file);
			}
			return $ret;
		}

		public function cacheEmpty() {
			global $site;
			$files = glob( $site->baseDir('/content/cache/*.cache') );
			if ($files) {
				foreach($files as $file){
					if( is_file($file) ) {
						unlink($file);
					}
				}
			}
			return $this;
		}

		public function isPluginLoaded($plugin) {
			$var = get_item($this->plugins, $plugin, false);
			return is_object($var);
		}

		public function loadPlugin($plugin) {
			global $site;
			$ret = null;
			$content_dir = $this->getDir('content');
			$plugins_dir = $this->getDir('plugins');
			$plugin_uri = $site->baseUrl("{$content_dir}{$plugins_dir}/{$plugin}");
			$plugin_dir = $site->baseDir("{$content_dir}{$plugins_dir}/{$plugin}");
			$plugin_file = file_exists("{$plugin_dir}/{$plugin}.php") ? "{$plugin_dir}/{$plugin}.php" : "{$plugin_dir}/plugin.php";
			if ( file_exists($plugin_file) ) {
				include $plugin_file;
				$plugin_class = ucfirst( dash_to_camel("{$plugin}-plugin") );
				if ( class_exists($plugin_class) ) {
					$instance = new $plugin_class;
					call_user_func([$instance, 'load'], $plugin_uri, $plugin_dir);
					$ret = $instance;
				}
			}
			return $ret;
		}

		protected function crunchImage($src, $overwrite = false) {
			global $site;
			# Extract file components
			$path = substr( $src, 0, strrpos($src, '/') );
			$file = substr( $src, strrpos($src, '/') + 1 );
			$name = substr( $file, 0, strrpos($file, '.') );
			$ext = substr( $file, strrpos($file, '.') + 1 );
			# Normalize JPEG extensions
			$ext = ($ext == 'jpeg') ? 'jpg' : $ext;
			//
			$content_dir = $this->getDir('content');
			$uploads_dir = $this->getDir('uploads');
			$path = str_replace($site->baseDir("{$content_dir}{$uploads_dir}"), '', $path);
			# Build file names array according to the required sizes
			$images = [];
			foreach ($this->image_sizes as $size => $config) {
				$images[$size] = $site->baseDir("{$content_dir}{$uploads_dir}/{$path}/{$name}-{$size}.{$ext}");
			}
			if ( class_exists('\claviska\SimpleImage') ) {
				try {
					foreach ($this->image_sizes as $size => $config) {
						$image = new \claviska\SimpleImage();
						$image->fromFile($src)->autoOrient();
						$is_horizontal = $image->getAspectRatio() > 1;
						if ( !$config['enlarge'] && ( $image->getWidth() < $config['width'] || $image->getHeight() < $config['height'] ) ) continue;
						switch ( $config['strategy'] ) {
							case 'short_side':
								if ($is_horizontal) {
									// $width = ;
									// $height = ;
									$image->resize( null, $config['width'] );
								} else {
									// $width = ;
									// $height = ;
									$image->resize( $config['width'], null );
								}
							break;
							case 'long_side':
								if ($is_horizontal) {
									$image->resize( $config['width'], null );
								} else {
									$image->resize( null, $config['width'] );
								}
							break;
							case 'absolute':
								$image->resize( $config['width'], $config['height'] );
							break;
							case 'thumbnail':
								$image->thumbnail( $config['width'], $config['height'] );
							break;
							case 'fit':
							default:
								$image->bestFit( $config['width'], $config['height'] );
							break;
						}
						if ($overwrite && file_exists( $images[$size] )) {
							unlink( $images[$size] );
						}
						$image->toFile( $images[$size] );
					}
				} catch (Exception $e) {
					error_log( $e->getMessage() );
				}
			}
			return $this;
		}

		protected function getUploadPath($date = null) {
			$time = $date ? strtotime($date) : time();
			$path = date('Y/m', $time);
			return $path;
		}

		protected function buildAttributes($attrs, $echo = false) {
			$ret = false;
			if ($attrs) {
				$ret = [];
				foreach ($attrs as $key => $value) {
					$ret[] = sprintf('%s="%s"', $this->sanitizeText($key), $this->sanitizeText($value));
				}
				$ret = implode(' ', $ret);
			}
			if ($echo) {
				echo $ret;
			}
			return $ret;
		}

		protected function renderSearchResults() {
			global $site;
			$dbh = $site->getDatabase();
			$request = $site->getRequest();
			$ret = false;
			#
			$param = $this->getOption('search_param', 's');
			$search = $request->param($param);
			#
			if ($search) {
				$types = [];
				if ($this->entities) {
					foreach ($this->entities as $name => $config) {
						if ( !$config->flags->search || !$config->flags->query ) continue;
						$types[] = $name;
					}
				}
				#
				$args = [];
				$args['search'] = $search;
				$args['type'] = $types;
				$args['page'] = $this->is_paginated ? $this->pagination->page : 1;
				$entities = $this->queryEntities($args);
				#
				$this->search = $search;
				#
				$site->addBodyClass('search');
				#
				$data = [];
				$data['search'] = $search;
				$params = [];
				$params['type'] = 'search';
				$params['data'] = $data;
				$ret = $this->renderArchive($entities, $params);
			}
			#
			return $ret;
		}

		protected function renderAuthorArchive($author, $user) {
			global $site;
			$request = $site->getRequest();
			$ret = false;
			#
			$types = [];
			if ($this->entities) {
				foreach ($this->entities as $name => $config) {
					if ( !$config->flags->archive || !$config->flags->query ) continue;
					$types[] = $name;
				}
			}
			#
			$args = [];
			$args['author'] = $user->id;
			$args['type'] = $types;
			$args['page'] = $this->is_paginated ? $this->pagination->page : 1;
			$entities = $this->queryEntities($args);
			#
			$site->addBodyClass(['archive', 'author', "author-{$author}"]);
			#
			$data = [];
			$data['author'] = $author;
			$data['user'] = $user;
			$params = [];
			$params['type'] = 'author';
			$params['data'] = $data;
			$ret = $this->renderArchive($entities, $params);
			#
			return $ret;
		}

		protected function renderEntityArchive($type, $entity) {
			global $site;
			$request = $site->getRequest();
			$ret = false;
			#
			$args = [];
			$args['type'] = $type;
			$args['page'] = $this->is_paginated ? $this->pagination->page : 1;
			$entities = $this->queryEntities($args);
			#
			$site->addBodyClass(['archive', "archive-{$type}"]);
			#
			$data = [];
			$data['type'] = $type;
			$data['entity'] = $entity;
			$params = [];
			$params['type'] = 'entity';
			$params['data'] = $data;
			$ret = $this->renderArchive($entities, $params);
			#
			return $ret;
		}

		protected function renderTaxonomyArchive($taxonomy, $term) {
			global $site;
			$request = $site->getRequest();
			$ret = false;
			#
			$entities = [];
			$args = [];
			$terms = [];
			$terms['include'] = $term->id;
			$args['terms'] = $terms;
			$args['page'] = $this->is_paginated ? $this->pagination->page : 1;
			$entities = $this->queryEntities($args);
			#
			$site->addBodyClass(['archive', $taxonomy->slug, "{$taxonomy->slug}-{$term->slug}"]);
			#
			$data = [];
			$data['taxonomy'] = $taxonomy;
			$data['term'] = $term;
			$params = [];
			$params['type'] = 'taxonomy';
			$params['data'] = $data;
			$ret = $this->renderArchive($entities, $params);
			#
			return $ret;
		}

		protected function renderArchive($entities, $params) {
			global $site;
			$request = $site->getRequest();
			#
			$data = [];
			$templates = [];
			$archive = new stdClass();
			switch ( $params['type'] ) {
				case 'search':
					$templates = ['search', 'archive'];
					$formatter = $this->getOption('title_search', 'Search results for "%s"');
					$archive->title = sprintf($formatter, $params['data']['search']);
					$archive->slug = 'search';
				break;
				case 'author':
					$templates = ["author-{$params['data']['author']}", 'archive'];
					$formatter = $this->getOption('title_author_archive', '%s archive');
					$archive->title = sprintf($formatter, $params['data']['user']->nicename);
					$archive->slug = "archive-{$params['data']['author']}";
				break;
				case 'entity':
					$templates = ["archive-{$params['data']['type']}", 'archive'];
					$formatter = $this->getOption('title_entity_archive', '%s archive');
					$archive->title = sprintf($formatter, $params['data']['entity']->strings->name);
					$archive->slug = "archive-{$params['data']['type']}";
				break;
				case 'taxonomy':
					$templates = ["taxonomy-{$params['data']['taxonomy']->slug}-{$params['data']['term']->slug}", "taxonomy-{$params['data']['taxonomy']->slug}", 'taxonomy', 'archive'];
					$formatter = $this->getOption('title_taxonomy_archive', '%s archive');
					$archive->title = sprintf($formatter, $params['data']['term']->name);
					$archive->slug = "archive-{$params['data']['term']->taxonomy}-{$params['data']['term']->slug}";
				break;
			}
			#
			$url = $this->getCanonicalUrl();
			#
			$image = $this->getOption("{$archive->slug}_image");
			if ( $image ) {
				$image = $this->getImage($image, null);
			}
			if ($image) {
				$site->addMeta('og:image', $image, 'property');
				$site->addMeta('twitter:image', $image);
			}
			#
			$keywords = $this->getOption("{$archive->slug}_keywords");
			if ($keywords) $site->addMeta('keywords', $keywords);
			#
			$description = $this->getOption("{$archive->slug}_description", $archive->title);
			$title = $this->getOption("{$archive->slug}_title", $archive->title);
			#
			$archive->title = $title;
			$archive->description = $description;
			#
			$site->addMeta('og:url', $url, 'property');
			$site->addMeta('og:title', $this->sanitizeText($title), 'property');
			$site->addMeta('og:description', $this->sanitizeText($description), 'property');
			#
			$site->addMeta('twitter:url', $url);
			$site->addMeta('twitter:title', $this->sanitizeText($title));
			$site->addMeta('twitter:description', $this->sanitizeText($description));
			#
			$site->setPageTitle( $site->getPageTitle($archive->title) );
			#
			$data['entities'] = $entities;
			$data['archive'] = $archive;
			$template = $templates ? $this->resolveTemplate($templates) : false;
			return $template ? $this->render($template, $data) : false;
		}

		protected function renderSingle($slug) {
			global $site;
			$request = $site->getRequest();
			$response = $site->getResponse();
			$ret = false;
			#
			$entity = $this->getEntity( array_pop($slug) );
			if ( $entity && $this->checkPath($slug, $entity) ) {
				#
				$image = $entity->getMeta('share_image');
				if ( $image ) {
					$image = $this->getImage($image, null);
				} else if ( $entity->hasThumbnail() ) {
					$image = $entity->getThumbnail('medium');
				}
				if ($image) {
					$site->addMeta('og:image', $image, 'property');
					$site->addMeta('twitter:image', $image);
				}
				#
				$keywords = $entity->getMeta('keywords');
				if ($keywords) $site->addMeta('keywords', $keywords);
				#
				$description = $entity->getMeta('share_description', $entity->excerpt);
				$title = $entity->getMeta('share_title', $entity->title);
				#
				$site->addMeta('description', $this->sanitizeText($description));
				#
				$site->addMeta('og:url', $entity->getPermalink(), 'property');
				$site->addMeta('og:title', $this->sanitizeText($title), 'property');
				$site->addMeta('og:description', $this->sanitizeText($description), 'property');
				#
				$site->addMeta('twitter:url', $entity->getPermalink());
				$site->addMeta('twitter:title', $this->sanitizeText($title));
				$site->addMeta('twitter:description', $this->sanitizeText($description));
				#
				$site->setPageTitle( $site->getPageTitle($entity->title) );
				#
				$templates = ["{$entity->type}-{$entity->slug}", "single-{$entity->type}-{$entity->slug}", "{$entity->type}-{$entity->id}", "{$entity->type}", "single-{$entity->type}", 'single'];
				#
				$classes = ['single', $entity->type, "{$entity->type}-{$entity->id}", "{$entity->type}-{$entity->slug}"];
				if ($entity->parent > 0) $classes[] = "{$entity->type}-child";
				$site->addBodyClass($classes);
				#
				$entity->views = $entity->views + 1;
				$entity->save();
				#
				$data = [];
				$data['entity'] = $entity;
				$data['author'] = $site->cms->getAuthor($entity);
				$data['terms'] = $entity->getTerms();
				$template = $templates ? $this->resolveTemplate($templates) : false;
				$ret = $template ? $this->render($template, $data) : false;
			} else {
				// ERROR
			}
			#
			return $ret;
		}

		protected function render($template, $data) {
			global $site;
			$ret = false;
			#
			if ($this->theme_config->assets->styles) {
				foreach ($this->theme_config->assets->styles as $key => $value) {
					$value = str_replace('%theme_url%', $this->theme_uri, $value);
					$site->registerStyle($key, $value, true);
					$site->enqueueStyle($key, $value);
				}
			}
			if ($this->theme_config->assets->scripts) {
				foreach ($this->theme_config->assets->scripts as $key => $value) {
					$value = str_replace('%theme_url%', $this->theme_uri, $value);
					$site->registerScript($key, $value, true);
					$site->enqueueScript($key, $value);
				}
			}
			#
			ob_start();
			$site->setDir('partials', "/content/themes/{$this->theme}/templates");
			$site->setDir('pages', "/content/themes/{$this->theme}/templates");
			$site->setDir('images', "/content/themes/{$this->theme}/assets/images");
			$site->setDir('scripts', "/content/themes/{$this->theme}/assets/scripts");
			$site->setDir('styles', "/content/themes/{$this->theme}/assets/styles");
			$site->render($template, $data);
			$ret = ob_get_flush();
			#
			return $ret;
		}

		protected function resolveTemplate($templates, $fallback = 'index', $partial = false) {
			global $site;
			$ret = $fallback;
			foreach ($templates as $entry) {
				$entry = $partial ? "_{$entry}" : $entry;
				$template_path = "{$this->theme_dir}/templates/{$entry}.php";
				if ( file_exists( $template_path ) ) {
					$ret = $partial ? ltrim($entry, '_') : $entry;
					break;
				}
			}
			return $ret;
		}

		protected function checkPath($path, $entity) {
			$ret = false;
			if ( $entity->parent == 0 && count($path) == 0 ) {
				$ret = true;
			} else {
				$slug = array_pop($path);
				$parent = $this->getEntity($entity->parent);
				if ($parent) {
					$ret = $slug == $parent->slug ? $this->checkPath($path, $parent) : false;
				}
			}
			return $ret;
		}

		protected function implodeSanitized($glue, $pieces) {
			global $site;
			$dbh = $site->getDatabase();
			$pieces = array_map([$dbh, 'quote'], $pieces);
			return implode($glue, $pieces);
		}

		protected static function handleShortcode($args) {
			global $site;
			$ret = get_item($args, 0);
			$name = get_item($args, 1);
			$params = get_item($args, 2, []);
			$callable = get_item($site->cms->shortcodes, $name);
			if ($callable && is_callable($callable)) {
				$fn_params = [];
				if ($params) {
					preg_match_all('/(\S+)=["\']?((?:.(?!["\']?\s+(?:\S+)=|[>"\']))+.)["\']?/', $params, $matches, PREG_SET_ORDER);
					if ($matches) {
						foreach ($matches as $value) {
							$name = get_item($value, 1);
							if (! $name ) continue;
							$fn_params[$name] = get_item($value, 2);
						}
					}
				}
				$ret = call_user_func_array($callable, $fn_params);
			}
			return $ret;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the *CMS* instance.
		 * @return void
		 */
		private function __clone() {
			#
		}

		/**
		 * Private unserialize method to prevent unserializing of the *CMS* instance.
		 * @return void
		 */
		private function __wakeup() {
			#
		}
	}

	$site->cms = CMS::getInstance();

?>