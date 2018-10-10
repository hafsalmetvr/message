<?php

namespace App\Jobs;


use App\SmstoMongo;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use MongoDB;

class ImportContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $data;
    public $list_id;
    public $user_id;
    public $user_default_prefix;
    public $last_batch;
    public $lists;
    public $total;
    public $count;

    /**
     * Create a new job instance.
     *
     * @param $data
     * @param $list_id
     * @param $user_default_prefix
     * @param bool $last_batch
     * @param int $total
     */
    public function __construct($data, $list_id, $user_id, $user_default_prefix, $last_batch = false, $total = 0)
    {
        $this->data = $data;
        $this->list_id = $list_id;
        $this->user_id = $user_id;
        $this->last_batch = $last_batch;
        $this->user_default_prefix = $user_default_prefix;
        $this->total = $total;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // echo count($this->data);
        // echo "\n";
        $count = 0;
        $contacts = [];
        $date = \Carbon\Carbon::now();

        foreach ($this->data as $row) {
            $contact = [];
            $phonenumber = $this->validateNumber('+'.trim($row['phone']));

            if (isset($phonenumber['valid']) && $phonenumber['valid']) {
                $count++;
                $contact['user_id'] = $this->user_id;
                $contact['list_id'] = $this->list_id;
                $contact['phone'] = $phonenumber['phonenumber'];
                $contact['firstname'] = isset($row['firstname']) ? $row['firstname'] : null;
                $contact['lastname'] = isset($row['lastname']) ? $row['lastname'] : null;
                $contact['email'] = isset($row['email']) ? $row['email'] : null;
                $contact['country_code'] = $phonenumber['country_code'];
                $contact['network_name'] = $phonenumber['network_name'];
                $contact['optedout'] = false;
                $contact['optout_date'] = null;
                $contact['created_at'] = $date;
                $contact['updated_at'] = $date;
                array_push($contacts, $contact);
            }
        }
        // echo "count: " . $count . "\n";

        $collection_name = "lists_" . $this->list_id . "_user_" . $this->user_id . "_contacts";

        $collection = (new SmstoMongo)->db()->{$collection_name};
        if (count($contacts) > 0) {
            $collection->insertMany($contacts);
        }
    }

    /**
     * validating phonenumbers
     * @param $phonenumber
     * @return array|bool|string
     */
    public function validateNumber($phonenumber)
    {
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phone = $phonenumber;

        if (preg_match("~^00\d+$~", $phone)) {
            $phone = preg_replace('/^00?/', "+", $phone);
        } else if (preg_match("~\+00\d+$~", $phone)) {
            $phone = preg_replace('/^\+00?/', "+", $phone);
        }

        try {
            $number = $phoneNumberUtil->parse($phone, null);
            $isValid = $phoneNumberUtil->getNumberType($number);

            if ($isValid != 1) {
                $phone = abs($phone);
                $number = $phoneNumberUtil->parse($phone, $this->user_default_prefix);
                $isValid = $phoneNumberUtil->getNumberType($number);
            }

            if ($isValid == 1) {
                $carrierMapper = \libphonenumber\PhoneNumberToCarrierMapper::getInstance();
                $networkName = $carrierMapper->getNameForNumber($number, "en");
                $countryCode = $number->getCountryCode();
                $nationalNumber = $number->getNationalNumber();
                $phone = '+' . $countryCode . $nationalNumber;

                return [
                    'valid' => true,
                    'country_code' => $countryCode,
                    'network_name' => $networkName,
                    'phonenumber' => $phone
                ];
            }

            return false;
        } catch (\libphonenumber\NumberParseException $e) {
            return $e->getMessage();
        }
    }
}
