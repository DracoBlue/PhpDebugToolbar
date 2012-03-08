<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_startup_errors', 'On');

require dirname(__FILE__) . '/../PhpDebugToolbar.class.php';
require dirname(__FILE__) . '/../extensions/CodeCoverageToolbarExtension.class.php';
PhpDebugToolbar::start(array(
    'extensions' => array(
        'CodeCoverageToolbarExtension'
    ),
    'code_coverage' => array(
        'password' => 'hans',
        'filename' => dirname(__FILE__) . '/cache/code_coverage.txt',
        'include' => array(
            dirname(dirname(__FILE__)),
        ),
        'exclude' => array(
            dirname(dirname(__FILE__)) . '/extensions/',
        )
    ), 
    'js_location' => './../pub/PhpDebugToolbar.js',
    'css_location' => './../pub/PhpDebugToolbar.css',
    'ui_css_location' => 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.5/themes/south-street/jquery-ui.css'
));

if (!is_dir(dirname(__FILE__) . '/cache/'))
{
    mkdir(dirname(__FILE__) . '/cache/');
    chmod(dirname(__FILE__) . '/cache/', 0777);
}

$section_id = PhpDebugToolbar::startSection('Calculation');

PhpDebugToolbar::finishSection($section_id);
?>

<html>
    <head>
<?php
echo PhpDebugToolbar::renderHead();
?>
        
    </head>
    <body>
<?php

echo PhpDebugToolbar::renderBody();
?>
    </body>
</html>