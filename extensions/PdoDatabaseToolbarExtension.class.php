<?php

class PdoDatabaseToolbarExtension
{
    static $count = 0;
    static $time = 0;

	static $sections_start_time = array();
	static $sections_start_count = array();

    public function startSection($section_id)
    {
    	self::$sections_start_time[$section_id] = self::$time;
    	self::$sections_start_count[$section_id] = self::$count;
    }
    
    public function finishSection($section_id)
    {
    	PhpDebugToolbar::incrementValue('database_count', self::$count - self::$sections_start_count[$section_id]);
    	PhpDebugToolbar::incrementValue('database_time', self::$time - self::$sections_start_time[$section_id]);
    }
}
