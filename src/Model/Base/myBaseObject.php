<?php
namespace Io\Model\Base;

use function MongoDB\BSON\toRelaxedExtendedJSON;

Class myBaseObject
{
    protected $Table = null;
    protected $DB = null;
    protected $Origin = null;

    protected $Object = null;
    private $Id = null;


    public function __construct($Origin = null, $Table = null, $DB = null)
    {
        if (is_object($Origin)) $this->Origin = $Origin;
        if (is_string($Table) && !empty($Table)) $this->Table = $Table;
        if ($DB instanceof \Mysqli && $DB->ping()) $this->DB = $DB;

        if ($this->Origin)
        {
            $this->Object = new \stdClass();
            foreach($this->Origin AS $field => $value)
            {
                if (strtolower($field) != 'id' && strtolower($field) != 'updated_at' && strtolower($field) != 'deleted_at')
                {
                    $this->Object->{$field} = $value;
                }
                else if (strtolower($field) == 'id' && (int) $value)
                {
                    $this->Id = (int) $value;
                }
            }
        }
        else if (!empty($this->Table) && $this->DB)
        {
            $query =  $this->DB->query("SHOW COLUMNS FROM " . $this->Table . ";");
            if ($this->DB->errno)
            {
                throw new \RuntimeException( $this->DB->error );
            }
            else if ($query->num_rows > 0)
            {
                $this->Origin = new \stdClass();
                $this->Object = new \stdClass();
                while ($row = $query->fetch_object())
                {
                    $field = $row->Field;
                    $this->Origin->{$field} = null;
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
        if ($this->Origin && property_exists($this->Origin, $field) && $this->Object && property_exists($this->Object, $field))
        {
            if (is_null($value))
            {
                $this->{$field} = null;
                $this->Object->{$field} = null;
            }
            else if (is_string($value))
            {
                $this->{$field} = mysqli_real_escape_string($this->DB, $value);
                $this->Object->{$field} = mysqli_real_escape_string($this->DB, $value);
            }
            else
            {
                $this->{$field} = $value;
                $this->Object->{$field} = $value;
            }
        }
    }


    public function __get($field=null)
    {
        if ($this->Object && property_exists($this->Object, $field))
        {
            return $this->Object->{$field};
        }

        return null;
    }


    public function isReady()
    {
        return $this->Table && $this->Object && $this->Object && $this->DB;
    }


    public function getId()
    {
        return $this->Id;
    }


    public function getObject()
    {
        if ($this->Object)
        {
            return $this->Object;
        }

        return null;
    }


    public function delete()
    {
        $Result = false;

        if ($this->isReady() && (int) $this->Id)
        {
            if (property_exists($this->Object, 'deleted_at'))
            {
                $this->Object->deleted_at = strftime("%Y-%m-%d %H:%M:%S", time());
                $Status = $this->save();
                $Result = $Status->success;
            }
            else
            {
                $Query = $this->DB->query("DELETE FROM " . $this->Table . " WHERE id=" . $this->Id . ";");
                $Result = ($this->DB->errno ? false:true);
            }
        }

        return $Result;
    }


    public function save()
    {
        $Status = new \stdClass();
        $Status->success = true;
        $Status->updated = null;
        $Status->nochange = null;
        $Status->created = null;
        $Status->matched = null;
        $Status->warning = null;
        $Status->error = null;

        if ($this->DB && !empty($this->Table) && $this->Origin && $this->Object)
        {
            // Create new record
            if (empty($this->Id) && !empty($this->Object))
            {
                $create = new \stdClass();

                foreach($this->Object AS $field => $value)
                {
                    if (property_exists($this->Origin, $field))
                    {
                        if (is_null($value) || $value === NULL || (is_string($value) && strtoupper(trim($value) == 'NULL')))
                        {
                            $create->{$field} = 'NULL';
                        }
                        else if (is_string($value))
                        {
                            $create->{$field} = "'" . $value . "'";
                        }
                        else
                        {
                            $create->{$field} = $value;
                        }
                    }
                }

                if (count((array) $create) > 0)
                {
                    $sql = "INSERT INTO " . $this->Table . " ";

                    if (property_exists($this->Origin, 'updated_at'))
                    {
                        $this->Object->updated_at = strftime("%Y-%m-%d %H:%M:%S", time());
                        $create->updated_at = $this->Object->updated_at;
                    }

                    $sql .= "(" . implode(", ", array_keys((array) $create)) . ") VALUES ";
                    $sql .= "(" . implode(", ", array_values((array) $create)) . ")";
                    $sql .= ";";

                    $this->DB->query($sql);
                    if ($this->DB->errno)
                    {
                        $Status->success = false;
                        $Status->error = $this->DB->error;
                    }
                    else
                    {
                        $Status->created = $this->DB->insert_id;
                        list($matched, $changed, $warnings) = sscanf($this->DB->info, "Rows matched: %d Changed: %d Warnings: %d");
                        $Status->updated = $changed;
                        $Status->matched = $matched;
                        $Status->warnings = $warnings;
                    }
                }
            }
            else if ($this->Id) // Updating record
            {
                $sql = "UPDATE " . $this->Table . " SET ";
                $update = new \stdClass();
                foreach($this->Object AS $field => $value)
                {
                    if (property_exists($this->Origin, $field) && $this->Origin->{$field} != $value)
                    {
                        if (is_null($value) || $value === NULL || (is_string($value) && strtoupper(trim($value) == 'NULL')))
                        {
                            $update->{$field} = $field . "=NULL";
                        }
                        else if (is_string($value))
                        {
                            $update->{$field} = $field . "='" . $value . "'";
                        }
                        else
                        {
                            $update->{$field} = $field . "=" . $value;
                        }
                    }
                }

                if (count((array) $update) > 0)
                {
                    $sql .= implode(", ", (array) $update );

                    if (property_exists($this->Origin, 'updated_at'))
                    {
                        $sql .= ", updated_at='" . strftime("%Y-%m-%d %H:%M:%S", time()) . "' ";
                    }

                    $sql .= " WHERE id=" . $this->Id . ";";

                    $this->DB->query($sql);
                    if ($this->DB->errno)
                    {
                        $Status->success = false;
                        $Status->error = $this->DB->error;
                    }
                    else
                    {
                        list($matched, $changed, $warnings) = sscanf($this->DB->info, "Rows matched: %d Changed: %d Warnings: %d");
                        $Status->updated = $changed;
                        $Status->matched = $matched;
                        $Status->warnings = $warnings;
                    }
                }
                else
                {
                    $Status->nochange = true;
                }
            }

        }
        else
        {
            $Status->success = false;
            if (!$this->DB)
            {
                $Status->error = "no db";
            }
            else if (!$this->Table)
            {
                $Status->error = "no table";
            }
            else if (!$this->Object)
            {
                $Status->error = "no internal object";
            }
            else
            {
                $Status->error = "no object";
            }
        }

        return $Status;
    }

}
