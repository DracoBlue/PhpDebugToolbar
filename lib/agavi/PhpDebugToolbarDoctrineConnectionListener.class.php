<?php

class PhpDebugToolbarDoctrineConnectionListener extends Doctrine_EventListener {
   
    protected $last_exec_start_time = null;

    protected $last_exec_start_memory = null;

    protected $last_exec_info = array();

    protected function storeLastExecInfoForEvent(Doctrine_Event $event)
    {
        $query_group = $event->getQuery();
        $query_string = $query_group;
        $params = $event->getParams();

        if ($params)
        {
            foreach ($params as $key => $value)
            {
                /*
                 * Just replace the first ?, not all!
                 */
                if (is_numeric($key))
                {
                    $query_string = preg_replace('/\?/', $value, $query_string, 1);
                }
                else
                {
                    $query_string = str_replace(':'.$key, is_numeric($value) ? $value : "'" . $value . "'", $query_string);
                }
            }
        }

        $backtrace = array_slice(debug_backtrace(false), 5, 6);
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

    protected function appendValueForLastExecInfo(Doctrine_Event $event)
    {
        $time = (microtime(true) - $this->last_exec_start_time);
        $memory = (memory_get_usage() - $this->last_exec_start_memory);
        $this->last_exec_info['memory'] = $memory;
        $this->last_exec_info['time'] = $time;
        $invoker = $event->getInvoker();
        $row_count = 0;
        if (is_callable(array($invoker, 'rowCount')))
        {
            $row_count = $invoker->rowCount();
        }
        $this->last_exec_info['rows'] = $row_count;
        PhpDebugToolbar::appendValue('database_queries', $this->last_exec_info);
        DoctrineDatabaseToolbarExtension::$count++;
        DoctrineDatabaseToolbarExtension::$time += $time;
    }

    public function preExec(Doctrine_Event $event)
    {
        $this->storeLastExecInfoForEvent($event);
    }

    public function postExec(Doctrine_Event $event)
    {
        $this->appendValueForLastExecInfo($event);
    }

    public function preStmtExecute(Doctrine_Event $event)
    {
        $this->storeLastExecInfoForEvent($event);
    }

    public function postStmtExecute(Doctrine_Event $event)
    {
        $this->appendValueForLastExecInfo($event);
    }
 
    public function preQuery(Doctrine_Event $event)
    {
        $this->storeLastExecInfoForEvent($event);
    }

    public function postQuery(Doctrine_Event $event)
    {
        $this->appendValueForLastExecInfo($event);
    }
}
