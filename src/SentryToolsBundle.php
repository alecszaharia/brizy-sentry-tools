<?php

namespace Brizy\SentryToolsBundle;

use Brizy\SentryToolsBundle\DependencyInjection\SentryToolsBundleExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class SentryToolsBundle extends Bundle
{
    public function getPath(): string
    {
        return __DIR__;
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new SentryToolsBundleExtension();
    }
}