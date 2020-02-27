<?php
namespace Fm\Instance;


/**
 * MySql instance class 
 * 
 * @author tholle@ioffice.dk
 * @since 2019-10-14
 * @package composer iofficedk/mybasemodel
 */
Class Mysqli extends \mysqli
{
	/**
	 * protected recurse var
	 * 
	 * @author henrik@ioffice.dk
	 * @since 2019-10-14
	 */
	protected $DB;
	
	
	/**
	 * The Mysql instace class constructor
	 * pick up configuration from .fm.json mysql section with Fm\Tool\Config::get('mysql')
	 * 
	 * @author henrik@ioffice.dk
	 * @since 2019-10-14
	*/
	public function __construct()
	{
		$ConfigName = 'default';

		if (property_exists($this, 'config') && !empty($this->config) && is_string($this->config))
		{
			$ConfigName = $this->config;	
		}

		$_config = \Fm\helpers\Config::get('mysql');
		
		if (is_object($_config))
		{
			if (property_exists($_config, $ConfigName))
			{
				$Config = $_config->{$ConfigName};
				if (
					property_exists($Config, "host") && !empty($Config->host) &&
					property_exists($Config, "user") && !empty($Config->user) && 
					property_exists($Config, "pass") && !empty($Config->pass) && 
					property_exists($Config, "base") && !empty($Config->base) && 
					property_exists($Config, "port") && !empty($Config->port) && (int) $Config->port
				){
					$mysqli = new \mysqli($Config->host, $Config->user, $Config->pass, $Config->base, $Config->port);
					if (!$mysqli->connect_errno)
					{
						$this->DB = &$mysqli;
						
					} 
					else throw new \Exception($mysqli->connect_error);
				}
				else throw new \Exception("error config not complete");
			}
			else throw new \Exception("error no config entrance key");
		}
		else throw new \Exception("error mysql config");
		
	}
}
?>