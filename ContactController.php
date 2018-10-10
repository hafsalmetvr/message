<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Models\Contact;
use App\Transformers\ContactTransformer;
use Illuminate\Http\Request;
use Dingo\Api\Routing\Helpers;
use App\User;
use App\ListContact;
use Illuminate\Support\Collection;
use JWTAuth;
use DB;
use Gravatar;
use App\Http\Requests\FileUpload;
use Illuminate\Validation\Rule;
use App\Http\Requests\StoreContactRequest;
use MongoDB;
use App\Services\ImportContact;
use App\Services\ContactService;
use Auth;
use App\Lists;
use App\SmstoMongo;

/**
 * @Resource("Contacts", uri="/contacts")
 */
class ContactController extends Controller
{

    use Helpers;

    protected $contacts;
    protected $user;
    public $import;
    protected $contactService;

    public function __construct(Contact $contacts, ImportContact $import, ContactService $contactService)
    {
        $this->contacts = $contacts;
        $this->import = $import;
        $this->contactService = $contactService;

        $this->middleware(function ($request, $next) {
            $this->user = jwt_user();
            return $next($request);
        });
    }

    /**
     * Show all contacts for particular users
     *
     * Get a JSON representation of all the contacts of the users.
     *
     * @Get("/")
     * @Versions({"v1"})
     * @Response(200, body={"id": 10, "username": "foo"})
     * @Parameters({
     *      @Parameter("page", type="integer", description="The page of results to view.", default=1),
     *      @Parameter("limit", type="integer", description="The amount of results per page.", default=10)
     * })
     */
    public function index(Request $request)
    {
        // We can get auth()->user() when we have ?token= in api request
        $contacts = Contact::with(['lists' => function ($query) {
            $query->select('list_id', 'name')->whereNull('lists_contacts.deleted_at');
        }])->where('user_id', $this->user->id)
            ->search($request->search)
            ->paginate(num_rec());

        return $this->response->paginator($contacts, new ContactTransformer);
        // return $this->response->item($user, new UserTransformer); // Responding With A Single Item
        // https://github.com/dingo/api/wiki/Responses
    }

    /**
     * Get avatar equivalent of the email
     *
     * Get avatar equivalent of the email
     *
     * @Get("/{email}/gravatar")
     * @Versions({"v1"})
     * @Response(200, body={"url": ""})
     */
    public function gravatar(Request $request, $email)
    {
        return response()->json(['url' => Gravatar::get($email)], 200);
    }

    public function show(Request $request, $id)
    {
        $contacts = Contact::findOrFail($id);

        return $this->response->item($contacts, new ContactTransformer);

        return $this->response->error('Record not found.', 404);
    }

    public function store(StoreContactRequest $request)
    {
        $user = Auth::user();

        $contact = new Contact;

        $contact->name = $request->name;
        $contact->surname = $request->surname;
        $contact->email = $request->email;
        $contact->phone = $request->phone;

        $contact = $contact->setContactNetwork($contact, $user);

        // We can use list_id also depending on params used
        if ($request->lists)
            $this->contactService->setListsIds($request->lists);

        $contact = $this->contactService->create($contact, $this->user);
        return $this->response->item($contact, new ContactTransformer)->setStatusCode(201);
    }

    public function update(Request $request, $id)
    {
        // Phone number is required
        $this->validate($request, [
            'phone' => 'required',
        ]);

        $contact = Contact::findOrFail($id);
        $contact->fill($request->all());
        $contact->save();

        // When updating contact theres an option to add or move the contact to a list
        // Contacts can be in multiple lists
        if ($request->has('lists')) {
            if (is_array($request->lists)) {
                foreach ($request->lists as $listId) {
                    ListContact::firstOrCreate([
                        'user_id' => $this->user->id,
                        'list_id' => $listId,
                        'contact_id' => $contact->id,
                    ]);
                }
            }
        }


        $contacts = $this->import->process($request, $this->user);

        if ($contacts) {
            return 'ok';
        }

        return 'not ok';
        // types
        // gmail, yahoo, file
        // Import upload csv, xls ods
        // Process the file
        return $this->response->created();
    }

    public function sendEmail(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        if ($contact) {
            if ($contact->user_id != $this->user->id) {
                return $this->response->errorBadRequest();
            }

            $contact->email;

            return $this->response->created(); // 201
        }
        // params email, message
    }

    public function sendSms(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        if ($contact) {
            if ($contact->user_id != $this->user->id) {
                return $this->response->errorBadRequest();
            }

            $contact->phone;

            return $this->response->created(); // 201
        }
        // params phone_number, message
    }

    public function deleteContacts(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        // Check if the delete request is multiple ids
        if ($contact) {
            if ($contact->user_id != $this->user->id) {
                return $this->response->errorBadRequest();
            }

            $contact->delete();
            return $this->response->noContent(); // 204
        }

        return $this->response->errorBadRequest();
        return $this->response->error('Record not found.', 404);
    }

    public function moveContacts(Request $request)
    {
        if (is_array($request->lists_contact_ids)) {
            foreach ($request->lists_contact_ids as $id) {
                ListContact::where('id', $id)->update(['list_id' => $request->list_id]);
            }
        }

        // Move from one list to another
        return ['moved' => true];
    }

    // todo:
    public function mergeContacts(Request $request)
    {
        // Get duplicate contacts if any
        $contacts = DB::table('contacts')
            ->select(DB::raw('name, phone, COUNT( * )'))
            ->where('user_id', 6)
            ->groupBy(DB::raw('name, phone'))
            ->having(DB::raw('phone > 1'))
            ->get();

        return ['merge' => true];
    }

    public function contacts(Request $request, $listsId)
    {
        $contacts = [];
        $paginator = [
            'count' => 0,
            'current_page' => (int)$request->get('page') ?: 1,
            'links' => [],
            'per_page' => 50,
            'total' => 0,
            'total_pages' => 0
        ];

        $query = [];

        if ($request->has('search')) {
            $query['$or'] = [
                [
                    'phone' => $request->get('search')
                ]
            ];
        }

        $collection_name = "lists_" . $listsId . "_user_" . $this->user()->id . "_contacts";
        $mongo = new SmstoMongo();
        $db = $mongo->db();
        $collection = $db->{$collection_name};
        $q = $collection->find(
            $query,
            [
                'limit' => $paginator['per_page'],
                'skip' => $paginator['per_page'] * ($paginator['current_page'] - 1)
            ]
        );

        $collectionCount = $collection->count();
        $paginator['total'] = $collectionCount;
        $paginator['total_pages'] = abs(floor($collectionCount / $paginator['per_page']));

        $count = 0;
        foreach ($q as $item) {
            $count++;
            array_push($contacts, $item);
        }
        $paginator['count'] = $count;

        return response()->json([
            'data' => $contacts,
            'meta' => [
                'pagination' => $paginator
            ],
            'request' => $request->all()
        ]);
    }

    /*
    * Function used to add lists to a contact
    * @param contactId, Request
    * @return JSON
    */
    public function addList(Request $request, $id)
    {
        if (is_array($request->lists)) {
            foreach ($request->lists as $list) {
                ListContact::firstOrCreate([
                    'user_id' => $this->user->id,
                    'list_id' => $list['id'],
                    'contact_id' => $id,
                ]);

                $list = Lists::findOrFail($list['id']);
                $list->number_contacts += 1;
                $list->save();
            }
        }
        return ['update' => true];
    }

    /*
    *Function is used to fetch contacts info
    *@param null
    *@return JSON
    */
    public function contactsInfo()
    {
        $lists = $this->user()->lists()->get();
        $count = 0;
        foreach ($lists as $list) {
            $collection_name = "lists_" . $list->id . "_user_" . $this->user()->id . "_contacts";
            $collection = (new SmstoMongo)->db()->{$collection_name};
            $count += $collection->count();
        }

        $contactsCount = $count;

        $totalLists = $this->user()->lists()->count();

        return response()->json(['totalLists' => $totalLists, 'total' => $contactsCount], 200);
    }

}
