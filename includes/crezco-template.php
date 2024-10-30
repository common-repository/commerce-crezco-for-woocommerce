<?php
final class WC_Crezco_Template {
	private $data = array();
		
	public function set($key, $value) {
		$this->data[$key] = $value;
	}

	public function render($template) {
		$file = plugin_dir_path(__FILE__) . 'views/' . $template . '.tpl';

		if (is_file($file)) {
			extract($this->data);

			ob_start();

			require($file);

			return ob_get_clean();
		}

		throw new \Exception('Error: Could not load template ' . $file . '!');
		exit();
	}	
}