<?php

	class XHRResponse {

		public $result;
		public $data;
		public $message;

		function __construct($result = 'error') {
			$this->result = $result;
			$this->data = [];
			$this->message = '';
		}

		/**
		 * Respond to XHR Request
		 * @param  string $format Response format (json|xml), 'json' is the default
		 */
		function respond($format = 'json', $exit = false) {
			if (!$this->message) {
				unset($this->message);
			}
			switch ($format) {
				case 'json':
					$response = json_encode($this);
					$mime = 'application/json';
				break;
				case 'xml':
					$xml = $this->encodeXML($this);
					$response = $xml ? $xml->asXML() : '';
					$mime = 'text/xml';
				break;
			}
			$length = strlen($response);
			header("Content-Length: {$length}");
			header("Content-Type: {$mime}");
			echo $response;
			if ($exit) {
				exit;
			}
		}

		protected function encodeXML($data, &$xml_data  = null) {
			$xml_data = $xml_data ?: new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><response></response>');
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
	}

?>