<?php
class DoctrineDatabaseToolbarExtension {

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

    	PhpDebugToolbar::incrementValue('database_count', self::$count - self::$sections_start_count[$section_id]);
    	PhpDebugToolbar::incrementValue('database_time', self::$time - self::$sections_start_time[$section_id]);
    }

}
