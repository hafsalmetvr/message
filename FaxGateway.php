<?php 

namespace App\Gateways;

use Illuminate\Http\Request;
use App\Services\SMS\Calculators\RateCalculator;
use App\Models\Document;
use App\Models\FaxJob;
use App\Models\NumberBanlist;
use App\Models\FaxJobTransaction;
use App\Models\CommProvider;
use App\Models\Sandbox\Document as SandboxDocument;
use App\Models\Sandbox\FaxJob as SandboxFaxJob;
use Mail, Queue;
use App\Mail\BalanceNotEnough;
use App\Services\FileConverter;
use App\Gateways\FileGateway;
use App\Services\FaxHandlers\FaxHandler;
use App\Jobs\SendFax;
use App\Models\Setting;

class FaxGateway
{
    public $uploadSuccess = false;
    public $document = null;
    protected $documents;
    protected $fileGateway;
    public $rateCalculator;

    public function __construct(Document $documents, FileGateway $fileGateway)
    {
        $this->documents = $documents;
        $this->fileGateway = $fileGateway;
    }

    public function upload(Request $request)
    {
        $document = $this->fileGateway->upload($request);

        if (@$document->id) {
            return response()->json([
                'status' => 'success',
                'document_id' => $document->id,
                'total_pages' => $document->total_pages,
                'document' => $document,
            ], 200);
        }

        return response()->json(['status' => 'failed', 'message' => 'File is required.'], 400);
    }

    public function getDocument($request)
    {
        //if ($request->has('document_id') and $request->get('document_id') != 'null') {
        if ($request->has('document_id')) {

            if (session('api_sandbox')) {
                $this->document = SandboxDocument::find($request->input('document_id'));
            } else {
                $this->document = Document::where('user_id', auth()->user()->id)
                    ->where('id', $request->input('document_id'))
                    ->first();
            }

            if ( ! $this->document ) {
                return response()->json(['status' => 'failed', 'message' => 'File could not found'], 404);
            }
            
        } else {
            $this->document = $this->fileGateway->upload($request);

            // if ($this->uploadSuccess == false) {
            //     return $this->uploadSuccess;
            // }
        }

        $this->uploadSuccess = true;
    }

    public function setRate($faxNumber, $doc, $api = false)
    {
        $rateCalculator = new RateCalculator;
        $rateCalculator->getCost($faxNumber, $doc, $api);
        $this->rateCalculator = $rateCalculator;
    }

    public function createFaxJob($request, $faxNumber, $doc)
    {
        if (session('api_sandbox')) {
            $f = new SandboxFaxJob;
        } else {
            $f = new FaxJob;
        }

        $from = auth()->user()->tsi_number;

        // If provided with tsi_number
        if ($request->input('tsi_number')) {
            $from = $request->input('tsi_number');
        }

        if ($request->input('fax_job_id')) {
            $faxJob = $f->find($request->input('fax_job_id'));
            $faxJob->channel_event = $request->input('channel_event');
            $faxJob->rate_id = $this->rateCalculator->rate->id;
            $faxJob->resolution = $request->input('resolution', 'normal');
            $faxJob->cost = $this->rateCalculator->cost;
            $faxJob->save();
        } else {
            $faxJob = $f->create([
                'user_id' => auth()->user()->id,
                'from' => $from,
                'fax_number' => $faxNumber,
                'delete_after_success' => $request->input('delete_file', 0),
                'job_api' => $request->input('job_api', 0),
                'api_callback_url' => $request->input('api_callback_url', NULL),
                'server_fax_job_id' => $request->input('server_fax_job_id', NULL),
                'platform' => $request->input('platform'),
                'resolution' => $request->input('resolution', 'normal'),
                'from_email' => $request->input('from_email'),
                'channel_event' => $request->input('channel_event'),
                'document_id' => $doc->id,
                'rate_id' => $this->rateCalculator->rate->id,
                'cost' => $this->rateCalculator->cost,
            ]);
        }

        return $faxJob;
    }

    public function create($request, $api = true)
    {
        $numberFax = Setting::getSetting('NUMBER_FAX_BEFORE_BAN', 30);

        $faxJobsCount = auth()->user()->fax_jobs()->whereRaw('DATE(created_at) = ?', [date('Y-m-d')])->count();

        if ($faxJobsCount > $numberFax) {
            return false;
        }

        // To do:
        // We can log any API users that upload files directly to fax API request

        // Get the document either using document id or the uploaded file itself
        $this->getDocument($request);

        // if ($this->uploadSuccess == false) {
        //     return response()->json(['status' => 'failed', 'message' => 'You need to upload file.'], 400);
        // }

        if ($request->has('coversheet')) {
            $this->document = $this->fileGateway->coversheet($request, $this->document);
        }

        if ($this->uploadSuccess == true) {

            $faxNumber = $request->input('fax_number');
            $faxNumber = str_replace(' ', '', $faxNumber);
            $faxNumber = str_replace('(', '', $faxNumber);
            $faxNumber = str_replace(')', '', $faxNumber);
            if ($request->input('job_api')) {
                $faxNumber = str_replace('+', '', $faxNumber);
            }
            
            $faxNumber = str_replace('-', '', $faxNumber);
            
            // Create fax job
            if ($request->fax_numbers) {
                $faxNumbers = $request->fax_numbers;
            } else {
                $faxNumbers[] = $faxNumber;
            }
            
            // Doc resolution
            $this->document->resolution = $request->input('resolution', 'normal');

            $countFaxNumbers = count($faxNumbers);
            $i = 1;
            foreach ($faxNumbers as $faxNumber) {
                $this->setRate($faxNumber, $this->document, $request->input('job_api', 0));
                $faxJob = $this->createFaxJob($request, $faxNumber, $this->document);
                // Stop banned numbers
                $ban = NumberBanlist::ban($request->input('fax_number'), $faxJob);
                if ($ban) {
                    return response()->json($ban, 400);
                }

                // Not enough balance
                if ( ! has_enough_balance(auth()->user(), $this->rateCalculator->cost) ) {
                    if ($faxJob->from_email == 1) {
                        // Lets email the user
                        Mail::send(new BalanceNotEnough($faxJob->user, $this->rateCalculator->cost));
                        return false;
                    }
                    $data['status'] = 'failed';
                    $data['msg'] = 'Your balance is not enough to send the fax. Please re-fund your account to continue.';

                    return response()->json($data, 400);
                }

                if ($i == $countFaxNumbers) {
                    return $this->sendFax($faxJob);
                } else {
                    $this->sendFax($faxJob);
                }

                $i ++;
            }
        }
    }

    public function sendFax($faxJob)
    {
        // If its a sandbox
        if (session('api_sandbox')) {
            
            $faxJob->status = 'success';
            $faxJob->message = 'Success! Your fax has been sent!';

            // We can allow user to put their own status and message
            // for testing purposes
            if (request('status') and request('message')) {
                $faxJob->status = 'failed';
                $faxJob->message = request('message');
            }

            $faxJob->save();

            $user = auth()->user();
            $user->sandbox_cash_balance -= $faxJob->cost;
            $user->save();

            $response = [
                'status' => 'executed',
                'fax_job_id' => (integer)$faxJob->id,
                'user_cash_balance' => (double)auth()->user()->sandbox_cash_balance,
            ];

            $date = Carbon::now()->addMinutes(1);
            Queue::later($date, new SendPost($faxJob)); // App\Commands\SendPost

            return response()->json($response, 200);
        }

        // If fax job is via API
        if ($faxJob->job_api) {
            // Get and set number of retries
            $defaultProvider = $faxJob->getRetries();
            $providerUsed = CommProvider::find($defaultProvider);
        } else {
            // If not API
            $providerUsed = $faxJob->rate->provider_priorities->first();
        }
        
        if (config('app.env') == 'local') {
            $providerUsed = CommProvider::find(15);
        }
        
        $faxHandler = new FaxHandler($faxJob, $providerUsed);
        $faxHandler->comm_provider->document = $faxJob->document;
        $response = $faxHandler->sendFax();
        
        // We are going to use asterisk-flowroute as priority for
        // sending fax using API
        $faxJobTransaction = new FaxJobTransaction([
            'comm_provider_id' => $providerUsed->id,
            'transaction_id' => @$response['transaction_id'],
            'response' => json_encode($response),
        ]);

        $faxJob->fax_job_transactions()->save($faxJobTransaction);
        
        $status = 'executed';

        $response = [
            'status' => 'executed',
            'fax_job_id' => (integer)$faxJob->id,
            'user_cash_balance' => (double)$response['user_cash_balance'],
            'cost' => $faxJob->cost,
        ];

        return response()->json($response, 200);
    }

    public function faxStatus($id)
    {
        if (session('api_sandbox')) {
            $faxJob = SandboxFaxJob::find($id);
        } else {
            $faxJob = FaxJob::find($id);
        }

        if ( ! $faxJob ) {
            return response()->json(['status' => 'failed', 'message' => 'Fax job could not found'], 404);
        }

        if (session('api_sandbox')) {
            return [
                'status' => 'answered', 
                'message' => 'Success! Your fax has been sent!',
                'user_cash_balance' => auth()->user()->sandbox_cash_balance,
            ];
        }

        return [
            'status' => $faxJob->status,
            'message' => $faxJob->message,
            'user_cash_balance' => (double)auth()->user()->cash_balance,
        ];
    }

    public function cost($id)
    {
        if (session('api_sandbox')) {
            $doc = SandboxDocument::find($id);
        } else {
            $doc = Document::find($id);
        }

        if ( ! $doc ) {
            return response()->json(['status' => 'failed', 'message' => 'File could not found'], 404);
        }

        $totalCost = $this->computeCost($doc, request('fax_number'));

        return response()->json(['status' => 'success', 'cost' => floatval($totalCost)], 200);
    }

    public function history()
    {
        if (session('api_sandbox')) {
            $history = auth()->user()->sandboxFaxJobs()
                ->whereNotIn('status',['pending'])
                ->orderBy('created_at', 'DESC')
                ->paginate(request('limit', 50));
        } else {
            $history = auth()->user()->fax_jobs()
                ->whereNotIn('status',['pending'])
                ->orderBy('created_at', 'DESC')
                ->paginate(request('limit', 50));
        }

        $historyArray = [];
        
        foreach ($history as $h) {

            $historyArray[] = [
                    'id' => (int)$h->id,
                    'created' => $h->created_at,
                    'document_id' => @$h->document->id,
                    'document' => @$h->document->filename_display,
                    'recipient' => $h->fax_number,
                    'status' => $h->status,
            ];
        }        
        return response()->json(['status' => 'success', 'history' => $historyArray], 200);
    }

    public function computeCost($doc, $faxNumber)
    {
        $faxNumber = $faxNumber;
        $faxNumber = str_replace(' ', '', $faxNumber);
        $faxNumber = str_replace('+', '', $faxNumber);

        $rateCalculator = new RateCalculator;
        return $rateCalculator->getCost($faxNumber, $doc, true);
    }
   
}