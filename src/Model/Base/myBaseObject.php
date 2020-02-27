<?php
namespace Fm\Model\Base;

Class myBaseObject
{
	protected $_Table = null;
	protected $_DB = null;
	protected $_Obj = null;
	
	protected $Object = null;
	private $ID = null;
	
	public function __construct($Obj = null, $Table = null, $DB = null) 
	{
		if (is_object($Obj)) $this->_Obj = $Obj;
		if (is_string($Table) && !empty($Table)) $this->_Table = $Table;
		if ($DB instanceof \Mysqli && $DB->ping()) $this->_DB = $DB;
		
		if ($this->_Obj)
		{
			$this->Object = new \stdClass();
			foreach($this->_Obj AS $field => $value)
			{
				if (strtolower($field) != 'id' && strtolower($field) != 'updated_at' && strtolower($field) != 'deleted_at')
				{
					$this->Object->{$field} = $value;
				}
				else if (strtolower($field) == 'id' && (int) $value)
				{
					$this->ID = (int) $value;
				}
			}
		}
		else if (!empty($this->_Table) && $this->_DB)
		{
			$query =  $this->_DB->query("SHOW COLUMNS FROM " . $this->_Table . ";");
			if ($this->_DB->errno) throw new \Exception( $this->_DB->error );
			else if ($query->num_rows > 0)
			{
				$this->_Obj = new \stdClass();
				$this->Object = new \stdClass();
				while ($row = $query->fetch_object())
				{
					$field = $row->Field;
					$this->_Obj->{$field} = null;
					if (strtolower($field) != 'id' && strtolower($field) != 'updated_at' && strtolower($field) != 'deleted_at')
					{
						$this->Object->{$field} = null;
					}
				}
			}
		}
	}

	
	public function __set($field=null, $value=null)
	{
		if ($this->Object && property_exists($this->Object, $field))
		{
			$this->{$field} = mysqli_real_escape_string($this->_DB, $value);
			$this->Object->{$field} = mysqli_real_escape_string($this->_DB, $value);
		}
	}
	
	public function __get($field=null)
	{
		if ($this->Object && property_exists($this->Object, $field))
		{
			return $this->Object->{$field};
		}
	}
	
	
	public function isReady()
	{
		return (($this->_Table && $this->_Obj && $this->Object && $this->_DB) ? true:false);
	}

	
	public function getId()
	{
		return $this->ID;
	}

	
	public function getObject()
	{
		if ($this->Object)
		{
			return $this->Object;
			return array_keys((array) $this->Object );
		}
		return null;
	}
	
	
	public function delete()
	{
		$result = false;
		if ($this->isReady() && (int) $this->_Id)
		{
			if (property_exists($this->Object, 'deleted_at'))
			{
				$this->Object->deleted_at = strftime("%Y-%m-%d %H:%M:%S", time());
				$Status = $this->save();
				$result = $Status->success;
			}
			else
			{
				$Query = $this->_DB->query("DELETE FROM " . $this->_Table . " WHERE id=" . $this->_Id . ";");
				$result = ($this->_DB->errno ? false:true);
			}
		}
		return $result;
	}
	
	
	public function save()
	{
		$status = new \stdClass();
		$status->success = true;
		$status->updated = null;
		$status->nochange = null;
		$status->created = null;
		$status->matched = null;
		$status->warning = null;
		$status->error = null;
		
		if ($this->_DB && !empty($this->_Table) && $this->_Obj && $this->Object)
		{
			// Create new record
			if (empty($this->ID) && !empty($this->Object))
			{
				$create = new \stdClass();
				
				foreach($this->Object AS $field => $value)
				{
					if (property_exists($this->_Obj, $field))
					{
						$create->{$field} = $value;
					}
				}
				
				if (count((array) $create) > 0)
				{
					$sql = "INSERT INTO " . $this->_Table . " ";
					
					if (property_exists($this->_Obj, 'updated_at')) // && (!property_exists($this->Object, 'updated_at') || (property_exists($this->Object, 'updated_at') && empty($this->Object->updated_at))))
					{
						$this->Object->updated_at = strftime("%Y-%m-%d %H:%M:%S", time());
						$create->updated_at = $this->Object->updated_at;
					}
					
					$sql .= "(" . implode(", ", array_keys((array) $create)) . ") VALUES ";
					$sql .= "('" . implode("', '", array_values((array) $create)) . "')";
					$sql .= ";";
					
					$this->_DB->query($sql);
					if ($this->_DB->errno)
					{
						$status->success = false;
						$status->error = $this->_DB->error;
					}
					else
					{
						$status->created = $this->_DB->insert_id;
						list($matched, $changed, $warnings) = sscanf($this->_DB->info, "Rows matched: %d Changed: %d Warnings: %d");
						$status->updated = $changed;
						$status->matched = $matched;
						$status->warnings = $warnings;
					}
				}
			}
			else // Updating record
			{
				$sql = "UPDATE " . $this->_Table . " SET ";
				$update = new \stdClass();
				foreach($this->Object AS $field => $value)
				{
					if (property_exists($this->_Obj, $field) && $this->_Obj->{$field} != $value)
					{
						$update->{$field} = $field . "= '" . $value . "'";
					}
				}
				
				if (count((array) $update) > 0)
				{
					$sql .= implode(", ", (array) $update );
					
					if (property_exists($this->_Obj, 'updated_at'))
					{
						$sql .= ", updated_at='" . strftime("%Y-%m-%d %H:%M:%S", time()) . "' ";
					}
					
					$sql .= " WHERE id=" . $this->ID . ";";

					$this->_DB->query($sql);
					if ($this->_DB->errno)
					{
						$status->success = false;
						$status->error = $this->_DB->error;	
					}
					else 
					{
						list($matched, $changed, $warnings) = sscanf($this->_DB->info, "Rows matched: %d Changed: %d Warnings: %d");
						$status->updated = $changed;
						$status->matched = $matched;
						$status->warnings = $warnings;
					}
				} 
				else
				{
					$status->nochange = true;
				}
			}
		}
		else 
		{
			$status->success = false;
			if (!$this->DB) $status->error = "no db";
			else if (!$this->_Table) $status->error = "no table";
			else if (!$this->_Obj) $status->error = "no internal object";
			else $status->error = "no object";
		}
		
		return $status;
	}
	
}