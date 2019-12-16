<?php

namespace App\Jobs;

use App\Mail\ArcherRelationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

class SendArcherRelationRequest extends ArcheryOSASender implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $email;
    private $firstname;
    private $requestusername;
    private $hash;
    private $url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email, $firstname, $requestusername, $hash, $url)
    {
        $this->email = $email;
        $this->firstname = $firstname;
        $this->requestusername = $requestusername;
        $this->hash = $hash;
        $this->url = $url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->checkEmailAddress($this->email)) {
            Mail::to($this->getEmailAddress($this->email))
                ->send(new ArcherRelationRequest($this->firstname, $this->requestusername, $this->hash, $this->url));
        }

    }
}
