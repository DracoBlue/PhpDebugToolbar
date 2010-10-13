<?php

class PhpDebugToolbarDoctrineConnectionListener extends Doctrine_EventListener {
   
    protected $last_exec_start_time = null;

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
        foreach ($event->getParams() as $value) {
            /*
             * Just replace the first ?, not all!
             */
            $query_string = preg_replace('/\?/', $value, $query_string, 1);
        }

        $backtrace = array_slice(debug_backtrace(false), 4);
        foreach ($backtrace as $k => $v)
        {
            unset($backtrace[$k]['args']);
            unset($backtrace[$k]['object']);
        }
        $this->last_exec_start_time = microtime(true);
        PhpDebugToolbar::appendValue('database_queries', array(
            'sql' => $query_string,
            'stack' => $backtrace,
            'group' => $query_group
        ));
    }

    public function postStmtExecute(Doctrine_Event $event)
    {
        DoctrineDatabaseToolbarExtension::$count++;
        DoctrineDatabaseToolbarExtension::$time += (microtime(true) - $this->last_exec_start_time);
    }
 
    public function preQuery(Doctrine_Event $event)
    {
        $this->last_exec_start_time = microtime(true);
    }

    public function postQuery(Doctrine_Event $event)
    {
        DoctrineDatabaseToolbarExtension::$count++;
        DoctrineDatabaseToolbarExtension::$time += (microtime(true) - $this->last_exec_start_time);
    }
}
