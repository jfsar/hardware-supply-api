---
paths:
  - 'app/Jobs/**'
---

# Jobs

## Never redeclare Queueable properties in job classes
Declaring public $queue / $connection in a job that also uses the Queueable trait is a hard PHP fatal ("define the same property ... incompatible composition") and kills the PHPUnit process with only "Premature end of PHP process". Assign queues at dispatch time instead: Job::dispatch($args)->onQueue('notifications').
