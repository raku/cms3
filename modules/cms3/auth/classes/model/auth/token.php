<?php

namespace CMS3\Auth;

use CMS3\Engine;
use CMS3\Engine\Model;
use CMS3\Engine\ORM_Meta;

class Model_Auth_Token extends Model {
	
	public static function initialize(ORM_Meta $meta)
	{
		$meta
			->table('auth_tokens')
			->fields(array(
				'id' 		=> new Engine\Field_Primary,
				'user' 		=> new Engine\Field_BelongsTo(array(
					'foreign'	=> 'user.id',
					'column'	=> 'user_id', 
				)),
				//'user_id'	=> new Engine\Field_Integer,
				'token'		=> new Engine\Field_String(array(
					// TODO: uncomment this
					//'unique'	=> TRUE,
					'rules'		=> array(
						'max_length' => array(32),
						'min_length' => array(32)
					)
				)),
				'created' => new Engine\Field_Timestamp(array(
					//'auto_now_create' => TRUE,
				)),
				'expires' => new Engine\Field_Timestamp,
			));
			
			/*
			if (mt_rand(1, 100) === 1)
			{
				ORM::delete('auth_token')->where('expires', '<', time())->execute();
			}
			*/
   }
   
	public function create($user_id, $expires = 54000)
	{		
		$this->token = $this->generate_token();
		$this->user = ORM::select('user', $user_id); // TODO
		// TODO: set expires
		
		return parent::save();
	}
	
	public function update()
	{
		$this->token = $this->generate_token();
		
		return parent::save();
	}
	
	protected function generate_token()
	{
		return \Text::random('alnum', 32);
	}
}