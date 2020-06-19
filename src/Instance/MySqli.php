<?php
namespace Io\Instance;

use Io;


Class MySqli extends \Mysqli
{
    protected $DB = null;

    public function __construct($ConfigName=null)
    {
        $ConfigName = 'default';

        if (property_exists($this, 'ConfigName') && !empty($this->ConfigName) && is_string($this->ConfigName))
        {
            $ConfigName = trim($this->ConfigName);
        }

        $Config = Io\Helper\Config::get('mysql', $ConfigName);
        if (!empty($Config) && is_object($Config)) {
            if (
                property_exists($Config, "host") && !empty($Config->host)
                && property_exists($Config, "port") && !empty($Config->port) && (int) $Config->port
                && property_exists($Config, "user") && !empty($Config->user)
                && property_exists($Config, "pass") && !empty($Config->pass)
                && property_exists($Config, "base") && !empty($Config->base)
            ){
                $mysqli = new \mysqli($Config->host, $Config->user, $Config->pass, $Config->base, $Config->port);
                if (!$mysqli->connect_errno) {
                    $this->DB = &$mysqli;
                }
                else throw new \RuntimeException('error mysql ' . $mysqli->connect_error);
            }
            else throw new \RuntimeException('error mysql config not complete');
        }
        else throw new \RuntimeException('error mysql config section', 1);
    }

}
