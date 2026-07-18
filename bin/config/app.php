<?php

return [
    'install directory' => 'install',
    'merge prefix' => '@',
    'merge after' => '/* merged content below */',
    'empty config file' => '<?php

    declare(strict_types=1);

    /* if a simple array you don\'t need the strict_types=1 because you can\'t declare type on arrays yet */

    return [
        **ARRAY**
    ];
    ',
    'empty config file merge variable' => '**ARRAY**',
    'valid directories' => [
        '/bin',
        '/htdocs',
        '/support',
        '/var',
        '/config',
    ],
    'valid files' => [
        '/config'
    ],
];
