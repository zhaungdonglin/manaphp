<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 2015/12/28
 * Time: 0:05
 */
namespace Models;

use ManaPHP\Mvc\Model;

class Student extends Model
{
    public $id;
    public $age;
    public $name;

    public static function getSource($context = null)
    {
        return '_student';
    }
}