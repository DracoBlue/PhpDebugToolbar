<?php
/*
 * Copyright 2010 by DracoBlue.net, licensed under the terms of MIT
 */

/**
 * PhpDebugToolbar is the php part for the PhpDebugToolbar.
 */
abstract class PhpDebugToolbar
{
    final function __construct()
    {
        throw new Exception('This class is meant to be used in a static manner');
    }

    static $options = array(
        'extensions' => array(),
        'log_strict_errors' => true,
        'log_deprecated_errors' => true,
        'ui_css_location' => 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.7/themes/cupertino/jquery-ui.css',
        'cookie' => 'php_debug_toolbar'
    );

    static $extensions = array();

    static $bootstrapping = false;

    static $current_section_id = 0;

    static $next_section_id = 1;

    static $section_id_stack = array();

    static $sections = array();

    static function isBootstrap()
    {
        return self::$bootstrapping;
    }

    static function start(array $options = array())
    {
        self::$options = array_merge(self::$options, $options);

        if (!defined('E_DEPRECATED'))
        {
            define('E_DEPRECATED', 8192);
        }

        if (!defined('E_USER_DEPRECATED'))
        {
            define('E_USER_DEPRECATED', 16384);
        }

        $log_level =  E_ALL;

        if (self::$options['log_strict_errors'])
        {
            $log_level = $log_level | E_STRICT;
        }

        if (self::$options['log_deprecated_errors'])
        {
            $log_level = $log_level | E_DEPRECATED;
        }
        
        set_error_handler(
            array(
                __CLASS__,
                'handleErrorsHandler'
            ),
            $log_level
        );
        
        foreach (self::$options['extensions'] as $extension)
        {
            self::$extensions[] = new $extension();
        }

        self::startSection('bootstrap');
        self::$bootstrapping = true;
        
        $bt = debug_backtrace(true);
        
        self::setValue('location', $bt[0]['file'] . ':' . $bt[0]['line']);
    }

    static function startSection($caption)
    {
        if (self::$bootstrapping)
        {
            self::finishSection(self::$current_section_id);
            self::$bootstrapping = false;
        }

        $section_id = self::$next_section_id++;

        self::$sections[$section_id] = array(
            'id' => $section_id,
            'caption' => $caption,
            'parent' => self::$current_section_id,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage()
        );

        self::$section_id_stack[] = $section_id;

        self::$current_section_id = $section_id;

        foreach (self::$extensions as $extension)
        {
            $extension->startSection($section_id);
        }

        return $section_id;
    }

    static function finishSection($section_id)
    {
        if (self::$current_section_id !== $section_id)
        {
            echo "<pre>";
            print_r(self::$section_id_stack);
            print_r(self::getSections());
            throw new Exception('Cannot close section #' . $section_id . ', because #' . self::$current_section_id . ' is still open!');
        }

        self::$sections[$section_id]['end_time'] = microtime(true);
        self::$sections[$section_id]['end_memory'] = memory_get_usage();

        foreach (self::$extensions as $extension)
        {
            $extension->finishSection($section_id);
        }

        array_pop(self::$section_id_stack);

        if (count(self::$section_id_stack))
        {
            self::$current_section_id = self::$section_id_stack[count(self::$section_id_stack) - 1];
        }
        else
        {
            self::$current_section_id = 0;
        }
    }

    static function getSections()
    {
        return array_values(self::$sections);
    }

    static function setValue($key, $value)
    {
        self::$sections[self::$current_section_id][$key] = $value;
    }

    static function incrementValue($key, $value = 1)
    {
        self::$sections[self::$current_section_id][$key] = @self::$sections[self::$current_section_id][$key] + $value;
    }

    static function appendValue($key, $value)
    {
        if (!isset(self::$sections[self::$current_section_id][$key]))
        {
            self::$sections[self::$current_section_id][$key] = array($value);
        }
        else
        {
            self::$sections[self::$current_section_id][$key][] = $value;
        }
    }
    
    static function log($level, $message, $callee = null)
    {
        if ($callee === null)
        {
            $callee = debug_backtrace(true);
            $callee = $callee[2];
        }
        
        if (!in_array($level, array('debug', 'warning', 'error')))
        {
            throw new Exception('Log level must be one of: debug, warning or error.');
        }
        
        self::appendValue('logs', array(
            'message' => $message,
            'level' => $level,
            'file' => $callee['file'],
            'line' => $callee['line']
        ));
    }

    static function renderHead()
    {
        if (self::$current_section_id !== 0)
        {
            throw new Exception('The sections: #' . implode(', #', self::$section_id_stack) . ' are still open. finish them first!');
        }

        $content = array();
        $content[] = '<link rel="stylesheet" href="' . self::$options['ui_css_location'] . '" type="text/css" />';
        
        if (isset(self::$options['css_location']))
        {
            $content[] = '<link rel="stylesheet" href="' . self::$options['css_location'] . '" type="text/css" />';
        }
        else
        {
            $content[] = '<style type="text/css">';
            $content[] = file_get_contents(dirname(__FILE__) . '/pub/PhpDebugToolbar.css');
            $content[] = '</style>';
        }
        return implode(PHP_EOL, $content);
    }

    static function renderBody()
    {
        if (self::$current_section_id !== 0)
        {
            throw new Exception('The sections: #' . implode(', #', self::$section_id_stack) . ' are still open. finish them first!');
        }

        $content = array();
        $content[] = '<div id="php_debug_toolbar"> </div>';
        
        if (isset(self::$options['js_location']))
        {
            $content[] = '<script src="' . self::$options['js_location'] . '" type="text/javascript" charset="utf-8"> </script>';
            $content[] = '<script type="text/javascript">';
            $content[] = '// <!--';
        }
        else
        {
            $content[] = '<script type="text/javascript">';
            $content[] = '// <!--';
            $content[] = file_get_contents(dirname(__FILE__) . '/pub/PhpDebugToolbar.js');
        }

        $content[] = 'new PhpDebugToolbar(document.getElementById("php_debug_toolbar"), ';
        $content[] =    json_encode(array(
            'sections' => self::getSections(),
            'ui_css_location' => self::$options['ui_css_location'],
            'cookie' => self::$options['cookie']
        )). ');';
        $content[] = '// -->';
        $content[] = '</script>';

        return implode(PHP_EOL, $content);
    }
    
    public static function handleErrorsHandler($severity, $message, $filename, $line)
    {
        $log_level = 'debug';
        
        switch ($severity)
        {
            case E_ERROR:
            case E_USER_ERROR:
            case E_PARSE: // won't work anyways ;)
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_RECOVERABLE_ERROR:
            {
                $log_level = 'error';
                break;
            }
            case E_NOTICE:
            case E_USER_NOTICE:
            {
                $log_level = 'warning';
                break;
            }
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_STRICT:
            {
                $log_level = 'warning';
                break;
            }
            case E_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            {
                $log_level = 'warning';
                break;
            }
        }

        self::appendValue('logs', array(
            'message' => $message,
            'level' => $log_level,
            'file' => $filename,
            'line' => $line
        ));
        
        return false;
    }
    
}
