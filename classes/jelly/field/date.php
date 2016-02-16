<?php

defined('SYSPATH') or die('No direct script access.');

class Jelly_Field_Date extends Jelly_Core_Field_Timestamp
{

    /**
     * @var  string  a date formula representing the time in the database
     */
    public $format = 'Y-m-d';
}
