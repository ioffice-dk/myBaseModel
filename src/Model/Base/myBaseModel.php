<?php
namespace Fm\Model\Base;

Class myBaseModel extends \Fm\Instance\Mysqli
{
    private $_Cronstructed = false;
    private $_Table = null;
    private $_Result = null;

    private static $_Id = null;
    private static $_Where = array();
    private static $_WhereOr = array();
    private static $_Fields = array();
    private static $_Sort = null;
    private static $_Sql = null;


    public function __construct()
    {
        echo __METHOD__;
    }


    public static function id($Id=null)
    {
        if ((int) $Id) self::$_Id = (int) $Id;

        $_this = new static();
        return $_this->next();
    }

    public static function where($Field, $Operator=null, $Value=null)
    {
        if (!empty($Operator) && empty($Value))
        {
            $Value = $Operator;
            $Operator = '=';
        }

        if (!empty($Field) &&  !empty($Value) && !empty($Operator) && in_array($Operator, array('=', '<>', '<=', '>=', 'LIKE')))
        {
            self::$_Where[] = (object) array("Field" => $Field, "Operator" => $Operator, "Value" => (string) $Value);
        }

        return new static();
    }



    public static function whereOr($Field, $Operator=null, $Value=null)
    {
        if (!empty($Operator) && empty($Value))
        {
            $Value = $Operator;
            $Operator = '=';
        }

        if (!empty($Field) &&  !empty($Value) && !empty($Operator) && in_array($Operator, array('=', '<>', '<=', '>=', 'LIKE')))
        {
            self::$_WhereOr[] = (object) array("Field" => $Field, "Operator" => $Operator, "Value" => (string) $Value);
        }

        return new static();
    }


    public static function sql($sql=null)
    {
        if (!empty($sql))
        {
            self::$_Sql = $sql;
            $_this = new static();
            return $_this->_query();
        }
    }


    public static function fields($field=null)
    {
        if (!empty($field) && is_string($field))
        {
            self::$_Fields[] = trim($field);
        }
        else if (is_array($field) && count($field) > 0)
        {
            self::$_Fields = array_merge(self::$_Fields, $field);
        }

        return new static();
    }

    public static function sort($Sort=null)
    {
        if (is_string($Sort) && !empty($Sort))
        {
            self::$_Sort = "ORDER BY " . $Sort;
        }
        return new static();
    }


    private function ModelConstruct()
    {
        parent::__construct();
        if ($this->DB)
        {
            if (isset($this->table) && !empty($this->table)) $this->_Table = $this->table;
            else if (isset($this->Table) && !empty($this->Table)) $this->_Table = $this->Table;
            else
            {
                if (!empty(get_class($this)) && strpos(get_class($this), '\\') > -1)
                {
                    $namespace = explode('\\', get_class($this));
                    if (is_array($namespace) && count($namespace) > 1)
                    {
                        if (end($namespace) == 'Model') array_pop($namespace);
                        $table = end($namespace);
                        if (!empty($table))
                        {
                            $table = preg_replace('/[A-Z]/', '_$0', $table);
                            $table = strtolower($table);
                            $table = ltrim($table, '_');
                            $table = trim($table);

                            if (!empty($table))
                            {
                                $this->_Table = ucfirst($table);
                            }
                        }
                    }
                }
            }

            if (empty($this->_Table)) throw new \Exception('no table name');
            else $this->_Cronstructed = true;
        }
    }


    private function _query()
    {
        if (!$this->_Cronstructed) $this->ModelConstruct();
        if ($this->DB && !empty($this->_Table) && (!empty(self::$_Where) || !empty(self::$_WhereOr) || !empty(self::$_Sql) || !empty(self::$_Fields) || !empty(self::$_Id)))
        {
            if (!empty(self::$_Sql))
            {
                $sql = self::$_Sql;
            }
            else
            {
                $fields = "*";
                if (!empty(self::$_Fields))
                {
                    $fields = implode(", ", self::$_Fields);
                }

                if (self::$_Id)
                {
                    $sql = "SELECT " . $fields . " FROM " . $this->_Table . " WHERE id=" . self::$_Id . ";";
                }
                else
                {

                    $sql = "SELECT " . $fields . " FROM " . $this->_Table ;

                    $where = array();
                    foreach(self::$_Where AS $_where)
                    {
                        if (strtoupper($_where->Value) == 'IS NULL')
                        {
                            $where[] = $_where->Field . " " . strtoupper($_where->Value);
                        }
                        else if (strtoupper($_where->Value) == 'IS NOT NULL')
                        {
                            $where[] = $_where->Field . " " . strtoupper($_where->Value);
                        }
                        else
                        {
                            $where[] = $_where->Field . " " . $_where->Operator . " '" . $_where->Value . "'";
                        }
                    }

                    $whereOr = array();
                    foreach(self::$_WhereOr AS $_whereOr)
                    {
                        if (strtoupper($_whereOr->Value) == 'IS NULL')
                        {
                            $whereOr[] = $_whereOr->Field . " " . strtoupper($_whereOr->Value);
                        }
                        else if (strtoupper($_whereOr->Value) == 'IS NOT NULL')
                        {
                            $whereOr[] = $_whereOr->Field . " " . strtoupper($_whereOr->Value);
                        }
                        else
                        {
                            $whereOr[] = $_whereOr->Field . " " . $_whereOr->Operator . " '" . $_whereOr->Value . "'";
                        }
                    }

                    if (count($where) > 0 || count($whereOr) > 0)
                    {
                        $sql .= " WHERE ";
                    }

                    if (count($where) > 0)
                    {
                        $sql .= implode(" AND ", $where);
                    }

                    if (count($whereOr) > 0)
                    {
                        if (count($where) > 0) $sql .= " AND ( " . implode(" OR ", $whereOr) . ")";
                        else $sql .= implode(" OR ", $whereOr);
                    }

                    if (self::$_Sort) $sql .= " " . self::$_Sort;

                    $sql .= ";";
                }
            }

            $this->_Result = $this->DB->query($sql);
            if ($this->DB->errno)
            {
                $this->_Resule = null;
                throw new \Exception($this->DB->error . $sql);
            }
            else if (!empty(self::$_Sql))
            {
                return mysqli_info($this->DB);
            }

            self::$_Id = null;
            self::$_Where = array();
            self::$_Fields = array();
            self::$_WhereOr = array();
            self::$_Sql = null;
        }
    }


    public function next()
    {
        $Result = false;
        if (empty($this->_Result)) $this->_query();
        if ($this->DB && !empty($this->_Result))
        {
            $Result = $this->_Result->fetch_object();
            if ($Result)
            {
                $Result = new \Fm\Model\Base\myBaseObject($Result, $this->_Table, $this->DB);
            }
        }

        return $Result;
    }


    public function count()
    {
        $Result = 0;
        if (empty($this->_Result)) $this->_query();
        if (!empty($this->_Result))
        {
            $Result = $this->_Result->num_rows;
        }

        return $Result;
    }


    public function getArray()
    {
        $result = array();
        if (empty($this->_Result)) $this->_query();
        if (!empty($this->_Result))
        {
            while($row = $this->_Result->fetch_object())
            {
                $result[] = $row;
            }
        }
        return $result;
    }


    public static function new()
    {
        $_this = new static();
        $_this->ModelConstruct();
        if ($_this->DB)
        {
            return new \Fm\Model\Base\MyBaseObject(null, $_this->_Table, $_this->DB);
        }

        return null;
    }




}
