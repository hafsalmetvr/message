<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Transformers\ListTransformer;
use Illuminate\Http\Request;
use Dingo\Api\Routing\Helpers;
use App\Lists;
use App\User;
use App\ListContact;
use JWTAuth;
use App\Http\Requests\StoreListRequest;

class ListController extends Controller {

    use Helpers;

    protected $user;

    public function __construct() {
        $this->middleware(function ($request, $next) {
            $this->user = jwt_user();
            return $next($request);
        });
    }

    public function getLists(Request $request) {
        $sortBy = null;
        $sort = ltrim($request->sort, '-');
        $type = 'ASC';
        if ($sort == 'contacts_count') {
            $sortBy = 'contacts_count';
            if (substr($request->sort, 0, 1) == '-') {
                $type = 'DESC';
            }
        }
        $lists = Lists::when($sortBy, function ($query) use ($sortBy, $type) {
                    return $query->orderBy($sortBy, $type);
                })->where('user_id', $this->user->id)
                ->search('name', $request->search)
                ->paginate(num_rec());
        return $this->response->paginator($lists, new ListTransformer);
    }

    public function show(Request $request, $id) {
        $lists = Lists::findOrFail($id);

        if ($lists) {
            return $this->response->item($lists, new ListTransformer);
        }

        return $this->response->error('Record not found.', 404);
    }

    public function store(StoreListRequest $request) {
        $list = Lists::create([
                    'user_id' => $this->user->id,
                    'name' => $request->name,
                    'description' => $request->description
        ]);

        return $this->response->item($list, new ListTransformer)->setStatusCode(201);
    }

    public function update(Request $request, $id) {
        // name is required
        $this->validate($request, [
            'name' => 'required',
        ]);

        $list = Lists::findOrFail($id);

        $list->fill($request->all());
        $list->save();

        return ['updated' => true];
    }

    public function delete(Request $request, $id) {
        $list = Lists::findOrFail($id);

        if ($list) {
            if ($list->user_id != $this->user->id) {
                return $this->response->errorBadRequest();
            }

            $list->delete();
            return $this->response->noContent(); // 204
        }

        return $this->response->errorBadRequest();
    }

    public function favorite(Request $request, $id) {
        $list = Lists::findOrFail($id);

        $list->favorite = $request->favorite;
        $list->save();

        return ['favorited' => true];
    }

    public function postContacts(Request $request, $listId) {
        // contact_id is required
        $this->validate($request, [
            'contact_id' => 'required',
        ]);

        $list = Lists::findOrFail($listId);

        ListContact::firstOrCreate(['user_id' => $this->user->id, 'list_id' => $listId, 'contact_id' => request('contact_id')]);

        return ['added' => true];
    }

    public function deleteContact(Request $request, $listId, $contactId) {
        $list = Lists::findOrFail($listId);

        ListContact::where('user_id', $this->user->id)
                ->where('list_id', $listId)
                ->where('contact_id', $contactId)
                ->delete();

        $list->number_contacts -= 1;
        $list->save();

        return ['deleted' => true];
    }

}
