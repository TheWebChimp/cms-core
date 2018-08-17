<?php

	abstract class CMSPlugin {

		abstract function load($uri, $dir);

		public function init() {
			return false;
		}

		public function install($options) {
			return false;
		}

		public function activate() {
			return false;
		}

		public function deactivate() {
			return false;
		}

		public function uninstall($options) {
			return false;
		}

		public function getActions() {
			return false;
		}
	}

?>