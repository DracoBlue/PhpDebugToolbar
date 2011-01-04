/*
 * Copyright 2010 by DracoBlue.net, licensed under the terms of MIT
 */

/**
 * PhpDebugToolbar is the javascript part for the PhpDebugToolbar.
 */
PhpDebugToolbar = function(container, options)
{
    var self = this;

    this.sections = options.sections;
    var sections = this.sections;

    this.ui_css_location = options.ui_css_location;
    this.cookie_name = options.cookie;

    this.initializeLogsCountAndLevel();

    this.total = {};

    for ( var key in sections[0])
    {
        if (key.match(/^start_/))
        {
            this.total[key] = sections[0][key];
        }
    }
    for ( var key in sections[sections.length - 1])
    {
        if (key.match(/^end_/))
        {
            this.total[key] = sections[sections.length - 1][key];
        }
    }

    this.dom_element = container;

    this.visible = false;
    this.database_visible = false;

    this.toggle_button = document.createElement('span');
    this.toggle_button.innerHTML = 'PhpDebugToolbar [show]';
    this.toggle_button.setAttribute('style', 'position:fixed; bottom: 40px; right: 20px; z-index: 20000;');
    this.toggle_button.onclick = function()
    {
        if (self.visible)
        {
            self.hide();
        }
        else
        {
            self.show();
        }
    };

    this.dom_element.appendChild(this.toggle_button);

    this.navigation = null;

    if (this.getOption('visible'))
    {
        this.show();
    }
    else
    {
        if (typeof this.getOption('visible') === 'undefined')
        {
            this.show();
        }
    }
    
    this.initializePopupStateChecker();
};

PhpDebugToolbar.prototype.initializeLogsCountAndLevel = function()
{
    this.errors_count = 0;
    this.warnings_count = 0;
    this.debugs_count = 0;
    this.logs_count = 0;

    this.logs = [];

    var sections = this.sections;
    var sections_length = sections.length;
    for ( var i = 0; i < sections_length; i++)
    {
        var logs = sections[i].logs || [];
        var logs_length = logs.length;
        for ( var n = 0; n < logs_length; n++)
        {
            this.logs.push(logs[n]);

            if (logs[n].level === 'error')
            {
                this.errors_count++;
            }
            if (logs[n].level === 'warning')
            {
                this.warnings_count++;
            }
            if (logs[n].level === 'debug')
            {
                this.debugs_count++;
            }
            this.logs_count++;
        }
    }
};

PhpDebugToolbar.prototype.encodeXml = (function()
{
    var xml_special_to_escaped_one_map = {
        '&': '&amp;',
        '"': '&quot;',
        '<': '&lt;',
        '>': '&gt;'
    };

    var encodeXml = function(string)
    {
        return String.prototype.replace.apply(string, [
            /([\&"<>])/g, function(str, item)
            {
                return xml_special_to_escaped_one_map[item];
            }
        ]);
    };
    return encodeXml;
})();

PhpDebugToolbar.prototype.getValueDifference = function(element, key)
{
    return element['end_' + key] - element['start_' + key];
};

PhpDebugToolbar.prototype.getOption = function(key)
{
    var options = this.getOptions();
    return options[key];
};

PhpDebugToolbar.prototype.setOption = function(key, value)
{
    var options = this.getOptions();
    if (options[key] === value)
    {
        return;
    }
    options[key] = value;
    document.cookie = this.cookie_name + '=' + encodeURIComponent(JSON.stringify(options)) + '; expires=0; path=/';
};

PhpDebugToolbar.prototype.unsetOption = function(key)
{
    var options = this.getOptions();
    if (typeof options[key] === 'undefined')
    {
        return;
    }
    delete options[key];
    document.cookie = this.cookie_name + '=' + encodeURIComponent(JSON.stringify(options)) + '; expires=0; path=/';
};

PhpDebugToolbar.prototype.getOptions = function(key)
{
    var match = (document.cookie).match(new RegExp(this.cookie_name + '\=([^;]+)'));
    if (!match)
    {
        return {};
    }
    return JSON.parse(decodeURIComponent(match[1]));
};

PhpDebugToolbar.prototype.addNavigationNode = function(name, options)
{
    var self = this;
    var node = document.createElement('li');

    node.className = 'php_debug_toolbar_button ui-widget-header ui-corner-all ui-state-default';

    node.innerHTML = options.html;
    node.title = options.title;

    node.onclick = options.onClick;

    node.onmouseover = function()
    {
        self.addClass(node, 'ui-state-hover');
    };
    node.onmouseout = function()
    {
        self.removeClass(node, 'ui-state-hover');
    };

    this.navigation_nodes[name] = node;
};

PhpDebugToolbar.prototype.initializeNavigation = function()
{
    var self = this;
    if (this.navigation)
    {
        return;
    }

    this.navigation = document.createElement('div');
    this.navigation.className = 'php_debug_toolbar_navigation';
    this.navigation_nodes = {};

    var ul = document.createElement('ul');

    /*
     * Database Node
     */
    var queries_count = self.getValueDifference(self.total, 'database_count');
    var queries_time = self.getValueDifference(self.total, 'database_time');
    this.addNavigationNode('database', {
        'title': 'Amount of databse querires and the time taken to process them',
        'html': 'DB: ' + queries_count + ' in ' + Math.floor(queries_time * 1000) + 'ms',
        'onClick': function()
        {
            self.toggleDatabaseInfo(true);
        }
    });

    var action_caption = this.encodeXml(this.sections[1].caption);
    var total_execution_time = self.getValueDifference(self.total, 'time');
    action_caption = action_caption + ' | Time: ' + Math.floor(total_execution_time * 1000) + 'ms';    
    var total_execution_memory = self.getValueDifference(self.total, 'memory');
    action_caption = action_caption + ' | Mem: ' + Math.floor(total_execution_memory / 1000) + 'KB';    
    
    this.addNavigationNode('action', {
        'title': 'Executed Action, time and memory usage at php',
        'html': action_caption,
        'onClick': function()
        {
            self.toggleActionsInfo(true);
        }
    });

    this.navigation_nodes['warnings'] = (function()
    {
        var node = document.createElement('li');
        node.className = 'php_debug_toolbar_button ui-widget-header ui-corner-all ui-state-default';

        var count = self.errors_count + self.warnings_count;
        if (count > 0)
        {
            var level = 'error';
            if (self.errors_count > 0)
            {
                node.innerHTML = self.errors_count + ' Error' + (self.errors_count > 0 ? 's' : '');
            }
            else
            {
                level = 'highlight';
                node.innerHTML = self.warnings_count + ' Warning' + (self.warnings_count > 0 ? 's' : '');
            }
            node.className = node.className + ' ui-state-' + level;
        }
        else
        {
            node.innerHTML = 'No Warnings';
        }
        node.title = 'Amount of problems while executing php code';
        node.onclick = function()
        {
            self.toggleLogsInfo(true);
        };
        return node;
    })();

    this.navigation_nodes['logs'] = (function()
    {
        var node = document.createElement('li');
        node.className = 'php_debug_toolbar_button ui-widget-header ui-corner-all';
        if (self.debugs_count !== 0)
        {
            node.className = node.className + ' ui-state-default';
            node.innerHTML = self.debugs_count + ' Message' + (self.debugs_count > 1 ? 's' : '');
        }
        else
        {
            node.className = node.className + ' ui-state-disabled';
            node.innerHTML = 'No Log';
        }
        node.title = 'Log messages';
        node.onclick = function()
        {
            self.toggleLogsInfo(true);
        };
        return node;
    })();

    ul.appendChild(this.navigation_nodes['warnings']);
    ul.appendChild(this.navigation_nodes['action']);
    ul.appendChild(this.navigation_nodes['database']);
    if (this.debugs_count !== 0)
    {
        ul.appendChild(this.navigation_nodes['logs']);
    }

    this.navigation.appendChild(ul);
};

PhpDebugToolbar.prototype.initializePopupStateChecker = function()
{
    var self = this;
    
    var store_position = function(key)
    {
        var x = self[key + '_window'].screenX;
        var y = self[key + '_window'].screenY;
        
        if (self[key + '_pos'])
        {
            if (self[key + '_pos'].x != x || self[key + '_pos'].y != y)
            {
                self.setOption(key + '_pos', x + 'x' + y);
            }
        } else {
            self[key + '_pos'] = {};
        }
    
        self[key + '_pos'].x = x;
        self[key + '_pos'].y = y;
    };
    
    setInterval(function() {
        if (self.actions_window && self.actions_visible)
        {
            if (self.actions_window.closed)
            {
                self.actions_window = null;
                self.toggleActionsInfo(false);
            }
            else
            {
                store_position('actions');
            }
        }
        if (self.database_window && self.database_visible)
        {
            if (self.database_window.closed)
            {
                self.database_window = null;
                self.toggleDatabaseInfo(false);
            }
            else
            {
                store_position('database');
            }
        }
        
        if (self.logs_window && self.logs_visible)
        {
            if (self.logs_window.closed)
            {
                self.logs_window = null;
                self.toggleLogsInfo(false);
            }
            else
            {
                store_position('logs');
            }
        }
    }, 1000);
}

PhpDebugToolbar.prototype.show = function()
{
    this.initializeNavigation();
    this.visible = true;
    this.setOption('visible', true);
    this.toggle_button.innerHTML = 'PhpDebugToolbar [hide]';
    this.dom_element.appendChild(this.navigation);
    if (this.getOption('logs'))
    {
        this.toggleLogsInfo(false);
    }
    if (this.getOption('database'))
    {
        this.toggleDatabaseInfo(false);
    }
    if (this.getOption('actions'))
    {
        this.toggleActionsInfo(false);
    }
};

PhpDebugToolbar.prototype.hide = function()
{
    this.initializeNavigation();
    this.visible = false;
    this.setOption('visible', false);
    this.toggle_button.innerHTML = 'PhpDebugToolbar [show]';
    this.dom_element.removeChild(this.navigation);
    this.hideLogsInfo();
    this.hideDatabaseInfo();
    this.hideActionsInfo();
};

PhpDebugToolbar.prototype.getInfoHeaderHtml = function(focus)
{
    var content = [];

    content.push('<link rel="stylesheet" href="' + this.encodeXml(this.ui_css_location) + '" type="text/css" media="all"/>');
    content.push('<style type="text/css">');
    /*
     * reset stylesheet
     */
    content.push("html,body,div,span,applet,object,iframe,h1,h2,h3,h4,h5,h6,p,blockquote,pre,a,abbr,");
    content.push("acronym,address,big,cite,code,del,dfn,em,font,img,ins,kbd,q,s,samp,small,strike,strong,");
    content.push("sub,sup,tt,var,b,u,i,center,dl,dt,dd,ol,ul,li,fieldset,form,label,legend,table,caption,");
    content.push("tbody,tfoot,thead,tr,th,td{margin:0;padding:0;border:0;outline:0;font-size:100%;");
    content.push("vertical-align:baseline;background:transparent;}body{line-height:1;}ol,ul{list-style:none;}");
    content.push("blockquote,q{quotes:none;}blockquote:before,blockquote:after,q:before,q:after{content:'';");
    content.push("content:none;}:focus{outline:0;}ins{text-decoration:none;}del{text-decoration:line-through;}");
    content.push("table{border-collapse:collapse;border-spacing:0;}");
    content.push('body {');
    content.push('    font-family: Helvetica,Arial,Sans-Serif;');
    content.push('    overflow-y: scroll;');
    content.push('}');
    content.push('.no-wrap { ');
    content.push('    white-space: nowrap;');
    content.push('}');
    content.push('.location {');
    content.push('   padding: 5px;');
    content.push('   font-size: 80%;');
    content.push('   display: block;');
    content.push('}');
    content.push('td, th {');
    content.push('    padding: 0.5em;');
    content.push('    vertical-align: top;');
    content.push('    text-align: left;');
    content.push('}');
    content.push('</style>');

    return content.join("\n");
};

PhpDebugToolbar.prototype.toggleActionsInfo = function(focus)
{
    if (this.actions_visible)
    {
        this.hideActionsInfo();
        this.unsetOption('actions');
        this.removeClass(this.navigation_nodes['action'], 'ui-state-active');
    }
    else
    {
        this.setOption('actions', true);
        this.actions_visible = true;
        this.addClass(this.navigation_nodes['action'], 'ui-state-active');
        this.actions_window = this.refreshInfoWindow('actions', 'Actions (Time & Memory)', this.getActionsInfoHtml());
        if (focus)
        {
            this.actions_window.focus();
        }
    }
};
PhpDebugToolbar.prototype.hideActionsInfo = function()
{
    this.actions_visible = false;
    if (this.actions_window)
    {
        this.actions_window.close();
        this.actions_window = null;
    }
};

PhpDebugToolbar.prototype.getActionsInfoHtml = function()
{
    var encodeXml = this.encodeXml;

    var content = [];
    content.push(this.getInfoHeaderHtml());
    content.push('<style type="text/css">');
    content.push('h3 {');
    content.push('   display: inline;');
    content.push('}');
    content.push('</style>');

    content.push('<table style="width: 100%">');
    content.push('<tr><th class="ui-widget-header">Section</th>');
    content.push('<th style="width:10px" class="ui-widget-header">DB</th>');
    content.push('<th style="width:10px" class="ui-widget-header">Memory</th>');
    content.push('<th style="width:10px" class="ui-widget-header">Time</th>');
    content.push('</tr>');
    content.push(this.getHtmlForFlowExecution( {
        'id': 0
    }, -1));
    content.push('</table>');

    return content.join("\n");
};

PhpDebugToolbar.prototype.getHtmlForFlowExecution = function(execution, indention)
{
    var encodeXml = this.encodeXml;
    var content = [];

    var id = execution.id;

    var executions = this.sections;
    var executions_length = executions.length;
    if (id !== 0)
    {
        content.push('<tr>');
        content.push('<td style="padding-left:' + (10 + indention * 20) + 'px" class="ui-widget-content">');
        content.push('<h3>' + encodeXml(execution.caption) + '</h3>');
        if (typeof execution.location === 'undefined')
        {
            execution.location = '';
        }
        content.push('<span class="location">' + encodeXml(execution.location) + '</span>');
        content.push('</td>');
        content.push('<td class="no-wrap ui-widget-content">');
        if (execution.end_database_count != execution.start_database_count)
        {
            content.push(encodeXml(Math.floor(this.getValueDifference(execution, 'database_count')) + ' in '));
            content.push(encodeXml(Math.floor(this.getValueDifference(execution, 'database_time') * 1000)) + 'ms');
        }
        content.push('</td>');
        content.push('<td class="no-wrap ui-widget-content">');
        content.push(encodeXml(Math.floor(this.getValueDifference(execution, 'memory') / 1000)));
        content.push(' KB</td>');
        content.push('<td class="no-wrap ui-widget-content">');
        content.push(encodeXml(Math.floor(this.getValueDifference(execution, 'time') * 1000)));
        content.push('ms</td>');
        content.push('</tr>');
    }

    for ( var i = 0; i < executions_length; i++)
    {
        var sub_execution = executions[i];
        if (sub_execution.parent == id)
        {
            content.push(this.getHtmlForFlowExecution(sub_execution, indention + 1));
        }
    }

    return content.join("\n");
};

PhpDebugToolbar.prototype.removeClass = function(dom_element, class_name)
{
    var old_class = ' ' + dom_element.className + ' ';
    dom_element.className = old_class.replace(new RegExp(' ' + class_name + ' ', 'g'), ' ').trim();
};

PhpDebugToolbar.prototype.addClass = function(dom_element, class_name)
{
    var old_class = ' ' + dom_element.className + '';
    dom_element.className = old_class.replace(' ' + class_name + ' ', ' ').trim() + ' ' + class_name;
};

PhpDebugToolbar.prototype.toggleLogsInfo = function(focus)
{
    if (this.logs_visible)
    {
        this.hideLogsInfo();
        this.unsetOption('logs');
        this.removeClass(this.navigation_nodes['logs'], 'ui-state-active');
        this.removeClass(this.navigation_nodes['warnings'], 'ui-state-active');
    }
    else
    {
        this.logs_visible = true;
        this.setOption('logs', true);
        this.addClass(this.navigation_nodes['logs'], 'ui-state-active');
        this.addClass(this.navigation_nodes['warnings'], 'ui-state-active');
        this.logs_window = this.refreshInfoWindow('logs', 'Logs', this.getLogsInfoHtml());
        if (focus)
        {
            this.logs_window.focus();
        }
    }
};

PhpDebugToolbar.prototype.hideLogsInfo = function()
{
    this.logs_visible = false;
    if (this.logs_window)
    {
        this.logs_window.close();
        this.logs_window = null;
    }
};

PhpDebugToolbar.prototype.getLogsInfoHtml = function()
{
    var encodeXml = this.encodeXml;

    var content = [];
    content.push(this.getInfoHeaderHtml());

    content.push('<table style="width:100%">');
    var groups = [
        "error", "warning", "debug"
    ];

    var groups_length = groups.length;
    for ( var i = 0; i < groups.length; i++)
    {
        var group = groups[i];
        if (this[group + 's_count'] > 0)
        {
            content.push('<tr><th class="ui-widget-header">Total ' + group + ': ' + encodeXml(this[group + 's_count']));
            content.push('</th></tr>');
            var logs = this.logs;
            var logs_length = logs.length;
            for ( var m = 0; m < logs_length; m++)
            {
                if (logs[m].level === group)
                {
                    var log_entry = logs[m];
                    var log_class = '';
                    if (group === 'warning')
                    {
                        log_class = 'ui-state-highlight';
                    }
                    if (group === 'error')
                    {
                        log_class = 'ui-state-error';
                    }
                    content.push('<tr><td class="' + log_class + '">' + encodeXml(log_entry.message));
                    content.push('<span class="location">' + encodeXml(log_entry.file + ':' + log_entry.line));
                    content.push('</span>');
                    content.push('</td></tr>');
                }
            }
        }
    }
    content.push('</table>');

    return content.join("\n");
};

PhpDebugToolbar.prototype.toggleDatabaseInfo = function(focus)
{
    if (this.database_visible)
    {
        this.hideDatabaseInfo();
        this.unsetOption('database');
        this.removeClass(this.navigation_nodes['database'], 'ui-state-active');
    }
    else
    {
        this.database_visible = true;
        this.setOption('database', true);
        this.addClass(this.navigation_nodes['database'], 'ui-state-active');
        this.database_window = this.refreshInfoWindow('database', 'Database', this.getDatabaseInfoHtml());
        if (focus)
        {
            this.database_window.focus();
        }
    }
};

PhpDebugToolbar.prototype.hideDatabaseInfo = function()
{
    this.database_visible = false;
    if (this.database_window)
    {
        this.database_window.close();
        this.database_window = null;
    }
};

PhpDebugToolbar.prototype.getDatabaseInfoHtml = function()
{
    var encodeXml = this.encodeXml;

    var content = [];
    content.push(this.getInfoHeaderHtml());
    content.push('<style type="text/css">');
    content.push('td .short {');
    content.push('    display: block;');
    content.push('    cursor: pointer;');
    content.push('}');
    content.push('td.more .short {');
    content.push('    display: none;');
    content.push('}');
    content.push('td .full {');
    content.push('    display: none;');
    content.push('}');
    content.push('td.more .full {');
    content.push('    display: block;');
    content.push('}');
    content.push('li {');
    content.push('    margin-left: 2em;');
    content.push('}');
    content.push('</style>');

    content.push('<table style="width: 100%">');
    content.push('<tr><th class="ui-widget-header">Query</th>');
    content.push('<th style="width:10px" class="ui-widget-header">Count</th>');
    content.push('</tr>');

    var queries_group_map = {};
    var next_query_group_id = 0;
    var query_groups = [];

    var sections_length = this.sections.length;
    for ( var i = 0; i < sections_length; i++)
    {
        var section = this.sections[i];
        if (section.database_queries && section.database_queries.length)
        {
            for ( var q = 0; q < section.database_queries.length; q++)
            {
                var query = section.database_queries[q];
                if (typeof queries_group_map[query.group] === "undefined")
                {
                    queries_group_map[query.group] = query_groups.length;
                    var query_entries = {};
                    query_entries[query.sql] = {
                        "stacks": [
                            query.stack
                        ]
                    };
                    query_groups.push( [
                        query.group, query_entries, 1
                    ]);
                }
                else
                {
                    var query_group_id = queries_group_map[query.group];
                    if (typeof query_groups[query_group_id][1][query.sql] === 'undefined')
                    {
                        query_groups[query_group_id][1][query.sql] = {
                            "stacks": [
                                query.stack
                            ]
                        };
                    }
                    else
                    {
                        query_groups[query_group_id][1][query.sql].stacks.push(query.stack);
                    }

                    query_groups[query_group_id][2]++;
                }
            }
        }
    }

    var query_groups_length = query_groups.length;

    for ( var qg = 0; qg < query_groups_length; qg++)
    {
        var query_group_name = query_groups[qg][0];
        var queries = query_groups[qg][1];
        var queries_count = query_groups[qg][2];
        var queries_length = queries.length;

        content.push('<tr>');
        var full_content = '<span onclick="this.parentNode.parentNode.className=&quot;&quot;"';
        full_content = full_content + ' style="cursor:pointer">' + encodeXml(query_group_name) + '</span>';
        full_content = full_content + '<ul>';

        for ( var query_sql in queries)
        {
            if (!queries.hasOwnProperty(query_sql))
            {
                continue;
            }
            var query_stacks = queries[query_sql].stacks;

            full_content = full_content + '<li><strong>' + encodeXml(query_sql) + '</strong><ul>';

            for ( var s = 0; s < query_stacks.length; s++)
            {
                var query_stack = query_stacks[s];
                var query_stack_length = query_stack.length;
                full_content = full_content + '<li><ul>';
                for ( var sq = 0; sq < query_stack_length; sq++)
                {
                    var stack_entry = query_stack[sq];

                    full_content = full_content + '<li>' + encodeXml(stack_entry['class'] + stack_entry['type']);
                    full_content = full_content + encodeXml(stack_entry['function']) + '<span class="location">';
                    full_content = full_content + encodeXml(stack_entry['file'] + ':' + stack_entry['line']);
                    full_content = full_content + '</span></li>';
                }
                full_content = full_content + '</ul></li>';
            }
            full_content = full_content + '</ul></li>';
        }

        full_content = full_content + '</ul>';
        content.push('<td class="">');
        content.push('<div class="short" onclick="this.parentNode.className=&quot;more&quot;">');
        content.push(encodeXml(query_group_name.substr(0, 80) + (query_group_name.length > 80 ? '...' : '')));
        content.push('</div><div class="full">' + full_content + '</div>');
        content.push('</td>');
        content.push('<td>' + encodeXml(queries_count) + '</td>');
        content.push('</tr>');
    }
    content.push('</table>');
    return content.join('\n');
};

PhpDebugToolbar.prototype.refreshInfoWindow = function(key, title, html)
{
    var detail = window.open('', "php_debug_toolbar_window_" + key, "width=800,height=400,status=yes,scrollbars=yes,resizable=yes");
    detail.document.write( [
        '<script type="text/javascript">',
        'if (!document.body) {document.write("<html><head><title></title></head><' + 'body></' + 'body></html>"); }',
        'document.getElementsByTagName("title")[0].innerHTML = ' + JSON.stringify(title) + ';',
        'var content = document.getElementById("content");', 'if (content) {', '    document.body.removeChild(content);', '}',
        'content = document.createElement("div");', 'content.id="content";', 'content.innerHTML = ' + JSON.stringify(html) + ';',
        'document.body.appendChild(content);', '<' + '/script>'
    ].join("\n"));
    
    if (this.getOption(key + '_pos'))
    {
        var coords = this.getOption(key + '_pos').split('x');
        detail.screenX = coords[0];
        detail.screenY = coords[1];
    }
    
    return detail;
};
