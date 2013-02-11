<?php

class PhpDebugToolbarPdoStatement extends PDOStatement
{
	protected $pdo;
	
    protected function __construct($pdo = null)
    {
        $this->pdo = $pdo;
    }
	
    public function execute($input_parameters = null)
    {
        $start_memory = memory_get_usage();
        $start_time = microtime(true);
        $result = parent::execute($input_parameters);
        $time = (microtime(true) - $start_time);
        $memory = memory_get_usage() - $start_memory;
        PdoDatabaseToolbarExtension::$time += $time;
        PdoDatabaseToolbarExtension::$count += 1;
		
        $backtrace = array_slice(debug_backtrace(false), 1, 6);
        
        foreach ($backtrace as $k => $v)
        {
            unset($backtrace[$k]['args']);
            unset($backtrace[$k]['object']);
        }
		
		$sql = $this->queryString;
		
		/**
		 * FIXME: sql should be the query string populated with all values properly
		 * some edge cases are still missing
		 */
		 
		if ($input_parameters)
		{
			foreach ($input_parameters as $key => $value)
			{
				if ($this->pdo)
				{
					$value = $this->pdo->quote($value);
				}
				if (is_numeric($key))
				{
					$sql = preg_replace('/\?/', $value, $sql, 1);
				}
				else
				{
					$sql = str_replace($key, $value, $sql);
				}
			}
		}
		
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $sql,
            'time' => $time,
            'memory' => $memory,
            'rows' => $this->rowCount(),
            'stack' => $backtrace,
            'group' => $this->queryString
        ));
        
        return $result;
    }
}
