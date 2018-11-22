<?php

/**
 * Javascript Decoder
 *
 * @author Masih Yeganeh <masihyeganeh@outlook.com>
 * @package YoutubeDownloader
 *
 * @version 2.9.6
 * @license http://opensource.org/licenses/MIT MIT
 */

namespace Masih\YoutubeDownloader;

class JsDecoder
{
	protected $path;
	protected $code;
	protected $type = null;
	protected $objects;
	protected $urlHash;
	protected $prefix = '';
	protected $phpCode = '';
	protected $v8jsCode = '';
	protected $initialized = false;

	function __construct($jsCodeUrl) {
		if (empty($jsCodeUrl))
			throw new YoutubeException('js code URL is empty', 12);
		$this->path = realpath('');
		if (file_exists(__DIR__.'/../../../../autoload.php')) // Installed as dependency
			$this->path = realpath(__DIR__.'/../../../../../');
		if (defined('GLOBAL_DOWNLOADER') || file_exists(__DIR__.'/../../vendor/autoload.php'))
			$this->path = realpath(__DIR__.'/../../'); // Downloaded or Installed globally
		$this->path .= DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR;

		if (!file_exists($this->path))
			@mkdir($this->path, 0777, true);

		$this->urlHash = strtolower(md5($jsCodeUrl));
		$this->prefix = '___decryption_' . $this->urlHash . '_';

		$file = $this->path . $this->urlHash;
		if (file_exists($file . 'p')) {
			include_once ($file . 'p');
			$this->initialized = true;
			$this->type = 'php';
		} elseif (file_exists($file . 'j')) {
			$this->v8jsCode = file_get_contents($file . 'j');
			$this->initialized = true;
			$this->type = 'js';
		}
	}

	public function isInitialized() {
		return $this->initialized;
	}

	public function parseJsCode($jsCode) {
		if (empty($jsCode))
			throw new YoutubeException('js code is empty', 12);

		$this->code = $jsCode;
		$this->objects = array();

		if (preg_match('/(?:["\']signature["\']\s*,\s*|\.sig\|\||yt\.akamaized\.net\/\)\s*\|\|\s*.*?\s*c\s*&&\s*d\.set\([^,]+\s*,\s*(?:\([^\)]+\)\()?|\bc\s*&&\s*d\.set\([^,]+\s*,\s*)([a-zA-Z0-9$]+)\(/', $this->code, $matches)) {
			$signFunctionName = $matches[1];
			$this->getFunction($signFunctionName);
			$file = $this->path . $this->urlHash;
			if ($this->parsedCodeIsValid()) {
				include_once ($file . 'p');
				$this->initialized = true;
				$this->type = 'php';
			} else {
				$this->prepareV8JsCode($signFunctionName);
				$this->initialized = true;
				$this->type = 'js';
			}
		} else
			throw new YoutubeException('Deprecated', 9);
	}

	protected function getFunction($name) {
		$phpCode = &$this->phpCode;
		preg_replace_callback('/\nfunction\s*' . $name .'\s*\(([^\)]*)\)\s*{([^}]*)};?|\n' . $name . '\s*=\s*function\s*\(([^\)]*)\)\s*{([^}]*)};/', function ($matches) use (&$phpCode) {
			$args = preg_split('/\s*,\s*/', $matches[2] ?: $matches[3]);
			$code = $matches[4];
			foreach ($args as &$arg) {
				$code = preg_replace('/\b' . $arg . '\b/', '$' . $arg, $code);
				$arg = '$' . $arg;
			}
			$args = implode(',', $args);

			$code = preg_replace('/(\$[\w\d]+)\.split\(([^\)]+)\)/', 'explode($2, $1)', $code);
			$code = preg_replace('/(\$[\w\d]+)\.join\(([^\)]+)\)/', 'implode($2, $1)', $code);
			$code = preg_replace('/explode\("",\s*(\$[\w\d]+)\)/', 'str_split($1)', $code);

			if (preg_match_all('/(([\w\d]+)\.([\w\d]+)\()/', $code, $matches)) {
				$objs = $matches[2];
				$methods = $matches[3];

				for ($i = 0; $i < count($objs); $i++) {
					$obj = $objs[$i];
					$method = $methods[$i];

					$this->getObject($obj);
					$code = str_replace($obj . '.' . $method . '(', $this->prefix . $obj . '__' . $method . '(', $code);
				}
			}

			$phpCode .= 'function ' . $this->prefix . 'decode(' . $args . ') {' . $code . ';}';
		}, $this->code);
	}


	protected function getObject($name) {
		if (array_search($name, $this->objects) !== false) return;
		preg_replace_callback('/var ' . $name . '\s*=\s*{(.*?)};/ms', function ($matches) use ($name) {
			$this->getFunctions($matches[1], $name);
		}, $this->code);
		array_push($this->objects, $name);
	}

	protected function getFunctions($code, $objName) {
		if (preg_match_all('/([\w\d]*)\s*:\s*function\s*([\w\d]*)\s*\(([^\)]*)\)\s*{([^}]*)}/', $code, $matches)) {
			$names = $matches[1];
			$arguments = $matches[3];
			$codes = $matches[4];

			for ($i = 0; $i < count($names); $i++) {
				$args = preg_split('/\s*,\s*/', $arguments[$i]);
				$code = $codes[$i];
				$name = $names[$i];

				foreach ($args as &$arg) {
					$code = preg_replace('/\b' . $arg . '\b/', '$' . $arg, $code);
					$arg = '$' . $arg;
				}
				$args = '&' . implode(',', $args);

				if (preg_match_all('/var ([\w\d]+)/', $code, $vars)) {
					foreach ($vars[1] as $variable) {
						$code = str_replace('var ' . $variable, $variable, $code);
						$code = preg_replace('/\b' . $variable . '\b/', '$' . $variable, $code);
					}
				}
				$code = preg_replace('/(\$[\w\d]+)\.length/', 'count($1)', $code);
				$code = preg_replace('/(\$[\w\d]+)\.splice\(([^,]+),([^\)]+)\)/', 'array_splice($1, $2, $3)', $code);
				$code = preg_replace('/(\$[\w\d]+)\.reverse\(\)/', '$1 = array_reverse($1)', $code);

				$this->phpCode .= 'function ' . $this->prefix . $objName . '__' . $name .'(' . $args . ') {' . $code . ';}' . "\n";
			}
		}
	}

	public function decode($encoded) {
		if ($this->initialized) {
			if ($this->type == 'php')
				return call_user_func($this->prefix . 'decode', $encoded);
			elseif ($this->type == 'js')
				return $this->v8jsDecrypt($encoded);
		} else
			throw new YoutubeException('dunno', 10);
		return '';
	}

	protected function prepareV8JsCode($functionName) {
		$code = str_replace(
			'var window=this;',
			'Array.concat=[].concat;Array.slice=[].slice;var window=PHP.this;',
			$this->code
		);
		$code = str_replace('.prototype.', '.', $code);
		$code = str_replace('(0,window.decodeURI)', 'window.decodeURI', $code);
		$code = preg_replace('/^(\w+\.install)\(/m', '$1=function(){};$1(', $code);
		$code = str_replace(
			'})(_yt_player);',
			'window.signature=' . $functionName . ';})(_yt_player);',
			$code
		);
		$this->v8jsCode = $code;

		$file = $this->path . $this->urlHash . 'j';
		if (file_exists($this->path))
			file_put_contents($file, $this->v8jsCode);
	}

	protected function v8jsDecrypt($signature) {
		if ($this->initialized) {
			if (!class_exists('V8Js') || !class_exists('V8JsException')) {
				throw new YoutubeException('Please install V8js [ http://php.net/manual/en/book.v8js.php ] to download encrypted videos.', 11);
			}

			$v8 = new \V8Js();
			$v8->this = new WindowStub();

			$code = $this->v8jsCode . 'signature=PHP.this.$signature("' . $signature . '");';

			try {
				return $v8->executeString($code, 'base.js');
			} catch (\V8JsException $e) {
				$code = $this->v8jsCode . 'signature=PHP.this.signature("' . $signature . '");';

				try {
					return $v8->executeString($code, 'base.js');
				} catch (\V8JsException $e) {
					throw new YoutubeException($e, 13);
				}
			}
		} else
			throw new YoutubeException('dunno', 11);
	}

	protected function parsedCodeIsValid() {
		$result = false;
		$file = $this->path . $this->urlHash . 'p';
		if (!file_exists($this->path)) return false;

		file_put_contents($file, '<?php ' . $this->phpCode);

		if (class_exists('ParseError')) {
			try {
				include $file;
				return true;
			} catch (\ParseError $error) {}
		} else {
			$output = shell_exec('php -l "' . $file . '"');
			preg_replace("/error:/", "", $output, -1, $count);
			if($count === 0) return true;
		}

		unlink($file);
		return $result;
	}
}
