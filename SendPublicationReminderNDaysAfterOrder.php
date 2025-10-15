<?php

namespace App\Console\Commands;

use App\Models\EmailGenerate;
use App\Models\Entity;
use App\Models\OrdersStatus;
use App\Models\Trigger;
use App\Services\Orders\OrderChecker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPublicationReminderNDaysAfterOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:sendPublicationReminderNDaysAfterOrder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send Publication Reminder N Days After Order';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Start Entity Publication Reminder Sending...');

        $entities = Entity::where('send_publication_reminder_at', '<=', now())
            ->whereNull('publication_reminder_processed_at')
            ->limit(1000)
            ->get()
        ;

        if ($entities->isEmpty()) {
            $this->comment(' Entity Publication Reminders not found');
            return 0;
        }

        foreach ($entities as $entity){
            if (!$entity->company_id) {
                Log::error("Entity {$entity->id} passed w/o company");

                $entity->publication_reminder_processed_at = now();
                $entity->save();

                continue;
            }

            //check maybe this entity already has publication order with done status
            if (OrderChecker::hasPublicationOrder($entity->id, OrdersStatus::DONE)) {
                //no reason to send notification
                $entity->publication_reminder_processed_at = now();
                $entity->save();

                continue;
            }

            $emailGenerate = new EmailGenerate();
            $emailGenerate->setTrigger(Trigger::PUBLICATION_REMINDER_N_DAYS_AFTER_ORDER)
                ->setWebsiteId($entity->website_id)
                ->setState($entity->state_id)
                ->setCompany($entity->company)
                ->setEntity($entity)
                ->setEmailData([
                    'entityId' => $entity->id
                ])
                ->generateEmailsByTrigger();

            $entity->publication_reminder_processed_at = now();
            $entity->save();
        }

        $cnt = $entities->count();
        $this->comment($cnt.' Entity Publication Reminders processed');

        return 0;
    }
}
