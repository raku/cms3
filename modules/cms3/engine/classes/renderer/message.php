<?php

namespace CMS3\Engine;

use CMS3\Template\Template;
 
class Renderer_Message extends Renderer {

	public function render($name, array $params = array())
	{
		if ($message = @$_GET['message']) // TODO: получать из Request->param
		{
			$type = isset($params['type']) ? $params['type'] : 'info';
			$data = Template::render('message', array('message' => $message, 'type' => $type));
		}
		else
		{
			$data = '';
		}
		
		return $data;
	}
	
}
