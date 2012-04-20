<?php

/**
 * Rah_meta plugin for Textpattern CMS
 *
 * @author Jukka Svahn
 * @date 2012-
 * @license GNU GPLv2
 * @link http://rahforum.biz/plugins/rah_metas
 *
 * Copyright (C) 2012 Jukka Svahn <http://rahforum.biz>
 * Licensed under GNU Genral Public License version 2
 * http://www.gnu.org/licenses/gpl-2.0.html
 */

	register_callback(array('rah_meta', 'output_buffer'), 'textpattern');

/**
 * The main tag
 */

	function rah_meta($atts, $thing=NULL) {
		
		extract(lAtts(array(
			'return' => NULL,
			'name' => NULL,
			'value' => NULL,
		), $atts, 0));
		
		if($name === NULL) {
			trigger_error(gTxt('invalid_attribute_value', array('{name}' => 'name')));
			return;
		}
		
		if($thing !== NULL) {
			$value = parse($thing);
		}
		
		if($value === NULL) {
			return rah_meta::factory()->housing($name, $atts);
		}
		
		rah_meta::factory()->set($name, $value);
		return;
	}

/**
 * Handles meta data
 */

class rah_meta {

	/**
	 * Randomizer token
	 */

	private $token;
	
	/**
	 * Supports multibyte or not
	 */
	
	private $multibyte = true;
	
	/**
	 * Pushed data
	 */
	
	private $pipeline = array();
	
	/**
	 * Returning points
	 */
	
	private $housing = array();
	
	/**
	 * Registered to the OB or not
	 */
	
	private $registered = false;
	
	/**
	 * Currently handled data
	 */
	
	private $r = array();
	
	/**
	 */
	
	static public $instance;
	
	/**
	 * Constructor
	 */
	
	public function __construct() {
		
		if(!function_exists('mb_strlen') || !function_exists('mb_substr')) {
			$this->multibyte = false;
		}
		
		if(!$this->token) {
			$this->token = md5(uniqid('', true));
		}
	}
	
	/**
	 * Factory
	 * @param bool $new
	 */
	
	static public function factory($new=false) {
		
		if(!self::$instance || $new === true) {
			self::$instance = new rah_meta();
		}
		
		return self::$instance;
	}
	
	/**
	 * Output buffer handler
	 * @param string $page
	 */
	
	static public function output_buffer($page=NULL) {
		
		if(!self::factory()->registered) {
			self::factory()->registered = true;
			ob_start(array('rah_meta', 'output_buffer'));
			return;
		}
		
		return self::factory()->make_page($page);
	}
	
	/**
	 * Adds meta information to the page
	 * @param string $page
	 */
	
	public function make_page($page) {
		
		foreach($this->housing as $name => $atts) {
			
			$value = '';
			
			if(isset($this->pipeline[$name])) {
				
				$this->r = $atts;
				$this->r['value'] = $this->pipeline[$name];
				$this->r['name'] = $name;
				$this->format($name);
				$method = 'form_'.$this->r['return'];
				
				if(method_exists($this, $method)) {
					$value = $this->$method();
				}
			}
			
			$page = str_replace($this->token($name), $value, $page);
		}
		
		return $page;
	}
	
	/**
	 * Return a meta tag
	 */
	
	private function form_metatag() {
		return 
			'<meta name="'.htmlspecialchars($this->r['name']).'" value="'.$this->r['value'].'" />';
	}
	
	/**
	 * Return plain snippet without formatting
	 */
	
	private function form_plain() {
		return $this->r['value'];
	}
	
	/**
	 * Adds binding point to the page template
	 * @param string $name
	 * @param array $attributes
	 */
	
	public function housing($name, $attributes) {
		$this->housing[$name] = $attributes;
		return $this->token($name);
	}
	
	/**
	 * Returns a replacement-point token
	 * @param string $uid
	 * @return string
	 */
	
	public function token($uid) {
		return '<!-- <[rah_meta: '. $this->token . ' ' . htmlspecialchars($uid) . ']> -->';
	}
	
	/**
	 * Sets a value
	 * @param string $name
	 * @param string $value
	 */
	
	public function set($name, $value) {
		$this->pipeline[$name][] = $value;
	}
	
	/**
	 * Formats
	 */
	
	private function format($name) {
		$method = 'build_'.$name;
		
		if(method_exists($this, $method)) {
			$this->$method();
			return;
		}
		
		$this->r = array_merge($this->r, lAtts(array(
			'separator' => n,
			'value' => '',
			'return' => 'plain',
			'offset' => 0,
			'limit' => NULL,
		), $this->r, 0));
		
		$this->r['value'] = implode($this->r['separator'], array_slice($this->r['value'], $this->r['offset'], $this->r['limit']));
	}
	
	/**
	 * Builds keywords
	 */

	private function build_keywords() {
		
		$this->r = array_merge($this->r, lAtts(array(
			'separator' => ', ',
			'value' => array(),
			'offset' => 0,
			'limit' => 30,
			'return' => 'metatag',
		), $this->r, 0));
		
		$this->r['value'] = implode($this->r['separator'], array_slice(array_unique(do_list($this->strip(implode(',', $this->r['value'])))), $this->r['offset'], $this->r['limit']));
	}

	/**
	 * Builds description
	 */

	private function build_description() {
		
		$this->r = array_merge($this->r, lAtts(array(
			'chars' => 250,
			'words' => 25,
			'value' => array(),
			'trail' => '&#8230;',
			'return' => 'metatag',
		), $this->r, 0));
		
		foreach($this->r['value'] as $a) {
			if(trim($a) !== '') {
				$this->r['value'] = $this->strip($a);
				break;
			}
		}
		
		$words = $chars = 0;
		$func = $this->multibyte ? 'mb_strlen' : 'strlen';
		$out = array();
		
		foreach(do_list($this->r['value'], ' ') as $word) {
			
			if($word === '') {
				continue;
			}
			
			if($chars <= $this->r['chars'] && $words <= $this->r['words']) {
				$out[] = $word;
			}
			
			else {
				
				$this->r['value'] = implode(' ', $out);
			
				foreach(array('...', '!', '.', '?', ',', '&#8230;', $this->r['trail']) as $s) {
					if(($len = strlen($s)) && substr($this->r['value'], 0-$len, $len) == $s) {
						$this->r['value'] = substr($this->r['value'], 0, 0-$len);
					}
				}
				
				$this->r['value'] .= $this->r['trail'];
				return;
			}
			
			$chars = $func($word) + $chars + 1;
			$words++;
		}
		
		$this->r['value'] = implode(' ', $out);
	}

	/**
	 * Strips line changes, tabs and stuff
	 * @param string $out String to clean.
	 * @return string
	 */

	protected function strip($out) {
		return 
			trim(str_replace(
				array("\r\n", "\n", "\r", "\t", '"', '>', '<'),
				array(' ', ' ', ' ', ' ', '&quot;', '&gt;', '&lt;'),
				strip_tags($out)
			));
	}
}

?>