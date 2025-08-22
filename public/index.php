<?php\n\nuse App\Kernel;\n\nrequire_once dirname(__DIR__).'/vendor/autoload_runtime.php';\n\nreturn function (array \) {\n    return new Kernel(\['APP_ENV'], (bool) \['APP_DEBUG']);\n};
