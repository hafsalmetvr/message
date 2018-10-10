<?php 

namespace App\Services\SMS\Providers;

interface SmsHandlerContract
{
    function sendsms($contactNums, $msgContent, $senderId);

    function batchDeliveryStatus($bulkId);

    function messageDeliveryStatus($messageId);
}