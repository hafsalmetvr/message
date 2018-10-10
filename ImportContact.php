<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use URL;


class ImportContact extends Mailable
{
    //use Queueable, SerializesModels;
    public $user;
    public $count;
    public $listName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user,$count,$listName)
    {
        $this->user = $user;
        $this->count = $count;
        $this->listName = $listName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    { 
        return $this->subject("Contact imported successfully  - SMS")->markdown('emails.import-contact')->with(['url' => url('/').'/app#/sms/send']);
    }
}
