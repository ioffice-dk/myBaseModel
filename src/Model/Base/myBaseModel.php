<?php
namespace Io\Model\Base;

use http\Exception\RuntimeException;
use Io;


/**
 * Class myBaseModel
 *
 * Your model that extends this must contain
 *
 * protected $TableName = 'your-table-name'
 *
 * @package Io\Model\Base
 */
Class myBaseModel extends Io\Instance\MySqli
{
    private static $Operators = array('=', '<>', '<=', '>=', 'LIKE');
    private static $Id = null;
    private static $Uuid = null;
    private static $Sql = null;
    private static $Condition = array();
    private static $Fields = array();
    private static $Sort = null;

    private $ModelConstructed = null;
    private $Table = null;
    private $Result = null;


    public static function id($Id=null)
    {
        if (!empty($Id) && (int) $Id)
        {
            self::$Id = (int) $Id;
        }
    }


    public static function uuid($Uuid=null)
    {
        if (!empty($Uuid) && $Uuid) // TODO Check if uuid
        {
            self::$Uuid = $Uuid;
        }
    }


    public static function sql($Sql=null)
    {
        if (!empty($Sql))
        {
            self::$Sql = $Sql;
        }
    }


    /**
     * Set your where AND Condition
     * If you compare you can omit the Operator
     *
     * @param string $Field - name of field
     * @param string $Operator - Operator OR Value
     * @param mixed $Value - the value you are looking for ..
     * @param string $Parenthesis - single or multi start or stop parenthesis
     * @return static
     */
    public static function where($Field=null, $Operator=null, $Value=null, $Parenthesis=null)
    {
        self::$Condition[] = self::whereParser('AND', $Field, $Operator, $Value, $Parenthesis);

        return new static();
    }


    /**
     * Set your where OR Condition
     * If you compare you can omit the Operator
     *
     * @param string $Field - name of field
     * @param string $Operator - Operator OR Value
     * @param mixed $Value - the value you are looking for ..
     * @param string $Parenthesis - single or multi start or stop parenthesis
     * @return static
     */
    public static function whereOr($Field=null, $Operator=null, $Value=null, $Parenthesis=null)
    {
        self::$Condition[] = self::whereParser('Or', $Field, $Operator, $Value, $Parenthesis);

        return new static();
    }


    /**
     * @param mixed $Field - name of field or array of fields
     * @return static
     */
    public static function field($Field=null)
    {
        if (!empty($Field) && is_string($Field))
        {
            self::$Fields[] = trim($Field);
        }
        else if (is_array($Field) && count($Field) > 0)
        {
            self::$Fields = array_merge(self::$Fields, $Field);
        }

        return new static();
    }


    /**
     * @param string $Sort - Sorting str - `name DESC, status ASC´
     * @return static
     */
    public static function sort($Sort=null)
    {
        if (is_string($Sort) && !empty($Sort))
        {
            self::$Sort = "ORDER BY " . $Sort;
        }
        return new static();
    }


    /**
     * Prepair new record
     *
     * @return myBaseObject
     */
    public static function new()
    {
        $_this = new static();
        $_this->ConstructModel();
        if ($_this->DB)
        {
            return new Io\Model\Base\myBaseObject(null, $_this->Table, $_this->DB);
        }

        return null;
    }


    private static function whereParser($Separator=null, $Field=null, $Operator=null, $Value=null, $Parenthesis=null)
    {
        $ConditionCount = count(self::$Condition);

        if (!empty($Separator)) $Separator = trim($Separator) . ' ';
        if ($ConditionCount == 0) $Separator = ' WHERE ';

        if (!empty($Field)) {
            if (!empty($Operator) && !in_array($Operator, self::$Operators)) {
                $Parenthesis = $Value;
                $Value = $Operator;
                $Operator = '=';
            }
        }

        if (strtoupper(trim($Operator)) == 'LIKE') $Operator = ' LIKE ';

        if (trim(strtoupper($Value)) == 'IS NOT NULL')
        {
            $Operator = null;
            $Value = ' IS NOT NULL';
        }
        else if (trim(strtoupper($Value)) == 'IS NULL')
        {
            $Operator = null;
            $Value = ' IS NULL';
        }
        else if (is_string($Value)) $Value = "'" . $Value . "'";

        $ParenthesisStart = null;
        $ParenthesisEnd = null;
        if (!empty($Parenthesis))
        {
            if (preg_match('/[\(]+$/', $Parenthesis)) $ParenthesisStart = $Parenthesis;
            else if (preg_match('/[\)]+$/', $Parenthesis)) $ParenthesisEnd = $Parenthesis;
        }

        return $Separator . $ParenthesisStart . $Field . $Operator . $Value . $ParenthesisEnd;
    }


    private function initTable()
    {
        $Result = false;

        if (isset($this->TableName) && !empty($this->TableName))
        {
            $this->Table = $this->TableName;
            $Result = true;
        }
        if (isset($this->Table) && !empty($this->Table))
        {
            $Result = true;
        }
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
                            $this->Table = $table;
                            $Result = true;
                        }
                    }
                }
            }
        }

        return $Result;
    }


    private function renderSql()
    {
        $sql = null;

        if (!empty(self::$Sql))
        {
            $sql = self::$Sql;
        }
        else if ($this->initTable())
        {
            $Fields = '*';
            if (!empty(self::$Fields))
            {
                $Fields = implode(", ", self::$Fields);
            }

            if ((int) self::$Id)
            {
                $sql = "SELECT " . $Fields . " FROM " . $this->Table . " WHERE id=" . self::$Id . ";";
            }
            else if (self::$Uuid)
            {
                $sql = "SELECT " . $Fields . " FROM " . $this->Table . " WHERE uuid=" . self::$Uuid . ";";
            }
            else
            {
                $sql = "SELECT " . $Fields . " FROM " . $this->Table ;

                if (count(self::$Condition) > 0)
                {
                    $sql .= implode(" ", self::$Condition);
                }

                if (self::$Sort) $sql .= " " . self::$Sort;

                $sql .= ';';
            }
        }

        return $sql;
    }


    private function ConstructModel()
    {
        $Result = null;
        if (!$this->ModelConstructed)
        {
            parent::__construct();
            if ($this->DB && $this->initTable())
            {
                $this->ModelConstructed = true;
                $Result = true;
            }
            else throw new RuntimeException('db error 101');
        }

        return $Result;
    }


    private function Querying()
    {
        $Result = false;
        $Sql = null;

        if (!$this->ModelConstructed) $this->ConstructModel();
        if ($this->DB && ($Sql = $this->getSql()))
        {
            $this->Result = $this->DB->query($Sql);
            if ($this->DB->errno)
            {
                $this->Result = null;
                throw new \RuntimeException($this->DB->error . $Sql);
            }
            else
            {
                $Result = mysqli_info($this->DB);
                self::$Id = null;
                self::$Uuid = null;
                self::$Sql = null;
                self::$Condition = array();
                self::$Fields = array();
                self::$Sort = null;
            }
        }
        else if (!$Sql)
        {
            throw new RuntimeException('error sql 102');
        }
        else if (!$this->DB)
        {
            throw new RuntimeException('error DB 103');
        }

        return $Result;
    }


    /**
     * Render and return the sql string
     *
     * @return string
     */
    public function getSql()
    {
        return self::renderSql();
    }


    /**
     * Get query num rows
     *
     * @return mixed - null if empty result or integer if result
     */
    public function count()
    {
        $Count = null;

        if (!$this->ModelConstructed) $this->ConstructModel();
        if ($this->DB && empty($this->Result)) $this->Querying();

        if (!empty($this->Result))
        {
            $Count = $this->Result->num_rows;
        }

        return $Count;
    }


    /**
     * Get next row in query result
     *
     * @return mixed - myBaseObject | null
     */
    public function next()
    {
        $Result = null;

        if (!$this->ModelConstructed) $this->ConstructModel();
        if ($this->DB && empty($this->Result)) $this->Querying();

        if (!empty($this->Result))
        {
            $Row = $this->Result->fetch_object();
            if ($Row)
            {
                $Result = new Io\Model\Base\myBaseObject($Row, $this->Table, $this->DB);
            }
        }

        return $Result;
    }


    public function getArray()
    {
        $Result = array();
        if (!$this->ModelConstructed) $this->ConstructModel();
        if ($this->DB && empty($this->Result)) $this->Querying();

        if (!empty($this->Result))
        {
            while($row = $this->Result->fetch_object())
            {
                $Result[] = $row;
            }
        }
        return $Result;
    }


}
