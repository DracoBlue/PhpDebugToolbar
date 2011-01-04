<?php

class PhpDebugToolbarPropelStatement extends DebugPDOStatement
{
    public function execute($input_parameters = null)
    {
        $start_memory = memory_get_usage();
        $start_time = microtime(true);
        $result = parent::execute($input_parameters);
        $time = (microtime(true) - $start_time);
        $memory = memory_get_usage() - $start_memory;
        PropelDatabaseToolbarExtension::$time += $time;
        
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $this->getExecutedQueryString(),
            'time' => $time,
            'memory' => $memory,
            'rows' => $this->rowCount(),
            'stack' => $this->pdo->getBacktrace(),
            'group' => $this->queryString
        ));
        
        return $result;
    }
}
