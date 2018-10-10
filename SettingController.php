<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Transformers\TemplateTransformer;
use Illuminate\Http\Request;
use Dingo\Api\Routing\Helpers;
use App\Models\Template;
use App\Models\TemplateField;
use App\User;
use JWTAuth;
use App\Services\ApiResponse;

class SettingController extends Controller
{
    use Helpers;
    
    protected $templates;
    protected $templatesFields;
    protected $user;
    
    public function __construct(Template $templates, TemplateField $templatesFields)
    {
        $this->templates = $templates;
        $this->templatesFields = $templatesFields;
        $this->middleware(function ($request, $next) {
            $this->user = jwt_user();
            return $next($request);
        });
    }
    
    public function index()
    {
    	$templates = $this->templates
            ->where('user_id', $this->user->id)
            ->paginate(num_rec());
        return $this->response->paginator($templates, new TemplateTransformer);
    }
    
    public function show(Request $request, $id)
    {
        $templates = $this->templates->find($id);
        
        if ($templates) {
            return $this->response->item($templates, new TemplateTransformer);
        }
        
        return $this->response->error('Record not found.', 404);
    }
    
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'messages' => 'required',
        ]);
        
        $template = $this->templates->create([
            'user_id' => $this->user->id,
            'name' => $request->name,
            'messages' => $request->messages,
        ]);
        
        // Save some templates fields if any
        $fields = [];
        
        return $this->response->created();
    }
    
    public function update(Request $request, $id)
    {
        // Name is required
        $this->validate($request, [
            'name' => 'required',
            'messages' => 'required',
        ]);
        
        $templates = $this->templates->find($id);
        
        $templates->fill($request->all());
        $templates->save();
        
        return ['updated' => true];
    }
    
    public function delete(Request $request, $id)
    {   
        $templates = $this->templates->find($id);
        
        if ($templates) {
            if ($templates->user_id != $this->user->id) {
                return $this->response->errorBadRequest();
            }
            
            $templates->delete();
            return $this->response->noContent(); // 204
        }
        
        return $this->response->errorBadRequest();
    }
}