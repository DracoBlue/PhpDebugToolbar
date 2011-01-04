<?php

class PhpDebugToolbarDoctrineConnectionListener extends Doctrine_EventListener {
   
    protected $last_exec_start_time = null;

    protected $last_exec_start_memory = null;

    protected $last_exec_info = array();

    public function preExec(Doctrine_Event $event)
    {
        $this->last_exec_start_time = microtime(true);
    }

    public function postExec(Doctrine_Event $event)
    {
        DoctrineDatabaseToolbarExtension::$count++;
        DoctrineDatabaseToolbarExtension::$time += (microtime(true) - $this->last_exec_start_time);
    }

    public function preStmtExecute(Doctrine_Event $event)
    {
        $query_group = $event->getQuery();
        $query_string = $query_group;
        $params = $event->getParams();

        if ($params) {
            foreach ($params as $value) {
                /*
                 * Just replace the first ?, not all!
                 */
                $query_string = preg_replace('/\?/', $value, $query_string, 1);
            }
        }

        $backtrace = array_slice(debug_backtrace(false), 4);
        foreach ($backtrace as $k => $v)
        {
            unset($backtrace[$k]['args']);
            unset($backtrace[$k]['object']);
        }
        $this->last_exec_info = array(
            'sql' => $query_string,
            'stack' => $backtrace,
            'group' => $query_group
        );
        $this->last_exec_start_time = microtime(true);
        $this->last_exec_start_memory = memory_get_usage();
    }

    public function postStmtExecute(Doctrine_Event $event)
    {
        $memory = (memory_get_usage() - $this->last_exec_start_memory);
        $this->last_exec_info['memory'] = $memory;
        $time = (microtime(true) - $this->last_exec_start_time);
        $this->last_exec_info['time'] = $time;
        PhpDebugToolbar::appendValue('database_queries', $this->last_exec_info);
        DoctrineDatabaseToolbarExtension::$count++;
        DoctrineDatabaseToolbarExtension::$time += $time;
    }
 
    public function preQuery(Doctrine_Event $event)
    {
        $this->last_exec_start_time = microtime(true);
    }

    public function postQuery(Doctrine_Event $event)
    {
        DoctrineDatabaseToolbarExtension::$count++;
        $time = (microtime(true) - $this->last_exec_start_time);
        DoctrineDatabaseToolbarExtension::$time += $time;
    }
}
