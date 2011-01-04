# PhpDebugToolbar

The PhpDebugToolbar (formerly known as Qos-Filter) is a tool, to help debugging
and maintaining agavi applications. The little 100% javascript toolbar displays
profiling information, which is necessary to find bottlenecks in the
application.

- Version: 1.3.0
- Date: 2011/12/04
- Screenshot: <http://twitpic.com/2zgig8>
- Official site: <http://dracoblue.net>

# Features

Current features contain:

* show a toolbar with memory usage, database usage and logging information
* doctrine/propel database queries and query groups + count + time + memory
* show sections (action+view) + db, memory usage and consumed time.
* logging window with warning, error and debug messages
* logging shows warnings and notices raised by php
* logging integrates with agavi's logging system
* stores the settings in a cookie
* refreshs the log+actions+database popup as soon as the main page refreshs
* shows whether an action was simple or not
* theming can be done by using jquery ui css framework
* remembers the position of the popups

# TODO

* add information about fragement cache
* highlight slots on mouseover
* expand/collapse sections

# Installation

Put PhpDebugToolbar into the folder `libs/PhpDebugToolbar` (next to your agavi
app folder). The folder looks like this then:

    app/
        config/
    libs/
        PhpDebugToolbar/
            README.md
            PhpDebugToolbar.class.php
            lib/
            ...

## Bootstrap

Now initialize the PhpDebugToolbar in your index.php file. Therefor edit
`pub/index.php` and add (at the very beginning):

    require dirname(__FILE__) . '/../libs/PhpDebugToolbar/PhpDebugToolbar.class.php';
    require dirname(__FILE__) . '/../libs/PhpDebugToolbar/extensions/DoctrineDatabaseToolbarExtension.class.php';
    PhpDebugToolbar::start(array(
        'extensions' => array(
            'DoctrineDatabaseToolbarExtension'
        ),
        'js_location' => '../libs/PhpDebugToolbar/pub/PhpDebugToolbar.js',
        'css_location' => '../libs/PhpDebugToolbar/pub/PhpDebugToolbar.css'
    ));

This example enables also the DoctrineDatabase Extension.

## Embedding agavi

To work great with all of agavi's features, you need to adjust some files.

Edit `app/config/factories.xml` and add:

    <ae:configuration environment="development.*">
        <execution_filter class="PhpDebugToolbarAgaviExecutionFilter" />
    </ae:configuration>

Edit `app/config/autoload.xml` and add:

    <xi:include href="%core.app_dir%/../libs/PhpDebugToolbar/lib/agavi/autoload.xml" xpointer="xmlns(ae=http://agavi.org/agavi/config/global/envelope/1.0) xpointer(/ae:configurations/*)">
    </xi:include>

(You may need to add ` xmlns:xi="http://www.w3.org/2001/XInclude"`, if not
available in your autoload, yet.)

That's it.

# Options

The `PhpDebugToolbar::start` method takes an array with options as first
argument.

Available options are:

## Option: extensions[]

Add the possible PhpDebugToolbar extensions by name.

Example (enable Doctrine Database):

    PhpDebugToolbar::start(array('extensions' => 'DoctrineDatabaseToolbarExtension'))

## Option: js_location + css_location

Even though PhpDebugToolbar is able to directly render the entire js and css
parts of the Toolbar with embedded javascript, you may want to load the files
from external resources (for performance reasons!).

Just configure:

    PhpDebugToolbar::start(array(
        'js_location' => '../libs/PhpDebugToolbar/pub/PhpDebugToolbar.js',
        'css_location' => '../libs/PhpDebugToolbar/pub/PhpDebugToolbar.css'
    ));

and PhpDebugToolbar will include <script-tags instead.

## Option: log_strict_errors + log_deprecated_errors

By default PhpDebugToolbar also logs deprecated and strict errors. If you don't
want to do so, disable this feature.

    PhpDebugToolbar::start(array(
        'log_strict_errors' => false,
        'log_deprecated_errors' => false
    ));

## Option: cookie

If you want to choose a custom cookie name to store the sessions (default is
php_debug_toolbar), specify the cookie option.

    PhpDebugToolbar::start(array(
        'cookie' => 'php_debug_toolbar'
    ));

## Option: ui_css_location

If you want to load a custom theme (default is the cupertino theme), you can
specify the theme url here.

Example:

    PhpDebugToolbar::start(array(
        'ui_css_location' => 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.5/themes/dark-hive/jquery-ui.css'
    ));

Enables the dark-hive theme.

# Contributors

The PhpDebugToolbar (formerly known as Qos-Filter) was created by and with
feedback from:

* Jan Schütze <JanS@DracoBlue.de>
* Sven Kretschmann <development@sven-kretschmann.de>
* Steffen Gransow, <steffen.gransow@mivesto.de>
* Christian Krupa <christian@krupa.biz>
* Tim Taubert <tim.taubert@digitale-enthusiasten.de>

# Changelog

- 1.3.0 (2011/01/04)
  - fixed issue with already initialized propel connection
  - fixed that sometimes loading the database query tab crashed
  - fixed that sometimes the overall db/memory/time sum was incorrect
  - display rows, time and memory in database tab
  - added rows, affected_rows, time and memory for db queries in
    propel
  - added rows, time and memory for doctrine statements
  - added # if query is executed multiple times
- 1.2.0 (2010/11/18)
  - added propel database extension
- 1.1.2 (2010/11/18)
  - fixed execution filter when cached actions get re-run
- 1.1.1 (2010/10/21)
  - removed dependency to JSON.encode/decode, using JSON.stringify/parse now
  - fixed list style, in case of usage without a reset stylesheet
  - link+style tags are injected into the head, html validation passes now
- 1.1.0 (2010/10/19)
  - remember the position of the popups
  - hiding the popup, even if closed with [x]
  - using the default-state-active instead of default-state-focus
  - merged action|time|memory into one button
  - fixed issue with prepared statements in doctrine
- 1.0.0 (2010/10/13)
  - initial version

# License

PhpDebugToolbar is licensed under the terms of MIT. See LICENSE for more information.
