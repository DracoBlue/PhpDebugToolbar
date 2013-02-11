<?php

if (!extension_loaded('apd'))
{
    throw new Exception('Extension apd not loaded! ApdMysqlDatabaseToolbarExtension will not work without apd installed!');
}

if (!function_exists('before_phpdebugtoolbar_mysql_query'))
{
    rename_function('mysql_query', 'before_phpdebugtoolbar_mysql_query');
}    

function mysql_query($query, $resource = null)
{
    ApdMysqlDatabaseToolbarExtension::$count++;

    $result = null;

    $start_memory = memory_get_usage();
    $start_time = microtime(true);


    if ($resource === null)
    {
        $result = before_phpdebugtoolbar_mysql_query($query);
    }
    else
    {
        $result = before_phpdebugtoolbar_mysql_query($query, $resource);
    }

    $time = (microtime(true) - $start_time);
    $memory = memory_get_usage() - $start_memory;

    ApdMysqlDatabaseToolbarExtension::$time += $time;

    if ($result)
    {
        $backtrace = array_slice(debug_backtrace(false), 5, 6);
        foreach ($backtrace as $k => $v)
        {
            unset($backtrace[$k]['args']);
            unset($backtrace[$k]['object']);
        }

        $num_rows = mysql_num_rows($result);
        
        if ($num_rows === false)
        {
            $num_rows = mysql_affected_rows($result);    
        }

        /*
         * Ok we have a valid result!
         */
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $query,
            'time' => $time,
            'memory' => $memory,
            'rows' => $num_rows,
            'stack' => $backtrace,
            'group' => $query
        ));
    }

    return $result;
};


class ApdMysqlDatabaseToolbarExtension
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
