<?php

declare(strict_types=1);

namespace B7s\FluentCut\Console;

use B7s\FluentCut\Console\Commands\DoctorCommand;
use B7s\FluentCut\Console\Commands\InfoCommand;
use Symfony\Component\Console\Application as BaseApplication;

final class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('FluentCut', '1.0.0');

        $this->add(new DoctorCommand());
        $this->add(new InfoCommand());
    }
}
