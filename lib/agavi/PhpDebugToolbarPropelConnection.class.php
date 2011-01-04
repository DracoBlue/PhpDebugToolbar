<?php

require_once(dirname(__FILE__) . '/PhpDebugToolbarPropelStatement.class.php');

class PhpDebugToolbarPropelConnection extends DebugPDO
{
    protected $statementClass = 'PhpDebugToolbarPropelStatement';

    public function exec($sql)
    {
        $start_time = microtime(true);
        $result = parent::exec($sql);
        $this->last_exec_start_time = microtime(true);
        
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $sql,
            'stack' => $this->getBacktrace(),
            'group' => $sql
        ));
        
        return $result;
    }
    
    public function query()
    {
        $args = func_get_args();
        $sql = $args[0];
        
        $start_time = microtime(true);
        $result = parent::query($sql);
        PropelDatabaseToolbarExtension::$time += (microtime(true) - $start_time);
        
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $sql,
            'stack' => $this->getBacktrace(),
            'group' => $sql
        ));
        
        return $result;
    }
    
    public function getBacktrace()
    {
        $backtrace = array_slice(debug_backtrace(false), 4, 6);
        
        foreach ($backtrace as $k => $v)
        {
            unset($backtrace[$k]['args']);
            unset($backtrace[$k]['object']);
        }
        
        return $backtrace;
    }
}
