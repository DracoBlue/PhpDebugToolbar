<?php

class PropelDatabaseToolbarExtension
{
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
            $config = Propel::getConfiguration(PropelConfiguration::TYPE_ARRAY);
            
            require_once(dirname(__FILE__) . '/../lib/agavi/PhpDebugToolbarPropelConnection.class.php');

            foreach ($config['datasources'] as $name => &$datasource)
            {
                if (is_array($datasource))
                {
                    $datasource['connection']['classname'] = 'PhpDebugToolbarPropelConnection';
                }
            }

            Propel::setConfiguration($config);
            /*
             * Closing the connection once: will force reinitializing the
             * connection when getDatabaseConnection is called again. That's
             * the only way to "inject" the PhpDebugToolbarPropelConnection
             * for sure.
             */
            Propel::close();
        }
        
    	PhpDebugToolbar::incrementValue('database_count', self::$count - self::$sections_start_count[$section_id]);
    	PhpDebugToolbar::incrementValue('database_time', self::$time - self::$sections_start_time[$section_id]);
    }
    
    private function getQueryCount()
    {
        $db_manager = AgaviContext::getInstance()->getDatabaseManager();
        
        if (!empty($db_manager))
        {
            $db_connection = $db_manager->getDatabase()->getConnection();
            
            if (is_callable(array($db_connection, 'getQueryCount')))
            {
                return $db_connection->getQueryCount();
            }
        }
        
        return 0;
    }
}
