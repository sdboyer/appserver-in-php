<?php

namespace MFS\AppServer;

function autoload($class_name)
{
    static $files = null;

    if (null === $files) {
        $root = __DIR__.'/';

        $files = array(
            'MFS\AppServer\iHandler' => $root.'interfaces.php',
        );
    }

    if (isset($files[$class_name]))
        require $files[$class_name];
}

spl_autoload_register('MFS\AppServer\autoload');


// helper functions

/**
 * unifies callbacks (makes all of them directly-callable)
 *
 * @param mixed $callback
 * @return callback
 */
function callable($callback)
{
    if (!is_callable($callback)) {
        throw new Exception("callable only works on is_callable's!");
    }

    if (!is_array($callback))
        return $callback;

    return function() use ($callback) {
        return call_user_func_array($callback, func_get_args());
    };
}
