<?php

declare(strict_types=1);

use Lingoda\CronBundle\Command\ListCronJobsCommand;
use Lingoda\CronBundle\Command\RunCronJobCommand;
use Lingoda\CronBundle\Command\TriggerDueCronJobsCommand;
use Lingoda\CronBundle\Cron\CronJobRunner;
use Lingoda\CronBundle\Cron\DueCronJobsTrigger;
use Lingoda\CronBundle\EventListener\RunCronJobInputDefinitionDecorator;
use Lingoda\CronBundle\Messenger\CronJobDueHandler;
use Lingoda\CronBundle\Repository\CronDatesRepository;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('lingoda_cron.due_cron_jobs_trigger', DueCronJobsTrigger::class)
        ->args([
            service('lingoda_cron.cron_job_start_time_repository'),
            service('messenger.default_bus'),
            tagged_iterator('lingoda_cron.cron_job', indexAttribute: 'service_id'),
        ]);
    $services->alias(DueCronJobsTrigger::class, 'lingoda_cron.due_cron_jobs_trigger');

    $services->set('lingoda_cron.cron_job_start_time_repository', CronDatesRepository::class)
        ->args([
            service('Doctrine\ORM\EntityManagerInterface'),
        ]);
    $services->alias(CronDatesRepository::class, 'lingoda_cron.cron_job_start_time_repository');

    $services->set(TriggerDueCronJobsCommand::class)
        ->tag('console.command')
        ->args([
            service('lingoda_cron.due_cron_jobs_trigger'),
        ]);

    $services->set(RunCronJobCommand::class)
        ->tag('console.command')
        ->args([
            service('lingoda_cron.cron_job_runner'),
        ]);

    $services->set(ListCronJobsCommand::class)
        ->tag('console.command')
        ->args([
            tagged_iterator('lingoda_cron.cron_job', indexAttribute: 'service_id'),
            service('lingoda_cron.cron_job_start_time_repository'),
        ]);

    $services->set('lingoda_cron.cron_job_runner', CronJobRunner::class)
        ->args([
            tagged_locator('lingoda_cron.cron_job', indexAttribute: 'service_id'),
            service('lock.default.factory'),
            service('lingoda_cron.cron_job_start_time_repository'),
        ]);
    $services->alias(CronJobRunner::class, 'lingoda_cron.cron_job_runner');

    $services->set('lingoda_cron.cron_job_due_handler', CronJobDueHandler::class)
        ->args([
            service('lingoda_cron.cron_job_runner'),
        ]);
    $services->alias(CronJobDueHandler::class, 'lingoda_cron.cron_job_due_handler');

    $services->set('lingoda_cron.run_cron_job_extra_help_listener', RunCronJobInputDefinitionDecorator::class)
        ->args([
            tagged_locator('lingoda_cron.cron_job'),
        ])
        ->tag('kernel.event_subscriber');
};
