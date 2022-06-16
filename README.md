# Lingoda's cron bundle

This bundle allows scheduling of jobs that will execute in a different process than the process that triggered them

## How to use

Either extend the ScheduleBaseCronJob abstract class or implement CronJobInterface. These will automatically be recognized by your Symfony application. Then there's nothing left to do than execute the command `bin/console lg:cron:trigger-due-jobs`. This will send a message on the application's messenger bus for each recognized cron job that is due. Usually, this means that a message will be put on some sort of a queue which will then be picked up by a handler which will actually run the job

When implementing a cron job, you shall not expect the cron to actually run at the scheduled times.

-   The queue might be unavailable or overloaded, and you can't predict the delay
-   The actual previous run time will be passed to your run method. Use it!
-   The job might be run manually. Or it might have been run manually previously. Even multiple times.

## Examples

Extending ScheduleBaseCronJob

```php
class MyCronJob extends ScheduleBaseCronJob
{
    protected function getSchedule(): Schedule
    {
        // this job will execute every hour at the 30th minute
        return Schedule::everyHour(30);
    }

    public function run(): void
    {
        // do the job here
    }
}
```

Implementing CronJobInterface

```php
class MyCronJob implements CronJobInterface
{
    public function run(): void
    {
        // do the job
    }

    public function shouldRun(DateTimeInterface $lastFinishTime = null): bool
    {
        // implement your own logic for determining if a job should
        // at a particular time or not
    }

    public function getLockTTL(): float
    {
        // before running, an expiring lock is acquired for each job to prevent
        // a job of the same type to run at the same time; with this method
        // the job can specify a custom expiry period in seconds for the lock

        return 30.0;
    }
}
```
