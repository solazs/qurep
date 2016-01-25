<?php

namespace QuReP\ApiBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class QuRePApiBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new DependencyInjection\QuRePApiExtension();
    }
}
