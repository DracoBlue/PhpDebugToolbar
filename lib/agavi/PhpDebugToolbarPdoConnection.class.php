<?php

require_once(dirname(__FILE__) . '/PhpDebugToolbarPdoStatement.class.php');

class PhpDebugToolbarPdoConnection extends PDO
{
	function __construct($dsn, $username = null, $password = null, array $driver_options = array() )
	{
	    parent::__construct($dsn, $username, $password, $driver_options);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('PhpDebugToolbarPdoStatement', array()));
	}

    public function exec($sql)
    {
        $start_memory = memory_get_usage();
        $start_time = microtime(true);
        $result = parent::exec($sql);
        $time = (microtime(true) - $start_time);
        $memory = memory_get_usage() - $start_memory;
        PdoDatabaseToolbarExtension::$time += $time;
        PdoDatabaseToolbarExtension::$count += 1;
        
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $sql,
            'time' => $time,
            'rows' => 0,
            'affected_rows' => $result,
            'memory' => $memory,
            'stack' => $this->getBacktrace(),
            'group' => $sql
        ));
        
        return $result;
    }
    
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        
        $start_memory = memory_get_usage();
        $start_time = microtime(true);
        $result = parent::query($sql);
        $time = (microtime(true) - $start_time);
        $memory = memory_get_usage() - $start_memory;
        PdoDatabaseToolbarExtension::$time += $time;
        PdoDatabaseToolbarExtension::$count += 1;

        $row_count = 0;
        if (is_callable(array($result, 'rowCount')))
        {
            $row_count = $result->rowCount();
        }
        
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $sql,
            'time' => $time,
            'rows' => $row_count,
            'memory' => $memory,
            'stack' => $this->getBacktrace(),
            'group' => $sql
        ));
        
        return $result;
    }
    
    public function getBacktrace()
    {
        $backtrace = array_slice(debug_backtrace(false), 1, 6);
        
        foreach ($backtrace as $k => $v)
        {
            unset($backtrace[$k]['args']);
            unset($backtrace[$k]['object']);
        }
        
        return $backtrace;
    }
}
