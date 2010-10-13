<?php
class DoctrineDatabaseToolbarExtension {

    static $count = 0;
    static $time = 0;

    public function startSection($section_id)
    {
        $db_count = self::$count;
        $db_time = self::$time;
        PhpDebugToolbar::setValue('start_database_count', $db_count);
        PhpDebugToolbar::setValue('start_database_time', $db_time);
    }

    public function finishSection($section_id)
    {
        $db_count = 0;
        $db_time = 0;
        if (PhpDebugToolbar::isBootstrap())
        {
            $db_manager = AgaviContext::getInstance()->getDatabaseManager();
            if (!empty($db_manager))
            {    
                $db_connection = $db_manager->getDatabase()->getConnection();
                if (is_callable(array($db_connection, 'addListener')))
                {    
                    require_once(dirname(__FILE__) . '/../lib/agavi/PhpDebugToolbarDoctrineConnectionListener.class.php');
                    $db_connection->addListener(new PhpDebugToolbarDoctrineConnectionListener());
                }    
            }  
        }
        else
        {
            $db_count = self::$count;
            $db_time = self::$time;
        }
        PhpDebugToolbar::setValue('end_database_count', $db_count);
        PhpDebugToolbar::setValue('end_database_time', $db_time);
    }

}
