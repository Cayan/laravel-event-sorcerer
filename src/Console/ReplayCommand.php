<?php

namespace Spatie\EventProjector\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\EventProjector\EventProjectionist;
use Spatie\EventProjector\Projectors\Projector;
use Spatie\EventProjector\Console\Concerns\ReplaysEvents;
use Spatie\EventProjector\Exceptions\InvalidEventHandler;

class ReplayCommand extends Command
{
    use ReplaysEvents;

    protected $signature = 'event-projector:replay
                            {--projector=* : The projector that should receive the event}';

    protected $description = 'Replay stored events';

    /** @var \Spatie\EventProjector\EventProjectionist */
    protected $eventProjectionist;

    /** @var string */
    protected $storedEventModelClass;

    public function __construct(EventProjectionist $eventProjectionist, string $storedEventModelClass)
    {
        parent::__construct();

        $this->eventProjectionist = $eventProjectionist;

        $this->storedEventModelClass = $storedEventModelClass;
    }

    public function handle()
    {
        if (! $this->commandShouldRun()) {
            return;
        }

        $projectors = $this->getProjectors();

        if ($projectors->isEmpty()) {
            $this->warn('No projectors found to replay events to...');

            return;
        }

        $this->replayEvents($projectors);
    }

    protected function getProjectors(): Collection
    {
        $onlyCallProjectors = $this->option('projector');

        $this->guardAgainstNonExistingProjectors($onlyCallProjectors);

        $allProjectors = $this->eventProjectionist->getProjectors();

        if (count($onlyCallProjectors) === 0) {
            return $allProjectors;
        }

        return $allProjectors
            ->filter(function ($projector) use ($onlyCallProjectors) {
                if (! is_string($projector)) {
                    $projector = get_class($projector);
                }

                return in_array($projector, $onlyCallProjectors);
            });
    }

    protected function guardAgainstNonExistingProjectors(array $onlyCallProjectors)
    {
        foreach ($onlyCallProjectors as $projector) {
            if (! class_exists($projector)) {
                throw InvalidEventHandler::doesNotExist($projector);
            }
        }
    }

    protected function commandShouldRun(): bool
    {
        if (count($this->option('projector') ?? []) === 0) {
            if (! $confirmed = $this->confirm('Are you sure you want to replay the events to all projectors?')) {
                $this->warn('No events replayed!');

                return false;
            }
        }

        return true;
    }
}