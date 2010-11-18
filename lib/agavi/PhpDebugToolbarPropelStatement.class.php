<?php

class PhpDebugToolbarPropelStatement extends DebugPDOStatement
{
    public function execute($input_parameters = null)
    {
        $start_time = microtime(true);
        $result = parent::execute($input_parameters);
        PropelDatabaseToolbarExtension::$time += (microtime(true) - $start_time);
        
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $this->getExecutedQueryString(),
            'stack' => $this->pdo->getBacktrace(),
            'group' => $this->queryString
        ));
        
        return $result;
    }
}
