<?php

namespace App\Jobs;

use App\Lists;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


class ExtractContacts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $file;
    public $list_id;
    public $list;
    public $user;
    public $contactService;
    public $per_batch = 5000;


    /**
     * Create a new job instance.
     *
     * @param $file_path
     * @param $list_id
     * @param User $user
     */
    public function __construct($file_path, $list_id, User $user)
    {
        $this->file = storage_path('app/' . $file_path);
        $this->list_id = $list_id;
        $this->user = $user;
        $this->list = Lists::findOrFail($list_id);
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public function handle()
    {
        $this->list->is_importing = 1;
        $this->list->save();

        $total = 0;

        try {
            $data = [];
            $handle = fopen($this->file, "r");
            if ($handle) {

                while (($line = fgets($handle)) !== false) {
                    $row = explode(",", $line);

                    if (strtolower(str_replace(" ", "", $row[0])) == "phone") {
                        continue;
                    }

                    array_push($data, [
                        'phone' => !empty($row[0]) ? $row[0] : '',
                        'firstname' => !empty($row[1]) ? $row[1] : '',
                        'lastname' => !empty($row[2]) ? $row[2] : '',
                        'email' => !empty($row[3]) ? $row[3] : '',
                        'list_id' => $this->list_id
                    ]);

                    $total++; // it needs by last batch job

                    if (count($data) == $this->per_batch) {
                        ImportContacts::dispatch($data, $this->list_id, $this->user->id, $this->user->default_prefix)->onQueue('import');
                        $data = [];
                    }
                }

                fclose($handle);
            }

            ImportContacts::withChain([
                new LastBatch($this->list, $this->user, $total)
            ])->dispatch($data, $this->list_id, $this->user->id, $this->user->default_prefix, true, $total)->onQueue('import');
        } catch (\Exception $e) {
            $e->getMessage();
        }
    }

    /**
     * validating phonenumbers
     * @throws \libphonenumber\NumberParseException
     */
    public function validateNumber($phonenumber)
    {
        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();
        $phone = $phonenumber;

        if (preg_match("~^00\d+$~", $phone)) {
            $phone = preg_replace('/^00?/', "+", $phone);
        }

        try {
            $number = $phoneNumberUtil->parse($phone, null);
            $isValid = $phoneNumberUtil->getNumberType($number);

            if($isValid !=1){

                $phone = abs($phone);
                $number = $phoneNumberUtil->parse($phone, $this->user->default_prefix);
                $isValid = $phoneNumberUtil->getNumberType($number);
            }

            if ($isValid == 1) {
                $carrierMapper = \libphonenumber\PhoneNumberToCarrierMapper::getInstance();
                $networkName = $carrierMapper->getNameForNumber($number, "en");
                $countryCode = $number->getCountryCode();
                $nationaNumber = $number->getNationalNumber();
                $phone = '+'.$countryCode.$nationaNumber;

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
