<?php

namespace CMS3\Engine;

class Expression_Calc_PHP implements Expression_Interface {

	protected $_vars = array();

	public function evaluate($expression, $variables = array())
	{
		foreach ($variables as $var_name => $var_value)
		{
			$this->_vars[$var_name] = $var_value;
		}
        
		$expression = strtolower($expression);
		$expression = str_replace('=', '==', $expression);
		$expression = str_replace('<>', '!=', $expression);
		
		$expression = preg_replace('/([^\w])and([^\w])/', '$1&&$2', $expression);
		$expression = preg_replace('/([^\w])or([^\w])/', '$1||$2', $expression);
		$expression = preg_replace('/([^\w])not([^\w])/', '$1!$2', $expression);
        
		$regexp = '/([a-zA-Z_\/]+)/';
		$expression = preg_replace($regexp, '$this->_vars[\'$1\']', $expression);

        set_error_handler(function(){});
		$result = eval("return $expression;");
        restore_error_handler();
        
		return $result;
	}

	public function __get($name)
	{
		if (isset($this->_vars[$name]))
		{
			return $name;
		}
		else
		{
			return NULL;
		}
	}
}
