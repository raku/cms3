<?php

namespace CMS3\Engine;

class App {

	public $document = NULL;

	public $modules = array();

	private $component_list = array();

	public $language;

	private $_languages = array();

	private $_config;
	
	protected static $_instance;

	public static function instance()
	{
		if (static::$_instance == NULL){
			static::$_instance = new App();
		}
		
		return static::$_instance;
	}
  
	public function initialize()
	{
		Core::$caching = TRUE; // TODO
		if (Core::$profiling === TRUE)
		{
			$benchmark = \Profiler::start(get_class($this), __FUNCTION__);
		}
		
		Core::$config->attach(new Config_File);
  
		$this->_config = Core::config('cms3\core');
  
		date_default_timezone_set($this->get_cfg('timezone'));
		
		Core::$base_url = $this->get_cfg('base_url');
		Core::$index_file = $this->get_cfg('index_file');
		
		$this->modules = $this->get_module_list();
						
		$connect_modules = array();
		foreach ($this->modules as $module)
		{
			$connect_modules[$module->name] = MODPATH . $module->name;
			if ($module->component)
			{
				$this->component_list[] = $module->name;
			}
		}
		Core::modules($connect_modules);
		
		Cache::$default = $this->get_cfg('default_caching_driver');
		
		$this->_languages = ORM::select('cms3\engine\language')
			->where('active', '=', 1)
			->execute();
			
		if (! count($this->_languages))
		{
			throw new Exception('No active languages');
		}
		$this->_set_default_routes();
		
		if (! \Security::token())
		{
			\Security::token(TRUE);
		}

		if (isset($benchmark))
		{
			\Profiler::stop($benchmark);
		}
	}
  
	private function _set_default_routes()
	{
		$action_defaults = array(
			'controller'		=> 'cms3\engine\app',
			'action'			=> 'call',
			'call_path'			=> '',
		);
		Route::set('action',
			$this->get_cfg('route_action'),
			array('call_path' => '([a-zA-Z0-9_/-])*', 'params' => '.*'))
			->defaults($action_defaults);
		
		$defaults = array(
			'controller'	=> 'cms3\engine\app',
			'action'		=> 'display',
			'path'			=> '',
			'language'		=> $this->get_cfg('default_language'),
			'format'		=> $this->get_cfg('default_output_format'),
			'params'		=> '',
		);
		
		$lang_codes = array_keys($this->_languages->as_array('short_code'));

		$lang_regexp = '(' . implode('|', $lang_codes) . ')';

		// TODO: разные поддомены для языков
		Route::set('default',
			$this->get_cfg('route_default'),
			array('path' => '([a-zA-Z0-9_/-])*', 'format' => '[a-zA-Z]*', 'language' => $lang_regexp, 'params' => '.*'))
			->defaults($defaults);
	}
	
	public function dispatch_action($controller, $action) // TODO
	{
		$params = $this->fetch_query_params();
		if ($action == 'finish_auth')
		{
			foreach ($_GET as $key => $value)
			{
				$parts = explode('.', $key);
				if (isset($parts[1]))
				{
					$_GET[$parts[0] . '_' . $parts[1]] = $value;
				}
			}
		}
		
		//TODO: 404 if not exists		
		$controller = new $controller(Request::instance());
		$controller->action($action, $params);
	}
	
	private function _replace_inline_route($uri)
	{
		$regex = array();
		
		// Find inline regex and remove it
		if (preg_match_all('/<(.+?):(.+?)>/', $uri, $matches, PREG_SET_ORDER))
		{
			$replace = array();

			foreach ($matches as $match)
			{
				list($search, $segment, $exp) = $match;

				// Add the regex for this segment
				$regex[$segment] = $exp;

				// Add the replacment for this segment
				$replace[$search] = '<'.$segment.'>';
			}

			// Remove all inline regex
			$uri = strtr($uri, $replace);
		}
	
		return array($uri, $regex);
	}
  
	public function dispatch($path, $language, $format)
	{
		if (Core::$profiling === TRUE)
		{
			$benchmark = \Profiler::start(get_class($this), __FUNCTION__);
		}
		//$get_params = Request::instance()->param('params');
				
		$get_params = $this->fetch_query_params();
		
		$lang_list = $this->_languages->as_array('short_code');

		if (! empty($language) && isset($lang_list[$language]))
		{
			$this->set_language($lang_list[$language]["code"]);
		}
		else
		{
			$this->set_language($this->get_cfg('default_language'));
		}
		
		Request::instance()->set_params(array());	
		$route_list = ORM::select('cms3\engine\route')->execute();
		foreach ($route_list as $route)
		{
			$parse = $this->_replace_inline_route($route->format);
			Route::set($route->id, $parse[0], $parse[1]);
		}
		
		$routes = Route::all();
		unset($routes['default']);
		unset($routes['action']);

		$found = FALSE;

		foreach ($routes as $name => $route)
		{
			if ($params = $route->matches($path))
			{
				unset($params['action']);
				$params = $this->explode_request_params($params);
				
				Request::instance()->set_params($params + $get_params);
				
				$found = TRUE;
				break;
			}
		}
		
		if (! $found && $path != '')
		{
			Request::instance()->status = 404;
			return;
		}
		
		$this->document = Document::factory($format);
		$this->document->language = $this->language;
		$this->document->charset = Core::$charset;
		$this->document->current_theme = $this->get_cfg('default_theme');
		
		$this->document->render();
		
		if (isset($benchmark))
		{
			\Profiler::stop($benchmark);
		}
		
		if (Request::instance()->param('profile')) // TODO
		{
			echo new \View('profiler/stats');
		}
	}
	
	public function fetch_query_params()
	{
		$params = array();
		$request = explode('?', $_SERVER['REQUEST_URI']);
		if (isset($request[1]))
		{
			$query_parts = explode('&', $request[1]);
			foreach ($query_parts as $part)
			{
				$part = explode('=', $part);
				$params[$part[0]] = @$part[1];
			}
			$_SERVER['QUERY_STRING'] = $request[1]; // TODO
		}
		$_GET = $params; // TODO!
		$_REQUEST = $params + $_POST; // TODO!
		return $params + $_POST;
	}

	public function set_language($language)
	{
		$language = strtolower($language);
		$this->language = $language;
		\I18n::$lang = $language;
		setlocale(LC_ALL, $language . '.utf-8');
	}

	public static function check_page_condition($condition)
	{
		$condition = trim($condition);
		if (! $condition)
		{
			return TRUE;
		}
		
		$params = Request::instance()->param();
		$expression = new Expression();
		
		return $expression->evaluate($condition, $params) != '';
	}

	public function explode_request_params($params)
	{
		return $this->modify_request_params($params, "explode");
	}

	public function implode_request_params($params)
	{
		return $this->modify_request_params($params, "implode");
	}

	protected function modify_request_params($params, $function)
	{
		/*
		$used_components = array();
		
		// Только те компоненты, переменные которых используются в выражениях
		foreach ($params as $key => $value)
		{
			$parts = explode("_", $key);
			if (in_array($parts[0], $this->component_list) && ! in_array($parts[0], $used_components))
			{
				$used_components[] = $parts[0];
			}
		}
		*/
		$used_components = $this->component_list;
		
		$function = $function . "_params";
		foreach ($used_components as $component)
		{
			$params = Component::instance($component)->$function($params);
		}
		return $params;
	}

	protected function get_module_list()
	{
		$modules = ORM::select('cms3\engine\module')
			->where('enabled', '=', 1)
			->execute();
		
		return $modules;
	}

	public function get_cfg($param)
	{
		$param = strtolower($param);
		if (isset($this->_config[$param]))
		{
			return $this->_config[$param];
		}
		else
		{
			return NULL;
		}
	}

	public function get_uri($route_id, $params, $format = NULL, $language = NULL)
	{
		$params = $this->implode_request_params($params);
		$path = Route::get($route_id)->uri($params);
		
		return $this->expand_uri($path, $format, $language);
	}

	public function expand_uri($path, $format = NULL, $language = NULL)
	{
		if ($language == NULL)
		{
			$lang_list = $this->_languages->as_array('code');
			$language = $lang_list[$this->language]['short_code'];
		}
		
		if (! $this->get_cfg('always_show_uri_language'))
		{
			$lang_list = $this->_languages->as_array('short_code');
			if (strtolower($lang_list[$language]['code']) == strtolower($this->get_cfg('default_language')))
			{
				$language = NULL;
			}
		}

		$uri = Route::get("default")->uri(array(
			"path" => $path,
			"language" => $language,
			"format" => $format,
		));
		
		return URL::site($uri);
	}

	// TODO
	public function redirect($url, $message = '', $type = 'info')
	{
		// TODO: парсить параметры url
		if ($message != '')
		{
			$url .= '?message=' . urlencode(__($message));
		}
		Request::instance()->redirect($url);
	}
}