<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UploadContactListRequest;
use App\Jobs\ProcessUpload;
use App\Models\User;
//use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UploadContactListController extends Controller
{
//    use Helpers;
    protected $user;
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = jwt_user();
            return $next($request);
        });
    }

    public function upload(UploadContactListRequest $request)
    {
        $file_path = $request->file_name->store('contacts');
        $list_id = $_POST['list_id'];

        $user = User::find($request->user()->id);

        $response = ProcessUpload::dispatch($file_path, $list_id, $user)->onQueue('uploadExtract');

        return response()->json([
            $response
        ]);
    }
}
