<?php
/*
 * Copyright 2010 by DracoBlue.net, licensed under the terms of MIT
 */

/**
 * PhpDebugToolbarLoggerAppender logs all log messages to the PhpDebugToolbar.
 */
class PhpDebugToolbarLoggerAppender extends AgaviLoggerAppender
{
	public function write($message)
	{
		if(($layout = $this->getLayout()) === null) {
			throw new AgaviLoggingException('No Layout set');
		}

		$str = $this->getLayout()->format($message);
        $bt = debug_backtrace();
        if (AgaviLogger::ERROR >= $message->getLevel()) {
            PhpDebugToolbar::log('error', $str, $bt[2]);
        } else if (AgaviLogger::WARN >= $message->getLevel()) {
            PhpDebugToolbar::log('warning', $str, $bt[2]);
        } else {
            PhpDebugToolbar::log('debug', $str, $bt[2]);
        }
	}

	public function shutdown()
    {
    }
}

