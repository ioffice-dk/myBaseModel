<?php
namespace Io\Model;

Class Example extends Io\Model\Base\myBaseModel
{
    protected $ConfigName = 'dev';  // name of the "mysql" section in io.json
    protected $Table = 'example';   // name of the table in db
}