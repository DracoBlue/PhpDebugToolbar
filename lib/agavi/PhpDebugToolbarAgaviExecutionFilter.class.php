<?php
/*
 * Copyright 2010 by DracoBlue.net, licensed under the terms of MIT
 */

class PhpDebugToolbarAgaviExecutionFilter extends AgaviExecutionFilter implements AgaviIActionFilter
{

    protected $php_debug_toolbar_section_id = 0;
    
    /**
     * This function is 99% like the original one, but has some additions.
     * Those are highlighted like this:
     * <pre>// PhpDebugToolbar additions:</pre>
     *
     * The function is licensed under the terms of LGPL.
     */
    public function execute(AgaviFilterChain $filterChain, AgaviExecutionContainer $container)
    {
        // $lm = $this->context->getLoggerManager();

        // get the context, controller and validator manager
        $controller = $this->context->getController();

        // get the current action information
        $actionName = $container->getActionName();
        $moduleName = $container->getModuleName();
        
        // the action instance
        $actionInstance = $container->getActionInstance();

        $request = $this->context->getRequest();

        $isCacheable = false;
        $cachingDotXml = AgaviToolkit::evaluateModuleDirective(
            $moduleName,
            'agavi.cache.path',
            array(
                'moduleName' => $moduleName,
                'actionName' => $actionName,
            )
        );
        if($this->getParameter('enable_caching', true) && is_readable($cachingDotXml)) {
            // $lm->log('Caching enabled, configuration file found, loading...');
            // no _once please!
            include(AgaviConfigCache::checkConfig($cachingDotXml, $this->context->getName()));
        }

        $isActionCached = false;

        if($isCacheable) {
            try {
                $groups = $this->determineGroups($config['groups'], $container);
                $actionGroups = array_merge($groups, array(self::ACTION_CACHE_ID));
            } catch(AgaviUncacheableException $e) {
                // a group callback threw an exception. that means we're not allowed t cache
                $isCacheable = false;
            }
            if($isCacheable) {
                // this is not wrapped in the try/catch block above as it might throw an exception itself
                $isActionCached = $this->checkCache(array_merge($groups, array(self::ACTION_CACHE_ID)), $config['lifetime']);
            
                if(!$isActionCached) {
                    // cacheable, but action is not cached. notify our callback so it can prevent the stampede that follows
                    $this->startedCacheCreationCallback(self::CACHE_CALLBACK_ACTION_NOT_CACHED, $actionGroups, $config, $container);
                }
            }
        } else {
            // $lm->log('Action is not cacheable!');
        }

        if($isActionCached) {
            // $lm->log('Action is cached, loading...');
            // cache/dir/4-8-15-16-23-42 contains the action cache
            try {
                $actionCache = $this->readCache($actionGroups);
                // and restore action attributes
                $actionInstance->setAttributes($actionCache['action_attributes']);
            } catch(AgaviException $e) {
                // cacheable, but action is not cached. notify our callback so it can prevent the stampede that follows
                $this->startedCacheCreationCallback(self::CACHE_CALLBACK_ACTION_CACHE_GONE, $actionGroups, $config, $container);
                $isActionCached = false;
            }
            
            if ($isActionCached)
            {
                // PhpDebugToolbar additions:
                $php_debug_toolbar_section_id = PhpDebugToolbar::startSection($actionName . 'Action [cached] (' . $moduleName .')');
                if ($actionInstance->isSimple())
                {
                    PhpDebugToolbar::setValue('caption', $actionName . ' [cached, simple] (' . $moduleName .')');
                }
                PhpDebugToolbar::finishSection($php_debug_toolbar_section_id);
            }
        }
        
        $isViewCached = false;
        $rememberTheView = null;
        
        while(true) {
            if(!$isActionCached) {
                // PhpDebugToolbar additions:
                $php_debug_toolbar_section_id = PhpDebugToolbar::startSection($actionName . 'Action (' . $moduleName . ')');
                if ($actionInstance->isSimple())
                {
                    PhpDebugToolbar::setValue('caption', $actionName . ' [simple] (' . $moduleName . ')');
                }
                
                $actionCache = array();
            
                // $lm->log('Action not cached, executing...');
                // execute the Action and get the View to execute
                list($actionCache['view_module'], $actionCache['view_name']) = $container->runAction();
                
                // check if we've just run the action again after a previous cache read revealed that the view is not cached for this output type and we need to go back to square one due to the lack of action attribute caching configuration...
                // if yes: is the view module/name that we got just now different from what was in the cache?
                if(isset($rememberTheView) && $actionCache != $rememberTheView) {
                    // yup. clear it!
                    $ourClass = get_class($this);
                    call_user_func(array($ourClass, 'clearCache'), $groups);
                }
                
                // check if the returned view is cacheable
                if($isCacheable && is_array($config['views']) && !in_array(array('module' => $actionCache['view_module'], 'name' => $actionCache['view_name']), $config['views'], true)) {
                    $isCacheable = false;
                    $this->abortedCacheCreationCallback(self::CACHE_CALLBACK_VIEW_NOT_CACHEABLE, $actionGroups, $config, $container);
                    
                    // so that view is not cacheable? okay then:
                    // check if we've just run the action again after a previous cache read revealed that the view is not cached for this output type and we need to go back to square one due to the lack of action attribute caching configuration...
                    // 'cause then we need to flush all those existing caches - obviously, that data is stale now, as we learned, since we are not allowed to cache anymore for the view that was returned now
                    if(isset($rememberTheView)) {
                        // yup. clear it!
                        $ourClass = get_class($this);
                        call_user_func(array($ourClass, 'clearCache'), $groups);
                    }
                    // $lm->log('Returned View is not cleared for caching, setting cacheable status to false.');
                } else {
                    // $lm->log('Returned View is cleared for caching, proceeding...');
                }

                $actionAttributes = $actionInstance->getAttributes();
                
                // PhpDebugToolbar additions:
                PhpDebugToolbar::finishSection($php_debug_toolbar_section_id);
            }

            // clear the response
            $response = $container->getResponse();
            $response->clear();

            // clear any forward set, it's ze view's job
            $container->clearNext();

            if($actionCache['view_name'] !== AgaviView::NONE) {

                $container->setViewModuleName($actionCache['view_module']);
                $container->setViewName($actionCache['view_name']);
                
                // PhpDebugToolbar additions:
                $php_debug_toolbar_section_id = PhpDebugToolbar::startSection($actionCache['view_name'].'View (' . $actionCache['view_module'] . ')');

                $key = $request->toggleLock();
                try {
                    // get the view instance
                    $viewInstance = $controller->createViewInstance($actionCache['view_module'], $actionCache['view_name']);
                    // initialize the view
                    $viewInstance->initialize($container);
                } catch(Exception $e) {
                    // we caught an exception... unlock the request and rethrow!
                    $request->toggleLock($key);
                    throw $e;
                }
                $request->toggleLock($key);

                // Set the View Instance in the container
                $container->setViewInstance($viewInstance);
            
                $outputType = $container->getOutputType()->getName();

                if($isCacheable) {
                    if(isset($config['output_types'][$otConfig = $outputType]) || isset($config['output_types'][$otConfig = '*'])) {
                        $otConfig = $config['output_types'][$otConfig];
                        
                        $viewGroups = array_merge($groups, array($outputType));

                        if($isActionCached) {
                            $isViewCached = $this->checkCache($viewGroups, $config['lifetime']);
                            if(!$isViewCached) {
                                // cacheable, but view is not cached. notify our callback so it can prevent the stampede that follows
                                $this->startedCacheCreationCallback(self::CACHE_CALLBACK_VIEW_NOT_CACHED, $viewGroups, $config, $container);
                            }
                        }
                    } else {
                        $this->abortedCacheCreationCallback(self::CACHE_CALLBACK_OUTPUT_TYPE_NOT_CACHEABLE, $actionGroups, $config, $container);
                        $isCacheable = false;
                    }
                }

                if($isViewCached) {
                    // $lm->log('View is cached, loading...');
                    try {
                        $viewCache = $this->readCache($viewGroups);
                    } catch(AgaviException $e) {
                        $this->startedCacheCreationCallback(self::CACHE_CALLBACK_VIEW_CACHE_GONE, $viewGroups, $config, $container);
                        $isViewCached = false;
                    }
                }
                
                if(!$isViewCached) {
                    // view not cached
                    // has the cache config a list of action attributes?
                    if($isActionCached && !$config['action_attributes']) {
                        // no. that means we must run the action again!
                        $isActionCached = false;
                        
                        if($isCacheable) {
                            // notify our callback so it can remove the lock that's on the view
                            // but only if we're still marked as cacheable (if not, then that means the OT is not cacheable, so there wouldn't be a $viewGroups)
                            $this->abortedCacheCreationCallback(self::CACHE_CALLBACK_ACTION_CACHE_USELESS, $viewGroups, $config, $container);
                        }
                        // notify our callback so it can prevent the stampede that follows
                        $this->startedCacheCreationCallback(self::CACHE_CALLBACK_ACTION_CACHE_USELESS, $actionGroups, $config, $container);
                        
                        // but remember the view info, just in case it differs if we run the action again now
                        $rememberTheView = array(
                            'view_module' => $actionCache['view_module'],
                            'view_name' => $actionCache['view_name'],
                        );
                        
                        // PhpDebugToolbar additions:
                        PhpDebugToolbar::finishSection($php_debug_toolbar_section_id);
                        
                        continue;
                    }
                
                    $viewCache = array();
                    $viewCache['next'] = $this->executeView($container);
                }

                if($viewCache['next'] instanceof AgaviExecutionContainer) {
                    // $lm->log('Forwarding request, skipping rendering...');
                    $container->setNext($viewCache['next']);
                } else {
                    $output = array();
                    $nextOutput = null;
                
                    if($isViewCached) {
                        // PhpDebugToolbar additions:
                        PhpDebugToolbar::setValue('caption', $actionCache['view_name'].'View' . ' [cached] (' . $actionCache['view_module'] . ')');
                        
                        $layers = $viewCache['layers'];
                        $response = $viewCache['response'];
                        $container->setResponse($response);

                        foreach($viewCache['template_variables'] as $name => $value) {
                            $viewInstance->setAttribute($name, $value);
                        }

                        foreach($viewCache['request_attributes'] as $requestAttribute) {
                            $request->setAttribute($requestAttribute['name'], $requestAttribute['value'], $requestAttribute['namespace']);
                        }
                    
                        foreach($viewCache['request_attribute_namespaces'] as $ranName => $ranValues) {
                            $request->setAttributes($ranValues, $ranName);
                        }

                        $nextOutput = $response->getContent();
                    } else {
                        if($viewCache['next'] !== null) {
                            // response content was returned from view execute()
                            $response->setContent($nextOutput = $viewCache['next']);
                            $viewCache['next'] = null;
                        }

                        $layers = $viewInstance->getLayers();

                        if($isCacheable) {
                            $viewCache['template_variables'] = array();
                            foreach($otConfig['template_variables'] as $varName) {
                                $viewCache['template_variables'][$varName] = $viewInstance->getAttribute($varName);
                            }

                            $viewCache['response'] = clone $response;

                            $viewCache['layers'] = array();

                            $viewCache['slots'] = array();

                            $lastCacheableLayer = -1;
                            if(is_array($otConfig['layers'])) {
                                if(count($otConfig['layers'])) {
                                    for($i = count($layers)-1; $i >= 0; $i--) {
                                        $layer = $layers[$i];
                                        $layerName = $layer->getName();
                                        if(isset($otConfig['layers'][$layerName])) {
                                            if(is_array($otConfig['layers'][$layerName])) {
                                                $lastCacheableLayer = $i - 1;
                                            } else {
                                                $lastCacheableLayer = $i;
                                            }
                                        }
                                    }
                                }
                            } else {
                                $lastCacheableLayer = count($layers) - 1;
                            }

                            for($i = $lastCacheableLayer + 1; $i < count($layers); $i++) {
                                // $lm->log('Adding non-cacheable layer "' . $layers[$i]->getName() . '" to list');
                                $viewCache['layers'][] = clone $layers[$i];
                            }
                        }
                    }

                    $attributes =& $viewInstance->getAttributes();

                    // whether or not we should assign the previous' layer's output to the $slots array
                    $assignInnerToSlots = $this->getParameter('assign_inner_to_slots', false);
                    
                    // $lm->log('Starting rendering...');
                    for($i = 0; $i < count($layers); $i++) {
                        $layer = $layers[$i];
                        $layerName = $layer->getName();
                        // $lm->log('Running layer "' . $layerName . '"...');
                        foreach($layer->getSlots() as $slotName => $slotContainer) {
                            if($isViewCached && isset($viewCache['slots'][$layerName][$slotName])) {
                                // $lm->log('Loading cached slot "' . $slotName . '"...');
                                $slotResponse = $viewCache['slots'][$layerName][$slotName];
                            } else {
                                // $lm->log('Running slot "' . $slotName . '"...');
                                $slotResponse = $slotContainer->execute();
                                if($isCacheable && !$isViewCached && isset($otConfig['layers'][$layerName]) && is_array($otConfig['layers'][$layerName]) && in_array($slotName, $otConfig['layers'][$layerName])) {
                                    // $lm->log('Adding response of slot "' . $slotName . '" to cache...');
                                    $viewCache['slots'][$layerName][$slotName] = $slotResponse;
                                }
                            }
                            // set the presentation data as a template attribute
                            $output[$slotName] = $slotResponse->getContent();
                            // and merge the other slot's response (this used to be conditional and done only when the content was not null)
                            // $lm->log('Merging in response from slot "' . $slotName . '"...');
                            $response->merge($slotResponse);
                        }
                        $moreAssigns = array(
                            'container' => $container,
                            'inner' => $nextOutput,
                            'request_data' => $container->getRequestData(),
                            'validation_manager' => $container->getValidationManager(),
                            'view' => $viewInstance,
                        );
                        // lock the request. can't be done outside the loop for the whole run, see #628
                        $key = $request->toggleLock();
                        try {
                            $nextOutput = $layer->getRenderer()->render($layer, $attributes, $output, $moreAssigns);
                        } catch(Exception $e) {
                            // we caught an exception... unlock the request and rethrow!
                            $request->toggleLock($key);
                            throw $e;
                        }
                        // and unlock the request again
                        $request->toggleLock($key);

                        $response->setContent($nextOutput);

                        if($isCacheable && !$isViewCached && $i === $lastCacheableLayer) {
                            $viewCache['response'] = clone $response;
                        }

                        $output = array();
                        if($assignInnerToSlots) {
                            $output[$layer->getName()] = $nextOutput;
                        }
                    }
                }

                if($isCacheable && !$isViewCached) {
                    // we're writing the view cache first. this is just in case we get into a situation with really bad timing on the leap of a second
                    $viewCache['request_attributes'] = array();
                    foreach($otConfig['request_attributes'] as $requestAttribute) {
                        $viewCache['request_attributes'][] = $requestAttribute + array('value' => $request->getAttribute($requestAttribute['name'], $requestAttribute['namespace']));
                    }
                    $viewCache['request_attribute_namespaces'] = array();
                    foreach($otConfig['request_attribute_namespaces'] as $requestAttributeNamespace) {
                        $viewCache['request_attribute_namespaces'][$requestAttributeNamespace] = $request->getAttributes($requestAttributeNamespace);
                    }

                    $this->writeCache($viewGroups, $viewCache, $config['lifetime']);

                    // notify callback that the execution has finished and caches have been written
                    $this->finishedCacheCreationCallback(self::CACHE_CALLBACK_VIEW_CACHE_WRITTEN, $viewGroups, $config, $container);
                    // $lm->log('Writing View cache...');
                }
                
                // PhpDebugToolbar additions:
                PhpDebugToolbar::finishSection($php_debug_toolbar_section_id);
            }
        
            // action cache writing must occur here, so actions that return AgaviView::NONE also get their cache written
            if($isCacheable && !$isActionCached) {
                $actionCache['action_attributes'] = array();
                foreach($config['action_attributes'] as $attributeName) {
                    $actionCache['action_attributes'][$attributeName] = $actionAttributes[$attributeName];
                }

                // $lm->log('Writing Action cache...');

                $this->writeCache($actionGroups, $actionCache, $config['lifetime']);
            
                // notify callback that the execution has finished and caches have been written
                $this->finishedCacheCreationCallback(self::CACHE_CALLBACK_ACTION_CACHE_WRITTEN, $actionGroups, $config, $container);
            }
            
            // we're done here. bai.
            break;
        }
        
        // PhpDebugToolbar additions:
        if (in_array($container->getOutputType(), array('html')) && count(PhpDebugToolbar::$section_id_stack) === 0)
        {
            $response = $container->getResponse();
            $output = $response->getContent();
            $output = str_replace('</head>', PhpDebugToolbar::renderHead() .'</head>', $output);
            $output = str_replace('</body>', PhpDebugToolbar::renderBody() .'</body>', $output);
            $response->setContent($output);
        }
    }
}
