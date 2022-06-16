<?php

declare(strict_types = 1);

namespace Lingoda\CronBundle\DependencyInjection;

use Lingoda\CronBundle\Cron\CronJobInterface;
use Lingoda\CronBundle\Exception\RuntimeException;
use Lingoda\CronBundle\Messenger\CronJobDueMessage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class LingodaCronExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(CronJobInterface::class)
            ->addTag('lingoda_cron.cron_job')
        ;

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $configuration = $this->getConfiguration($configs, $container);
        if (!$configuration) {
            throw new RuntimeException('Missing configuration');
        }
        $config = $this->processConfiguration($configuration, $configs);

        $bus = $config['messenger_bus_name'] ?? null;

        if (null !== $bus) {
            $triggerDefinition = $container->getDefinition('lingoda_cron.due_cron_jobs_trigger');
            $triggerDefinition->replaceArgument(1, new Reference($bus));
        }

        $handlerDefinition = $container->getDefinition('lingoda_cron.cron_job_due_handler');
        $handlerDefinition->addTag('messenger.message_handler', ['handles' => CronJobDueMessage::class, 'bus' => $bus]);
    }
}
