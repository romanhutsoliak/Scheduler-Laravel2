<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use Illuminate\Support\Facades\Http;

class TaskExecution extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TaskExecution';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Task Execution';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tasks = Task::where('isActive', 1)
            ->where(function ($query) {
                $query->where('nextRunDateTimeUtc', date('Y-m-d H:i:00'))
                ->orWhere(function ($query) {
                    $query->where('mustBeCompleted', 0)->where('nextRunDateTimeUtc', '<', date('Y-m-d H:i:00'));
                });
            })
            ->get();

        echo $tasks->count() . "\n";
        foreach ($tasks as $task) {
            foreach ($task->userDevices as $userDevice) {
                if (!$userDevice->notificationToken)
                    continue;

                Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Authorization' => 'key='.env('FIRE_BASE_KEY')
                ])->post('https://fcm.googleapis.com/fcm/send', [
                    'notification' => [
                        'title' => trim($task->name),
                        'body' => trim($task->description),
                        'badge' => '1',
                        'sound' => 'default',
                        'showWhenInForeground' => true,
                    ],
                    'content_available' => false,
                    'data' => [
                        'redirectTo' => '/tasks/' . $task->id,
                    ],
                    'android' => [
                        'icon' => 'firebase_icon'
                    ],
                    'priority' => 'High',
                    'registration_ids' => [
                        $userDevice->notificationToken
                    ],
                ]);
            }
            if (!$task->mustBeCompleted && $task->periodType != 5) {
                $task->calculateNextRunDateTime(true);
                $task->save();
            }
        }
    }
}
